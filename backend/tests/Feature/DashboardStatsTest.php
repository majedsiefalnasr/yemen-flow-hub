<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private Bank $otherBank;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedPermissions();

        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function seedPermissions(): void
    {
        Permission::query()->firstOrCreate(
            ['slug' => 'request.create'],
            ['name_ar' => 'إنشاء طلب', 'name_en' => 'Create Request', 'group' => 'requests']
        );
    }

    private function makeBank(string $code): Bank
    {
        return Bank::query()->create([
            'name'      => "بنك {$code}",
            'code'      => $code,
            'is_active' => true,
        ]);
    }

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
        $de       = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $otherDe  = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        // 2 drafts in my bank, 3 in other bank
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        $this->makeRequest($this->otherBank, $otherDe, RequestStatus::DRAFT);
        $this->makeRequest($this->otherBank, $otherDe, RequestStatus::DRAFT);
        $this->makeRequest($this->otherBank, $otherDe, RequestStatus::DRAFT);

        $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.draft', 2);
    }

    public function test_data_entry_draft_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);

        $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.draft', 2);
    }

    public function test_data_entry_returned_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT_REJECTED_INTERNAL);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT_REJECTED_INTERNAL);

        $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.returned', 2);
    }

    public function test_data_entry_under_cby_processing_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::BANK_APPROVED);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_PENDING);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);

        $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.under_cby_processing', 3);
    }

    public function test_data_entry_completed_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);

        $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.completed', 2);
    }

    public function test_data_entry_returned_requests_contains_draft_rejected_only(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT_REJECTED_INTERNAL);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);

        $response = $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertOk();

        $returnedRequests = $response->json('data.returned_requests');
        $this->assertCount(1, $returnedRequests);
        $this->assertSame(RequestStatus::DRAFT_REJECTED_INTERNAL->value, $returnedRequests[0]['status']);
    }

    public function test_data_entry_draft_requests_contains_drafts_only(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT_REJECTED_INTERNAL);

        $response = $this->actingAs($de)
            ->getJson('/api/dashboard/stats')
            ->assertOk();

        $draftRequests = $response->json('data.draft_requests');
        $this->assertCount(1, $draftRequests);
        $this->assertSame(RequestStatus::DRAFT->value, $draftRequests[0]['status']);
    }

    public function test_data_entry_draft_requests_max_5(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        for ($i = 0; $i < 7; $i++) {
            $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        }

        $response = $this->actingAs($de)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertCount(5, $response->json('data.draft_requests'));
    }

    public function test_data_entry_recent_requests_max_5(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        for ($i = 0; $i < 7; $i++) {
            $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
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
        $de       = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br       = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $otherDe  = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        $this->makeRequest($this->otherBank, $otherDe, RequestStatus::SUBMITTED);

        $this->actingAs($br)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.pending_review', 1);
    }

    public function test_bank_reviewer_pending_review_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        $this->makeRequest($this->bank, $de, RequestStatus::BANK_REVIEW);
        $this->makeRequest($this->bank, $de, RequestStatus::BANK_APPROVED);

        $this->actingAs($br)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.pending_review', 2);
    }

    public function test_bank_reviewer_at_cby_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::BANK_APPROVED);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_PENDING);
        $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_SWIFT);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);

        $this->actingAs($br)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.at_cby', 3);
    }

    public function test_bank_reviewer_returned_by_support_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);

        $this->actingAs($br)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.returned_by_support', 1);
    }

    public function test_bank_reviewer_approved_completed_count(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);
        $this->makeRequest($this->bank, $de, RequestStatus::CUSTOMS_DECLARATION_ISSUED);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);

        $this->actingAs($br)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.approved_completed', 3);
    }

    public function test_bank_reviewer_review_queue_contains_submitted_and_bank_review(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $br = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        $this->makeRequest($this->bank, $de, RequestStatus::BANK_REVIEW);
        $this->makeRequest($this->bank, $de, RequestStatus::BANK_APPROVED);

        $response = $this->actingAs($br)->getJson('/api/dashboard/stats')->assertOk();
        $queue = $response->json('data.review_queue');
        $this->assertCount(2, $queue);
        $statuses = array_column($queue, 'status');
        $this->assertContains(RequestStatus::SUBMITTED->value, $statuses);
        $this->assertContains(RequestStatus::BANK_REVIEW->value, $statuses);
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

        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_PENDING);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_PENDING);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_IN_PROGRESS);

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.waiting_for_claim', 2);
    }

    public function test_support_committee_active_by_me_counts_only_my_claims(): void
    {
        $de  = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc  = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $sc2 = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $myRequest    = $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_IN_PROGRESS);
        $otherRequest = $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_IN_PROGRESS);

        app()->instance('workflow.transition.active', true);
        try {
            $myRequest->update(['claimed_by' => $sc->id]);
            $otherRequest->update(['claimed_by' => $sc2->id]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.active_by_me', 1);
    }

    public function test_support_committee_claimed_by_others_excludes_mine(): void
    {
        $de  = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc  = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $sc2 = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $myRequest    = $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_IN_PROGRESS);
        $otherRequest = $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_IN_PROGRESS);

        app()->instance('workflow.transition.active', true);
        try {
            $myRequest->update(['claimed_by' => $sc->id]);
            $otherRequest->update(['claimed_by' => $sc2->id]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.claimed_by_others', 1);
    }

    public function test_support_committee_recently_approved_counts_within_7_day_window(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        // Two approved within the 7-day window — set support_approved_at to now
        $r1 = $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_APPROVED);
        $r2 = $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_APPROVED);
        \Illuminate\Support\Facades\DB::table('import_requests')
            ->whereIn('id', [$r1->id, $r2->id])
            ->update(['support_approved_at' => now()]);

        // One approved 8 days ago — must NOT be counted
        $old = $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_APPROVED);
        \Illuminate\Support\Facades\DB::table('import_requests')
            ->where('id', $old->id)
            ->update(['support_approved_at' => now()->subDays(8)]);

        // One still pending — must NOT be counted
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_PENDING);

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.recently_approved', 2);
    }

    public function test_support_committee_recently_approved_excludes_older_records(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        // All approved but support_approved_at is older than 7 days
        $r1 = $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_APPROVED);
        $r2 = $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_APPROVED);
        \Illuminate\Support\Facades\DB::table('import_requests')
            ->whereIn('id', [$r1->id, $r2->id])
            ->update(['support_approved_at' => now()->subDays(10)]);

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.recently_approved', 0);
    }

    public function test_support_committee_recently_approved_null_support_approved_at_excluded(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        // Status is SUPPORT_APPROVED but support_approved_at is NULL — must not be counted
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_APPROVED);
        // support_approved_at remains NULL (makeRequest does not set it)

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.recently_approved', 0);
    }

    public function test_support_committee_queue_contains_pending_and_in_progress(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_PENDING);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_IN_PROGRESS);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_APPROVED);

        $response = $this->actingAs($sc)->getJson('/api/dashboard/stats')->assertOk();
        $queue = $response->json('data.support_queue');
        $this->assertCount(2, $queue);
        $statuses = array_column($queue, 'status');
        $this->assertContains(RequestStatus::SUPPORT_REVIEW_PENDING->value, $statuses);
        $this->assertContains(RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value, $statuses);
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
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);
        $otherDe = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);
        $this->makeRequest($this->otherBank, $otherDe, RequestStatus::DRAFT);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.total', 3);
    }

    public function test_bank_admin_pending_counts_submitted_and_bank_review(): void
    {
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        $this->makeRequest($this->bank, $de, RequestStatus::BANK_REVIEW);
        $this->makeRequest($this->bank, $de, RequestStatus::BANK_APPROVED);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.pending', 2);
    }

    public function test_bank_admin_approved_counts_terminal_approved_statuses(): void
    {
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);
        $this->makeRequest($this->bank, $de, RequestStatus::CUSTOMS_DECLARATION_ISSUED);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.approved', 3);
    }

    public function test_bank_admin_rejected_counts_support_executive_and_bank_terminal_rejections(): void
    {
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::BANK_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.rejected', 3);
    }

    public function test_bank_admin_total_financed_amount_sums_approved_requests(): void
    {
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED, ['amount' => 10000.00]);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED, ['amount' => 5000.00]);
        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT, ['amount' => 3000.00]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertEquals(15000.0, $response->json('data.total_financed_amount'));
    }

    public function test_bank_admin_total_financed_amount_is_zero_for_no_approved(): void
    {
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT, ['amount' => 5000.00]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertEquals(0.0, $response->json('data.total_financed_amount'));
    }

    public function test_bank_admin_stats_are_org_scoped(): void
    {
        $de      = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin   = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);
        $otherDe = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        $this->makeRequest($this->otherBank, $otherDe, RequestStatus::SUBMITTED);
        $this->makeRequest($this->otherBank, $otherDe, RequestStatus::SUBMITTED);

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
        $de      = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin   = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);
        $otherDe = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);

        $currentMonth = now()->format('Y-m');

        $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);
        $this->makeRequest($this->otherBank, $otherDe, RequestStatus::DRAFT);

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
        $de       = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $reviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::SUBMITTED);

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
            $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
            $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

            $aprilRequest = $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
            $aprilRequest->forceFill([
                'created_at' => '2026-04-30 23:59:59',
                'updated_at' => '2026-04-30 23:59:59',
            ])->saveQuietly();

            $mayRequest = $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
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
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);

        for ($i = 0; $i < 12; $i++) {
            $this->makeRequest($this->bank, $de, RequestStatus::DRAFT);
        }

        $response = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertCount(10, $response->json('data.recent_requests'));
    }

    public function test_support_committee_queue_includes_claimer_name(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $req = $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_IN_PROGRESS);
        app()->instance('workflow.transition.active', true);
        try {
            $req->update(['claimed_by' => $sc->id]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

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
        $sc  = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $this->makeRequest($this->bank, $de1, RequestStatus::SUPPORT_REVIEW_PENDING);
        $this->makeRequest($this->otherBank, $de2, RequestStatus::SUPPORT_REVIEW_PENDING);

        $this->actingAs($sc)
            ->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.waiting_for_claim', 2);
    }

    public function test_support_committee_queue_max_50(): void
    {
        $de = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $sc = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        for ($i = 0; $i < 55; $i++) {
            $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_REVIEW_PENDING);
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
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_SWIFT);
        $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_SWIFT);
        $this->makeRequest($this->bank, $de, RequestStatus::SWIFT_UPLOADED);

        $this->actingAs($swift)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.pending_swift_upload', 2);
    }

    public function test_swift_officer_uploaded_counts_requests_with_swift_uploaded_at(): void
    {
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        // Production swift_upload auto-chains SWIFT_UPLOADED to WAITING_FOR_VOTING_OPEN,
        // so the stable uploaded signal is the request-level upload timestamp.
        $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_VOTING_OPEN, ['swift_uploaded_at' => now()]);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN, ['swift_uploaded_at' => now()]);
        $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_SWIFT);

        $this->actingAs($swift)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.uploaded', 2);
    }

    public function test_swift_officer_final_approved_counts_executive_approved_and_completed(): void
    {
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);
        $this->makeRequest($this->bank, $de, RequestStatus::CUSTOMS_DECLARATION_ISSUED);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);

        $this->actingAs($swift)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.final_approved', 2);
    }

    public function test_swift_officer_final_rejected_counts_executive_rejected(): void
    {
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);

        $this->actingAs($swift)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.final_rejected', 1);
    }

    public function test_swift_officer_queue_shows_only_waiting_for_swift(): void
    {
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_SWIFT);
        $this->makeRequest($this->bank, $de, RequestStatus::SWIFT_UPLOADED);
        $this->makeRequest($this->bank, $de, RequestStatus::SUPPORT_APPROVED);

        $response = $this->actingAs($swift)->getJson('/api/dashboard/stats')->assertOk();
        $queue    = $response->json('data.swift_queue');
        $this->assertCount(1, $queue);
        $this->assertSame(RequestStatus::WAITING_FOR_SWIFT->value, $queue[0]['status']);
    }

    public function test_swift_officer_cannot_see_other_bank_requests(): void
    {
        $de1   = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $de2   = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        $this->makeRequest($this->bank, $de1, RequestStatus::WAITING_FOR_SWIFT);
        $this->makeRequest($this->otherBank, $de2, RequestStatus::WAITING_FOR_SWIFT);

        $this->actingAs($swift)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.pending_swift_upload', 1);
    }

    public function test_swift_officer_queue_max_50(): void
    {
        $de    = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);

        for ($i = 0; $i < 55; $i++) {
            $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_SWIFT);
        }

        $response = $this->actingAs($swift)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertCount(50, $response->json('data.swift_queue'));
    }

    // ─── EXECUTIVE_MEMBER stats ───────────────────────────────────────────────

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

    public function test_executive_member_waiting_for_voting_open_count(): void
    {
        $de   = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $exec = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_VOTING_OPEN);
        $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_VOTING_OPEN);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->actingAs($exec)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.waiting_for_voting_open', 2);
    }

    public function test_executive_member_active_voting_sessions_count(): void
    {
        $de   = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $exec = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_VOTING_OPEN);

        $this->actingAs($exec)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.active_voting_sessions', 2);
    }

    public function test_executive_member_decisions_approved_count(): void
    {
        $de   = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $exec = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);
        $this->makeRequest($this->bank, $de, RequestStatus::CUSTOMS_DECLARATION_ISSUED);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);

        $this->actingAs($exec)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.decisions_approved', 3);
    }

    public function test_executive_member_decisions_rejected_count(): void
    {
        $de   = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $exec = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);

        $this->actingAs($exec)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.decisions_rejected', 1);
    }

    public function test_executive_member_voting_queue_contains_correct_statuses(): void
    {
        $de   = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $exec = $this->makeUser(UserRole::EXECUTIVE_MEMBER);

        $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_VOTING_OPEN);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_CLOSED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);

        $response = $this->actingAs($exec)->getJson('/api/dashboard/stats')->assertOk();
        $queue    = $response->json('data.voting_queue');
        $statuses = array_column($queue, 'status');

        $this->assertContains(RequestStatus::WAITING_FOR_VOTING_OPEN->value, $statuses);
        $this->assertContains(RequestStatus::EXECUTIVE_VOTING_OPEN->value, $statuses);
        $this->assertCount(2, $queue);
    }

    // ─── COMMITTEE_DIRECTOR stats ─────────────────────────────────────────────

    public function test_committee_director_stats_returns_correct_kpi_keys(): void
    {
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        $this->actingAs($director)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'waiting_for_voting_open',
                'active_voting_sessions',
                'decisions_approved',
                'decisions_rejected',
                'finalized_decisions',
                'voting_queue',
                'customs_declaration_pending',
            ]]);
    }

    public function test_committee_director_sees_all_banks_requests(): void
    {
        $de1      = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $de2      = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        $this->makeRequest($this->bank, $de1, RequestStatus::WAITING_FOR_VOTING_OPEN);
        $this->makeRequest($this->otherBank, $de2, RequestStatus::WAITING_FOR_VOTING_OPEN);

        $this->actingAs($director)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.waiting_for_voting_open', 2);
    }

    public function test_committee_director_voting_queue_max_50(): void
    {
        $de       = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        for ($i = 0; $i < 55; $i++) {
            $this->makeRequest($this->bank, $de, RequestStatus::WAITING_FOR_VOTING_OPEN);
        }

        $response = $this->actingAs($director)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertCount(50, $response->json('data.voting_queue'));
    }

    public function test_committee_director_finalized_decisions_count(): void
    {
        $de       = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);
        $this->makeRequest($this->bank, $de, RequestStatus::CUSTOMS_DECLARATION_ISSUED);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_VOTING_OPEN);

        $this->actingAs($director)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.finalized_decisions', 4);
    }

    public function test_committee_director_customs_declaration_pending_lists_executive_approved(): void
    {
        $de       = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_APPROVED);
        $this->makeRequest($this->bank, $de, RequestStatus::COMPLETED);
        $this->makeRequest($this->bank, $de, RequestStatus::EXECUTIVE_REJECTED);

        $response = $this->actingAs($director)->getJson('/api/dashboard/stats')->assertOk();
        $pending  = $response->json('data.customs_declaration_pending');

        $this->assertCount(1, $pending);
        $this->assertSame(RequestStatus::EXECUTIVE_APPROVED->value, $pending[0]['status']);
    }
}
