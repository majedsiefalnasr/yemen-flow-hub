<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Services\Operations\OperationalAlertLogger;
use App\Services\Workflow\EngineClaimService;
use App\Support\RoleCodes;
use Illuminate\Console\Command;

class ExpireEngineClaimsCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'workflow:expire-engine-claims';

    protected $description = 'Release engine request claims whose TTL has expired';

    public function handle(EngineClaimService $claimService, EngineNotificationDispatcher $dispatcher): int
    {
        return $this->runWithHeartbeat(function () use ($claimService, $dispatcher): array {
            $expiredIds = EngineRequest::query()
                ->whereNotNull('claim_expires_at')
                ->where('claim_expires_at', '<', now())
                ->pluck('id');

            $released = 0;
            $failedCount = 0;

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

                    $released++;
                } catch (\Throwable $e) {
                    $failedCount++;
                    OperationalAlertLogger::failure('claim_sweep', $e, ['request_id' => $id]);
                    $this->error("Failed to expire engine claim for request {$id}: {$e->getMessage()}");
                }
            }

            return [
                'affected' => $released,
                'meta' => $failedCount > 0 ? ['failed_count' => $failedCount] : [],
            ];
        });
    }

    /** @return int[] */
    private function resolveCbyAdminIds(): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('code', RoleCodes::SYSTEM_ADMIN))
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();
    }
}
