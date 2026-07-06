<?php

namespace App\Services\Documents;

use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class EngineRequestDocumentReplacementService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly EngineRequestDocumentIntegrityService $documentIntegrity,
    ) {}

    public function replace(
        EngineRequest $engineRequest,
        EngineRequestDocument $document,
        UploadedFile $file,
        User $actor,
        ?string $reason = null,
    ): EngineRequestDocument {
        if ((int) $document->request_id !== (int) $engineRequest->id) {
            abort(404);
        }

        if ($document->trashed()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Deleted documents cannot be replaced.',
                'error_code' => 'DOCUMENT_NOT_REPLACEABLE',
            ], 422));
        }

        $status = $document->status ?? DocumentStatus::Active;
        if (! $status->isActive()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Only the active document can be replaced.',
                'error_code' => 'DOCUMENT_NOT_REPLACEABLE',
            ], 422));
        }

        return DB::transaction(function () use ($engineRequest, $document, $file, $actor, $reason) {
            $path = $file->store("engine-requests/{$engineRequest->id}", 'private');
            $nextVersion = ((int) $document->version) + 1;

            $replacement = EngineRequestDocument::create([
                'request_id' => $engineRequest->id,
                'field_id' => $document->field_id,
                'uploaded_by' => $actor->id,
                'stage_id' => $engineRequest->current_stage_id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'scan_status' => $this->documentIntegrity->scanStatusForNewUpload(),
                'version' => $nextVersion,
                'status' => DocumentStatus::Active,
            ]);

            $document->update([
                'status' => DocumentStatus::Superseded,
                'superseded_by' => $replacement->id,
            ]);

            $auditContext = [
                'request_id' => $engineRequest->id,
                'replaced_document_id' => $document->id,
                'replacement_document_id' => $replacement->id,
                'reason' => $reason,
            ];

            $this->auditService->log(
                AuditAction::DOCUMENT_REPLACED,
                $actor,
                $replacement,
                $auditContext,
                workflowInstanceId: $engineRequest->id,
            );

            return $replacement->fresh(['uploader', 'stage', 'field']);
        });
    }
}
