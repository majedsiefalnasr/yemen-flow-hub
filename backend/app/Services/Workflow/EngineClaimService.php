<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Exceptions\EngineException;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Settings\SettingResolver;
use App\Support\RoleCodes;
use Illuminate\Support\Facades\DB;

class EngineClaimService
{
    public function __construct(
        private AuditService $auditService,
        private SettingResolver $settings,
    ) {}

    private function ttlMinutes(): int
    {
        return (int) $this->settings->get('support_claim_ttl', 15);
    }

    /**
     * Require the caller to hold a valid claim when the current stage requires one.
     */
    public function ensureClaimHeld(EngineRequest $request, User $user): void
    {
        $request->loadMissing('currentStage');
        $stage = $request->currentStage;
        if ($stage === null || ! $stage->requires_claim) {
            return;
        }

        if (! ($request->claimed_by === $user->id && $request->isClaimed())) {
            throw EngineException::claimNotHeld();
        }

        if ($request->claim_stage_id !== null
            && (int) $request->claim_stage_id !== (int) $request->current_stage_id) {
            throw EngineException::claimNotHeld();
        }
    }

    public function claim(EngineRequest $request, User $user): EngineRequest
    {
        return DB::transaction(function () use ($request, $user) {
            $locked = EngineRequest::lockForUpdate()->findOrFail($request->id);

            if (! $locked->isActive()) {
                throw EngineException::requestClosed();
            }

            if ($locked->isClaimed() && $locked->claimed_by !== $user->id) {
                throw EngineException::stageClaimed();
            }

            $expiresAt = now()->addMinutes($this->ttlMinutes());
            $isNew = ! $locked->isClaimed();
            $locked->forceFill([
                'claimed_by' => $user->id,
                'claimed_at' => $locked->claimed_at ?? now(),
                'claim_expires_at' => $expiresAt,
                'claim_stage_id' => $locked->current_stage_id,
            ])->save();

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
        return DB::transaction(function () use ($request, $user) {
            $locked = EngineRequest::lockForUpdate()->findOrFail($request->id);

            if (! $locked->isActive()) {
                throw EngineException::requestClosed();
            }

            if ($locked->claimed_by !== $user->id) {
                throw EngineException::claimNotHeld();
            }

            if ($locked->claim_expires_at === null || $locked->claim_expires_at->isPast()) {
                throw EngineException::claimNotHeld();
            }

            if ($locked->claim_stage_id !== null
                && (int) $locked->claim_stage_id !== (int) $locked->current_stage_id) {
                throw EngineException::claimNotHeld();
            }

            $expiresAt = now()->addMinutes($this->ttlMinutes());
            $locked->forceFill(['claim_expires_at' => $expiresAt])->save();

            return $locked;
        });
    }

    public function release(EngineRequest $request, User $user): EngineRequest
    {
        return DB::transaction(function () use ($request, $user) {
            $locked = EngineRequest::lockForUpdate()->findOrFail($request->id);
            $isAdmin = $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN);
            if (! $isAdmin && $locked->claimed_by !== $user->id) {
                throw EngineException::claimNotHeld();
            }
            $this->clearClaimFields($locked);
            $this->auditService->log(AuditAction::CLAIM_RELEASED, $user, $locked, [
                'entity_type' => 'engine_request',
            ]);

            return $locked;
        });
    }

    /**
     * Release a claim whose TTL has expired. System-initiated (no holder-identity
     * check, unlike release()) — called by the scheduled expiry command, not a user
     * action. Mirrors release() but tags the audit entry with reason: ttl_expired.
     */
    public function releaseExpired(EngineRequest $request): EngineRequest
    {
        return DB::transaction(function () use ($request) {
            $locked = EngineRequest::lockForUpdate()->findOrFail($request->id);

            if ($locked->claimed_by === null || $locked->claim_expires_at === null || $locked->claim_expires_at->isFuture()) {
                return $locked; // already released or re-claimed/extended since the scan — no-op
            }

            $this->clearClaimFields($locked);
            $this->auditService->log(AuditAction::CLAIM_RELEASED, null, $locked, [
                'entity_type' => 'engine_request',
                'reason' => 'ttl_expired',
            ]);

            return $locked;
        });
    }

    /**
     * System-initiated claim release after a successful stage transition.
     * No holder-identity check — the transition performer triggers this within their transaction.
     */
    public function releaseForStageChange(EngineRequest $request, User $actor): EngineRequest
    {
        if ($request->claimed_by === null) {
            return $request;
        }

        $request->loadMissing('currentStage');
        // Claim was already scoped to the from-stage; release regardless of to-stage requires_claim.
        $this->clearClaimFields($request);
        $this->auditService->log(AuditAction::CLAIM_RELEASED, $actor, $request, [
            'entity_type' => 'engine_request',
            'reason' => 'stage_changed',
        ]);

        return $request;
    }

    private function clearClaimFields(EngineRequest $request): void
    {
        $request->forceFill([
            'claimed_by' => null,
            'claimed_at' => null,
            'claim_expires_at' => null,
            'claim_stage_id' => null,
        ])->save();
    }
}
