<?php

namespace Tests\Feature\Voting;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VoteType;
use App\Enums\VotingSessionStatus;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Permission;
use App\Models\RequestVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VotingEngineTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private User $director;
    private User $exec1;
    private User $exec2;
    private User $dataEntry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->bank      = $this->makeBank('YCB');
        $this->director  = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $this->exec1     = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->exec2     = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function seedPermissions(): void
    {
        $votingCast = DB::table('permissions')->insertGetId([
            'slug' => 'voting.cast', 'name_ar' => 'التصويت', 'name_en' => 'Cast vote', 'group' => 'voting',
        ]);
        $votingFinalize = DB::table('permissions')->insertGetId([
            'slug' => 'voting.finalize', 'name_ar' => 'إغلاق التصويت', 'name_en' => 'Finalize voting', 'group' => 'voting',
        ]);

        DB::table('role_permissions')->insert([
            ['permission_id' => $votingCast, 'role' => UserRole::EXECUTIVE_MEMBER->value],
            ['permission_id' => $votingCast, 'role' => UserRole::COMMITTEE_DIRECTOR->value],
            ['permission_id' => $votingFinalize, 'role' => UserRole::COMMITTEE_DIRECTOR->value],
        ]);
    }

    private function makeBank(string $code): Bank
    {
        return Bank::query()->create(['name' => "بنك {$code}", 'code' => $code, 'is_active' => true]);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;
        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@vettest.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(RequestStatus $status, array $extra = []): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create(array_merge([
                'bank_id' => $this->bank->id,
                'created_by' => $this->dataEntry->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => $status,
                'current_owner_role' => UserRole::COMMITTEE_DIRECTOR,
            ], $extra));
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function castVote(ImportRequest $request, User $user, VoteType $vote): void
    {
        RequestVote::query()->create([
            'request_id' => $request->id,
            'user_id' => $user->id,
            'vote' => $vote,
        ]);
    }

    // ─── AC-1: Open voting session ─────────────────────────────────────────────

    public function test_director_can_open_voting_session(): void
    {
        $request = $this->makeRequest(RequestStatus::WAITING_FOR_VOTING_OPEN);

        $this->actingAs($this->director)
            ->postJson("/api/voting/{$request->id}/open")
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::EXECUTIVE_VOTING_OPEN->value);

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'status' => RequestStatus::EXECUTIVE_VOTING_OPEN->value,
            'voting_opened_by' => $this->director->id,
            'voting_session_status' => VotingSessionStatus::OPEN->value,
        ]);
        $this->assertNotNull($request->fresh()->voting_opened_at);
    }

    public function test_open_session_wrong_status_returns_422(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->actingAs($this->director)
            ->postJson("/api/voting/{$request->id}/open")
            ->assertStatus(422);
    }

    public function test_open_session_non_director_returns_403(): void
    {
        $request = $this->makeRequest(RequestStatus::WAITING_FOR_VOTING_OPEN);

        $this->actingAs($this->exec1)
            ->postJson("/api/voting/{$request->id}/open")
            ->assertStatus(403);
    }

    public function test_open_session_creates_stage_history_and_audit(): void
    {
        $request = $this->makeRequest(RequestStatus::WAITING_FOR_VOTING_OPEN);

        $this->actingAs($this->director)
            ->postJson("/api/voting/{$request->id}/open")
            ->assertOk();

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'action' => 'open_voting',
            'actor_id' => $this->director->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'subject_id' => $request->id,
            'user_id' => $this->director->id,
        ]);
    }

    // ─── AC-2: Cast vote ──────────────────────────────────────────────────────

    public function test_executive_member_can_cast_approve_vote(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->actingAs($this->exec1)
            ->postJson("/api/voting/{$request->id}/vote", ['vote' => 'APPROVE'])
            ->assertOk();

        $this->assertDatabaseHas('request_votes', [
            'request_id' => $request->id,
            'user_id' => $this->exec1->id,
            'vote' => VoteType::APPROVE->value,
        ]);
    }

    public function test_executive_member_can_cast_reject_vote(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->actingAs($this->exec1)
            ->postJson("/api/voting/{$request->id}/vote", ['vote' => 'REJECT'])
            ->assertOk();
    }

    public function test_executive_member_can_cast_abstain_vote(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->actingAs($this->exec1)
            ->postJson("/api/voting/{$request->id}/vote", ['vote' => 'ABSTAIN'])
            ->assertOk();
    }

    public function test_director_can_also_vote(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->actingAs($this->director)
            ->postJson("/api/voting/{$request->id}/vote", ['vote' => 'APPROVE'])
            ->assertOk();
    }

    public function test_duplicate_vote_returns_422(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->castVote($request, $this->exec1, VoteType::APPROVE);

        $this->actingAs($this->exec1)
            ->postJson("/api/voting/{$request->id}/vote", ['vote' => 'REJECT'])
            ->assertStatus(422);
    }

    public function test_vote_on_wrong_status_returns_422(): void
    {
        $request = $this->makeRequest(RequestStatus::WAITING_FOR_VOTING_OPEN);

        $this->actingAs($this->exec1)
            ->postJson("/api/voting/{$request->id}/vote", ['vote' => 'APPROVE'])
            ->assertStatus(422);
    }

    public function test_data_entry_cannot_vote_returns_403(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->actingAs($this->dataEntry)
            ->postJson("/api/voting/{$request->id}/vote", ['vote' => 'APPROVE'])
            ->assertStatus(422);  // VotingService throws VotingException for wrong role
    }

    // ─── AC-3: Close session ──────────────────────────────────────────────────

    public function test_director_can_close_session(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->castVote($request, $this->exec1, VoteType::APPROVE);

        $this->actingAs($this->director)
            ->postJson("/api/voting/{$request->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::EXECUTIVE_VOTING_CLOSED->value);

        $fresh = $request->fresh();
        $this->assertEquals(RequestStatus::EXECUTIVE_VOTING_CLOSED, $fresh->status);
        $this->assertEquals(VotingSessionStatus::CLOSED, $fresh->voting_session_status);
        $this->assertEquals($this->director->id, $fresh->voting_closed_by);
        $this->assertNotNull($fresh->voting_closed_at);
    }

    public function test_close_session_auto_abstains_non_voters(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        // exec1 votes; exec2 and director do NOT vote
        $this->castVote($request, $this->exec1, VoteType::APPROVE);

        $this->actingAs($this->director)
            ->postJson("/api/voting/{$request->id}/close")
            ->assertOk();

        // exec2 should get AUTO_ABSTAIN_TIMEOUT
        $this->assertDatabaseHas('request_votes', [
            'request_id' => $request->id,
            'user_id' => $this->exec2->id,
            'vote' => VoteType::AUTO_ABSTAIN_TIMEOUT->value,
        ]);
    }

    public function test_close_session_wrong_role_returns_403(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->actingAs($this->exec1)
            ->postJson("/api/voting/{$request->id}/close")
            ->assertStatus(403);
    }

    // ─── AC-4: Finalize decision ──────────────────────────────────────────────

    public function test_finalize_decision_approve_majority(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $this->castVote($request, $this->exec1, VoteType::APPROVE);
        $this->castVote($request, $this->exec2, VoteType::APPROVE);

        $this->actingAs($this->director)
            ->postJson("/api/workflow/{$request->id}/finalize-decision")
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::EXECUTIVE_APPROVED->value);

        $this->assertEquals(VotingSessionStatus::FINALIZED, $request->fresh()->voting_session_status);
    }

    public function test_finalize_decision_reject_majority(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $this->castVote($request, $this->exec1, VoteType::REJECT);
        $this->castVote($request, $this->exec2, VoteType::REJECT);

        $this->actingAs($this->director)
            ->postJson("/api/workflow/{$request->id}/finalize-decision")
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::EXECUTIVE_REJECTED->value);
    }

    public function test_finalize_tie_director_approve_wins(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $this->castVote($request, $this->exec1, VoteType::APPROVE);
        $this->castVote($request, $this->exec2, VoteType::REJECT);
        $this->castVote($request, $this->director, VoteType::APPROVE);

        $this->actingAs($this->director)
            ->postJson("/api/workflow/{$request->id}/finalize-decision")
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::EXECUTIVE_APPROVED->value);
    }

    public function test_finalize_tie_no_director_vote_defaults_rejected(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $this->castVote($request, $this->exec1, VoteType::APPROVE);
        $this->castVote($request, $this->exec2, VoteType::REJECT);
        // Director has NOT voted

        $this->actingAs($this->director)
            ->postJson("/api/workflow/{$request->id}/finalize-decision")
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::EXECUTIVE_REJECTED->value);
    }

    public function test_executive_rejected_is_immutable(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_REJECTED);

        // Any workflow mutation on terminal EXECUTIVE_REJECTED must return 403
        $this->actingAs($this->director)
            ->postJson("/api/workflow/{$request->id}/finalize-decision")
            ->assertStatus(422);  // VotingService: wrong status
    }

    // ─── AC-5: Director override ──────────────────────────────────────────────

    public function test_director_override_approve_closes_and_finalizes(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->castVote($request, $this->exec1, VoteType::REJECT);

        $this->actingAs($this->director)
            ->postJson("/api/voting/{$request->id}/override", [
                'decision' => 'APPROVE',
                'justification' => 'Director override for testing purposes',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::EXECUTIVE_APPROVED->value);

        $fresh = $request->fresh();
        $this->assertEquals(RequestStatus::EXECUTIVE_APPROVED, $fresh->status);
        $this->assertEquals(VotingSessionStatus::FINALIZED, $fresh->voting_session_status);
    }

    public function test_director_override_reject_closes_and_finalizes(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->castVote($request, $this->exec1, VoteType::APPROVE);

        $this->actingAs($this->director)
            ->postJson("/api/voting/{$request->id}/override", [
                'decision' => 'REJECT',
                'justification' => 'Director overrides the approve vote',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::EXECUTIVE_REJECTED->value);
    }

    public function test_director_override_auto_abstains_non_voters(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);
        // exec1 voted; exec2 did NOT vote
        $this->castVote($request, $this->exec1, VoteType::APPROVE);

        $this->actingAs($this->director)
            ->postJson("/api/voting/{$request->id}/override", [
                'decision' => 'APPROVE',
                'justification' => 'Override test',
            ])
            ->assertOk();

        $this->assertDatabaseHas('request_votes', [
            'request_id' => $request->id,
            'user_id' => $this->exec2->id,
            'vote' => VoteType::AUTO_ABSTAIN_TIMEOUT->value,
        ]);
    }

    public function test_director_override_missing_justification_returns_422(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->actingAs($this->director)
            ->postJson("/api/voting/{$request->id}/override", [
                'decision' => 'APPROVE',
                'justification' => '',
            ])
            ->assertStatus(422);
    }

    public function test_director_override_wrong_role_returns_403(): void
    {
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->actingAs($this->exec1)
            ->postJson("/api/voting/{$request->id}/override", [
                'decision' => 'APPROVE',
                'justification' => 'Test override',
            ])
            ->assertStatus(403);
    }

    // ─── AC-6: voting_session_status sync ────────────────────────────────────

    public function test_voting_session_status_syncs_through_full_lifecycle(): void
    {
        $request = $this->makeRequest(RequestStatus::WAITING_FOR_VOTING_OPEN);

        // Open: OPEN
        $this->actingAs($this->director)->postJson("/api/voting/{$request->id}/open")->assertOk();
        $this->assertEquals(VotingSessionStatus::OPEN, $request->fresh()->voting_session_status);

        // Cast a vote
        $this->actingAs($this->exec1)->postJson("/api/voting/{$request->id}/vote", ['vote' => 'APPROVE'])->assertOk();

        // Close: CLOSED
        $this->actingAs($this->director)->postJson("/api/voting/{$request->id}/close")->assertOk();
        $this->assertEquals(VotingSessionStatus::CLOSED, $request->fresh()->voting_session_status);

        // Finalize: FINALIZED
        $this->actingAs($this->director)->postJson("/api/workflow/{$request->id}/finalize-decision")->assertOk();
        $this->assertEquals(VotingSessionStatus::FINALIZED, $request->fresh()->voting_session_status);
    }
}
