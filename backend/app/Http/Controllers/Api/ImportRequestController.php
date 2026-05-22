<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
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
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\DuplicateDetectionService;
use App\Services\Settings\AdminSettingsService;
use App\Services\Workflow\WorkflowService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ImportRequestController extends Controller
{
    public function __construct(
        private readonly DuplicateDetectionService $duplicateService,
        private readonly AdminSettingsService $settingsService,
        private readonly AuditService $auditService,
    ) {
    }
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

        $request->validate([
            'created_from'          => ['nullable', 'date'],
            'created_to'            => ['nullable', 'date'],
            'amount_min'            => ['nullable', 'numeric', 'min:0'],
            'amount_max'            => ['nullable', 'numeric', 'min:0', Rule::when(
                fn () => $request->filled('amount_min'),
                'gte:amount_min',
            )],
            'assigned_reviewer_id'  => ['nullable', 'integer'],
        ]);

        $statusTotals = $this->buildIndexQuery($request, false)
            ->reorder()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        $paginator = $this->buildIndexQuery($request)->paginate(20);

        return ApiResponse::success(
            [
                'data' => ImportRequestListResource::collection($paginator->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'status_totals' => $statusTotals,
                ],
            ],
            'Requests retrieved successfully.'
        );
    }

    private function buildIndexQuery(Request $request, bool $applyStatusFilter = true): Builder
    {
        $query = ImportRequest::query()
            ->with(['bank', 'merchant', 'claimedByUser'])
            ->forUser($request->user());

        return $this->applyIndexFilters($query, $request, $applyStatusFilter);
    }

    private function applyIndexFilters(Builder $query, Request $request, bool $applyStatusFilter = true): Builder
    {
        $query
            ->when($applyStatusFilter && $request->filled('status'), function (Builder $q) use ($request) {
                $statuses = array_filter(array_map('trim', explode(',', (string) $request->query('status'))));

                return $q->whereIn('status', $statuses);
            })
            ->when(
                $request->filled('bank_id') && $request->user()->isCbyUser(),
                fn (Builder $q) => $q->where('bank_id', $request->integer('bank_id'))
            )
            ->when(
                $request->filled('currency'),
                fn (Builder $q) => $q->where('currency', $request->string('currency')->toString())
            )
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $search = $request->string('search')->toString();

                return $q->where(function (Builder $inner) use ($search) {
                    $inner->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('merchant', function (Builder $merchantQuery) use ($search) {
                            $merchantQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when(
                $request->filled('created_from') || $request->filled('from_date'),
                fn (Builder $q) => $q->whereDate('created_at', '>=', $request->input('created_from') ?? $request->input('from_date'))
            )
            ->when(
                $request->filled('created_to') || $request->filled('to_date'),
                fn (Builder $q) => $q->whereDate('created_at', '<=', $request->input('created_to') ?? $request->input('to_date'))
            )
            ->when(
                $request->filled('amount_min'),
                fn (Builder $q) => $q->where('amount', '>=', $request->input('amount_min'))
            )
            ->when(
                $request->filled('amount_max'),
                fn (Builder $q) => $q->where('amount', '<=', $request->input('amount_max'))
            )
            ->when(
                $request->filled('assigned_reviewer_id'),
                function (Builder $q) use ($request) {
                    $reviewerId = $request->integer('assigned_reviewer_id');

                    if (! $request->user()->isCbyUser()) {
                        $inScope = User::where('id', $reviewerId)
                            ->where('bank_id', $request->user()->bank_id)
                            ->exists();
                        if (! $inScope) {
                            return $q;
                        }
                    }

                    return $q->where('reviewed_by', $reviewerId);
                }
            )
            ->latest('id');

        if ($request->user()->hasRole(UserRole::SUPPORT_COMMITTEE)) {
            $claimFilter = (string) $request->query('claim_filter', 'all');

            if ($claimFilter === 'available') {
                $query->where(function (Builder $q) {
                    $q->where('status', RequestStatus::SUPPORT_REVIEW_PENDING->value)
                        ->orWhere(function (Builder $inner) {
                            $inner->where('status', RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value)
                                ->whereNotNull('claimed_by')
                                ->where(function (Builder $expires) {
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

        return $query;
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
                    new OA\Property(property: 'goods_type', type: 'string', maxLength: 100, nullable: true),
                    new OA\Property(property: 'payment_terms', type: 'string', enum: ['LC', 'TT', 'CAD'], nullable: true),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'invoice_number', type: 'string', maxLength: 100, nullable: true),
                    new OA\Property(property: 'invoice_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'origin_country', type: 'string', maxLength: 100, nullable: true),
                    new OA\Property(property: 'arrival_port', type: 'string', maxLength: 100, nullable: true),
                    new OA\Property(property: 'shipping_port', type: 'string', maxLength: 255, nullable: true),
                    new OA\Property(property: 'customs_office', type: 'string', maxLength: 100, nullable: true),
                    new OA\Property(property: 'bl_number', type: 'string', maxLength: 100, nullable: true),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Request created')]
    )]
    public function store(StoreImportRequest $request)
    {
        $this->authorize('create', ImportRequest::class);

        $invoiceNumber = $request->validated('invoice_number');
        $duplicateCount = 0;

        if ($invoiceNumber) {
            $duplicates = $this->duplicateService->findDuplicatesForInvoice($invoiceNumber);
            $duplicateCount = $duplicates->count();

            if ($duplicateCount > 0) {
                $policy = $this->settingsService->getSetting('duplicate_invoice_policy');
                if ($policy === 'block') {
                    return ApiResponse::error(
                        'رقم الفاتورة مكرر في طلبات أخرى - يرجى المراجعة',
                        ['invoice_number' => ['رقم الفاتورة مكرر في طلبات أخرى - يرجى المراجعة']],
                        422
                    );
                }
            }
        }

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

        $this->auditService->log(
            AuditAction::REQUEST_CREATED,
            $request->user(),
            $importRequest,
            array_filter([
                'notes' => $duplicateCount > 0 ? ['duplicate_count' => $duplicateCount] : null,
            ])
        );

        return ApiResponse::success(new ImportRequestResource($importRequest->load(ImportRequestResource::baseRelations())), 'Draft request created.', 201);
    }

    #[OA\Get(
        path: '/api/requests/{id}',
        tags: ['Import Requests'],
        summary: 'Show request details',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Request retrieved')]
    )]
    public function show(Request $request, ImportRequest $importRequest)
    {
        $this->authorize('view', $importRequest);

        $resource = new ImportRequestResource($importRequest->load(ImportRequestResource::detailRelations()));
        $data = $resource->resolve($request);

        $role = $request->user()->role;
        $isFullAuditor = in_array($role, [UserRole::CBY_ADMIN, UserRole::SUPPORT_COMMITTEE], true);
        $isBankScoped = in_array($role, [UserRole::BANK_REVIEWER, UserRole::BANK_ADMIN], true);

        if ($isFullAuditor || $isBankScoped) {
            $duplicates = $importRequest->invoice_number
                ? $this->duplicateService->findDuplicatesForInvoice($importRequest->invoice_number, $importRequest->id)
                : collect();

            if ($isFullAuditor) {
                $data['duplicate_warnings'] = $duplicates->map(fn ($d) => [
                    'id' => $d->id,
                    'reference_number' => $d->reference_number,
                    'bank_id' => $d->bank_id,
                    'bank_name' => $d->bank?->name,
                    'amount' => (float) $d->amount,
                    'currency' => is_object($d->currency) ? $d->currency->value : $d->currency,
                    'created_at' => $d->created_at?->toISOString(),
                    'status' => is_object($d->status) ? $d->status->value : $d->status,
                ])->values()->all();
            } else {
                // Bank-scoped: count + bank names only
                $data['duplicate_warnings'] = $duplicates->map(fn ($d) => [
                    'bank_name' => $d->bank?->name,
                ])->values()->all();
            }
        }
        // DATA_ENTRY: field omitted entirely

        return ApiResponse::success($data, 'Request retrieved successfully.');
    }

    #[OA\Put(
        path: '/api/requests/{id}',
        tags: ['Import Requests'],
        summary: 'Update request',
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
                    new OA\Property(property: 'goods_type', type: 'string', maxLength: 100, nullable: true),
                    new OA\Property(property: 'payment_terms', type: 'string', enum: ['LC', 'TT', 'CAD'], nullable: true),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'invoice_number', type: 'string', maxLength: 100, nullable: true),
                    new OA\Property(property: 'invoice_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'origin_country', type: 'string', maxLength: 100, nullable: true),
                    new OA\Property(property: 'arrival_port', type: 'string', maxLength: 100, nullable: true),
                    new OA\Property(property: 'shipping_port', type: 'string', maxLength: 255, nullable: true),
                    new OA\Property(property: 'customs_office', type: 'string', maxLength: 100, nullable: true),
                    new OA\Property(property: 'bl_number', type: 'string', maxLength: 100, nullable: true),
                ]
            )
        ),
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

        return ApiResponse::success(new ImportRequestResource($importRequest->refresh()->load(ImportRequestResource::baseRelations())), 'Request updated successfully.');
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
            StageHistoryResource::collection($importRequest->stageHistory()->with('actor')->oldest('id')->get()),
            'History retrieved successfully.'
        );
    }

    public function clone(Request $request, ImportRequest $importRequest, WorkflowService $workflowService)
    {
        $this->authorize('clone', $importRequest);

        $cloned = $workflowService->cloneRequest($importRequest, $request->user());

        return ApiResponse::success(
            new ImportRequestResource($cloned->load(ImportRequestResource::baseRelations())),
            'Request cloned successfully.',
            201
        );
    }
}
