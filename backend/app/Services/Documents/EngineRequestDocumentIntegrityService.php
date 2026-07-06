<?php

namespace App\Services\Documents;

use App\Enums\AuditAction;
use App\Enums\DocumentScanStatus;
use App\Models\EngineRequestDocument;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Storage;

class EngineRequestDocumentIntegrityService
{
    public function __construct(private readonly AuditService $auditService) {}

    public function scanStatusForNewUpload(): DocumentScanStatus
    {
        if (config('workflow.document_scan_enforced')) {
            return DocumentScanStatus::Pending;
        }

        return DocumentScanStatus::Clean;
    }

    public function assertDownloadAllowed(
        EngineRequestDocument $document,
        User $actor,
        int $workflowInstanceId,
    ): void {
        $enforced = (bool) config('workflow.document_scan_enforced');
        $scanStatus = $document->scan_status ?? DocumentScanStatus::Clean;

        if (! $scanStatus->isDownloadable($enforced)) {
            $this->auditService->log(
                AuditAction::DOCUMENT_SCAN_BLOCKED,
                $actor,
                $document,
                [
                    'request_id' => $workflowInstanceId,
                    'scan_status' => $scanStatus->value,
                ],
                workflowInstanceId: $workflowInstanceId,
            );

            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Document is not available for download until malware scanning completes successfully.',
                'error_code' => 'DOCUMENT_SCAN_BLOCKED',
                'scan_status' => $scanStatus->value,
            ], 403));
        }

        if ($document->checksum === null || $document->checksum === '') {
            $this->auditChecksumFailure($document, $actor, $workflowInstanceId, null, 'missing');

            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Document integrity could not be verified.',
                'error_code' => 'DOCUMENT_CHECKSUM_MISMATCH',
            ], 403));
        }

        $disk = Storage::disk('private');
        $actualChecksum = hash_file('sha256', $disk->path($document->path));

        if (! hash_equals($document->checksum, $actualChecksum)) {
            $this->auditChecksumFailure($document, $actor, $workflowInstanceId, $actualChecksum, 'mismatch');

            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Document integrity verification failed.',
                'error_code' => 'DOCUMENT_CHECKSUM_MISMATCH',
            ], 403));
        }
    }

    private function auditChecksumFailure(
        EngineRequestDocument $document,
        User $actor,
        int $workflowInstanceId,
        ?string $actualChecksum,
        string $reason,
    ): void {
        $this->auditService->log(
            AuditAction::DOCUMENT_CHECKSUM_MISMATCH,
            $actor,
            $document,
            [
                'request_id' => $workflowInstanceId,
                'reason' => $reason,
                'expected_checksum' => $document->checksum,
                'actual_checksum' => $actualChecksum,
            ],
            workflowInstanceId: $workflowInstanceId,
        );
    }
}
