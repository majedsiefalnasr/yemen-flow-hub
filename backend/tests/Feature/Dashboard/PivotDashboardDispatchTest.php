<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivotDashboardDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(ScreenPermissionSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_each_role_gets_a_dashboard_response(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::query()->withUserRole($role)->first();
            if ($user === null) {
                continue;
            }

            $response = $this->actingAs($user)->getJson('/api/dashboard/stats');
            $response->assertOk();
        }
    }

    public function test_committee_director_and_executive_member_get_different_dashboards(): void
    {
        $director = $this->firstUserWithRole(UserRole::COMMITTEE_DIRECTOR);
        $executive = $this->firstUserWithRole(UserRole::EXECUTIVE_MEMBER);

        $directorResponse = $this->actingAs($director)->getJson('/api/dashboard/stats')->json();
        $executiveResponse = $this->actingAs($executive)->getJson('/api/dashboard/stats')->json();

        $this->assertNotEquals($directorResponse, $executiveResponse);
    }

    public function test_committee_director_can_access_audit_log(): void
    {
        $director = $this->firstUserWithRole(UserRole::COMMITTEE_DIRECTOR);

        $response = $this->actingAs($director)->getJson('/api/v1/audit-logs');
        $response->assertOk();
    }

    public function test_executive_member_can_access_audit_log_when_granted(): void
    {
        $executive = $this->firstUserWithRole(UserRole::EXECUTIVE_MEMBER);

        $response = $this->actingAs($executive)->getJson('/api/v1/audit-logs');
        $response->assertOk();
    }

    public function test_cby_admin_can_access_audit_log(): void
    {
        $admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);

        $response = $this->actingAs($admin)->getJson('/api/v1/audit-logs');
        $response->assertOk();
    }
}
