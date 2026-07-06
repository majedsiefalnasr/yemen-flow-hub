<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowStageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_version_id' => $this->workflow_version_id,
            'code' => $this->code,
            'semantic_role' => $this->semantic_role?->value,
            'attached_effects' => $this->attached_effects ?? [],
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => (int) $this->sort_order,
            'is_initial' => (bool) $this->is_initial,
            'is_final' => (bool) $this->is_final,
            'final_outcome' => $this->final_outcome?->value,
            'requires_claim' => (bool) $this->requires_claim,
            'sla_duration_minutes' => $this->sla_duration_minutes !== null ? (int) $this->sla_duration_minutes : null,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'version' => (int) $this->version,
        ];
    }
}
