<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StaleResourceException;
use App\Exceptions\WorkflowVersionImmutableException;
use App\Exceptions\WorkflowVersionValidationException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\UpdateWorkflowVersionRequest;
use App\Http\Resources\WorkflowVersionResource;
use App\Models\WorkflowVersion;
use App\Services\Workflow\WorkflowDesignerService;
use App\Services\Workflow\WorkflowGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowVersionController extends Controller
{
    public function __construct(
        private readonly WorkflowDesignerService $designer,
        private readonly WorkflowGraphService $graphService,
    ) {}

    public function show(WorkflowVersion $workflowVersion): WorkflowVersionResource
    {
        $this->authorize('view', $workflowVersion);

        return new WorkflowVersionResource($workflowVersion);
    }

    /**
     * Read-only process graph derived from the version's stages + transitions.
     */
    public function graph(WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('view', $workflowVersion);

        return response()->json(['data' => $this->graphService->build($workflowVersion)]);
    }

    public function update(UpdateWorkflowVersionRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('update', $workflowVersion);

        try {
            $workflowVersion = $this->designer->updateVersion(
                $request->user(),
                $workflowVersion,
                [],
                $request->integer('version'),
            );
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The workflow version was modified by another user.', 409);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new WorkflowVersionResource($workflowVersion))->response();
    }

    public function clone(Request $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('clone', $workflowVersion);

        try {
            $clone = $this->designer->cloneVersion($request->user(), $workflowVersion);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new WorkflowVersionResource($clone))->response()->setStatusCode(201);
    }

    public function publish(Request $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('update', $workflowVersion);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        try {
            $workflowVersion = $this->designer->publishVersion($request->user(), $workflowVersion, $validated['version']);
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The workflow version was modified by another user.', 409);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        } catch (WorkflowVersionValidationException $exception) {
            return response()->json([
                'error' => [
                    'code' => 'WORKFLOW_VALIDATION_FAILED',
                    'message' => 'The workflow version cannot be published until all validation errors are resolved.',
                    'errors' => $exception->errors,
                    'request_id' => request()->header('X-Request-ID'),
                ],
            ], 422);
        }

        return (new WorkflowVersionResource($workflowVersion))->response();
    }

    /**
     * Validate a version without side effects. Returns the (possibly empty) error
     * list; an empty list means the version is publishable.
     */
    public function validate(WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('view', $workflowVersion);

        return response()->json(['data' => ['errors' => $this->designer->validateVersion($workflowVersion)]]);
    }

    public function archive(Request $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('update', $workflowVersion);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        try {
            $workflowVersion = $this->designer->archiveVersion($request->user(), $workflowVersion, $validated['version']);
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The workflow version was modified by another user.', 409);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new WorkflowVersionResource($workflowVersion))->response();
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
