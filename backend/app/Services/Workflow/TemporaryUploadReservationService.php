<?php

namespace App\Services\Workflow;

use App\Exceptions\EngineException;
use App\Models\TemporaryUpload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Atomic reservation lifecycle binding a set of TemporaryUpload rows to one
 * specific submission attempt (an idempotency claim + its own claim_token —
 * never the idempotency row id alone, since a reclaimed row keeps the same
 * id with a new claim_token). Every mutating operation here carries both
 * predicates together so a superseded attempt can never release, consume,
 * or otherwise affect a reservation that has since been reclaimed by a
 * newer attempt under the same idempotency key.
 */
class TemporaryUploadReservationService
{
    /**
     * Reserve every given token, in ascending id order, inside one short
     * transaction. All-or-nothing: any single conflict rolls back every
     * reservation this call attempted.
     *
     * @param  list<int>  $tokenIds  TemporaryUpload row ids, not tokens
     *
     * @throws EngineException UPLOAD_TOKEN_RESERVED if any token is held by a different, still-active attempt
     */
    public function reserve(array $tokenIds, int $idempotencyKeyId, string $claimToken, int $leaseSeconds): void
    {
        if ($tokenIds === []) {
            return;
        }

        sort($tokenIds);

        DB::transaction(function () use ($tokenIds, $idempotencyKeyId, $claimToken, $leaseSeconds): void {
            foreach ($tokenIds as $id) {
                $row = TemporaryUpload::query()->whereKey($id)->lockForUpdate()->first();

                if ($row === null) {
                    throw EngineException::uploadTokenInvalid();
                }

                $ownedByThisAttempt = $row->reserved_by_idempotency_key_id === $idempotencyKeyId
                    && $row->reservation_claim_token === $claimToken;
                $unclaimed = $row->reserved_by_idempotency_key_id === null;
                $abandoned = $row->reservation_expires_at !== null && $row->reservation_expires_at->isPast();

                if (! $ownedByThisAttempt && ! $unclaimed && ! $abandoned) {
                    throw EngineException::uploadTokenReserved();
                }

                $row->forceFill([
                    'reserved_by_idempotency_key_id' => $idempotencyKeyId,
                    'reservation_claim_token' => $claimToken,
                    'reservation_expires_at' => now()->addSeconds($leaseSeconds),
                ])->save();
            }
        });
    }

    /**
     * Renew every owned reservation together. Returns false (does not
     * throw) if fewer than the full expected set was actually renewed —
     * the caller decides what "renewal failed" means for its own flow.
     *
     * reservation_expires_at is a second-precision column, and MySQL's PDO
     * driver reports UPDATE's affected-row count as rows *changed*, not
     * rows *matched* (see IdempotencyCoordinator::renewLease() for the
     * full explanation and empirical confirmation). A renewal issued
     * within the same wall-clock second as the row's current value can
     * write an identical (second-truncated) value and be undercounted as
     * a false negative. When the row count comes up short, re-verify
     * ownership of the shortfall directly before concluding it was lost.
     *
     * @param  list<int>  $tokenIds
     */
    public function renew(array $tokenIds, int $idempotencyKeyId, string $claimToken, int $leaseSeconds): bool
    {
        if ($tokenIds === []) {
            return true;
        }

        $newExpiresAt = now()->addSeconds($leaseSeconds);

        $affected = TemporaryUpload::query()
            ->whereIn('id', $tokenIds)
            ->where('reserved_by_idempotency_key_id', $idempotencyKeyId)
            ->where('reservation_claim_token', $claimToken)
            ->update(['reservation_expires_at' => $newExpiresAt]);

        if ($affected === count($tokenIds)) {
            return true;
        }

        return $this->verifyOwnershipAfterRenewal($tokenIds, $idempotencyKeyId, $claimToken, $newExpiresAt);
    }

    /**
     * Extracted so the same-second no-op-write fallback can be exercised
     * directly in tests regardless of a given database driver's UPDATE
     * affected-row semantics (see renew()'s docblock — the false negative
     * this guards against is specific to MySQL's PDO driver and does not
     * reproduce on SQLite, which reports rows matched rather than rows
     * changed).
     *
     * @param  list<int>  $tokenIds
     */
    private function verifyOwnershipAfterRenewal(array $tokenIds, int $idempotencyKeyId, string $claimToken, Carbon $expectedExpiresAt): bool
    {
        $ownedCount = TemporaryUpload::query()
            ->whereIn('id', $tokenIds)
            ->where('reserved_by_idempotency_key_id', $idempotencyKeyId)
            ->where('reservation_claim_token', $claimToken)
            ->where('reservation_expires_at', '>=', $expectedExpiresAt->copy()->subSecond())
            ->count();

        return $ownedCount === count($tokenIds);
    }

    /**
     * Mark reserved uploads consumed (promotion succeeded) — still guarded
     * by the claim-token predicate, so a superseded attempt's consumption
     * call is a safe no-op rather than corrupting a newer attempt's rows.
     *
     * @param  list<int>  $tokenIds
     */
    public function consume(array $tokenIds, int $idempotencyKeyId, string $claimToken): bool
    {
        if ($tokenIds === []) {
            return true;
        }

        $affected = TemporaryUpload::query()
            ->whereIn('id', $tokenIds)
            ->where('reserved_by_idempotency_key_id', $idempotencyKeyId)
            ->where('reservation_claim_token', $claimToken)
            ->update([
                'consumed_at' => now(),
                'reserved_by_idempotency_key_id' => null,
                'reservation_claim_token' => null,
                'reservation_expires_at' => null,
            ]);

        return $affected === count($tokenIds);
    }

    /**
     * Release a set of reservations (deterministic pre-creation rejection).
     * Claim-token-guarded — a stale attempt's release call cannot touch a
     * reservation that has since been reclaimed.
     *
     * @param  list<int>  $tokenIds
     */
    public function release(array $tokenIds, int $idempotencyKeyId, string $claimToken): void
    {
        if ($tokenIds === []) {
            return;
        }

        TemporaryUpload::query()
            ->whereIn('id', $tokenIds)
            ->where('reserved_by_idempotency_key_id', $idempotencyKeyId)
            ->where('reservation_claim_token', $claimToken)
            ->update([
                'reserved_by_idempotency_key_id' => null,
                'reservation_claim_token' => null,
                'reservation_expires_at' => null,
            ]);
    }
}
