<?php

namespace Tests\Feature\Workflow;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    private WorkflowVersion $draft;

    private WorkflowStage $stageA;

    private WorkflowStage $stageB;

    private WorkflowAction $approve;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);
        $this->nonAdmin = User::query()->withoutUserRole(UserRole::CBY_ADMIN)->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'flow', 'name' => 'Flow']);
        $this->draft = $definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ])->refresh();
        $this->stageA = $this->draft->stages()->create(['code' => 'a', 'name' => 'A', 'is_initial' => true]);
        $this->stageB = $this->draft->stages()->create(['code' => 'b', 'name' => 'B', 'is_final' => true]);
        $this->approve = WorkflowAction::query()->create(['code' => 'APPROVE', 'name' => 'Approve', 'kind' => 'APPROVE']);
    }

    public function test_add_transition(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/transitions", [
                'from_stage_id' => $this->stageA->id,
                'action_id' => $this->approve->id,
                'to_stage_id' => $this->stageB->id,
                'requires_comment' => true,
                'confirmation_message' => 'هل أنت متأكد؟',
            ])->assertCreated()
            ->assertJsonPath('data.from_stage_id', $this->stageA->id)
            ->assertJsonPath('data.to_stage_id', $this->stageB->id)
            ->assertJsonPath('data.requires_comment', true);
    }

    public function test_duplicate_from_stage_and_action_is_rejected(): void
    {
        WorkflowTransition::query()->create([
            'workflow_version_id' => $this->draft->id,
            'from_stage_id' => $this->stageA->id,
            'action_id' => $this->approve->id,
            'to_stage_id' => $this->stageB->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/transitions", [
                'from_stage_id' => $this->stageA->id,
                'action_id' => $this->approve->id,
                'to_stage_id' => $this->stageA->id,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('from_stage_id');
    }

    public function test_self_stage_transition_is_allowed(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/transitions", [
                'from_stage_id' => $this->stageA->id,
                'action_id' => $this->approve->id,
                'to_stage_id' => $this->stageA->id,
            ])->assertCreated()
            ->assertJsonPath('data.from_stage_id', $this->stageA->id)
            ->assertJsonPath('data.to_stage_id', $this->stageA->id);
    }

    public function test_cross_version_stage_is_rejected(): void
    {
        $otherVersion = $this->draft->definition->versions()->create([
            'version_number' => 2,
            'state' => WorkflowVersionState::DRAFT,
        ]);
        $foreignStage = $otherVersion->stages()->create(['code' => 'x', 'name' => 'X']);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/transitions", [
                'from_stage_id' => $foreignStage->id,
                'action_id' => $this->approve->id,
                'to_stage_id' => $this->stageB->id,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('from_stage_id');
    }

    public function test_mutating_transition_on_published_version_is_rejected(): void
    {
        $transition = WorkflowTransition::query()->create([
            'workflow_version_id' => $this->draft->id,
            'from_stage_id' => $this->stageA->id,
            'action_id' => $this->approve->id,
            'to_stage_id' => $this->stageB->id,
        ])->refresh();
        $this->draft->update(['state' => WorkflowVersionState::PUBLISHED]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$this->draft->id}/transitions/{$transition->id}", [
                'requires_comment' => true,
                'version' => $transition->version,
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_IMMUTABLE_STATE');
    }

    public function test_update_transition_behavioural_fields(): void
    {
        $transition = WorkflowTransition::query()->create([
            'workflow_version_id' => $this->draft->id,
            'from_stage_id' => $this->stageA->id,
            'action_id' => $this->approve->id,
            'to_stage_id' => $this->stageB->id,
        ])->refresh();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$this->draft->id}/transitions/{$transition->id}", [
                'requires_comment' => true,
                'version' => 1,
            ])->assertOk()
            ->assertJsonPath('data.requires_comment', true)
            ->assertJsonPath('data.version', 2);
    }

    public function test_delete_transition(): void
    {
        $transition = WorkflowTransition::query()->create([
            'workflow_version_id' => $this->draft->id,
            'from_stage_id' => $this->stageA->id,
            'action_id' => $this->approve->id,
            'to_stage_id' => $this->stageB->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$this->draft->id}/transitions/{$transition->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workflow_transitions', ['id' => $transition->id]);
    }

    public function test_stage_bound_to_a_transition_cannot_be_deleted(): void
    {
        WorkflowTransition::query()->create([
            'workflow_version_id' => $this->draft->id,
            'from_stage_id' => $this->stageA->id,
            'action_id' => $this->approve->id,
            'to_stage_id' => $this->stageB->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$this->draft->id}/stages/{$this->stageA->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_STAGE_BOUND');
    }

    public function test_action_used_by_a_transition_cannot_be_deleted_or_deactivated(): void
    {
        WorkflowTransition::query()->create([
            'workflow_version_id' => $this->draft->id,
            'from_stage_id' => $this->stageA->id,
            'action_id' => $this->approve->id,
            'to_stage_id' => $this->stageB->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-actions/{$this->approve->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_ACTION_PROTECTED');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-actions/{$this->approve->id}/deactivate", [
                'version' => $this->approve->fresh()->version,
            ])->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_ACTION_IN_USE');
    }

    public function test_non_admin_cannot_manage_transitions(): void
    {
        $this->actingAs($this->nonAdmin)
            ->getJson("/api/v1/workflow-versions/{$this->draft->id}/transitions")
            ->assertForbidden();
    }
}
