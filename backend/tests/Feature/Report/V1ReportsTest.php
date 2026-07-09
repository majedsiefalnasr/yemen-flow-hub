<?php

namespace Tests\Feature\Report;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class V1ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $bankUser;

    private Bank $bank;

    private WorkflowVersion $version;

    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $cbyOrg = Organization::where('code', 'national_committee')->first();
        $bankOrg = Organization::where('code', 'commercial_banks')->first();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@cby.gov',
            'password' => bcrypt('password'),
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $systemAdminRole = Role::query()->where('code', 'system_admin')->firstOrFail();
        $this->admin->roles()->attach($systemAdminRole->id);

        $this->bank = Bank::create(['name' => 'Test Bank', 'code' => 'TST', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $this->bankUser = User::create([
            'name' => 'Entry',
            'email' => 'entry@test.bank',
            'password' => bcrypt('password'),
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);

        $def = WorkflowDefinition::create(['code' => 'IMPORT', 'name' => 'Import', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'published_by' => $this->admin->id,
            'published_at' => now(),
        ]);
        $this->stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'INTAKE',
            'name' => 'Intake',
            'order' => 1,
            'is_initial' => true,
            'sla_duration_minutes' => 60,
        ]);
    }

    private function createRequest(array $overrides = []): EngineRequest
    {
        return EngineRequest::create(array_merge([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-'.rand(1000, 9999),
            'status' => 'ACTIVE',
            'created_by' => $this->bankUser->id,
            'bank_id' => $this->bank->id,
            'data' => [],
            'version' => 1,
            'amount' => 10000,
            'currency' => 'USD',
        ], $overrides));
    }

    public function test_summary_returns_counts_and_total_amount(): void
    {
        $this->createRequest(['status' => 'ACTIVE', 'amount' => 5000]);
        $this->createRequest(['status' => 'CLOSED', 'amount' => 15000]);
        $this->createRequest(['status' => 'REJECTED', 'amount' => 3000]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/summary')
            ->assertOk();

        $this->assertEquals(3, $response->json('data.total'));
        $this->assertEquals(1, $response->json('data.active'));
        $this->assertEquals(1, $response->json('data.closed'));
        $this->assertEquals(1, $response->json('data.rejected'));
        $this->assertEquals(23000, $response->json('data.totalAmount'));
    }

    public function test_summary_filters_narrow_by_bank(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->first();
        $otherBank = Bank::create(['name' => 'Other', 'code' => 'OTH', 'is_active' => true, 'organization_id' => $bankOrg->id]);
        $this->createRequest(['bank_id' => $this->bank->id]);
        $this->createRequest(['bank_id' => $otherBank->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/summary?bank='.$this->bank->id)
            ->assertOk();

        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_requests_over_time_returns_monthly_data(): void
    {
        $this->createRequest();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/requests-over-time')
            ->assertOk();

        $this->assertNotEmpty($response->json('data'));
        $this->assertArrayHasKey('month', $response->json('data.0'));
        $this->assertArrayHasKey('total', $response->json('data.0'));
    }

    public function test_by_workflow_stage_groups_correctly(): void
    {
        $this->createRequest();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/by-workflow-stage')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('INTAKE', $response->json('data.0.stage_code'));
    }

    public function test_by_bank_returns_bank_breakdown(): void
    {
        $this->createRequest();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/by-bank')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Test Bank', $response->json('data.0.bank_name'));
    }

    public function test_by_currency_groups_correctly(): void
    {
        $this->createRequest(['currency' => 'USD']);
        $this->createRequest(['currency' => 'EUR']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/by-currency')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_filters_narrow_results(): void
    {
        $this->createRequest(['status' => 'ACTIVE']);
        $this->createRequest(['status' => 'CLOSED']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/summary?status=ACTIVE')
            ->assertOk();

        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_team_performance_returns_role_aggregation(): void
    {
        $req = $this->createRequest();
        WorkflowHistoryEntry::create([
            'request_id' => $req->id,
            'from_stage_id' => null,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->bankUser->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/team-performance')
            ->assertOk();

        $this->assertNotEmpty($response->json('data'));
        $this->assertArrayHasKey('role', $response->json('data.0'));
        $this->assertArrayHasKey('actions', $response->json('data.0'));
    }

    public function test_forbidden_without_permission(): void
    {
        $this->actingAs($this->bankUser)
            ->getJson('/api/v1/reports/summary')
            ->assertForbidden();
    }

    /**
     * Guards API-007: stage-duration's from/to filter must use a half-open
     * range instead of whereDate(), which wraps h1.created_at in DATE() and
     * defeats any index on the column. created_to must stay inclusive of the
     * whole day.
     */
    public function test_stage_duration_to_filter_is_inclusive_of_the_whole_day(): void
    {
        $inRangeReq = $this->createRequest();
        WorkflowHistoryEntry::create([
            'request_id' => $inRangeReq->id,
            'from_stage_id' => null,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->bankUser->id,
            'created_at' => '2026-03-10 23:30:00',
        ]);
        WorkflowHistoryEntry::create([
            'request_id' => $inRangeReq->id,
            'from_stage_id' => $this->stage->id,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->bankUser->id,
            'created_at' => '2026-03-11 00:00:00',
        ]);

        $outOfRangeReq = $this->createRequest();
        WorkflowHistoryEntry::create([
            'request_id' => $outOfRangeReq->id,
            'from_stage_id' => null,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->bankUser->id,
            'created_at' => '2026-03-11 08:00:00',
        ]);
        WorkflowHistoryEntry::create([
            'request_id' => $outOfRangeReq->id,
            'from_stage_id' => $this->stage->id,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->bankUser->id,
            'created_at' => '2026-03-11 09:00:00',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/stage-duration?from=2026-03-10&to=2026-03-10')
            ->assertOk();

        $this->assertEquals(1, $response->json('data.0.transitions'));
    }

    /**
     * Guards API-006: unfiltered stage-duration/team-performance calls
     * default to a 90-day created_at window instead of scanning all history;
     * ?all=true bypasses it for an explicit full-history pull.
     */
    public function test_stage_duration_defaults_to_a_ninety_day_window(): void
    {
        $recentReq = $this->createRequest();
        WorkflowHistoryEntry::create(['request_id' => $recentReq->id, 'from_stage_id' => null, 'to_stage_id' => $this->stage->id, 'performed_by' => $this->bankUser->id, 'created_at' => now()->subDays(5)]);
        WorkflowHistoryEntry::create(['request_id' => $recentReq->id, 'from_stage_id' => $this->stage->id, 'to_stage_id' => $this->stage->id, 'performed_by' => $this->bankUser->id, 'created_at' => now()->subDays(5)->addHour()]);

        $oldReq = $this->createRequest();
        WorkflowHistoryEntry::create(['request_id' => $oldReq->id, 'from_stage_id' => null, 'to_stage_id' => $this->stage->id, 'performed_by' => $this->bankUser->id, 'created_at' => now()->subDays(120)]);
        WorkflowHistoryEntry::create(['request_id' => $oldReq->id, 'from_stage_id' => $this->stage->id, 'to_stage_id' => $this->stage->id, 'performed_by' => $this->bankUser->id, 'created_at' => now()->subDays(120)->addHour()]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/stage-duration')
            ->assertOk();
        $this->assertEquals(1, $response->json('data.0.transitions'), 'Only the transition within the default 90-day window should count.');

        $responseAll = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/stage-duration?all=true')
            ->assertOk();
        $this->assertEquals(2, $responseAll->json('data.0.transitions'), '?all=true must include transitions outside the default window.');
    }

    public function test_team_performance_defaults_to_a_ninety_day_window(): void
    {
        $recentReq = $this->createRequest();
        $recentReq->forceFill(['created_at' => now()->subDays(5)])->save();
        WorkflowHistoryEntry::create(['request_id' => $recentReq->id, 'from_stage_id' => null, 'to_stage_id' => $this->stage->id, 'performed_by' => $this->bankUser->id, 'created_at' => now()->subDays(5)]);

        $oldReq = $this->createRequest();
        $oldReq->forceFill(['created_at' => now()->subDays(120)])->save();
        WorkflowHistoryEntry::create(['request_id' => $oldReq->id, 'from_stage_id' => null, 'to_stage_id' => $this->stage->id, 'performed_by' => $this->bankUser->id, 'created_at' => now()->subDays(120)]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/team-performance')
            ->assertOk();
        $this->assertEquals(1, $response->json('data.0.actions'), 'Only the action within the default 90-day window should count.');

        $responseAll = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/team-performance?all=true')
            ->assertOk();
        $this->assertEquals(2, $responseAll->json('data.0.actions'), '?all=true must include actions outside the default window.');
    }
}
