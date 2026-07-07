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

class WorkflowVersionDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    private WorkflowDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);
        $this->nonAdmin = User::query()->withoutUserRole(UserRole::CBY_ADMIN)->firstOrFail();
        $this->definition = WorkflowDefinition::query()->create(['code' => 'flow-del', 'name' => 'Flow Delete']);
    }

    public function test_delete_version_with_no_requests(): void
    {
        $version = $this->definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$version->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workflow_versions', ['id' => $version->id]);
    }

    public function test_delete_version_with_requests_is_rejected(): void
    {
        $version = $this->definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
        ]);
        $stage = $version->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        EngineRequest::query()->create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'REF-'.uniqid(),
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$version->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_VERSION_IN_USE');

        $this->assertDatabaseHas('workflow_versions', ['id' => $version->id]);
    }

    public function test_non_admin_cannot_delete_version(): void
    {
        $version = $this->definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ]);

        $this->actingAs($this->nonAdmin)
            ->deleteJson("/api/v1/workflow-versions/{$version->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('workflow_versions', ['id' => $version->id]);
    }

    public function test_delete_published_version_with_no_requests_is_allowed(): void
    {
        $version = $this->definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$version->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workflow_versions', ['id' => $version->id]);
    }
}
