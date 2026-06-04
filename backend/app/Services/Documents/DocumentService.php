<?php

namespace App\Services\Documents;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Exceptions\DocumentException;
use App\Exceptions\WorkflowLockedStateException;
use App\Models\ImportRequest;
use App\Models\RequestDocument;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Workflow\WorkflowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly AuditService $auditService
    ) {
    }

    public function uploadRequestDocument(ImportRequest $request, User $uploader, UploadedFile $file, ?string $subType = null): RequestDocument
    {
        $this->assertFileValid($file);

        if (!$request->isEditable()) {
            throw new WorkflowLockedStateException('Request documents can only be uploaded while request is editable.');
        }

        if (!$uploader->hasPermission('request.create') || $uploader->bank_id !== $request->bank_id) {
            throw new DocumentException('Only authorized bank users can upload request documents.');
        }

        $document = $this->storeDocument($request, $uploader, $file, 'REQUEST_DOC', "requests/{$request->id}", $subType);

        $this->auditService->log(AuditAction::DOCUMENT_UPLOADED, $uploader, $document, [
            'request_id' => $request->id,
            'type' => 'REQUEST_DOC',
        ]);

        return $document;
    }

    public function uploadConfirmationRequest(ImportRequest $request, User $uploader, UploadedFile $file): RequestDocument
    {
        $this->assertFileValid($file);

        if (!$request->isEditable()) {
            throw new WorkflowLockedStateException('Confirmation request can only be uploaded while request is editable.');
        }

        if (!$uploader->hasPermission('request.create') || $uploader->bank_id !== $request->bank_id) {
            throw new DocumentException('Only authorized bank users can upload confirmation request documents.');
        }

        if (RequestDocument::query()->where('request_id', $request->id)->where('type', 'CONFIRMATION_REQUEST')->exists()) {
            $existing = RequestDocument::query()
                ->where('request_id', $request->id)
                ->where('type', 'CONFIRMATION_REQUEST')
                ->get();

            foreach ($existing as $document) {
                $this->deleteStoredDocument($document);
                $document->delete();
            }
        }

        $document = $this->storeDocument($request, $uploader, $file, 'CONFIRMATION_REQUEST', "confirmation-request/{$request->id}", 'confirmation_request');

        $this->auditService->log(AuditAction::DOCUMENT_UPLOADED, $uploader, $document, [
            'request_id' => $request->id,
            'type' => 'CONFIRMATION_REQUEST',
        ]);

        return $document;
    }

    /**
     * Legacy single-file SWIFT upload flow.
     * Kept for backward compatibility with older clients/tests while package mode rolls out.
     */
    public function uploadSwift(ImportRequest $request, User $uploader, UploadedFile $file): RequestDocument
    {
        $this->assertFileValid($file);

        if ($request->status !== RequestStatus::WAITING_FOR_SWIFT) {
            throw new DocumentException('SWIFT can only be uploaded when request is in WAITING_FOR_SWIFT status.');
        }

        if (!$uploader->hasPermission('swift.upload') || $uploader->bank_id !== $request->bank_id) {
            throw new DocumentException('Only authorized bank users can upload SWIFT documents.');
        }

        if (RequestDocument::query()->where('request_id', $request->id)->where('type', 'SWIFT')->exists()) {
            throw new DocumentException('SWIFT document already uploaded and cannot be replaced.');
        }

        $document = $this->storeDocument($request, $uploader, $file, 'SWIFT', "swift/{$request->id}");

        $this->auditService->log(AuditAction::SWIFT_UPLOADED, $uploader, $document, [
            'request_id' => $request->id,
            'type' => 'SWIFT',
        ]);

        $this->workflowService->transition($request->fresh(), 'swift_upload', $uploader);

        return $document;
    }

    public function uploadSwiftPackage(
        ImportRequest $request,
        User $uploader,
        UploadedFile $swiftFile,
        UploadedFile $fxRequestFile,
        string $swiftReference
    ): array
    {
        $this->assertFileValid($swiftFile);
        $this->assertFileValid($fxRequestFile);

        if ($request->status !== RequestStatus::WAITING_FOR_SWIFT) {
            throw new DocumentException('SWIFT can only be uploaded when request is in WAITING_FOR_SWIFT status.');
        }

        if (!$uploader->hasPermission('swift.upload') || $uploader->bank_id !== $request->bank_id) {
            throw new DocumentException('Only authorized bank users can upload SWIFT documents.');
        }

        if (RequestDocument::query()->where('request_id', $request->id)->where('type', 'SWIFT')->exists()) {
            throw new DocumentException('SWIFT document already uploaded and cannot be replaced.');
        }

        if (RequestDocument::query()->where('request_id', $request->id)->where('type', 'FX_REQUEST')->exists()) {
            throw new DocumentException('FX request document already uploaded and cannot be replaced.');
        }

        /** @var array{swift: RequestDocument, fx_request: RequestDocument} $documents */
        $documents = DB::transaction(function () use ($request, $uploader, $swiftFile, $fxRequestFile, $swiftReference): array {
            $swiftDocument = $this->storeDocument($request, $uploader, $swiftFile, 'SWIFT', "swift/{$request->id}");
            $fxRequestDocument = $this->storeDocument($request, $uploader, $fxRequestFile, 'FX_REQUEST', "fx-request/{$request->id}");

            $this->auditService->log(AuditAction::SWIFT_UPLOADED, $uploader, $swiftDocument, [
                'request_id' => $request->id,
                'type' => 'SWIFT',
                'swift_reference' => $swiftReference,
            ]);

            $this->auditService->log(AuditAction::DOCUMENT_UPLOADED, $uploader, $fxRequestDocument, [
                'request_id' => $request->id,
                'type' => 'FX_REQUEST',
                'swift_reference' => $swiftReference,
            ]);

            $this->workflowService->transition($request->fresh(), 'swift_upload', $uploader, null, [
                'swift_reference' => $swiftReference,
                'swift_document_id' => $swiftDocument->id,
                'fx_request_document_id' => $fxRequestDocument->id,
            ]);

            return [
                'swift' => $swiftDocument,
                'fx_request' => $fxRequestDocument,
            ];
        });

        return $documents;
    }

    public function download(RequestDocument $document, User $user): StreamedResponse
    {
        Gate::forUser($user)->authorize('download', $document);

        $fullPath = 'private/'.$document->stored_path;
        if (!Storage::disk('local')->exists($fullPath)) {
            throw new DocumentException('Document file not found.');
        }

        $stream = Storage::disk('local')->readStream($fullPath);
        if ($stream === false) {
            throw new DocumentException('Unable to read document stream.');
        }

        $this->auditService->log(AuditAction::DOCUMENT_DOWNLOADED, $user, $document, [
            'request_id' => $document->request_id,
            'document_id' => $document->id,
            'document_type' => $document->type,
        ]);

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $document->original_filename, ['Content-Type' => $document->mime_type]);
    }

    public function delete(RequestDocument $document, User $user): void
    {
        $request = $document->request;

        if ($document->type !== 'REQUEST_DOC') {
            throw new DocumentException('Only regular request documents can be deleted.');
        }

        if (!$request->isEditable()) {
            throw new WorkflowLockedStateException('Documents can only be deleted while request is editable.');
        }

        $this->deleteStoredDocument($document);

        $documentId = $document->id;
        $document->delete();

        $this->auditService->log(AuditAction::REQUEST_UPDATED, $user, $request, [
            'action' => 'document_deleted',
            'document_id' => $documentId,
            'request_id' => $request->id,
        ]);
    }

    private function deleteStoredDocument(RequestDocument $document): void
    {
        $path = 'private/'.$document->stored_path;
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function storeDocument(ImportRequest $request, User $uploader, UploadedFile $file, string $type, string $folder, ?string $subType = null): RequestDocument
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $filename = Str::uuid().".{$extension}";
        $relativePath = "{$folder}/{$filename}";
        $storagePath = 'private/'.$folder;

        $checksum = hash_file('sha256', $file->getRealPath());
        if ($checksum === false) {
            throw new DocumentException('Failed to compute file checksum.');
        }

        $stored = Storage::disk('local')->putFileAs($storagePath, $file, $filename);
        if ($stored === false) {
            throw new DocumentException('Failed to store file on disk.');
        }

        try {
            return DB::transaction(function () use ($request, $uploader, $file, $type, $subType, $relativePath, $checksum): RequestDocument {
                return RequestDocument::query()->create([
                    'request_id' => $request->id,
                    'uploaded_by' => $uploader->id,
                    'type' => $type,
                    'document_sub_type' => $subType,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $relativePath,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $file->getSize() ?: 0,
                    'checksum' => $checksum,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete('private/'.$relativePath);
            throw $e;
        }
    }

    private function assertFileValid(UploadedFile $file): void
    {
        $allowed = config('documents.allowed_mime_types', []);
        $max = (int) config('documents.max_size_bytes', 10485760);

        $mime = $file->getMimeType();
        if (!$mime || !in_array($mime, $allowed, true)) {
            throw new DocumentException('Unsupported file type.');
        }

        if (($file->getSize() ?: 0) > $max) {
            throw new DocumentException('File exceeds maximum allowed size.');
        }
    }
}
