<?php

namespace Tests\Feature\Workflow;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\WorkflowVersionValidator;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowPublishTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $org;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);
        $this->org = Organization::query()->firstOrFail();
        $this->role = Role::query()->where('organization_id', $this->org->id)->firstOrFail();
    }

    /**
     * Build a minimal valid version: one initial non-final stage (with an EXECUTE
     * permission + an outgoing transition) → one final stage.
     */
    private function validDraft(): WorkflowVersion
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'flow_'.uniqid(), 'name' => 'Flow']);
        $version = $definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ])->refresh();

        $intake = $version->stages()->create(['code' => 'intake', 'name' => 'Intake', 'is_initial' => true, 'sort_order' => 0]);
        $done = $version->stages()->create(['code' => 'done', 'name' => 'Done', 'is_final' => true, 'final_outcome' => 'COMPLETED', 'sort_order' => 1]);

        $intake->stagePermissions()->create([
            'organization_id' => $this->org->id,
            'role_id' => $this->role->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Reviewers',
        ]);

        $approve = WorkflowAction::query()->create(['code' => 'APPROVE_'.uniqid(), 'name' => 'Approve', 'kind' => 'APPROVE']);
        $version->transitions()->create([
            'from_stage_id' => $intake->id,
            'action_id' => $approve->id,
            'to_stage_id' => $done->id,
        ]);

        return $version->refresh();
    }

    public function test_validate_returns_errors_for_an_empty_version(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'empty', 'name' => 'Empty']);
        $version = $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::DRAFT]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$version->id}/validate")
            ->assertOk();

        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('NO_INITIAL_STAGE', $codes);
        $this->assertContains('NO_FINAL_STAGE', $codes);
    }

    public function test_validate_returns_empty_for_a_valid_version(): void
    {
        $version = $this->validDraft();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$version->id}/validate")
            ->assertOk()
            ->assertJsonPath('data.errors', []);
    }

    public function test_publish_is_rejected_when_invalid(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'invalid', 'name' => 'Invalid']);
        $version = $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::DRAFT])->refresh();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$version->id}/publish", ['version' => $version->version])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_VALIDATION_FAILED');

        $this->assertDatabaseHas('workflow_versions', ['id' => $version->id, 'state' => 'DRAFT']);
    }

    public function test_publish_succeeds_for_a_valid_version(): void
    {
        $version = $this->validDraft();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$version->id}/publish", ['version' => $version->version])
            ->assertOk()
            ->assertJsonPath('data.state', 'PUBLISHED');

        $this->assertNotNull($version->fresh()->published_at);

        // Now immutable: a stage write is rejected.
        $stage = $version->stages()->first();
        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$version->id}/stages/{$stage->id}", ['name' => 'X', 'version' => $stage->version])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_IMMUTABLE_STATE');
    }

    public function test_publishing_archives_the_prior_published_version(): void
    {
        $first = $this->validDraft();
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$first->id}/publish", ['version' => $first->version])
            ->assertOk();

        // Clone the published version into a new DRAFT; deep-copy already carries
        // the source's valid stages/transitions/permissions, so it's publishable as-is.
        $clone = $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$first->id}/clone")
            ->assertCreated()
            ->json('data.id');

        $cloneVersion = WorkflowVersion::query()->findOrFail($clone);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$clone}/publish", ['version' => $cloneVersion->fresh()->version])
            ->assertOk();

        // The first version is now archived; the clone is published.
        $this->assertDatabaseHas('workflow_versions', ['id' => $first->id, 'state' => 'ARCHIVED']);
        $this->assertDatabaseHas('workflow_versions', ['id' => $clone, 'state' => 'PUBLISHED']);
    }

    public function test_validator_unit_flags_non_final_stage_without_transition_or_executor(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'partial', 'name' => 'Partial']);
        $version = $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::DRAFT])->refresh();
        $version->stages()->create(['code' => 'a', 'name' => 'A', 'is_initial' => true, 'sort_order' => 0]);
        $version->stages()->create(['code' => 'b', 'name' => 'B', 'is_final' => true, 'final_outcome' => 'COMPLETED', 'sort_order' => 1]);

        $errors = collect(app(WorkflowVersionValidator::class)->validate($version))->pluck('code');

        $this->assertContains('STAGE_NO_OUTGOING_TRANSITION', $errors);
        $this->assertContains('STAGE_NO_EXECUTOR', $errors);
    }

    public function test_validator_flags_transition_referencing_inactive_action(): void
    {
        $version = $this->validDraft();
        $transition = $version->transitions()->first();
        $transition->action->update(['is_active' => false]);

        $errors = collect(app(WorkflowVersionValidator::class)->validate($version))->pluck('code');

        $this->assertContains('TRANSITION_INVALID_ACTION', $errors);
    }

    public function test_validator_flags_stage_with_only_self_loop_transitions(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'self_loop', 'name' => 'Self Loop']);
        $version = $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::DRAFT])->refresh();
        $intake = $version->stages()->create(['code' => 'intake', 'name' => 'Intake', 'is_initial' => true, 'sort_order' => 0]);
        $version->stages()->create(['code' => 'done', 'name' => 'Done', 'is_final' => true, 'final_outcome' => 'COMPLETED', 'sort_order' => 1]);
        $intake->stagePermissions()->create([
            'organization_id' => $this->org->id,
            'role_id' => $this->role->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Reviewers',
        ]);
        $note = WorkflowAction::query()->create(['code' => 'NOTE_'.uniqid(), 'name' => 'Add Note', 'kind' => 'INFO']);
        $version->transitions()->create([
            'from_stage_id' => $intake->id,
            'action_id' => $note->id,
            'to_stage_id' => $intake->id,
        ]);

        $errors = collect(app(WorkflowVersionValidator::class)->validate($version))->pluck('code');

        $this->assertContains('STAGE_ONLY_SELF_LOOP', $errors);
    }

    public function test_validator_flags_executor_whose_sole_pinned_user_is_inactive(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'inactive_exec', 'name' => 'Inactive Executor']);
        $version = $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::DRAFT])->refresh();
        $intake = $version->stages()->create(['code' => 'intake', 'name' => 'Intake', 'is_initial' => true, 'sort_order' => 0]);
        $done = $version->stages()->create(['code' => 'done', 'name' => 'Done', 'is_final' => true, 'final_outcome' => 'COMPLETED', 'sort_order' => 1]);

        $inactiveUser = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => false]);
        $intake->stagePermissions()->create([
            'organization_id' => $this->org->id,
            'user_id' => $inactiveUser->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Sole Reviewer',
        ]);
        $approve = WorkflowAction::query()->create(['code' => 'APPROVE_'.uniqid(), 'name' => 'Approve', 'kind' => 'APPROVE']);
        $version->transitions()->create([
            'from_stage_id' => $intake->id,
            'action_id' => $approve->id,
            'to_stage_id' => $done->id,
        ]);

        $errors = collect(app(WorkflowVersionValidator::class)->validate($version))->pluck('code');

        $this->assertContains('STAGE_NO_EXECUTOR', $errors);
    }
}
