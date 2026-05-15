<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UploadDocumentRequest;
use App\Http\Requests\UploadRequestDocumentRequest;
use App\Http\Requests\UploadSwiftRequest;
use App\Http\Resources\DocumentResource;
use App\Models\ImportRequest;
use App\Models\RequestDocument;
use App\Services\Documents\DocumentService;
use App\Support\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documentService)
    {
    }

    #[OA\Post(
        path: '/api/documents/upload',
        tags: ['Documents'],
        summary: 'Upload a PDF document to a request (request_id in body)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['request_id', 'file'],
                    properties: [
                        new OA\Property(property: 'request_id', type: 'integer'),
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Document uploaded')]
    )]
    public function upload(UploadRequestDocumentRequest $request)
    {
        $importRequest = ImportRequest::findOrFail($request->validated('request_id'));

        $document = $this->documentService->uploadRequestDocument(
            $importRequest,
            $request->user(),
            $request->file('file')
        );

        return ApiResponse::success(new DocumentResource($document), 'Document uploaded successfully.', 201);
    }

    #[OA\Post(
        path: '/api/requests/{importRequest}/documents',
        tags: ['Documents'],
        summary: 'Upload request document',
        parameters: [
            new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')]
                )
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Document uploaded')]
    )]
    public function uploadRequestDocument(UploadDocumentRequest $request, ImportRequest $importRequest)
    {
        $document = $this->documentService->uploadRequestDocument(
            $importRequest,
            $request->user(),
            $request->file('file')
        );

        return ApiResponse::success(new DocumentResource($document), 'Document uploaded successfully.', 201);
    }

    #[OA\Post(
        path: '/api/workflow/{importRequest}/swift-upload',
        tags: ['Documents'],
        summary: 'Upload SWIFT document and trigger workflow transition',
        parameters: [
            new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')]
                )
            )
        ),
        responses: [new OA\Response(response: 201, description: 'SWIFT uploaded')]
    )]
    public function uploadSwift(UploadSwiftRequest $request, ImportRequest $importRequest)
    {
        $document = $this->documentService->uploadSwift(
            $importRequest,
            $request->user(),
            $request->file('file')
        );

        return ApiResponse::success(new DocumentResource($document), 'SWIFT uploaded successfully.', 201);
    }

    #[OA\Get(
        path: '/api/documents/{id}/download',
        tags: ['Documents'],
        summary: 'Download document file',
        responses: [new OA\Response(response: 200, description: 'File stream')]
    )]
    public function download(RequestDocument $document): StreamedResponse
    {
        return $this->documentService->download($document, request()->user());
    }

    #[OA\Delete(
        path: '/api/documents/{id}',
        tags: ['Documents'],
        summary: 'Delete request document',
        responses: [new OA\Response(response: 200, description: 'Document deleted')]
    )]
    public function destroy(RequestDocument $document)
    {
        $this->documentService->delete($document, request()->user());

        return ApiResponse::success((object) [], 'Document deleted successfully.');
    }
}
