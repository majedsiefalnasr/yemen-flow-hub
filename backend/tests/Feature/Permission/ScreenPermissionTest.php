<?php

namespace Tests\Feature\Permission;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Screen;
use App\Models\ScreenPermission;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $bankUser;

    private Role $systemAdminRole;

    private Role $intakeRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernanceSeeder::class);
        $this->seed(ScreenPermissionSeeder::class);

        $sysOrg = Organization::where('code', 'system_administration')->firstOrFail();
        $this->systemAdminRole = Role::where('code', 'system_admin')->firstOrFail();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.cby',
            'password' => bcrypt('password'),
            'role' => UserRole::CBY_ADMIN,
            'organization_id' => $sysOrg->id,
            'is_active' => true,
        ]);
        $this->admin->roles()->attach($this->systemAdminRole->id);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->intakeRole = Role::where('code', 'intake')->firstOrFail();

        $this->bankUser = User::create([
            'name' => 'Entry',
            'email' => 'entry@test.bank',
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->bankUser->roles()->attach($this->intakeRole->id);
    }

    // ── AC 1: Catalog seeded ──────────────────────────────────────────────

    public function test_catalog_has_14_screens(): void
    {
        $this->assertSame(14, Screen::count());
    }

    public function test_all_required_screens_exist(): void
    {
        $expected = [
            'organizations', 'teams', 'roles', 'banks', 'users',
            'merchants', 'workflow_designer', 'requests', 'reports',
            'audit', 'reference_data', 'screen_permissions', 'notifications', 'settings',
        ];

        foreach ($expected as $key) {
            $this->assertTrue(Screen::where('key', $key)->exists(), "Screen '{$key}' missing");
        }
    }

    public function test_get_screens_returns_all(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/screens')
            ->assertOk()
            ->assertJsonCount(14, 'data');
    }

    // ── AC 2: PUT grants persist unique ───────────────────────────────────

    public function test_put_screen_permissions_replaces_grants(): void
    {
        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->intakeRole->id}/screen-permissions", [
                'grants' => [
                    'reports' => ['VIEW'],
                ],
            ])
            ->assertOk();

        // 1 row: reports(VIEW). `requests` is no longer writable via this endpoint.
        $this->assertSame(1, ScreenPermission::where('role_id', $this->intakeRole->id)->count());
    }

    public function test_get_screen_permissions_reflects_grants(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/v1/roles/{$this->intakeRole->id}/screen-permissions")
            ->assertOk()
            ->assertJsonPath('data.role_id', $this->intakeRole->id)
            ->assertJsonPath('data.role_code', 'intake');
    }

    // ── AC 2: /auth/me/permissions reflects grants ────────────────────────

    public function test_auth_me_permissions_returns_screen_permissions(): void
    {
        $this->actingAs($this->bankUser)
            ->getJson('/api/auth/me/permissions')
            ->assertOk()
            ->assertJsonStructure(['data' => ['screen_permissions', 'capabilities']]);
    }

    public function test_auth_me_returns_screen_permissions(): void
    {
        $response = $this->actingAs($this->bankUser)
            ->getJson('/api/auth/me')
            ->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('screen_permissions', $data);
        // `requests` is now workflow-derived; it's present only if the role has an
        // active stage assignment in the published workflow. Assert the key type
        // instead of presence.
        $this->assertIsArray($data['screen_permissions']);
    }

    // ── AC 2: Gating uses screen_permissions not role codes ───────────────

    public function test_screen_permissions_derive_from_table_not_role_codes(): void
    {
        // Clear all grants for intake role
        ScreenPermission::where('role_id', $this->intakeRole->id)->delete();

        // Now /auth/me should return empty screen_permissions for the bank user
        $response = $this->actingAs($this->bankUser)
            ->getJson('/api/auth/me/permissions')
            ->assertOk();

        $sp = $response->json('data.screen_permissions');
        $this->assertEmpty($sp);

        // Add back only reports:VIEW
        $screenId = Screen::where('key', 'reports')->value('id');
        ScreenPermission::create([
            'role_id' => $this->intakeRole->id,
            'screen_id' => $screenId,
            'capability' => 'VIEW',
        ]);

        // Clear cache
        app(PermissionService::class)
            ->clearScreenPermissionCache($this->intakeRole->id);

        $response2 = $this->actingAs($this->bankUser)
            ->getJson('/api/auth/me/permissions')
            ->assertOk();

        $sp2 = $response2->json('data.screen_permissions');
        $this->assertArrayHasKey('reports', $sp2);
        $this->assertContains('VIEW', $sp2['reports']);
        $this->assertArrayNotHasKey('requests', $sp2);
    }

    // ── AC 3: Last-admin MANAGE removal blocked ──────────────────────────

    public function test_last_admin_manage_removal_blocked(): void
    {
        // system_admin is the only role with screen_permissions:MANAGE
        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->systemAdminRole->id}/screen-permissions", [
                'grants' => [
                    // deliberately omitting screen_permissions
                ],
            ])
            ->assertStatus(422);
    }

    public function test_manage_removal_allowed_when_another_role_has_it(): void
    {
        // Give intake role MANAGE on screen_permissions too
        $screenId = Screen::where('key', 'screen_permissions')->value('id');
        ScreenPermission::create([
            'role_id' => $this->intakeRole->id,
            'screen_id' => $screenId,
            'capability' => 'MANAGE',
        ]);

        // Now removing from system_admin should succeed. `grants` must be a non-empty
        // array (the endpoint's `required` rule rejects `[]`), so keep an unrelated,
        // harmless grant — the last-admin guard is what this test actually verifies.
        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->systemAdminRole->id}/screen-permissions", [
                'grants' => [
                    'reports' => ['VIEW'],
                ],
            ])
            ->assertOk();
    }

    public function test_put_validates_unknown_screen(): void
    {
        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->intakeRole->id}/screen-permissions", [
                'grants' => [
                    'nonexistent_screen' => ['VIEW'],
                ],
            ])
            ->assertStatus(422);
    }

    // ── Matrix excludes the real system_admin role code ──────────────────

    public function test_matrix_excludes_system_admin_role(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/screen-permissions/matrix')
            ->assertOk();

        $roleCodes = collect($response->json('data.roles'))->pluck('code')->all();
        $this->assertNotContains('system_admin', $roleCodes);
    }

    // ── Matrix no longer surfaces a role-keyed `requests` capability ─────

    public function test_matrix_response_never_includes_a_requests_key(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/screen-permissions/matrix')
            ->assertOk();

        foreach ($response->json('data.roles') as $role) {
            $this->assertArrayNotHasKey('requests', $role);
        }
    }

    // ── Merchants MANAGE carve-out: system_admin is always denied ────────

    public function test_system_admin_never_has_merchants_manage_even_if_granted(): void
    {
        $merchantsScreenId = Screen::where('key', 'merchants')->value('id');

        // Force-grant MANAGE directly, bypassing the admin UI, to prove the
        // code-level guard holds even when the data says otherwise.
        ScreenPermission::create([
            'role_id' => $this->systemAdminRole->id,
            'screen_id' => $merchantsScreenId,
            'capability' => 'MANAGE',
        ]);

        app(PermissionService::class)->clearScreenPermissionCache($this->systemAdminRole->id);

        $this->assertFalse(
            app(PermissionService::class)->userHasCapability($this->admin, 'merchants', 'MANAGE')
        );
    }

    public function test_system_admin_keeps_merchants_view_and_export(): void
    {
        $this->assertTrue(
            app(PermissionService::class)->userHasCapability($this->admin, 'merchants', 'VIEW')
        );
        $this->assertTrue(
            app(PermissionService::class)->userHasCapability($this->admin, 'merchants', 'EXPORT')
        );
    }
}
