<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Enums\IdempotencyKeyState;
use App\Models\IdempotencyKey;
use App\Models\TemporaryUpload;
use Illuminate\Console\Command;

/**
 * Two independent, separately-configured eligibility rules:
 *  - COMPLETED records past idempotency_key_retention_days (default 90d).
 *  - PROCESSING records abandoned well past their lease
 *    (abandoned_processing_margin_minutes, default 30m beyond the lease
 *    itself — not the lease expiry alone, since an expired lease is the
 *    normal, expected, reclaimable state for an active retry) with no
 *    active reservation and no attached request.
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
                ->get();

            $abandonedPurged = 0;
            foreach ($candidates as $key) {
                $hasActiveReservation = TemporaryUpload::query()
                    ->where('reserved_by_idempotency_key_id', $key->id)
                    ->where('reservation_claim_token', $key->claim_token)
                    ->where('reservation_expires_at', '>=', now())
                    ->exists();

                if ($hasActiveReservation) {
                    continue;
                }

                $key->delete();
                $abandonedPurged++;
            }

            return $completedPurged + $abandonedPurged;
        });
    }
}
