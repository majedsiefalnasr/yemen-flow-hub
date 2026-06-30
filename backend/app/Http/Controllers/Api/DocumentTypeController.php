<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreDocumentTypeRequest;
use App\Http\Requests\UpdateDocumentTypeRequest;
use App\Http\Resources\DocumentTypeResource;
use App\Models\DocumentType;
use App\Support\ApiResponse;
use OpenApi\Attributes as OA;

class DocumentTypeController extends Controller
{
    #[OA\Get(path: '/api/document-types', tags: ['Documents'], summary: 'List document types', responses: [new OA\Response(response: 200, description: 'Document types retrieved')])]
    public function index()
    {
        return ApiResponse::success(DocumentTypeResource::collection(DocumentType::query()->orderBy('sort_order')->orderBy('id')->get()), 'Document types retrieved.');
    }

    #[OA\Post(
        path: '/api/document-types',
        tags: ['Documents'],
        summary: 'Create document type',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['slug', 'name_ar', 'name_en'],
                properties: [
                    new OA\Property(property: 'slug', type: 'string', maxLength: 255),
                    new OA\Property(property: 'name_ar', type: 'string', maxLength: 255),
                    new OA\Property(property: 'name_en', type: 'string', maxLength: 255),
                    new OA\Property(property: 'is_required', type: 'boolean'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'sort_order', type: 'integer', minimum: 0, maximum: 65535),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Document type created')]
    )]
    public function store(StoreDocumentTypeRequest $request)
    {
        if (! request()->user()->hasPermission('docrules.manage')) {
            return ApiResponse::forbidden();
        }

        $row = DocumentType::query()->create($request->validated());

        return ApiResponse::success(new DocumentTypeResource($row), 'Document type created successfully.', 201);
    }

    #[OA\Put(path: '/api/document-types/{id}', tags: ['Documents'], summary: 'Update document type', responses: [new OA\Response(response: 200, description: 'Document type updated')])]
    public function update(UpdateDocumentTypeRequest $request, DocumentType $documentType)
    {
        if (! request()->user()->hasPermission('docrules.manage')) {
            return ApiResponse::forbidden();
        }
        $documentType->update($request->validated());

        return ApiResponse::success(new DocumentTypeResource($documentType->refresh()), 'Document type updated successfully.');
    }

    #[OA\Delete(path: '/api/document-types/{id}', tags: ['Documents'], summary: 'Delete document type', responses: [new OA\Response(response: 200, description: 'Document type deleted')])]
    public function destroy(DocumentType $documentType)
    {
        if (! request()->user()->hasPermission('docrules.manage')) {
            return ApiResponse::forbidden();
        }
        $documentType->delete();

        return ApiResponse::success((object) [], 'Document type deleted successfully.');
    }
}
