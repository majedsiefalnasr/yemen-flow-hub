<?php

namespace Tests\Feature\Engine;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class EngineClaimEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_by_user_without_execute_returns_403(): void
    {
        ['request' => $request] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    public function test_claim_endpoint_returns_200_and_sets_holder(): void
    {
        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->actingAs($user)
            ->postJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.claimed_by', $user->id);
    }

    public function test_claim_endpoint_exposes_requires_claim_and_claim_holder(): void
    {
        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->actingAs($user)
            ->postJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.current_stage.requires_claim', true)
            ->assertJsonPath('data.claimed_by_user.id', $user->id)
            ->assertJsonPath('data.claimed_by_user.name', $user->name);
    }

    public function test_second_user_claim_returns_409(): void
    {
        ['request' => $request, 'executor' => $a] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $b = EngineWorkflowFactory::executorPeer($a, $request);

        $this->actingAs($a)->postJson("/api/v1/engine-requests/{$request->id}/claim")->assertOk();
        $this->actingAs($b)->postJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'STAGE_CLAIMED');
    }

    public function test_heartbeat_by_non_holder_returns_403(): void
    {
        ['request' => $request, 'executor' => $a] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $b = EngineWorkflowFactory::executorPeer($a, $request);
        $this->actingAs($a)->postJson("/api/v1/engine-requests/{$request->id}/claim")->assertOk();

        $this->actingAs($b)->postJson("/api/v1/engine-requests/{$request->id}/claim/heartbeat")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'CLAIM_NOT_HELD');
    }

    public function test_release_clears_holder(): void
    {
        ['request' => $request, 'executor' => $a] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $this->actingAs($a)->postJson("/api/v1/engine-requests/{$request->id}/claim")->assertOk();

        $this->actingAs($a)->deleteJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.claimed_by', null);
    }
}
