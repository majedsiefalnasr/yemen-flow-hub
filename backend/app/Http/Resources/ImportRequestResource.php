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
            'bank_name_ar' => $this->bank?->name_ar,
            'bank_name_en' => $this->bank?->name_en,
            'merchant' => $this->merchant ? [
                'id' => $this->merchant->id,
                'name' => $this->merchant->name,
                'commercial_register' => $this->merchant->commercial_register,
            ] : null,
            'created_by' => $this->created_by,
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
            'amount' => (float) $this->amount,
            'currency' => is_object($this->currency) ? $this->currency->value : $this->currency,
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
            'voting_session_status' => $this->voting_session_status?->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
