<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Enums\StageAccessLevel;
use App\Exceptions\EngineException;
use App\Models\EngineRequest;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

class EngineTransitionService
{
    public function __construct(
        private StagePermissionResolver $permissionResolver,
        private StageFieldRuleValidator $fieldRuleValidator,
        private RequestProjectionSync $projectionSync,
        private AuditService $auditService,
        private StageHookRegistry $hookRegistry,
    ) {}

    public function execute(
        EngineRequest $request,
        int $transitionId,
        ?string $comment,
        array $data,
        int $version,
    ): EngineRequest {
        return DB::transaction(function () use ($request, $transitionId, $comment, $data, $version) {
            $request = EngineRequest::lockForUpdate()->findOrFail($request->id);

            if (! $request->isActive()) {
                throw EngineException::requestClosed();
            }

            if ($request->version !== $version) {
                throw EngineException::requestStale();
            }

            $transition = WorkflowTransition::with(['fromStage', 'toStage', 'action'])->find($transitionId);
            if ($transition === null || (int) $transition->from_stage_id !== (int) $request->current_stage_id) {
                throw EngineException::transitionNotAvailable();
            }

            $user = auth()->user();
            if (! $this->permissionResolver->userCanAccessStage($user, $transition->fromStage, StageAccessLevel::EXECUTE)) {
                throw EngineException::stageExecutionForbidden();
            }

            if ($transition->requires_comment && (trim($comment ?? '') === '')) {
                throw EngineException::commentRequired();
            }

            $mergedData = array_merge($request->data ?? [], $data);
            $fieldErrors = $this->fieldRuleValidator->validateStage(
                $transition->fromStage,
                $mergedData,
                $request->data ?? [],
                true,
            );
            if ($fieldErrors !== []) {
                throw EngineException::stageFieldsInvalid($fieldErrors);
            }

            $newStatus = $transition->toStage->is_final ? 'CLOSED' : 'ACTIVE';

            $request->forceFill([
                'data' => $mergedData,
                'current_stage_id' => $transition->to_stage_id,
                'status' => $newStatus,
                'version' => $request->version + 1,
            ])->save();

            $this->projectionSync->sync($request);

            WorkflowHistoryEntry::create([
                'request_id' => $request->id,
                'from_stage_id' => $transition->from_stage_id,
                'to_stage_id' => $transition->to_stage_id,
                'action_code' => $transition->action?->code,
                'performed_by' => $user->id,
                'comments' => $comment,
                'created_at' => now(),
            ]);

            $this->auditService->log(
                AuditAction::STATUS_TRANSITION,
                $user,
                $request,
                [
                    'transition_id' => $transition->id,
                    'from_stage_id' => $transition->from_stage_id,
                    'to_stage_id' => $transition->to_stage_id,
                    'action_code' => $transition->action?->code,
                ],
            );

            $this->hookRegistry->fireExit($request, $transition, $user);
            $this->hookRegistry->fireEntry($request, $transition, $user);

            return $request->fresh(['currentStage', 'creator', 'bank', 'merchant']);
        });
    }

    public function saveDraft(EngineRequest $request, array $data, int $version): EngineRequest
    {
        return DB::transaction(function () use ($request, $data, $version) {
            $request = EngineRequest::lockForUpdate()->findOrFail($request->id);

            if (! $request->isActive()) {
                throw EngineException::requestClosed();
            }

            if ($request->version !== $version) {
                throw EngineException::requestStale();
            }

            $user = auth()->user();
            if (! $this->permissionResolver->userCanAccessStage($user, $request->currentStage, StageAccessLevel::EXECUTE)) {
                throw EngineException::stageExecutionForbidden();
            }

            $mergedData = array_merge($request->data ?? [], $data);
            $fieldErrors = $this->fieldRuleValidator->validateStage(
                $request->currentStage,
                $mergedData,
                $request->data ?? [],
                false,
            );
            if ($fieldErrors !== []) {
                throw EngineException::stageFieldsInvalid($fieldErrors);
            }

            $request->forceFill([
                'data' => $mergedData,
                'version' => $request->version + 1,
            ])->save();

            $this->projectionSync->sync($request);

            $this->auditService->log(
                AuditAction::REQUEST_UPDATED,
                $user,
                $request,
                ['action' => 'draft_save'],
            );

            return $request->fresh(['currentStage', 'creator', 'bank', 'merchant']);
        });
    }
}
