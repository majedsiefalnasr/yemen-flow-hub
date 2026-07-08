<?php

namespace Tests\Feature\Workflow;

use App\Enums\FinalOutcome;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
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

class WorkflowStageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    private WorkflowVersion $draft;

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
    }

    public function test_add_stage_to_draft_version(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/stages", [
                'code' => 'intake',
                'name' => 'Intake',
                'is_initial' => true,
                'sla_duration_minutes' => 120,
            ])->assertCreated()
            ->assertJsonPath('data.code', 'intake')
            ->assertJsonPath('data.is_initial', true)
            ->assertJsonPath('data.sla_duration_minutes', 120);

        $this->assertDatabaseHas('workflow_stages', [
            'id' => $response->json('data.id'),
            'workflow_version_id' => $this->draft->id,
            'code' => 'intake',
        ]);
    }

    public function test_listing_stages_is_ordered_by_sort_order(): void
    {
        $this->draft->stages()->create(['code' => 'b', 'name' => 'B', 'sort_order' => 2]);
        $this->draft->stages()->create(['code' => 'a', 'name' => 'A', 'sort_order' => 1]);

        $this->actingAs($this->admin)->getJson("/api/v1/workflow-versions/{$this->draft->id}/stages")
            ->assertOk()
            ->assertJsonPath('data.0.code', 'a')
            ->assertJsonPath('data.1.code', 'b');
    }

    public function test_update_stage_in_draft_version(): void
    {
        $stage = $this->draft->stages()->create(['code' => 'review', 'name' => 'Review'])->refresh();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$this->draft->id}/stages/{$stage->id}", [
                'name' => 'Bank Review',
                'version' => 1,
            ])->assertOk()
            ->assertJsonPath('data.name', 'Bank Review')
            ->assertJsonPath('data.version', 2);
    }

    public function test_duplicate_code_within_version_is_rejected(): void
    {
        $this->draft->stages()->create(['code' => 'intake', 'name' => 'Intake']);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/stages", [
                'code' => 'intake',
                'name' => 'Other',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_same_code_allowed_across_different_versions(): void
    {
        $this->draft->stages()->create(['code' => 'intake', 'name' => 'Intake']);

        $otherVersion = $this->draft->definition->versions()->create([
            'version_number' => 2,
            'state' => WorkflowVersionState::DRAFT,
        ])->refresh();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$otherVersion->id}/stages", [
                'code' => 'intake',
                'name' => 'Intake',
            ])->assertCreated();
    }

    public function test_mutating_a_stage_on_a_published_version_is_rejected(): void
    {
        $stage = $this->draft->stages()->create(['code' => 'intake', 'name' => 'Intake'])->refresh();
        $this->draft->update(['state' => WorkflowVersionState::PUBLISHED]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/stages", [
                'code' => 'review',
                'name' => 'Review',
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_IMMUTABLE_STATE');

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$this->draft->id}/stages/{$stage->id}", [
                'name' => 'Changed',
                'version' => $stage->fresh()->version,
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_IMMUTABLE_STATE');
    }

    public function test_setting_a_new_initial_stage_demotes_the_previous_one(): void
    {
        $first = $this->draft->stages()->create(['code' => 'a', 'name' => 'A', 'is_initial' => true])->refresh();
        $second = $this->draft->stages()->create(['code' => 'b', 'name' => 'B'])->refresh();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$this->draft->id}/stages/{$second->id}", [
                'is_initial' => true,
                'version' => 1,
            ])->assertOk()
            ->assertJsonPath('data.is_initial', true);

        $this->assertDatabaseHas('workflow_stages', ['id' => $first->id, 'is_initial' => false]);
        $this->assertDatabaseHas('workflow_stages', ['id' => $second->id, 'is_initial' => true]);
    }

    public function test_stage_cannot_be_both_initial_and_final_on_create(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/stages", [
                'code' => 'weird',
                'name' => 'Weird',
                'is_initial' => true,
                'is_final' => true,
                'final_outcome' => FinalOutcome::COMPLETED->value,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('is_final');
    }

    public function test_stage_cannot_be_both_initial_and_final_on_update(): void
    {
        $stage = $this->draft->stages()->create([
            'code' => 'review',
            'name' => 'Review',
            'is_initial' => true,
        ])->refresh();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$this->draft->id}/stages/{$stage->id}", [
                'is_final' => true,
                'final_outcome' => FinalOutcome::COMPLETED->value,
                'version' => 1,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('is_final');

        $this->assertDatabaseHas('workflow_stages', [
            'id' => $stage->id,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);
    }

    public function test_delete_unbound_stage_in_draft(): void
    {
        $stage = $this->draft->stages()->create(['code' => 'temp', 'name' => 'Temp'])->refresh();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$this->draft->id}/stages/{$stage->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workflow_stages', ['id' => $stage->id]);
    }

    public function test_stage_update_rejects_stale_version(): void
    {
        $stage = $this->draft->stages()->create(['code' => 'intake', 'name' => 'Intake'])->refresh();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$this->draft->id}/stages/{$stage->id}", [
                'name' => 'Changed',
                'version' => 999,
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'STALE_RESOURCE');
    }

    public function test_non_admin_cannot_manage_stages(): void
    {
        $this->actingAs($this->nonAdmin)
            ->getJson("/api/v1/workflow-versions/{$this->draft->id}/stages")
            ->assertForbidden();
    }

    public function test_stage_creation_is_audited(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/stages", [
                'code' => 'intake',
                'name' => 'Intake',
            ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'GOVERNANCE_CREATED',
            'subject_type' => WorkflowStage::class,
            'subject_id' => $response->json('data.id'),
        ]);
    }
}
