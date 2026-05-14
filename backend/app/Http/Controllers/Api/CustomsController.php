<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CustomsDeclarationResource;
use App\Models\CustomsDeclaration;
use App\Models\ImportRequest;
use App\Services\Customs\CustomsService;
use App\Support\ApiResponse;
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
        if (!request()->user()->hasPermission('customs.issue')) {
            return ApiResponse::forbidden();
        }

        $declaration = $this->customsService->generate($importRequest, request()->user());

        return ApiResponse::success(new CustomsDeclarationResource($declaration), 'Customs declaration generated successfully.', 201);
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

        return ApiResponse::success(new CustomsDeclarationResource($customsDeclaration), 'Customs declaration retrieved.');
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
}
