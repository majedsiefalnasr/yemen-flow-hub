<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Enums\WorkflowVersionState;
use App\Exceptions\StaleResourceException;
use App\Exceptions\WorkflowDesignProtectionException;
use App\Exceptions\WorkflowVersionImmutableException;
use App\Exceptions\WorkflowVersionValidationException;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Engine-core designer service for Epic 18.4. Owns workflow definitions and their
 * versions (DRAFT/PUBLISHED/ARCHIVED lifecycle). Establishes the version-scoping
 * convention that sibling stories (stages/actions/transitions/permissions/fields)
 * hang off. Published versions are immutable; clone produces a fully independent
 * DRAFT.
 */
class WorkflowDesignerService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly WorkflowVersionValidator $validator,
    ) {}

    /**
     * Create a definition and auto-create its first DRAFT version (version_number 1).
     */
    public function createDefinition(User $actor, array $attributes): WorkflowDefinition
    {
        return DB::transaction(function () use ($actor, $attributes): WorkflowDefinition {
            $definition = WorkflowDefinition::query()->create($attributes)->refresh();

            $version = $definition->versions()->create([
                'version_number' => 1,
                'state' => WorkflowVersionState::DRAFT,
            ])->refresh();

            $this->auditService->log(
                AuditAction::GOVERNANCE_CREATED,
                $actor,
                $definition,
                ['after' => $definition->toArray(), 'first_version_id' => $version->id],
            );

            return $definition;
        });
    }

    /**
     * Edit a DRAFT version. PUBLISHED/ARCHIVED versions reject edits.
     */
    public function updateVersion(
        User $actor,
        WorkflowVersion $version,
        array $attributes,
        int $expectedVersion,
    ): WorkflowVersion {
        return DB::transaction(function () use ($actor, $version, $attributes, $expectedVersion): WorkflowVersion {
            $locked = WorkflowVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);
            $this->ensureEditable($locked);

            $before = $locked->toArray();
            $locked->update([
                ...$attributes,
                'version' => $locked->version + 1,
            ]);
            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->toArray()],
            );

            return $locked->refresh();
        });
    }

    /**
     * Clone a PUBLISHED version into a new independent DRAFT with the next
     * version_number. The original is untouched. Deep-copy of stages/actions/
     * transitions/permissions/fields/rules is delegated to sibling stories as
     * those tables come online (18.4.2–18.4.7).
     */
    public function cloneVersion(User $actor, WorkflowVersion $version): WorkflowVersion
    {
        return DB::transaction(function () use ($actor, $version): WorkflowVersion {
            $source = WorkflowVersion::query()->lockForUpdate()->findOrFail($version->getKey());

            if ($source->state !== WorkflowVersionState::PUBLISHED) {
                throw new WorkflowVersionImmutableException('Only PUBLISHED versions can be cloned to a new DRAFT.');
            }

            $nextNumber = (int) WorkflowVersion::query()
                ->where('workflow_definition_id', $source->workflow_definition_id)
                ->lockForUpdate()
                ->max('version_number') + 1;

            $clone = WorkflowVersion::query()->create([
                'workflow_definition_id' => $source->workflow_definition_id,
                'version_number' => $nextNumber,
                'state' => WorkflowVersionState::DRAFT,
            ])->refresh();

            $this->auditService->log(
                AuditAction::GOVERNANCE_CREATED,
                $actor,
                $clone,
                ['cloned_from_version_id' => $source->id, 'version_number' => $nextNumber],
            );

            return $clone;
        });
    }

    /**
     * Validate a version without side effects (validate-before-publish, FR-WD9).
     *
     * @return array<int, array{code: string, target: string, message: string}>
     */
    public function validateVersion(WorkflowVersion $version): array
    {
        return $this->validator->validate($version);
    }

    /**
     * Publish a DRAFT version. Re-runs validation server-side and rejects on any
     * error. On success the version becomes the active PUBLISHED config (immutable),
     * and the previously published version of the same definition is archived.
     *
     * @throws WorkflowVersionValidationException
     */
    public function publishVersion(User $actor, WorkflowVersion $version, int $expectedVersion): WorkflowVersion
    {
        return DB::transaction(function () use ($actor, $version, $expectedVersion): WorkflowVersion {
            $locked = WorkflowVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);
            $this->ensureValidStateTransition($locked->state, WorkflowVersionState::PUBLISHED);

            $errors = $this->validator->validate($locked);
            if ($errors !== []) {
                throw new WorkflowVersionValidationException($errors);
            }

            // Archive the current active published version of the same definition.
            WorkflowVersion::query()
                ->where('workflow_definition_id', $locked->workflow_definition_id)
                ->where('state', WorkflowVersionState::PUBLISHED->value)
                ->where('id', '!=', $locked->getKey())
                ->lockForUpdate()
                ->get()
                ->each(function (WorkflowVersion $prior) use ($actor): void {
                    $before = $prior->only(['state', 'version']);
                    $prior->update([
                        'state' => WorkflowVersionState::ARCHIVED,
                        'version' => $prior->version + 1,
                    ]);
                    $this->auditService->log(
                        AuditAction::GOVERNANCE_UPDATED,
                        $actor,
                        $prior,
                        ['before' => $before, 'after' => $prior->only(['state', 'version']), 'reason' => 'superseded_by_publish'],
                    );
                });

            $before = $locked->only(['state', 'published_at', 'version']);
            $locked->update([
                'state' => WorkflowVersionState::PUBLISHED,
                'published_at' => now(),
                'version' => $locked->version + 1,
            ]);
            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->only(['state', 'published_at', 'version']), 'reason' => 'published'],
            );

            return $locked->refresh();
        });
    }

    /**
     * Archive a PUBLISHED version.
     */
    public function archiveVersion(User $actor, WorkflowVersion $version, int $expectedVersion): WorkflowVersion
    {
        return $this->transitionState($actor, $version, WorkflowVersionState::ARCHIVED, $expectedVersion);
    }

    private function transitionState(
        User $actor,
        WorkflowVersion $version,
        WorkflowVersionState $target,
        int $expectedVersion,
    ): WorkflowVersion {
        return DB::transaction(function () use ($actor, $version, $target, $expectedVersion): WorkflowVersion {
            $locked = WorkflowVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);
            $this->ensureValidStateTransition($locked->state, $target);

            $before = $locked->only(['state', 'published_at', 'version']);
            $locked->update([
                'state' => $target,
                'published_at' => $target === WorkflowVersionState::PUBLISHED ? now() : $locked->published_at,
                'version' => $locked->version + 1,
            ]);
            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->only(['state', 'published_at', 'version'])],
            );

            return $locked->refresh();
        });
    }

    /**
     * Create a stage under a DRAFT version. If this is the first stage marked
     * is_initial, any other initial stage on the version is unset to preserve the
     * single-initial invariant (final-count is enforced at validate-before-publish).
     */
    public function createStage(User $actor, WorkflowVersion $version, array $attributes): WorkflowStage
    {
        return DB::transaction(function () use ($actor, $version, $attributes): WorkflowStage {
            $lockedVersion = WorkflowVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $this->ensureEditable($lockedVersion);

            $stage = $lockedVersion->stages()->create($attributes)->refresh();

            if ($stage->is_initial) {
                $this->demoteOtherInitialStages($lockedVersion, $stage);
            }

            $this->auditService->log(
                AuditAction::GOVERNANCE_CREATED,
                $actor,
                $stage,
                ['after' => $stage->toArray()],
            );

            return $stage;
        });
    }

    public function updateStage(
        User $actor,
        WorkflowStage $stage,
        array $attributes,
        int $expectedVersion,
    ): WorkflowStage {
        return DB::transaction(function () use ($actor, $stage, $attributes, $expectedVersion): WorkflowStage {
            $locked = WorkflowStage::query()->lockForUpdate()->findOrFail($stage->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);
            $parent = WorkflowVersion::query()->lockForUpdate()->findOrFail($locked->workflow_version_id);
            $this->ensureEditable($parent);

            $before = $locked->toArray();
            $locked->update([
                ...$attributes,
                'version' => $locked->version + 1,
            ]);

            if (($attributes['is_initial'] ?? false) === true) {
                $this->demoteOtherInitialStages($parent, $locked);
            }

            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->toArray()],
            );

            return $locked->refresh();
        });
    }

    /**
     * Delete a stage. Blocked when the parent version is not DRAFT, or when the
     * stage is bound to a transition or a request (those tables arrive in later
     * stories — the binding check expands as they come online).
     */
    public function deleteStage(User $actor, WorkflowStage $stage): void
    {
        $blocked = DB::transaction(function () use ($actor, $stage): ?WorkflowStage {
            $locked = WorkflowStage::query()->lockForUpdate()->findOrFail($stage->getKey());
            $parent = WorkflowVersion::query()->lockForUpdate()->findOrFail($locked->workflow_version_id);
            $this->ensureEditable($parent);

            if ($this->stageIsBound($locked)) {
                return $locked;
            }

            $before = $locked->toArray();
            $locked->delete();
            $this->auditService->log(
                AuditAction::GOVERNANCE_DELETED,
                $actor,
                $locked,
                ['before' => $before],
            );

            return null;
        });

        if ($blocked !== null) {
            $this->auditService->log(
                AuditAction::AUTHORIZATION_FAILURE,
                $actor,
                $blocked,
                ['reason' => 'workflow_stage_bound'],
            );
            throw new WorkflowDesignProtectionException(
                'WORKFLOW_STAGE_BOUND',
                'Stage cannot be deleted while it is bound to a transition or request.',
            );
        }
    }

    /**
     * Create a transition on a DRAFT version. The from/to stages must belong to the
     * version; self-stage (from == to) is allowed. The action must be active and in
     * the global catalog. Duplicate (from_stage_id, action_id) is caught by the DB
     * unique constraint and surfaced as a 422 by the Form Request.
     */
    public function createTransition(User $actor, WorkflowVersion $version, array $attributes): WorkflowTransition
    {
        return DB::transaction(function () use ($actor, $version, $attributes): WorkflowTransition {
            $lockedVersion = WorkflowVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $this->ensureEditable($lockedVersion);

            $transition = $lockedVersion->transitions()->create($attributes)->refresh();
            $this->auditService->log(
                AuditAction::GOVERNANCE_CREATED,
                $actor,
                $transition,
                ['after' => $transition->toArray()],
            );

            return $transition;
        });
    }

    public function updateTransition(
        User $actor,
        WorkflowTransition $transition,
        array $attributes,
        int $expectedVersion,
    ): WorkflowTransition {
        return DB::transaction(function () use ($actor, $transition, $attributes, $expectedVersion): WorkflowTransition {
            $locked = WorkflowTransition::query()->lockForUpdate()->findOrFail($transition->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);
            $parent = WorkflowVersion::query()->lockForUpdate()->findOrFail($locked->workflow_version_id);
            $this->ensureEditable($parent);

            $before = $locked->toArray();
            $locked->update([
                ...$attributes,
                'version' => $locked->version + 1,
            ]);
            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->toArray()],
            );

            return $locked->refresh();
        });
    }

    public function deleteTransition(User $actor, WorkflowTransition $transition): void
    {
        DB::transaction(function () use ($actor, $transition): void {
            $locked = WorkflowTransition::query()->lockForUpdate()->findOrFail($transition->getKey());
            $parent = WorkflowVersion::query()->lockForUpdate()->findOrFail($locked->workflow_version_id);
            $this->ensureEditable($parent);

            $before = $locked->toArray();
            $locked->delete();
            $this->auditService->log(
                AuditAction::GOVERNANCE_DELETED,
                $actor,
                $locked,
                ['before' => $before],
            );
        });
    }

    /**
     * Create a stage_permissions row on a stage whose version is DRAFT. The row's
     * org/team/role/user references are validated at the Form Request layer; this
     * method enforces the DRAFT gate and audits.
     */
    public function createStagePermission(User $actor, WorkflowStage $stage, array $attributes): StagePermission
    {
        return DB::transaction(function () use ($actor, $stage, $attributes): StagePermission {
            $this->ensureStageVersionEditable($stage);

            $permission = $stage->stagePermissions()->create($attributes)->refresh();
            $this->auditService->log(
                AuditAction::GOVERNANCE_CREATED,
                $actor,
                $permission,
                ['after' => $permission->toArray()],
            );

            return $permission;
        });
    }

    public function updateStagePermission(
        User $actor,
        StagePermission $permission,
        array $attributes,
        int $expectedVersion,
    ): StagePermission {
        return DB::transaction(function () use ($actor, $permission, $attributes, $expectedVersion): StagePermission {
            $locked = StagePermission::query()->lockForUpdate()->findOrFail($permission->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);
            $this->ensureStageVersionEditable($locked->stage);

            $before = $locked->toArray();
            $locked->update([
                ...$attributes,
                'version' => $locked->version + 1,
            ]);
            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->toArray()],
            );

            return $locked->refresh();
        });
    }

    public function deleteStagePermission(User $actor, StagePermission $permission): void
    {
        DB::transaction(function () use ($actor, $permission): void {
            $locked = StagePermission::query()->lockForUpdate()->findOrFail($permission->getKey());
            $this->ensureStageVersionEditable($locked->stage);

            $before = $locked->toArray();
            $locked->delete();
            $this->auditService->log(
                AuditAction::GOVERNANCE_DELETED,
                $actor,
                $locked,
                ['before' => $before],
            );
        });
    }

    private function ensureStageVersionEditable(WorkflowStage $stage): void
    {
        $version = WorkflowVersion::query()->lockForUpdate()->findOrFail($stage->workflow_version_id);
        $this->ensureEditable($version);
    }

    private function demoteOtherInitialStages(WorkflowVersion $version, WorkflowStage $keep): void
    {
        $version->stages()
            ->where('id', '!=', $keep->getKey())
            ->where('is_initial', true)
            ->update(['is_initial' => false]);
    }

    /**
     * Whether the stage is referenced by a transition or a request. The transition
     * and request tables arrive in 18.4.4 and 18.5.x; until then nothing binds a
     * stage, so this is false. Expanded as those relations land.
     */
    private function stageIsBound(WorkflowStage $stage): bool
    {
        if (DB::getSchemaBuilder()->hasTable('workflow_transitions')) {
            $bound = DB::table('workflow_transitions')
                ->where('from_stage_id', $stage->getKey())
                ->orWhere('to_stage_id', $stage->getKey())
                ->exists();
            if ($bound) {
                return true;
            }
        }

        if (DB::getSchemaBuilder()->hasTable('requests')) {
            return DB::table('requests')->where('current_stage_id', $stage->getKey())->exists();
        }

        return false;
    }

    private function ensureEditable(WorkflowVersion $version): void
    {
        if (! $version->isEditable()) {
            throw new WorkflowVersionImmutableException(
                'This workflow version is '.$version->state->value.' and cannot be edited.',
            );
        }
    }

    private function ensureValidStateTransition(WorkflowVersionState $from, WorkflowVersionState $to): void
    {
        $allowed = match ($to) {
            WorkflowVersionState::PUBLISHED => $from === WorkflowVersionState::DRAFT,
            WorkflowVersionState::ARCHIVED => $from === WorkflowVersionState::PUBLISHED,
            WorkflowVersionState::DRAFT => false,
        };

        if (! $allowed) {
            throw new WorkflowVersionImmutableException(
                'Cannot transition workflow version from '.$from->value.' to '.$to->value.'.',
            );
        }
    }

    private function ensureCurrentVersion(int $actualVersion, int $expectedVersion): void
    {
        if ($actualVersion !== $expectedVersion) {
            throw new StaleResourceException;
        }
    }
}
