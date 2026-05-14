<?php

namespace Database\Seeders\Support;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VoteType;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\RequestDocument;
use App\Models\RequestStageHistory;
use App\Models\RequestVote;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RequestScenarioBuilder
{
    private int $customsSequence = 1;

    public function build(string $scenario, Bank $bank): ImportRequest
    {
        $data = $this->scenarioConfig($scenario);
        $timeline = $this->timelineForStatus($data['status']);

        $entry = User::query()->where('bank_id', $bank->id)->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();
        $reviewer = User::query()->where('bank_id', $bank->id)->where('role', UserRole::BANK_REVIEWER->value)->where('id', '!=', $entry->id)->firstOrFail();
        $swift = User::query()->where('bank_id', $bank->id)->where('role', UserRole::SWIFT_OFFICER->value)->firstOrFail();
        $supportUsers = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->orderBy('id')->get();
        $support = $supportUsers->firstOrFail();
        $director = User::query()->where('role', UserRole::EXECUTIVE_DIRECTOR->value)->firstOrFail();
        $execs = User::query()->where('role', UserRole::EXECUTIVE_MEMBER->value)->orderBy('id')->get();
        $merchant = Merchant::query()->where('bank_id', $bank->id)->inRandomOrder()->firstOrFail();

        $claimedBy = null;
        $claimedAt = null;
        $claimExpiresAt = null;
        if (($data['claim_state'] ?? null) === 'active') {
            $claimer = $supportUsers->first();
            $claimedBy = $claimer?->id;
            $claimedAt = now()->subHour();
            $claimExpiresAt = now()->addHours(23);
        } elseif (($data['claim_state'] ?? null) === 'expired') {
            $claimer = $supportUsers->skip(1)->first() ?? $supportUsers->first();
            $claimedBy = $claimer?->id;
            $claimedAt = now()->subHours(30);
            $claimExpiresAt = now()->subHour();
        }

        $owner = $this->ownerForStatus($data['status']);
        $request = ImportRequest::query()->create([
            'bank_id' => $bank->id,
            'merchant_id' => $merchant->id,
            'created_by' => $entry->id,
            'currency' => Arr::random(['USD', 'EUR', 'SAR', 'AED', 'CNY']),
            'amount' => fake()->randomFloat(2, 1000, 300000),
            'supplier_name' => Arr::random(['Al-Hadi Trading LLC', 'Shanghai Medical Supplies Co.', 'Global Grain & Food Imports', 'Aden Industrial Sourcing Ltd.']),
            'goods_description' => Arr::random(['Medical Equipment', 'Food Supplies', 'Telecom Devices', 'Industrial Spare Parts']),
            'port_of_entry' => Arr::random(['Aden Port', 'Hodeidah Port', 'Mukalla Port', "Sana'a Airport"]),
            'notes' => fake()->boolean(35) ? Arr::random(['Additional document under review', 'Need minor clarification']) : null,
            'status' => $data['status'],
            'current_owner_role' => $owner,
            'claimed_by' => $claimedBy,
            'claimed_at' => $claimedAt,
            'claim_expires_at' => $claimExpiresAt,
            'submitted_at' => $timeline['submitted_at'],
            'bank_approved_at' => $timeline['bank_approved_at'],
            'support_approved_at' => $timeline['support_approved_at'],
            'swift_uploaded_at' => $timeline['swift_uploaded_at'],
            'executive_decided_at' => $timeline['executive_decided_at'],
            'customs_issued_at' => $timeline['customs_issued_at'],
            'revision_count' => $data['revision_count'],
            'created_at' => $timeline['created_at'],
            'updated_at' => $timeline['updated_at'],
        ]);

        $this->seedRequestDocs($request, $entry, $timeline['created_at']);
        if ($this->reached(RequestStatus::SWIFT_UPLOADED, $request->status)) {
            $this->seedSwiftDoc($request, $swift, $timeline['swift_uploaded_at'] ?? now());
        }

        $this->seedHistory($scenario, $request, $entry, $reviewer, $support, $swift, $director, $timeline);
        $this->seedVotes($scenario, $request, $execs, $timeline);

        if ($this->reached(RequestStatus::CUSTOMS_ISSUED, $request->status)) {
            $declaration = $this->seedCustomsDeclaration($request, $director, $timeline['customs_issued_at'] ?? now());
            $this->seedCustomsDoc($request, $director, $declaration->declaration_number, $timeline['customs_issued_at'] ?? now());
        }

        return $request;
    }

    private function scenarioConfig(string $scenario): array
    {
        return match ($scenario) {
            'draft' => ['status' => RequestStatus::DRAFT, 'revision_count' => 0],
            'submitted' => ['status' => RequestStatus::SUBMITTED, 'revision_count' => 0],
            'bank_approved' => ['status' => RequestStatus::BANK_APPROVED, 'revision_count' => 0],
            'bank_rejected_terminal' => ['status' => RequestStatus::BANK_REJECTED, 'revision_count' => 0],
            'returned_to_entry_once' => ['status' => RequestStatus::RETURNED_TO_DATA_ENTRY, 'revision_count' => 1],
            'support_under_review_claimed' => ['status' => RequestStatus::SUPPORT_UNDER_REVIEW, 'revision_count' => 0, 'claim_state' => 'active'],
            'support_claim_expired' => ['status' => RequestStatus::SUPPORT_UNDER_REVIEW, 'revision_count' => 0, 'claim_state' => 'expired'],
            'support_approved_waiting_swift' => ['status' => RequestStatus::SUPPORT_APPROVED, 'revision_count' => 0],
            'support_rejected_pending_reviewer' => ['status' => RequestStatus::SUPPORT_REJECTED, 'revision_count' => 0],
            'executive_voting_pending' => ['status' => RequestStatus::EXECUTIVE_VOTING, 'revision_count' => 0],
            'executive_voting_tie' => ['status' => RequestStatus::EXECUTIVE_VOTING, 'revision_count' => 0],
            'executive_approved_no_customs_yet' => ['status' => RequestStatus::EXECUTIVE_APPROVED, 'revision_count' => 0],
            'executive_rejected_returned' => ['status' => RequestStatus::EXECUTIVE_REJECTED, 'revision_count' => 0],
            'customs_issued' => ['status' => RequestStatus::CUSTOMS_ISSUED, 'revision_count' => 0],
            'completed' => ['status' => RequestStatus::COMPLETED, 'revision_count' => 0],
            'completed_with_revision' => ['status' => RequestStatus::COMPLETED, 'revision_count' => 1],
            default => throw new \InvalidArgumentException("Unknown scenario {$scenario}"),
        };
    }

    private function ownerForStatus(RequestStatus $status): UserRole
    {
        return match ($status) {
            RequestStatus::DRAFT, RequestStatus::RETURNED_TO_DATA_ENTRY => UserRole::DATA_ENTRY,
            RequestStatus::SUBMITTED, RequestStatus::SUPPORT_REJECTED, RequestStatus::EXECUTIVE_REJECTED => UserRole::BANK_REVIEWER,
            RequestStatus::BANK_APPROVED, RequestStatus::SUPPORT_UNDER_REVIEW => UserRole::SUPPORT_COMMITTEE,
            RequestStatus::SUPPORT_APPROVED => UserRole::SWIFT_OFFICER,
            RequestStatus::SWIFT_UPLOADED, RequestStatus::EXECUTIVE_VOTING => UserRole::EXECUTIVE_MEMBER,
            RequestStatus::EXECUTIVE_APPROVED, RequestStatus::CUSTOMS_ISSUED, RequestStatus::BANK_REJECTED, RequestStatus::COMPLETED => UserRole::EXECUTIVE_DIRECTOR,
        };
    }

    private function timelineForStatus(RequestStatus $status): array
    {
        $created = now()->subDays(rand(20, 80));
        $submitted = $created->copy()->addDays(rand(1, 2));
        $bankApproved = $submitted->copy()->addDays(rand(1, 2));
        $supportApproved = $bankApproved->copy()->addDays(rand(1, 2));
        $swiftUploaded = $supportApproved->copy()->addDays(rand(1, 2));
        $executiveDecided = $swiftUploaded->copy()->addDays(rand(1, 2));
        $customsIssued = $executiveDecided->copy()->addDays(rand(1, 2));

        return [
            'created_at' => $created,
            'submitted_at' => $this->reached(RequestStatus::SUBMITTED, $status) ? $submitted : null,
            'bank_approved_at' => $this->reached(RequestStatus::BANK_APPROVED, $status) ? $bankApproved : null,
            'support_approved_at' => $this->reached(RequestStatus::SUPPORT_APPROVED, $status) ? $supportApproved : null,
            'swift_uploaded_at' => $this->reached(RequestStatus::SWIFT_UPLOADED, $status) ? $swiftUploaded : null,
            'executive_decided_at' => $this->reached(RequestStatus::EXECUTIVE_APPROVED, $status) || $this->reached(RequestStatus::EXECUTIVE_REJECTED, $status) || $this->reached(RequestStatus::CUSTOMS_ISSUED, $status) || $this->reached(RequestStatus::COMPLETED, $status) ? $executiveDecided : null,
            'customs_issued_at' => $this->reached(RequestStatus::CUSTOMS_ISSUED, $status) || $this->reached(RequestStatus::COMPLETED, $status) ? $customsIssued : null,
            'updated_at' => $customsIssued,
        ];
    }

    private function reached(RequestStatus $target, RequestStatus $current): bool
    {
        $order = [
            RequestStatus::DRAFT->value => 1,
            RequestStatus::SUBMITTED->value => 2,
            RequestStatus::BANK_APPROVED->value => 3,
            RequestStatus::SUPPORT_UNDER_REVIEW->value => 4,
            RequestStatus::SUPPORT_APPROVED->value => 5,
            RequestStatus::SWIFT_UPLOADED->value => 6,
            RequestStatus::EXECUTIVE_VOTING->value => 7,
            RequestStatus::EXECUTIVE_APPROVED->value => 8,
            RequestStatus::CUSTOMS_ISSUED->value => 9,
            RequestStatus::COMPLETED->value => 10,
        ];

        return ($order[$current->value] ?? 0) >= ($order[$target->value] ?? 0);
    }

    private function seedRequestDocs(ImportRequest $request, User $actor, Carbon $at): void
    {
        $count = in_array($request->status, [RequestStatus::DRAFT], true) ? rand(0, 1) : rand(1, 3);
        for ($i = 0; $i < $count; $i++) {
            $doc = RequestDocument::query()->create([
                'request_id' => $request->id,
                'uploaded_by' => $actor->id,
                'type' => 'REQUEST_DOC',
                'original_filename' => Arr::random(['invoice.pdf', 'contract.pdf', 'goods_list.pdf']),
                'stored_path' => "requests/{$request->id}/".Str::uuid().'.pdf',
                'mime_type' => Arr::random(['application/pdf', 'image/jpeg', 'image/png']),
                'size_bytes' => rand(50 * 1024, 5 * 1024 * 1024),
                'created_at' => $at->copy()->addMinutes($i + 1),
                'updated_at' => $at->copy()->addMinutes($i + 1),
            ]);
            $this->log(AuditAction::DOCUMENT_UPLOADED, $actor, $doc, ['request_id' => $request->id], $doc->created_at);
        }
    }

    private function seedSwiftDoc(ImportRequest $request, User $actor, Carbon $at): void
    {
        $doc = RequestDocument::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $actor->id,
            'type' => 'SWIFT',
            'original_filename' => 'swift_message.pdf',
            'stored_path' => "swift/{$request->id}/".Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => rand(60 * 1024, 2 * 1024 * 1024),
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $this->log(AuditAction::SWIFT_UPLOADED, $actor, $doc, ['request_id' => $request->id], $at);
    }

    private function seedCustomsDoc(ImportRequest $request, User $actor, string $declNo, Carbon $at): void
    {
        $doc = RequestDocument::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $actor->id,
            'type' => 'CUSTOMS',
            'original_filename' => "customs_declaration_{$declNo}.pdf",
            'stored_path' => "customs/{$request->id}/".Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => rand(80 * 1024, 3 * 1024 * 1024),
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $this->log(AuditAction::DOCUMENT_UPLOADED, $actor, $doc, ['request_id' => $request->id], $at);
    }

    private function seedHistory(string $scenario, ImportRequest $request, User $entry, User $reviewer, User $support, User $swift, User $director, array $timeline): void
    {
        $steps = [];
        $add = function (?RequestStatus $from, RequestStatus $to, UserRole $fromOwner, ?UserRole $toOwner, User $actor, string $action, ?string $reason = null, ?Carbon $at = null) use (&$steps): void {
            $steps[] = compact('from', 'to', 'fromOwner', 'toOwner', 'actor', 'action', 'reason', 'at');
        };

        if ($scenario !== 'draft') {
            $add(RequestStatus::DRAFT, RequestStatus::SUBMITTED, UserRole::DATA_ENTRY, UserRole::BANK_REVIEWER, $entry, 'submit', null, $timeline['submitted_at']);
        }
        if (in_array($scenario, ['bank_approved', 'support_under_review_claimed', 'support_claim_expired', 'support_approved_waiting_swift', 'executive_voting_pending', 'executive_voting_tie', 'executive_approved_no_customs_yet', 'executive_rejected_returned', 'customs_issued', 'completed', 'completed_with_revision'], true)) {
            $add(RequestStatus::SUBMITTED, RequestStatus::BANK_APPROVED, UserRole::BANK_REVIEWER, UserRole::SUPPORT_COMMITTEE, $reviewer, 'bank_approve', null, $timeline['bank_approved_at']);
        }
        if ($scenario === 'bank_rejected_terminal') {
            $add(RequestStatus::SUBMITTED, RequestStatus::BANK_REJECTED, UserRole::BANK_REVIEWER, null, $reviewer, 'bank_reject', 'Bank rejected', $timeline['bank_approved_at'] ?? now());
        }
        if (in_array($scenario, ['returned_to_entry_once', 'completed_with_revision'], true)) {
            $add(RequestStatus::SUBMITTED, RequestStatus::RETURNED_TO_DATA_ENTRY, UserRole::BANK_REVIEWER, UserRole::DATA_ENTRY, $reviewer, 'return_to_entry', 'Missing supplier license', $timeline['bank_approved_at'] ?? now());
        }
        if (in_array($scenario, ['support_under_review_claimed', 'support_claim_expired', 'support_approved_waiting_swift', 'support_rejected_pending_reviewer', 'executive_voting_pending', 'executive_voting_tie', 'executive_approved_no_customs_yet', 'executive_rejected_returned', 'customs_issued', 'completed', 'completed_with_revision'], true)) {
            $add(RequestStatus::BANK_APPROVED, RequestStatus::SUPPORT_UNDER_REVIEW, UserRole::SUPPORT_COMMITTEE, UserRole::SUPPORT_COMMITTEE, $support, 'support_claim', null, $timeline['support_approved_at']);
        }
        if (in_array($scenario, ['support_approved_waiting_swift', 'executive_voting_pending', 'executive_voting_tie', 'executive_approved_no_customs_yet', 'executive_rejected_returned', 'customs_issued', 'completed', 'completed_with_revision'], true)) {
            $add(RequestStatus::SUPPORT_UNDER_REVIEW, RequestStatus::SUPPORT_APPROVED, UserRole::SUPPORT_COMMITTEE, UserRole::SWIFT_OFFICER, $support, 'support_approve', null, $timeline['support_approved_at']);
        }
        if ($scenario === 'support_rejected_pending_reviewer') {
            $add(RequestStatus::SUPPORT_UNDER_REVIEW, RequestStatus::SUPPORT_REJECTED, UserRole::SUPPORT_COMMITTEE, UserRole::BANK_REVIEWER, $support, 'support_reject', 'Compliance gap', $timeline['support_approved_at'] ?? now());
        }
        if (in_array($scenario, ['executive_voting_pending', 'executive_voting_tie', 'executive_approved_no_customs_yet', 'executive_rejected_returned', 'customs_issued', 'completed', 'completed_with_revision'], true)) {
            $add(RequestStatus::SUPPORT_APPROVED, RequestStatus::SWIFT_UPLOADED, UserRole::SWIFT_OFFICER, UserRole::EXECUTIVE_MEMBER, $swift, 'swift_upload', null, $timeline['swift_uploaded_at']);
            $add(RequestStatus::SWIFT_UPLOADED, RequestStatus::EXECUTIVE_VOTING, UserRole::EXECUTIVE_MEMBER, UserRole::EXECUTIVE_MEMBER, $swift, 'start_voting', null, ($timeline['swift_uploaded_at'] ?? now())->copy()->addHour());
        }
        if (in_array($scenario, ['executive_approved_no_customs_yet', 'customs_issued', 'completed', 'completed_with_revision'], true)) {
            $add(RequestStatus::EXECUTIVE_VOTING, RequestStatus::EXECUTIVE_APPROVED, UserRole::EXECUTIVE_MEMBER, UserRole::EXECUTIVE_DIRECTOR, $director, 'finalize_approved', null, $timeline['executive_decided_at']);
        }
        if ($scenario === 'executive_rejected_returned') {
            $add(RequestStatus::EXECUTIVE_VOTING, RequestStatus::EXECUTIVE_REJECTED, UserRole::EXECUTIVE_MEMBER, UserRole::BANK_REVIEWER, $director, 'finalize_rejected', 'Votes rejected', $timeline['executive_decided_at']);
        }
        if (in_array($scenario, ['customs_issued', 'completed', 'completed_with_revision'], true)) {
            $add(RequestStatus::EXECUTIVE_APPROVED, RequestStatus::CUSTOMS_ISSUED, UserRole::EXECUTIVE_DIRECTOR, UserRole::EXECUTIVE_DIRECTOR, $director, 'issue_customs', null, $timeline['customs_issued_at']);
        }
        if (in_array($scenario, ['completed', 'completed_with_revision'], true)) {
            $add(RequestStatus::CUSTOMS_ISSUED, RequestStatus::COMPLETED, UserRole::EXECUTIVE_DIRECTOR, null, $director, 'complete', null, ($timeline['customs_issued_at'] ?? now())->copy()->addHour());
        }

        foreach ($steps as $step) {
            $row = RequestStageHistory::query()->create([
                'request_id' => $request->id,
                'from_status' => $step['from'],
                'to_status' => $step['to'],
                'from_owner_role' => $step['fromOwner'],
                'to_owner_role' => $step['toOwner'],
                'actor_id' => $step['actor']->id,
                'actor_role' => $step['actor']->role,
                'action' => $step['action'],
                'reason' => $step['reason'],
                'metadata' => null,
                'created_at' => $step['at'] ?? now(),
                'updated_at' => $step['at'] ?? now(),
            ]);
            $this->log(AuditAction::STATUS_TRANSITION, $step['actor'], $request, ['history_id' => $row->id, 'to_status' => $step['to']->value], $row->created_at);
        }
    }

    private function seedVotes(string $scenario, ImportRequest $request, $execs, array $timeline): void
    {
        if (!in_array($scenario, ['executive_voting_pending', 'executive_voting_tie', 'executive_approved_no_customs_yet', 'executive_rejected_returned', 'customs_issued', 'completed', 'completed_with_revision'], true)) {
            return;
        }

        $votes = [];
        if ($scenario === 'executive_voting_pending') {
            for ($i = 0; $i < rand(0, 3); $i++) {
                $votes[] = Arr::random([VoteType::APPROVE, VoteType::REJECT, VoteType::ABSTAIN]);
            }
        } elseif ($scenario === 'executive_voting_tie') {
            $votes = [VoteType::APPROVE, VoteType::APPROVE, VoteType::APPROVE, VoteType::REJECT, VoteType::REJECT, VoteType::REJECT];
        } elseif (in_array($scenario, ['executive_approved_no_customs_yet', 'customs_issued', 'completed', 'completed_with_revision'], true)) {
            $votes = [VoteType::APPROVE, VoteType::APPROVE, VoteType::APPROVE, VoteType::APPROVE, VoteType::REJECT, VoteType::ABSTAIN];
        } elseif ($scenario === 'executive_rejected_returned') {
            $votes = [VoteType::REJECT, VoteType::REJECT, VoteType::REJECT, VoteType::REJECT, VoteType::REJECT, VoteType::APPROVE];
        }

        foreach ($votes as $idx => $voteType) {
            $actor = $execs[$idx];
            $at = ($timeline['swift_uploaded_at'] ?? now())->copy()->addHours($idx + 1);
            $vote = RequestVote::query()->create([
                'request_id' => $request->id,
                'user_id' => $actor->id,
                'vote' => $voteType,
                'justification' => fake()->boolean(50) ? Arr::random(['Need stronger docs', 'High risk', 'Compliant']) : null,
                'is_director_override' => false,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $this->log(AuditAction::VOTE_CAST, $actor, $vote, ['request_id' => $request->id], $at);
        }
    }

    private function seedCustomsDeclaration(ImportRequest $request, User $issuer, Carbon $at): CustomsDeclaration
    {
        $year = now()->format('Y');
        $number = 'CD-'.$year.'-'.str_pad((string) $this->customsSequence++, 6, '0', STR_PAD_LEFT);
        $decl = CustomsDeclaration::query()->create([
            'request_id' => $request->id,
            'declaration_number' => $number,
            'issued_by' => $issuer->id,
            'issued_at' => $at,
            'pdf_path' => "customs/{$request->id}/{$number}.pdf",
            'metadata' => [
                'bank_name' => $request->bank?->name,
                'supplier' => $request->supplier_name,
                'amount' => (float) $request->amount,
                'currency' => $request->currency,
                'goods' => $request->goods_description,
                'port' => $request->port_of_entry,
            ],
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $this->log(AuditAction::CUSTOMS_ISSUED, $issuer, $decl, ['request_id' => $request->id], $at);
        return $decl;
    }

    private function log(AuditAction $action, User $actor, $subject, array $meta, Carbon $at): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'user_role' => $actor->role->value,
            'action' => $action->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Seeder/1.0',
            'metadata' => $meta,
            'created_at' => $at,
        ]);
    }
}

