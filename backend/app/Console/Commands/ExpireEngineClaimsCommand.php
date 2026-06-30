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
        $systemActor = User::query()->where('role', UserRole::CBY_ADMIN)->first();
        if (! $systemActor) {
            $this->error('No CBY_ADMIN user found — cannot expire engine claims.');

            return;
        }

        $expiredIds = EngineRequest::query()
            ->whereNotNull('claim_expires_at')
            ->where('claim_expires_at', '<', now())
            ->pluck('id');

        $count = 0;
        foreach ($expiredIds as $id) {
            try {
                $request = EngineRequest::find($id);
                if ($request === null || ! $request->claimIsExpired()) {
                    continue;
                }
                $priorHolderId = $request->claimed_by;
                $released = $claimService->release($request, $systemActor);
                $dispatcher->custom(
                    'claim.released',
                    'info',
                    'انتهت مهلة المطالبة',
                    "تم تحرير المطالبة على الطلب {$released->reference} لانتهاء المهلة.",
                    'engine_request',
                    $released->id,
                    null,
                    $priorHolderId !== null ? [$priorHolderId] : [],
                );
                $count++;
            } catch (\Throwable $e) {
                $this->error("Failed to expire engine claim for request {$id}: {$e->getMessage()}");
            }
        }

        $this->info("Released {$count} expired engine claim(s).");
    }
}
