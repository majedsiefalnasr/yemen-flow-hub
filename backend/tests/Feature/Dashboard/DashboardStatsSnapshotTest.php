<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class DashboardStatsSnapshotTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }

    public function test_empty_dashboard_payloads_are_characterized_by_role(): void
    {
        $bank = Bank::query()->create([
            'name' => 'Snapshot Bank',
            'code' => 'SNAP',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $snapshots = [
            UserRole::DATA_ENTRY->value => ['draft', 'returned', 'under_cby_processing', 'completed', 'draft_requests', 'returned_requests', 'recent_requests'],
            UserRole::BANK_REVIEWER->value => ['pending_review', 'at_cby', 'returned_by_support', 'approved_completed', 'review_queue', 'downstream_queue'],
            UserRole::BANK_ADMIN->value => ['total', 'pending', 'approved', 'rejected', 'total_financed_amount', 'recent_requests', 'monthly_requests'],
            UserRole::SUPPORT_COMMITTEE->value => ['waiting_for_claim', 'active_by_me', 'claimed_by_others', 'recently_approved', 'support_queue'],
            UserRole::SWIFT_OFFICER->value => ['pending_swift_upload', 'uploaded', 'final_approved', 'final_rejected', 'swift_queue'],
            UserRole::EXECUTIVE_MEMBER->value => ['waiting_for_voting_open', 'active_voting_sessions', 'decisions_approved', 'decisions_rejected', 'finalized_decisions', 'voting_queue'],
            UserRole::COMMITTEE_DIRECTOR->value => ['final_pending', 'final_pending_queue', 'finalized_approved', 'finalized_rejected', 'fx_confirmation_pending', 'customs_declaration_pending'],
            UserRole::CBY_ADMIN->value => ['total', 'approved', 'in_process', 'rejected', 'compliance_alerts', 'most_active_banks', 'monthly_requests', 'category_distribution', 'recent_requests'],
        ];

        foreach ($snapshots as $role => $expectedKeys) {
            $user = $this->user(UserRole::from($role), $bank);
            $data = $this->actingAs($user)->getJson('/api/dashboard/stats')->assertOk()->json('data');

            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $data, "Missing {$key} for {$role}");
            }
        }

        $defaultUser = User::query()->create([
            'name' => 'No Pivot Role',
            'email' => 'no-pivot-role@example.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->actingAs($defaultUser)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    private function user(UserRole $role, Bank $bank): User
    {
        static $counter = 0;
        $counter++;

        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => "Snapshot {$role->value}",
            'email' => "snapshot-{$counter}@example.test",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => in_array($role, [UserRole::DATA_ENTRY, UserRole::BANK_REVIEWER, UserRole::BANK_ADMIN], true) ? $bank->id : null,
            'is_active' => true,
        ]), $role);
    }
}
