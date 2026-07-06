<?php

namespace Tests\Feature\Workflow;

use App\Enums\UserRole;
use App\Exceptions\EngineException;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Workflow\EngineClaimService;
use App\Services\Workflow\EngineTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class EngineClaimLifecycleTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    public function test_claim_lifecycle_is_characterized(): void
    {
        $service = app(EngineClaimService::class);
        ['request' => $request, 'executor' => $holder] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $peer = EngineWorkflowFactory::executorPeer($holder, $request);

        $claimed = $service->claim($request, $holder);
        $this->assertSame($holder->id, $claimed->claimed_by);
        $this->assertNotNull($claimed->claimed_at);
        $this->assertTrue($claimed->claim_expires_at->isFuture());

        try {
            $service->claim($request->fresh(), $peer);
            $this->fail('Second claimant should be rejected while claim is unexpired.');
        } catch (EngineException $exception) {
            $this->assertSame('STAGE_CLAIMED', $exception->render()->getData(true)['error_code']);
            $this->assertSame(409, $exception->render()->getStatusCode());
        }

        $originalClaimedAt = $request->fresh()->claimed_at;
        $this->travel(2)->minutes();
        $reclaimed = $service->claim($request->fresh(), $holder);
        $this->assertTrue($reclaimed->claim_expires_at->greaterThan($claimed->claim_expires_at));
        $this->assertTrue($reclaimed->claimed_at->equalTo($originalClaimedAt));

        $request->fresh()->forceFill(['claim_expires_at' => now()->subMinute()])->save();
        $expiredClaimed = $service->claim($request->fresh(), $peer);
        $this->assertSame($peer->id, $expiredClaimed->claimed_by);

        $beforeHeartbeat = $expiredClaimed->claim_expires_at;
        $this->travel(1)->minute();
        $afterHeartbeat = $service->heartbeat($request->fresh(), $peer);
        $this->assertTrue($afterHeartbeat->claim_expires_at->greaterThan($beforeHeartbeat));

        try {
            $service->heartbeat($request->fresh(), $holder);
            $this->fail('Non-holder heartbeat should be rejected.');
        } catch (EngineException $exception) {
            $this->assertSame('CLAIM_NOT_HELD', $exception->render()->getData(true)['error_code']);
            $this->assertSame(403, $exception->render()->getStatusCode());
        }

        $released = $service->release($request->fresh(), $peer);
        $this->assertNull($released->claimed_by);
        $this->assertNull($released->claimed_at);
        $this->assertNull($released->claim_expires_at);
    }

    public function test_admin_release_and_expiry_sweeper_are_characterized(): void
    {
        $this->seedGovernance();
        $service = app(EngineClaimService::class);
        ['request' => $request, 'executor' => $holder] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $admin = $this->assignGovernanceIdentity(User::factory()->create([
            'role' => UserRole::CBY_ADMIN,
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);

        $service->claim($request, $holder);
        $service->release($request->fresh(), $admin);
        $this->assertNull($request->fresh()->claimed_by);

        $service->claim($request->fresh(), $holder);
        $request->fresh()->forceFill(['claim_expires_at' => now()->subMinute()])->save();
        $service->releaseExpired($request->fresh());

        $this->assertNull($request->fresh()->claimed_by);
        $this->assertSame(
            'ttl_expired',
            AuditLog::query()->where('action', 'CLAIM_RELEASED')->latest('id')->first()?->metadata['reason'] ?? null,
        );

        $service->claim($request->fresh(), $holder);
        $service->releaseExpired($request->fresh());
        $this->assertSame($holder->id, $request->fresh()->claimed_by);
    }

    public function test_requires_claim_stage_transition_and_draft_save_current_behavior(): void
    {
        ['request' => $request, 'transitionId' => $transitionId, 'executor' => $holder]
            = EngineWorkflowFactory::seedClaimStageWithTransition();
        $peer = EngineWorkflowFactory::executorPeer($holder, $request);

        try {
            app(EngineTransitionService::class)->execute($request, $transitionId, null, [], $request->version, $holder);
            $this->fail('Transition without claim should be rejected.');
        } catch (EngineException $exception) {
            $this->assertSame('CLAIM_NOT_HELD', $exception->render()->getData(true)['error_code']);
        }

        app(EngineClaimService::class)->claim($request->fresh(), $holder);

        // @see WP-5 D9-N2 currently, draft save on a requires_claim stage by a non-holder succeeds.
        $draftSaved = app(EngineTransitionService::class)->saveDraft($request->fresh(), ['note' => 'peer edit'], $request->fresh()->version, $peer);
        $this->assertSame('peer edit', $draftSaved->data['note']);

        $transitioned = app(EngineTransitionService::class)->execute($request->fresh(), $transitionId, null, [], $request->fresh()->version, $holder);
        $this->assertNotNull($transitioned);

        // @see WP-5 D9-N1 currently, successful transitions leave claim fields in place.
        $this->assertSame($holder->id, $request->fresh()->claimed_by);
    }
}
