<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StaleResourceException;
use App\Exceptions\WorkflowVersionImmutableException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreStagePermissionRequest;
use App\Http\Requests\UpdateStagePermissionRequest;
use App\Http\Resources\StagePermissionResource;
use App\Models\StagePermission;
use App\Models\WorkflowStage;
use App\Services\Authorization\PermissionService;
use App\Services\Workflow\WorkflowDesignerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StagePermissionController extends Controller
{
    public function __construct(
        private readonly WorkflowDesignerService $designer,
        private readonly PermissionService $permissionService,
    ) {}

    public function index(WorkflowStage $workflowStage): JsonResponse
    {
        $this->authorize('viewAny', StagePermission::class);
        $permissions = $workflowStage->stagePermissions()->orderBy('id')->get();

        return response()->json(['data' => StagePermissionResource::collection($permissions)->resolve()]);
    }

    public function show(WorkflowStage $workflowStage, StagePermission $stagePermission): StagePermissionResource
    {
        $this->authorize('view', $stagePermission);

        return new StagePermissionResource($stagePermission);
    }

    public function store(StoreStagePermissionRequest $request, WorkflowStage $workflowStage): JsonResponse
    {
        $this->authorize('create', StagePermission::class);

        try {
            $permission = $this->designer->createStagePermission($request->user(), $workflowStage, $request->validated());
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        $this->permissionService->clearAllScreenPermissionCaches();

        return (new StagePermissionResource($permission))->response()->setStatusCode(201);
    }

    public function update(
        UpdateStagePermissionRequest $request,
        WorkflowStage $workflowStage,
        StagePermission $stagePermission,
    ): JsonResponse {
        $this->authorize('update', $stagePermission);
        $attributes = collect($request->validated())->except('version')->all();

        try {
            $stagePermission = $this->designer->updateStagePermission(
                $request->user(),
                $stagePermission,
                $attributes,
                $request->integer('version'),
            );
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The stage permission was modified by another user.', 409);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        $this->permissionService->clearAllScreenPermissionCaches();

        return (new StagePermissionResource($stagePermission))->response();
    }

    public function destroy(
        Request $request,
        WorkflowStage $workflowStage,
        StagePermission $stagePermission,
    ): JsonResponse {
        $this->authorize('delete', $stagePermission);

        try {
            $this->designer->deleteStagePermission($request->user(), $stagePermission);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        $this->permissionService->clearAllScreenPermissionCaches();

        return response()->json(null, 204);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
