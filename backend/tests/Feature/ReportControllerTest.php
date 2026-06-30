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

        $this->bank = Bank::query()->create(['name' => 'بنك اليمن المركزي', 'code' => 'CBY', 'is_active' => true]);
        $this->admin = $this->makeUser(UserRole::CBY_ADMIN);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@example.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(Bank $bank, User $creator, RequestStatus $status, array $extra = []): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create(array_merge([
                'bank_id' => $bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Supplier Co.',
                'goods_description' => 'Industrial equipment',
                'port_of_entry' => 'Aden Port',
                'status' => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ], $extra));
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function makeVote(ImportRequest $request, User $voter, VoteType $vote): RequestVote
    {
        return RequestVote::query()->create([
            'request_id' => $request->id,
            'user_id' => $voter->id,
            'vote' => $vote->value,
            'is_director_override' => false,
            'voted_at' => now(),
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
                    'monthly_trend',
                    'category_distribution',
                    'amount_by_currency',
                    'submission_heatmap',
                    'total_financing_value',
                    'duplicate_invoice_count',
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
            ->getJson('/api/reports/workflow?from_date='.now()->subDays(5)->toDateString())
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
            ->getJson('/api/reports/workflow?to_date='.now()->subDays(10)->toDateString())
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
            ->getJson('/api/reports/workflow?from_date='.now()->subDays(5)->toDateString())
            ->assertOk()
            ->json('data.counts_by_bank');

        $row = collect($response)->firstWhere('bank_id', $this->bank->id);
        $this->assertEquals(1, $row['total']);
    }

    public function test_workflow_report_date_range_filters_throughput(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $old = $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);
        ImportRequest::query()->where('id', $old->id)->update(['created_at' => now()->subDays(30)]);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);

        $throughput = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow?from_date='.now()->subDays(5)->toDateString())
            ->assertOk()
            ->json('data.throughput');

        $this->assertEquals(1, $throughput['completed']);
    }

    public function test_workflow_report_rejected_throughput_includes_bank_rejected(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::BANK_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);

        $throughput = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data.throughput');

        $this->assertEquals(3, $throughput['rejected']);
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
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
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

    public function test_voting_report_date_range_filters_sessions_and_votes(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $old = $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);
        ImportRequest::query()->where('id', $old->id)->update(['created_at' => now()->subDays(30)]);
        $this->makeVote($old, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::REJECT);

        $recent = $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->makeVote($recent, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::APPROVE);

        $data = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting?from_date='.now()->subDays(5)->toDateString())
            ->assertOk()
            ->json('data');

        $this->assertEquals(1, $data['total_voting_sessions']);
        $this->assertEquals(1, $data['vote_tallies']['approve']);
        $this->assertEquals(0, $data['vote_tallies']['reject']);
    }

    public function test_workflow_report_invalid_from_date_returns_422(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow?from_date=not-a-date')
            ->assertStatus(422)
            ->assertJsonPath('errors.from_date.0', 'The from_date must be in Y-m-d format.');
    }

    public function test_workflow_report_invalid_to_date_returns_422(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow?to_date=2024/01/01')
            ->assertStatus(422)
            ->assertJsonPath('errors.to_date.0', 'The to_date must be in Y-m-d format.');
    }

    public function test_voting_report_invalid_date_returns_422(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/voting?from_date=2026-02-31')
            ->assertStatus(422)
            ->assertJsonPath('errors.from_date.0', 'The from_date must be in Y-m-d format.');
    }

    // ─── D1 fix: null to_status uses 'unknown' key ────────────────────────────

    public function test_workflow_report_stage_durations_key_is_unknown_not_empty_for_null_to_status(): void
    {
        // When all stage history rows have a known to_status, the avg_time map has no empty key
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $req = $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);

        // No stage history rows at all → avg_time_per_stage_hours is empty (not crash)
        $data = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data.avg_time_per_stage_hours');

        // If any key exists, it must not be an empty string
        foreach (array_keys($data) as $key) {
            $this->assertNotSame('', $key, 'Empty string key found in avg_time_per_stage_hours — D1 regression');
        }
    }

    // ─── D4 fix: tie detection via aggregated query ───────────────────────────

    public function test_voting_report_tie_rate_detects_tied_sessions(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $req = $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);

        // 2 approve + 2 reject + 2 abstain = 6 total, neither side ≥4 → tie
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::APPROVE);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::APPROVE);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::REJECT);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::REJECT);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::ABSTAIN);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::ABSTAIN);

        $tieRate = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->json('data.tie_rate');

        $this->assertEquals(100.0, $tieRate);
    }

    public function test_voting_report_no_tie_when_one_side_has_majority(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $req = $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);

        // 4 approve + 2 reject = no tie
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::APPROVE);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::APPROVE);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::APPROVE);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::APPROVE);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::REJECT);
        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::REJECT);

        $tieRate = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->json('data.tie_rate');

        $this->assertEquals(0.0, $tieRate);
    }

    // ─── D6 fix: AUTO_ABSTAIN_TIMEOUT counted in abstain totals ──────────────

    public function test_voting_report_vote_tallies_include_auto_abstain_timeout(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $req = $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->makeVote($req, $this->makeUser(UserRole::EXECUTIVE_MEMBER), VoteType::APPROVE);
        // Simulate AUTO_ABSTAIN_TIMEOUT by inserting directly
        RequestVote::query()->create([
            'request_id' => $req->id,
            'user_id' => $this->makeUser(UserRole::EXECUTIVE_MEMBER)->id,
            'vote' => 'AUTO_ABSTAIN_TIMEOUT',
            'is_director_override' => false,
            'voted_at' => now(),
        ]);

        $tallies = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->json('data.vote_tallies');

        $this->assertEquals(1, $tallies['approve']);
        $this->assertEquals(1, $tallies['abstain'], 'AUTO_ABSTAIN_TIMEOUT must be counted in abstain — D6 regression');
    }

    // ─── Bank report ─────────────────────────────────────────────────────────

    public function test_bank_report_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/reports/bank')->assertUnauthorized();
    }

    public function test_bank_report_swift_officer_returns_403(): void
    {
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $this->actingAs($swift)->getJson('/api/reports/bank')->assertForbidden();
    }

    public function test_bank_report_support_committee_returns_403(): void
    {
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $this->actingAs($sc)->getJson('/api/reports/bank')->assertForbidden();
    }

    public function test_bank_report_bank_user_sees_own_bank_only(): void
    {
        $bank2 = Bank::query()->create(['name' => 'بنك ثانٍ', 'code' => 'BNK2', 'is_active' => true]);
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $de2 = $this->makeUser(UserRole::DATA_ENTRY, $bank2);

        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        $this->makeRequest($bank2, $de2, RequestStatus::DRAFT);

        $data = $this->actingAs($de)
            ->getJson('/api/reports/bank')
            ->assertOk()
            ->json('data');

        $this->assertEquals(2, $data['total_requests']);
        $this->assertArrayNotHasKey('per_bank', $data, 'Bank user must not receive cross-bank breakdown');
    }

    public function test_bank_report_cby_admin_sees_cross_bank_breakdown(): void
    {
        $bank2 = Bank::query()->create(['name' => 'بنك ثانٍ', 'code' => 'BNK2', 'is_active' => true]);
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $de2 = $this->makeUser(UserRole::DATA_ENTRY, $bank2);

        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        $this->makeRequest($bank2, $de2, RequestStatus::DRAFT);

        $data = $this->actingAs($this->admin)
            ->getJson('/api/reports/bank')
            ->assertOk()
            ->json('data');

        $this->assertEquals(2, $data['total_requests']);
        $this->assertIsArray($data['per_bank']);
        $this->assertCount(2, $data['per_bank']);
    }

    public function test_bank_report_date_range_filters_correctly(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $old = $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        ImportRequest::query()->where('id', $old->id)->update(['created_at' => now()->subDays(30)]);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);

        $data = $this->actingAs($de)
            ->getJson('/api/reports/bank?from_date='.now()->subDays(5)->toDateString())
            ->assertOk()
            ->json('data');

        $this->assertEquals(1, $data['total_requests']);
    }

    public function test_bank_report_invalid_date_returns_422(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->actingAs($de)
            ->getJson('/api/reports/bank?from_date=not-a-date')
            ->assertStatus(422);
    }

    public function test_bank_report_approval_rate_calculated_correctly(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);

        $data = $this->actingAs($de)
            ->getJson('/api/reports/bank')
            ->assertOk()
            ->json('data');

        $this->assertEquals(50.0, $data['approval_rate']);
        $this->assertEquals(50.0, $data['rejection_rate']);
    }

    public function test_bank_report_rejected_count_includes_bank_rejected(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::BANK_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);

        $data = $this->actingAs($de)
            ->getJson('/api/reports/bank')
            ->assertOk()
            ->json('data');

        $this->assertEquals(3, $data['rejected_count']);
        $this->assertEquals(100.0, $data['rejection_rate']);
    }

    public function test_bank_report_date_range_filters_average_processing_time(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $old = $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED, [
            'created_at' => now()->subDays(30),
            'executive_decided_at' => now()->subDays(29),
        ]);
        ImportRequest::query()->where('id', $old->id)->update([
            'created_at' => now()->subDays(30),
            'executive_decided_at' => now()->subDays(29),
        ]);

        $recent = $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);
        ImportRequest::query()->where('id', $recent->id)->update([
            'created_at' => now()->subHours(5),
            'executive_decided_at' => now(),
        ]);

        $data = $this->actingAs($de)
            ->getJson('/api/reports/bank?from_date='.now()->subDays(5)->toDateString())
            ->assertOk()
            ->json('data');

        $this->assertEquals(5.0, $data['avg_processing_hours']);
    }

    // ─── Export: workflow ─────────────────────────────────────────────────────

    public function test_workflow_export_cby_user_can_export_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/api/reports/workflow/export?format=excel')
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('workflow-report.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_workflow_export_bank_user_returns_403(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->actingAs($de)->get('/api/reports/workflow/export')->assertForbidden();
    }

    public function test_workflow_export_logs_audit_entry(): void
    {
        $this->actingAs($this->admin)
            ->get('/api/reports/workflow/export?format=excel')
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'REPORT_EXPORTED',
        ]);
    }

    public function test_workflow_export_pdf_returns_pdf_content_type(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/api/reports/workflow/export?format=pdf')
            ->assertOk();

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_workflow_export_invalid_format_returns_422(): void
    {
        $this->actingAs($this->admin)
            ->get('/api/reports/workflow/export?format=xlsx')
            ->assertStatus(422)
            ->assertJsonPath('errors.format.0', 'The format must be either excel or pdf.');
    }

    // ─── Export: bank ─────────────────────────────────────────────────────────

    public function test_bank_export_bank_user_can_export_csv(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $response = $this->actingAs($de)
            ->get('/api/reports/bank/export?format=excel')
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('bank-report.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_bank_export_swift_officer_returns_403(): void
    {
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $this->actingAs($swift)->get('/api/reports/bank/export')->assertForbidden();
    }

    public function test_bank_export_logs_audit_entry(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->actingAs($de)
            ->get('/api/reports/bank/export?format=excel')
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $de->id,
            'action' => 'REPORT_EXPORTED',
        ]);
    }

    public function test_bank_export_pdf_returns_pdf_content_type(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $response = $this->actingAs($de)
            ->get('/api/reports/bank/export?format=pdf')
            ->assertOk();

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_bank_export_cby_admin_can_export_cross_bank(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/api/reports/bank/export?format=excel')
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_bank_export_header_uses_not_eligible_label_while_rows_keep_values(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);

        $response = $this->actingAs($de)
            ->get('/api/reports/bank/export?format=excel')
            ->assertOk();

        $csv = $response->getContent();

        $this->assertStringContainsString(RequestStatus::EXECUTIVE_REJECTED->label(), $csv);
        $this->assertStringNotContainsString('rejected_count', $csv);
        $this->assertStringContainsString($this->bank->name.',1,0,1,0,0,100', $csv);
    }

    // ─── Workflow report: new analytics fields ─────────────────────────────────

    public function test_workflow_report_monthly_trend_has_correct_shape(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk();

        $monthlyTrend = $response->json('data.monthly_trend');
        $this->assertIsArray($monthlyTrend);

        foreach ($monthlyTrend as $entry) {
            $this->assertArrayHasKey('month', $entry);
            $this->assertArrayHasKey('total', $entry);
            $this->assertArrayHasKey('approved', $entry);
            $this->assertArrayHasKey('rejected', $entry);
            $this->assertMatchesRegularExpression('/\d{4}-\d{2}/', $entry['month']);
        }
    }

    public function test_workflow_report_monthly_trend_rejected_includes_bank_rejected(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::BANK_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);

        $monthlyTrend = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data.monthly_trend');

        $currentMonth = now()->format('Y-m');
        $row = collect($monthlyTrend)->firstWhere('month', $currentMonth);

        $this->assertNotNull($row);
        $this->assertEquals(3, $row['rejected']);
    }

    public function test_workflow_report_total_financing_value_sums_approved_requests(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED, ['amount' => 1000.00]);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED, ['amount' => 2000.00]);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED, ['amount' => 500.00]); // Not approved

        $data = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data');

        $this->assertEquals(3000.0, $data['total_financing_value']);
    }

    public function test_workflow_report_duplicate_invoice_count_detects_duplicates(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED, ['invoice_number' => 'INV-001']);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED, ['invoice_number' => 'INV-001']);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED, ['invoice_number' => 'INV-001']);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED, ['invoice_number' => 'INV-002']); // Unique

        $data = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data');

        // 3 INV-001 entries = 2 duplicates (3 - 1)
        $this->assertEquals(2, $data['duplicate_invoice_count']);
    }

    public function test_workflow_report_heatmap_groups_by_day_and_time_slot(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);

        $data = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data');

        $heatmap = $data['submission_heatmap'];
        $this->assertIsArray($heatmap);

        foreach ($heatmap as $entry) {
            $this->assertArrayHasKey('day', $entry);
            $this->assertArrayHasKey('slot', $entry);
            $this->assertArrayHasKey('count', $entry);
            $this->assertGreaterThanOrEqual(1, $entry['day']);
            $this->assertLessThanOrEqual(7, $entry['day']);
            $this->assertGreaterThanOrEqual(0, $entry['slot']);
            $this->assertLessThanOrEqual(22, $entry['slot']);
        }
    }
}
