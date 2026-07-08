<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

/**
 * Voting on engine requests is driven by the ExecuteAction endpoint.
 * We set up a voting stage, grant EXECUTIVE_MEMBER/COMMITTEE_DIRECTOR
 * EXECUTE, then drive transitions through the stage.
 */
class EngineVotingTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    private User $executive;

    private User $director;

    private User $nonVoter;

    private WorkflowVersion $version;

    private WorkflowStage $votingStage;

    private WorkflowTransition $approveTransition;

    private WorkflowTransition $rejectTransition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();

        $bank = Bank::create(['name' => 'Vote Bank', 'code' => 'VBK', 'is_active' => true]);

        $this->executive = User::create([
            'name' => 'Executive',
            'email' => 'exec@vote.test',
            'password' => bcrypt('pass'),
            'bank_id' => null,
            'is_active' => true,
        ]);
        $this->executive = $this->assignGovernanceIdentity($this->executive, UserRole::EXECUTIVE_MEMBER);

        $this->director = User::create([
            'name' => 'Director',
            'email' => 'dir@vote.test',
            'password' => bcrypt('pass'),
            'bank_id' => null,
            'is_active' => true,
        ]);
        $this->director = $this->assignGovernanceIdentity($this->director, UserRole::COMMITTEE_DIRECTOR);

        $this->nonVoter = User::create([
            'name' => 'Non Voter',
            'email' => 'nonvoter@vote.test',
            'password' => bcrypt('pass'),
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);
        $this->nonVoter = $this->assignGovernanceIdentity($this->nonVoter, UserRole::DATA_ENTRY);

        $def = WorkflowDefinition::create(['code' => 'VOTE_WF', 'name' => 'Vote WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        $this->votingStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'VOTING',
            'name' => 'Voting',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        $approvedStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'APPROVED',
            'name' => 'Approved',
            'sort_order' => 2,
            'is_initial' => false,
            'is_final' => true,
            'version' => 1,
        ]);

        $rejectedStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'REJECTED_STAGE',
            'name' => 'Rejected',
            'sort_order' => 3,
            'is_initial' => false,
            'is_final' => true,
            'version' => 1,
        ]);

        // Grant EXECUTE on voting stage to executive and director (user-scoped)
        StagePermission::create([
            'stage_id' => $this->votingStage->id,
            'user_id' => $this->executive->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Vote',
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $this->votingStage->id,
            'user_id' => $this->director->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Vote Dir',
            'version' => 1,
        ]);

        $approveAction = WorkflowAction::create([
            'code' => 'VOTE_APPROVE',
            'name' => 'Approve Vote',
            'kind' => 'APPROVE',
            'is_active' => true,
            'version' => 1,
        ]);

        $rejectAction = WorkflowAction::create([
            'code' => 'VOTE_REJECT',
            'name' => 'Reject Vote',
            'kind' => 'REJECT',
            'is_active' => true,
            'version' => 1,
        ]);

        $this->approveTransition = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->votingStage->id,
            'to_stage_id' => $approvedStage->id,
            'action_id' => $approveAction->id,
            'requires_comment' => false,
            'version' => 1,
        ]);

        $this->rejectTransition = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->votingStage->id,
            'to_stage_id' => $rejectedStage->id,
            'action_id' => $rejectAction->id,
            'requires_comment' => false,
            'version' => 1,
        ]);
    }

    private function makeVotingRequest(): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->votingStage->id,
            'reference' => 'ENG-VOTE-'.uniqid(),
            'status' => 'ACTIVE',
            'created_by' => $this->executive->id,
            'data' => [],
            'version' => 1,
        ]);
    }

    public function test_executive_member_can_execute_approve_transition(): void
    {
        $request = $this->makeVotingRequest();

        $response = $this->actingAs($this->executive)->postJson(
            "/api/v1/engine-requests/{$request->id}/actions",
            [
                'transition_id' => $this->approveTransition->id,
                'data' => [],
                'version' => $request->version,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.current_stage.code', 'APPROVED');

        $request->refresh();
        $this->assertEquals('CLOSED', $request->status);
    }

    public function test_director_can_execute_reject_transition(): void
    {
        $request = $this->makeVotingRequest();

        $response = $this->actingAs($this->director)->postJson(
            "/api/v1/engine-requests/{$request->id}/actions",
            [
                'transition_id' => $this->rejectTransition->id,
                'data' => [],
                'version' => $request->version,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.current_stage.code', 'REJECTED_STAGE');
    }

    public function test_non_executive_cannot_execute_voting_transition(): void
    {
        $request = $this->makeVotingRequest();

        $response = $this->actingAs($this->nonVoter)->postJson(
            "/api/v1/engine-requests/{$request->id}/actions",
            [
                'transition_id' => $this->approveTransition->id,
                'data' => [],
                'version' => $request->version,
            ]
        );

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    public function test_closed_request_blocks_further_voting_transitions(): void
    {
        $request = $this->makeVotingRequest();

        // First transition closes it
        $this->actingAs($this->director)->postJson(
            "/api/v1/engine-requests/{$request->id}/actions",
            ['transition_id' => $this->approveTransition->id, 'data' => [], 'version' => $request->version]
        )->assertOk();

        $request->refresh();

        // Second attempt on closed request must be forbidden
        $this->actingAs($this->director)->postJson(
            "/api/v1/engine-requests/{$request->id}/actions",
            ['transition_id' => $this->approveTransition->id, 'data' => [], 'version' => $request->version]
        )->assertStatus(403);
    }

    public function test_stale_version_is_rejected_on_voting_transition(): void
    {
        $request = $this->makeVotingRequest();

        $this->actingAs($this->executive)->postJson(
            "/api/v1/engine-requests/{$request->id}/actions",
            ['transition_id' => $this->approveTransition->id, 'data' => [], 'version' => 9999]
        )->assertStatus(409)
            ->assertJsonPath('error_code', 'REQUEST_STALE');
    }
}
