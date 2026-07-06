<?php

namespace App\Services\Workflow;

use App\Enums\GovernanceReferenceEntityType;
use App\Enums\WorkflowVersionState;
use App\Models\FieldDefinition;
use App\Models\ReferenceTable;
use App\Models\ReferenceValue;
use App\Models\WorkflowStage;
use App\Support\GovernanceExecutorSimulation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared guard for governance entities referenced by published workflow definitions.
 *
 * Pure reads — transactional callers must lock the entity row before invoking
 * destructive paths that depend on these checks.
 */
class PublishedWorkflowReferenceGuard
{
    public function __construct(private readonly StagePermissionAudience $audience) {}

    public function isReferencedByPublishedPermissions(GovernanceReferenceEntityType $entityType, int $entityId): bool
    {
        return match ($entityType) {
            GovernanceReferenceEntityType::REFERENCE_TABLE => $this->fieldDefinitionQuery($entityId, [WorkflowVersionState::PUBLISHED])->exists(),
            GovernanceReferenceEntityType::REFERENCE_VALUE => $this->isReferenceValueStructurallyProtected($entityId),
            default => $this->permissionQuery($entityType, $entityId, [WorkflowVersionState::PUBLISHED])->exists(),
        };
    }

    public function referencedByDraft(GovernanceReferenceEntityType $entityType, int $entityId): bool
    {
        if ($this->isReferencedByPublishedPermissions($entityType, $entityId)) {
            return false;
        }

        return match ($entityType) {
            GovernanceReferenceEntityType::REFERENCE_TABLE => $this->fieldDefinitionQuery($entityId, [WorkflowVersionState::DRAFT])->exists(),
            GovernanceReferenceEntityType::REFERENCE_VALUE => false,
            default => $this->permissionQuery($entityType, $entityId, [WorkflowVersionState::DRAFT])->exists(),
        };
    }

    public function wouldLeaveStageWithoutExecutor(GovernanceReferenceEntityType $entityType, int $entityId): bool
    {
        if (! in_array($entityType, [
            GovernanceReferenceEntityType::ORGANIZATION,
            GovernanceReferenceEntityType::TEAM,
            GovernanceReferenceEntityType::ROLE,
            GovernanceReferenceEntityType::USER,
        ], true)) {
            return false;
        }

        $simulation = match ($entityType) {
            GovernanceReferenceEntityType::ORGANIZATION => GovernanceExecutorSimulation::forOrganization($entityId),
            GovernanceReferenceEntityType::TEAM => GovernanceExecutorSimulation::forTeam($entityId),
            GovernanceReferenceEntityType::ROLE => GovernanceExecutorSimulation::forRole($entityId),
            GovernanceReferenceEntityType::USER => GovernanceExecutorSimulation::forUser($entityId),
            default => null,
        };

        foreach ($this->affectedPublishedStages($entityType, $entityId) as $stage) {
            if ($stage->is_final) {
                continue;
            }

            if ($this->audience->executeHolderIds($stage, $simulation) === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function impact(GovernanceReferenceEntityType $entityType, int $entityId): array
    {
        $referencedPublished = $this->isReferencedByPublishedPermissions($entityType, $entityId);
        $referencedDraftOnly = $this->referencedByDraft($entityType, $entityId);
        $wouldBreakExecutor = $this->wouldLeaveStageWithoutExecutor($entityType, $entityId);

        return [
            'entity_type' => $entityType->value,
            'entity_id' => $entityId,
            'referenced_by_published' => $referencedPublished,
            'referenced_by_draft_only' => $referencedDraftOnly,
            'would_break_executor' => $wouldBreakExecutor,
            'affected' => $this->buildAffectedEntries($entityType, $entityId),
            'warnings' => $this->buildWarnings($entityType, $entityId, $referencedDraftOnly),
        ];
    }

    /**
     * @param  array<WorkflowVersionState>  $states
     */
    private function permissionQuery(
        GovernanceReferenceEntityType $entityType,
        int $entityId,
        array $states,
    ): QueryBuilder {
        $column = $entityType->permissionColumn();
        if ($column === null) {
            return DB::table('stage_permissions')->whereRaw('1 = 0');
        }

        $stateValues = array_map(fn (WorkflowVersionState $state) => $state->value, $states);

        return DB::table('stage_permissions')
            ->join('workflow_stages', 'workflow_stages.id', '=', 'stage_permissions.stage_id')
            ->join('workflow_versions', 'workflow_versions.id', '=', 'workflow_stages.workflow_version_id')
            ->join('workflow_definitions', 'workflow_definitions.id', '=', 'workflow_versions.workflow_definition_id')
            ->where("stage_permissions.{$column}", $entityId)
            ->whereIn('workflow_versions.state', $stateValues);
    }

    /**
     * @param  array<WorkflowVersionState>  $states
     */
    private function fieldDefinitionQuery(int $referenceTableId, array $states): Builder
    {
        $stateValues = array_map(fn (WorkflowVersionState $state) => $state->value, $states);

        return FieldDefinition::query()
            ->where('reference_table_id', $referenceTableId)
            ->whereHas('workflowVersion', fn ($q) => $q->whereIn('state', $stateValues));
    }

    private function isReferenceValueStructurallyProtected(int $referenceValueId): bool
    {
        $value = ReferenceValue::query()->find($referenceValueId);
        if ($value === null) {
            return false;
        }

        if ($value->isInUse()) {
            return true;
        }

        return $this->fieldDefinitionQuery((int) $value->reference_table_id, [WorkflowVersionState::PUBLISHED])->exists();
    }

    /**
     * @return Collection<int, WorkflowStage>
     */
    private function affectedPublishedStages(GovernanceReferenceEntityType $entityType, int $entityId): Collection
    {
        if ($entityType === GovernanceReferenceEntityType::REFERENCE_TABLE) {
            $stageIds = $this->fieldDefinitionQuery($entityId, [WorkflowVersionState::PUBLISHED])
                ->join('workflow_stages', 'workflow_stages.workflow_version_id', '=', 'field_definitions.workflow_version_id')
                ->pluck('workflow_stages.id');

            return WorkflowStage::query()->whereIn('id', $stageIds)->get();
        }

        if ($entityType === GovernanceReferenceEntityType::REFERENCE_VALUE) {
            return collect();
        }

        $column = $entityType->permissionColumn();
        if ($column === null) {
            return collect();
        }

        $stageIds = DB::table('stage_permissions')
            ->join('workflow_stages', 'workflow_stages.id', '=', 'stage_permissions.stage_id')
            ->join('workflow_versions', 'workflow_versions.id', '=', 'workflow_stages.workflow_version_id')
            ->where("stage_permissions.{$column}", $entityId)
            ->where('workflow_versions.state', WorkflowVersionState::PUBLISHED->value)
            ->pluck('workflow_stages.id');

        return WorkflowStage::query()->whereIn('id', $stageIds)->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAffectedEntries(GovernanceReferenceEntityType $entityType, int $entityId): array
    {
        if ($entityType === GovernanceReferenceEntityType::REFERENCE_TABLE) {
            return $this->buildFieldDefinitionAffectedEntries($entityId);
        }

        if ($entityType === GovernanceReferenceEntityType::REFERENCE_VALUE) {
            $value = ReferenceValue::query()->with('referenceTable')->find($entityId);

            return $value === null ? [] : $this->buildFieldDefinitionAffectedEntries((int) $value->reference_table_id);
        }

        $simulation = match ($entityType) {
            GovernanceReferenceEntityType::ORGANIZATION => GovernanceExecutorSimulation::forOrganization($entityId),
            GovernanceReferenceEntityType::TEAM => GovernanceExecutorSimulation::forTeam($entityId),
            GovernanceReferenceEntityType::ROLE => GovernanceExecutorSimulation::forRole($entityId),
            GovernanceReferenceEntityType::USER => GovernanceExecutorSimulation::forUser($entityId),
            default => null,
        };

        $entries = [];
        foreach ($this->affectedPublishedStages($entityType, $entityId) as $stage) {
            $stage->loadMissing(['workflowVersion.definition']);
            $version = $stage->workflowVersion;
            $definition = $version?->definition;
            $currentCount = count($this->audience->executeHolderIds($stage));
            $afterCount = $simulation !== null
                ? count($this->audience->executeHolderIds($stage, $simulation))
                : $currentCount;

            $entries[] = [
                'workflow_definition' => $definition ? [
                    'id' => $definition->id,
                    'code' => $definition->code,
                    'name' => $definition->name,
                ] : null,
                'workflow_version' => $version ? [
                    'id' => $version->id,
                    'version_number' => $version->version_number,
                    'state' => $version->state->value,
                ] : null,
                'stage' => [
                    'id' => $stage->id,
                    'code' => $stage->code,
                    'name' => $stage->name,
                    'is_final' => $stage->is_final,
                ],
                'executor_count' => $currentCount,
                'executor_count_after' => $afterCount,
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFieldDefinitionAffectedEntries(int $referenceTableId): array
    {
        $definitions = FieldDefinition::query()
            ->with(['workflowVersion.definition'])
            ->where('reference_table_id', $referenceTableId)
            ->whereHas('workflowVersion', fn ($q) => $q->where('state', WorkflowVersionState::PUBLISHED->value))
            ->get();

        return $definitions->map(function (FieldDefinition $field) {
            $version = $field->workflowVersion;
            $definition = $version?->definition;

            return [
                'workflow_definition' => $definition ? [
                    'id' => $definition->id,
                    'code' => $definition->code,
                    'name' => $definition->name,
                ] : null,
                'workflow_version' => $version ? [
                    'id' => $version->id,
                    'version_number' => $version->version_number,
                    'state' => $version->state->value,
                ] : null,
                'field' => [
                    'id' => $field->id,
                    'key' => $field->key,
                    'label' => $field->label,
                ],
            ];
        })->values()->all();
    }

    /**
     * @return array<int, string>
     */
    private function buildWarnings(
        GovernanceReferenceEntityType $entityType,
        int $entityId,
        bool $referencedDraftOnly,
    ): array {
        $warnings = [];
        if ($referencedDraftOnly) {
            $warnings[] = 'Entity is referenced by draft workflow versions only.';
        }

        if ($entityType === GovernanceReferenceEntityType::REFERENCE_TABLE) {
            $table = ReferenceTable::query()->find($entityId);
            if ($table !== null && ! $table->is_active) {
                $warnings[] = 'Reference table is already inactive for designer binding.';
            }
        }

        if ($entityType === GovernanceReferenceEntityType::REFERENCE_VALUE) {
            $value = ReferenceValue::query()->find($entityId);
            if ($value !== null && $value->isInUse()) {
                $warnings[] = 'Reference value is linked to merchant company records.';
            }
        }

        return $warnings;
    }
}
