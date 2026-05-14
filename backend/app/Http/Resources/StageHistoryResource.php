<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StageHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_id' => $this->request_id,
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status?->value,
            'from_owner_role' => $this->from_owner_role?->value,
            'to_owner_role' => $this->to_owner_role?->value,
            'actor_id' => $this->actor_id,
            'actor_role' => $this->actor_role?->value,
            'action' => $this->action,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
