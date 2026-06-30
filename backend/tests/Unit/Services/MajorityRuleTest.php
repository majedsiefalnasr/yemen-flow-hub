<?php

namespace Tests\Unit\Services;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VoteType;
use App\Exceptions\VotingException;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\RequestVote;
use App\Models\User;
use App\Services\Voting\VotingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MajorityRuleTest extends TestCase
{
    use RefreshDatabase;

    private VotingService $votingService;

    private Bank $bank;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->votingService = app(VotingService::class);
        $this->bank = Bank::query()->create(['name' => 'Test Bank', 'code' => 'TB', 'is_active' => true]);
        // Director counts as one eligible member (Story 3.4 predicate).
        $this->director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
    }

    private function makeUser(UserRole $role, bool $active = true): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@majority.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => null,
            'is_active' => $active,
        ]);
    }

    private function makeRequest(RequestStatus $status, int $votingRuleVersion): ImportRequest
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
                'voting_rule_version' => $votingRuleVersion,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function castVoteDirectly(ImportRequest $request, User $user, VoteType $vote): void
    {
        RequestVote::query()->create([
            'request_id' => $request->id,
            'user_id' => $user->id,
            'vote' => $vote,
            'justification' => null,
            'voted_at' => now(),
        ]);
    }

    /** @return User[] make $count active EXECUTIVE_MEMBERs (in addition to the director) */
    private function makeMembers(int $count): array
    {
        return array_map(fn () => $this->makeUser(UserRole::EXECUTIVE_MEMBER), range(1, $count));
    }

    // ─── AC1: threshold matrix 6→4, 8→5, 10→6 ────────────────────────────────

    public function test_six_eligible_four_approvals_is_approved(): void
    {
        // director + 5 members = 6 eligible; floor(6/2)+1 = 4 required
        $members = $this->makeMembers(5);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED, 2);

        $this->castVoteDirectly($request, $members[0], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[1], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[2], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[3], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[4], VoteType::REJECT);

        $updated = $this->votingService->finalize($request, $this->director);

        $this->assertEquals(RequestStatus::EXECUTIVE_APPROVED, $updated->status);
    }

    public function test_six_eligible_three_approvals_is_not_eligible(): void
    {
        $members = $this->makeMembers(5);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED, 2);

        $this->castVoteDirectly($request, $members[0], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[1], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[2], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[3], VoteType::REJECT);

        $updated = $this->votingService->finalize($request, $this->director);

        $this->assertEquals(RequestStatus::EXECUTIVE_REJECTED, $updated->status);
    }

    public function test_eight_eligible_five_approvals_is_approved(): void
    {
        // director + 7 members = 8 eligible; floor(8/2)+1 = 5 required
        $members = $this->makeMembers(7);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED, 2);

        for ($i = 0; $i < 5; $i++) {
            $this->castVoteDirectly($request, $members[$i], VoteType::APPROVE);
        }

        $updated = $this->votingService->finalize($request, $this->director);

        $this->assertEquals(RequestStatus::EXECUTIVE_APPROVED, $updated->status);
    }

    public function test_eight_eligible_four_approvals_is_not_eligible(): void
    {
        $members = $this->makeMembers(7);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED, 2);

        for ($i = 0; $i < 4; $i++) {
            $this->castVoteDirectly($request, $members[$i], VoteType::APPROVE);
        }

        $updated = $this->votingService->finalize($request, $this->director);

        $this->assertEquals(RequestStatus::EXECUTIVE_REJECTED, $updated->status);
    }

    public function test_ten_eligible_six_approvals_is_approved(): void
    {
        // director + 9 members = 10 eligible; floor(10/2)+1 = 6 required
        $members = $this->makeMembers(9);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED, 2);

        for ($i = 0; $i < 6; $i++) {
            $this->castVoteDirectly($request, $members[$i], VoteType::APPROVE);
        }

        $updated = $this->votingService->finalize($request, $this->director);

        $this->assertEquals(RequestStatus::EXECUTIVE_APPROVED, $updated->status);
    }

    // ─── AC3: even split → Not-Eligible, no tie-break ─────────────────────────

    public function test_even_split_three_three_is_not_eligible(): void
    {
        // director + 5 members = 6 eligible; 3 approve vs 3 reject → 3 < 4 required
        $members = $this->makeMembers(5);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED, 2);

        $this->castVoteDirectly($request, $members[0], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[1], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[2], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[3], VoteType::REJECT);
        $this->castVoteDirectly($request, $members[4], VoteType::REJECT);
        $this->castVoteDirectly($request, $this->director, VoteType::REJECT);

        $updated = $this->votingService->finalize($request, $this->director);

        $this->assertEquals(RequestStatus::EXECUTIVE_REJECTED, $updated->status);
    }

    // ─── AC2: inactive executives excluded from eligible count ────────────────

    public function test_inactive_executives_excluded_from_threshold(): void
    {
        // director + 4 active members + 2 inactive = 5 eligible; floor(5/2)+1 = 3 required
        $members = $this->makeMembers(4);
        $this->makeUser(UserRole::EXECUTIVE_MEMBER, active: false);
        $this->makeUser(UserRole::EXECUTIVE_MEMBER, active: false);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED, 2);

        $this->castVoteDirectly($request, $members[0], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[1], VoteType::APPROVE);
        $this->castVoteDirectly($request, $members[2], VoteType::APPROVE);

        $updated = $this->votingService->finalize($request, $this->director);

        // 3 approvals meets the 3-required threshold only if inactive members are excluded
        $this->assertEquals(RequestStatus::EXECUTIVE_APPROVED, $updated->status);
    }

    // ─── AC3: override rejected for v2; v1 unaffected ─────────────────────────

    public function test_override_rejected_for_v2(): void
    {
        $this->makeMembers(3);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN, 2);

        $this->expectException(VotingException::class);
        $this->votingService->overrideAndFinalize($request, $this->director, VoteType::APPROVE, 'Override reason');
    }

    public function test_override_succeeds_for_v1(): void
    {
        $members = $this->makeMembers(2);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN, 1);
        $this->castVoteDirectly($request, $members[0], VoteType::REJECT);

        $updated = $this->votingService->overrideAndFinalize($request, $this->director, VoteType::APPROVE, 'Override reason');

        $this->assertEquals(RequestStatus::EXECUTIVE_APPROVED, $updated->status);
    }

    // ─── AC4: v1 tie-break unchanged (no recompute under new rule) ────────────

    public function test_v1_tie_break_still_resolves_via_director_vote(): void
    {
        // Genuine tie: 1 member REJECT vs Director APPROVE → approve == reject == 1
        $members = $this->makeMembers(1);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED, 1);
        $this->castVoteDirectly($request, $members[0], VoteType::REJECT);
        $this->castVoteDirectly($request, $this->director, VoteType::APPROVE);

        $updated = $this->votingService->finalize($request, $this->director);

        // Legacy rule: tie resolved by Director APPROVE vote
        $this->assertEquals(RequestStatus::EXECUTIVE_APPROVED, $updated->status);
    }
}
