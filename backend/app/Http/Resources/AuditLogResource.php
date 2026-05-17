<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->relationLoaded('user') && $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user->role?->value,
            ] : null,
            'user_id' => $this->user_id,
            'user_role' => $this->user_role,
            'action' => $this->action,
            'entity_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'entity_id' => $this->subject_id,
            'from_status' => $this->metadata['from_status'] ?? null,
            'to_status' => $this->metadata['to_status'] ?? null,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
