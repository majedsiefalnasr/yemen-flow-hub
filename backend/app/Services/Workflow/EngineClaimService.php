<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Exceptions\EngineException;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EngineClaimService
{
    public function __construct(private AuditService $auditService) {}

    private function ttlMinutes(): int
    {
        return (int) config('workflow.support_claim_ttl_minutes', 15);
    }

    private function cacheKey(EngineRequest $request): string
    {
        return "engine_claim:{$request->id}";
    }

    public function claim(EngineRequest $request, User $user): EngineRequest
    {
        return DB::transaction(function () use ($request, $user) {
            $locked = EngineRequest::lockForUpdate()->findOrFail($request->id);

            if ($locked->isClaimed() && $locked->claimed_by !== $user->id) {
                throw EngineException::stageClaimed();
            }

            $expiresAt = now()->addMinutes($this->ttlMinutes());
            $isNew = ! $locked->isClaimed();
            $locked->forceFill([
                'claimed_by' => $user->id,
                'claimed_at' => $locked->claimed_at ?? now(),
                'claim_expires_at' => $expiresAt,
            ])->save();

            Cache::put($this->cacheKey($locked), $user->id, $expiresAt);

            if ($isNew) {
                $this->auditService->log(AuditAction::CLAIM_ACQUIRED, $user, $locked, [
                    'entity_type' => 'engine_request',
                ]);
            }

            return $locked;
        });
    }

    public function heartbeat(EngineRequest $request, User $user): EngineRequest
    {
        $fresh = EngineRequest::findOrFail($request->id);
        if ($fresh->claimed_by !== $user->id) {
            throw EngineException::claimNotHeld();
        }
        $expiresAt = now()->addMinutes($this->ttlMinutes());
        $fresh->forceFill(['claim_expires_at' => $expiresAt])->save();
        Cache::put($this->cacheKey($fresh), $user->id, $expiresAt);

        return $fresh;
    }

    public function release(EngineRequest $request, User $user): EngineRequest
    {
        return DB::transaction(function () use ($request, $user) {
            $locked = EngineRequest::lockForUpdate()->findOrFail($request->id);
            $isAdmin = $user->role === UserRole::CBY_ADMIN;
            if (! $isAdmin && $locked->claimed_by !== $user->id) {
                throw EngineException::claimNotHeld();
            }
            $locked->forceFill([
                'claimed_by' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
            ])->save();
            Cache::forget($this->cacheKey($locked));
            $this->auditService->log(AuditAction::CLAIM_RELEASED, $user, $locked, [
                'entity_type' => 'engine_request',
            ]);

            return $locked;
        });
    }
}
