<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Models\EngineRequestDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeOrphanDocumentsCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'documents:purge-orphans';

    protected $description = 'Delete orphan document files on private disk with no DB reference';

    public function handle(): int
    {
        return $this->runWithHeartbeat(function (): int {
            $disk = Storage::disk('private');
            $referencedPaths = EngineRequestDocument::withTrashed()
                ->whereNotNull('path')
                ->pluck('path')
                ->flip()
                ->all();

            $cutoff = now()->subHours(config('retention.orphan_file_grace_hours'))->getTimestamp();
            $purged = 0;

            foreach ($disk->allFiles('engine-requests') as $path) {
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
