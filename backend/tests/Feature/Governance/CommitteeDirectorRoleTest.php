<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use Database\Seeders\DatabaseSeeder;
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

    /**
     * RBAC-005 regression: the sidebar offers the Director the requests surface
     * (nav.workflows, guarded by the `requests` screen), and /workflows is
     * route-guarded on the `requests` screen capability. The `requests`
     * capability is derived from a stage_permissions EXECUTE row, so the Director
     * only earns it once they own an executable stage. Under the corrected V2
     * workflow the Director owns FINAL (WF-002); on the uncorrected V1 they own
     * no stage and /auth/me lacked `requests`, dead-ending the visible nav at
     * /forbidden.
     *
     * This asserts that on the published V2 the seeded Director's actual
     * /auth/me capability map grants `requests`, keeping the offered navigation
     * and the route guard in agreement.
     */
    public function test_seeded_director_auth_me_grants_the_requests_capability_its_nav_offers(): void
    {
        $this->seed(DatabaseSeeder::class);
        // Publish the corrected V2 (FINAL owned by committee_director) through the
        // real designer-driven, validated publish path.
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);

        $director = $this->firstUserWithRole(UserRole::COMMITTEE_DIRECTOR);

        $this->actingAs($director)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role.code', 'committee_director')
            // The `requests` screen backs both the visible nav link and the
            // /workflows route guard; its presence proves the nav is not a
            // dead-end for the Director.
            ->assertJsonStructure(['data' => ['screen_permissions' => ['requests']]]);
    }
}
