<?php

namespace Tests\Feature\Workflow;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Workflow\StagePermissionResolver;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagePermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    private WorkflowVersion $draft;

    private WorkflowStage $stage;

    private Organization $org;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->nonAdmin = User::query()->where('role', '!=', UserRole::CBY_ADMIN->value)->firstOrFail();

        $this->org = Organization::query()->firstOrFail();
        $this->role = Role::query()->where('organization_id', $this->org->id)->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'flow', 'name' => 'Flow']);
        $this->draft = $definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ])->refresh();
        $this->stage = $this->draft->stages()->create(['code' => 'intake', 'name' => 'Intake']);
    }

    public function test_add_stage_permission_row(): void
    {
        $team = Team::query()->create([
            'organization_id' => $this->org->id,
            'code' => 'reviewers',
            'name' => 'Reviewers Team',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/permissions", [
                'organization_id' => $this->org->id,
                'team_id' => $team->id,
                'role_id' => $this->role->id,
                'access_level' => 'EXECUTE',
                'display_label' => 'مراجعو البنك',
            ])->assertCreated()
            ->assertJsonPath('data.access_level', 'EXECUTE')
            ->assertJsonPath('data.display_label', 'مراجعو البنك');

        $this->assertDatabaseHas('stage_permissions', [
            'id' => $response->json('data.id'),
            'stage_id' => $this->stage->id,
            'team_id' => $team->id,
            'role_id' => $this->role->id,
        ]);
    }

    public function test_row_requires_organization(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/permissions", [
                'access_level' => 'VIEW',
                'display_label' => 'Everyone',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_row_allows_organization_only_without_team_or_role(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/permissions", [
                'organization_id' => $this->org->id,
                'access_level' => 'VIEW',
                'display_label' => 'Org-wide access',
            ])->assertCreated();

        $this->assertDatabaseHas('stage_permissions', [
            'id' => $response->json('data.id'),
            'organization_id' => $this->org->id,
            'team_id' => null,
            'role_id' => null,
        ]);
    }

    public function test_role_must_belong_to_organization(): void
    {
        $otherOrg = Organization::query()->create(['code' => 'OTHER', 'name' => 'Other Org']);
        $team = Team::query()->create([
            'organization_id' => $this->org->id,
            'code' => 'reviewers',
            'name' => 'Reviewers Team',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/permissions", [
                'organization_id' => $otherOrg->id,
                'team_id' => $team->id,
                'role_id' => $this->role->id,
                'access_level' => 'VIEW',
                'display_label' => 'Mismatch',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');
    }

    public function test_update_access_level_and_label(): void
    {
        $permission = $this->stage->stagePermissions()->create([
            'role_id' => $this->role->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Reviewers',
        ])->refresh();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-stages/{$this->stage->id}/permissions/{$permission->id}", [
                'access_level' => 'EXECUTE',
                'display_label' => 'Bank Reviewers',
                'version' => 1,
            ])->assertOk()
            ->assertJsonPath('data.access_level', 'EXECUTE')
            ->assertJsonPath('data.version', 2);
    }

    public function test_mutating_permission_on_published_version_is_rejected(): void
    {
        $team = Team::query()->create([
            'organization_id' => $this->org->id,
            'code' => 'reviewers',
            'name' => 'Reviewers Team',
        ]);

        $permission = $this->stage->stagePermissions()->create([
            'role_id' => $this->role->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Reviewers',
        ])->refresh();
        $this->draft->update(['state' => WorkflowVersionState::PUBLISHED]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/permissions", [
                'organization_id' => $this->org->id,
                'team_id' => $team->id,
                'role_id' => $this->role->id,
                'access_level' => 'VIEW',
                'display_label' => 'Another',
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_IMMUTABLE_STATE');

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-stages/{$this->stage->id}/permissions/{$permission->id}", [
                'display_label' => 'Changed',
                'version' => $permission->fresh()->version,
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_IMMUTABLE_STATE');
    }

    public function test_delete_permission(): void
    {
        $permission = $this->stage->stagePermissions()->create([
            'role_id' => $this->role->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Reviewers',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-stages/{$this->stage->id}/permissions/{$permission->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('stage_permissions', ['id' => $permission->id]);
    }

    public function test_resolver_derives_access_from_rows_via_db(): void
    {
        $member = User::query()->create([
            'name' => 'Member',
            'email' => 'member-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => UserRole::BANK_REVIEWER->value,
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);
        $member->roles()->attach($this->role->id);

        $this->stage->stagePermissions()->create([
            'organization_id' => $this->org->id,
            'role_id' => $this->role->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Reviewers',
        ]);

        $resolver = app(StagePermissionResolver::class);
        $this->assertTrue($resolver->userCanAccessStage($member, $this->stage, StageAccessLevel::EXECUTE));
        $this->assertContains($this->stage->id, $resolver->accessibleStageIds($member, StageAccessLevel::VIEW));

        // A different user without the role gets no access.
        $outsider = User::query()->create([
            'name' => 'Outsider',
            'email' => 'outsider-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => UserRole::DATA_ENTRY->value,
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);
        $this->assertFalse($resolver->userCanAccessStage($outsider, $this->stage, StageAccessLevel::VIEW));
    }

    public function test_non_admin_cannot_manage_permissions(): void
    {
        $this->actingAs($this->nonAdmin)
            ->getJson("/api/v1/workflow-stages/{$this->stage->id}/permissions")
            ->assertForbidden();
    }
}
