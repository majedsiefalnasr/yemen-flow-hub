<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StageAccessLevel;
use App\Enums\StageHistoryVisibility;
use App\Enums\WorkflowVersionState;
use App\Exceptions\EngineException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreEngineRequestRequest;
use App\Http\Resources\EngineRequestResource;
use App\Models\EngineRequest;
use App\Models\FieldGroup;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Authorization\DataScope;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Services\Workflow\DuplicateInvoiceChecker;
use App\Services\Workflow\DynamicFieldOptionsResolver;
use App\Services\Workflow\EngineRequestStatsService;
use App\Services\Workflow\EngineRequestSubmissionService;
use App\Services\Workflow\EngineTransitionService;
use App\Services\Workflow\StageFieldOutputFilter;
use App\Services\Workflow\StageHistoryVisibilityResolver;
use App\Services\Workflow\StagePermissionResolver;
use App\Services\Workflow\UserActionableRequestQuery;
use App\Services\Workflow\WorkflowGraphService;
use App\Support\ApiResponse;
use App\Support\EngineRequestListQuery;
use App\Support\RequestCreationGate;
use App\Support\RoleCodes;
use App\Support\UnionStagePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngineRequestController extends Controller
{
    public function __construct(
        private EngineRequestSubmissionService $submissionService,
        private EngineTransitionService $transitionService,
        private StagePermissionResolver $permissionResolver,
        private DuplicateInvoiceChecker $duplicateChecker,
        private WorkflowGraphService $graphService,
        private EngineNotificationDispatcher $notificationDispatcher,
        private EngineRequestListQuery $listQuery,
        private StageFieldOutputFilter $outputFilter,
        private UserActionableRequestQuery $actionableQuery,
        private StageHistoryVisibilityResolver $historyVisibilityResolver,
    ) {}

    // ── 18.5.1: Create ──────────────────────────────────────────────────

    public function store(StoreEngineRequestRequest $request): JsonResponse
    {
        $result = $this->submissionService->submit(
            $request->validated(),
            $request->header('Idempotency-Key'),
            $request->user(),
        );

        return response()->json($result->toResponseArray(), $result->httpStatus, $result->headers);
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
        $data = $this->buildFieldGroupSchema(
            $engineRequest->workflow_version_id,
            $stage,
            $request->user(),
            $engineRequest,
        );

        return response()->json(['data' => ['field_groups' => $data]]);
    }

    /**
     * Version-scoped counterpart to formSchema(): the initial stage's field
     * schema for a workflow a user is about to submit — no EngineRequest
     * exists yet under the deferred-creation architecture, so this cannot be
     * request-scoped. Same authorization gate as availableWorkflows()/store().
     */
    public function initialFormSchema(Request $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $user = $request->user();

        if (! RequestCreationGate::userCanCreateRequests($user)) {
            throw EngineException::creationNotAllowedForOrganization();
        }

        if ($workflowVersion->state !== WorkflowVersionState::PUBLISHED) {
            throw EngineException::versionNotPublished();
        }

        $initialStage = $workflowVersion->stages()->where('is_initial', true)->first();
        if ($initialStage === null) {
            throw EngineException::noInitialStage();
        }

        if (! $this->permissionResolver->userCanAccessStage($user, $initialStage, StageAccessLevel::EXECUTE)) {
            throw EngineException::stageExecutionForbidden();
        }

        $data = $this->buildFieldGroupSchema($workflowVersion->id, $initialStage, $user, null);

        return response()->json(['data' => ['field_groups' => $data]]);
    }

    /** @return array<int, array<string, mixed>> */
    private function buildFieldGroupSchema(
        int $workflowVersionId,
        WorkflowStage $stage,
        User $user,
        ?EngineRequest $engineRequest,
    ): array {
        $fields = $this->outputFilter->visibleFieldsForStage($workflowVersionId, $stage);
        $rulesByFieldId = $stage->stageFieldRules()->get()->keyBy('field_id');
        $groups = FieldGroup::query()
            ->where('workflow_version_id', $workflowVersionId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $optionsResolver = app(DynamicFieldOptionsResolver::class);
        $fieldsByGroup = $fields->groupBy('field_group_id');

        return $groups->map(function ($group) use ($fieldsByGroup, $rulesByFieldId, $optionsResolver, $user, $engineRequest): array {
            $groupFields = ($fieldsByGroup->get($group->id) ?? collect())
                ->map(function ($field) use ($rulesByFieldId, $optionsResolver, $user, $engineRequest): array {
                    $rule = $rulesByFieldId->get($field->id);

                    return [
                        'id' => $field->id,
                        'key' => $field->key,
                        'semantic_tag' => $field->semantic_tag?->value,
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
                                $user,
                                $engineRequest,
                                $engineRequest?->data[$field->key] ?? null,
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
        })->values()->all();
    }

    // ── 18.5.2: List (scoped & filtered) ─────────────────────────────────

    public function stats(Request $request): JsonResponse
    {
        $scope = $request->string('scope', 'all')->value();
        abort_unless(in_array($scope, ['all', 'queue'], true), 422);

        $data = app(EngineRequestStatsService::class)->aggregate($request->user(), $request, $scope);

        return response()->json(['data' => $data]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // User::hasRoleCode()/role() short-circuit on a loaded `roles` relation
        // instead of querying. The same $user instance is reused by
        // EngineRequestResource for every row during list serialization, so
        // loading it once here avoids a hasRoleCode() query per row.
        $user->loadMissing('roles');

        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            $query = EngineRequest::query()
                ->withStageEntry()
                ->with(['currentStage.stageFieldRules', 'bank', 'merchant', 'creator', 'workflowVersion.definition', 'customsDeclaration']);
            $this->listQuery->applyFilters($query, $request);

            $page = $query->orderByDesc('engine_requests.created_at')
                ->orderBy('engine_requests.id')
                ->paginate($this->listQuery->perPage($request));

            return $this->listQuery->paginatedResponse($page);
        }

        $accessibleStageIds = $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::VIEW);

        $branchFactory = function (int $stageId) use ($request, $user): Builder {
            $query = EngineRequest::query()
                ->withStageEntry()
                ->forUser($user)
                ->where('engine_requests.current_stage_id', $stageId);
            $this->listQuery->applyFilters($query, $request);

            return $query;
        };

        $page = UnionStagePaginator::paginate(
            $branchFactory,
            $accessibleStageIds,
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: $request->integer('page', 1),
            perPage: $this->listQuery->perPage($request),
            forceIndex: 'er_stage_created',
        );

        $page->load(['currentStage.stageFieldRules', 'bank', 'merchant', 'creator', 'workflowVersion.definition', 'customsDeclaration']);

        return $this->listQuery->paginatedResponse($page);
    }

    // ── 18.5.3: My Queue (دوري) ──────────────────────────────────────────

    public function myQueue(Request $request): JsonResponse
    {
        // The دوري priority (SLA-breached → nearest-to-breach → oldest-in-stage),
        // the EXECUTE-stage scoping, DataScope, and filters all live in
        // UserActionableRequestQuery — the single actionable-work contract shared
        // with the work dashboard and the nav badge (Phase D0).
        $page = $this->actionableQuery->paginate($request->user(), $request);

        $page->load(['currentStage.stageFieldRules', 'bank', 'merchant', 'creator', 'claimedBy', 'workflowVersion.definition', 'customsDeclaration']);

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

        $incomingInvoiceNumber = $validated['data']['invoice_number'] ?? $engineRequest->invoice_number;
        if ($incomingInvoiceNumber !== null) {
            $precheck = $this->duplicateChecker->check($incomingInvoiceNumber, $engineRequest->id);
            if ($precheck !== null && $precheck['severity'] === 'block') {
                return ApiResponse::error(
                    'This invoice number matches an existing active request and cannot be submitted.',
                    [],
                    422,
                    'DUPLICATE_INVOICE_BLOCKED',
                );
            }
        }

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

    // ── 18.5.7: History & Graph ──────────────────────────────────────────

    public function history(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $user = $request->user();

        $entries = $engineRequest->history()
            ->with(['fromStage:id,code,name', 'toStage:id,code,name', 'performer:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $data = $entries
            ->map(function ($e) use ($user) {
                $visibility = $this->historyVisibilityResolver->visibilityFor($user, $e);

                return match ($visibility) {
                    StageHistoryVisibility::HIDDEN => null,
                    StageHistoryVisibility::SANITIZED => [
                        'id' => $e->id,
                        'from_stage' => null,
                        'to_stage' => null,
                        'action_code' => null,
                        'performed_by' => $e->performer ? ['id' => $e->performer->id, 'name' => $e->performer->name] : null,
                        'comments' => null,
                        'created_at' => $e->created_at?->toISOString(),
                        'restricted' => true,
                        'restricted_label' => 'إجراء تم في مرحلة مقيدة',
                    ],
                    StageHistoryVisibility::FULL => [
                        'id' => $e->id,
                        'from_stage' => $e->fromStage ? ['id' => $e->fromStage->id, 'code' => $e->fromStage->code, 'name' => $e->fromStage->name] : null,
                        'to_stage' => $e->toStage ? ['id' => $e->toStage->id, 'code' => $e->toStage->code, 'name' => $e->toStage->name] : null,
                        'action_code' => $e->action_code,
                        'performed_by' => $e->performer ? ['id' => $e->performer->id, 'name' => $e->performer->name] : null,
                        'comments' => $e->comments,
                        'created_at' => $e->created_at?->toISOString(),
                        'restricted' => false,
                        'restricted_label' => null,
                    ],
                };
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function graph(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $engineRequest->load(['workflowVersion', 'history']);
        $user = $request->user();
        $graphData = $this->graphService->build($engineRequest->workflowVersion, $user);
        $versionStageIds = array_column($graphData['nodes'], 'id');

        $viewableStageIds = $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            ? $versionStageIds
            : array_values(array_intersect(
                $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::VIEW),
                $versionStageIds,
            ));
        $viewableIdSet = array_flip($viewableStageIds);

        $graphData['nodes'] = array_values(array_filter(
            $graphData['nodes'],
            fn ($node) => isset($viewableIdSet[$node['id']]),
        ));
        $graphData['edges'] = array_values(array_filter(
            $graphData['edges'],
            fn ($edge) => isset($viewableIdSet[$edge['from_stage_id']]) && isset($viewableIdSet[$edge['to_stage_id']]),
        ));

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
        $executeStageIds = array_values(array_intersect(
            $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::EXECUTE),
            array_column($graphData['nodes'], 'id'),
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
