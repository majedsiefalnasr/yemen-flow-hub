<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\OrganizationClassification;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class AuditScopeTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
        $this->seed(ScreenPermissionSeeder::class);
        app(PermissionService::class)->clearAllScreenPermissionCaches();
    }

    #[Test]
    public function national_committee_user_with_audit_view_capability_can_access_audit_logs(): void
    {
        $user = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        // Ensure the organization has the correct classification
        $user->organization->update(['classification' => OrganizationClassification::NATIONAL_COMMITTEE]);
        $user->refresh();

        $this->actingAs($user)
            ->getJson('/api/v1/audit-logs')
            ->assertOk();
    }

    /**
     * SEC-002: bank_admin now carries audit.VIEW (ScreenPermissionSeeder) and
     * audit_logs.bank_id exists, so a bank-scoped user sees their own bank's
     * rows via index() instead of the blanket 403 this scenario used to hit
     * when audit_logs had no bank column to scope against.
     */
    #[Test]
    public function banking_sector_user_with_audit_view_capability_sees_only_own_bank_rows(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();

        $ownBank = Bank::create(['name' => 'Own Bank', 'code' => 'OWN', 'is_active' => true, 'organization_id' => $bankOrg->id]);
        $otherBank = Bank::create(['name' => 'Other Bank', 'code' => 'OTB', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $user = $this->makeUser(UserRole::BANK_ADMIN);
        $user->organization->update(['classification' => OrganizationClassification::BANKING_SECTOR]);
        $user->forceFill(['bank_id' => $ownBank->id])->save();
        $user = $user->fresh();

        AuditLog::create(['user_id' => $user->id, 'action' => AuditAction::USER_UPDATED->value, 'bank_id' => $ownBank->id, 'created_at' => now()]);
        AuditLog::create(['user_id' => $user->id, 'action' => AuditAction::USER_UPDATED->value, 'bank_id' => $otherBank->id, 'created_at' => now()]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/audit-logs')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function banking_sector_user_without_a_bank_id_is_denied_access(): void
    {
        // A bank-org user with no bank_id (e.g. mid-provisioning) has nothing
        // to scope against -- must not fall through to seeing all rows.
        $user = $this->makeUser(UserRole::BANK_ADMIN);
        $user->organization->update(['classification' => OrganizationClassification::BANKING_SECTOR]);

        $this->actingAs($user)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(403);
    }

    #[Test]
    public function system_admin_can_access_audit_logs(): void
    {
        $user = $this->makeUser(UserRole::CBY_ADMIN);

        // CBY_ADMIN is in OTHER organization by default in GovernanceSeeder
        $this->assertSame(OrganizationClassification::OTHER, $user->organization->classification);

        $this->actingAs($user)
            ->getJson('/api/v1/audit-logs')
            ->assertOk();
    }

    #[Test]
    public function user_without_audit_view_capability_is_denied_access(): void
    {
        $user = $this->makeUser(UserRole::DATA_ENTRY);

        $this->actingAs($user)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(403);
    }

    private function makeUser(UserRole $role): User
    {
        static $n = 0;
        $n++;

        $user = User::query()->create([
            'name' => "User {$n}",
            'email' => "user{$n}@audit-scope.test",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'is_active' => true,
        ]);

        return $this->assignGovernanceIdentity($user, $role);
    }
}
