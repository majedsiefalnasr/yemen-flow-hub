<?php

namespace Tests\Feature\Engine;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests governance-level RBAC via screen permissions.
 * The /api/auth/me endpoint returns screen_permissions and capabilities
 * derived from the governance role's screen_permission rows.
 */
class EngineGovernanceRbacTest extends TestCase
{
    use RefreshDatabase;

    private Organization $bankOrg;

    private Organization $cbyOrg;

    private Organization $sysOrg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $this->bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->cbyOrg = Organization::where('code', 'national_committee')->firstOrFail();
        $this->sysOrg = Organization::where('code', 'system_administration')->firstOrFail();
    }

    private function makeUser(string $email, UserRole $legacyRole, Organization $org, string $roleCode, string $teamCode, ?Bank $bank = null): User
    {
        $governanceRole = Role::where('code', $roleCode)->firstOrFail();
        $team = Team::where('code', $teamCode)->firstOrFail();

        $user = User::create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $legacyRole,
            'organization_id' => $org->id,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
        $user->teams()->attach($team);
        $user->roles()->attach($governanceRole);

        return $user;
    }

    // ── system_admin (CBY_ADMIN equivalent) ──────────────────────────────

    public function test_system_admin_has_manage_access_to_banks(): void
    {
        $admin = $this->makeUser('sysadmin@gov.test', UserRole::CBY_ADMIN, $this->sysOrg, 'system_admin', 'administration');

        $response = $this->actingAs($admin)->getJson('/api/auth/me');

        $response->assertOk();
        $this->assertContains('MANAGE', $response->json('data.screen_permissions.banks') ?? []);
    }

    public function test_system_admin_has_manage_access_to_users(): void
    {
        $admin = $this->makeUser('sysadmin2@gov.test', UserRole::CBY_ADMIN, $this->sysOrg, 'system_admin', 'administration');

        $response = $this->actingAs($admin)->getJson('/api/auth/me');

        $response->assertOk();
        $this->assertContains('MANAGE', $response->json('data.screen_permissions.users') ?? []);
    }

    public function test_system_admin_has_manage_access_to_organizations(): void
    {
        $admin = $this->makeUser('sysadmin3@gov.test', UserRole::CBY_ADMIN, $this->sysOrg, 'system_admin', 'administration');

        $response = $this->actingAs($admin)->getJson('/api/auth/me');

        $response->assertOk();
        $this->assertContains('MANAGE', $response->json('data.screen_permissions.organizations') ?? []);
    }

    public function test_system_admin_capabilities_include_manage_users_and_banks(): void
    {
        $admin = $this->makeUser('sysadmin4@gov.test', UserRole::CBY_ADMIN, $this->sysOrg, 'system_admin', 'administration');

        $response = $this->actingAs($admin)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.capabilities.manage_users', true)
            ->assertJsonPath('data.capabilities.manage_banks', true);
    }

    // ── bank_admin governance role ────────────────────────────────────────

    public function test_bank_admin_has_manage_access_to_users(): void
    {
        $bank = Bank::create(['name' => 'Gov Bank', 'code' => 'GVB', 'is_active' => true, 'organization_id' => $this->bankOrg->id]);
        $admin = $this->makeUser('bankgov@gov.test', UserRole::BANK_ADMIN, $this->bankOrg, 'bank_admin', 'bank_admin', $bank);

        $response = $this->actingAs($admin)->getJson('/api/auth/me');

        $response->assertOk();
        $this->assertContains('MANAGE', $response->json('data.screen_permissions.users') ?? []);
    }

    public function test_bank_admin_does_not_have_manage_access_to_organizations(): void
    {
        $bank = Bank::create(['name' => 'Gov Bank2', 'code' => 'GVB2', 'is_active' => true, 'organization_id' => $this->bankOrg->id]);
        $admin = $this->makeUser('bankgov2@gov.test', UserRole::BANK_ADMIN, $this->bankOrg, 'bank_admin', 'bank_admin', $bank);

        $response = $this->actingAs($admin)->getJson('/api/auth/me');

        $response->assertOk();
        $this->assertNotContains('MANAGE', $response->json('data.screen_permissions.organizations') ?? []);
    }

    // ── screen permissions matrix endpoint ───────────────────────────────

    public function test_screen_permissions_matrix_endpoint_accessible_to_system_admin(): void
    {
        $admin = $this->makeUser('sysadmin5@gov.test', UserRole::CBY_ADMIN, $this->sysOrg, 'system_admin', 'administration');

        // The matrix endpoint requires MANAGE on screen_permissions screen
        $this->actingAs($admin)
            ->getJson('/api/v1/screen-permissions/matrix')
            ->assertOk();
    }

    // ── intake (DATA_ENTRY) role ──────────────────────────────────────────

    public function test_intake_role_does_not_have_access_to_organizations_screen(): void
    {
        $bank = Bank::create(['name' => 'Intake Bank', 'code' => 'ITB', 'is_active' => true, 'organization_id' => $this->bankOrg->id]);
        $intake = $this->makeUser('intake@gov.test', UserRole::DATA_ENTRY, $this->bankOrg, 'intake', 'entry', $bank);

        $response = $this->actingAs($intake)->getJson('/api/auth/me');

        $response->assertOk();
        $this->assertEmpty($response->json('data.screen_permissions.organizations') ?? []);
    }

    public function test_intake_role_has_view_access_to_requests(): void
    {
        $bank = Bank::create(['name' => 'Intake Bank2', 'code' => 'ITB2', 'is_active' => true, 'organization_id' => $this->bankOrg->id]);
        $intake = $this->makeUser('intake2@gov.test', UserRole::DATA_ENTRY, $this->bankOrg, 'intake', 'entry', $bank);

        $response = $this->actingAs($intake)->getJson('/api/auth/me');

        $response->assertOk();
        $this->assertContains('VIEW', $response->json('data.screen_permissions.requests') ?? []);
    }

    // ── support role ──────────────────────────────────────────────────────

    public function test_support_role_has_view_access_to_audit_screen(): void
    {
        $support = $this->makeUser('support@gov.test', UserRole::SUPPORT_COMMITTEE, $this->cbyOrg, 'support', 'support');

        $response = $this->actingAs($support)->getJson('/api/auth/me');

        $response->assertOk();
        $this->assertContains('VIEW', $response->json('data.screen_permissions.audit') ?? []);
    }

    public function test_support_role_does_not_have_manage_banks(): void
    {
        $support = $this->makeUser('support2@gov.test', UserRole::SUPPORT_COMMITTEE, $this->cbyOrg, 'support', 'support');

        $response = $this->actingAs($support)->getJson('/api/auth/me');

        $response->assertOk();
        $this->assertNotContains('MANAGE', $response->json('data.screen_permissions.banks') ?? []);
    }
}
