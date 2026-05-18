<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Resources\CustomsDeclarationResource;
use App\Models\CustomsDeclaration;
use App\Models\ImportRequest;
use App\Services\Customs\CustomsService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomsController extends Controller
{
    public function __construct(private readonly CustomsService $customsService)
    {
    }

    #[OA\Post(
        path: '/api/customs/{importRequest}/generate',
        tags: ['Customs'],
        summary: 'Generate customs declaration and PDF',
        parameters: [
            new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 201, description: 'Customs declaration generated')]
    )]
    public function generate(ImportRequest $importRequest)
    {
        $this->authorize('view', $importRequest);

        $declaration = $this->customsService->generate($importRequest, request()->user());

        return ApiResponse::success(
            new CustomsDeclarationResource($declaration->load(['issuer', 'request.bank'])),
            'Customs declaration generated successfully.',
            201
        );
    }

    #[OA\Get(
        path: '/api/customs/{id}',
        tags: ['Customs'],
        summary: 'Get customs declaration metadata',
        responses: [new OA\Response(response: 200, description: 'Customs declaration retrieved')]
    )]
    public function show(CustomsDeclaration $customsDeclaration)
    {
        $this->authorize('view', $customsDeclaration->request);

        return ApiResponse::success(
            new CustomsDeclarationResource($customsDeclaration->load(['issuer', 'request.bank'])),
            'Customs declaration retrieved.'
        );
    }

    #[OA\Get(
        path: '/api/customs/{id}/download',
        tags: ['Customs'],
        summary: 'Download customs declaration PDF',
        responses: [new OA\Response(response: 200, description: 'PDF stream')]
    )]
    public function download(CustomsDeclaration $customsDeclaration): StreamedResponse
    {
        return $this->customsService->getPdfStream($customsDeclaration, request()->user());
    }

    #[OA\Get(
        path: '/api/requests/{importRequest}/customs-preview',
        tags: ['Customs'],
        summary: 'Get customs declaration for a request (preview)',
        parameters: [
            new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Customs declaration data for preview'),
            new OA\Response(response: 403, description: 'Access denied'),
            new OA\Response(response: 404, description: 'No customs declaration for this request'),
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

        if (!$canAttemptPreview) {
            throw new AuthorizationException();
        }

        $declaration = $importRequest->customsDeclaration()->first();

        if ($declaration === null) {
            abort(404, 'No customs declaration found for this request.');
        }

        $this->authorize('download', $declaration);

        return ApiResponse::success(
            new CustomsDeclarationResource($declaration->load(['issuer', 'request.bank'])),
            'Customs declaration retrieved for preview.'
        );
    }
}
