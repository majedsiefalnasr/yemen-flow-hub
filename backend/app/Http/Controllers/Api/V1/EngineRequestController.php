<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StageAccessLevel;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreEngineRequestRequest;
use App\Http\Resources\EngineRequestResource;
use App\Models\EngineRequest;
use App\Models\FieldGroup;
use App\Models\User;
use App\Models\WorkflowVersion;
use App\Services\Authorization\DataScope;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Services\Workflow\DuplicateInvoiceChecker;
use App\Services\Workflow\DynamicFieldOptionsResolver;
use App\Services\Workflow\EngineRequestService;
use App\Services\Workflow\EngineTransitionService;
use App\Services\Workflow\StageFieldOutputFilter;
use App\Services\Workflow\StagePermissionResolver;
use App\Services\Workflow\WorkflowGraphService;
use App\Support\EngineRequestListQuery;
use App\Support\RequestCreationGate;
use App\Support\RoleCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngineRequestController extends Controller
{
    public function __construct(
        private EngineRequestService $requestService,
        private EngineTransitionService $transitionService,
        private StagePermissionResolver $permissionResolver,
        private DuplicateInvoiceChecker $duplicateChecker,
        private WorkflowGraphService $graphService,
        private EngineNotificationDispatcher $notificationDispatcher,
        private EngineRequestListQuery $listQuery,
        private StageFieldOutputFilter $outputFilter,
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
                $originalDuplicates = $warning['duplicates'];
                $warning = $this->maskDuplicates($warning, $request->user());
                $response['warnings'] = [$warning];
                $this->notificationDispatcher->afterDuplicateInvoice(
                    $engineRequest->id,
                    (string) $engineRequest->reference,
                    $invoiceNumber,
                    $originalDuplicates,
                );
            }
        }

        return response()->json($response, 201);
    }

    public function availableWorkflows(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! RequestCreationGate::userCanCreateRequests($user)) {
            return response()->json(['data' => []]);
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

        $engineRequest->load(['currentStage', 'creator', 'bank', 'merchant', 'claimedBy', 'workflowVersion.definition', 'customsDeclaration.issuer']);

        $response = [
            'success' => true,
            'data' => new EngineRequestResource($engineRequest),
        ];

        // Surface a duplicate-invoice warning on the detail view so a reviewer
        // sees the conflict without re-running a transition. Read-only: no
        // notification is dispatched here (that happens on create/transition).
        $invoiceNumber = $engineRequest->invoice_number;
        if ($invoiceNumber !== null) {
            $warning = $this->duplicateChecker->check($invoiceNumber, $engineRequest->id);
            if ($warning !== null) {
                $warning = $this->maskDuplicates($warning, request()->user());
                $response['warnings'] = [$warning];
            }
        }

        return response()->json($response);
    }

    public function formSchema(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $stage = $engineRequest->currentStage;
        $fields = $this->outputFilter->visibleFieldsForStage($engineRequest->workflow_version_id, $stage);
        $rulesByFieldId = $stage->stageFieldRules()->get()->keyBy('field_id');
        $groups = FieldGroup::query()
            ->where('workflow_version_id', $engineRequest->workflow_version_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $optionsResolver = app(DynamicFieldOptionsResolver::class);

        $fieldsByGroup = $fields->groupBy('field_group_id');

        $data = $groups->map(function ($group) use ($fieldsByGroup, $rulesByFieldId, $optionsResolver, $request, $engineRequest): array {
            $groupFields = ($fieldsByGroup->get($group->id) ?? collect())
                ->map(function ($field) use ($rulesByFieldId, $optionsResolver, $request, $engineRequest): array {
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
                        'dynamic_options' => $field->dynamic_source !== null
                            ? $optionsResolver->resolve(
                                $field,
                                $request->user(),
                                $engineRequest,
                                $engineRequest->data[$field->key] ?? null,
                            )
                            : null,
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
            ->with(['currentStage', 'bank', 'merchant', 'creator', 'workflowVersion.definition']);

        if (! $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            $query
                ->forUser($user)
                ->whereIn('engine_requests.current_stage_id', $accessibleStageIds);
        }

        $this->listQuery->applyFilters($query, $request);

        $page = $query->orderByDesc('engine_requests.created_at')
            ->orderBy('engine_requests.id')
            ->paginate($this->listQuery->perPage($request));

        return $this->listQuery->paginatedResponse($page);
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
            ->with(['currentStage', 'bank', 'merchant', 'creator', 'claimedBy', 'workflowVersion.definition']);

        $this->listQuery->applyFilters($query, $request);

        // Default دوري priority: SLA-breached → nearest-to-breach → oldest-in-stage.
        $page = $query
            ->orderBySlaPriority()
            ->orderBy('engine_requests.id')
            ->paginate($this->listQuery->perPage($request));

        return $this->listQuery->paginatedResponse($page);
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
                $originalDuplicates = $warning['duplicates'];
                $warning = $this->maskDuplicates($warning, $request->user());
                $response['warnings'] = [$warning];
                $this->notificationDispatcher->afterDuplicateInvoice(
                    $result->id,
                    (string) $result->reference,
                    $invoiceNumber,
                    $originalDuplicates,
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

    public function abandon(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('abandon', $engineRequest);

        $validated = $request->validate([
            'version' => ['required', 'integer'],
        ]);

        $result = $this->transitionService->abandonDraft(
            $engineRequest,
            $validated['version'],
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft abandoned successfully.',
            'data' => new EngineRequestResource($result),
        ]);
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

    public function graph(Request $request, EngineRequest $engineRequest): JsonResponse
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

        // Stages the current user can execute, scoped to this version, so the UI can
        // mark non-current "دورك" (your turn) stages on the process rail.
        $versionStageIds = array_column($graphData['nodes'], 'id');
        $executeStageIds = array_values(array_intersect(
            $this->permissionResolver->accessibleStageIds($request->user(), StageAccessLevel::EXECUTE),
            $versionStageIds,
        ));

        return response()->json([
            'success' => true,
            'data' => $graphData + ['execute_stage_ids' => $executeStageIds],
        ]);
    }

    /**
     * Mask cross-bank duplicate warnings for non-NC users (WP-7 S-8).
     */
    private function maskDuplicates(array $warning, User $user): array
    {
        $scope = DataScope::forUser($user);

        if ($scope->systemWide) {
            return $warning;
        }

        $warning['duplicates'] = array_map(function ($dup) use ($scope) {
            if ($dup['bank_id'] === $scope->ownBankId) {
                return $dup;
            }

            return [
                'id' => null,
                'reference' => 'طلب مكرر في مؤسسة أخرى', // "Duplicate request at another institution"
                'bank_id' => null,
            ];
        }, $warning['duplicates']);

        return $warning;
    }
}
