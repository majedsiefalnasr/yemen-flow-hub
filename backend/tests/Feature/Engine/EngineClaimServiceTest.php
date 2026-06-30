<?php

namespace Tests\Feature\Engine;

use App\Exceptions\EngineException;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Workflow\EngineClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class EngineClaimServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(): EngineRequest
    {
        return EngineWorkflowFactory::seedRequestOnClaimStage();
    }

    public function test_claim_sets_holder_and_expiry(): void
    {
        $svc = app(EngineClaimService::class);
        $user = User::factory()->create();
        $request = $this->makeRequest();

        $claimed = $svc->claim($request, $user);

        $this->assertSame($user->id, $claimed->claimed_by);
        $this->assertNotNull($claimed->claimed_at);
        $this->assertTrue($claimed->claim_expires_at->isFuture());
    }

    public function test_claim_by_second_user_throws_stage_claimed(): void
    {
        $svc = app(EngineClaimService::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $request = $this->makeRequest();

        $svc->claim($request, $a);
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('already being reviewed');
        $svc->claim($request->fresh(), $b);
    }

    public function test_reclaim_by_same_user_is_idempotent(): void
    {
        $svc = app(EngineClaimService::class);
        $a = User::factory()->create();
        $request = $this->makeRequest();

        $svc->claim($request, $a);
        $again = $svc->claim($request->fresh(), $a);
        $this->assertSame($a->id, $again->claimed_by);
    }

    public function test_heartbeat_extends_expiry_for_holder(): void
    {
        $svc = app(EngineClaimService::class);
        $a = User::factory()->create();
        $request = $this->makeRequest();
        $svc->claim($request, $a);

        $before = $request->fresh()->claim_expires_at;
        $this->travel(2)->minutes();
        $after = $svc->heartbeat($request->fresh(), $a)->claim_expires_at;

        $this->assertTrue($after->greaterThan($before));
    }

    public function test_heartbeat_by_non_holder_throws_claim_not_held(): void
    {
        $svc = app(EngineClaimService::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $request = $this->makeRequest();
        $svc->claim($request, $a);

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('do not hold the claim');
        $svc->heartbeat($request->fresh(), $b);
    }

    public function test_release_clears_holder(): void
    {
        $svc = app(EngineClaimService::class);
        $a = User::factory()->create();
        $request = $this->makeRequest();
        $svc->claim($request, $a);

        $released = $svc->release($request->fresh(), $a);
        $this->assertNull($released->claimed_by);
        $this->assertNull($released->claim_expires_at);
    }
}
