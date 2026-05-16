<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function makeRequest(Bank $bank, User $creator, RequestStatus $status): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id'           => $bank->id,
                'created_by'        => $creator->id,
                'currency'          => 'USD',
                'amount'            => 10000.00,
                'supplier_name'     => 'Supplier Co.',
                'goods_description' => 'Industrial equipment',
                'port_of_entry'     => 'Aden Port',
                'status'            => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
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

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/dashboard/stats')->assertUnauthorized();
    }
}
