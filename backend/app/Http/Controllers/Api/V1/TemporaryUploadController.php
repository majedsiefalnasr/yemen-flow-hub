<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FieldType;
use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Exceptions\EngineException;
use App\Http\Controllers\Api\Controller;
use App\Jobs\ScanTemporaryUpload;
use App\Models\FieldDefinition;
use App\Models\TemporaryUpload;
use App\Models\WorkflowVersion;
use App\Services\Documents\EngineRequestDocumentIntegrityService;
use App\Services\Operations\OperationalAlertLogger;
use App\Services\Workflow\StagePermissionResolver;
use App\Support\RequestCreationGate;
use App\Support\UploadSizeLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TemporaryUploadController extends Controller
{
    public function __construct(
        private StagePermissionResolver $permissionResolver,
        private EngineRequestDocumentIntegrityService $documentIntegrity,
        private UploadSizeLimit $uploadSizeLimit,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.$this->uploadSizeLimit->maxKilobytes()],
            'upload_session_token' => ['required', 'string', 'max:64'],
            'workflow_version_id' => ['required', 'integer', 'exists:workflow_versions,id'],
            'field_id' => [
                'required',
                'integer',
                Rule::exists('field_definitions', 'id')
                    ->where('workflow_version_id', $request->input('workflow_version_id')),
            ],
        ]);

        if (! RequestCreationGate::userCanCreateRequests($request->user())) {
            throw EngineException::creationNotAllowedForOrganization();
        }

        $version = WorkflowVersion::findOrFail($validated['workflow_version_id']);

        if ($version->state !== WorkflowVersionState::PUBLISHED) {
            throw EngineException::versionNotPublished();
        }

        $field = FieldDefinition::query()->find($validated['field_id']);
        if ($field === null || $field->type !== FieldType::FILE) {
            throw EngineException::uploadFieldInvalid();
        }

        $initialStage = $version->stages()->where('is_initial', true)->first();

        if ($initialStage === null
            || ! $this->permissionResolver->userCanAccessStage($request->user(), $initialStage, StageAccessLevel::EXECUTE)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to upload files for this workflow.',
                'error_code' => 'WORKFLOW_FORBIDDEN',
            ], 403);
        }

        $file = $request->file('file');
        $token = Str::random(48);
        $path = $file->store('', 'private-tmp');

        if ($path === false) {
            return response()->json([
                'success' => false,
                'message' => 'Could not store the uploaded file. Please try again.',
                'error_code' => 'FILE_STORAGE_FAILED',
            ], 422);
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        if ($checksum === false) {
            Storage::disk('private-tmp')->delete($path);

            return response()->json([
                'success' => false,
                'message' => 'Could not verify the uploaded file. Please try again.',
                'error_code' => 'FILE_STORAGE_FAILED',
            ], 422);
        }

        try {
            $upload = TemporaryUpload::create([
                'token' => $token,
                'upload_session_token' => $validated['upload_session_token'],
                'user_id' => $request->user()->id,
                'organization_id' => $request->user()->organization_id,
                'bank_id' => $request->user()->bank_id,
                'workflow_version_id' => $version->id,
                'field_id' => $validated['field_id'],
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'checksum' => $checksum,
                'scan_status' => $this->documentIntegrity->scanStatusForNewUpload(),
                'expires_at' => now()->addHours((int) config('retention.temporary_upload_ttl_hours')),
            ]);
        } catch (\Throwable $e) {
            // Immediate compensation: file stored but the DB insert failed —
            // never leave an orphaned temp file relying on the scheduled sweep
            // for an immediately-detectable failure.
            Storage::disk('private-tmp')->delete($path);
            throw $e;
        }

        if ($upload->scan_status?->value === 'pending') {
            DB::afterCommit(function () use ($upload) {
                ScanTemporaryUpload::dispatch($upload->id);
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully.',
            'data' => [
                'token' => $upload->token,
                'expires_at' => $upload->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $upload = TemporaryUpload::query()->where('token', $token)->first();

        if ($upload === null || (int) $upload->user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        if ($upload->consumed_at === null) {
            $disk = Storage::disk('private-tmp');
            if ($disk->exists($upload->path) && ! $disk->delete($upload->path)) {
                OperationalAlertLogger::failure(
                    'temporary_upload_release',
                    new \RuntimeException("Failed to delete released temporary upload file: {$upload->path}"),
                    ['path' => $upload->path, 'temporary_upload_id' => $upload->id],
                );
            }
            $upload->delete();
        }

        return response()->json(['success' => true, 'message' => 'Upload released.']);
    }

    public function show(Request $request, string $token): JsonResponse
    {
        $upload = $this->authorizedUpload($request, $token);
        if ($upload === null) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->statusResource($upload)]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tokens' => ['sometimes', 'array', 'max:50'],
            'tokens.*' => ['string', 'max:64'],
        ]);
        $tokens = $validated['tokens'] ?? [];

        $uploads = TemporaryUpload::query()
            ->whereIn('token', $tokens)
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $uploads->map(fn (TemporaryUpload $u) => $this->statusResource($u))->values(),
        ]);
    }

    private function authorizedUpload(Request $request, string $token): ?TemporaryUpload
    {
        $upload = TemporaryUpload::query()->where('token', $token)->first();

        if ($upload === null) {
            return null;
        }

        $user = $request->user();
        if ((int) $upload->user_id !== (int) $user->id) {
            return null;
        }
        if ($upload->organization_id !== null && (int) $upload->organization_id !== (int) $user->organization_id) {
            return null;
        }
        if ($upload->bank_id !== null && (int) $upload->bank_id !== (int) $user->bank_id) {
            return null;
        }

        return $upload;
    }

    /** @return array<string, mixed> */
    private function statusResource(TemporaryUpload $upload): array
    {
        return [
            'token' => $upload->token,
            'scan_status' => $upload->scan_status?->value,
            'original_name' => $upload->original_name,
            'size' => $upload->size,
            'expires_at' => $upload->expires_at->toIso8601String(),
        ];
    }
}
