<?php

namespace App\Http\Resources;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportRequestListResource extends JsonResource
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
            'status' => $this->status?->value,
            'voting_rule_version' => (int) ($this->voting_rule_version ?? 1),
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
            'goods_type' => $this->goods_type,
            'invoice_number' => $this->invoice_number,
            'created_at' => $this->created_at?->toISOString(),
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
