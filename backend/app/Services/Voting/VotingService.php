<?php

namespace App\Services\Voting;

use App\DTOs\Voting\VotingTally;
use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VoteType;
use App\Exceptions\DuplicateVoteException;
use App\Exceptions\VotingException;
use App\Models\ImportRequest;
use App\Models\RequestVote;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Workflow\WorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class VotingService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly AuditService $auditService
    ) {}

    public function castVote(ImportRequest $request, User $voter, VoteType $vote, ?string $justification): RequestVote
    {
        if ($request->status !== RequestStatus::EXECUTIVE_VOTING_OPEN) {
            throw new VotingException('Request is not in executive voting stage.');
        }

        if (! $voter->hasRole(UserRole::EXECUTIVE_MEMBER) && ! $voter->hasRole(UserRole::COMMITTEE_DIRECTOR)) {
            throw new VotingException('Only executive members and committee director can cast votes.');
        }

        return DB::transaction(function () use ($request, $voter, $vote, $justification) {
            $existing = RequestVote::query()
                ->where('request_id', $request->id)
                ->where('user_id', $voter->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new DuplicateVoteException('You already voted on this request.');
            }

            $record = RequestVote::query()->create([
                'request_id' => $request->id,
                'user_id' => $voter->id,
                'vote' => $vote,
                'justification' => $justification,
                'voted_at' => now(),
            ]);

            $this->auditService->log(
                AuditAction::VOTE_CAST,
                $voter,
                $request,
                ['vote' => $vote->value]
            );

            return $record->refresh();
        });
    }

    public function closeSession(ImportRequest $request, User $director): ImportRequest
    {
        if (! $director->hasRole(UserRole::COMMITTEE_DIRECTOR)) {
            throw new VotingException('Only committee director can close the voting session.');
        }

        if ($request->status !== RequestStatus::EXECUTIVE_VOTING_OPEN) {
            throw new VotingException('Voting session is not open.');
        }

        return DB::transaction(function () use ($request, $director) {
            $lockedRequest = ImportRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->first();

            $votedUserIds = RequestVote::query()
                ->where('request_id', $lockedRequest->id)
                ->lockForUpdate()
                ->pluck('user_id')
                ->all();

            $nonVoters = $this->activeExecutiveQuery()
                ->whereNotIn('id', $votedUserIds)
                ->get();

            foreach ($nonVoters as $member) {
                RequestVote::query()->create([
                    'request_id' => $lockedRequest->id,
                    'user_id' => $member->id,
                    'vote' => VoteType::AUTO_ABSTAIN_TIMEOUT,
                    'justification' => null,
                    'voted_at' => now(),
                ]);

                $this->logAutoAbstainVote($lockedRequest, $director, $member, 'close_voting');
            }

            return $this->workflowService->transition($lockedRequest->fresh(), 'close_voting', $director);
        });
    }

    public function tally(ImportRequest $request): VotingTally
    {
        $counts = RequestVote::query()
            ->selectRaw('vote, COUNT(*) as aggregate')
            ->where('request_id', $request->id)
            ->groupBy('vote')
            ->pluck('aggregate', 'vote');

        $approve = (int) ($counts[VoteType::APPROVE->value] ?? 0);
        $reject = (int) ($counts[VoteType::REJECT->value] ?? 0);
        $abstain = (int) ($counts[VoteType::ABSTAIN->value] ?? 0);
        $autoAbstain = (int) ($counts[VoteType::AUTO_ABSTAIN_TIMEOUT->value] ?? 0);
        $totalCast = $approve + $reject + $abstain + $autoAbstain;

        // Majority computed from approve vs reject only; abstains excluded per spec
        if ($approve === 0 && $reject === 0) {
            $result = 'PENDING';
            $isDecided = false;
        } elseif ($approve > $reject) {
            $result = 'APPROVED';
            $isDecided = true;
        } elseif ($reject > $approve) {
            $result = 'REJECTED';
            $isDecided = true;
        } else {
            $result = 'TIE';
            $isDecided = true;
        }

        return new VotingTally($approve, $reject, $abstain, $autoAbstain, $totalCast, $isDecided, $result);
    }

    public function finalize(ImportRequest $request, User $director): ImportRequest
    {
        if (! $director->hasRole(UserRole::COMMITTEE_DIRECTOR)) {
            throw new VotingException('Only committee director can finalize the voting decision.');
        }

        if ($request->status !== RequestStatus::EXECUTIVE_VOTING_CLOSED) {
            throw new VotingException('Voting session must be closed before finalizing.');
        }

        return DB::transaction(function () use ($request, $director) {
            $lockedRequest = ImportRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->first();

            // Re-check status inside the lock to guard against concurrent finalize calls
            if ($lockedRequest->status !== RequestStatus::EXECUTIVE_VOTING_CLOSED) {
                throw new VotingException('Voting session must be closed before finalizing.');
            }

            $tally = $this->tally($lockedRequest);

            // Era gate (Epic 17-E.3): two finalize rules coexist, keyed on the request's
            // stored voting_rule_version (1 = legacy, 2 = new National Committee). The gate
            // is read here, at finalize-time, from the request row — never inferred from
            // created_at — so an in-flight legacy session opened before the 17-E deploy
            // still finalizes under the legacy rule (including Director tie-break) and no
            // already-closed session is ever recomputed.
            if ((int) ($lockedRequest->voting_rule_version ?? 1) === 2) {
                // New rule: simple majority of ALL eligible members. floor(n/2)+1 approvals
                // → APPROVED; every other outcome (sub-majority or even split) → Not-Eligible
                // (EXECUTIVE_REJECTED). No Director tie-break.
                $required = intdiv($this->totalEligibleMembers(), 2) + 1;
                $action = $tally->approveCount >= $required ? 'finalize_approved' : 'finalize_rejected';

                return $this->workflowService->transition($lockedRequest, $action, $director);
            }

            if ($tally->approveCount > $tally->rejectCount) {
                return $this->workflowService->transition($lockedRequest, 'finalize_approved', $director);
            }

            if ($tally->rejectCount > $tally->approveCount) {
                return $this->workflowService->transition($lockedRequest, 'finalize_rejected', $director);
            }

            // Tie: Director's APPROVE vote is the tiebreaker; anything else → REJECTED (safe stance)
            $directorVoteRecord = RequestVote::query()
                ->where('request_id', $lockedRequest->id)
                ->where('user_id', $director->id)
                ->lockForUpdate()
                ->first();

            if ($directorVoteRecord !== null && $directorVoteRecord->vote === VoteType::APPROVE) {
                return $this->workflowService->transition($lockedRequest, 'finalize_approved', $director);
            }

            return $this->workflowService->transition($lockedRequest, 'finalize_rejected', $director);
        });
    }

    public function overrideAndFinalize(
        ImportRequest $request,
        User $director,
        VoteType $finalDecision,
        string $justification
    ): ImportRequest {
        if (! $director->hasRole(UserRole::COMMITTEE_DIRECTOR)) {
            throw new VotingException('Only committee director can override voting decisions.');
        }

        if ($request->status !== RequestStatus::EXECUTIVE_VOTING_OPEN) {
            throw new VotingException('Override is only allowed during executive voting stage.');
        }

        // Era gate (Epic 17-E.3): new-rule (voting_rule_version = 2) sessions finalize on a
        // simple majority with no Director tie-break/override path. Legacy (version 1)
        // sessions keep the override exactly as shipped.
        if ((int) ($request->voting_rule_version ?? 1) === 2) {
            throw new VotingException('قرار حسم التعادل غير متاح لطلبات اللجنة الوطنية. / Director tie-break/override is not available for National Committee requests.');
        }

        if (trim($justification) === '') {
            throw new VotingException('Override justification is required.');
        }

        $tally = $this->tally($request);

        $overrideMeta = [
            'was_override' => true,
            'tally_before_override' => [
                'approve' => $tally->approveCount,
                'reject' => $tally->rejectCount,
                'abstain' => $tally->abstainCount,
                'total_cast' => $tally->totalCast,
                'result' => $tally->result,
            ],
        ];

        return DB::transaction(function () use ($request, $director, $finalDecision, $justification, $overrideMeta) {
            $lockedRequest = ImportRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->first();

            $votedUserIds = RequestVote::query()
                ->where('request_id', $lockedRequest->id)
                ->lockForUpdate()
                ->pluck('user_id')
                ->all();

            $nonVoters = $this->activeExecutiveQuery()
                ->where('id', '!=', $director->id)
                ->whereNotIn('id', $votedUserIds)
                ->get();

            foreach ($nonVoters as $member) {
                RequestVote::query()->create([
                    'request_id' => $lockedRequest->id,
                    'user_id' => $member->id,
                    'vote' => VoteType::AUTO_ABSTAIN_TIMEOUT,
                    'justification' => null,
                    'voted_at' => now(),
                ]);

                $this->logAutoAbstainVote($lockedRequest, $director, $member, 'override_and_finalize');
            }

            RequestVote::query()->updateOrCreate(
                ['request_id' => $lockedRequest->id, 'user_id' => $director->id],
                [
                    'vote' => $finalDecision,
                    'justification' => $justification,
                    'is_director_override' => true,
                    'voted_at' => now(),
                ]
            );

            $action = $finalDecision === VoteType::APPROVE ? 'finalize_approved' : 'finalize_rejected';

            // OPEN → CLOSED → final decision
            $closed = $this->workflowService->transition(
                $lockedRequest->fresh(),
                'close_voting',
                $director,
                $justification,
                $overrideMeta
            );

            return $this->workflowService->transition($closed, $action, $director, $justification, $overrideMeta);
        });
    }

    /**
     * Active executive voters eligible to participate in a session.
     *
     * Single source of truth for "eligible" (Story 3.4 predicate): role in
     * [EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR] AND is_active = true. The Director is
     * counted as an eligible voter, matching the close-session/auto-abstain logic.
     * Shared by AUTO_ABSTAIN_TIMEOUT backfill and the Story 17-E.3 majority threshold
     * so the two definitions can never drift.
     */
    private function activeExecutiveQuery(): Builder
    {
        return User::query()
            ->whereIn('role', [UserRole::EXECUTIVE_MEMBER->value, UserRole::COMMITTEE_DIRECTOR->value])
            ->where('is_active', true);
    }

    private function totalEligibleMembers(): int
    {
        return $this->activeExecutiveQuery()->count();
    }

    private function logAutoAbstainVote(ImportRequest $request, User $director, User $member, string $sourceAction): void
    {
        $this->auditService->log(
            AuditAction::VOTE_CAST,
            $director,
            $request,
            [
                'vote' => VoteType::AUTO_ABSTAIN_TIMEOUT->value,
                'auto_abstain' => true,
                'member_id' => $member->id,
                'member_role' => $member->role?->value,
                'source_action' => $sourceAction,
            ]
        );
    }
}
