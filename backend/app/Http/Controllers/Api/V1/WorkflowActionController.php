<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StaleResourceException;
use App\Exceptions\WorkflowDesignProtectionException;
use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\GuardsDesignerInput;
use App\Http\Requests\StoreWorkflowActionRequest;
use App\Http\Requests\UpdateWorkflowActionRequest;
use App\Http\Resources\WorkflowActionResource;
use App\Models\WorkflowAction;
use App\Services\Workflow\WorkflowActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowActionController extends Controller
{
    use GuardsDesignerInput;

    private const SORT_COLUMNS = ['code', 'name', 'kind', 'is_active', 'created_at'];

    public function __construct(private readonly WorkflowActionService $actions) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkflowAction::class);
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', self::SORT_COLUMNS)],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $sort = $validated['sort'] ?? 'code';
        $direction = $validated['direction'] ?? 'asc';

        $page = WorkflowAction::query()
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n
                ->where('code', 'like', '%'.$this->escapeLike($request->string('search')->toString()).'%')
                ->orWhere('name', 'like', '%'.$this->escapeLike($request->string('search')->toString()).'%')))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($request->integer('per_page', 25));

        $page->getCollection()->transform(function (WorkflowAction $action) {
            $action->setAttribute('is_in_use', $this->inUseFlag($action));

            return $action;
        });

        return response()->json([
            'data' => WorkflowActionResource::collection($page->items())->resolve(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(WorkflowAction $workflowAction): WorkflowActionResource
    {
        $this->authorize('view', $workflowAction);
        $workflowAction->setAttribute('is_in_use', $this->inUseFlag($workflowAction));

        return new WorkflowActionResource($workflowAction);
    }

    public function store(StoreWorkflowActionRequest $request): JsonResponse
    {
        $this->authorize('create', WorkflowAction::class);
        $action = $this->withUniqueViolationGuard(
            fn () => $this->actions->create($request->user(), $request->validated()),
            'code',
            'A workflow action with this code already exists.',
        );

        return (new WorkflowActionResource($action))->response()->setStatusCode(201);
    }

    public function update(UpdateWorkflowActionRequest $request, WorkflowAction $workflowAction): JsonResponse
    {
        $this->authorize('update', $workflowAction);
        $attributes = collect($request->validated())->except(['version', 'code'])->all();

        try {
            $workflowAction = $this->actions->update(
                $request->user(),
                $workflowAction,
                $attributes,
                $request->integer('version'),
            );
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The workflow action was modified by another user.', 409);
        }

        return (new WorkflowActionResource($workflowAction))->response();
    }

    public function activate(Request $request, WorkflowAction $workflowAction): JsonResponse
    {
        return $this->setActive($request, $workflowAction, true);
    }

    public function deactivate(Request $request, WorkflowAction $workflowAction): JsonResponse
    {
        return $this->setActive($request, $workflowAction, false);
    }

    public function destroy(Request $request, WorkflowAction $workflowAction): JsonResponse
    {
        $this->authorize('delete', $workflowAction);

        try {
            $this->actions->delete($request->user(), $workflowAction);
        } catch (WorkflowDesignProtectionException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 422);
        }

        return response()->json(null, 204);
    }

    private function setActive(Request $request, WorkflowAction $workflowAction, bool $active): JsonResponse
    {
        $this->authorize('update', $workflowAction);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        try {
            $workflowAction = $this->actions->setActive($request->user(), $workflowAction, $active, $validated['version']);
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The workflow action was modified by another user.', 409);
        } catch (WorkflowDesignProtectionException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 422);
        }

        return (new WorkflowActionResource($workflowAction))->response();
    }

    private function inUseFlag(WorkflowAction $action): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('workflow_transitions')) {
            return false;
        }

        return DB::table('workflow_transitions')->where('action_id', $action->getKey())->exists();
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
