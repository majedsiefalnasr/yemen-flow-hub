<?php

namespace Tests\Feature\Permission;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditScreenPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_index_denied_without_audit_view_capability(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(ScreenPermissionSeeder::class);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $intakeRole = Role::where('code', 'intake')->firstOrFail();

        $user = User::create([
            'name' => 'Entry',
            'email' => 'entry@test.bank',
            'password' => bcrypt('password'),
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $user->roles()->attach($intakeRole->id);

        // intake role has no audit grant per the seeder.
        $this->actingAs($user)
            ->getJson('/api/v1/audit-logs')
            ->assertForbidden();
    }

    public function test_audit_log_index_allowed_with_audit_view_capability(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(ScreenPermissionSeeder::class);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $supportOrg = Organization::where('code', 'national_committee')->first() ?? $bankOrg;
        $supportRole = Role::where('code', 'support')->firstOrFail();

        $user = User::create([
            'name' => 'Support',
            'email' => 'support@test.cby',
            'password' => bcrypt('password'),
            'organization_id' => $supportOrg->id,
            'is_active' => true,
        ]);
        $user->roles()->attach($supportRole->id);

        // support role has audit:VIEW per the seeder.
        $this->actingAs($user)
            ->getJson('/api/v1/audit-logs')
            ->assertOk();
    }
}
