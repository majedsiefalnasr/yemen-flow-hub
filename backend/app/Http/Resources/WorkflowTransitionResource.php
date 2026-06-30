<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowTransitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_version_id' => $this->workflow_version_id,
            'from_stage_id' => $this->from_stage_id,
            'action_id' => $this->action_id,
            'to_stage_id' => $this->to_stage_id,
            'requires_comment' => (bool) $this->requires_comment,
            'confirmation_message' => $this->confirmation_message,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'version' => (int) $this->version,
        ];
    }
}
