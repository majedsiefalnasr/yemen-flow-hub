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
            'bank_name_ar' => $this->bank?->name_ar,
            'bank_name_en' => $this->bank?->name_en,
            'status' => $this->status?->value,
            'current_owner_role' => $this->current_owner_role?->value,
            'claimed_by' => $this->claimedByUser ? [
                'id' => $this->claimedByUser->id,
                'name' => $this->claimedByUser->name,
            ] : null,
            'claimed_until' => $this->claim_expires_at?->toISOString(),
            'is_claimed' => $this->isClaimed(),
            'is_claimed_by_me' => $request->user() ? $this->isClaimedBy($request->user()) : false,
            'can_be_claimed' => $this->status === RequestStatus::SUPPORT_REVIEW_PENDING
                || ($this->status === RequestStatus::SUPPORT_REVIEW_IN_PROGRESS && $this->isClaimExpired()),
            'currency' => is_object($this->currency) ? $this->currency->value : $this->currency,
            'amount' => (float) $this->amount,
            'supplier_name' => $this->supplier_name,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
