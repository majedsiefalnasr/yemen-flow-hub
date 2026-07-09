<?php

namespace Tests\Feature\Workflow;

use App\Enums\StageAccessLevel;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Workflow\StagePermissionResolver;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Guards ARCH-001: StagePermissionResolver::accessibleStageIds() was changed
 * from loading the entire stage_permissions table into PHP and grouping/filtering
 * in memory, to a bounded SQL query. These tests pin the SQL result to the exact
 * output of the pure-PHP evaluator (identityMatchesAny) across every identity
 * shape, so the optimization cannot silently widen or narrow access.
 *
 * The oracle is the resolver's own identityMatchesAny() — the pure-PHP evaluator
 * that is deliberately left untouched by ARCH-001 and still covers
 * userCanAccessStage()/PermissionService. Reusing it as the oracle makes this a
 * true parity check: the new SQL path must agree with the old PHP algorithm for
 * every (identity, access level) pair.
 */
class AccessibleStageIdsParityTest extends TestCase
{
    use RefreshDatabase;

    private StagePermissionResolver $resolver;

    private WorkflowVersion $version;

    /** @var array<string, WorkflowStage> */
    private array $stages = [];

    private Organization $orgA;

    private Organization $orgB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class]);
        $this->resolver = app(StagePermissionResolver::class);

        $this->orgA = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->orgB = Organization::where('code', 'national_committee')->firstOrFail();

        $def = WorkflowDefinition::create(['code' => 'PARITY_WF', 'name' => 'Parity WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        foreach (['wildcard', 'org', 'team', 'role', 'user', 'anded', 'execute', 'unreachable'] as $i => $code) {
            $this->stages[$code] = WorkflowStage::create([
                'workflow_version_id' => $this->version->id,
                'code' => strtoupper($code),
                'name' => ucfirst($code),
                'sort_order' => $i + 1,
                'is_initial' => $i === 0,
                'is_final' => false,
                'version' => 1,
            ]);
        }
    }

    private function team(Organization $org, string $code): Team
    {
        return Team::create(['organization_id' => $org->id, 'code' => $code, 'name' => $code, 'is_active' => true]);
    }

    private function role(Organization $org, string $code): Role
    {
        return Role::create(['organization_id' => $org->id, 'code' => $code, 'name' => $code, 'is_active' => true]);
    }

    private function user(Organization $org, array $teams, array $roles): User
    {
        $u = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@parity.test',
            'password' => bcrypt('pass'),
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        foreach ($teams as $t) {
            $u->teams()->attach($t);
        }
        foreach ($roles as $r) {
            $u->roles()->attach($r);
        }

        return $u->fresh(['teams', 'roles']);
    }

    private function perm(WorkflowStage $stage, array $attributes): void
    {
        StagePermission::create(array_merge([
            'stage_id' => $stage->id,
            'organization_id' => null,
            'team_id' => null,
            'role_id' => null,
            'user_id' => null,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'p',
            'version' => 1,
        ], $attributes));
    }

    /**
     * The oracle: reproduce the pre-ARCH-001 whole-table-in-PHP algorithm using
     * the untouched pure evaluator, returning the accessible stage-id set.
     *
     * @return array<int, int>
     */
    private function oracleStageIds(User $user, StageAccessLevel $required): array
    {
        $identity = $this->identityFor($user);

        return StagePermission::query()
            ->get()
            ->groupBy('stage_id')
            ->filter(fn (Collection $rows) => $this->resolver->identityMatchesAny($identity, $rows, $required))
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array{organization_id: int|null, team_ids: array<int>, role_ids: array<int>, user_id: int}
     */
    private function identityFor(User $user): array
    {
        return [
            'organization_id' => $user->organization_id !== null ? (int) $user->organization_id : null,
            'team_ids' => $user->teams()->where('teams.is_active', true)->pluck('teams.id')->map(fn ($id) => (int) $id)->all(),
            'role_ids' => $user->roles()->where('roles.is_active', true)->pluck('roles.id')->map(fn ($id) => (int) $id)->all(),
            'user_id' => (int) $user->getKey(),
        ];
    }

    private function assertParity(User $user, StageAccessLevel $required): void
    {
        $expected = $this->oracleStageIds($user, $required);

        $actual = $this->resolver->accessibleStageIds($user, $required);
        sort($actual);

        $this->assertSame(
            $expected,
            $actual,
            "SQL accessibleStageIds must match the PHP oracle for {$required->value}",
        );
    }

    public function test_parity_across_all_row_shapes_and_identities(): void
    {
        $teamA = $this->team($this->orgA, 'TA');
        $teamOther = $this->team($this->orgA, 'TO');
        $roleA = $this->role($this->orgA, 'RA');
        $roleOther = $this->role($this->orgA, 'RO');

        $userA = $this->user($this->orgA, [$teamA], [$roleA]);
        $userB = $this->user($this->orgB, [], []);

        // Rows spanning every match shape.
        $this->perm($this->stages['wildcard'], []); // all-null → matches anyone with an org
        $this->perm($this->stages['org'], ['organization_id' => $this->orgA->id]);
        $this->perm($this->stages['team'], ['team_id' => $teamA->id]);
        $this->perm($this->stages['role'], ['role_id' => $roleA->id]);
        $this->perm($this->stages['user'], ['user_id' => $userA->id]);
        // AND-in-row: org matches userA but role does not → userA excluded.
        $this->perm($this->stages['anded'], ['organization_id' => $this->orgA->id, 'role_id' => $roleOther->id]);
        // EXECUTE⊃VIEW discrimination.
        $this->perm($this->stages['execute'], ['role_id' => $roleA->id, 'access_level' => StageAccessLevel::EXECUTE]);
        // OR-across-rows on the same stage: two rows, only the second matches userA.
        $this->perm($this->stages['unreachable'], ['team_id' => $teamOther->id]);
        $this->perm($this->stages['unreachable'], ['user_id' => $userA->id]);

        foreach ([$userA, $userB] as $user) {
            $this->assertParity($user, StageAccessLevel::VIEW);
            $this->assertParity($user, StageAccessLevel::EXECUTE);
        }
    }

    public function test_parity_for_user_with_no_teams_or_roles(): void
    {
        // Empty team_ids/role_ids is the SQL edge case: `IN ()` must not be emitted,
        // and non-null team/role rows must not match.
        $roleA = $this->role($this->orgA, 'RNONE');
        $lonely = $this->user($this->orgA, [], []);

        $this->perm($this->stages['wildcard'], []);
        $this->perm($this->stages['org'], ['organization_id' => $this->orgA->id]);
        $this->perm($this->stages['role'], ['role_id' => $roleA->id]); // must NOT match (no roles)

        $this->assertParity($lonely, StageAccessLevel::VIEW);
        $this->assertParity($lonely, StageAccessLevel::EXECUTE);

        // Concretely: wildcard + org match, role stage does not.
        $ids = $this->resolver->accessibleStageIds($lonely, StageAccessLevel::VIEW);
        $this->assertContains($this->stages['wildcard']->id, $ids);
        $this->assertContains($this->stages['org']->id, $ids);
        $this->assertNotContains($this->stages['role']->id, $ids);
    }

    public function test_parity_for_multi_team_multi_role_identity(): void
    {
        $team1 = $this->team($this->orgA, 'MT1');
        $team2 = $this->team($this->orgA, 'MT2');
        $role1 = $this->role($this->orgA, 'MR1');
        $role2 = $this->role($this->orgA, 'MR2');
        $multi = $this->user($this->orgA, [$team1, $team2], [$role1, $role2]);

        $this->perm($this->stages['team'], ['team_id' => $team2->id]); // second of the user's teams
        $this->perm($this->stages['role'], ['role_id' => $role2->id]); // second of the user's roles
        $this->perm($this->stages['anded'], ['team_id' => $team1->id, 'role_id' => $role1->id]);

        $this->assertParity($multi, StageAccessLevel::VIEW);
        $this->assertParity($multi, StageAccessLevel::EXECUTE);
    }

    public function test_inactive_team_and_role_are_excluded_in_both_paths(): void
    {
        $activeTeam = $this->team($this->orgA, 'ACT');
        $inactiveTeam = Team::create(['organization_id' => $this->orgA->id, 'code' => 'INACT', 'name' => 'inact', 'is_active' => false]);
        $inactiveRole = Role::create(['organization_id' => $this->orgA->id, 'code' => 'IROLE', 'name' => 'irole', 'is_active' => false]);

        $user = $this->user($this->orgA, [$activeTeam], []);
        $user->teams()->attach($inactiveTeam);
        $user->roles()->attach($inactiveRole);
        $user = $user->fresh(['teams', 'roles']);

        // Rows keyed to the inactive membership must NOT grant access in either path.
        $this->perm($this->stages['team'], ['team_id' => $inactiveTeam->id]);
        $this->perm($this->stages['role'], ['role_id' => $inactiveRole->id]);
        $this->perm($this->stages['user'], ['team_id' => $activeTeam->id]);

        $this->assertParity($user, StageAccessLevel::VIEW);

        $ids = $this->resolver->accessibleStageIds($user, StageAccessLevel::VIEW);
        $this->assertNotContains($this->stages['team']->id, $ids);
        $this->assertNotContains($this->stages['role']->id, $ids);
        $this->assertContains($this->stages['user']->id, $ids);
    }
}
