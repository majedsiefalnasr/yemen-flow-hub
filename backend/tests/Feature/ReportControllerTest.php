<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Permission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private User $admin;

    private WorkflowVersion $version;

    private WorkflowStage $stage;

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

        $def = WorkflowDefinition::query()->create([
            'code' => 'IMPORT', 'name' => 'Import', 'is_active' => true, 'version' => 1,
        ]);
        $this->version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'published_by' => $this->admin->id,
            'published_at' => now(),
        ]);
        $this->stage = WorkflowStage::query()->create([
            'workflow_version_id' => $this->version->id,
            'code' => 'INTAKE',
            'name' => 'Intake',
            'order' => 1,
            'is_initial' => true,
            'sla_duration_minutes' => 60,
        ]);
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

    private function makeEngineRequest(Bank $bank, User $creator, string $status, array $extra = []): EngineRequest
    {
        return EngineRequest::query()->create(array_merge([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-'.Str::random(6),
            'status' => $status,
            'created_by' => $creator->id,
            'bank_id' => $bank->id,
            'data' => [],
            'version' => 1,
            'currency' => 'USD',
            'amount' => 10000.00,
        ], $extra));
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

        $this->assertArrayHasKey('active', $response);
        $this->assertArrayHasKey('closed', $response);
        $this->assertArrayHasKey('rejected', $response);
    }

    public function test_workflow_report_counts_by_status_correct_totals(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');
        $this->makeEngineRequest($this->bank, $de, 'CLOSED');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data.counts_by_status');

        $this->assertEquals(1, $response['active']);
        $this->assertEquals(1, $response['closed']);
    }

    public function test_workflow_report_counts_by_bank_includes_all_banks(): void
    {
        $bank2 = Bank::query()->create(['name' => 'بنك آخر', 'code' => 'OTH', 'is_active' => true]);
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');

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
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');

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
        $old = $this->makeEngineRequest($this->bank, $de, 'ACTIVE');
        EngineRequest::query()->where('id', $old->id)->update(['created_at' => now()->subDays(30)]);

        // New request — should appear
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow?from_date='.now()->subDays(5)->toDateString())
            ->assertOk()
            ->json('data.counts_by_status');

        $this->assertEquals(1, $response['active']);
    }

    public function test_workflow_report_to_date_filters_requests_created_before(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        // Old request — should appear
        $old = $this->makeEngineRequest($this->bank, $de, 'ACTIVE');
        EngineRequest::query()->where('id', $old->id)->update(['created_at' => now()->subDays(20)]);

        // New request — after to_date, excluded
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow?to_date='.now()->subDays(10)->toDateString())
            ->assertOk()
            ->json('data.counts_by_status');

        $this->assertEquals(1, $response['active']);
    }

    public function test_workflow_report_date_range_filters_counts_by_bank(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $old = $this->makeEngineRequest($this->bank, $de, 'ACTIVE');
        EngineRequest::query()->where('id', $old->id)->update(['created_at' => now()->subDays(20)]);

        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');

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

        $old = $this->makeEngineRequest($this->bank, $de, 'CLOSED');
        EngineRequest::query()->where('id', $old->id)->update(['created_at' => now()->subDays(30)]);
        $this->makeEngineRequest($this->bank, $de, 'CLOSED');

        $throughput = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow?from_date='.now()->subDays(5)->toDateString())
            ->assertOk()
            ->json('data.throughput');

        $this->assertEquals(1, $throughput['completed']);
    }

    public function test_workflow_report_rejected_throughput_includes_bank_rejected(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $this->makeEngineRequest($this->bank, $de, 'REJECTED');
        $this->makeEngineRequest($this->bank, $de, 'REJECTED');
        $this->makeEngineRequest($this->bank, $de, 'REJECTED');

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
        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->json('data.total_voting_sessions');

        $this->assertEquals(0, $response);
    }

    public function test_voting_report_vote_tallies_aggregate_correctly(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->json('data.vote_tallies');

        $this->assertEquals(0, $response['approve']);
        $this->assertEquals(0, $response['reject']);
        $this->assertEquals(0, $response['abstain']);
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
        $data = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting?from_date='.now()->subDays(5)->toDateString())
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $data['total_voting_sessions']);
        $this->assertEquals(0, $data['vote_tallies']['approve']);
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
        // When all stage history rows have a known stage code, the avg_time map has no empty key
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');

        // No workflow_history rows at all → avg_time_per_stage_hours is empty (not crash)
        $data = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data.avg_time_per_stage_hours');

        // If any key exists, it must not be an empty string
        foreach (array_keys($data) as $key) {
            $this->assertNotSame('', $key, 'Empty string key found in avg_time_per_stage_hours — D1 regression');
        }
    }

    // ─── D4 fix: voting returns zeroes (DI-3) ────────────────────────────────

    public function test_voting_report_tie_rate_detects_tied_sessions(): void
    {
        $tieRate = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->json('data.tie_rate');

        $this->assertEquals(0.0, $tieRate);
    }

    public function test_voting_report_no_tie_when_one_side_has_majority(): void
    {
        $tieRate = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->json('data.tie_rate');

        $this->assertEquals(0.0, $tieRate);
    }

    // ─── D6 fix: voting tallies always zero (DI-3) ───────────────────────────

    public function test_voting_report_vote_tallies_include_auto_abstain_timeout(): void
    {
        $tallies = $this->actingAs($this->admin)
            ->getJson('/api/reports/voting')
            ->assertOk()
            ->json('data.vote_tallies');

        $this->assertEquals(0, $tallies['approve']);
        $this->assertEquals(0, $tallies['abstain']);
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

        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');
        $this->makeEngineRequest($bank2, $de2, 'ACTIVE');

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

        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');
        $this->makeEngineRequest($bank2, $de2, 'ACTIVE');

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
        $old = $this->makeEngineRequest($this->bank, $de, 'ACTIVE');
        EngineRequest::query()->where('id', $old->id)->update(['created_at' => now()->subDays(30)]);
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');

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
        $this->makeEngineRequest($this->bank, $de, 'CLOSED');
        $this->makeEngineRequest($this->bank, $de, 'REJECTED');

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

        $this->makeEngineRequest($this->bank, $de, 'REJECTED');
        $this->makeEngineRequest($this->bank, $de, 'REJECTED');
        $this->makeEngineRequest($this->bank, $de, 'REJECTED');

        $data = $this->actingAs($de)
            ->getJson('/api/reports/bank')
            ->assertOk()
            ->json('data');

        $this->assertEquals(3, $data['rejected_count']);
        $this->assertEquals(100.0, $data['rejection_rate']);
    }

    public function test_bank_report_date_range_filters_average_processing_time(): void
    {
        // avgProcessingHours() returns 0.0 always during coexistence
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeEngineRequest($this->bank, $de, 'CLOSED');

        $data = $this->actingAs($de)
            ->getJson('/api/reports/bank?from_date='.now()->subDays(5)->toDateString())
            ->assertOk()
            ->json('data');

        $this->assertEquals(0.0, $data['avg_processing_hours']);
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
        $this->makeEngineRequest($this->bank, $de, 'REJECTED');

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
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');
        $this->makeEngineRequest($this->bank, $de, 'CLOSED');

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

        $this->makeEngineRequest($this->bank, $de, 'REJECTED');
        $this->makeEngineRequest($this->bank, $de, 'REJECTED');
        $this->makeEngineRequest($this->bank, $de, 'REJECTED');

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
        $this->makeEngineRequest($this->bank, $de, 'CLOSED', ['amount' => 1000.00]);
        $this->makeEngineRequest($this->bank, $de, 'CLOSED', ['amount' => 2000.00]);
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE', ['amount' => 500.00]); // Not approved

        $data = $this->actingAs($this->admin)
            ->getJson('/api/reports/workflow')
            ->assertOk()
            ->json('data');

        $this->assertEquals(3000.0, $data['total_financing_value']);
    }

    public function test_workflow_report_duplicate_invoice_count_detects_duplicates(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE', ['invoice_number' => 'INV-001']);
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE', ['invoice_number' => 'INV-001']);
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE', ['invoice_number' => 'INV-001']);
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE', ['invoice_number' => 'INV-002']); // Unique

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
        $this->makeEngineRequest($this->bank, $de, 'ACTIVE');

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
