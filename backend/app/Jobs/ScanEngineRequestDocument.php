<?php

namespace App\Jobs;

use App\Enums\DocumentScanStatus;
use App\Models\EngineRequestDocument;
use App\Services\Operations\OperationalAlertLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * Async malware scan placeholder (F-8). When enforcement is enabled, uploads
 * start as pending and this job marks them clean unless the filename signals
 * a test infection (EICAR).
 *
 * QUEUE-001 (fail-closed): a scan that keeps throwing must never leave a
 * document silently trusted. Retries are bounded ($tries/$timeout/backoff); on
 * final failure, failed() flips a still-pending document to Failed — which
 * DocumentScanStatus::isDownloadable() treats as not-clean — and alerts. A
 * document is only ever Clean when a scan actually completed and found nothing.
 */
class ScanEngineRequestDocument implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    /** Bounded retries so a stuck scan reaches failed() instead of retrying forever. */
    public int $tries = 3;

    /** Kill a hung scan worker rather than letting it occupy the queue indefinitely. */
    public int $timeout = 120;

    public function __construct(public int $documentId)
    {
        // QUEUE-003: dedicated queue so scans don't compete with notification
        // fan-out/exports on `default`.
        $this->onQueue('scans');
    }

    /**
     * Exponential-ish backoff between retries (seconds).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

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

    /**
     * Fail-closed: after retries are exhausted, a document still stuck in Pending
     * is marked Failed so it is never treated as scanned/clean, and the failure
     * is surfaced for operator follow-up. Documents already resolved by a prior
     * attempt (Clean/Infected) are left untouched.
     */
    public function failed(\Throwable $exception): void
    {
        $document = EngineRequestDocument::query()->find($this->documentId);

        if ($document !== null
            && ($document->scan_status ?? DocumentScanStatus::Clean) === DocumentScanStatus::Pending) {
            $document->forceFill(['scan_status' => DocumentScanStatus::Failed])->save();
        }

        OperationalAlertLogger::failure('document_scan', $exception, [
            'document_id' => $this->documentId,
        ]);
    }
}
