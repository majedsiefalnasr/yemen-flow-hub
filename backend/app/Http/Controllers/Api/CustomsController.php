<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Requests\FxConfirmationUploadRequest;
use App\Http\Resources\CustomsDeclarationResource;
use App\Models\CustomsDeclaration;
use App\Models\ImportRequest;
use App\Services\Customs\CustomsService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomsController extends Controller
{
    public function __construct(private readonly CustomsService $customsService) {}

    #[OA\Post(
        path: '/api/customs/{importRequest}/generate',
        tags: ['Customs'],
        summary: 'Generate external FX confirmation document and PDF',
        parameters: [
            new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 201, description: 'External FX confirmation document generated')]
    )]
    public function generate(ImportRequest $importRequest)
    {
        $this->authorize('view', $importRequest);

        $declaration = $this->customsService->generate($importRequest, request()->user());

        return ApiResponse::success(
            new CustomsDeclarationResource($declaration->load(['issuer', 'request.bank'])),
            'External FX confirmation document generated successfully.',
            201
        );
    }

    #[OA\Get(
        path: '/api/customs/{id}',
        tags: ['Customs'],
        summary: 'Get external FX confirmation metadata',
        responses: [new OA\Response(response: 200, description: 'External FX confirmation document retrieved')]
    )]
    public function show(CustomsDeclaration $customsDeclaration)
    {
        $this->authorize('view', $customsDeclaration->request);

        return ApiResponse::success(
            new CustomsDeclarationResource($customsDeclaration->load(['issuer', 'request.bank'])),
            'External FX confirmation document retrieved.'
        );
    }

    #[OA\Get(
        path: '/api/customs/{id}/download',
        tags: ['Customs'],
        summary: 'Download external FX confirmation PDF',
        responses: [new OA\Response(response: 200, description: 'PDF stream')]
    )]
    public function download(CustomsDeclaration $customsDeclaration): StreamedResponse
    {
        return $this->customsService->getPdfStream($customsDeclaration, request()->user());
    }

    #[OA\Post(
        path: '/api/requests/{importRequest}/fx-confirmation-upload',
        tags: ['Customs'],
        summary: 'Upload signed FX confirmation PDF',
        parameters: [
            new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Signed FX confirmation uploaded')]
    )]
    public function uploadSignedFx(FxConfirmationUploadRequest $request, ImportRequest $importRequest)
    {
        $this->authorize('view', $importRequest);

        $this->customsService->uploadSignedFxDoc(
            $importRequest,
            $request->user(),
            $request->file('signed_document')
        );

        return ApiResponse::success(null, 'تم رفع وثيقة المصارفة الموقعة بنجاح.');
    }

    #[OA\Get(
        path: '/api/customs/{id}/signed-fx-download',
        tags: ['Customs'],
        summary: 'Download the signed FX confirmation PDF uploaded by the director',
        responses: [
            new OA\Response(response: 200, description: 'PDF stream'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'No signed document uploaded yet'),
        ]
    )]
    public function downloadSignedFx(CustomsDeclaration $customsDeclaration): StreamedResponse
    {
        $this->authorize('downloadSignedFx', $customsDeclaration);

        if (! $customsDeclaration->signed_fx_doc_path) {
            abort(404, 'No signed FX confirmation document has been uploaded yet.');
        }

        $fullPath = 'private/'.$customsDeclaration->signed_fx_doc_path;
        if (! Storage::disk('local')->exists($fullPath)) {
            abort(404, 'Signed FX confirmation file not found on disk.');
        }

        $stream = Storage::disk('local')->readStream($fullPath);

        $filename = 'fx-confirmation-signed-'
            .($customsDeclaration->request_id ?? $customsDeclaration->id)
            .'.pdf';

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    #[OA\Get(
        path: '/api/requests/{importRequest}/customs-preview',
        tags: ['Customs'],
        summary: 'Get external FX confirmation for a request (preview)',
        parameters: [
            new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'External FX confirmation data for preview'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'No external FX confirmation document for this request'),
        ]
    )]
    public function preview(ImportRequest $importRequest)
    {
        $user = request()->user();
        $canAttemptPreview = match ($user->role) {
            UserRole::COMMITTEE_DIRECTOR,
            UserRole::CBY_ADMIN => true,
            UserRole::BANK_REVIEWER => $user->bank_id !== null && $user->bank_id === $importRequest->bank_id,
            default => false,
        };

        if (! $canAttemptPreview) {
            throw new AuthorizationException;
        }

        $declaration = $importRequest->customsDeclaration()->first();

        if ($declaration === null) {
            abort(404, 'No external FX confirmation document found for this request.');
        }

        $this->authorize('download', $declaration);

        return ApiResponse::success(
            new CustomsDeclarationResource($declaration->load(['issuer', 'request.bank'])),
            'External FX confirmation document retrieved for preview.'
        );
    }
}
