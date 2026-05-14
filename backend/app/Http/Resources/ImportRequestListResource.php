<?php

namespace App\Http\Resources;

use App\Enums\RequestStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportRequestListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'bank_id' => $this->bank_id,
            'bank_name' => $this->bank?->name,
            'status' => $this->status?->value,
            'current_owner_role' => $this->current_owner_role?->value,
            'claimed_by' => $this->claimedBy ? [
                'id' => $this->claimedBy->id,
                'name' => $this->claimedBy->name,
            ] : null,
            'claimed_until' => $this->claim_expires_at?->toISOString(),
            'is_claimed' => $this->isClaimed(),
            'is_claimed_by_me' => $request->user() ? $this->isClaimedBy($request->user()) : false,
            'can_be_claimed' => $this->status === RequestStatus::BANK_APPROVED
                || ($this->status === RequestStatus::SUPPORT_UNDER_REVIEW && $this->isClaimExpired()),
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'supplier_name' => $this->supplier_name,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
