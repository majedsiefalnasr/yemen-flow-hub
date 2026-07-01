<?php

namespace Tests\Feature\Engine;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class EngineSupportClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_member_can_claim_active_request(): void
    {
        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->actingAs($user)
            ->postJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.claimed_by', $user->id);

        $request->refresh();
        $this->assertEquals($user->id, $request->claimed_by);
        $this->assertNotNull($request->claim_expires_at);
    }

    public function test_second_claimant_gets_409(): void
    {
        ['request' => $request, 'executor' => $a] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $b = EngineWorkflowFactory::executorPeer($a, $request);

        $this->actingAs($a)->postJson("/api/v1/engine-requests/{$request->id}/claim")->assertOk();

        $this->actingAs($b)->postJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'STAGE_CLAIMED');
    }

    public function test_heartbeat_extends_ttl(): void
    {
        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->actingAs($user)->postJson("/api/v1/engine-requests/{$request->id}/claim")->assertOk();
        $beforeExpiry = $request->fresh()->claim_expires_at;

        // Advance time then heartbeat
        $this->travel(5)->minutes();

        $this->actingAs($user)->postJson("/api/v1/engine-requests/{$request->id}/claim/heartbeat")->assertOk();

        $afterExpiry = $request->fresh()->claim_expires_at;
        $this->assertTrue($afterExpiry > $beforeExpiry, 'Heartbeat should extend claim_expires_at');
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

    public function test_release_clears_claim_holder(): void
    {
        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->actingAs($user)->postJson("/api/v1/engine-requests/{$request->id}/claim")->assertOk();

        $this->actingAs($user)->deleteJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.claimed_by', null);

        $request->refresh();
        $this->assertNull($request->claimed_by);
    }

    public function test_user_without_execute_permission_cannot_claim(): void
    {
        ['request' => $request] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    public function test_claim_sets_expires_at_in_the_future(): void
    {
        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->actingAs($user)->postJson("/api/v1/engine-requests/{$request->id}/claim")->assertOk();

        $request->refresh();
        $this->assertTrue($request->claim_expires_at->isFuture(), 'claim_expires_at must be in the future');
        $this->assertTrue($request->isClaimed());
    }
}
