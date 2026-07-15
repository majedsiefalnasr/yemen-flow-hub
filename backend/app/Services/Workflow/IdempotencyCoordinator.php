<?php

namespace App\Services\Workflow;

use App\DTOs\ClaimResult;
use App\Enums\IdempotencyKeyState;
use App\Exceptions\EngineException;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
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
                return ClaimResult::inProgress((int) now()->diffInSeconds($row->locked_until, true));
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
                // Reclaimed by someone else in the gap — caller should retry
                // shortly; the row's lease was already expired so a short,
                // fixed backoff is appropriate here (no fresh locked_until to
                // read: the winner's new lease value isn't visible to us).
                return ClaimResult::inProgress(1);
            }

            return ClaimResult::claimed($row->fresh(), $claimToken);
        });
    }

    /**
     * `locked_until` is a second-precision column (see migration), while
     * the value written here is computed from now() at microsecond
     * precision and truncated to whole seconds by Eloquent's date
     * formatting before it reaches the query. A renewal issued within the
     * same wall-clock second as the row's current locked_until can
     * therefore compute and write the identical value the row already
     * has. MySQL's PDO driver reports UPDATE's affected-row count as rows
     * *changed*, not rows *matched* (no PDO::MYSQL_ATTR_FOUND_ROWS override
     * is set) — so that same-value write legitimately returns 0 affected
     * rows even though the WHERE predicate matched this exact row. Treating
     * that as "not owned" is a false negative. When the UPDATE reports 0,
     * fall back to a direct ownership check on the same predicates (plus
     * confirming locked_until now reflects — at second precision — the
     * value we just tried to set) before concluding the lease was lost.
     */
    public function renewLease(int $idempotencyKeyId, string $claimToken, int $leaseSeconds): bool
    {
        $newLockedUntil = now()->addSeconds($leaseSeconds);

        $affected = IdempotencyKey::query()
            ->whereKey($idempotencyKeyId)
            ->where('claim_token', $claimToken)
            ->where('state', IdempotencyKeyState::Processing->value)
            ->update(['locked_until' => $newLockedUntil]);

        if ($affected === 1) {
            return true;
        }

        // 0 affected rows: either genuinely not ours (reclaimed/expired/
        // wrong token) or a same-second no-op write. Disambiguate by
        // re-checking ownership directly — this still requires claim_token
        // and state to match, so a truly superseded attempt still fails.
        return $this->verifyOwnershipAfterRenewal($idempotencyKeyId, $claimToken, $newLockedUntil);
    }

    /**
     * Extracted so the same-second no-op-write fallback can be exercised
     * directly in tests regardless of a given database driver's UPDATE
     * affected-row semantics (see renewLease()'s docblock — the false
     * negative this guards against is specific to MySQL's PDO driver and
     * does not reproduce on SQLite, which reports rows matched rather than
     * rows changed).
     */
    private function verifyOwnershipAfterRenewal(int $idempotencyKeyId, string $claimToken, Carbon $expectedLockedUntil): bool
    {
        return IdempotencyKey::query()
            ->whereKey($idempotencyKeyId)
            ->where('claim_token', $claimToken)
            ->where('state', IdempotencyKeyState::Processing->value)
            ->where('locked_until', '>=', $expectedLockedUntil->copy()->subSecond())
            ->exists();
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
