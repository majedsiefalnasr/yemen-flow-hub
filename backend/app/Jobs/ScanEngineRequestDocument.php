<?php

namespace App\Jobs;

use App\Enums\DocumentScanStatus;
use App\Models\EngineRequestDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Async malware scan placeholder (F-8). When enforcement is enabled, uploads
 * start as pending and this job marks them clean unless the filename signals
 * a test infection (EICAR).
 */
class ScanEngineRequestDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $documentId) {}

    public function handle(): void
    {
        if (! config('workflow.document_scan_enforced')) {
            return;
        }

        $document = EngineRequestDocument::query()->find($this->documentId);
        if ($document === null) {
            return;
        }

        if (($document->scan_status ?? DocumentScanStatus::Clean) !== DocumentScanStatus::Pending) {
            return;
        }

        $infected = str_contains(strtoupper($document->original_name), 'EICAR');

        $document->forceFill([
            'scan_status' => $infected ? DocumentScanStatus::Infected : DocumentScanStatus::Clean,
        ])->save();

        if ($infected) {
            Storage::disk('private')->delete($document->path);
        }
    }
}
