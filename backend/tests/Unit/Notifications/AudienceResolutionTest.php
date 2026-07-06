<?php

namespace Tests\Unit\Notifications;

use App\Enums\StageAccessLevel;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Services\Workflow\StagePermissionResolver;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudienceResolutionTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowStage $stage;

    private Organization $org;

    private Team $team;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);

        $this->org = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $this->team = Team::query()->whereBelongsTo($this->org)->where('code', 'entry')->firstOrFail();
        $this->role = Role::query()->whereBelongsTo($this->org)->where('code', 'intake')->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'wp0-audience', 'name' => 'WP0 Audience']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
        ]);
        $this->stage = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'audience',
            'name' => 'Audience',
        ]);
    }

    public function test_execute_audience_matrix_matches_current_rules(): void
    {
        $orgUser = $this->user('org@example.test');
        $teamUser = $this->user('team@example.test', team: true);
        $roleUser = $this->user('role@example.test', role: true);
        $intersectionUser = $this->user('intersection@example.test', team: true, role: true);
        $specificUser = $this->user('specific@example.test');
        $inactiveUser = $this->user('inactive@example.test', role: true, active: false);

        $this->assertAudience(['org@example.test', 'team@example.test', 'role@example.test', 'intersection@example.test', 'specific@example.test'], [
            'organization_id' => $this->org->id,
        ]);
        $this->assertAudience(['team@example.test', 'intersection@example.test'], [
            'organization_id' => $this->org->id,
            'team_id' => $this->team->id,
        ]);
        $this->assertAudience(['role@example.test', 'intersection@example.test'], [
            'organization_id' => $this->org->id,
            'role_id' => $this->role->id,
        ]);
        $this->assertAudience(['intersection@example.test'], [
            'organization_id' => $this->org->id,
            'team_id' => $this->team->id,
            'role_id' => $this->role->id,
        ]);
        $this->assertAudience(['specific@example.test'], [
            'user_id' => $specificUser->id,
        ]);

        $this->assertNotContains($inactiveUser->id, $this->resolveAudience([
            'organization_id' => $this->org->id,
            'role_id' => $this->role->id,
        ]));
    }

    public function test_all_null_and_view_rows_are_characterized(): void
    {
        $user = $this->user('null-row@example.test');
        $row = $this->permission([]);

        $this->assertSame([], $this->resolveExecuteHolders());
        $this->assertTrue(app(StagePermissionResolver::class)->identityMatchesAny([
            'organization_id' => $user->organization_id,
            'team_ids' => [],
            'role_ids' => [],
            'user_id' => $user->id,
        ], [$row], StageAccessLevel::EXECUTE));

        $this->stage->stagePermissions()->delete();
        $this->permission([
            'organization_id' => $this->org->id,
            'access_level' => StageAccessLevel::VIEW,
        ]);

        $this->assertSame([], $this->resolveExecuteHolders());
    }

    public function test_inactive_role_and_team_still_match_until_wp7(): void
    {
        $this->team = Team::query()->create([
            'organization_id' => $this->org->id,
            'code' => 'inactive_wp0_team',
            'name' => 'Inactive WP0 Team',
            'is_active' => false,
        ]);
        $this->role = Role::query()->create([
            'organization_id' => $this->org->id,
            'code' => 'inactive_wp0_role',
            'name' => 'Inactive WP0 Role',
            'is_active' => false,
        ]);
        $user = $this->user('inactive-pivots@example.test', team: true, role: true);

        // @see WP-7 D8-N1 inactive team/role pivots currently still match.
        $this->assertAudience(['inactive-pivots@example.test'], [
            'organization_id' => $this->org->id,
            'team_id' => $this->team->id,
            'role_id' => $this->role->id,
        ]);

        $this->assertTrue(app(StagePermissionResolver::class)->userCanAccessStage($user, $this->stage, StageAccessLevel::EXECUTE));
    }

    /**
     * @param  array<int, string>  $emails
     * @param  array<string, mixed>  $permission
     */
    private function assertAudience(array $emails, array $permission): void
    {
        $ids = $this->resolveAudience($permission);
        $actual = User::query()->whereIn('id', $ids)->pluck('email')->all();

        $this->assertEqualsCanonicalizing($emails, $actual);
    }

    /**
     * @param  array<string, mixed>  $permission
     * @return array<int, int>
     */
    private function resolveAudience(array $permission): array
    {
        $this->stage->stagePermissions()->delete();
        $this->permission($permission);

        return $this->resolveExecuteHolders();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function permission(array $attributes): StagePermission
    {
        return $this->stage->stagePermissions()->create(array_merge([
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Audience',
        ], $attributes));
    }

    private function user(string $email, bool $team = false, bool $role = false, bool $active = true): User
    {
        $user = User::query()->create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => 'DATA_ENTRY',
            'organization_id' => $this->org->id,
            'is_active' => $active,
        ]);

        if ($team) {
            $user->teams()->attach($this->team);
        }
        if ($role) {
            $user->roles()->attach($this->role);
        }

        return $user;
    }

    /**
     * @return array<int, int>
     */
    private function resolveExecuteHolders(): array
    {
        $method = new \ReflectionMethod(EngineNotificationDispatcher::class, 'resolveExecuteHolders');
        $method->setAccessible(true);

        return $method->invoke(app(EngineNotificationDispatcher::class), $this->stage);
    }
}
