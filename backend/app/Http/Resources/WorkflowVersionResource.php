<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_definition_id' => $this->workflow_definition_id,
            'version_number' => (int) $this->version_number,
            'state' => $this->state->value,
            'is_editable' => $this->isEditable(),
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'version' => (int) $this->version,
            'stages_count' => $this->whenCounted('stages'),
            'transitions_count' => $this->whenCounted('transitions'),
            'fields_count' => $this->whenCounted('fields'),
        ];
    }
}
