<?php

namespace Tests\Feature\Workflow;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowAction;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkflowActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowActionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, WorkflowActionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->nonAdmin = User::query()->where('role', '!=', UserRole::CBY_ADMIN->value)->firstOrFail();
    }

    public function test_seeded_default_actions_exist(): void
    {
        $this->assertDatabaseHas('workflow_actions', ['code' => 'APPROVE', 'kind' => 'APPROVE', 'is_system' => true]);
        $this->assertDatabaseHas('workflow_actions', ['code' => 'SAVE_DRAFT', 'kind' => 'DRAFT', 'is_system' => true]);
        $this->assertDatabaseHas('workflow_actions', ['code' => 'FINAL_APPROVE', 'kind' => 'APPROVE', 'is_system' => true]);
    }

    public function test_create_action(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/workflow-actions', [
            'code' => 'ESCALATE',
            'name' => 'تصعيد',
            'kind' => 'CUSTOM',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'ESCALATE')
            ->assertJsonPath('data.kind', 'CUSTOM')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_create_rejects_invalid_kind(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/workflow-actions', [
            'code' => 'BAD',
            'name' => 'Bad',
            'kind' => 'NONSENSE',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('kind');
    }

    public function test_name_editable_but_code_immutable_on_update(): void
    {
        $action = WorkflowAction::query()->create(['code' => 'ESCALATE', 'name' => 'Escalate', 'kind' => 'CUSTOM'])->refresh();

        $this->actingAs($this->admin)->putJson("/api/v1/workflow-actions/{$action->id}", [
            'name' => 'Escalate to Director',
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Escalate to Director')
            ->assertJsonPath('data.version', 2);

        // Attempting to change code is rejected.
        $this->actingAs($this->admin)->putJson("/api/v1/workflow-actions/{$action->id}", [
            'code' => 'CHANGED',
            'name' => 'Escalate to Director',
            'version' => 2,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseHas('workflow_actions', ['id' => $action->id, 'code' => 'ESCALATE']);
    }

    public function test_duplicate_code_rejected(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/workflow-actions', [
            'code' => 'APPROVE',
            'name' => 'Dup',
            'kind' => 'APPROVE',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_activate_and_deactivate_a_custom_action(): void
    {
        $action = WorkflowAction::query()->create(['code' => 'ESCALATE', 'name' => 'Escalate', 'kind' => 'CUSTOM'])->refresh();

        $this->actingAs($this->admin)->postJson("/api/v1/workflow-actions/{$action->id}/deactivate", [
            'version' => 1,
        ])->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.version', 2);

        $this->actingAs($this->admin)->postJson("/api/v1/workflow-actions/{$action->id}/activate", [
            'version' => 2,
        ])->assertOk()->assertJsonPath('data.is_active', true)->assertJsonPath('data.version', 3);
    }

    public function test_system_action_delete_blocked(): void
    {
        $action = WorkflowAction::query()->where('code', 'APPROVE')->firstOrFail();

        $this->actingAs($this->admin)->deleteJson("/api/v1/workflow-actions/{$action->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_ACTION_PROTECTED');

        $this->assertDatabaseHas('workflow_actions', ['id' => $action->id]);
    }

    public function test_delete_unused_custom_action(): void
    {
        $action = WorkflowAction::query()->create(['code' => 'ESCALATE', 'name' => 'Escalate', 'kind' => 'CUSTOM'])->refresh();

        $this->actingAs($this->admin)->deleteJson("/api/v1/workflow-actions/{$action->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workflow_actions', ['id' => $action->id]);
    }

    public function test_update_rejects_stale_version(): void
    {
        $action = WorkflowAction::query()->create(['code' => 'ESCALATE', 'name' => 'Escalate', 'kind' => 'CUSTOM'])->refresh();

        $this->actingAs($this->admin)->putJson("/api/v1/workflow-actions/{$action->id}", [
            'name' => 'Changed',
            'version' => 999,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'STALE_RESOURCE');
    }

    public function test_mutations_are_audited(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/workflow-actions', [
            'code' => 'ESCALATE',
            'name' => 'Escalate',
            'kind' => 'CUSTOM',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'GOVERNANCE_CREATED',
            'subject_type' => WorkflowAction::class,
            'subject_id' => $response->json('data.id'),
        ]);
    }

    public function test_non_admin_forbidden(): void
    {
        $this->actingAs($this->nonAdmin)->getJson('/api/v1/workflow-actions')->assertForbidden();
        $this->actingAs($this->nonAdmin)->postJson('/api/v1/workflow-actions', [
            'code' => 'X', 'name' => 'X', 'kind' => 'CUSTOM',
        ])->assertForbidden();
    }
}
