<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VoteType;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Permission;
use App\Models\RequestVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        Permission::query()->firstOrCreate(
            ['slug' => 'request.create'],
            ['name_ar' => 'إنشاء طلب', 'name_en' => 'Create Request', 'group' => 'requests']
        );

        $this->bank  = Bank::query()->create(['name' => 'بنك اليمن المركزي', 'code' => 'CBY', 'is_active' => true]);
        $this->admin = $this->makeUser(UserRole::CBY_ADMIN);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;
        return User::query()->create([
            'name'      => "User {$counter}",
            'email'     => "user{$counter}@example.com",
            'password'  => Hash::make('password'),
            'role'      => $role->value,
            'bank_id'   => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(Bank $bank, User $creator, RequestStatus $status, array $extra = []): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create(array_merge([
                'bank_id'            => $bank->id,
                'created_by'         => $creator->id,
                'currency'           => 'USD',
                'amount'             => 10000.00,
                'supplier_name'      => 'Supplier Co.',
                'goods_description'  => 'Industrial equipment',
                'port_of_entry'      => 'Aden Port',
                'status'             => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ], $extra));
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function makeVote(ImportRequest $request, User $voter, VoteType $vote): RequestVote
    {
        return RequestVote::query()->create([
            'request_id'          => $request->id,
            'user_id'             => $voter->id,
            'vote'                => $vote->value,
            'is_director_override' => false,
            'voted_at'            => now(),
        ]);
    }

    // ─── Authorization ────────────────────────────────────────────────────────

    public function test_workflow_report_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/reports/workflow')->assertUnauthorized();
    }

    public function test_workflow_report_bank_user_returns_403(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->actingAs($de)->getJson('/api/reports/workflow')->assertForbidden();
    }

    public function test_voting_report_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/reports/voting')->assertUnauthorized();
    }

    public function test_voting_report_bank_user_returns_403(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->actingAs($de)->getJson('/api/reports/voting')->assertForbidden();
    }

    // ─── Workflow report structure ────────────────────────────────────────────

    public function test_workflow_report_returns_expected_keys(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'counts_by_status',
                    'counts_by_bank',
                    'avg_time_per_stage_hours',
                    'throughput',
                ],
            ]);
    }

    public function test_workflow_report_counts_by_status_includes_all_statuses(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data.counts_by_status');

        foreach (RequestStatus::cases() as $status) {
            $this->assertArrayHasKey($status->value, $response, "Missing status key: {$status->value}");
        }
    }

    public function test_workflow_report_counts_by_status_correct_totals(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data.counts_by_status');

        $this->assertEquals(1, $response[RequestStatus::DRAFT->value]);
        $this->assertEquals(2, $response[RequestStatus::SUBMITTED->value]);
    }

    public function test_workflow_report_counts_by_bank_includes_all_banks(): void
    {
        $bank2 = Bank::query()->create(['name' => 'بنك آخر', 'code' => 'OTH', 'is_active' => true]);
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data.counts_by_bank');

        $bankIds = array_column($response, 'bank_id');
        $this->assertContains($this->bank->id, $bankIds);
        $this->assertContains($bank2->id, $bankIds);
    }

    public function test_workflow_report_counts_by_bank_zero_for_bank_with_no_requests(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $bank2 = Bank::query()->create(['name' => 'بنك آخر', 'code' => 'OTH2', 'is_active' => true]);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data.counts_by_bank');

        $bank2Row = collect($response)->firstWhere('bank_id', $bank2->id);
        $this->assertNotNull($bank2Row);
        $this->assertEquals(0, $bank2Row['total']);
    }

    // ─── Workflow report — date-range filtering ───────────────────────────────

    public function test_workflow_report_from_date_filters_requests_created_after(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        // Old request — before filter
        $old = $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        ImportRequest::query()->where('id', $old->id)->update(['created_at' => now()->subDays(30)]);

        // New request — should appear
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow?from_date=' . now()->subDays(5)->toDateString())
            ->assertOk()
            ->json('data.counts_by_status');

        $this->assertEquals(1, $response[RequestStatus::SUBMITTED->value]);
    }

    public function test_workflow_report_to_date_filters_requests_created_before(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        // Old request — should appear
        $old = $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        ImportRequest::query()->where('id', $old->id)->update(['created_at' => now()->subDays(20)]);

        // New request — after to_date, excluded
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow?to_date=' . now()->subDays(10)->toDateString())
            ->assertOk()
            ->json('data.counts_by_status');

        $this->assertEquals(1, $response[RequestStatus::SUBMITTED->value]);
    }

    public function test_workflow_report_date_range_filters_counts_by_bank(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $old = $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        ImportRequest::query()->where('id', $old->id)->update(['created_at' => now()->subDays(20)]);

        $new = $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow?from_date=' . now()->subDays(5)->toDateString())
            ->assertOk()
            ->json('data.counts_by_bank');

        $row = collect($response)->firstWhere('bank_id', $this->bank->id);
        $this->assertEquals(1, $row['total']);
    }

    // ─── Voting report structure ───────────────────────────────────────────────

    public function test_voting_report_returns_expected_keys(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_voting_sessions',
                    'vote_tallies' => ['approve', 'reject', 'abstain'],
                    'approval_rate',
                    'rejection_rate',
                    'tie_rate',
                    'avg_time_to_decision_hours',
                ],
            ]);
    }

    public function test_voting_report_total_voting_sessions_counts_requests_in_voting_states(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED); // Not a voting state

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->json('data.total_voting_sessions');

        $this->assertEquals(3, $response);
    }

    public function test_voting_report_vote_tallies_aggregate_correctly(): void
    {
        $de  = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $req = $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);

        // Each executive member can only vote once per request (unique constraint)
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::APPROVE);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::APPROVE);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::REJECT);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::ABSTAIN);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->json('data.vote_tallies');

        $this->assertEquals(2, $response['approve']);
        $this->assertEquals(1, $response['reject']);
        $this->assertEquals(1, $response['abstain']);
    }

    public function test_voting_report_approval_rate_is_zero_when_no_decided_requests(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->assertJsonPath('data.approval_rate', 0);
    }
}
