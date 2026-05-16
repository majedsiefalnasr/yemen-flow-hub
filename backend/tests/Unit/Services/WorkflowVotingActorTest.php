<?php

namespace Tests\Unit\Services;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VotingSessionStatus;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use App\Services\Workflow\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowVotingActorTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $service;
    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkflowService::class);
        $this->bank = Bank::query()->create(['name' => 'Test Bank', 'code' => 'TB', 'is_active' => true]);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;
        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@votingtest.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(RequestStatus $status): ImportRequest
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
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
                'current_owner_role' => UserRole::COMMITTEE_DIRECTOR,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    public function test_open_voting_sets_voting_opened_by_and_at(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $request = $this->makeRequest(RequestStatus::WAITING_FOR_VOTING_OPEN);

        $updated = $this->service->transition($request, 'open_voting', $director);

        $this->assertEquals($director->id, $updated->voting_opened_by);
        $this->assertNotNull($updated->voting_opened_at);
    }

    public function test_open_voting_sets_voting_session_status_to_open(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $request = $this->makeRequest(RequestStatus::WAITING_FOR_VOTING_OPEN);

        $updated = $this->service->transition($request, 'open_voting', $director);

        $this->assertEquals(VotingSessionStatus::OPEN, $updated->voting_session_status);
    }

    public function test_close_voting_sets_voting_closed_by_and_at(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $updated = $this->service->transition($request, 'close_voting', $director);

        $this->assertEquals($director->id, $updated->voting_closed_by);
        $this->assertNotNull($updated->voting_closed_at);
    }

    public function test_close_voting_sets_voting_session_status_to_closed(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_OPEN);

        $updated = $this->service->transition($request, 'close_voting', $director);

        $this->assertEquals(VotingSessionStatus::CLOSED, $updated->voting_session_status);
    }

    public function test_finalize_approved_sets_voting_session_status_to_finalized(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);

        $updated = $this->service->transition($request, 'finalize_approved', $director);

        $this->assertEquals(VotingSessionStatus::FINALIZED, $updated->voting_session_status);
        $this->assertEquals(RequestStatus::EXECUTIVE_APPROVED, $updated->status);
    }

    public function test_finalize_rejected_sets_voting_session_status_to_finalized(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $request = $this->makeRequest(RequestStatus::EXECUTIVE_VOTING_CLOSED);

        // finalize_rejected has next_owner=null in TransitionMap — use forceFill guard-free
        $this->assertDatabaseHas('import_requests', ['id' => $request->id]);

        $this->assertNotNull($request->id);

        // Transition via WorkflowService; verify voting_session_status is set to FINALIZED
        // Note: finalize_rejected sets next_owner=null which requires SQLite-compat handling
        $updated = $this->service->transition($request, 'finalize_rejected', $director, null, []);

        $this->assertEquals(VotingSessionStatus::FINALIZED, $updated->voting_session_status);
        $this->assertEquals(RequestStatus::EXECUTIVE_REJECTED, $updated->status);
    }

    public function test_open_voting_creates_stage_history_and_audit_log(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $request = $this->makeRequest(RequestStatus::WAITING_FOR_VOTING_OPEN);

        $this->service->transition($request, 'open_voting', $director);

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'action' => 'open_voting',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => ImportRequest::class,
            'subject_id' => $request->id,
            'user_id' => $director->id,
        ]);
    }
}
