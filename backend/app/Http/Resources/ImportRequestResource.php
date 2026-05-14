<?php

namespace App\Http\Resources;

use App\Enums\RequestStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'bank_id' => $this->bank_id,
            'bank_name' => $this->bank?->name,
            'merchant' => $this->merchant ? [
                'id' => $this->merchant->id,
                'name' => $this->merchant->name,
                'commercial_register' => $this->merchant->commercial_register,
            ] : null,
            'created_by' => $this->created_by,
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
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'supplier_name' => $this->supplier_name,
            'goods_description' => $this->goods_description,
            'port_of_entry' => $this->port_of_entry,
            'notes' => $this->notes,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'bank_approved_at' => $this->bank_approved_at?->toISOString(),
            'support_approved_at' => $this->support_approved_at?->toISOString(),
            'swift_uploaded_at' => $this->swift_uploaded_at?->toISOString(),
            'executive_decided_at' => $this->executive_decided_at?->toISOString(),
            'customs_issued_at' => $this->customs_issued_at?->toISOString(),
            'revision_count' => $this->revision_count,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
