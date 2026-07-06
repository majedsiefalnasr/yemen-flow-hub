<?php

namespace App\Services\Workflow;

use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\FieldDefinition;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Filters request output (JSON data + field-linked documents) by per-stage
 * is_visible rules for the viewing context (D3-N2, D10-N3).
 *
 * Input enforcement lives in StageFieldRuleValidator; this class is the
 * symmetric read-side gate for detail, list, form-schema, and documents.
 */
class StageFieldOutputFilter
{
    /**
     * @return array<string, mixed>
     */
    public function filterRequestData(EngineRequest $request, ?User $viewer = null): array
    {
        $data = $request->data ?? [];
        if ($data === []) {
            return [];
        }

        $stage = $this->resolveStage($request);
        if ($stage === null) {
            Log::warning('Request has non-empty data but its current stage could not be resolved; hiding all fields.', [
                'engine_request_id' => $request->id,
                'current_stage_id' => $request->current_stage_id,
            ]);

            return [];
        }

        $visibleKeys = $this->visibleFieldKeysForStage($request->workflow_version_id, $stage);

        return array_intersect_key($data, array_flip($visibleKeys));
    }

    public function isFieldVisibleAtStage(WorkflowStage $stage, int $fieldId): bool
    {
        $rule = $this->rulesForStage($stage)->firstWhere('field_id', $fieldId);

        return $rule?->is_visible ?? true;
    }

    /**
     * Field-linked documents are suppressed when the owning field is hidden at
     * the request's current stage. Unlinked documents (field_id null) are not
     * gated here — general request/stage policy applies upstream.
     */
    public function canViewerAccessFieldLinkedDocument(
        EngineRequest $request,
        EngineRequestDocument $document,
        ?User $viewer = null,
    ): bool {
        if ($document->field_id === null) {
            return true;
        }

        $stage = $this->resolveStage($request);
        if ($stage === null) {
            return false;
        }

        return $this->isFieldVisibleAtStage($stage, (int) $document->field_id);
    }

    /**
     * @return list<string> field keys visible at the stage
     */
    public function visibleFieldKeysForStage(int $workflowVersionId, WorkflowStage $stage): array
    {
        $rulesByFieldId = $this->rulesForStage($stage)->keyBy('field_id');

        return FieldDefinition::query()
            ->where('workflow_version_id', $workflowVersionId)
            ->get()
            ->filter(function (FieldDefinition $field) use ($rulesByFieldId): bool {
                $rule = $rulesByFieldId->get($field->id);

                return $rule?->is_visible ?? true;
            })
            ->pluck('key')
            ->all();
    }

    /**
     * @return Collection<int, FieldDefinition>
     */
    public function visibleFieldsForStage(int $workflowVersionId, WorkflowStage $stage): Collection
    {
        $rulesByFieldId = $this->rulesForStage($stage)->keyBy('field_id');

        return FieldDefinition::query()
            ->where('workflow_version_id', $workflowVersionId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(function (FieldDefinition $field) use ($rulesByFieldId): bool {
                $rule = $rulesByFieldId->get($field->id);

                return $rule?->is_visible ?? true;
            })
            ->values();
    }

    private function resolveStage(EngineRequest $request): ?WorkflowStage
    {
        if ($request->relationLoaded('currentStage')) {
            return $request->currentStage;
        }

        return $request->currentStage()->first();
    }

    /**
     * @return Collection<int, StageFieldRule>
     */
    private function rulesForStage(WorkflowStage $stage): Collection
    {
        if ($stage->relationLoaded('stageFieldRules')) {
            return $stage->stageFieldRules;
        }

        return $stage->stageFieldRules()->get();
    }
}
