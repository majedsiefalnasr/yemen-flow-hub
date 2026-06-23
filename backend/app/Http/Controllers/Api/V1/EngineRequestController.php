<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Enums\StageAccessLevel;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreEngineRequestRequest;
use App\Http\Resources\EngineRequestResource;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\WorkflowVersion;
use App\Services\Audit\AuditService;
use App\Services\Workflow\DuplicateInvoiceChecker;
use App\Services\Workflow\EngineRequestService;
use App\Services\Workflow\EngineTransitionService;
use App\Services\Workflow\StagePermissionResolver;
use App\Services\Workflow\WorkflowGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EngineRequestController extends Controller
{
    public function __construct(
        private EngineRequestService $requestService,
        private EngineTransitionService $transitionService,
        private StagePermissionResolver $permissionResolver,
        private DuplicateInvoiceChecker $duplicateChecker,
        private WorkflowGraphService $graphService,
        private AuditService $auditService,
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
            }
        }

        return response()->json($response, 201);
    }

    public function show(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $engineRequest->load(['currentStage', 'creator', 'bank', 'merchant']);

        return response()->json([
            'success' => true,
            'data' => new EngineRequestResource($engineRequest),
        ]);
    }

    // ── 18.5.2: List (scoped & filtered) ─────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessibleStageIds = $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::VIEW);

        $query = EngineRequest::query()
            ->forUser($user)
            ->whereIn('current_stage_id', $accessibleStageIds)
            ->with(['currentStage', 'bank', 'merchant', 'creator']);

        $this->applyFilters($query, $request);

        $page = $query->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 25));

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

    // ── 18.5.3: My Queue (دوري) ──────────────────────────────────────────

    public function myQueue(Request $request): JsonResponse
    {
        $user = $request->user();
        $executeStageIds = $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::EXECUTE);

        $query = EngineRequest::query()
            ->active()
            ->forUser($user)
            ->whereIn('current_stage_id', $executeStageIds)
            ->with(['currentStage', 'bank', 'merchant', 'creator']);

        $this->applyFilters($query, $request);

        $page = $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 25));

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

    // ── 18.5.4: Execute Transition ───────────────────────────────────────

    public function executeAction(Request $request, EngineRequest $engineRequest): JsonResponse
    {
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
            }
        }

        return response()->json($response);
    }

    // ── 18.5.5: Save Draft ───────────────────────────────────────────────

    public function draft(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $validated = $request->validate([
            'data' => ['required', 'array'],
            'version' => ['required', 'integer'],
        ]);

        $result = $this->transitionService->saveDraft(
            $engineRequest,
            $validated['data'],
            $validated['version'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft saved successfully.',
            'data' => new EngineRequestResource($result),
        ]);
    }

    // ── 18.5.6: Documents ────────────────────────────────────────────────

    public function uploadDocument(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'field_id' => ['nullable', 'integer', 'exists:field_definitions,id'],
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

    public function downloadDocument(EngineRequest $engineRequest, EngineRequestDocument $document): mixed
    {
        $this->authorize('view', $engineRequest);

        if ((int) $document->request_id !== (int) $engineRequest->id) {
            abort(404);
        }

        $this->auditService->log(
            AuditAction::DOCUMENT_DOWNLOADED,
            auth()->user(),
            $document,
            ['request_id' => $engineRequest->id],
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
            AuditAction::DOCUMENT_DOWNLOADED,
            $request->user(),
            $document,
            ['request_id' => $engineRequest->id, 'action' => 'delete'],
        );

        return response()->json(['success' => true, 'message' => 'Document deleted.']);
    }

    // ── 18.5.7: History & Graph ──────────────────────────────────────────

    public function history(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $entries = $engineRequest->history()
            ->with(['fromStage:id,code,name', 'toStage:id,code,name', 'performer:id,name'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $entries->map(fn ($e) => [
                'id' => $e->id,
                'from_stage' => $e->fromStage ? ['id' => $e->fromStage->id, 'code' => $e->fromStage->code, 'name' => $e->fromStage->name] : null,
                'to_stage' => ['id' => $e->toStage->id, 'code' => $e->toStage->code, 'name' => $e->toStage->name],
                'action_code' => $e->action_code,
                'performed_by' => ['id' => $e->performer->id, 'name' => $e->performer->name],
                'comments' => $e->comments,
                'created_at' => $e->created_at?->toISOString(),
            ]),
        ]);
    }

    public function graph(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $engineRequest->load('workflowVersion');
        $graphData = $this->graphService->build($engineRequest->workflowVersion);

        $executedStageIds = $engineRequest->history()
            ->pluck('to_stage_id')
            ->merge($engineRequest->history()->pluck('from_stage_id'))
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

        $executedTransitionIds = $engineRequest->history()
            ->whereNotNull('from_stage_id')
            ->get(['from_stage_id', 'to_stage_id'])
            ->map(fn ($h) => "{$h->from_stage_id}-{$h->to_stage_id}")
            ->all();

        foreach ($graphData['edges'] as &$edge) {
            $key = "{$edge['from_stage_id']}-{$edge['to_stage_id']}";
            $edge['state'] = in_array($key, $executedTransitionIds, true) ? 'executed' : 'possible';
        }

        return response()->json([
            'success' => true,
            'data' => $graphData,
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('workflow_id')) {
            $query->whereHas('workflowVersion', fn ($q) => $q->where('workflow_definition_id', $request->integer('workflow_id')));
        }
        if ($request->filled('workflow_version_id')) {
            $query->where('workflow_version_id', $request->integer('workflow_version_id'));
        }
        if ($request->filled('stage_id')) {
            $query->where('current_stage_id', $request->integer('stage_id'));
        }
        if ($request->filled('bank_id')) {
            $query->where('bank_id', $request->integer('bank_id'));
        }
        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->integer('merchant_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->date('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->date('created_to'));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q
                ->where('reference', 'like', $term)
                ->orWhere('invoice_number', 'like', $term));
        }
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
