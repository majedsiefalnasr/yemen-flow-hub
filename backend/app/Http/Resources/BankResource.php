<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'name' => $this->name,
            'name_ar' => $this->name,
            'name_en' => $this->name,
            'code' => $this->code,
            'license_number' => $this->license_number,
            'swift_code' => $this->swift_code,
            'status' => $this->status,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'version' => (int) ($this->version ?? 1),
            'admin' => $this->whenLoaded(
                'bankAdmin',
                fn () => $this->bankAdmin ? new UserResource($this->bankAdmin) : null,
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
