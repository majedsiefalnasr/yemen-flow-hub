<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\WorkflowDesignProtectionException;
use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\GuardsDesignerInput;
use App\Http\Requests\StoreWorkflowDefinitionRequest;
use App\Http\Resources\WorkflowDefinitionResource;
use App\Models\WorkflowDefinition;
use App\Services\Workflow\WorkflowDesignerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowDefinitionController extends Controller
{
    use GuardsDesignerInput;

    private const SORT_COLUMNS = ['code', 'name', 'is_active', 'created_at'];

    public function __construct(private readonly WorkflowDesignerService $designer) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkflowDefinition::class);
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', self::SORT_COLUMNS)],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';

        $page = WorkflowDefinition::query()
            ->with(['versions' => fn ($q) => $q->orderByDesc('version_number')->withCount(['stages', 'transitions', 'fields'])])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n
                ->where('code', 'like', '%'.$this->escapeLike($request->string('search')->toString()).'%')
                ->orWhere('name', 'like', '%'.$this->escapeLike($request->string('search')->toString()).'%')))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => WorkflowDefinitionResource::collection($page->items())->resolve(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(WorkflowDefinition $workflowDefinition): WorkflowDefinitionResource
    {
        $this->authorize('view', $workflowDefinition);

        return new WorkflowDefinitionResource(
            $workflowDefinition->load(['versions' => fn ($q) => $q->orderByDesc('version_number')]),
        );
    }

    public function store(StoreWorkflowDefinitionRequest $request): JsonResponse
    {
        $this->authorize('create', WorkflowDefinition::class);
        $definition = $this->withUniqueViolationGuard(
            fn () => $this->designer->createDefinition($request->user(), $request->validated()),
            'code',
            'A workflow definition with this code already exists.',
        );

        return (new WorkflowDefinitionResource(
            $definition->load(['versions' => fn ($q) => $q->orderByDesc('version_number')]),
        ))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, WorkflowDefinition $workflowDefinition): JsonResponse
    {
        $this->authorize('delete', $workflowDefinition);

        try {
            $this->designer->deleteDefinition($request->user(), $workflowDefinition);
        } catch (WorkflowDesignProtectionException $e) {
            return $this->error($e->errorCode, $e->getMessage(), 422);
        }

        return response()->json(null, 204);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
