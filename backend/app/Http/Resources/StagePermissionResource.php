<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StagePermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage_id' => $this->stage_id,
            'organization_id' => $this->organization_id,
            'team_id' => $this->team_id,
            'role_id' => $this->role_id,
            'user_id' => $this->user_id,
            'access_level' => $this->access_level->value,
            'display_label' => $this->display_label,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'version' => (int) $this->version,
        ];
    }
}
