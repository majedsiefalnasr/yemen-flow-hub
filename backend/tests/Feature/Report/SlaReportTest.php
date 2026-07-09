<?php

namespace Tests\Feature\Report;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards API-006: reports/sla must bucket by SLA status (breached/nearing/ok)
 * in SQL — the pre-fix implementation loaded every matching EngineRequest via
 * ->get() and derived the bucket per-row in PHP via groupBy()/filter(). The
 * bucketing formula (breached: past deadline; nearing: within the final 20%
 * of the SLA window, min 1 minute; ok: otherwise) must produce identical
 * counts to the old per-row PHP derivation (EngineRequest::getSlaStatusAttribute).
 */
class SlaReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Bank $bank;

    private WorkflowVersion $version;

    private WorkflowStage $stage100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $cbyOrg = Organization::where('code', 'national_committee')->first();
        $bankOrg = Organization::where('code', 'commercial_banks')->first();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@slareport.test',
            'password' => bcrypt('password'),
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $systemAdminRole = Role::query()->where('code', 'system_admin')->firstOrFail();
        $this->admin->roles()->attach($systemAdminRole->id);

        $this->bank = Bank::create(['name' => 'SLA Bank', 'code' => 'SLA', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $def = WorkflowDefinition::create(['code' => 'SLA_WF', 'name' => 'SLA WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        // 100-minute SLA: nearing window is the final 20 minutes before deadline.
        $this->stage100 = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'REVIEW',
            'name' => 'Review',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'sla_duration_minutes' => 100,
            'version' => 1,
        ]);
    }

    private function requestEnteredMinutesAgo(int $minutesAgo, string $reference): EngineRequest
    {
        $request = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage100->id,
            'reference' => $reference,
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
            'bank_id' => $this->bank->id,
            'data' => [],
            'version' => 1,
        ]);
        $request->forceFill(['stage_entered_at' => now()->subMinutes($minutesAgo)])->save();

        return $request;
    }

    public function test_sla_report_buckets_requests_correctly(): void
    {
        // Deadline = entered_at + 100min. Nearing window = last 20min before deadline.
        $this->requestEnteredMinutesAgo(150, 'SLA-BREACHED-1'); // 50 min past deadline
        $this->requestEnteredMinutesAgo(101, 'SLA-BREACHED-2'); // 1 min past deadline
        $this->requestEnteredMinutesAgo(90, 'SLA-NEARING-1');   // 10 min remaining (within 20min window)
        $this->requestEnteredMinutesAgo(85, 'SLA-NEARING-2');   // 15 min remaining
        $this->requestEnteredMinutesAgo(50, 'SLA-OK-1');        // 50 min remaining
        $this->requestEnteredMinutesAgo(10, 'SLA-OK-2');        // 90 min remaining

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/sla')
            ->assertOk();

        $data = collect($response->json('data'));
        $reviewBucket = $data->firstWhere('stage_code', 'REVIEW');

        $this->assertNotNull($reviewBucket);
        $this->assertSame(6, $reviewBucket['total']);
        $this->assertSame(2, $reviewBucket['breached']);
        $this->assertSame(2, $reviewBucket['nearing']);
        $this->assertSame(2, $reviewBucket['ok']);
        $this->assertSame(33.33, $reviewBucket['breach_rate']);
    }

    /**
     * API-006: unfiltered calls default to a 90-day created_at window instead
     * of scanning all history. A row created (stage-entered) beyond that
     * window is excluded unless the caller asks for full history explicitly.
     */
    public function test_sla_report_defaults_to_a_ninety_day_window(): void
    {
        $recent = $this->requestEnteredMinutesAgo(10, 'SLA-RECENT');
        $recent->forceFill(['created_at' => now()->subDays(5)])->save();

        $old = $this->requestEnteredMinutesAgo(10, 'SLA-OLD');
        $old->forceFill(['created_at' => now()->subDays(120)])->save();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/sla')
            ->assertOk();

        $reviewBucket = collect($response->json('data'))->firstWhere('stage_code', 'REVIEW');
        $this->assertSame(1, $reviewBucket['total'], 'Only the request within the default 90-day window should count.');
    }

    public function test_sla_report_all_true_bypasses_the_default_window(): void
    {
        $recent = $this->requestEnteredMinutesAgo(10, 'SLA-RECENT');
        $recent->forceFill(['created_at' => now()->subDays(5)])->save();

        $old = $this->requestEnteredMinutesAgo(10, 'SLA-OLD');
        $old->forceFill(['created_at' => now()->subDays(120)])->save();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/sla?all=true')
            ->assertOk();

        $reviewBucket = collect($response->json('data'))->firstWhere('stage_code', 'REVIEW');
        $this->assertSame(2, $reviewBucket['total'], '?all=true must include rows outside the default window.');
    }

    public function test_sla_report_excludes_requests_without_sla_configured(): void
    {
        $noSlaStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'NO_SLA',
            'name' => 'No SLA',
            'sort_order' => 2,
            'is_initial' => false,
            'is_final' => false,
            'sla_duration_minutes' => null,
            'version' => 1,
        ]);

        $request = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $noSlaStage->id,
            'reference' => 'NO-SLA-1',
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
            'bank_id' => $this->bank->id,
            'data' => [],
            'version' => 1,
        ]);
        $request->forceFill(['stage_entered_at' => now()->subMinutes(500)])->save();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/sla')
            ->assertOk();

        $codes = collect($response->json('data'))->pluck('stage_code')->all();
        $this->assertNotContains('NO_SLA', $codes);
    }

    public function test_sla_report_respects_bank_scope(): void
    {
        $this->requestEnteredMinutesAgo(150, 'SLA-BANK-A');

        $otherBankOrg = Organization::where('code', 'commercial_banks')->first();
        $otherBank = Bank::create(['name' => 'Other Bank', 'code' => 'OTB', 'is_active' => true, 'organization_id' => $otherBankOrg->id]);
        $otherBankUser = User::create([
            'name' => 'Other Bank User',
            'email' => 'other@slareport.test',
            'password' => bcrypt('password'),
            'bank_id' => $otherBank->id,
            'organization_id' => $otherBankOrg->id,
            'is_active' => true,
        ]);
        $reportsRole = Role::query()->where('code', 'intake')->firstOrFail();
        $otherBankUser->roles()->attach($reportsRole);

        $otherRequest = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage100->id,
            'reference' => 'SLA-BANK-B',
            'status' => 'ACTIVE',
            'created_by' => $otherBankUser->id,
            'bank_id' => $otherBank->id,
            'data' => [],
            'version' => 1,
        ]);
        $otherRequest->forceFill(['stage_entered_at' => now()->subMinutes(150)])->save();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/sla?bank='.$this->bank->id)
            ->assertOk();

        $reviewBucket = collect($response->json('data'))->firstWhere('stage_code', 'REVIEW');
        $this->assertSame(1, $reviewBucket['total']);
    }
}
