<?php

namespace App\Jobs;

use App\Enums\DocumentScanStatus;
use App\Models\TemporaryUpload;
use App\Services\Documents\DocumentScanner;
use App\Services\Operations\OperationalAlertLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Same fail-closed shape as ScanEngineRequestDocument, scanning a
 * TemporaryUpload row instead. Shares DocumentScanner's scan decision and
 * quarantine action rather than duplicating them.
 */
class ScanTemporaryUpload implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $uploadId)
    {
        $this->onQueue('scans');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(DocumentScanner $scanner): void
    {
        if (! config('workflow.document_scan_enforced')) {
            return;
        }

        $upload = TemporaryUpload::query()->find($this->uploadId);
        if ($upload === null) {
            return;
        }

        if (($upload->scan_status ?? DocumentScanStatus::Clean) !== DocumentScanStatus::Pending) {
            return;
        }

        $status = $scanner->scan($upload->original_name);

        $upload->forceFill(['scan_status' => $status])->save();

        if ($status === DocumentScanStatus::Infected) {
            $scanner->quarantine('private-tmp', $upload->path);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $upload = TemporaryUpload::query()->find($this->uploadId);

        if ($upload !== null
            && ($upload->scan_status ?? DocumentScanStatus::Clean) === DocumentScanStatus::Pending) {
            $upload->forceFill(['scan_status' => DocumentScanStatus::Failed])->save();
        }

        OperationalAlertLogger::failure('temporary_upload_scan', $exception, [
            'upload_id' => $this->uploadId,
        ]);
    }
}
