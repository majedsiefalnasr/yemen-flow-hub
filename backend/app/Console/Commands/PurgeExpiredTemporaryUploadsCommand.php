<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Models\TemporaryUpload;
use App\Services\Operations\OperationalAlertLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Two independent eligibility branches: unconsumed uploads past their TTL
 * (never claimed by a submission), and consumed uploads whose afterCommit()
 * cleanup callback was missed (crash between commit and the callback
 * running). Actively-reserved rows are excluded from both — a live
 * reservation means a submission is using the file right now.
 *
 * Race safety: the initial query only gathers CANDIDATE ids. Every row is
 * then locked and its full eligibility predicate re-evaluated immediately
 * before deletion, inside its own short transaction — a concurrent
 * submission that reserves or consumes the row between the candidate scan
 * and the delete must win, not this sweep.
 */
class PurgeExpiredTemporaryUploadsCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'workflow:purge-expired-temporary-uploads';

    protected $description = 'Delete expired unconsumed temporary uploads and sweep consumed uploads whose cleanup callback was missed.';

    public function handle(): int
    {
        return $this->runWithHeartbeat(function (): int {
            $ttlHours = (int) config('retention.temporary_upload_ttl_hours');
            $now = now();

            $candidateIds = TemporaryUpload::query()
                ->where(function ($q) use ($now, $ttlHours) {
                    $q->where(function ($unconsumed) use ($now) {
                        $unconsumed->whereNull('consumed_at')
                            ->where('expires_at', '<', $now);
                    })->orWhere(function ($consumedMissed) use ($now, $ttlHours) {
                        $consumedMissed->whereNotNull('consumed_at')
                            ->where('consumed_at', '<', $now->copy()->subHours($ttlHours));
                    });
                })
                ->pluck('id');

            $purged = 0;
            foreach ($candidateIds as $id) {
                if ($this->tryPurgeOne($id)) {
                    $purged++;
                }
            }

            return $purged;
        });
    }

    private function tryPurgeOne(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $upload = TemporaryUpload::query()->whereKey($id)->lockForUpdate()->first();

            if ($upload === null) {
                return false;
            }

            $now = now();
            $ttlHours = (int) config('retention.temporary_upload_ttl_hours');

            $isActivelyReserved = $upload->reservation_expires_at !== null
                && $upload->reservation_expires_at->isFuture();
            if ($isActivelyReserved) {
                return false;
            }

            $unconsumedExpired = $upload->consumed_at === null && $upload->expires_at->isPast();
            $consumedMissed = $upload->consumed_at !== null
                && $upload->consumed_at->lt($now->copy()->subHours($ttlHours));

            if (! $unconsumedExpired && ! $consumedMissed) {
                // No longer eligible — a concurrent submission consumed or
                // renewed the reservation since the candidate scan.
                return false;
            }

            $path = $upload->path;
            $disk = Storage::disk('private-tmp');

            // Delete the physical file BEFORE the row — never delete the row
            // first, or a delete failure leaves an untracked orphan with no
            // DB record left to identify it. On failure, keep the row (so
            // it's retried on the next run) and don't count this as purged.
            if ($disk->exists($path) && ! $disk->delete($path)) {
                OperationalAlertLogger::failure(
                    'temporary_upload_purge',
                    new \RuntimeException("Failed to delete expired temporary upload file: {$path}"),
                    ['path' => $path, 'temporary_upload_id' => $id],
                );

                return false;
            }

            $upload->delete();

            return true;
        });
    }
}
