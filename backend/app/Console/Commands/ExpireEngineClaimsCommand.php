<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Services\Workflow\EngineClaimService;
use Illuminate\Console\Command;

class ExpireEngineClaimsCommand extends Command
{
    protected $signature = 'workflow:expire-engine-claims';

    protected $description = 'Release engine request claims whose TTL has expired';

    public function handle(EngineClaimService $claimService, EngineNotificationDispatcher $dispatcher): void
    {
        $expiredIds = EngineRequest::query()
            ->whereNotNull('claim_expires_at')
            ->where('claim_expires_at', '<', now())
            ->pluck('id');

        foreach ($expiredIds as $id) {
            try {
                $request = EngineRequest::findOrFail($id);
                $referenceNumber = $request->reference;
                $claimService->releaseExpired($request);

                $dispatcher->custom(
                    type: 'claim.released',
                    severity: 'info',
                    title: "أُلغيت مطالبة بسبب انتهاء المهلة: {$referenceNumber}",
                    body: null,
                    entityType: 'engine_request',
                    entityId: $id,
                    actionUrl: "/requests/{$id}",
                    recipientUserIds: $this->resolveCbyAdminIds(),
                );
            } catch (\Throwable $e) {
                $this->error("Failed to expire engine claim for request {$id}: {$e->getMessage()}");
            }
        }
    }

    /** @return int[] */
    private function resolveCbyAdminIds(): array
    {
        return User::query()
            ->where('role', UserRole::CBY_ADMIN->value)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();
    }
}
