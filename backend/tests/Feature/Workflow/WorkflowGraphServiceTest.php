<?php

namespace Tests\Feature\Workflow;

use App\Enums\WorkflowVersionState;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowTransition;
use App\Services\Workflow\WorkflowGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowGraphServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_graph_edges_include_confirmation_and_destructive_flags(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'flow', 'name' => 'Flow']);
        $version = $definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ])->refresh();

        $from = $version->stages()->create(['code' => 'a', 'name' => 'A', 'is_initial' => true]);
        $to = $version->stages()->create(['code' => 'b', 'name' => 'B', 'is_final' => true]);
        $action = WorkflowAction::query()->create(['code' => 'REJECT', 'name' => 'Reject', 'kind' => 'REJECT']);

        $transition = WorkflowTransition::query()->create([
            'workflow_version_id' => $version->id,
            'from_stage_id' => $from->id,
            'action_id' => $action->id,
            'to_stage_id' => $to->id,
            'confirmation_message' => 'هل أنت متأكد من الرفض؟',
            'is_destructive' => true,
            'is_default_submit' => false,
        ]);

        $graph = app(WorkflowGraphService::class)->build($version->refresh());
        $edge = collect($graph['edges'])->firstWhere('id', $transition->id);

        $this->assertSame('هل أنت متأكد من الرفض؟', $edge['confirmation_message']);
        $this->assertTrue($edge['is_destructive']);
        $this->assertFalse($edge['is_default_submit']);
    }
}
