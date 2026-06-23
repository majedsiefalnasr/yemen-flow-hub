<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entityType = $this->subject_type
            ? (str_contains($this->subject_type, '\\') ? class_basename($this->subject_type) : $this->subject_type)
            : null;

        return [
            'id' => $this->id,
            'actor' => $this->relationLoaded('user') && $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'actor_user_id' => $this->user_id,
            'actor_role' => $this->relationLoaded('actorRole') && $this->actorRole ? [
                'id' => $this->actorRole->id,
                'code' => $this->actorRole->code,
                'name' => $this->actorRole->name,
            ] : null,
            'actor_role_id' => $this->actor_role_id,
            'user_role' => $this->user_role,
            'event_code' => $this->action,
            'entity_type' => $entityType,
            'entity_id' => $this->subject_id,
            'request_id' => $this->workflow_instance_id,
            'correlation_id' => $this->correlation_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'metadata' => $this->metadata,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
