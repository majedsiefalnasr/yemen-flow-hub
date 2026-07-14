<?php

namespace App\Services\Documents;

use App\Exceptions\EngineException;
use App\Models\TemporaryUpload;
use App\Services\Operations\OperationalAlertLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copies a TemporaryUpload's bytes from the isolated `private-tmp` disk to
 * a stable, server-generated path on `private` — before any request exists,
 * so the path can never depend on a request id. Both disks are configured
 * non-throwing ('throw' => false), so every operation's boolean/resource
 * return value is checked explicitly; nothing here relies on an exception
 * being thrown on failure.
 *
 * Deliberately not "copy()" — Storage::disk()->copy() operates within one
 * disk's own adapter and cannot transfer bytes between two different disks.
 * This uses an explicit readStream/put stream transfer instead.
 */
class TemporaryUploadPromotionService
{
    private const SOURCE_DISK = 'private-tmp';

    private const DEST_DISK = 'private';

    /** @return string the new permanent path, e.g. "engine-requests/{uuid}.pdf" */
    public function promote(TemporaryUpload $upload): string
    {
        $destPath = 'engine-requests/'.Str::uuid()->toString().'.pdf';

        $source = Storage::disk(self::SOURCE_DISK);
        $dest = Storage::disk(self::DEST_DISK);

        $stream = $source->readStream($upload->path);
        if ($stream === false) {
            throw EngineException::filePromotionFailed();
        }

        try {
            $written = $dest->put($destPath, $stream, ['visibility' => 'private']);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($written === false) {
            throw EngineException::filePromotionFailed();
        }

        $actualChecksum = hash_file('sha256', $dest->path($destPath));
        if (! hash_equals($upload->checksum, (string) $actualChecksum)) {
            $this->deleteCompensating($destPath);
            throw EngineException::uploadIntegrityMismatch();
        }

        return $destPath;
    }

    /**
     * Delete a set of already-promoted permanent files (main-transaction
     * failure compensation). Best-effort: a delete failure here means an
     * orphan exists, which the scheduled documents:purge-orphans sweep is
     * the safety net for — logged, not thrown.
     *
     * @param  list<string>  $paths
     */
    public function compensate(array $paths): void
    {
        foreach ($paths as $path) {
            $this->deleteCompensating($path);
        }
    }

    private function deleteCompensating(string $path): void
    {
        $deleted = Storage::disk(self::DEST_DISK)->delete($path);
        if ($deleted === false) {
            OperationalAlertLogger::failure(
                'temporary_upload_promotion_compensation',
                new \RuntimeException("Failed to delete orphaned promoted file: {$path}"),
                ['path' => $path],
            );
        }
    }
}
