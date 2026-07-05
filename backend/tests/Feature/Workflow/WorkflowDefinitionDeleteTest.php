<?php

namespace Tests\Feature\Workflow;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowDefinitionDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->nonAdmin = User::query()->where('role', '!=', UserRole::CBY_ADMIN->value)->firstOrFail();
    }

    public function test_delete_definition_with_no_requests_across_any_version(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'def-del', 'name' => 'Definition Delete']);
        $v1 = $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::ARCHIVED]);
        $v1->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        $definition->versions()->create(['version_number' => 2, 'state' => WorkflowVersionState::DRAFT]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-definitions/{$definition->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workflow_definitions', ['id' => $definition->id]);
        $this->assertDatabaseMissing('workflow_versions', ['workflow_definition_id' => $definition->id]);
        $this->assertDatabaseMissing('workflow_stages', ['code' => 'intake']);
    }

    public function test_delete_definition_with_a_request_on_any_version_is_rejected(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'def-del-2', 'name' => 'Definition Delete 2']);
        $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::ARCHIVED]);
        $v2 = $definition->versions()->create(['version_number' => 2, 'state' => WorkflowVersionState::PUBLISHED]);
        $stage = $v2->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        EngineRequest::query()->create([
            'workflow_version_id' => $v2->id,
            'current_stage_id' => $stage->id,
            'reference' => 'REF-'.uniqid(),
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-definitions/{$definition->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_DEFINITION_IN_USE');

        $this->assertDatabaseHas('workflow_definitions', ['id' => $definition->id]);
    }

    public function test_non_admin_cannot_delete_definition(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'def-del-3', 'name' => 'Definition Delete 3']);

        $this->actingAs($this->nonAdmin)
            ->deleteJson("/api/v1/workflow-definitions/{$definition->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('workflow_definitions', ['id' => $definition->id]);
    }
}
