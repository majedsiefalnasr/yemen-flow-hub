<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Exceptions\WorkflowImmutableStateException;
use App\Exceptions\WorkflowLockedStateException;
use App\Http\Requests\StoreImportRequest;
use App\Http\Requests\UpdateImportRequest;
use App\Http\Resources\ImportRequestListResource;
use App\Http\Resources\ImportRequestResource;
use App\Http\Resources\StageHistoryResource;
use App\Models\ImportRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use OpenApi\Attributes as OA;

class ImportRequestController extends Controller
{
    #[OA\Get(
        path: '/api/requests',
        tags: ['Import Requests'],
        summary: 'List requests',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Requests retrieved')]
    )]
    public function index(Request $request)
    {
        $this->authorize('viewAny', ImportRequest::class);

        $query = ImportRequest::query()
            ->with(['bank', 'merchant', 'claimedByUser'])
            ->forUser($request->user())
            ->when($request->filled('status'), function ($q) use ($request) {
                $statuses = array_filter(array_map('trim', explode(',', (string) $request->query('status'))));
                return $q->whereIn('status', $statuses);
            })
            ->when($request->filled('bank_id') && $request->user()->isCbyUser(), fn ($q) => $q->where('bank_id', $request->integer('bank_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                return $q->where(function ($inner) use ($search) {
                    $inner->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('from_date')->toString()))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('to_date')->toString()))
            ->latest('id');

        if ($request->user()->hasRole(UserRole::SUPPORT_COMMITTEE)) {
            $claimFilter = (string) $request->query('claim_filter', 'all');

            if ($claimFilter === 'available') {
                $query->where(function ($q) {
                    $q->where('status', RequestStatus::SUPPORT_REVIEW_PENDING->value)
                        ->orWhere(function ($inner) {
                            $inner->where('status', RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value)
                                ->whereNotNull('claimed_by')
                                ->where(function ($expires) {
                                    $expires->whereNull('claim_expires_at')
                                        ->orWhere('claim_expires_at', '<=', now());
                                });
                        });
                });
            } elseif ($claimFilter === 'mine') {
                $query->where('status', RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value)
                    ->where('claimed_by', $request->user()->id)
                    ->whereNotNull('claim_expires_at')
                    ->where('claim_expires_at', '>', now());
            }
        }

        return ApiResponse::success(
            ImportRequestListResource::collection($query->paginate(20)),
            'Requests retrieved successfully.'
        );
    }

    #[OA\Post(
        path: '/api/requests',
        tags: ['Import Requests'],
        summary: 'Create draft request',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['merchant_id', 'currency', 'amount', 'supplier_name', 'goods_description', 'port_of_entry'],
                properties: [
                    new OA\Property(property: 'merchant_id', type: 'integer'),
                    new OA\Property(property: 'currency', type: 'string', enum: ['USD', 'EUR', 'SAR', 'AED', 'CNY']),
                    new OA\Property(property: 'amount', type: 'number', format: 'float'),
                    new OA\Property(property: 'supplier_name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'goods_description', type: 'string'),
                    new OA\Property(property: 'port_of_entry', type: 'string', maxLength: 255),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Request created')]
    )]
    public function store(StoreImportRequest $request)
    {
        $this->authorize('create', ImportRequest::class);

        App::instance('workflow.transition.active', true);
        try {
            $importRequest = ImportRequest::query()->create([
                ...$request->validated(),
                'bank_id' => $request->user()->bank_id,
                'created_by' => $request->user()->id,
                'status' => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            App::offsetUnset('workflow.transition.active');
        }

        return ApiResponse::success(new ImportRequestResource($importRequest->load(['bank', 'merchant', 'claimedByUser'])), 'Draft request created.', 201);
    }

    #[OA\Get(
        path: '/api/requests/{id}',
        tags: ['Import Requests'],
        summary: 'Show request details',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Request retrieved')]
    )]
    public function show(ImportRequest $importRequest)
    {
        $this->authorize('view', $importRequest);

        return ApiResponse::success(
            new ImportRequestResource($importRequest->load([
                'bank',
                'merchant',
                'claimedByUser',
                'documents.uploader',
                'issuedCustomsDeclaration.issuer',
            ])),
            'Request retrieved successfully.'
        );
    }

    #[OA\Put(
        path: '/api/requests/{id}',
        tags: ['Import Requests'],
        summary: 'Update request',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Request updated')]
    )]
    public function update(UpdateImportRequest $request, ImportRequest $importRequest)
    {
        $this->authorize('update', $importRequest);

        $status = $importRequest->status;

        abort_if($status === null, 500, 'Request has no status — data integrity error.');

        if ($status->isTerminal()) {
            throw new WorkflowImmutableStateException($status);
        }

        if (!$importRequest->isEditable()) {
            throw new WorkflowLockedStateException();
        }

        $importRequest->update([
            ...$request->validated(),
            'last_updated_by' => $request->user()->id,
        ]);

        return ApiResponse::success(new ImportRequestResource($importRequest->refresh()->load(['bank', 'merchant', 'claimedByUser'])), 'Request updated successfully.');
    }

    #[OA\Delete(
        path: '/api/requests/{id}',
        tags: ['Import Requests'],
        summary: 'Delete draft request',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Request deleted')]
    )]
    public function destroy(Request $request, ImportRequest $importRequest)
    {
        $this->authorize('delete', $importRequest);

        $status = $importRequest->status;

        abort_if($status === null, 500, 'Request has no status — data integrity error.');

        if ($status->isTerminal()) {
            throw new WorkflowImmutableStateException($status);
        }

        if ($status !== RequestStatus::DRAFT) {
            throw new WorkflowLockedStateException('Request can only be deleted in DRAFT status.');
        }

        $importRequest->delete();

        return ApiResponse::success((object) [], 'Request deleted successfully.');
    }

    #[OA\Get(
        path: '/api/requests/{id}/history',
        tags: ['Workflow'],
        summary: 'Request stage history',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'History retrieved')]
    )]
    public function history(ImportRequest $importRequest)
    {
        $this->authorize('view', $importRequest);

        return ApiResponse::success(
            StageHistoryResource::collection($importRequest->stageHistory()->latest('id')->get()),
            'History retrieved successfully.'
        );
    }
}
