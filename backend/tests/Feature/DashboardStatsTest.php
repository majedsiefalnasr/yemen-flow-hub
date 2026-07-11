<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    private Bank $bank;

    private Bank $otherBank;

    /** @var array{version: WorkflowVersion, stages: array<string, WorkflowStage>} */
    private array $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedGovernance();

        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
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

        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@example.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]), $role);
    }

    /**
     * @return array{version: WorkflowVersion, stages: array<string, WorkflowStage>}
     */
    private function workflowWithStages(array $stageCodes): array
    {
        $definition = WorkflowDefinition::query()->create([
            'code' => 'DASHBOARD_'.Str::random(8),
            'name' => 'Dashboard Test Workflow',
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
     * Creates an EngineRequest on a given stage + status. Mirrors the canonical
     * helper in tests/Feature/Engine/EngineSharedReadModelTest.php.
     */
    private function makeRequest(Bank $bank, User $creator, string $stageCode, string $status = 'ACTIVE', array $extra = []): EngineRequest
    {
        $merchant = Merchant::query()->create([
            'bank_id' => $bank->id,
            'name' => $extra['supplier_name'] ?? ('Merchant '.Str::random(6)),
            'tax_number' => 'TX-'.Str::random(10),
            'created_by' => $creator->id,
        ]);
        unset($extra['supplier_name']);

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

    // ─── AC-3: DATA_ENTRY stats shape ─────────────────────────────────────────

    public function test_data_entry_stats_returns_correct_kpi_keys(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'draft',
                    'returned',
                    'under_cby_processing',
                    'completed',
                    'draft_requests',
                    'returned_requests',
                    'recent_requests',
                ],
            ]);
    }

    public function test_data_entry_stats_counts_are_bank_scoped(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $otherDe = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        // 2 drafts in my bank, 3 in other bank
        $this->makeRequest($this->bank, $de, 'CREATE');
        $this->makeRequest($this->bank, $de, 'CREATE');
        $this->makeRequest($this->otherBank, $otherDe, 'CREATE');
        $this->makeRequest($this->otherBank, $otherDe, 'CREATE');
        $this->makeRequest($this->otherBank, $otherDe, 'CREATE');

        $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.draft', 2);
    }

    public function test_data_entry_draft_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, 'CREATE');
        $this->makeRequest($this->bank, $de, 'CREATE');
        $this->makeRequest($this->bank, $de, 'INTERNAL');

        $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.draft', 2);
    }

    public function test_data_entry_returned_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        // DRAFT_REJECTED_INTERNAL → CREATE/REJECTED
        $this->makeRequest($this->bank, $de, 'CREATE', 'REJECTED');
        $this->makeRequest($this->bank, $de, 'CREATE', 'REJECTED');

        $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.returned', 2);
    }

    public function test_data_entry_under_cby_processing_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, 'SUPPORT');
        $this->makeRequest($this->bank, $de, 'SUPPORT');
        $this->makeRequest($this->bank, $de, 'EXEC');
        $this->makeRequest($this->bank, $de, 'CREATE');

        $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.under_cby_processing', 3);
    }

    public function test_data_entry_completed_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CREATE');

        $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.completed', 2);
    }

    public function test_data_entry_returned_requests_contains_draft_rejected_only(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, 'CREATE', 'REJECTED');
        $this->makeRequest($this->bank, $de, 'CREATE');

        $response = $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertOk();

        $returnedRequests = $response->json('data.returned_requests');
        $this->assertCount(1, $returnedRequests);
        $this->assertSame('REJECTED', $returnedRequests[0]['status']);
    }

    public function test_data_entry_draft_requests_contains_drafts_only(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, 'CREATE');
        $this->makeRequest($this->bank, $de, 'CREATE', 'REJECTED');

        $response = $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertOk();

        $draftRequests = $response->json('data.draft_requests');
        $this->assertCount(1, $draftRequests);
        $this->assertSame('ACTIVE', $draftRequests[0]['status']);
    }

    public function test_data_entry_draft_requests_max_5(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        for ($i = 0; $i < 7; $i++) {
            $this->makeRequest($this->bank, $de, 'CREATE');
        }

        $response = $this->actingAs($de)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertCount(5, $response->json('data.draft_requests'));
    }

    public function test_data_entry_recent_requests_max_5(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        for ($i = 0; $i < 7; $i++) {
            $this->makeRequest($this->bank, $de, 'CREATE');
        }

        $response = $this->actingAs($de)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertCount(5, $response->json('data.recent_requests'));
    }

    // ─── AC-3: BANK_REVIEWER stats shape ──────────────────────────────────────

    public function test_bank_reviewer_stats_returns_correct_kpi_keys(): void
    {
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);

        $this->actingAs($br)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'pending_review',
                    'at_cby',
                    'returned_by_support',
                    'approved_completed',
                    'review_queue',
                ],
            ]);
    }

    public function test_bank_reviewer_stats_counts_are_bank_scoped(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $otherDe = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->otherBank, $otherDe, 'INTERNAL');

        $this->actingAs($br)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.pending_review', 1);
    }

    public function test_bank_reviewer_pending_review_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        // SUBMITTED/BANK_REVIEW → INTERNAL
        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->bank, $de, 'SUPPORT');

        $this->actingAs($br)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.pending_review', 2);
    }

    public function test_bank_reviewer_at_cby_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->makeRequest($this->bank, $de, 'SUPPORT');
        $this->makeRequest($this->bank, $de, 'SUPPORT');
        $this->makeRequest($this->bank, $de, 'FX');
        $this->makeRequest($this->bank, $de, 'CREATE');

        $this->actingAs($br)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.at_cby', 3);
    }

    public function test_bank_reviewer_returned_by_support_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        // SUPPORT_REJECTED → SUPPORT/REJECTED
        $this->makeRequest($this->bank, $de, 'SUPPORT', 'REJECTED');
        $this->makeRequest($this->bank, $de, 'CREATE');

        $this->actingAs($br)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.returned_by_support', 1);
    }

    public function test_bank_reviewer_approved_completed_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CREATE');

        $this->actingAs($br)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.approved_completed', 3);
    }

    public function test_bank_reviewer_review_queue_contains_submitted_and_bank_review(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->bank, $de, 'SUPPORT');

        $response = $this->actingAs($br)->getJson('/api/dashboard/stats')->assertOk();
        $queue = $response->json('data.review_queue');
        $this->assertCount(2, $queue);
        $stageCodes = array_column($queue, 'stage_code');
        $this->assertSame(['INTERNAL', 'INTERNAL'], $stageCodes);
    }

    // ─── AC-5: SUPPORT_COMMITTEE stats shape ──────────────────────────────────

    public function test_support_committee_stats_returns_correct_kpi_keys(): void
    {
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'waiting_for_claim',
                    'active_by_me',
                    'claimed_by_others',
                    'recently_approved',
                    'support_queue',
                ],
            ]);
    }

    public function test_support_committee_waiting_for_claim_counts_support_review_pending(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $this->makeRequest($this->bank, $de, 'SUPPORT');
        $this->makeRequest($this->bank, $de, 'SUPPORT');
        $claimed = $this->makeRequest($this->bank, $de, 'SUPPORT');
        $claimed->update(['claimed_by' => $sc->id, 'claimed_at' => now(), 'claim_expires_at' => now()->addMinutes(15)]);

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.waiting_for_claim', 2);
    }

    public function test_support_committee_active_by_me_counts_only_my_claims(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $sc2 = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $myRequest = $this->makeRequest($this->bank, $de, 'SUPPORT');
        $otherRequest = $this->makeRequest($this->bank, $de, 'SUPPORT');

        $myRequest->update(['claimed_by' => $sc->id, 'claimed_at' => now(), 'claim_expires_at' => now()->addMinutes(15)]);
        $otherRequest->update(['claimed_by' => $sc2->id, 'claimed_at' => now(), 'claim_expires_at' => now()->addMinutes(15)]);

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.active_by_me', 1);
    }

    public function test_support_committee_claimed_by_others_excludes_mine(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $sc2 = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $myRequest = $this->makeRequest($this->bank, $de, 'SUPPORT');
        $otherRequest = $this->makeRequest($this->bank, $de, 'SUPPORT');

        $myRequest->update(['claimed_by' => $sc->id, 'claimed_at' => now(), 'claim_expires_at' => now()->addMinutes(15)]);
        $otherRequest->update(['claimed_by' => $sc2->id, 'claimed_at' => now(), 'claim_expires_at' => now()->addMinutes(15)]);

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.claimed_by_others', 1);
    }

    public function test_support_committee_recently_approved_counts_within_7_day_window(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        // Two approved within the 7-day window — updated_at now via support stage closed.
        $r1 = $this->makeRequest($this->bank, $de, 'SUPPORT', 'CLOSED');
        $r2 = $this->makeRequest($this->bank, $de, 'SUPPORT', 'CLOSED');
        $r1->forceFill(['updated_at' => now()])->saveQuietly();
        $r2->forceFill(['updated_at' => now()])->saveQuietly();

        // One approved 8 days ago — must NOT be counted
        $old = $this->makeRequest($this->bank, $de, 'SUPPORT', 'CLOSED');
        $old->forceFill(['updated_at' => now()->subDays(8)])->saveQuietly();

        // One still pending — must NOT be counted
        $this->makeRequest($this->bank, $de, 'SUPPORT');

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.recently_approved', 2);
    }

    public function test_support_committee_recently_approved_excludes_older_records(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $r1 = $this->makeRequest($this->bank, $de, 'SUPPORT', 'CLOSED');
        $r2 = $this->makeRequest($this->bank, $de, 'SUPPORT', 'CLOSED');
        $r1->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();
        $r2->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.recently_approved', 0);
    }

    public function test_support_committee_queue_contains_pending_and_in_progress(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $this->makeRequest($this->bank, $de, 'SUPPORT');
        $claimed = $this->makeRequest($this->bank, $de, 'SUPPORT');
        $claimed->update(['claimed_by' => $sc->id, 'claimed_at' => now(), 'claim_expires_at' => now()->addMinutes(15)]);
        // A closed request has left the SUPPORT stage and must NOT appear in the
        // active support queue, so it is not counted below.
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');

        $response = $this->actingAs($sc)->getJson('/api/dashboard/stats')->assertOk();
        $queue = $response->json('data.support_queue');
        $this->assertCount(2, $queue);
    }

    // ─── Story 6.3.2: BANK_ADMIN dashboard ───────────────────────────────────

    public function test_bank_admin_stats_returns_all_required_kpi_keys(): void
    {
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total',
                    'pending',
                    'approved',
                    'rejected',
                    'total_financed_amount',
                    'monthly_requests',
                    'recent_requests',
                ],
            ]);
    }

    public function test_bank_admin_total_counts_all_bank_requests(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);
        $otherDe = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        $this->makeRequest($this->bank, $de, 'CREATE');
        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->otherBank, $otherDe, 'CREATE');

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.total', 3);
    }

    public function test_bank_admin_pending_counts_submitted_and_bank_review(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->bank, $de, 'SUPPORT');

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.pending', 2);
    }

    public function test_bank_admin_approved_counts_terminal_approved_statuses(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CREATE');

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.approved', 3);
    }

    public function test_bank_admin_rejected_counts_support_executive_and_bank_terminal_rejections(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, 'INTERNAL', 'REJECTED');
        $this->makeRequest($this->bank, $de, 'SUPPORT', 'REJECTED');
        $this->makeRequest($this->bank, $de, 'EXEC', 'REJECTED');
        $this->makeRequest($this->bank, $de, 'CREATE');

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.rejected', 3);
    }

    public function test_bank_admin_total_financed_amount_sums_approved_requests(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED', ['amount' => 10000.00]);
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED', ['amount' => 5000.00]);
        $this->makeRequest($this->bank, $de, 'CREATE', 'ACTIVE', ['amount' => 3000.00]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertEquals(15000.0, $response->json('data.total_financed_amount'));
    }

    public function test_bank_admin_total_financed_amount_is_zero_for_no_approved(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, 'CREATE', 'ACTIVE', ['amount' => 5000.00]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertEquals(0.0, $response->json('data.total_financed_amount'));
    }

    public function test_bank_admin_stats_are_org_scoped(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);
        $otherDe = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->otherBank, $otherDe, 'INTERNAL');
        $this->makeRequest($this->otherBank, $otherDe, 'INTERNAL');

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.total', 1);
    }

    public function test_bank_admin_monthly_requests_returns_6_month_array(): void
    {
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk();
        $monthly = $response->json('data.monthly_requests');

        $this->assertIsArray($monthly);
        $this->assertCount(6, $monthly);
        $this->assertArrayHasKey('month', $monthly[0]);
        $this->assertArrayHasKey('count', $monthly[0]);
    }

    public function test_bank_admin_monthly_requests_counts_are_bank_scoped(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);
        $otherDe = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        $currentMonth = now()->format('Y-m');

        $this->makeRequest($this->bank, $de, 'CREATE');
        $this->makeRequest($this->bank, $de, 'INTERNAL');
        $this->makeRequest($this->otherBank, $otherDe, 'CREATE');

        $response = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk();
        $monthly = $response->json('data.monthly_requests');

        $thisMonth = collect($monthly)->firstWhere('month', $currentMonth);
        $this->assertNotNull($thisMonth);
        $this->assertEquals(2, $thisMonth['count']);
    }

    public function test_bank_admin_empty_bank_returns_zeros_and_empty_arrays(): void
    {
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk();

        $this->assertEquals(0, $response->json('data.total'));
        $this->assertEquals(0, $response->json('data.pending'));
        $this->assertEquals(0, $response->json('data.approved'));
        $this->assertEquals(0, $response->json('data.rejected'));
        $this->assertEquals(0.0, $response->json('data.total_financed_amount'));
        $this->assertEmpty($response->json('data.recent_requests'));
    }

    public function test_bank_admin_stats_keep_legacy_compatibility_keys(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, 'INTERNAL');

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'pending_bank_review',
                    'at_cby',
                    'completed',
                    'rejected',
                    'active_users',
                    'recent_requests',
                ],
            ])
            ->assertJsonPath('data.pending_bank_review', 1)
            ->assertJsonPath('data.active_users', 2);
    }

    public function test_bank_admin_without_bank_id_returns_zero_payload_instead_of_error(): void
    {
        $admin = $this->makeUser(UserRole::BANK_ADMIN, null);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk();

        $this->assertEquals(0, $response->json('data.total'));
        $this->assertEquals(0, $response->json('data.pending'));
        $this->assertEquals(0, $response->json('data.approved'));
        $this->assertEquals(0, $response->json('data.rejected'));
        $this->assertEquals(0.0, $response->json('data.total_financed_amount'));
        $this->assertEquals(0, $response->json('data.pending_bank_review'));
        $this->assertEquals(0, $response->json('data.at_cby'));
        $this->assertEquals(0, $response->json('data.completed'));
        $this->assertEquals(0, $response->json('data.active_users'));
        $this->assertEmpty($response->json('data.recent_requests'));
        $this->assertCount(6, $response->json('data.monthly_requests'));
    }

    public function test_bank_admin_monthly_requests_handles_month_boundaries_consistently(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        try {
            $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
            $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

            $aprilRequest = $this->makeRequest($this->bank, $de, 'CREATE');
            $aprilRequest->forceFill([
                'created_at' => '2026-04-30 23:59:59',
                'updated_at' => '2026-04-30 23:59:59',
            ])->saveQuietly();

            $mayRequest = $this->makeRequest($this->bank, $de, 'CREATE');
            $mayRequest->forceFill([
                'created_at' => '2026-05-01 00:00:00',
                'updated_at' => '2026-05-01 00:00:00',
            ])->saveQuietly();

            $response = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk();
            $monthly = collect($response->json('data.monthly_requests'));

            $this->assertEquals(1, $monthly->firstWhere('month', '2026-04')['count'] ?? null);
            $this->assertEquals(1, $monthly->firstWhere('month', '2026-05')['count'] ?? null);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_bank_admin_recent_requests_max_10(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        for ($i = 0; $i < 12; $i++) {
            $this->makeRequest($this->bank, $de, 'CREATE');
        }

        $response = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertCount(10, $response->json('data.recent_requests'));
    }

    public function test_support_committee_queue_includes_claimer_name(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $req = $this->makeRequest($this->bank, $de, 'SUPPORT');
        $req->update(['claimed_by' => $sc->id, 'claimed_at' => now(), 'claim_expires_at' => now()->addMinutes(15)]);

        $response = $this->actingAs($sc)->getJson('/api/dashboard/stats')->assertOk();
        $queue = $response->json('data.support_queue');
        $this->assertCount(1, $queue);
        $this->assertNotNull($queue[0]['claimed_by']);
        $this->assertSame($sc->id, $queue[0]['claimed_by']['id']);
        $this->assertSame($sc->name, $queue[0]['claimed_by']['name']);
    }

    public function test_support_committee_sees_requests_across_all_banks(): void
    {
        $de1 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $de2 = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $this->makeRequest($this->bank, $de1, 'SUPPORT');
        $this->makeRequest($this->otherBank, $de2, 'SUPPORT');

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.waiting_for_claim', 2);
    }

    public function test_support_committee_queue_max_50(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        for ($i = 0; $i < 55; $i++) {
            $this->makeRequest($this->bank, $de, 'SUPPORT');
        }

        $response = $this->actingAs($sc)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertCount(50, $response->json('data.support_queue'));
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/dashboard/stats')->assertUnauthorized();
    }

    // ─── AC-1: SWIFT Officer stats shape ──────────────────────────────────────

    public function test_swift_officer_stats_returns_correct_kpi_keys(): void
    {
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        $this->actingAs($swift)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'pending_swift_upload',
                    'uploaded',
                    'final_approved',
                    'final_rejected',
                    'swift_queue',
                ],
            ]);
    }

    public function test_swift_officer_pending_swift_upload_counts_waiting_for_swift(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        $this->makeRequest($this->bank, $de, 'FX');
        $this->makeRequest($this->bank, $de, 'FX');
        $this->makeRequest($this->bank, $de, 'FX', 'CLOSED');

        $this->actingAs($swift)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.pending_swift_upload', 2);
    }

    public function test_swift_officer_uploaded_counts_requests_with_swift_uploaded_at(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        // "uploaded" now means present in (or past) the FX stage at all.
        $this->makeRequest($this->bank, $de, 'FX');
        $this->makeRequest($this->bank, $de, 'FX', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'INTERNAL');

        $this->actingAs($swift)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.uploaded', 2);
    }

    public function test_swift_officer_final_approved_counts_executive_approved_and_completed(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        $this->makeRequest($this->bank, $de, 'EXEC', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'EXEC', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'EXEC', 'REJECTED');

        $this->actingAs($swift)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.final_approved', 2);
    }

    public function test_swift_officer_final_rejected_counts_executive_rejected(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        $this->makeRequest($this->bank, $de, 'EXEC', 'REJECTED');
        $this->makeRequest($this->bank, $de, 'EXEC', 'CLOSED');

        $this->actingAs($swift)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.final_rejected', 1);
    }

    public function test_swift_officer_queue_shows_waiting_and_uploaded(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        // Only the active FX request is operational work for the SWIFT officer.
        // Closed requests have left the FX stage and must not appear in the queue.
        $this->makeRequest($this->bank, $de, 'FX');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'INTERNAL');

        $response = $this->actingAs($swift)->getJson('/api/dashboard/stats')->assertOk();
        $queue = $response->json('data.swift_queue');
        $this->assertCount(1, $queue);

        $stageCodes = collect($queue)->pluck('stage_code')->all();
        $this->assertSame(['FX'], $stageCodes);
    }

    public function test_swift_officer_cannot_see_other_bank_requests(): void
    {
        $de1 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $de2 = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        $this->makeRequest($this->bank, $de1, 'FX');
        $this->makeRequest($this->otherBank, $de2, 'FX');

        $this->actingAs($swift)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.pending_swift_upload', 1);
    }

    public function test_swift_officer_queue_max_50(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        for ($i = 0; $i < 55; $i++) {
            $this->makeRequest($this->bank, $de, 'FX');
        }

        $response = $this->actingAs($swift)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertCount(50, $response->json('data.swift_queue'));
    }

    // ─── EXECUTIVE_MEMBER stats (voting removed by DI-3 — zeroed) ────────────

    public function test_executive_member_stats_returns_correct_kpi_keys(): void
    {
        $exec = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $this->actingAs($exec)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'waiting_for_voting_open',
                'active_voting_sessions',
                'decisions_approved',
                'decisions_rejected',
                'finalized_decisions',
                'voting_queue',
            ]]);
    }

    public function test_executive_member_voting_counters_are_zeroed(): void
    {
        $exec = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $this->actingAs($exec)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.waiting_for_voting_open', 0)
            ->assertJsonPath('data.active_voting_sessions', 0)
            ->assertJsonPath('data.decisions_approved', 0)
            ->assertJsonPath('data.decisions_rejected', 0)
            ->assertJsonPath('data.finalized_decisions', 0)
            ->assertJsonPath('data.voting_queue', []);
    }

    // ─── COMMITTEE_DIRECTOR stats ─────────────────────────────────────────────

    public function test_committee_director_stats_returns_correct_kpi_keys(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        $this->actingAs($director)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'final_pending',
                'final_pending_queue',
                'finalized_approved',
                'finalized_rejected',
                // Backward-compatible keys retained during the dashboard migration.
                'fx_confirmation_pending',
                'customs_declaration_pending',
            ]]);
    }

    // UI-FX-001: the Director dashboard headline counts the FINAL stage (the
    // Director's own executable queue), not the FX_CONFIRM stage (owned by the
    // national FX team). This is what makes the dashboard agree with /customs
    // and my-queue.
    public function test_committee_director_final_pending_counts_final_stage_not_fx_confirm(): void
    {
        $de1 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $de2 = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        // Two at FINAL (the Director's queue) + one at FX_CONFIRM (the FX team's).
        $this->makeRequest($this->bank, $de1, 'FINAL');
        $this->makeRequest($this->otherBank, $de2, 'FINAL');
        $this->makeRequest($this->bank, $de1, 'FX_CONFIRM');

        $this->actingAs($director)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.final_pending', 2)
            // Backward-compat key now mirrors the FINAL count, not FX_CONFIRM.
            ->assertJsonPath('data.fx_confirmation_pending', 2);
    }

    public function test_committee_director_final_queue_lists_final_stage_requests(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        $this->makeRequest($this->bank, $de, 'FINAL');
        $this->makeRequest($this->bank, $de, 'FX_CONFIRM');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'EXEC', 'REJECTED');

        $response = $this->actingAs($director)->getJson('/api/dashboard/stats')->assertOk();
        $queue = $response->json('data.final_pending_queue');

        $this->assertCount(1, $queue);
        $this->assertSame('FINAL', $queue[0]['stage_code']);
    }

    public function test_committee_director_finalized_counters_reflect_terminal_outcomes(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'CLOSED', 'CLOSED');
        $this->makeRequest($this->bank, $de, 'EXEC', 'REJECTED');

        $this->actingAs($director)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.finalized_approved', 2)
            ->assertJsonPath('data.finalized_rejected', 1);
    }
}
