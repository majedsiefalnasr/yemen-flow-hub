<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ImportRequest;
use App\Models\User;
use App\Services\Workflow\WorkflowService;
use Illuminate\Console\Command;

class ExpireClaimsCommand extends Command
{
    protected $signature = 'workflow:expire-claims';
    protected $description = 'Release support review claims whose TTL has expired';

    public function handle(WorkflowService $workflowService): void
    {
        $expired = ImportRequest::query()
            ->where('status', RequestStatus::SUPPORT_REVIEW_IN_PROGRESS)
            ->where('claim_expires_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            return;
        }

        $systemActor = User::query()
            ->where('role', UserRole::CBY_ADMIN)
            ->first();

        if (!$systemActor) {
            // Fallback: build a transient actor model for auditing
            $systemActor = new User();
            $systemActor->id = 0;
            $systemActor->name = 'System (auto-expire)';
            $systemActor->role = UserRole::CBY_ADMIN;
            $systemActor->bank_id = null;
            $systemActor->is_active = true;
        }

        foreach ($expired as $request) {
            try {
                $workflowService->transition(
                    $request,
                    'support_release',
                    $systemActor,
                    null,
                    ['auto_finalized' => true, 'auto_expire' => true]
                );
            } catch (\Throwable $e) {
                $this->error("Failed to expire claim for request {$request->id}: {$e->getMessage()}");
            }
        }
    }
}
