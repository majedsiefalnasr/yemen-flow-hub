<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGovernanceHelpersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(BankSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_has_role_code_matches_assigned_pivot_role(): void
    {
        $admin = \App\Models\User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $this->assertTrue($admin->hasRoleCode('system_admin'));
        $this->assertFalse($admin->hasRoleCode('committee_director'));
    }

    public function test_has_any_role_code_matches_one_of_several(): void
    {
        $director = \App\Models\User::query()->where('role', UserRole::COMMITTEE_DIRECTOR->value)->firstOrFail();

        $this->assertTrue($director->hasAnyRoleCode(['committee_director', 'system_admin']));
        $this->assertFalse($director->hasAnyRoleCode(['committee_manager', 'system_admin']));
    }

    public function test_in_organization_matches_assigned_org(): void
    {
        $admin = \App\Models\User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $bankAdmin = \App\Models\User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();

        $this->assertTrue($admin->inOrganization('system_administration'));
        $this->assertFalse($admin->inOrganization('commercial_banks'));
        $this->assertTrue($bankAdmin->inOrganization('commercial_banks'));
    }

    public function test_has_role_code_works_with_preloaded_relation(): void
    {
        $admin = \App\Models\User::query()
            ->with('roles')
            ->where('role', UserRole::CBY_ADMIN->value)
            ->firstOrFail();

        $this->assertTrue($admin->relationLoaded('roles'));
        $this->assertTrue($admin->hasRoleCode('system_admin'));
    }
}
