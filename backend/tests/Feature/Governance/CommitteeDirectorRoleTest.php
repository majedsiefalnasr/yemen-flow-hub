<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeDirectorRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_committee_director_role_code_exists_and_is_distinct_from_committee_manager(): void
    {
        $this->seed(GovernanceSeeder::class);

        $organization = Organization::query()->where('code', 'national_committee')->firstOrFail();
        $team = Team::query()->where('organization_id', $organization->id)->where('code', 'executive')->firstOrFail();

        $director = Role::query()->where('organization_id', $organization->id)->where('code', 'committee_director')->first();
        $manager = Role::query()->where('organization_id', $organization->id)->where('code', 'committee_manager')->first();

        $this->assertNotNull($director, 'committee_director role must exist');
        $this->assertNotNull($manager, 'committee_manager role must still exist');
        $this->assertNotEquals($director->id, $manager->id);
    }

    public function test_seeded_committee_director_user_has_committee_director_role_code(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);

        $director = $this->firstUserWithRole(UserRole::COMMITTEE_DIRECTOR);

        $this->assertTrue($director->roles->contains('code', 'committee_director'));
        $this->assertFalse($director->roles->contains('code', 'committee_manager'));
    }

    public function test_seeded_executive_member_user_keeps_committee_manager_role_code(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);

        $executive = $this->firstUserWithRole(UserRole::EXECUTIVE_MEMBER);

        $this->assertTrue($executive->roles->contains('code', 'committee_manager'));
        $this->assertFalse($executive->roles->contains('code', 'committee_director'));
    }

    public function test_committee_director_has_screen_permissions(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(ScreenPermissionSeeder::class);

        $director = Role::query()->where('code', 'committee_director')->firstOrFail();
        $this->assertTrue($director->screenPermissions()->exists());
    }
}
