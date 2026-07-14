<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Models\TemporaryUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Safety net for files on the `private-tmp` disk with no TemporaryUpload row
 * — the scenario an immediate-compensation delete failure can leave behind
 * (e.g. TemporaryUploadController::store()'s catch block deletes the file
 * when the DB insert fails; if THAT delete also fails, the file survives
 * with nothing in the DB to identify it, so PurgeExpiredTemporaryUploadsCommand
 * — which only ever walks rows — can never find it). Mirrors
 * PurgeOrphanDocumentsCommand's disk-scan pattern for the `private` disk.
 */
class PurgeOrphanTemporaryUploadsCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'workflow:purge-orphan-temporary-uploads';

    protected $description = 'Delete files on the private-tmp disk with no TemporaryUpload DB reference.';

    public function handle(): int
    {
        return $this->runWithHeartbeat(function (): int {
            $disk = Storage::disk('private-tmp');
            $referencedPaths = TemporaryUpload::query()
                ->whereNotNull('path')
                ->pluck('path')
                ->flip()
                ->all();

            $cutoff = now()->subHours((int) config('retention.orphan_file_grace_hours'))->getTimestamp();
            $purged = 0;

            foreach ($disk->allFiles() as $path) {
                if (isset($referencedPaths[$path])) {
                    continue;
                }

                if ($disk->lastModified($path) > $cutoff) {
                    continue;
                }

                $disk->delete($path);
                $purged++;
            }

            return $purged;
        });
    }
}
