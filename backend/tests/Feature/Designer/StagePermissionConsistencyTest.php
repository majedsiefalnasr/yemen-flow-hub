<?php

namespace Tests\Feature\Designer;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagePermissionConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $orgA;

    private Organization $orgB;

    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);
        $this->orgA = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $this->orgB = Organization::query()->where('code', 'national_committee')->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'wp0-permissions', 'name' => 'WP0 Permissions']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ]);
        $this->stage = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'review',
            'name' => 'Review',
        ]);
    }

    public function test_partial_team_update_cannot_cross_existing_organization(): void
    {
        $permission = $this->permission();
        $foreignTeam = Team::query()->whereBelongsTo($this->orgB)->firstOrFail();

        $this->actingAs($this->admin)
            ->putJson($this->permissionUrl($permission->id), [
                'team_id' => $foreignTeam->id,
                'version' => $permission->version,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_partial_role_update_cannot_cross_existing_organization(): void
    {
        $permission = $this->permission();
        $foreignRole = Role::query()->whereBelongsTo($this->orgB)->firstOrFail();

        $this->actingAs($this->admin)
            ->putJson($this->permissionUrl($permission->id), [
                'role_id' => $foreignRole->id,
                'version' => $permission->version,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_partial_user_update_cannot_cross_existing_organization(): void
    {
        $permission = $this->permission();
        $foreignUser = User::query()->where('organization_id', $this->orgB->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->putJson($this->permissionUrl($permission->id), [
                'user_id' => $foreignUser->id,
                'version' => $permission->version,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_label_and_same_org_partial_updates_still_succeed(): void
    {
        $permission = $this->permission();
        $sameOrgTeam = Team::query()
            ->whereBelongsTo($this->orgA)
            ->where('id', '!=', $permission->team_id)
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->putJson($this->permissionUrl($permission->id), [
                'display_label' => 'Updated label',
                'version' => $permission->version,
            ])
            ->assertOk()
            ->assertJsonPath('data.display_label', 'Updated label');

        $permission->refresh();

        $this->actingAs($this->admin)
            ->putJson($this->permissionUrl($permission->id), [
                'team_id' => $sameOrgTeam->id,
                'version' => $permission->version,
            ])
            ->assertOk()
            ->assertJsonPath('data.team_id', $sameOrgTeam->id);
    }

    public function test_full_update_with_consistent_organization_fields_succeeds(): void
    {
        $permission = $this->permission();
        $role = Role::query()->whereBelongsTo($this->orgB)->firstOrFail();
        $team = Team::query()->whereBelongsTo($this->orgB)->firstOrFail();

        $this->actingAs($this->admin)
            ->putJson($this->permissionUrl($permission->id), [
                'organization_id' => $this->orgB->id,
                'team_id' => $team->id,
                'role_id' => $role->id,
                'access_level' => 'EXECUTE',
                'display_label' => 'Committee execute',
                'version' => $permission->version,
            ])
            ->assertOk()
            ->assertJsonPath('data.organization_id', $this->orgB->id)
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.role_id', $role->id);
    }

    private function permission(): StagePermission
    {
        $team = Team::query()->whereBelongsTo($this->orgA)->firstOrFail();
        $role = Role::query()->whereBelongsTo($this->orgA)->firstOrFail();

        return $this->stage->stagePermissions()->create([
            'organization_id' => $this->orgA->id,
            'team_id' => $team->id,
            'role_id' => $role->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Bank viewers',
        ])->refresh();
    }

    private function permissionUrl(int $permissionId): string
    {
        return "/api/v1/workflow-stages/{$this->stage->id}/permissions/{$permissionId}";
    }
}
