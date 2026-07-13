<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\Screen;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

/**
 * Phase D0.6: the analytics/governance dashboard families are gated on their own
 * screen capability, enforced by the backend independently of the frontend. A
 * user without the capability does not receive that family's analytics, and
 * revoking the capability removes access.
 */
class DashboardFamilyCapabilityTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
        $this->seed(ScreenPermissionSeeder::class);
    }

    private function makeUser(UserRole $role): User
    {
        $user = User::query()->create([
            'name' => 'Cap User',
            'email' => 'cap-'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        return $this->assignGovernanceIdentity($user, $role);
    }

    private function revokeCapability(string $roleCode, string $screenKey): void
    {
        $roleId = Role::query()->where('code', $roleCode)->value('id');
        $screenId = Screen::query()->where('key', $screenKey)->value('id');
        DB::table('screen_permissions')
            ->where('role_id', $roleId)->where('screen_id', $screenId)->delete();
        app(PermissionService::class)->clearScreenPermissionCache((int) $roleId);
    }

    public function test_bank_admin_with_capability_gets_org_analytics(): void
    {
        $admin = $this->makeUser(UserRole::BANK_ADMIN);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure(['data' => ['total', 'pending', 'approved', 'rejected', 'monthly_requests']]);
    }

    public function test_revoking_org_analytics_capability_removes_analytics_access(): void
    {
        $admin = $this->makeUser(UserRole::BANK_ADMIN);
        $this->revokeCapability('bank_admin', 'org_analytics');

        $data = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk()->json('data');

        $this->assertArrayNotHasKey('monthly_requests', $data);
        $this->assertArrayNotHasKey('total_financed_amount', $data);
    }

    public function test_system_admin_with_capability_gets_system_analytics(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure(['data' => ['compliance_alerts', 'most_active_banks']]);
    }

    public function test_revoking_system_dashboard_capability_removes_analytics_access(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);
        $this->revokeCapability('system_admin', 'system_dashboard');

        $data = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk()->json('data');

        $this->assertArrayNotHasKey('compliance_alerts', $data);
        $this->assertArrayNotHasKey('most_active_banks', $data);
    }

    public function test_a_workflow_user_never_receives_analytics_family_data(): void
    {
        // A support committee member holds no analytics capability; they must not
        // receive bank or system analytics from the shared stats endpoint.
        $support = $this->makeUser(UserRole::SUPPORT_COMMITTEE);

        $data = $this->actingAs($support)->getJson('/api/dashboard/stats')->assertOk()->json('data');

        $this->assertArrayNotHasKey('monthly_requests', $data);
        $this->assertArrayNotHasKey('compliance_alerts', $data);
    }
}
