<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StaleResourceException;
use App\Exceptions\WorkflowDesignProtectionException;
use App\Exceptions\WorkflowVersionImmutableException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreFieldGroupRequest;
use App\Http\Requests\UpdateFieldGroupRequest;
use App\Http\Resources\FieldGroupResource;
use App\Models\FieldGroup;
use App\Models\WorkflowVersion;
use App\Services\Workflow\FieldDesignerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldGroupController extends Controller
{
    public function __construct(private readonly FieldDesignerService $designer) {}

    public function index(WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('viewAny', FieldGroup::class);
        $groups = $workflowVersion->fieldGroups()
            ->with(['fields' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => FieldGroupResource::collection($groups)->resolve()]);
    }

    public function store(StoreFieldGroupRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('create', FieldGroup::class);

        try {
            $group = $this->designer->createGroup($request->user(), $workflowVersion, $request->validated());
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new FieldGroupResource($group))->response()->setStatusCode(201);
    }

    public function update(UpdateFieldGroupRequest $request, WorkflowVersion $workflowVersion, FieldGroup $fieldGroup): JsonResponse
    {
        $this->authorize('update', $fieldGroup);
        $attributes = collect($request->validated())->except('version')->all();

        try {
            $fieldGroup = $this->designer->updateGroup($request->user(), $fieldGroup, $attributes, $request->integer('version'));
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The field group was modified by another user.', 409);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new FieldGroupResource($fieldGroup))->response();
    }

    public function destroy(Request $request, WorkflowVersion $workflowVersion, FieldGroup $fieldGroup): JsonResponse
    {
        $this->authorize('delete', $fieldGroup);

        try {
            $this->designer->deleteGroup($request->user(), $fieldGroup);
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
