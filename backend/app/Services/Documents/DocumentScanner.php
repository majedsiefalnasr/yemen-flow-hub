<?php

namespace App\Services\Documents;

use App\Enums\DocumentScanStatus;
use Illuminate\Support\Facades\Storage;

/**
 * Shared scan decision + quarantine action, extracted from
 * ScanEngineRequestDocument so both EngineRequestDocument and
 * TemporaryUpload scanning share one implementation instead of two drifting
 * copies of the fail-closed retry logic.
 *
 * Placeholder scan (F-8): flags a known test-infection filename convention
 * (EICAR) — not a real malware scanning engine. Accurate description only;
 * this is the existing project behavior, unchanged by the extraction.
 */
class DocumentScanner
{
    public function scan(string $originalName): DocumentScanStatus
    {
        $infected = str_contains(strtoupper($originalName), 'EICAR');

        return $infected ? DocumentScanStatus::Infected : DocumentScanStatus::Clean;
    }

    public function quarantine(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($path);
    }
}
