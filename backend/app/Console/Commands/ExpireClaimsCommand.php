<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ImportRequest;
use App\Models\User;
use App\Notifications\ClaimReleasedNotification;
use App\Services\Audit\AuditService;
use App\Services\Workflow\WorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireClaimsCommand extends Command
{
    protected $signature = 'workflow:expire-claims';
    protected $description = 'Release support review claims whose TTL has expired';

    public function handle(WorkflowService $workflowService, AuditService $auditService): void
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

        $cbyadmins = User::query()->where('role', UserRole::CBY_ADMIN->value)->where('is_active', true)->get();

        foreach ($expiredIds as $id) {
            try {
                // Re-fetch with lockForUpdate inside a transaction so a concurrent heartbeat
                // cannot extend the TTL between our initial scan and the actual release.
                DB::transaction(function () use ($id, $systemActor, $workflowService, $auditService, $cbyadmins): void {
                    $request = ImportRequest::query()->lockForUpdate()->find($id);
                    if (!$request || $request->status !== RequestStatus::SUPPORT_REVIEW_IN_PROGRESS) {
                        return;
                    }
                    if ($request->claim_expires_at === null || $request->claim_expires_at->isFuture()) {
                        return;
                    }
                    $updated = $workflowService->transition(
                        $request,
                        'support_release',
                        $systemActor,
                        null,
                        ['auto_finalized' => true, 'auto_expire' => true]
                    );

                    $notification = new ClaimReleasedNotification($updated, 'ttl_expired');
                    $cbyadmins->each(function (User $u) use ($notification): void {
                        $prefs = $u->user_preferences['notification_preferences'] ?? [];
                        if (($prefs['claim_released'] ?? true) !== false) {
                            $u->notify($notification);
                        }
                    });

                    // user_id: NULL signals a system/cron event (no human actor)
                    $auditService->log(AuditAction::CLAIM_RELEASED, null, $updated, [
                        'reason' => 'ttl_expired',
                        'request_id' => $updated->id,
                        'reference_number' => $updated->reference_number,
                    ]);
                });
            } catch (\Throwable $e) {
                $this->error("Failed to expire claim for request {$id}: {$e->getMessage()}");
            }
        }
    }
}
