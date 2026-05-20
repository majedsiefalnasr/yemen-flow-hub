<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_id' => $this->bank_id,
            'bank_name' => $this->bank?->name,
            'name' => $this->name,
            'commercial_register' => $this->commercial_register,
            'tax_number' => $this->tax_number,
            'national_id' => $this->national_id,
            'owner_name' => $this->owner_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'business_type' => $this->business_type,
            'is_active' => (bool) $this->is_active,
            'transaction_count' => $this->import_requests_count ?? null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
