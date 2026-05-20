<?php

namespace App\Http\Resources;

use App\Enums\RequestStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

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
            'created_by_user' => $this->whenLoaded('creator', fn () => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null),
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
            'goods_type' => $this->goods_type,
            'payment_terms' => $this->payment_terms,
            'due_date' => $this->due_date,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date,
            'origin_country' => $this->origin_country,
            'arrival_port' => $this->arrival_port,
            'shipping_port' => $this->shipping_port,
            'customs_office' => $this->customs_office,
            'bl_number' => $this->bl_number,
            'submitted_by' => $this->submitted_by,
            'submitted_by_user' => $this->whenLoaded('submittedBy', fn () => $this->submittedBy ? ['id' => $this->submittedBy->id, 'name' => $this->submittedBy->name] : null),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_by_user' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy ? ['id' => $this->reviewedBy->id, 'name' => $this->reviewedBy->name] : null),
            'approved_by' => $this->approved_by,
            'approved_by_user' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? ['id' => $this->approvedBy->id, 'name' => $this->approvedBy->name] : null),
            'rejected_by' => $this->rejected_by,
            'rejected_by_user' => $this->whenLoaded('rejectedBy', fn () => $this->rejectedBy ? ['id' => $this->rejectedBy->id, 'name' => $this->rejectedBy->name] : null),
            'resubmitted_by' => $this->resubmitted_by,
            'resubmitted_by_user' => $this->whenLoaded('resubmittedBy', fn () => $this->resubmittedBy ? ['id' => $this->resubmittedBy->id, 'name' => $this->resubmittedBy->name] : null),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'bank_approved_at' => $this->bank_approved_at?->toISOString(),
            'support_approved_at' => $this->support_approved_at?->toISOString(),
            'swift_uploaded_at' => $this->swift_uploaded_at?->toISOString(),
            'executive_decided_at' => $this->executive_decided_at?->toISOString(),
            'customs_issued_at' => $this->customs_issued_at?->toISOString(),
            'customs_declaration' => $this->issuedCustomsDeclaration ? [
                'id' => $this->issuedCustomsDeclaration->id,
                'declaration_number' => $this->issuedCustomsDeclaration->declaration_number,
                'issued_at' => $this->issuedCustomsDeclaration->issued_at?->toISOString(),
                'issued_by' => $this->issuedCustomsDeclaration->issuer ? [
                    'id' => $this->issuedCustomsDeclaration->issuer->id,
                    'name' => $this->issuedCustomsDeclaration->issuer->name,
                ] : null,
                'download_url' => url("/api/customs/{$this->issuedCustomsDeclaration->id}/download"),
            ] : null,
            'revision_count' => $this->revision_count,
            'voting_session_status' => $this->voting_session_status?->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'swift_uploaded_by' => $this->swift_uploaded_by,
            'swift_uploaded_by_user' => $this->whenLoaded('swiftUploadedBy', fn () => $this->swiftUploadedBy ? ['id' => $this->swiftUploadedBy->id, 'name' => $this->swiftUploadedBy->name] : null),
            'documents' => $this->whenLoaded('documents', function () {
                return $this->documents->map(fn ($doc) => [
                    'id' => $doc->id,
                    'type' => $doc->type,
                    'original_filename' => $doc->original_filename,
                    'mime_type' => $doc->mime_type,
                    'size_bytes' => $doc->size_bytes,
                    'checksum' => $doc->checksum,
                    'uploaded_by' => $doc->uploaded_by,
                    'uploaded_by_name' => $doc->relationLoaded('uploader') ? $doc->uploader?->name : null,
                    'uploaded_at' => $doc->created_at?->toISOString(),
                    'download_url' => url("/api/documents/{$doc->id}/download"),
                ])->values()->all();
            }),
        ];
    }
}
