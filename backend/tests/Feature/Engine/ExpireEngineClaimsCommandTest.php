<?php

namespace Tests\Feature\Engine;

use App\Enums\UserRole;
use App\Jobs\DispatchNotification;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Workflow\EngineClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class ExpireEngineClaimsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_claim_is_released_by_command(): void
    {
        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();
        app(EngineClaimService::class)->claim($request, $user);

        // force expiry in the past
        $request->fresh()->forceFill(['claim_expires_at' => now()->subMinute()])->save();

        $this->artisan('workflow:expire-engine-claims')->assertSuccessful();

        $fresh = $request->fresh();
        $this->assertNull($fresh->claimed_by);
        $this->assertNull($fresh->claimed_at);
        $this->assertNull($fresh->claim_expires_at);

        $auditLog = AuditLog::query()
            ->where('action', 'CLAIM_RELEASED')
            ->where('subject_id', $request->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('ttl_expired', $auditLog->metadata['reason'] ?? null);
    }

    public function test_unexpired_claim_is_not_released(): void
    {
        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();
        app(EngineClaimService::class)->claim($request, $user);

        $this->artisan('workflow:expire-engine-claims')->assertSuccessful();

        $fresh = $request->fresh();
        $this->assertSame($user->id, $fresh->claimed_by);
        $this->assertNotNull($fresh->claim_expires_at);
    }

    public function test_no_claimed_requests_is_a_noop(): void
    {
        EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->artisan('workflow:expire-engine-claims')->assertSuccessful();

        $this->assertSame(0, AuditLog::query()->where('action', 'CLAIM_RELEASED')->count());
    }

    public function test_dispatches_notification_to_active_cby_admins_on_expiry(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'role' => UserRole::CBY_ADMIN,
            'is_active' => true,
        ]);

        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();
        app(EngineClaimService::class)->claim($request, $user);
        $request->fresh()->forceFill(['claim_expires_at' => now()->subMinute()])->save();

        $this->artisan('workflow:expire-engine-claims')->assertSuccessful();

        Queue::assertPushed(DispatchNotification::class, function (DispatchNotification $job) use ($admin, $request) {
            // DispatchNotification has private readonly properties; use reflection to
            // assert recipients/entity without depending on a public accessor.
            $ref = new \ReflectionObject($job);
            $recipientIds = $ref->getProperty('recipientUserIds');
            $recipientIds->setAccessible(true);
            $entityId = $ref->getProperty('entityId');
            $entityId->setAccessible(true);
            $type = $ref->getProperty('type');
            $type->setAccessible(true);

            return in_array($admin->id, $recipientIds->getValue($job), true)
                && $entityId->getValue($job) === $request->id
                && $type->getValue($job) === 'claim.released';
        });
    }
}
