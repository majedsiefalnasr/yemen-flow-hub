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

class VotingService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly AuditService $auditService
    ) {
    }

    public function castVote(ImportRequest $request, User $voter, VoteType $vote, ?string $justification): RequestVote
    {
        if ($request->status !== RequestStatus::EXECUTIVE_VOTING) {
            throw new VotingException('Request is not in executive voting stage.');
        }

        if (!$voter->hasRole(UserRole::EXECUTIVE_MEMBER)) {
            throw new VotingException('Only executive members can cast votes.');
        }

        if (RequestVote::query()->where('request_id', $request->id)->where('user_id', $voter->id)->exists()) {
            throw new DuplicateVoteException('You already voted on this request.');
        }

        $record = RequestVote::query()->create([
            'request_id' => $request->id,
            'user_id' => $voter->id,
            'vote' => $vote,
            'justification' => $justification,
        ]);

        $this->auditService->log(
            AuditAction::VOTE_CAST,
            $voter,
            $request,
            ['vote' => $vote->value]
        );

        $tally = $this->tally($request->fresh());
        if ($tally->approveCount >= 4) {
            $this->workflowService->transition($request->fresh(), 'finalize_approved', $voter, null, ['auto_finalized' => true]);
        } elseif ($tally->rejectCount >= 4) {
            $this->workflowService->transition($request->fresh(), 'finalize_rejected', $voter, null, ['auto_finalized' => true]);
        }

        return $record->refresh();
    }

    public function tally(ImportRequest $request): VotingTally
    {
        $counts = RequestVote::query()
            ->selectRaw("vote, COUNT(*) as aggregate")
            ->where('request_id', $request->id)
            ->groupBy('vote')
            ->pluck('aggregate', 'vote');

        $approve = (int) ($counts[VoteType::APPROVE->value] ?? 0);
        $reject = (int) ($counts[VoteType::REJECT->value] ?? 0);
        $abstain = (int) ($counts[VoteType::ABSTAIN->value] ?? 0);
        $totalCast = $approve + $reject + $abstain;

        $result = 'PENDING';
        $isDecided = false;

        if ($approve >= 4) {
            $result = 'APPROVED';
            $isDecided = true;
        } elseif ($reject >= 4) {
            $result = 'REJECTED';
            $isDecided = true;
        } elseif ($totalCast === 6) {
            $result = 'TIE';
            $isDecided = true;
        }

        return new VotingTally($approve, $reject, $abstain, $totalCast, $isDecided, $result);
    }

    public function finalize(ImportRequest $request, User $director, ?VoteType $directorVote = null): ImportRequest
    {
        if (!$director->hasRole(UserRole::EXECUTIVE_DIRECTOR)) {
            throw new VotingException('Only executive director can finalize tie votes.');
        }

        $tally = $this->tally($request);
        if ($tally->result !== 'TIE') {
            throw new VotingException('Director decision is only allowed for ties.');
        }

        if (!in_array($directorVote, [VoteType::APPROVE, VoteType::REJECT], true)) {
            throw new VotingException('Director vote must be APPROVE or REJECT.');
        }

        RequestVote::query()->updateOrCreate(
            ['request_id' => $request->id, 'user_id' => $director->id],
            [
                'vote' => $directorVote,
                'justification' => null,
                'is_director_override' => true,
            ]
        );

        $action = $directorVote === VoteType::APPROVE ? 'finalize_approved' : 'finalize_rejected';

        return $this->workflowService->transition($request->fresh(), $action, $director, null, ['director_override' => true]);
    }

    public function overrideAndFinalize(
        ImportRequest $request,
        User $director,
        VoteType $finalDecision,
        string $justification
    ): ImportRequest {
        if (!$director->hasRole(UserRole::EXECUTIVE_DIRECTOR)) {
            throw new VotingException('Only executive director can override voting decisions.');
        }

        if ($request->status !== RequestStatus::EXECUTIVE_VOTING) {
            throw new VotingException('Override is only allowed during executive voting stage.');
        }

        if (trim($justification) === '') {
            throw new VotingException('Override justification is required.');
        }

        $tally = $this->tally($request);

        RequestVote::query()->updateOrCreate(
            ['request_id' => $request->id, 'user_id' => $director->id],
            [
                'vote' => $finalDecision,
                'justification' => $justification,
                'is_director_override' => true,
            ]
        );

        $action = $finalDecision === VoteType::APPROVE ? 'finalize_approved' : 'finalize_rejected';

        return $this->workflowService->transition(
            $request->fresh(),
            $action,
            $director,
            $justification,
            [
                'was_override' => true,
                'tally_before_override' => [
                    'approve' => $tally->approveCount,
                    'reject' => $tally->rejectCount,
                    'abstain' => $tally->abstainCount,
                    'total_cast' => $tally->totalCast,
                    'result' => $tally->result,
                ],
            ]
        );
    }
}
