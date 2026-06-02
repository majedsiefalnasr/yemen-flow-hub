<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ImportRequest;
use App\Models\User;
use App\Services\Notifications\ClaimReleaseNotifier;
use App\Services\Workflow\WorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireClaimsCommand extends Command
{
    protected $signature = 'workflow:expire-claims';
    protected $description = 'Release support and bank review claims whose TTL has expired, and unblock requests stuck without a TTL';

    public function handle(WorkflowService $workflowService, ClaimReleaseNotifier $claimReleaseNotifier): void
    {
        $systemActor = User::query()->where('role', UserRole::CBY_ADMIN)->first();

        if (!$systemActor) {
            $this->error('No CBY_ADMIN user found — cannot expire claims. Seed a system CBY_ADMIN user.');
            return;
        }

        // Expire support committee claims whose TTL has passed
        $this->expireByStatus(
            RequestStatus::SUPPORT_REVIEW_IN_PROGRESS,
            'support_release',
            $systemActor,
            $workflowService,
            $claimReleaseNotifier
        );

        // Expire bank reviewer claims whose TTL has passed (transitions BANK_REVIEW → SUBMITTED)
        $this->expireByStatus(
            RequestStatus::BANK_REVIEW,
            'bank_claim_release',
            $systemActor,
            $workflowService,
            $claimReleaseNotifier
        );
    }

    private function expireByStatus(
        RequestStatus $status,
        string $releaseAction,
        User $systemActor,
        WorkflowService $workflowService,
        ClaimReleaseNotifier $claimReleaseNotifier
    ): void {
        $expiredIds = ImportRequest::query()
            ->where('status', $status)
            ->whereNotNull('claim_expires_at')
            ->where('claim_expires_at', '<', now())
            ->pluck('id');

        foreach ($expiredIds as $id) {
            try {
                DB::transaction(function () use ($id, $status, $releaseAction, $systemActor, $workflowService, $claimReleaseNotifier): void {
                    $request = ImportRequest::query()->lockForUpdate()->find($id);
                    if (!$request || $request->status !== $status) {
                        return;
                    }
                    if ($request->claim_expires_at === null || $request->claim_expires_at->isFuture()) {
                        return;
                    }
                    $updated = $workflowService->transition(
                        $request,
                        $releaseAction,
                        $systemActor,
                        null,
                        ['auto_finalized' => true, 'auto_expire' => true]
                    );
                    $claimReleaseNotifier->dispatch($updated, 'ttl_expired');
                });
            } catch (\Throwable $e) {
                $this->error("Failed to expire claim for request {$id} ({$releaseAction}): {$e->getMessage()}");
            }
        }
    }
}
