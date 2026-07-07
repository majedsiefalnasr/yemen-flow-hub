<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Exceptions\StaleResourceException;
use App\Exceptions\WorkflowDesignProtectionException;
use App\Exceptions\WorkflowVersionImmutableException;
use App\Models\FieldDefinition;
use App\Models\FieldGroup;
use App\Models\StageFieldRule;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Designer service for field groups (tabs) and typed field definitions within a
 * workflow version (FR-WD6). Mirrors the version-scoped DRAFT-only convention used
 * by stages/transitions. `key` is immutable once the version is used (PUBLISHED or
 * has request instances); default + in-use fields are delete-protected — changes
 * happen via a new version.
 */
class FieldDesignerService
{
    public function __construct(private readonly AuditService $auditService) {}

    // ── Field groups ────────────────────────────────────────────────────────

    public function createGroup(User $actor, WorkflowVersion $version, array $attributes): FieldGroup
    {
        return DB::transaction(function () use ($actor, $version, $attributes): FieldGroup {
            $locked = WorkflowVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $this->ensureDraft($locked);

            $group = $locked->fieldGroups()->create($attributes)->refresh();
            $this->audit(AuditAction::GOVERNANCE_CREATED, $actor, $group, ['after' => $group->toArray()]);

            return $group;
        });
    }

    public function updateGroup(User $actor, FieldGroup $group, array $attributes, int $expectedVersion): FieldGroup
    {
        return DB::transaction(function () use ($actor, $group, $attributes, $expectedVersion): FieldGroup {
            $locked = FieldGroup::query()->lockForUpdate()->findOrFail($group->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);
            $this->ensureDraftFor($locked->workflow_version_id);

            $before = $locked->toArray();
            $locked->update([...$attributes, 'version' => $locked->version + 1]);
            $this->audit(AuditAction::GOVERNANCE_UPDATED, $actor, $locked, ['before' => $before, 'after' => $locked->toArray()]);

            return $locked->refresh();
        });
    }

    public function deleteGroup(User $actor, FieldGroup $group): void
    {
        $blocked = DB::transaction(function () use ($actor, $group): bool {
            $locked = FieldGroup::query()->lockForUpdate()->findOrFail($group->getKey());
            $this->ensureDraftFor($locked->workflow_version_id);

            if ($locked->fields()->exists()) {
                return true;
            }

            $before = $locked->toArray();
            $locked->delete();
            $this->audit(AuditAction::GOVERNANCE_DELETED, $actor, $locked, ['before' => $before]);

            return false;
        });

        if ($blocked) {
            throw new WorkflowDesignProtectionException(
                'FIELD_GROUP_NOT_EMPTY',
                'Field group cannot be deleted while it still contains fields.',
            );
        }
    }

    // ── Field definitions ───────────────────────────────────────────────────

    public function createField(User $actor, WorkflowVersion $version, array $attributes): FieldDefinition
    {
        return DB::transaction(function () use ($actor, $version, $attributes): FieldDefinition {
            $locked = WorkflowVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $this->ensureDraft($locked);

            $field = $locked->fieldDefinitions()->create($attributes)->refresh();
            $this->audit(AuditAction::GOVERNANCE_CREATED, $actor, $field, ['after' => $field->toArray()]);

            return $field;
        });
    }

    public function updateField(User $actor, FieldDefinition $field, array $attributes, int $expectedVersion): FieldDefinition
    {
        return DB::transaction(function () use ($actor, $field, $attributes, $expectedVersion): FieldDefinition {
            $locked = FieldDefinition::query()->lockForUpdate()->findOrFail($field->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);
            $this->ensureDraftFor($locked->workflow_version_id);

            // key is immutable once the version is used; the Form Request also rejects
            // a key change, but enforce defensively here too.
            unset($attributes['key']);

            $before = $locked->toArray();
            $locked->update([...$attributes, 'version' => $locked->version + 1]);
            $this->audit(AuditAction::GOVERNANCE_UPDATED, $actor, $locked, ['before' => $before, 'after' => $locked->toArray()]);

            return $locked->refresh();
        });
    }

    public function deleteField(User $actor, FieldDefinition $field): void
    {
        $blockReason = DB::transaction(function () use ($actor, $field): ?string {
            $locked = FieldDefinition::query()->lockForUpdate()->findOrFail($field->getKey());
            $this->ensureDraftFor($locked->workflow_version_id);

            if ($locked->isProtected()) {
                return 'field_definition_system_protected';
            }
            if ($this->fieldIsUsed($locked)) {
                return 'field_definition_in_use';
            }

            $before = $locked->toArray();
            $locked->delete();
            $this->audit(AuditAction::GOVERNANCE_DELETED, $actor, $locked, ['before' => $before]);

            return null;
        });

        if ($blockReason !== null) {
            throw new WorkflowDesignProtectionException(
                'FIELD_DEFINITION_PROTECTED',
                'Field cannot be deleted because it is a default field or is used by a request. Change it in a new version.',
            );
        }
    }

    // ── Per-stage field rules (FR-WD7) ──────────────────────────────────────

    /**
     * Upsert the rule for a (stage, field) pair on a DRAFT version. The stage and
     * field must belong to the same version (enforced at the Form Request layer).
     */
    public function setStageFieldRule(User $actor, WorkflowStage $stage, array $attributes): StageFieldRule
    {
        return DB::transaction(function () use ($actor, $stage, $attributes): StageFieldRule {
            $this->ensureDraftFor($stage->workflow_version_id);

            $existing = StageFieldRule::query()
                ->where('stage_id', $stage->getKey())
                ->where('field_id', $attributes['field_id'])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $before = $existing->toArray();
                $existing->update([
                    'is_visible' => $attributes['is_visible'] ?? $existing->is_visible,
                    'is_editable' => $attributes['is_editable'] ?? $existing->is_editable,
                    'is_required' => $attributes['is_required'] ?? $existing->is_required,
                    'version' => $existing->version + 1,
                ]);
                $this->audit(AuditAction::GOVERNANCE_UPDATED, $actor, $existing, ['before' => $before, 'after' => $existing->toArray()]);

                return $existing->refresh();
            }

            $rule = $stage->stageFieldRules()->create([
                'field_id' => $attributes['field_id'],
                'is_visible' => $attributes['is_visible'] ?? true,
                'is_editable' => $attributes['is_editable'] ?? true,
                'is_required' => $attributes['is_required'] ?? false,
            ])->refresh();
            $this->audit(AuditAction::GOVERNANCE_CREATED, $actor, $rule, ['after' => $rule->toArray()]);

            return $rule;
        });
    }

    public function deleteStageFieldRule(User $actor, StageFieldRule $rule): void
    {
        DB::transaction(function () use ($actor, $rule): void {
            $locked = StageFieldRule::query()->lockForUpdate()->findOrFail($rule->getKey());
            $this->ensureDraftFor($locked->stage->workflow_version_id);

            $before = $locked->toArray();
            $locked->delete();
            $this->audit(AuditAction::GOVERNANCE_DELETED, $actor, $locked, ['before' => $before]);
        });
    }

    /**
     * A field is "used" once an engine request on this workflow version exists.
     */
    private function fieldIsUsed(FieldDefinition $field): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('engine_requests')) {
            return false;
        }

        return DB::table('engine_requests')
            ->where('workflow_version_id', $field->workflow_version_id)
            ->exists();
    }

    private function ensureDraft(WorkflowVersion $version): void
    {
        if (! $version->isEditable()) {
            throw new WorkflowVersionImmutableException(
                'This workflow version is '.$version->state->value.' and its fields cannot be changed.',
            );
        }
    }

    private function ensureDraftFor(int $versionId): void
    {
        $version = WorkflowVersion::query()->lockForUpdate()->findOrFail($versionId);
        $this->ensureDraft($version);
    }

    private function ensureCurrentVersion(int $actualVersion, int $expectedVersion): void
    {
        if ($actualVersion !== $expectedVersion) {
            throw new StaleResourceException;
        }
    }

    private function audit(AuditAction $action, User $actor, $subject, array $metadata): void
    {
        $this->auditService->log($action, $actor, $subject, $metadata);
    }
}
