<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeOldReportExportsCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'reports:purge-old-exports';

    protected $description = 'Expire completed report export files past retention horizon';

    public function handle(): int
    {
        return $this->runWithHeartbeat(function (): int {
            $cutoff = now()->subDays(config('retention.export_file_days'));
            $expired = 0;

            ReportExport::query()
                ->where('status', 'COMPLETED')
                ->whereNotNull('file_path')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->chunkById(100, function ($exports) use (&$expired): void {
                    foreach ($exports as $export) {
                        if ($export->file_path !== null && Storage::disk('private')->exists($export->file_path)) {
                            Storage::disk('private')->delete($export->file_path);
                        }

                        $export->update([
                            'status' => 'EXPIRED',
                            'file_path' => null,
                        ]);

                        $expired++;
                    }
                });

            return $expired;
        });
    }
}
