<?php

namespace Tests\Unit\Services;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VoteType;
use App\Exceptions\DuplicateVoteException;
use App\Exceptions\VotingException;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\RequestVote;
use App\Models\User;
use App\Services\Voting\VotingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VotingServiceTest extends TestCase
{
    use RefreshDatabase;

    private VotingService $votingService;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->votingService = app(VotingService::class);
        $this->bank = Bank::query()->create(['name' => 'Test Bank', 'code' => 'TB', 'is_active' => true]);
    }

    private function makeUser(UserRole $role): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@vstest.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => null,
            'is_active' => true,
        ]);
    }

    private function makeRequest(RequestStatus $status): ImportRequest
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY);
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 5000.00,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => $status,
                'current_owner_role' => UserRole::EXECUTIVE_MEMBER,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function castVoteDirectly(ImportRequest $request, User $user, VoteType $vote): RequestVote
    {
        return RequestVote::query()->create([
            'request_id' => $request->id,
            'user_id' => $user->id,
            'vote' => $vote,
            'justification' => null,
        ]);
    }

    // ─── Tally tests ──────────────────────────────────────────────────────────

    public function test_tally_majority_approve(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $u1 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u2 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u3 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->castVoteDirectly($request, $u1, VoteType::APPROVE);
        $this->castVoteDirectly($request, $u2, VoteType::APPROVE);
        $this->castVoteDirectly($request, $u3, VoteType::REJECT);

        $tally = $this->votingService->tally($request);

        $this->assertEquals(2, $tally->approveCount);
        $this->assertEquals(1, $tally->rejectCount);
        $this->assertEquals('APPROVED', $tally->result);
        $this->assertTrue($tally->isDecided);
    }

    public function test_tally_majority_reject(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $u1 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u2 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u3 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->castVoteDirectly($request, $u1, VoteType::REJECT);
        $this->castVoteDirectly($request, $u2, VoteType::REJECT);
        $this->castVoteDirectly($request, $u3, VoteType::APPROVE);

        $tally = $this->votingService->tally($request);

        $this->assertEquals('REJECTED', $tally->result);
        $this->assertTrue($tally->isDecided);
    }

    public function test_tally_tie_when_approve_equals_reject(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $u1 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u2 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->castVoteDirectly($request, $u1, VoteType::APPROVE);
        $this->castVoteDirectly($request, $u2, VoteType::REJECT);

        $tally = $this->votingService->tally($request);

        $this->assertEquals('TIE', $tally->result);
    }

    public function test_tally_abstain_excluded_from_majority(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $u1 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u2 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u3 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u4 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->castVoteDirectly($request, $u1, VoteType::APPROVE);
        $this->castVoteDirectly($request, $u2, VoteType::APPROVE);
        $this->castVoteDirectly($request, $u3, VoteType::ABSTAIN);
        $this->castVoteDirectly($request, $u4, VoteType::AUTO_ABSTAIN_TIMEOUT);

        $tally = $this->votingService->tally($request);

        // 2 approve vs 0 reject → APPROVED (abstains excluded)
        $this->assertEquals('APPROVED', $tally->result);
        $this->assertEquals(1, $tally->abstainCount);
        $this->assertEquals(1, $tally->autoAbstainCount);
    }

    public function test_tally_pending_when_no_votes(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $tally = $this->votingService->tally($request);

        $this->assertEquals('PENDING', $tally->result);
        $this->assertFalse($tally->isDecided);
    }

    // ─── castVote tests ────────────────────────────────────────────────────────

    public function test_cast_vote_records_vote(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $member = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $vote = $this->votingService->castVote($request, $member, VoteType::APPROVE, null);

        $this->assertEquals(VoteType::APPROVE, $vote->vote);
        $this->assertEquals($member->id, $vote->user_id);
        $this->assertDatabaseHas('request_votes', [
            'request_id' => $request->id,
            'user_id' => $member->id,
            'vote' => VoteType::APPROVE->value,
        ]);
    }

    public function test_cast_vote_director_can_also_vote(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        $vote = $this->votingService->castVote($request, $director, VoteType::APPROVE, null);

        $this->assertEquals(VoteType::APPROVE, $vote->vote);
    }

    public function test_cast_vote_duplicate_throws_422(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $member = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $this->votingService->castVote($request, $member, VoteType::APPROVE, null);

        $this->expectException(DuplicateVoteException::class);
        $this->votingService->castVote($request, $member, VoteType::REJECT, null);
    }

    public function test_cast_vote_wrong_status_throws_exception(): void
    {
        $request = $this->makeRequest(RequestStatus::WAITING_FOR_VOTING_OPEN);
        $member = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $this->expectException(VotingException::class);
        $this->votingService->castVote($request, $member, VoteType::APPROVE, null);
    }

    public function test_cast_vote_wrong_role_throws_exception(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY);

        $this->expectException(VotingException::class);
        $this->votingService->castVote($request, $dataEntry, VoteType::APPROVE, null);
    }

    // ─── finalize tests ────────────────────────────────────────────────────────

    public function test_finalize_approves_when_approve_majority(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $u1 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u2 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u3 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->castVoteDirectly($request, $u1, VoteType::APPROVE);
        $this->castVoteDirectly($request, $u2, VoteType::APPROVE);
        $this->castVoteDirectly($request, $u3, VoteType::REJECT);

        $updated = $this->votingService->finalize($request, $director);

        $this->assertEquals(RequestStatus::EXECUTIVE_APPROVED, $updated->status);
    }

    public function test_finalize_rejects_when_reject_majority(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $u1 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u2 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->castVoteDirectly($request, $u1, VoteType::REJECT);
        $this->castVoteDirectly($request, $u2, VoteType::REJECT);

        $updated = $this->votingService->finalize($request, $director);

        $this->assertEquals(RequestStatus::EXECUTIVE_REJECTED, $updated->status);
    }

    public function test_finalize_tie_director_approve_wins(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $u1 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u2 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->castVoteDirectly($request, $u1, VoteType::APPROVE);
        $this->castVoteDirectly($request, $u2, VoteType::REJECT);
        // Director voted APPROVE
        $this->castVoteDirectly($request, $director, VoteType::APPROVE);

        $updated = $this->votingService->finalize($request, $director);

        $this->assertEquals(RequestStatus::EXECUTIVE_APPROVED, $updated->status);
    }

    public function test_finalize_tie_director_reject_wins(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $u1 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u2 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->castVoteDirectly($request, $u1, VoteType::APPROVE);
        $this->castVoteDirectly($request, $u2, VoteType::REJECT);
        // Director voted REJECT
        $this->castVoteDirectly($request, $director, VoteType::REJECT);

        $updated = $this->votingService->finalize($request, $director);

        $this->assertEquals(RequestStatus::EXECUTIVE_REJECTED, $updated->status);
    }

    public function test_finalize_tie_no_director_vote_defaults_to_rejected(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $u1 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u2 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->castVoteDirectly($request, $u1, VoteType::APPROVE);
        $this->castVoteDirectly($request, $u2, VoteType::REJECT);
        // Director has NOT voted

        $updated = $this->votingService->finalize($request, $director);

        $this->assertEquals(RequestStatus::EXECUTIVE_REJECTED, $updated->status);
    }

    public function test_finalize_tie_director_abstained_defaults_to_rejected(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $u1 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $u2 = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->castVoteDirectly($request, $u1, VoteType::APPROVE);
        $this->castVoteDirectly($request, $u2, VoteType::REJECT);
        // Director abstained
        $this->castVoteDirectly($request, $director, VoteType::ABSTAIN);

        $updated = $this->votingService->finalize($request, $director);

        $this->assertEquals(RequestStatus::EXECUTIVE_REJECTED, $updated->status);
    }

    public function test_finalize_wrong_status_throws(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        $this->expectException(VotingException::class);
        $this->votingService->finalize($request, $director);
    }

    public function test_finalize_wrong_role_throws(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $member = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $this->expectException(VotingException::class);
        $this->votingService->finalize($request, $member);
    }

    public function test_finalize_already_finalized_throws(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_APPROVED);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        $this->expectException(VotingException::class);
        $this->votingService->finalize($request, $director);
    }

    // ─── voted_at persistence tests ───────────────────────────────────────────

    public function test_cast_vote_persists_voted_at(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $member = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $vote = $this->votingService->castVote($request, $member, VoteType::APPROVE, null);

        $this->assertNotNull($vote->voted_at);
        $this->assertDatabaseHas('request_votes', [
            'request_id' => $request->id,
            'user_id' => $member->id,
        ]);
        $this->assertNotNull(
            RequestVote::query()
                ->where('request_id', $request->id)
                ->where('user_id', $member->id)
                ->value('voted_at')
        );
    }

    // ─── inactive user exclusion tests ────────────────────────────────────────

    public function test_close_session_excludes_inactive_users_from_auto_abstain(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $activeExec = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $inactiveExec = User::query()->create([
            'name' => 'Inactive Exec',
            'email' => 'inactive@vstest.com',
            'password' => Hash::make('password'),
            'role' => UserRole::EXECUTIVE_MEMBER->value,
            'bank_id' => null,
            'is_active' => false,
        ]);

        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->castVoteDirectly($request, $activeExec, VoteType::APPROVE);

        $this->votingService->closeSession($request, $director);

        // Inactive user must NOT receive an AUTO_ABSTAIN_TIMEOUT record
        $this->assertDatabaseMissing('request_votes', [
            'request_id' => $request->id,
            'user_id' => $inactiveExec->id,
        ]);

        // Director (active, not voted) DOES get AUTO_ABSTAIN_TIMEOUT
        $this->assertDatabaseHas('request_votes', [
            'request_id' => $request->id,
            'user_id' => $director->id,
            'vote' => VoteType::AUTO_ABSTAIN_TIMEOUT->value,
        ]);
    }

    public function test_auto_abstain_voted_at_is_persisted(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $exec = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        // exec votes; director does not → director receives AUTO_ABSTAIN_TIMEOUT
        $this->castVoteDirectly($request, $exec, VoteType::APPROVE);

        $this->votingService->closeSession($request, $director);

        $directorVote = RequestVote::query()
            ->where('request_id', $request->id)
            ->where('user_id', $director->id)
            ->first();

        $this->assertNotNull($directorVote);
        $this->assertEquals(VoteType::AUTO_ABSTAIN_TIMEOUT, $directorVote->vote);
        $this->assertNotNull($directorVote->voted_at);
    }

    public function test_close_session_logs_auto_abstain_audit_for_each_non_voter(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $voter = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $nonVoter = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->castVoteDirectly($request, $voter, VoteType::APPROVE);

        $this->votingService->closeSession($request, $director);

        $log = AuditLog::query()
            ->where('user_id', $director->id)
            ->where('user_role', UserRole::COMMITTEE_DIRECTOR->value)
            ->where('action', AuditAction::VOTE_CAST->value)
            ->where('subject_type', ImportRequest::class)
            ->where('subject_id', $request->id)
            ->where('metadata->auto_abstain', true)
            ->where('metadata->member_id', $nonVoter->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(VoteType::AUTO_ABSTAIN_TIMEOUT->value, $log->metadata['vote']);
    }

    public function test_override_and_finalize_logs_auto_abstain_audit_for_each_non_voter(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $voter = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $nonVoter = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->castVoteDirectly($request, $voter, VoteType::REJECT);

        $this->votingService->overrideAndFinalize($request, $director, VoteType::APPROVE, 'Director override reason');

        $log = AuditLog::query()
            ->where('user_id', $director->id)
            ->where('user_role', UserRole::COMMITTEE_DIRECTOR->value)
            ->where('action', AuditAction::VOTE_CAST->value)
            ->where('subject_type', ImportRequest::class)
            ->where('subject_id', $request->id)
            ->where('metadata->auto_abstain', true)
            ->where('metadata->member_id', $nonVoter->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(VoteType::AUTO_ABSTAIN_TIMEOUT->value, $log->metadata['vote']);

        $this->assertDatabaseMissing('audit_logs', [
            'user_id' => $director->id,
            'action' => AuditAction::VOTE_CAST->value,
            'subject_type' => ImportRequest::class,
            'subject_id' => $request->id,
            'metadata->auto_abstain' => true,
            'metadata->member_id' => $director->id,
        ]);

        $this->assertDatabaseHas('request_votes', [
            'request_id' => $request->id,
            'user_id' => $director->id,
            'vote' => VoteType::APPROVE->value,
            'is_director_override' => true,
        ]);
    }
}
