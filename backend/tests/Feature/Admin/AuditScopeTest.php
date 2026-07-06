<?php

namespace Tests\Feature\Admin;

use App\Enums\OrganizationClassification;
use App\Enums\UserRole;
use App\Models\Screen;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    #[Test]
    public function banking_sector_user_with_audit_view_capability_is_denied_access(): void
    {
        $user = $this->makeUser(UserRole::BANK_ADMIN);

        // Ensure the organization has the correct classification
        $user->organization->update(['classification' => OrganizationClassification::BANKING_SECTOR]);

        // Manually grant audit VIEW capability to the bank role
        $screen = Screen::query()->where('key', 'audit')->firstOrFail();
        DB::table('screen_permissions')->insert([
            'role_id' => $user->role()->id,
            'screen_id' => $screen->id,
            'capability' => 'VIEW',
        ]);
        app(PermissionService::class)->clearScreenPermissionCache($user->role()->id);

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
