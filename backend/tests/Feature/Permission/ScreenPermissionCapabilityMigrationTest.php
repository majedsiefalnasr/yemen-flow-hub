<?php

namespace Tests\Feature\Permission;

use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScreenPermissionCapabilityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_update_delete_rows_collapse_to_manage_without_duplicate_violation(): void
    {
        $this->seed(GovernanceSeeder::class);

        $roleId = DB::table('roles')->where('code', 'bank_admin')->value('id');
        $screenId = DB::table('screens')->insertGetId([
            'key' => 'merchants_test_screen',
            'label' => 'Test Screen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // RefreshDatabase already ran every migration -- including this one --
        // before the test body executes, so the legacy rows seeded below would
        // never be touched by a second `migrate` call (Laravel skips
        // already-batched migrations). Roll this migration back first so
        // seeding legacy rows and re-running `up()` actually exercises it.
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_07_05_000001_collapse_screen_permission_capabilities.php', '--realpath' => false]);

        // Seed CREATE, UPDATE, DELETE, and an existing MANAGE row for the same
        // (role, screen) pair -- the migration must dedupe rather than violate
        // the unique(role_id, screen_id, capability) constraint.
        foreach (['CREATE', 'UPDATE', 'DELETE', 'MANAGE'] as $capability) {
            DB::table('screen_permissions')->insert([
                'role_id' => $roleId,
                'screen_id' => $screenId,
                'capability' => $capability,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_05_000001_collapse_screen_permission_capabilities.php', '--realpath' => false]);

        $capabilities = DB::table('screen_permissions')
            ->where('role_id', $roleId)
            ->where('screen_id', $screenId)
            ->pluck('capability')
            ->all();

        $this->assertSame(['MANAGE'], $capabilities);
    }

    public function test_system_admin_manage_on_merchants_is_stripped(): void
    {
        $this->seed(GovernanceSeeder::class);

        $systemAdminRoleId = DB::table('roles')->where('code', 'system_admin')->value('id');
        $merchantsScreenId = DB::table('screens')->insertGetId([
            'key' => 'merchants',
            'label' => 'Merchants',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // See the note in the first test: this migration already ran once
        // during RefreshDatabase setup, so roll it back before seeding the
        // pre-migration state we want `up()` to act on.
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_07_05_000001_collapse_screen_permission_capabilities.php', '--realpath' => false]);

        DB::table('screen_permissions')->insert([
            ['role_id' => $systemAdminRoleId, 'screen_id' => $merchantsScreenId, 'capability' => 'VIEW', 'created_at' => now(), 'updated_at' => now()],
            ['role_id' => $systemAdminRoleId, 'screen_id' => $merchantsScreenId, 'capability' => 'MANAGE', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_05_000001_collapse_screen_permission_capabilities.php', '--realpath' => false]);

        $capabilities = DB::table('screen_permissions')
            ->where('role_id', $systemAdminRoleId)
            ->where('screen_id', $merchantsScreenId)
            ->pluck('capability')
            ->all();

        $this->assertSame(['VIEW'], $capabilities);
    }
}
