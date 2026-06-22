<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantCompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'commercial_registration_number' => $this->commercial_registration_number,
            'commercial_registration_expiry' => $this->commercial_registration_expiry?->toDateString(),
            'sector_reference_value_id' => $this->sector_reference_value_id,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
