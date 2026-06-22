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
            'tax_number' => $this->tax_number,
            'tax_card_expiry' => $this->tax_card_expiry?->toDateString(),
            'address' => $this->address,
            'phone' => $this->phone,
            'status' => $this->status,
            'version' => $this->version,
            'transaction_count' => $this->import_requests_count ?? 0,
            'owners' => MerchantOwnerResource::collection($this->whenLoaded('owners')),
            'companies' => MerchantCompanyResource::collection($this->whenLoaded('companies')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
