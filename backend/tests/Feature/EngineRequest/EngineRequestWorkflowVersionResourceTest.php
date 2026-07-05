<?php

namespace Tests\Feature\EngineRequest;

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

class EngineRequestWorkflowVersionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_includes_workflow_version_and_definition_name(): void
    {
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'wv-flow', 'name' => 'تمويل الواردات']);
        $version = $definition->versions()->create(['version_number' => 3, 'state' => WorkflowVersionState::PUBLISHED]);
        $stage = $version->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        $engineRequest = EngineRequest::query()->create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'REF-'.uniqid(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/engine-requests/{$engineRequest->id}")
            ->assertOk();

        $response->assertJsonPath('data.workflow_version.version_number', 3)
            ->assertJsonPath('data.workflow_version.definition.name', 'تمويل الواردات');
    }

    public function test_index_includes_workflow_version_for_each_row(): void
    {
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'wv-flow-2', 'name' => 'Flow Two']);
        $version = $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::PUBLISHED]);
        $stage = $version->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        EngineRequest::query()->create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'REF-'.uniqid(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/engine-requests')->assertOk();

        $row = collect($response->json('data'))->first();
        $this->assertSame('Flow Two', $row['workflow_version']['definition']['name']);
    }
}
