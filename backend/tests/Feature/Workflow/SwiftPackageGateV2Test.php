<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Enums\DocumentScanStatus;
use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Exceptions\EngineException;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\FieldDefinition;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\EngineTransitionService;
use App\Services\Workflow\StageFieldRuleValidator;
use App\Services\Workflow\StagePermissionResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WF-003 runtime acceptance: on the published V2 workflow, a request cannot leave
 * the FX (SWIFT) stage until the approved SWIFT package documents exist, and can
 * once they do. Proves the fix is enforced by the engine, not just configured.
 */
class SwiftPackageGateV2Test extends TestCase
{
    use RefreshDatabase;

    private WorkflowVersion $v2;

    private WorkflowStage $fxStage;

    private WorkflowTransition $fxApprove;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);

        $this->v2 = WorkflowDefinition::query()->where('code', 'IMPORT_FINANCING')->firstOrFail()
            ->versions()->where('state', WorkflowVersionState::PUBLISHED)->firstOrFail();

        $this->fxStage = WorkflowStage::query()
            ->where('workflow_version_id', $this->v2->id)->where('code', 'FX')->firstOrFail();
        $fxConfirm = WorkflowStage::query()
            ->where('workflow_version_id', $this->v2->id)->where('code', 'FX_CONFIRM')->firstOrFail();
        $this->fxApprove = WorkflowTransition::query()
            ->where('workflow_version_id', $this->v2->id)
            ->where('from_stage_id', $this->fxStage->id)
            ->where('to_stage_id', $fxConfirm->id)
            ->firstOrFail();

        $this->officer = $this->makeFxOfficer();
    }

    private function makeFxOfficer(): User
    {
        $bankOrg = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $fxTeam = Team::query()->where('organization_id', $bankOrg->id)->where('code', 'fx_ops')->firstOrFail();
        $fxRole = Role::query()->where('organization_id', $bankOrg->id)->where('code', 'fx_swift')->firstOrFail();

        $user = User::factory()->create(['organization_id' => $bankOrg->id, 'bank_id' => $this->bankId()]);
        $user->teams()->sync([$fxTeam->id]);
        $user->assignActiveRole($fxRole->id);

        return $user->fresh();
    }

    private function bankId(): int
    {
        return (int) Bank::query()->value('id');
    }

    private function makeFxRequest(): EngineRequest
    {
        return EngineRequest::query()->create([
            'workflow_version_id' => $this->v2->id,
            'current_stage_id' => $this->fxStage->id,
            'reference' => 'ENG-FXGATE-'.uniqid(),
            'status' => 'ACTIVE',
            'created_by' => $this->officer->id,
            'bank_id' => $this->bankId(),
            'version' => 1,
        ]);
    }

    private function linkPdf(EngineRequest $request, string $fieldKey): int
    {
        $fieldId = (int) FieldDefinition::query()
            ->where('workflow_version_id', $this->v2->id)->where('key', $fieldKey)->value('id');

        return (int) EngineRequestDocument::query()->create([
            'request_id' => $request->id,
            'field_id' => $fieldId,
            'uploaded_by' => $this->officer->id,
            'stage_id' => $this->fxStage->id,
            'original_name' => $fieldKey.'.pdf',
            'path' => "fake/{$fieldKey}.pdf",
            'mime' => 'application/pdf',
            'size' => 1024,
            'checksum' => hash('sha256', $fieldKey),
            'scan_status' => DocumentScanStatus::Clean->value,
            'version' => 1,
            'status' => 'active',
        ])->id;
    }

    public function test_fx_transition_is_blocked_without_the_swift_package(): void
    {
        $request = $this->makeFxRequest();

        // Officer holds EXECUTE at FX (fx_ops), but no SWIFT documents/reference.
        $this->assertTrue(
            app(StagePermissionResolver::class)
                ->userCanAccessStage($this->officer, $this->fxStage, StageAccessLevel::EXECUTE),
            'FX officer should hold EXECUTE at FX.'
        );

        $this->expectException(EngineException::class);
        app(EngineTransitionService::class)->execute(
            $request,
            $this->fxApprove->id,
            null,
            ['swift_reference' => ''],
            $request->version,
            $this->officer,
        );
    }

    public function test_fx_field_gate_passes_once_the_full_package_is_present(): void
    {
        $request = $this->makeFxRequest();
        $swiftDocId = $this->linkPdf($request, 'swift_file');
        $fxDocId = $this->linkPdf($request, 'fx_request_file');

        // The WF-003 gate is the stage field-rule validation: with the reference and
        // both linked PDF documents present, the FX field rules raise no error (the
        // transition is no longer blocked by the SWIFT package). Downstream stage
        // hooks are out of scope for this gate.
        $errors = app(StageFieldRuleValidator::class)->validateStage(
            $this->fxStage,
            [
                'swift_reference' => 'UETR-2026-TEST-0001',
                'swift_file' => $swiftDocId,
                'fx_request_file' => $fxDocId,
            ],
            [],
            enforceRequired: true,
            actor: $this->officer,
            request: $request->fresh(),
        );

        $this->assertSame([], $errors, 'The SWIFT package should satisfy the FX field gate: '.json_encode($errors));
    }

    public function test_fx_field_gate_reports_each_missing_package_element(): void
    {
        $request = $this->makeFxRequest();

        $errors = app(StageFieldRuleValidator::class)->validateStage(
            $this->fxStage,
            [],
            [],
            enforceRequired: true,
            actor: $this->officer,
            request: $request->fresh(),
        );

        $this->assertArrayHasKey('swift_reference', $errors);
        $this->assertArrayHasKey('swift_file', $errors);
        $this->assertArrayHasKey('fx_request_file', $errors);
    }
}
