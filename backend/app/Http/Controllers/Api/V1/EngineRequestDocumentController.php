<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Api\Controller;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Services\Audit\AuditService;
use App\Services\Documents\EngineRequestDocumentIntegrityService;
use App\Services\Documents\EngineRequestDocumentReplacementService;
use App\Services\Workflow\EngineClaimService;
use App\Services\Workflow\StageFieldOutputFilter;
use App\Support\UploadSizeLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EngineRequestDocumentController extends Controller
{
    public function __construct(
        private AuditService $auditService,
        private EngineClaimService $claimService,
        private EngineRequestDocumentIntegrityService $documentIntegrity,
        private EngineRequestDocumentReplacementService $documentReplacement,
        private StageFieldOutputFilter $outputFilter,
        private UploadSizeLimit $uploadSizeLimit,
    ) {}

    public function uploadDocument(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);
        $engineRequest->loadMissing('currentStage');
        $this->claimService->ensureClaimHeld($engineRequest, $request->user());

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.$this->uploadSizeLimit->maxKilobytes()],
            'field_id' => [
                'nullable',
                'integer',
                Rule::exists('field_definitions', 'id')
                    ->where('workflow_version_id', $engineRequest->workflow_version_id),
            ],
        ]);

        $file = $request->file('file');
        $path = $file->store("engine-requests/{$engineRequest->id}", 'private');

        $doc = EngineRequestDocument::create([
            'request_id' => $engineRequest->id,
            'field_id' => $request->input('field_id'),
            'uploaded_by' => $request->user()->id,
            'stage_id' => $engineRequest->current_stage_id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'scan_status' => $this->documentIntegrity->scanStatusForNewUpload(),
            'status' => DocumentStatus::Active,
        ]);

        $this->auditService->log(
            AuditAction::DOCUMENT_UPLOADED,
            $request->user(),
            $doc,
            ['request_id' => $engineRequest->id, 'original_name' => $doc->original_name],
            workflowInstanceId: $engineRequest->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'data' => $this->documentResource($doc),
        ], 201);
    }

    public function listDocuments(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $engineRequest->loadMissing('currentStage');

        $docs = $engineRequest->documents()
            ->with(['uploader', 'stage', 'field'])
            ->get()
            ->filter(fn (EngineRequestDocument $doc) => $this->outputFilter->canViewerAccessFieldLinkedDocument(
                $engineRequest,
                $doc,
                $request->user(),
            ));

        return response()->json([
            'success' => true,
            'data' => $docs->map(fn ($d) => $this->documentResource($d))->values(),
        ]);
    }

    public function downloadDocument(Request $request, EngineRequest $engineRequest, EngineRequestDocument $document): mixed
    {
        $this->authorize('view', $engineRequest);

        if ((int) $document->request_id !== (int) $engineRequest->id) {
            abort(404);
        }

        $engineRequest->loadMissing('currentStage');
        if (! $this->outputFilter->canViewerAccessFieldLinkedDocument($engineRequest, $document, $request->user())) {
            abort(404);
        }

        if (! Storage::disk('private')->exists($document->path)) {
            abort(404);
        }

        $this->documentIntegrity->assertDownloadAllowed(
            $document,
            $request->user(),
            $engineRequest->id,
        );

        $this->auditService->log(
            AuditAction::DOCUMENT_DOWNLOADED,
            $request->user(),
            $document,
            ['request_id' => $engineRequest->id],
            workflowInstanceId: $engineRequest->id,
        );

        return Storage::disk('private')->download($document->path, $document->original_name);
    }

    public function deleteDocument(Request $request, EngineRequest $engineRequest, EngineRequestDocument $document): JsonResponse
    {
        $this->authorize('execute', $engineRequest);
        $engineRequest->loadMissing('currentStage');
        $this->claimService->ensureClaimHeld($engineRequest, $request->user());

        if ((int) $document->request_id !== (int) $engineRequest->id) {
            abort(404);
        }

        if ((int) $document->stage_id !== (int) $engineRequest->current_stage_id) {
            return response()->json([
                'success' => false,
                'message' => 'Document cannot be deleted after the stage has been left.',
                'error_code' => 'DOCUMENT_LOCKED',
            ], 422);
        }

        $document->delete();

        $this->auditService->log(
            AuditAction::DOCUMENT_DELETED,
            $request->user(),
            $document,
            ['request_id' => $engineRequest->id],
            workflowInstanceId: $engineRequest->id,
        );

        return response()->json(['success' => true, 'message' => 'Document deleted.']);
    }

    public function replaceDocument(Request $request, EngineRequest $engineRequest, EngineRequestDocument $document): JsonResponse
    {
        $this->authorize('execute', $engineRequest);
        $engineRequest->loadMissing('currentStage');
        $this->claimService->ensureClaimHeld($engineRequest, $request->user());

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.$this->uploadSizeLimit->maxKilobytes()],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $replacement = $this->documentReplacement->replace(
            $engineRequest,
            $document,
            $request->file('file'),
            $request->user(),
            $request->input('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Document replaced successfully.',
            'data' => $this->documentResource($replacement),
        ], 201);
    }

    private function documentResource(EngineRequestDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'request_id' => $doc->request_id,
            'field_id' => $doc->field_id,
            'stage_id' => $doc->stage_id,
            'original_name' => $doc->original_name,
            'mime' => $doc->mime,
            'size' => $doc->size,
            'version' => $doc->version,
            'status' => ($doc->status ?? DocumentStatus::Active)->value,
            'is_active' => $doc->isActive(),
            'superseded_by' => $doc->superseded_by,
            'uploaded_by' => $doc->relationLoaded('uploader') && $doc->uploader
                ? ['id' => $doc->uploader->id, 'name' => $doc->uploader->name]
                : $doc->uploaded_by,
            'created_at' => $doc->created_at?->toISOString(),
        ];
    }
}
