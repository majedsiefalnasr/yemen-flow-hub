<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
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

class CbyAdminDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private Bank $otherBank;

    private User $admin;

    /** @var array{version: WorkflowVersion, stages: array<string, WorkflowStage>} */
    private array $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        Permission::query()->firstOrCreate(
            ['slug' => 'request.create'],
            ['name_ar' => 'إنشاء طلب', 'name_en' => 'Create Request', 'group' => 'requests']
        );

        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
        $this->admin = $this->makeUser(UserRole::CBY_ADMIN);
        $this->workflow = $this->workflowWithStages([
            'CREATE', 'INTERNAL', 'SUPPORT', 'EXEC', 'FX', 'FX_CONFIRM', 'FINAL', 'CLOSED',
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeBank(string $code): Bank
    {
        return Bank::query()->create([
            'name' => "بنك {$code}",
            'code' => $code,
            'is_active' => true,
        ]);
    }

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

    /**
     * @return array{version: WorkflowVersion, stages: array<string, WorkflowStage>}
     */
    private function workflowWithStages(array $stageCodes): array
    {
        $definition = WorkflowDefinition::query()->create([
            'code' => 'CBYADMIN_'.Str::random(8),
            'name' => 'CBY Admin Test Workflow',
            'is_active' => true,
            'version' => 1,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        $stages = [];
        foreach ($stageCodes as $index => $stageCode) {
            $stages[$stageCode] = WorkflowStage::query()->create([
                'workflow_version_id' => $version->id,
                'code' => $stageCode,
                'name' => Str::headline(Str::lower($stageCode)),
                'sort_order' => $index + 1,
                'is_initial' => $index === 0,
                'is_final' => $stageCode === 'CLOSED',
                'version' => 1,
            ]);
        }

        return ['version' => $version, 'stages' => $stages];
    }

    /**
     * Creates an EngineRequest on a given stage + status. `merchant_name` (extra key)
     * lets duplicate-supplier tests control the merchant identity directly; it is
     * stripped before being passed to EngineRequest::create().
     */
    private function makeRequest(Bank $bank, User $creator, string $stageCode, string $status = 'ACTIVE', array $extra = []): EngineRequest
    {
        $merchantName = $extra['merchant_name'] ?? ('Merchant '.Str::random(6));
        unset($extra['merchant_name']);

        $merchant = Merchant::query()->create([
            'bank_id' => $bank->id,
            'name' => $merchantName,
            'tax_number' => 'TX-'.Str::random(10),
            'created_by' => $creator->id,
        ]);

        return EngineRequest::query()->create(array_merge([
            'workflow_version_id' => $this->workflow['version']->id,
            'current_stage_id' => $this->workflow['stages'][$stageCode]->id,
            'reference' => 'ENG-'.Str::random(10),
            'status' => $status,
            'created_by' => $creator->id,
            'bank_id' => $bank->id,
            'merchant_id' => $merchant->id,
            'data' => [],
            'version' => 1,
            'currency' => 'USD',
            'amount' => 10000.00,
        ], $extra));
    }

    // ─── AC8: Response shape ──────────────────────────────────────────────────

    public function test_cby_admin_stats_returns_correct_shape(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, 'CREATE');

        $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total',
                    'approved',
                    'in_process',
                    'rejected',
                    'compliance_alerts' => [
                        'duplicate_suppliers',
                        'high_amount_requests',
                        'stale_pending_requests',
                    ],
                    'most_active_banks',
                    'monthly_requests',
                    'category_distribution',
                    'recent_requests',
                ],
            ]);
    }

    // ─── Monthly trend chart fields ───────────────────────────────────────────

    public function test_cby_admin_monthly_requests_returns_6_month_window(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, 'INTERNAL');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.monthly_requests');

        $this->assertCount(6, $response);
        $this->assertArrayHasKey('month', $response[0]);
        $this->assertArrayHasKey('submitted', $response[0]);
        $this->assertArrayHasKey('approved', $response[0]);
    }

    public function test_cby_admin_monthly_requests_counts_submitted_and_approved(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->bank, $de, 'EXEC', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.monthly_requests');

        $currentMonth = now()->format('Y-m');
        $currentEntry = collect($response)->firstWhere('month', $currentMonth);

        $this->assertNotNull($currentEntry);
        $this->assertEquals(3, $currentEntry['submitted']);
        $this->assertEquals(2, $currentEntry['approved']); // EXEC/CLOSED + CLOSED/CLOSED
    }

    // ─── Category distribution ────────────────────────────────────────────────

    public function test_cby_admin_category_distribution_returns_currency_groups(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, 'INTERNAL', 'ACTIVE', ['currency' => 'USD']);
        $this->makeRequest($this->bank, $de, 'INTERNAL', 'ACTIVE', ['currency' => 'EUR']);
        $this->makeRequest($this->bank, $de, 'INTERNAL', 'ACTIVE', ['currency' => 'USD']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.category_distribution');

        $this->assertNotEmpty($response);
        $this->assertArrayHasKey('label', $response[0]);
        $this->assertArrayHasKey('count', $response[0]);
        $this->assertArrayHasKey('color', $response[0]);

        $usd = collect($response)->firstWhere('label', 'USD');
        $this->assertNotNull($usd);
        $this->assertEquals(2, $usd['count']);
    }

    // ─── Recent requests ──────────────────────────────────────────────────────

    public function test_cby_admin_recent_requests_returns_up_to_10_requests(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        for ($i = 0; $i < 12; $i++) {
            $this->makeRequest($this->bank, $de, 'INTERNAL');
        }

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.recent_requests');

        $this->assertLessThanOrEqual(10, count($response));
        $this->assertGreaterThan(0, count($response));
    }

    public function test_cby_admin_recent_requests_spans_all_banks(): void
    {
        $de1 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $de2 = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        $this->makeRequest($this->bank, $de1, 'INTERNAL');
        $this->makeRequest($this->otherBank, $de2, 'INTERNAL');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.recent_requests');

        $this->assertCount(2, $response);
    }

    // ─── AC5: CBY Admin sees all banks ────────────────────────────────────────

    public function test_cby_admin_total_count_spans_all_banks(): void
    {
        $de1 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $de2 = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        $this->makeRequest($this->bank, $de1, 'CREATE');
        $this->makeRequest($this->bank, $de1, 'INTERNAL');
        $this->makeRequest($this->otherBank, $de2, 'CREATE');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data');

        $this->assertEquals(3, $response['total']);
    }

    // ─── AC8: KPI counts ──────────────────────────────────────────────────────

    public function test_cby_admin_approved_count_includes_approved_customs_completed(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        // EXECUTIVE_APPROVED, CUSTOMS_DECLARATION_ISSUED, COMPLETED all port to CLOSED status.
        $this->makeRequest($this->bank, $de, 'EXEC', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'FX_CONFIRM', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CREATE');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data');

        $this->assertEquals(3, $response['approved']);
    }

    public function test_cby_admin_rejected_count_is_executive_rejected_only(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $this->makeRequest($this->bank, $de, 'EXEC', 'REJECTED');
        $this->makeRequest($this->bank, $de, 'SUPPORT', 'REJECTED');
        $this->makeRequest($this->bank, $de, 'CREATE');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data');

        $this->assertEquals(2, $response['rejected']);
    }

    public function test_cby_admin_in_process_excludes_draft_and_terminal(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        // in-process: should count these
        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->bank, $de, 'SUPPORT');

        // not in-process: excluded
        $this->makeRequest($this->bank, $de, 'CREATE');
        $this->makeRequest($this->bank, $de, 'CREATE', 'REJECTED');
        $this->makeRequest($this->bank, $de, 'EXEC', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'EXEC', 'REJECTED');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'FX_CONFIRM', 'CLOSED');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data');

        $this->assertEquals(3, $response['in_process']);
    }

    // ─── AC2: Compliance alerts ───────────────────────────────────────────────

    public function test_compliance_alerts_duplicate_suppliers_returns_suppliers_with_2_or_more_requests(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $this->makeRequest($this->bank, $de, 'INTERNAL', 'ACTIVE', ['merchant_name' => 'Dup Supplier']);
        $this->makeRequest($this->bank, $de, 'INTERNAL', 'ACTIVE', ['merchant_name' => 'Dup Supplier']);
        $this->makeRequest($this->bank, $de, 'INTERNAL', 'ACTIVE', ['merchant_name' => 'Unique Supplier']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.compliance_alerts.duplicate_suppliers');

        $this->assertCount(1, $response);
        $this->assertEquals('Dup Supplier', $response[0]['supplier_name']);
        $this->assertEquals(2, $response[0]['count']);
    }

    public function test_compliance_alerts_duplicate_suppliers_excludes_draft_requests(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        // Both are drafts (CREATE stage) — should NOT be counted as duplicate
        $this->makeRequest($this->bank, $de, 'CREATE', 'ACTIVE', ['merchant_name' => 'Draft Supplier']);
        $this->makeRequest($this->bank, $de, 'CREATE', 'ACTIVE', ['merchant_name' => 'Draft Supplier']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.compliance_alerts.duplicate_suppliers');

        $this->assertCount(0, $response);
    }

    public function test_compliance_alerts_high_amount_requests_returns_usd_over_1m(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $this->makeRequest($this->bank, $de, 'INTERNAL', 'ACTIVE', [
            'amount' => 1_500_000,
            'currency' => 'USD',
        ]);
        $this->makeRequest($this->bank, $de, 'INTERNAL', 'ACTIVE', [
            'amount' => 500_000,
            'currency' => 'USD',
        ]);
        // EUR exceeding 1M — should NOT appear (USD only)
        $this->makeRequest($this->bank, $de, 'INTERNAL', 'ACTIVE', [
            'amount' => 2_000_000,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.compliance_alerts.high_amount_requests');

        $this->assertCount(1, $response);
        $this->assertEquals(1_500_000.0, $response[0]['amount']);
        $this->assertEquals('USD', $response[0]['currency']);
    }

    public function test_compliance_alerts_high_amount_excludes_terminal_requests(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        // Terminal — should NOT appear
        $this->makeRequest($this->bank, $de, 'EXEC', 'REJECTED', [
            'amount' => 2_000_000,
            'currency' => 'USD',
        ]);
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED', [
            'amount' => 3_000_000,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.compliance_alerts.high_amount_requests');

        $this->assertCount(0, $response);
    }

    public function test_compliance_alerts_stale_pending_requests_returns_old_non_terminal_requests(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        // Stale — updated 20 days ago
        $stale = $this->makeRequest($this->bank, $de, 'INTERNAL');
        EngineRequest::query()->where('id', $stale->id)->update(['updated_at' => now()->subDays(20)]);

        // Fresh — should NOT appear
        $this->makeRequest($this->bank, $de, 'INTERNAL');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.compliance_alerts.stale_pending_requests');

        $this->assertCount(1, $response);
        $this->assertEquals($stale->fresh()->reference, $response[0]['reference_number']);
    }

    public function test_compliance_alerts_stale_pending_excludes_draft_and_terminal(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $fixtures = [
            ['CREATE', 'ACTIVE'],
            ['CREATE', 'REJECTED'],
            ['EXEC', 'REJECTED'],
            ['CLOSED', 'CLOSED'],
        ];

        foreach ($fixtures as [$stageCode, $status]) {
            $req = $this->makeRequest($this->bank, $de, $stageCode, $status);
            EngineRequest::query()->where('id', $req->id)->update(['updated_at' => now()->subDays(20)]);
        }

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.compliance_alerts.stale_pending_requests');

        $this->assertCount(0, $response);
    }

    public function test_compliance_alerts_stale_pending_excludes_executive_approved(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        // EXECUTIVE_APPROVED ports to EXEC/CLOSED — excluded via the `active` status bucket.
        $req = $this->makeRequest($this->bank, $de, 'EXEC', 'CLOSED');
        EngineRequest::query()->where('id', $req->id)->update(['updated_at' => now()->subDays(20)]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.compliance_alerts.stale_pending_requests');

        $this->assertCount(0, $response);
    }

    // ─── AC3: Most active banks ───────────────────────────────────────────────

    public function test_most_active_banks_returns_top_banks_by_request_count(): void
    {
        $de1 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $de2 = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        $this->makeRequest($this->bank, $de1, 'CREATE');
        $this->makeRequest($this->bank, $de1, 'CREATE');
        $this->makeRequest($this->otherBank, $de2, 'CREATE');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.most_active_banks');

        $this->assertCount(2, $response);
        $this->assertEquals(2, $response[0]['request_count']);
        $this->assertEquals(1, $response[1]['request_count']);
        $this->assertArrayHasKey('bank_id', $response[0]);
        $this->assertArrayHasKey('bank_name', $response[0]);
    }

    public function test_most_active_banks_limits_to_5(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        // Create 4 more banks (including the 2 already created in setUp = 6 total)
        for ($i = 0; $i < 4; $i++) {
            $bank = $this->makeBank("B{$i}");
            $newDe = $this->makeUser(UserRole::DATA_ENTRY, $bank);
            $this->makeRequest($bank, $newDe, 'CREATE');
        }
        $this->makeRequest($this->bank, $de, 'CREATE');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data.most_active_banks');

        $this->assertLessThanOrEqual(5, count($response));
    }

    // ─── Non-CBY users cannot get CBY admin stats ─────────────────────────────

    public function test_bank_user_gets_different_stats_not_cby_admin_format(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $response = $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->json('data');

        // Bank user should NOT receive CBY Admin format
        $this->assertArrayNotHasKey('compliance_alerts', $response);
        $this->assertArrayNotHasKey('most_active_banks', $response);
    }
}
