<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Enums\StageAccessLevel;
use App\Exceptions\CustomsException;
use App\Exceptions\EngineException;
use App\Exceptions\FinancingLimitExceededException;
use App\Exceptions\FinancingLockTimeoutException;
use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditService;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Support\EngineRequestStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EngineTransitionService
{
    public function __construct(
        private StagePermissionResolver $permissionResolver,
        private StageFieldRuleValidator $fieldRuleValidator,
        private RequestProjectionSync $projectionSync,
        private AuditService $auditService,
        private StageHookRegistry $hookRegistry,
        private EngineNotificationDispatcher $notificationDispatcher,
    ) {}

    public function execute(
        EngineRequest $request,
        int $transitionId,
        ?string $comment,
        array $data,
        int $version,
        User $actor,
    ): EngineRequest {
        return DB::transaction(function () use ($request, $transitionId, $comment, $data, $version, $actor) {
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

            $user = $actor;
            if (! $this->permissionResolver->userCanAccessStage($user, $transition->fromStage, StageAccessLevel::EXECUTE)) {
                throw EngineException::stageExecutionForbidden();
            }

            if ($transition->fromStage->requires_claim
                && ! ($request->claimed_by === $user->id && $request->isClaimed())) {
                throw EngineException::claimNotHeld();
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

            $newStatus = $this->resolveStatusAfterTransition($transition->toStage);

            $request->forceFill([
                'data' => $mergedData,
                'current_stage_id' => $transition->to_stage_id,
                'status' => $newStatus,
                'version' => $request->version + 1,
            ])->save();

            $this->projectionSync->sync($request);

            $correlationId = (string) Str::uuid();

            WorkflowHistoryEntry::create([
                'request_id' => $request->id,
                'from_stage_id' => $transition->from_stage_id,
                'to_stage_id' => $transition->to_stage_id,
                'action_code' => $transition->action?->code,
                'performed_by' => $user->id,
                'comments' => $comment,
                'correlation_id' => $correlationId,
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
                workflowInstanceId: $request->id,
                correlationId: $correlationId,
            );

            // Hooks run inside the transaction so any failure rolls the transition back
            // atomically. Domain exceptions that already carry their own error envelope
            // (engine, financing, customs) propagate as-is so the client sees the correct
            // error_code; only an unexpected throwable is wrapped to avoid a bare 500.
            try {
                $this->hookRegistry->fireExit($request, $transition, $user);
                $this->hookRegistry->fireEntry($request, $transition, $user);
            } catch (EngineException|FinancingLimitExceededException|FinancingLockTimeoutException|CustomsException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new EngineException(
                    'A stage side-effect failed; the transition was rolled back.',
                    'STAGE_HOOK_FAILED',
                    422,
                );
            }

            $this->notificationDispatcher->afterTransition(
                requestId: $request->id,
                referenceNumber: $request->reference_number ?? "#{$request->id}",
                toStage: $transition->toStage,
                fromStageName: $transition->fromStage->name ?? $transition->fromStage->code,
                toStageName: $transition->toStage->name ?? $transition->toStage->code,
                actionLabel: $transition->action?->label ?? $transition->action?->code ?? 'transition',
            );

            return $request->fresh(['currentStage', 'creator', 'bank', 'merchant']);
        });
    }

    public function saveDraft(EngineRequest $request, array $data, int $version, User $actor): EngineRequest
    {
        return DB::transaction(function () use ($request, $data, $version, $actor) {
            $request = EngineRequest::lockForUpdate()->findOrFail($request->id);

            if (! $request->isActive()) {
                throw EngineException::requestClosed();
            }

            if ($request->version !== $version) {
                throw EngineException::requestStale();
            }

            $user = $actor;
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
                workflowInstanceId: $request->id,
            );

            return $request->fresh(['currentStage', 'creator', 'bank', 'merchant']);
        });
    }

    public function abandonDraft(EngineRequest $request, int $version, User $actor): EngineRequest
    {
        return DB::transaction(function () use ($request, $version, $actor) {
            $request = EngineRequest::lockForUpdate()->findOrFail($request->id);

            if (! $request->isActive()) {
                throw EngineException::requestClosed();
            }

            if ($request->version !== $version) {
                throw EngineException::requestStale();
            }

            $request->loadMissing('currentStage');
            $stage = $request->currentStage;
            if ($stage === null || ! $stage->is_initial) {
                throw EngineException::abandonNotAvailable();
            }

            if (! $this->permissionResolver->userCanAccessStage($actor, $stage, StageAccessLevel::EXECUTE)) {
                throw EngineException::stageExecutionForbidden();
            }

            if ($stage->requires_claim
                && ! ($request->claimed_by === $actor->id && $request->isClaimed())) {
                throw EngineException::claimNotHeld();
            }

            $fromStageId = $request->current_stage_id;
            $correlationId = (string) Str::uuid();

            $request->forceFill([
                'status' => EngineRequestStatus::ABANDONED,
                'version' => $request->version + 1,
                'claimed_by' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
            ])->save();

            $this->projectionSync->sync($request);

            WorkflowHistoryEntry::create([
                'request_id' => $request->id,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => null,
                'action_code' => 'ABANDON',
                'performed_by' => $actor->id,
                'comments' => null,
                'correlation_id' => $correlationId,
                'created_at' => now(),
            ]);

            $this->auditService->log(
                AuditAction::REQUEST_ABANDONED,
                $actor,
                $request,
                ['from_stage_id' => $fromStageId],
                workflowInstanceId: $request->id,
                correlationId: $correlationId,
            );

            return $request->fresh(['currentStage', 'creator', 'bank', 'merchant']);
        });
    }

    private function resolveStatusAfterTransition(WorkflowStage $toStage): string
    {
        if (! $toStage->is_final) {
            return EngineRequestStatus::ACTIVE;
        }

        if ($toStage->final_outcome === null) {
            Log::warning('Final stage reached with null final_outcome; defaulting to CLOSED.', [
                'stage_id' => $toStage->id,
                'stage_code' => $toStage->code,
            ]);

            return EngineRequestStatus::CLOSED;
        }

        return $toStage->final_outcome->toRequestStatus();
    }
}
