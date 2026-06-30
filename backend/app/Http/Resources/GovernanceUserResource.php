<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GovernanceUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'mfa_enabled' => (bool) $this->mfa_enabled,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'team' => new TeamResource($this->resource->team()),
            'role' => new RoleResource($this->resource->role()),
            'bank' => $this->bank ? new BankResource($this->bank) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'version' => (int) $this->version,
        ];
    }
}
