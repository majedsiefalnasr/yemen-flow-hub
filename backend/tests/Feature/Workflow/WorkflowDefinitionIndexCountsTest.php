<?php

namespace Tests\Feature\Workflow;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowDefinitionIndexCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_exposes_stage_transition_and_field_counts_per_version(): void
    {
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'counts', 'name' => 'Counts Flow']);
        $version = $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::DRAFT]);
        $stageA = $version->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        $stageB = $version->stages()->create(['code' => 'review', 'name' => 'Review']);
        $action = WorkflowAction::query()->create(['code' => 'SUBMIT_'.uniqid(), 'name' => 'Submit', 'kind' => 'APPROVE']);
        $version->transitions()->create([
            'from_stage_id' => $stageA->id,
            'action_id' => $action->id,
            'to_stage_id' => $stageB->id,
        ]);
        $group = $version->fieldGroups()->create(['name' => 'basics', 'label' => 'Basics', 'sort_order' => 1]);
        $group->fields()->create([
            'workflow_version_id' => $version->id,
            'key' => 'amount',
            'label' => 'Amount',
            'type' => 'NUMBER',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/workflow-definitions')->assertOk();

        $versionPayload = collect($response->json('data'))
            ->firstWhere('id', $definition->id)['versions'][0];

        $this->assertSame(2, $versionPayload['stages_count']);
        $this->assertSame(1, $versionPayload['transitions_count']);
        $this->assertSame(1, $versionPayload['fields_count']);
    }
}
