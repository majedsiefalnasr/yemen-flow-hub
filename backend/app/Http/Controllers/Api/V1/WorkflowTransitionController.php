<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StaleResourceException;
use App\Exceptions\WorkflowVersionImmutableException;
use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\GuardsDesignerInput;
use App\Http\Requests\StoreWorkflowTransitionRequest;
use App\Http\Requests\UpdateWorkflowTransitionRequest;
use App\Http\Resources\WorkflowTransitionResource;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\WorkflowDesignerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkflowTransitionController extends Controller
{
    use GuardsDesignerInput;

    public function __construct(private readonly WorkflowDesignerService $designer) {}

    public function index(WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('viewAny', WorkflowTransition::class);
        $transitions = $workflowVersion->transitions()->orderBy('from_stage_id')->orderBy('id')->get();

        return response()->json(['data' => WorkflowTransitionResource::collection($transitions)->resolve()]);
    }

    public function show(WorkflowVersion $workflowVersion, WorkflowTransition $workflowTransition): WorkflowTransitionResource
    {
        $this->authorize('view', $workflowTransition);

        return new WorkflowTransitionResource($workflowTransition);
    }

    public function store(StoreWorkflowTransitionRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('create', WorkflowTransition::class);

        try {
            $transition = $this->designer->createTransition($request->user(), $workflowVersion, $request->validated());
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages(['action_id' => 'A transition for this action already exists from this stage.']);
        }

        return (new WorkflowTransitionResource($transition))->response()->setStatusCode(201);
    }

    public function update(
        UpdateWorkflowTransitionRequest $request,
        WorkflowVersion $workflowVersion,
        WorkflowTransition $workflowTransition,
    ): JsonResponse {
        $this->authorize('update', $workflowTransition);
        $attributes = collect($request->validated())->except('version')->all();

        try {
            $workflowTransition = $this->designer->updateTransition(
                $request->user(),
                $workflowTransition,
                $attributes,
                $request->integer('version'),
            );
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The workflow transition was modified by another user.', 409);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages(['action_id' => 'A transition for this action already exists from this stage.']);
        }

        return (new WorkflowTransitionResource($workflowTransition))->response();
    }

    public function destroy(
        Request $request,
        WorkflowVersion $workflowVersion,
        WorkflowTransition $workflowTransition,
    ): JsonResponse {
        $this->authorize('delete', $workflowTransition);

        try {
            $this->designer->deleteTransition($request->user(), $workflowTransition);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return response()->json(null, 204);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
