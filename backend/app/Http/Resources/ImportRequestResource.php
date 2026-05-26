<?php

namespace App\Http\Resources;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class ImportRequestResource extends JsonResource
{
    private static ?int $executiveVotersCount = null;

    private function executiveVotersCount(): int
    {
        if (self::$executiveVotersCount !== null) {
            return self::$executiveVotersCount;
        }

        self::$executiveVotersCount = User::query()
            ->where('role', UserRole::EXECUTIVE_MEMBER->value)
            ->where('is_active', true)
            ->count();

        return self::$executiveVotersCount;
    }

    public static function baseRelations(): array
    {
        return [
            'bank',
            'merchant',
            'claimedByUser',
            'creator',
            'lastUpdatedBy',
            'submittedBy',
            'reviewedBy',
            'approvedBy',
            'rejectedBy',
            'resubmittedBy',
            'supportReviewedBy',
            'swiftUploadedBy',
        ];
    }

    public static function detailRelations(): array
    {
        return [
            ...self::baseRelations(),
            'documents.uploader',
            'votes',
            'issuedCustomsDeclaration.issuer',
        ];
    }

    private function actorSummary($user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    private function actorWhenLoaded(string $relation): mixed
    {
        return $this->whenLoaded($relation, fn () => $this->actorSummary($this->{$relation}));
    }

    public function toArray(Request $request): array
    {
        $votes = $this->relationLoaded('votes') ? $this->votes : collect();
        $documents = $this->relationLoaded('documents') ? $this->documents : collect();
        $totalVoters = $this->executiveVotersCount();
        $votesCast = $votes->count();
        $approveCount = $votes->filter(fn ($vote) => $vote->vote?->value === 'APPROVE')->count();
        $rejectCount = $votes->filter(fn ($vote) => $vote->vote?->value === 'REJECT')->count();
        $hasSwiftDocument = $documents->contains(fn ($document) => $document->type === 'SWIFT');
        $hasFxRequestDocument = $documents->contains(fn ($document) => $document->type === 'FX_REQUEST');

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
            'created_by_user' => $this->actorWhenLoaded('creator'),
            'last_updated_by' => $this->last_updated_by,
            'last_updated_by_user' => $this->actorWhenLoaded('lastUpdatedBy'),
            'status' => $this->status?->value,
            'current_owner_role' => $this->current_owner_role?->value,
            'claimed_by' => $this->actorSummary($this->claimedByUser),
            'support_claimed_by' => $this->actorSummary($this->claimedByUser),
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
            'submitted_by_user' => $this->actorWhenLoaded('submittedBy'),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_by_user' => $this->actorWhenLoaded('reviewedBy'),
            'internal_reviewer' => $this->actorWhenLoaded('reviewedBy'),
            'approved_by' => $this->approved_by,
            'approved_by_user' => $this->actorWhenLoaded('approvedBy'),
            'rejected_by' => $this->rejected_by,
            'rejected_by_user' => $this->actorWhenLoaded('rejectedBy'),
            'resubmitted_by' => $this->resubmitted_by,
            'resubmitted_by_user' => $this->actorWhenLoaded('resubmittedBy'),
            'support_reviewed_by' => $this->support_reviewed_by,
            'support_reviewed_by_user' => $this->actorWhenLoaded('supportReviewedBy'),
            'support_reviewer' => $this->actorWhenLoaded('supportReviewedBy'),
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
            'bank_return_comment' => $this->status === RequestStatus::BANK_RETURNED
                ? $this->stageHistory()
                    ->where('action', 'bank_return_to_intake')
                    ->latest()
                    ->value('reason')
                : null,
            'bank_reject_comment' => $this->status === RequestStatus::BANK_REJECTED
                ? $this->stageHistory()
                    ->where('action', 'bank_reject_terminal')
                    ->latest()
                    ->value('reason')
                : null,
            'support_return_comment' => $this->status === RequestStatus::SUPPORT_RETURNED
                ? $this->stageHistory()
                    ->where('action', 'support_return_to_intake')
                    ->latest()
                    ->value('reason')
                : null,
            'revision_count' => $this->revision_count,
            'voting_session_status' => $this->voting_session_status?->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'swift_uploaded_by' => $this->swift_uploaded_by,
            'swift_uploaded_by_user' => $this->actorWhenLoaded('swiftUploadedBy'),
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
            'votes_cast' => $votesCast,
            'total_voters' => $totalVoters,
            'ready_to_close' => $this->status === RequestStatus::EXECUTIVE_VOTING_OPEN
                && $totalVoters > 0
                && $votesCast >= $totalVoters,
            'is_tie' => $this->status === RequestStatus::EXECUTIVE_VOTING_OPEN
                && $approveCount > 0
                && $approveCount === $rejectCount,
            'has_swift_document' => $hasSwiftDocument,
            'has_fx_request_document' => $hasFxRequestDocument,
        ];
    }
}
