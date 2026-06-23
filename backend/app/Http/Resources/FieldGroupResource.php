<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_version_id' => $this->workflow_version_id,
            'name' => $this->name,
            'label' => $this->label,
            'sort_order' => (int) $this->sort_order,
            'fields' => FieldDefinitionResource::collection($this->whenLoaded('fields')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'version' => (int) $this->version,
        ];
    }
}
