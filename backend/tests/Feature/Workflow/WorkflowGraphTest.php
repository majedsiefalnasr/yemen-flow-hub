<?php

namespace Tests\Feature\Workflow;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Organization;
use App\Models\StagePermission;
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

class WorkflowGraphTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    private WorkflowVersion $version;

    private WorkflowStage $intake;

    private WorkflowStage $review;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->nonAdmin = User::query()->where('role', '!=', UserRole::CBY_ADMIN->value)->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'flow', 'name' => 'Flow']);
        $this->version = $definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ])->refresh();
        $this->intake = $this->version->stages()->create(['code' => 'intake', 'name' => 'Intake', 'is_initial' => true, 'sort_order' => 0]);
        $this->review = $this->version->stages()->create(['code' => 'review', 'name' => 'Review', 'is_final' => true, 'sort_order' => 1]);

        $approve = WorkflowAction::query()->create(['code' => 'APPROVE', 'name' => 'Approve', 'kind' => 'APPROVE']);
        $return = WorkflowAction::query()->create(['code' => 'RETURN', 'name' => 'Return', 'kind' => 'RETURN']);

        // Forward edge intake → review.
        WorkflowTransition::query()->create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->intake->id,
            'action_id' => $approve->id,
            'to_stage_id' => $this->review->id,
        ]);
        // Return edge review → intake (to an earlier sort_order).
        WorkflowTransition::query()->create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->review->id,
            'action_id' => $return->id,
            'to_stage_id' => $this->intake->id,
            'requires_comment' => true,
        ]);
    }

    public function test_graph_returns_nodes_and_edges_from_config(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/workflow-versions/{$this->version->id}/graph")
            ->assertOk()
            ->assertJsonCount(2, 'data.nodes')
            ->assertJsonCount(2, 'data.edges');

        $nodes = collect($response->json('data.nodes'));
        $this->assertTrue($nodes->firstWhere('code', 'intake')['is_initial']);
        $this->assertTrue($nodes->firstWhere('code', 'review')['is_final']);
    }

    public function test_return_edge_is_flagged(): void
    {
        $edges = collect(
            $this->actingAs($this->admin)
                ->getJson("/api/v1/workflow-versions/{$this->version->id}/graph")
                ->json('data.edges'),
        );

        $forward = $edges->firstWhere('action_code', 'APPROVE');
        $return = $edges->firstWhere('action_code', 'RETURN');

        $this->assertFalse($forward['is_return']);
        $this->assertTrue($return['is_return']);
        $this->assertTrue($return['requires_comment']);
    }

    public function test_node_display_label_comes_from_stage_permissions(): void
    {
        StagePermission::query()->create([
            'stage_id' => $this->intake->id,
            'organization_id' => Organization::query()->value('id'),
            'access_level' => 'VIEW',
            'display_label' => 'موظفو الإدخال',
        ]);

        $nodes = collect(
            $this->actingAs($this->admin)
                ->getJson("/api/v1/workflow-versions/{$this->version->id}/graph")
                ->json('data.nodes'),
        );

        $this->assertSame('موظفو الإدخال', $nodes->firstWhere('code', 'intake')['display_label']);
    }

    public function test_non_admin_cannot_view_graph(): void
    {
        $this->actingAs($this->nonAdmin)
            ->getJson("/api/v1/workflow-versions/{$this->version->id}/graph")
            ->assertForbidden();
    }
}
