<?php

namespace App\Services\Workflow;

use App\DTOs\ClaimResult;
use App\Enums\IdempotencyKeyState;
use App\Exceptions\EngineException;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PROCESSING/COMPLETED state machine for idempotent submission. Every
 * mutating statement is guarded by the row's exact claim_token, not just
 * its id, so a reclaimed lease can never be affected by the attempt it
 * superseded (see TemporaryUploadReservationService for the identical
 * principle applied to upload reservations).
 */
class IdempotencyCoordinator
{
    public const OPERATION_ENGINE_REQUEST_CREATE = 'engine_request.create';

    /** @return ClaimResult one of: claimed (proceed), replay (return stored response), in-progress (return 202), reused (return 409) */
    public function claim(User $user, string $operation, string $key, string $fingerprint, int $leaseSeconds): ClaimResult
    {
        $claimToken = (string) Str::uuid();

        try {
            $row = IdempotencyKey::query()->create([
                'key' => $key,
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
                'operation' => $operation,
                'request_fingerprint' => $fingerprint,
                'state' => IdempotencyKeyState::Processing,
                'claim_token' => $claimToken,
                'locked_until' => now()->addSeconds($leaseSeconds),
            ]);

            return ClaimResult::claimed($row, $claimToken);
        } catch (UniqueConstraintViolationException) {
            // Another attempt already claimed this (user, operation, key) —
            // fall through to look up and branch on its current state.
        }

        return DB::transaction(function () use ($user, $operation, $key, $fingerprint, $leaseSeconds, $claimToken): ClaimResult {
            $row = IdempotencyKey::query()
                ->where('user_id', $user->id)
                ->where('operation', $operation)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                // Vanished between the failed insert and this lookup (raced
                // with a delete on deterministic rejection) — safe to retry
                // the claim once from a clean slate.
                $row = IdempotencyKey::query()->create([
                    'key' => $key,
                    'user_id' => $user->id,
                    'organization_id' => $user->organization_id,
                    'operation' => $operation,
                    'request_fingerprint' => $fingerprint,
                    'state' => IdempotencyKeyState::Processing,
                    'claim_token' => $claimToken,
                    'locked_until' => now()->addSeconds($leaseSeconds),
                ]);

                return ClaimResult::claimed($row, $claimToken);
            }

            if ($row->state === IdempotencyKeyState::Completed) {
                if ($row->request_fingerprint !== $fingerprint) {
                    throw EngineException::idempotencyKeyReused();
                }

                return ClaimResult::replay($row);
            }

            // PROCESSING
            if ($row->request_fingerprint !== $fingerprint) {
                throw EngineException::idempotencyKeyReused();
            }

            if (! $row->locked_until->isPast()) {
                return ClaimResult::inProgress();
            }

            // Lease expired — atomic compare-and-set reclaim on the old
            // claim_token, never an unconditional overwrite.
            $affected = IdempotencyKey::query()
                ->whereKey($row->id)
                ->where('claim_token', $row->claim_token)
                ->where('state', IdempotencyKeyState::Processing->value)
                ->update([
                    'claim_token' => $claimToken,
                    'locked_until' => now()->addSeconds($leaseSeconds),
                ]);

            if ($affected !== 1) {
                // Reclaimed by someone else in the gap — caller should retry once.
                return ClaimResult::inProgress();
            }

            return ClaimResult::claimed($row->fresh(), $claimToken);
        });
    }

    /** @param  list<int>  $reservedUploadIds  ids this attempt currently owns, for the renewal's row-count check */
    public function renewLease(int $idempotencyKeyId, string $claimToken, int $leaseSeconds): bool
    {
        $affected = IdempotencyKey::query()
            ->whereKey($idempotencyKeyId)
            ->where('claim_token', $claimToken)
            ->where('state', IdempotencyKeyState::Processing->value)
            ->update(['locked_until' => now()->addSeconds($leaseSeconds)]);

        return $affected === 1;
    }

    public function verifyStillOwned(int $idempotencyKeyId, string $claimToken): bool
    {
        return IdempotencyKey::query()
            ->whereKey($idempotencyKeyId)
            ->where('claim_token', $claimToken)
            ->where('state', IdempotencyKeyState::Processing->value)
            ->where('locked_until', '>=', now())
            ->lockForUpdate()
            ->exists();
    }

    public function complete(
        int $idempotencyKeyId,
        string $claimToken,
        int $engineRequestId,
        int $responseStatus,
        array $responseBody,
    ): bool {
        $affected = IdempotencyKey::query()
            ->whereKey($idempotencyKeyId)
            ->where('claim_token', $claimToken)
            ->update([
                'state' => IdempotencyKeyState::Completed,
                'response_status' => $responseStatus,
                'response_body' => $responseBody,
                'engine_request_id' => $engineRequestId,
                'completed_at' => now(),
            ]);

        return $affected === 1;
    }

    /** Deterministic pre-creation rejection: delete rather than mark COMPLETED, so a retry re-claims cleanly. */
    public function deleteClaim(int $idempotencyKeyId, string $claimToken): void
    {
        IdempotencyKey::query()
            ->whereKey($idempotencyKeyId)
            ->where('claim_token', $claimToken)
            ->where('state', IdempotencyKeyState::Processing->value)
            ->delete();
    }
}
