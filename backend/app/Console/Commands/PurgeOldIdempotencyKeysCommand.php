<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Enums\IdempotencyKeyState;
use App\Models\IdempotencyKey;
use App\Models\TemporaryUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Two independent, separately-configured eligibility rules:
 *  - COMPLETED records past idempotency_key_retention_days (default 90d).
 *  - PROCESSING records abandoned well past their lease
 *    (abandoned_processing_margin_minutes, default 30m beyond the lease
 *    itself — not the lease expiry alone, since an expired lease is the
 *    normal, expected, reclaimable state for an active retry) with no
 *    active reservation and no attached request.
 *
 * Race safety: COMPLETED deletion is a plain bulk delete — a COMPLETED row
 * is a terminal state (IdempotencyCoordinator never mutates it further), so
 * there is nothing left to race with. The PROCESSING branch is different: a
 * concurrent reclaim can turn an abandoned row into a live retry at any
 * moment, so each candidate is locked and its full eligibility predicate
 * (claim_token unchanged, lease still stale, no active reservation, still
 * unattached) is re-evaluated immediately before deletion.
 */
class PurgeOldIdempotencyKeysCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'workflow:purge-old-idempotency-keys';

    protected $description = 'Delete completed idempotency records past retention, and abandoned PROCESSING records nobody retried.';

    public function handle(): int
    {
        return $this->runWithHeartbeat(function (): int {
            $retentionDays = (int) config('retention.idempotency_key_retention_days');
            $marginMinutes = (int) config('retention.abandoned_processing_margin_minutes');

            $completedPurged = IdempotencyKey::query()
                ->where('state', IdempotencyKeyState::Completed->value)
                ->where('completed_at', '<', now()->subDays($retentionDays))
                ->delete();

            $staleCutoff = now()->subMinutes($marginMinutes);
            $candidates = IdempotencyKey::query()
                ->where('state', IdempotencyKeyState::Processing->value)
                ->where('locked_until', '<', $staleCutoff)
                ->whereNull('engine_request_id')
                ->pluck('id');

            $abandonedPurged = 0;
            foreach ($candidates as $id) {
                if ($this->tryPurgeOneAbandoned($id, $marginMinutes)) {
                    $abandonedPurged++;
                }
            }

            return $completedPurged + $abandonedPurged;
        });
    }

    private function tryPurgeOneAbandoned(int $id, int $marginMinutes): bool
    {
        return DB::transaction(function () use ($id, $marginMinutes): bool {
            $key = IdempotencyKey::query()->whereKey($id)->lockForUpdate()->first();

            if ($key === null) {
                return false;
            }

            $staleCutoff = now()->subMinutes($marginMinutes);
            $stillEligible = $key->state === IdempotencyKeyState::Processing
                && $key->locked_until->lt($staleCutoff)
                && $key->engine_request_id === null;

            if (! $stillEligible) {
                // Reclaimed, completed, or attached to a request since the
                // candidate scan.
                return false;
            }

            $hasActiveReservation = TemporaryUpload::query()
                ->where('reserved_by_idempotency_key_id', $key->id)
                ->where('reservation_claim_token', $key->claim_token)
                ->where('reservation_expires_at', '>=', now())
                ->lockForUpdate()
                ->exists();

            if ($hasActiveReservation) {
                return false;
            }

            $key->delete();

            return true;
        });
    }
}
