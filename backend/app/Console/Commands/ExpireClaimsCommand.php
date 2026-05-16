<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ImportRequest;
use App\Models\User;
use App\Services\Workflow\WorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireClaimsCommand extends Command
{
    protected $signature = 'workflow:expire-claims';
    protected $description = 'Release support review claims whose TTL has expired';

    public function handle(WorkflowService $workflowService): void
    {
        $expiredIds = ImportRequest::query()
            ->where('status', RequestStatus::SUPPORT_REVIEW_IN_PROGRESS)
            ->where('claim_expires_at', '<', now())
            ->pluck('id');

        if ($expiredIds->isEmpty()) {
            return;
        }

        $systemActor = User::query()
            ->where('role', UserRole::CBY_ADMIN)
            ->first();

        if (!$systemActor) {
            $this->error('No CBY_ADMIN user found — cannot expire claims. Seed a system CBY_ADMIN user.');
            return;
        }

        foreach ($expiredIds as $id) {
            try {
                // Re-fetch with lockForUpdate inside a transaction so a concurrent heartbeat
                // cannot extend the TTL between our initial scan and the actual release.
                DB::transaction(function () use ($id, $systemActor, $workflowService): void {
                    $request = ImportRequest::query()->lockForUpdate()->find($id);
                    if (!$request || $request->status !== RequestStatus::SUPPORT_REVIEW_IN_PROGRESS) {
                        return;
                    }
                    if ($request->claim_expires_at === null || $request->claim_expires_at->isFuture()) {
                        return;
                    }
                    $workflowService->transition(
                        $request,
                        'support_release',
                        $systemActor,
                        null,
                        ['auto_finalized' => true, 'auto_expire' => true]
                    );
                });
            } catch (\Throwable $e) {
                $this->error("Failed to expire claim for request {$id}: {$e->getMessage()}");
            }
        }
    }
}
