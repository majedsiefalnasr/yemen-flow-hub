<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Enums\StageAccessLevel;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\FxConfirmationUploadRequest;
use App\Http\Requests\StoreEngineRequestRequest;
use App\Http\Resources\CustomsDeclarationResource;
use App\Http\Resources\EngineRequestResource;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\FieldDefinition;
use App\Models\FieldGroup;
use App\Models\WorkflowVersion;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Customs\EngineCustomsService;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Services\Workflow\DuplicateInvoiceChecker;
use App\Services\Workflow\DynamicFieldOptionsResolver;
use App\Services\Workflow\EngineClaimService;
use App\Services\Workflow\EngineRequestService;
use App\Services\Workflow\EngineTransitionService;
use App\Services\Workflow\StagePermissionResolver;
use App\Services\Workflow\WorkflowGraphService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EngineRequestController extends Controller
{
    public function __construct(
        private EngineRequestService $requestService,
        private EngineTransitionService $transitionService,
        private StagePermissionResolver $permissionResolver,
        private DuplicateInvoiceChecker $duplicateChecker,
        private WorkflowGraphService $graphService,
        private AuditService $auditService,
        private EngineNotificationDispatcher $notificationDispatcher,
        private EngineClaimService $claimService,
    ) {}

    // ── 18.5.1: Create ──────────────────────────────────────────────────

    public function store(StoreEngineRequestRequest $request): JsonResponse
    {
        $version = WorkflowVersion::findOrFail($request->validated('workflow_version_id'));

        $engineRequest = $this->requestService->create(
            $version,
            $request->validated(),
            $request->user(),
        );

        $response = [
            'success' => true,
            'message' => 'Request created successfully.',
            'data' => new EngineRequestResource($engineRequest),
        ];

        $invoiceNumber = $engineRequest->invoice_number;
        if ($invoiceNumber !== null) {
            $warning = $this->duplicateChecker->check($invoiceNumber, $engineRequest->id);
            if ($warning !== null) {
                $response['warnings'] = [$warning];
                $this->notificationDispatcher->afterDuplicateInvoice(
                    $engineRequest->id,
                    (string) $engineRequest->reference,
                    $invoiceNumber,
                    $warning['duplicates'],
                );
            }
        }

        return response()->json($response, 201);
    }

    public function availableWorkflows(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! app(PermissionService::class)->userHasCapability($user, 'requests', 'CREATE')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create requests.',
            ], 403);
        }

        $versions = WorkflowVersion::query()
            ->where('state', 'PUBLISHED')
            ->with('definition')
            ->get()
            ->filter(function (WorkflowVersion $version) use ($user): bool {
                $initialStage = $version->stages()->where('is_initial', true)->first();
                if ($initialStage === null) {
                    return false;
                }

                return $this->permissionResolver->userCanAccessStage($user, $initialStage, StageAccessLevel::EXECUTE);
            })
            ->map(fn (WorkflowVersion $version): array => [
                'id' => $version->definition->id,
                'code' => $version->definition->code,
                'name' => $version->definition->name,
                'version_id' => $version->id,
                'version_number' => $version->version_number,
            ])
            ->values();

        return response()->json(['data' => $versions]);
    }

    public function show(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $engineRequest->load(['currentStage', 'creator', 'bank', 'merchant', 'claimedBy']);

        return response()->json([
            'success' => true,
            'data' => new EngineRequestResource($engineRequest),
        ]);
    }

    public function formSchema(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $stage = $engineRequest->currentStage;
        $fields = FieldDefinition::query()
            ->where('workflow_version_id', $engineRequest->workflow_version_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $rulesByFieldId = $stage->stageFieldRules()->get()->keyBy('field_id');
        $groups = FieldGroup::query()
            ->where('workflow_version_id', $engineRequest->workflow_version_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $optionsResolver = app(DynamicFieldOptionsResolver::class);

        $fieldsByGroup = $fields->groupBy('field_group_id');

        $data = $groups->map(function ($group) use ($fieldsByGroup, $rulesByFieldId, $optionsResolver): array {
            $groupFields = ($fieldsByGroup->get($group->id) ?? collect())
                ->map(function ($field) use ($rulesByFieldId, $optionsResolver): array {
                    $rule = $rulesByFieldId->get($field->id);

                    return [
                        'id' => $field->id,
                        'key' => $field->key,
                        'label' => $field->label,
                        'type' => $field->type->value,
                        'placeholder' => $field->placeholder,
                        'help_text' => $field->help_text,
                        'default_value' => $field->default_value,
                        'min_value' => $field->min_value !== null ? (float) $field->min_value : null,
                        'max_value' => $field->max_value !== null ? (float) $field->max_value : null,
                        'min_length' => $field->min_length,
                        'max_length' => $field->max_length,
                        'regex_pattern' => $field->regex_pattern,
                        'options' => $field->options,
                        'dynamic_source' => $field->dynamic_source?->value,
                        'allowed_file_types' => $field->allowed_file_types,
                        'max_file_size' => $field->max_file_size,
                        'multiple' => (bool) $field->multiple,
                        'is_visible' => $rule?->is_visible ?? true,
                        'is_editable' => $rule?->is_editable ?? true,
                        'is_required' => $rule?->is_required ?? (bool) $field->is_required,
                        'dynamic_options' => $field->dynamic_source !== null ? $optionsResolver->resolve($field) : null,
                    ];
                })
                ->values();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'label' => $group->label,
                'sort_order' => $group->sort_order,
                'fields' => $groupFields,
            ];
        })->values();

        return response()->json(['data' => ['field_groups' => $data]]);
    }

    // ── 18.5.2: List (scoped & filtered) ─────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessibleStageIds = $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::VIEW);

        $query = EngineRequest::query()
            ->withStageEntry()
            ->forUser($user)
            ->whereIn('engine_requests.current_stage_id', $accessibleStageIds)
            ->with(['currentStage', 'bank', 'merchant', 'creator']);

        $this->applyFilters($query, $request);

        $page = $query->orderByDesc('engine_requests.created_at')
            ->orderBy('engine_requests.id')
            ->paginate($this->perPage($request));

        return $this->paginatedResponse($page);
    }

    // ── 18.5.3: My Queue (دوري) ──────────────────────────────────────────

    public function myQueue(Request $request): JsonResponse
    {
        $user = $request->user();
        $executeStageIds = $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::EXECUTE);

        $query = EngineRequest::query()
            ->withStageEntry()
            ->active()
            ->forUser($user)
            ->whereIn('engine_requests.current_stage_id', $executeStageIds)
            ->with(['currentStage', 'bank', 'merchant', 'creator']);

        $this->applyFilters($query, $request);

        // Default دوري priority: SLA-breached → nearest-to-breach → oldest-in-stage.
        $page = $query
            ->orderBySlaPriority()
            ->orderBy('engine_requests.id')
            ->paginate($this->perPage($request));

        return $this->paginatedResponse($page);
    }

    // ── 18.5.4: Execute Transition ───────────────────────────────────────

    public function executeAction(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);

        $validated = $request->validate([
            'transition_id' => ['required', 'integer', 'exists:workflow_transitions,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'data' => ['nullable', 'array'],
            'version' => ['required', 'integer'],
        ]);

        $result = $this->transitionService->execute(
            $engineRequest,
            $validated['transition_id'],
            $validated['comment'] ?? null,
            $validated['data'] ?? [],
            $validated['version'],
            $request->user(),
        );

        $response = [
            'success' => true,
            'message' => 'Transition executed successfully.',
            'data' => new EngineRequestResource($result),
        ];

        $invoiceNumber = $result->invoice_number;
        if ($invoiceNumber !== null) {
            $warning = $this->duplicateChecker->check($invoiceNumber, $result->id);
            if ($warning !== null) {
                $response['warnings'] = [$warning];
                $this->notificationDispatcher->afterDuplicateInvoice(
                    $result->id,
                    (string) $result->reference,
                    $invoiceNumber,
                    $warning['duplicates'],
                );
            }
        }

        return response()->json($response);
    }

    // ── 18.5.5: Save Draft ───────────────────────────────────────────────

    public function draft(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);

        $validated = $request->validate([
            'data' => ['required', 'array'],
            'version' => ['required', 'integer'],
        ]);

        $result = $this->transitionService->saveDraft(
            $engineRequest,
            $validated['data'],
            $validated['version'],
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft saved successfully.',
            'data' => new EngineRequestResource($result),
        ]);
    }

    // ── Stage Claim ───────────────────────────────────────────────────────

    public function claim(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);

        $updated = $this->claimService->claim($engineRequest, request()->user());
        $updated->load(['currentStage', 'claimedBy']);

        return response()->json([
            'success' => true,
            'data' => new EngineRequestResource($updated),
        ]);
    }

    public function heartbeatClaim(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);

        $updated = $this->claimService->heartbeat($engineRequest, request()->user());
        $updated->load(['currentStage', 'claimedBy']);

        return response()->json([
            'success' => true,
            'data' => new EngineRequestResource($updated),
        ]);
    }

    public function releaseClaim(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);

        $updated = $this->claimService->release($engineRequest, request()->user());
        $updated->load(['currentStage', 'claimedBy']);

        return response()->json([
            'success' => true,
            'data' => new EngineRequestResource($updated),
        ]);
    }

    // ── 18.5.6: Documents ────────────────────────────────────────────────

    public function uploadDocument(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
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

    public function listDocuments(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $docs = $engineRequest->documents()->with(['uploader', 'stage', 'field'])->get();

        return response()->json([
            'success' => true,
            'data' => $docs->map(fn ($d) => $this->documentResource($d)),
        ]);
    }

    public function downloadDocument(Request $request, EngineRequest $engineRequest, EngineRequestDocument $document): mixed
    {
        $this->authorize('view', $engineRequest);

        if ((int) $document->request_id !== (int) $engineRequest->id) {
            abort(404);
        }

        if (! Storage::disk('private')->exists($document->path)) {
            abort(404);
        }

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

    public function uploadSignedFx(FxConfirmationUploadRequest $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $declaration = app(EngineCustomsService::class)->uploadSignedFxDoc(
            $engineRequest,
            $request->user(),
            $request->file('signed_document')
        );

        return ApiResponse::success(
            new CustomsDeclarationResource($declaration->load(['issuer', 'engineRequest.bank'])),
            'تم رفع وثيقة المصارفة الموقعة بنجاح.'
        );
    }

    public function downloadCustomsDeclaration(Request $request, EngineRequest $engineRequest): StreamedResponse
    {
        $declaration = CustomsDeclaration::query()
            ->where('engine_request_id', $engineRequest->id)
            ->firstOrFail();

        $this->authorize('download', $declaration);

        abort_unless(
            $declaration->pdf_path !== null && Storage::disk('local')->exists('private/'.$declaration->pdf_path),
            404,
            'Customs declaration PDF not found.'
        );

        $filename = 'customs-declaration-'.$engineRequest->reference_number.'.pdf';

        return Storage::disk('local')->download('private/'.$declaration->pdf_path, $filename);
    }

    public function downloadSignedFxDoc(Request $request, EngineRequest $engineRequest): StreamedResponse
    {
        $declaration = CustomsDeclaration::query()
            ->where('engine_request_id', $engineRequest->id)
            ->firstOrFail();

        $this->authorize('downloadSignedFx', $declaration);

        abort_unless(
            $declaration->signed_fx_doc_path !== null && Storage::disk('local')->exists('private/'.$declaration->signed_fx_doc_path),
            404,
            'Signed FX confirmation document not found.'
        );

        $filename = 'signed-fx-confirmation-'.$engineRequest->reference_number.'.pdf';

        return Storage::disk('local')->download('private/'.$declaration->signed_fx_doc_path, $filename);
    }

    // ── 18.5.7: History & Graph ──────────────────────────────────────────

    public function history(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $entries = $engineRequest->history()
            ->with(['fromStage:id,code,name', 'toStage:id,code,name', 'performer:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $entries->map(fn ($e) => [
                'id' => $e->id,
                'from_stage' => $e->fromStage ? ['id' => $e->fromStage->id, 'code' => $e->fromStage->code, 'name' => $e->fromStage->name] : null,
                'to_stage' => $e->toStage ? ['id' => $e->toStage->id, 'code' => $e->toStage->code, 'name' => $e->toStage->name] : null,
                'action_code' => $e->action_code,
                'performed_by' => $e->performer ? ['id' => $e->performer->id, 'name' => $e->performer->name] : null,
                'comments' => $e->comments,
                'created_at' => $e->created_at?->toISOString(),
            ]),
        ]);
    }

    public function graph(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $engineRequest->load(['workflowVersion', 'history']);
        $graphData = $this->graphService->build($engineRequest->workflowVersion);

        $history = $engineRequest->history;

        $executedStageIds = $history
            ->pluck('to_stage_id')
            ->merge($history->pluck('from_stage_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $currentStageId = $engineRequest->current_stage_id;

        foreach ($graphData['nodes'] as &$node) {
            $node['state'] = match (true) {
                $node['id'] === $currentStageId => 'current',
                in_array($node['id'], $executedStageIds, true) => 'executed',
                default => 'possible',
            };
        }
        unset($node);

        $executedTransitionKeys = $history
            ->whereNotNull('from_stage_id')
            ->map(fn ($h) => "{$h->from_stage_id}-{$h->to_stage_id}")
            ->all();

        foreach ($graphData['edges'] as &$edge) {
            $key = "{$edge['from_stage_id']}-{$edge['to_stage_id']}";
            $edge['state'] = in_array($key, $executedTransitionKeys, true) ? 'executed' : 'possible';
        }
        unset($edge);

        return response()->json([
            'success' => true,
            'data' => $graphData,
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private const ALLOWED_STATUSES = ['ACTIVE', 'CLOSED', 'REJECTED'];

    private const ALLOWED_SLA_STATUSES = ['ok', 'nearing', 'breached'];

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('workflow_id')) {
            $query->whereHas('workflowVersion', fn ($q) => $q->where('workflow_definition_id', $request->integer('workflow_id')));
        }
        if ($request->filled('workflow_version_id')) {
            $query->where('engine_requests.workflow_version_id', $request->integer('workflow_version_id'));
        }
        if ($request->filled('stage_id')) {
            $query->where('engine_requests.current_stage_id', $request->integer('stage_id'));
        }
        if ($request->filled('bank_id')) {
            $query->where('engine_requests.bank_id', $request->integer('bank_id'));
        }
        if ($request->filled('merchant_id')) {
            $query->where('engine_requests.merchant_id', $request->integer('merchant_id'));
        }
        if ($request->filled('status') && in_array($request->string('status')->value(), self::ALLOWED_STATUSES, true)) {
            $query->where('engine_requests.status', $request->string('status'));
        }
        if ($request->filled('created_from')) {
            $query->whereDate('engine_requests.created_at', '>=', $request->date('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->whereDate('engine_requests.created_at', '<=', $request->date('created_to'));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q
                ->where('engine_requests.reference', 'like', $term)
                ->orWhere('engine_requests.invoice_number', 'like', $term));
        }
        if ($request->filled('sla_status') && in_array($request->string('sla_status')->value(), self::ALLOWED_SLA_STATUSES, true)) {
            $this->applySlaStatusFilter($query, $request->string('sla_status')->value());
        }
    }

    /**
     * Filters on derived SLA status using the stage-entry subselect + stage SLA window,
     * never a JSON scan. `ok` and `nearing` exclude requests with no SLA configured.
     * Expressions are epoch-second based so they run on both MySQL and SQLite.
     */
    private function applySlaStatusFilter($query, string $slaStatus): void
    {
        $deadline = EngineRequest::slaDeadlineEpochSql();
        $now = EngineRequest::nowEpochSql();
        // Nearing window = the final 20% of the SLA (at least 1 minute) before the deadline.
        $nearingWindow = 'MAX(1, CAST(current_stage.sla_duration_minutes * 0.2 AS INTEGER)) * 60';
        $threshold = "({$deadline}) - ({$nearingWindow})";

        match ($slaStatus) {
            'breached' => $query->whereNotNull('current_stage.sla_duration_minutes')
                ->whereRaw("({$deadline}) < ({$now})"),
            'nearing' => $query->whereNotNull('current_stage.sla_duration_minutes')
                ->whereRaw("({$deadline}) >= ({$now})")
                ->whereRaw("({$now}) >= ({$threshold})"),
            'ok' => $query->whereNotNull('current_stage.sla_duration_minutes')
                ->whereRaw("({$now}) < ({$threshold})"),
            default => null,
        };
    }

    private function perPage(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 25)));
    }

    private function paginatedResponse($page): JsonResponse
    {
        return response()->json([
            'data' => EngineRequestResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
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
            'uploaded_by' => $doc->relationLoaded('uploader') && $doc->uploader
                ? ['id' => $doc->uploader->id, 'name' => $doc->uploader->name]
                : $doc->uploaded_by,
            'created_at' => $doc->created_at?->toISOString(),
        ];
    }
}
