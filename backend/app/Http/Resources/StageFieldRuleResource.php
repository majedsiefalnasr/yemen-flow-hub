<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StageFieldRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage_id' => $this->stage_id,
            'field_id' => $this->field_id,
            'is_visible' => (bool) $this->is_visible,
            'is_editable' => (bool) $this->is_editable,
            'is_required' => (bool) $this->is_required,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'version' => (int) $this->version,
        ];
    }
}
