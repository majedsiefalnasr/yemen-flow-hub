<?php

namespace App\Http\Resources;

use App\Models\EngineRequest;
use App\Support\EngineRequestReadModel;
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
            'user' => $this->relationLoaded('user') && $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user_role,
            ] : null,
            'user_id' => $this->user_id,
            'user_role' => $this->user_role,
            'action' => $this->action,
            'entity_type' => $entityType,
            'entity_id' => $this->subject_id,
            'entity_reference' => $this->entityReference(),
            'from_status' => $this->metadata['from_status'] ?? null,
            'to_status' => $this->metadata['to_status'] ?? null,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function entityReference(): ?string
    {
        if (! $this->subject_id) {
            return null;
        }

        // Engine request subject
        if (in_array($this->subject_type, [EngineRequest::class, 'engine_request'], true)) {
            $engineRequest = EngineRequest::query()
                ->select(['id', 'reference'])
                ->find($this->subject_id);

            return EngineRequestReadModel::reference($engineRequest, $this->subject_id);
        }

        return null;
    }
}
