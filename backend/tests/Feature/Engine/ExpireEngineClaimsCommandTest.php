<?php

namespace Tests\Feature\Engine;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Workflow\EngineClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class ExpireEngineClaimsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_claim_is_released_by_command(): void
    {
        User::factory()->create(['role' => UserRole::CBY_ADMIN]);

        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();
        app(EngineClaimService::class)->claim($request, $user);

        // force expiry in the past
        $request->fresh()->forceFill(['claim_expires_at' => now()->subMinute()])->save();

        $this->artisan('workflow:expire-engine-claims')->assertSuccessful();

        $this->assertNull($request->fresh()->claimed_by);
    }

    public function test_unexpired_claim_is_not_released(): void
    {
        User::factory()->create(['role' => UserRole::CBY_ADMIN]);

        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();
        app(EngineClaimService::class)->claim($request, $user);

        $this->artisan('workflow:expire-engine-claims')->assertSuccessful();

        $this->assertSame($user->id, $request->fresh()->claimed_by);
    }
}
