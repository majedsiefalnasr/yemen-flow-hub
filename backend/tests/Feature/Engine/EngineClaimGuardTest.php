<?php

namespace Tests\Feature\Engine;

use App\Exceptions\EngineException;
use App\Services\Workflow\EngineClaimService;
use App\Services\Workflow\EngineTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class EngineClaimGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_on_claim_stage_blocked_without_claim(): void
    {
        ['request' => $request, 'transitionId' => $tid, 'executor' => $user]
            = EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('do not hold the claim');
        app(EngineTransitionService::class)->execute($request, $tid, null, [], $request->version, $user);
    }

    public function test_action_on_claim_stage_allowed_for_holder(): void
    {
        ['request' => $request, 'transitionId' => $tid, 'executor' => $user]
            = EngineWorkflowFactory::seedClaimStageWithTransition();

        app(EngineClaimService::class)->claim($request, $user);
        $result = app(EngineTransitionService::class)->execute($request->fresh(), $tid, null, [], $request->fresh()->version, $user);

        $this->assertNotNull($result);
    }
}
