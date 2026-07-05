<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Exceptions\StaleResourceException;
use App\Exceptions\WorkflowDesignProtectionException;
use App\Exceptions\WorkflowVersionImmutableException;
use App\Exceptions\WorkflowVersionValidationException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\UpdateWorkflowVersionRequest;
use App\Http\Resources\WorkflowVersionResource;
use App\Models\User;
use App\Models\WorkflowVersion;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Services\Workflow\WorkflowDesignerService;
use App\Services\Workflow\WorkflowGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowVersionController extends Controller
{
    public function __construct(
        private readonly WorkflowDesignerService $designer,
        private readonly WorkflowGraphService $graphService,
        private readonly AuditService $auditService,
        private readonly EngineNotificationDispatcher $notificationDispatcher,
        private readonly PermissionService $permissionService,
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

        $this->auditService->log(AuditAction::WORKFLOW_CLONED, $request->user(), $clone, [
            'source_version_id' => $workflowVersion->id,
        ]);

        return (new WorkflowVersionResource($clone))->response()->setStatusCode(201);
    }

    public function publish(Request $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('publish', $workflowVersion);
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

        $this->auditService->log(AuditAction::WORKFLOW_PUBLISHED, $request->user(), $workflowVersion, [
            'workflow_definition_id' => $workflowVersion->workflow_definition_id,
        ]);

        $definition = $workflowVersion->workflowDefinition;
        $adminUserIds = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('code', 'system_admin'))
            ->pluck('id')
            ->toArray();

        $this->notificationDispatcher->afterWorkflowPublished(
            definitionId: (int) $workflowVersion->workflow_definition_id,
            workflowName: $definition?->name ?? 'Workflow',
            versionLabel: $workflowVersion->label ?? "v{$workflowVersion->id}",
            recipientUserIds: $adminUserIds,
        );

        $this->permissionService->clearAllScreenPermissionCaches();

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
        $this->authorize('archive', $workflowVersion);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        try {
            $workflowVersion = $this->designer->archiveVersion($request->user(), $workflowVersion, $validated['version']);
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The workflow version was modified by another user.', 409);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        $this->permissionService->clearAllScreenPermissionCaches();

        return (new WorkflowVersionResource($workflowVersion))->response();
    }

    public function destroy(Request $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('delete', $workflowVersion);

        try {
            $this->designer->deleteVersion($request->user(), $workflowVersion);
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
