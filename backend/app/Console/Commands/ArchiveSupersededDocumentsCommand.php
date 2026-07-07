<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Enums\DocumentStatus;
use App\Models\EngineRequestDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ArchiveSupersededDocumentsCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'documents:archive-superseded';

    protected $description = 'Remove physical files for superseded documents past retention horizon';

    public function handle(): int
    {
        return $this->runWithHeartbeat(function (): int {
            $cutoff = now()->subDays(config('retention.superseded_document_file_days'));
            $archived = 0;

            EngineRequestDocument::query()
                ->where('status', DocumentStatus::Superseded)
                ->whereNotNull('path')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->chunkById(100, function ($documents) use (&$archived): void {
                    foreach ($documents as $document) {
                        if ($document->path !== null && Storage::disk('private')->exists($document->path)) {
                            Storage::disk('private')->delete($document->path);
                        }

                        $document->update(['path' => null]);
                        $archived++;
                    }
                });

            return $archived;
        });
    }
}
