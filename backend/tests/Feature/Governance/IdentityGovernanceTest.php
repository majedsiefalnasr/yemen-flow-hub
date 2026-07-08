<?php

namespace Tests\Feature\Governance;

use App\Enums\OrganizationClassification;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class IdentityGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_governance_schema_and_unique_constraints_exist(): void
    {
        foreach (['organizations', 'teams', 'roles', 'user_teams', 'user_roles'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} table to exist.");
        }

        $this->assertTrue(Schema::hasColumn('users', 'organization_id'));

        $organization = Organization::query()->create([
            'code' => 'test_org',
            'name' => 'Test organization',
            'classification' => OrganizationClassification::OTHER,
        ]);

        $this->expectException(QueryException::class);
        Organization::query()->create([
            'code' => 'test_org',
            'name' => 'Duplicate organization',
            'classification' => OrganizationClassification::OTHER,
        ]);
    }

    public function test_team_and_role_codes_are_unique_within_an_organization(): void
    {
        $organization = Organization::query()->create([
            'code' => 'test_org',
            'name' => 'Test organization',
            'classification' => OrganizationClassification::OTHER,
        ]);

        Team::query()->create([
            'organization_id' => $organization->id,
            'code' => 'operations',
            'name' => 'Operations',
        ]);

        Role::query()->create([
            'organization_id' => $organization->id,
            'code' => 'operator',
            'name' => 'Operator',
        ]);

        try {
            Team::query()->create([
                'organization_id' => $organization->id,
                'code' => 'operations',
                'name' => 'Duplicate operations',
            ]);
            $this->fail('Duplicate team code in the same organization must be rejected.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        Role::query()->create([
            'organization_id' => $organization->id,
            'code' => 'operator',
            'name' => 'Duplicate operator',
        ]);
    }

    public function test_governance_seeder_creates_expected_protected_defaults(): void
    {
        $this->seed(GovernanceSeeder::class);

        $this->assertDatabaseCount('organizations', 3);
        $this->assertDatabaseCount('teams', 8);
        $this->assertDatabaseCount('roles', 9);

        foreach (['commercial_banks', 'national_committee', 'system_administration'] as $code) {
            $this->assertDatabaseHas('organizations', [
                'code' => $code,
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        $this->assertDatabaseHas('roles', ['code' => 'committee_manager']);
        $this->assertDatabaseHas('roles', ['code' => 'committee_director']);
        $this->assertDatabaseHas('roles', ['code' => 'fx_confirm']);
        $this->assertDatabaseHas('teams', ['code' => 'administration']);
    }

    public function test_seeded_users_have_one_org_consistent_team_and_role(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(BankSeeder::class);
        $this->seed(UserSeeder::class);

        User::query()->with(['organization', 'teams.organization', 'roles.organization'])
            ->each(function (User $user): void {
                $this->assertNotNull($user->organization);
                $this->assertCount(1, $user->teams);
                $this->assertCount(1, $user->roles);
                $this->assertTrue($user->teams->first()->organization->is($user->organization));
                $this->assertTrue($user->roles->first()->organization->is($user->organization));

                if ($user->organization->code === 'commercial_banks') {
                    $this->assertNotNull($user->bank_id);
                } else {
                    $this->assertNull($user->bank_id);
                }
            });
    }

    public function test_system_governance_rows_cannot_be_deleted(): void
    {
        $this->seed(GovernanceSeeder::class);

        foreach ([
            Organization::query()->where('code', 'commercial_banks')->firstOrFail(),
            Team::query()->where('code', 'entry')->firstOrFail(),
            Role::query()->where('code', 'intake')->firstOrFail(),
        ] as $model) {
            try {
                $model->delete();
                $this->fail('System governance rows must be delete-protected.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('protected', $exception->getMessage());
            }
        }
    }
}
