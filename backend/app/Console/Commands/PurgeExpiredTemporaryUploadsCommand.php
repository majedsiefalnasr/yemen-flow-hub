<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Models\TemporaryUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Two independent eligibility branches: unconsumed uploads past their TTL
 * (never claimed by a submission), and consumed uploads whose afterCommit()
 * cleanup callback was missed (crash between commit and the callback
 * running). Actively-reserved rows are excluded from both — a live
 * reservation means a submission is using the file right now.
 */
class PurgeExpiredTemporaryUploadsCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'workflow:purge-expired-temporary-uploads';

    protected $description = 'Delete expired unconsumed temporary uploads and sweep consumed uploads whose cleanup callback was missed.';

    public function handle(): int
    {
        return $this->runWithHeartbeat(function (): int {
            $disk = Storage::disk('private-tmp');
            $ttlHours = (int) config('retention.temporary_upload_ttl_hours');
            $now = now();
            $purged = 0;

            $unconsumedExpired = TemporaryUpload::query()
                ->whereNull('consumed_at')
                ->where('expires_at', '<', $now)
                ->where(function ($q) use ($now) {
                    $q->whereNull('reservation_expires_at')
                        ->orWhere('reservation_expires_at', '<', $now);
                })
                ->get();

            $consumedMissed = TemporaryUpload::query()
                ->whereNotNull('consumed_at')
                ->where('consumed_at', '<', $now->copy()->subHours($ttlHours))
                ->get();

            foreach ($unconsumedExpired->merge($consumedMissed) as $upload) {
                if ($disk->exists($upload->path)) {
                    $disk->delete($upload->path);
                }
                $upload->delete();
                $purged++;
            }

            return $purged;
        });
    }
}
