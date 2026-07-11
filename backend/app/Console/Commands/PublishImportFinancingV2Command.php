<?php

namespace App\Console\Commands;

use App\Enums\FieldType;
use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Models\FieldDefinition;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\FieldDesignerService;
use App\Services\Workflow\WorkflowDesignerService;
use App\Services\Workflow\WorkflowVersionValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Build and publish IMPORT_FINANCING V2 through the real Workflow Designer
 * lifecycle (Phase B, B1–B3). Clones the published V1 into a DRAFT, applies the
 * approved corrections through the designer services, validates, and publishes —
 * never a raw insert or direct status update. V1 is left untouched.
 *
 * Corrections applied:
 *  - B1: reasoned/confirmed reject transitions + explicit Support self-loop.
 *  - B2: FINAL stage EXECUTE moves from committee_manager to committee_director.
 *  - B3: SWIFT package fields (swift_reference, swift_file, fx_request_file)
 *        required to leave FX; visible read-only downstream.
 *
 * B4 (semantic_role) is deferred to Phase D; V2 publishes validator-clean using
 * the runtime stage-code compatibility fallback.
 */
class PublishImportFinancingV2Command extends Command
{
    protected $signature = 'workflow:publish-import-financing-v2
        {--definition=IMPORT_FINANCING : Workflow definition code}
        {--source-version=1 : The published source version number to clone from}
        {--publish : Actually publish. WITHOUT this flag the command is a safe dry-run that mutates nothing.}';

    protected $description = 'Build a corrected IMPORT_FINANCING V2 through the designer lifecycle (Phase B). Dry-run by default; pass --publish to persist.';

    /** Reject transitions that must require a comment + confirmation message (from → to). */
    private const REJECT_TRANSITIONS = [
        ['INTERNAL', 'CREATE', 'إعادة الطلب إلى موظف الإدخال للتصحيح.'],
        ['EXEC', 'CLOSED_REJECTED', 'رفض الطلب نهائياً من اللجنة التنفيذية.'],
        ['FX_CONFIRM', 'FX', 'إرجاع الطلب إلى مرحلة السويفت لإعادة المعالجة.'],
        ['FINAL', 'FX_CONFIRM', 'إرجاع الطلب إلى مرحلة تأكيد الصرف.'],
    ];

    /** [key, label, type, required-to-leave-FX] — canonical SWIFT package (M1 §5). */
    private const SWIFT_FIELDS = [
        ['swift_reference', 'رقم مرجع السويفت (UETR / Message Reference)', FieldType::TEXT],
        ['swift_file', 'وثيقة السويفت (MT103 / MT202)', FieldType::FILE],
        ['fx_request_file', 'طلب تأكيد المصارفة الخارجية', FieldType::FILE],
    ];

    public function handle(
        WorkflowDesignerService $designer,
        FieldDesignerService $fieldDesigner,
        WorkflowVersionValidator $validator,
    ): int {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            $this->error('This command is restricted to local/staging/testing environments.');

            return self::FAILURE;
        }

        $definitionCode = (string) $this->option('definition');
        $sourceVersionNumber = (int) $this->option('source-version');
        // Safe by default: only --publish persists. Interactive/Tinker invocations
        // and forgotten flags therefore mutate nothing.
        $dryRun = ! (bool) $this->option('publish');

        // Announce the target and intended mutations before doing anything.
        $this->info(sprintf(
            'env=%s · definition=%s · source=V%d · mode=%s',
            app()->environment(),
            $definitionCode,
            $sourceVersionNumber,
            $dryRun ? 'DRY-RUN (no mutation)' : 'PUBLISH',
        ));
        $this->line('Intended: clone V'.$sourceVersionNumber.' → DRAFT → apply B1 (reasoned rejects + self-loop), B2 (FINAL→committee_director), B3 (SWIFT gate) → validate'.($dryRun ? ' (then roll back).' : ' → publish → archive prior.'));

        $definition = WorkflowDefinition::query()->where('code', $definitionCode)->first();
        if ($definition === null) {
            $this->error("Workflow definition '{$definitionCode}' not found.");

            return self::FAILURE;
        }

        // Idempotency: if a corrected version beyond the source is already
        // published, do nothing. Checked before the strict source lookup because a
        // successful prior run archives the source version.
        $existingPublishedV2 = $definition->versions()
            ->where('version_number', '>', $sourceVersionNumber)
            ->where('state', WorkflowVersionState::PUBLISHED)
            ->first();
        if ($existingPublishedV2 !== null) {
            $this->warn("A published version {$existingPublishedV2->version_number} already exists; nothing to do.");

            return self::SUCCESS;
        }

        $source = $definition->versions()
            ->where('version_number', $sourceVersionNumber)
            ->where('state', WorkflowVersionState::PUBLISHED)
            ->first();
        if ($source === null) {
            $this->error("Published source version {$sourceVersionNumber} of '{$definitionCode}' not found.");

            return self::FAILURE;
        }

        $actor = $this->resolveActor();
        if ($actor === null) {
            $this->error('No system_admin actor available to attribute designer actions.');

            return self::FAILURE;
        }

        try {
            return DB::transaction(function () use ($designer, $fieldDesigner, $validator, $source, $actor, $dryRun): int {
                $draft = $designer->cloneVersion($actor, $source);
                $this->info("Cloned V{$source->version_number} → DRAFT V{$draft->version_number} (id {$draft->id}).");

                $this->applyRejectSemantics($designer, $draft, $actor);
                $this->applyFinalOwnership($designer, $draft, $actor);
                $this->applySwiftPackage($fieldDesigner, $draft, $actor);

                $draft->refresh();
                $errors = $validator->validate($draft);
                if ($errors !== []) {
                    foreach ($errors as $error) {
                        $this->error("[{$error['code']}] {$error['target']}: {$error['message']}");
                    }
                    // Roll back the whole DRAFT — V1 untouched, nothing partially published.
                    throw new PublishAbortedException('Validation failed; DRAFT discarded, V1 untouched.');
                }
                $this->info('Validation passed (0 errors).');

                if ($dryRun) {
                    $this->info('Dry run: rolling back the DRAFT without publishing.');
                    throw new PublishAbortedException('DRY_RUN');
                }

                $published = $designer->publishVersion($actor, $draft, $draft->version);
                $this->info("Published V{$published->version_number} (id {$published->id}); prior published version archived.");

                return self::SUCCESS;
            });
        } catch (PublishAbortedException $e) {
            if ($e->getMessage() === 'DRY_RUN') {
                $this->info('Dry run complete. No changes persisted.');

                return self::SUCCESS;
            }
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('V2 build failed; V1 untouched: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function applyRejectSemantics(WorkflowDesignerService $designer, WorkflowVersion $draft, User $actor): void
    {
        $stages = $this->stageMap($draft);

        foreach (self::REJECT_TRANSITIONS as [$from, $to, $message]) {
            $transition = WorkflowTransition::query()
                ->where('workflow_version_id', $draft->id)
                ->where('from_stage_id', $stages[$from])
                ->where('to_stage_id', $stages[$to])
                ->first();
            if ($transition === null) {
                continue;
            }
            $designer->updateTransition($actor, $transition, [
                'requires_comment' => true,
                'confirmation_message' => $message,
            ], $transition->version);
        }

        // Mark the Support self-loop (SUPPORT → SUPPORT) explicitly intentional.
        $selfLoop = WorkflowTransition::query()
            ->where('workflow_version_id', $draft->id)
            ->where('from_stage_id', $stages['SUPPORT'])
            ->where('to_stage_id', $stages['SUPPORT'])
            ->first();
        if ($selfLoop !== null && ! $selfLoop->is_self_loop) {
            $designer->updateTransition($actor, $selfLoop, ['is_self_loop' => true], $selfLoop->version);
        }

        $this->info('B1: reject transitions require comment + confirmation; Support self-loop marked.');
    }

    private function applyFinalOwnership(WorkflowDesignerService $designer, WorkflowVersion $draft, User $actor): void
    {
        $stages = $this->stageMap($draft);
        $directorRole = Role::query()
            ->where('code', 'committee_director')
            ->whereHas('organization', fn ($q) => $q->where('code', 'national_committee'))
            ->firstOrFail();

        // FINAL EXECUTE row currently bound to committee_manager → committee_director.
        $finalExecute = StagePermission::query()
            ->where('stage_id', $stages['FINAL'])
            ->where('access_level', StageAccessLevel::EXECUTE)
            ->first();
        if ($finalExecute !== null && (int) $finalExecute->role_id !== (int) $directorRole->id) {
            $designer->updateStagePermission($actor, $finalExecute, [
                'role_id' => $directorRole->id,
                'display_label' => 'الاعتماد النهائي (مدير اللجنة)',
            ], $finalExecute->version);
        }

        $this->info('B2: FINAL stage EXECUTE assigned to committee_director; EXEC keeps committee_manager.');
    }

    private function applySwiftPackage(FieldDesignerService $fieldDesigner, WorkflowVersion $draft, User $actor): void
    {
        $stages = $this->stageMap($draft);
        $downstream = ['FX_CONFIRM', 'FINAL', 'CLOSED_COMPLETED', 'CLOSED_REJECTED'];

        // Reuse the "docs" field group so the SWIFT fields sit with other documents.
        $docsGroupId = DB::table('field_groups')
            ->where('workflow_version_id', $draft->id)
            ->where('name', 'docs')
            ->value('id');

        foreach (self::SWIFT_FIELDS as $index => [$key, $label, $type]) {
            $field = FieldDefinition::query()
                ->where('workflow_version_id', $draft->id)
                ->where('key', $key)
                ->first();
            if ($field === null) {
                $field = $fieldDesigner->createField($actor, $draft, [
                    'field_group_id' => $docsGroupId,
                    'key' => $key,
                    'label' => $label,
                    'type' => $type->value,
                    'is_system' => true,
                    'sort_order' => 100 + $index,
                ]);
            }

            $fxStage = WorkflowStage::query()->find($stages['FX']);
            // Required + editable to leave FX.
            $fieldDesigner->setStageFieldRule($actor, $fxStage, [
                'field_id' => $field->id,
                'is_visible' => true,
                'is_editable' => true,
                'is_required' => true,
            ]);

            // Visible, read-only, not required at downstream stages.
            foreach ($downstream as $stageCode) {
                $stage = WorkflowStage::query()->find($stages[$stageCode]);
                $fieldDesigner->setStageFieldRule($actor, $stage, [
                    'field_id' => $field->id,
                    'is_visible' => true,
                    'is_editable' => false,
                    'is_required' => false,
                ]);
            }
        }

        $this->info('B3: SWIFT package (swift_reference, swift_file, fx_request_file) required to leave FX; read-only downstream.');
    }

    /**
     * @return array<string, int> stage code → id for the draft.
     */
    private function stageMap(WorkflowVersion $draft): array
    {
        return WorkflowStage::query()
            ->where('workflow_version_id', $draft->id)
            ->pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function resolveActor(): ?User
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('code', 'system_admin'))
            ->first();
    }
}
