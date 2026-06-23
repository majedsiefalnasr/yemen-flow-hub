<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StaleResourceException;
use App\Exceptions\WorkflowDesignProtectionException;
use App\Exceptions\WorkflowVersionImmutableException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreWorkflowStageRequest;
use App\Http\Requests\UpdateWorkflowStageRequest;
use App\Http\Resources\WorkflowStageResource;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Workflow\WorkflowDesignerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowStageController extends Controller
{
    public function __construct(private readonly WorkflowDesignerService $designer) {}

    public function index(WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('viewAny', WorkflowStage::class);
        $stages = $workflowVersion->stages()->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => WorkflowStageResource::collection($stages)->resolve()]);
    }

    public function show(WorkflowVersion $workflowVersion, WorkflowStage $workflowStage): WorkflowStageResource
    {
        $this->authorize('view', $workflowStage);

        return new WorkflowStageResource($workflowStage);
    }

    public function store(StoreWorkflowStageRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('create', WorkflowStage::class);

        try {
            $stage = $this->designer->createStage($request->user(), $workflowVersion, $request->validated());
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new WorkflowStageResource($stage))->response()->setStatusCode(201);
    }

    public function update(
        UpdateWorkflowStageRequest $request,
        WorkflowVersion $workflowVersion,
        WorkflowStage $workflowStage,
    ): JsonResponse {
        $this->authorize('update', $workflowStage);
        $attributes = collect($request->validated())->except('version')->all();

        try {
            $workflowStage = $this->designer->updateStage(
                $request->user(),
                $workflowStage,
                $attributes,
                $request->integer('version'),
            );
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The workflow stage was modified by another user.', 409);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new WorkflowStageResource($workflowStage))->response();
    }

    public function destroy(
        Request $request,
        WorkflowVersion $workflowVersion,
        WorkflowStage $workflowStage,
    ): JsonResponse {
        $this->authorize('delete', $workflowStage);

        try {
            $this->designer->deleteStage($request->user(), $workflowStage);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        } catch (WorkflowDesignProtectionException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 422);
        }

        return response()->json(null, 204);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
