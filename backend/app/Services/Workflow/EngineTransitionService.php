<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Enums\FieldSemanticTag;
use App\Enums\StageAccessLevel;
use App\Exceptions\CustomsException;
use App\Exceptions\EngineException;
use App\Exceptions\FinancingLimitExceededException;
use App\Exceptions\FinancingLockTimeoutException;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\User;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditService;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Services\Operations\OperationalAlertLogger;
use App\Support\EngineRequestStatus;
use App\Support\TransitionFieldDiffBuilder;
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
        private EngineClaimService $claimService,
        private TransitionFieldDiffBuilder $fieldDiffBuilder,
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

            $beforeData = $request->data ?? [];
            $mergedData = array_merge($beforeData, $data);
            $fieldErrors = $this->fieldRuleValidator->validateStage(
                $transition->fromStage,
                $mergedData,
                $beforeData,
                true,
                $user,
                $request,
            );
            if ($fieldErrors !== []) {
                throw EngineException::stageFieldsInvalid($fieldErrors);
            }

            $newStatus = $this->resolveStatusAfterTransition($transition->toStage);

            // ARCH-002: stamp the moment the request enters its new stage into an
            // indexed projection column, using the same timestamp written to the
            // workflow_history row below so the column equals the value the old
            // correlated max(created_at) subquery computed.
            $enteredAt = now();

            // DB-001/DB-002 follow-up: maintain the SLA deadline as a plain indexed
            // epoch-int column alongside stage_entered_at, so ORDER BY/WHERE on the
            // deadline can use er_stage_sla_deadline instead of sorting on a computed
            // join+expression. toStage is already eager-loaded above, so no extra query.
            $slaDeadlineEpoch = $transition->toStage->sla_duration_minutes !== null
                ? $enteredAt->getTimestamp() + ((int) $transition->toStage->sla_duration_minutes * 60)
                : null;

            $request->forceFill([
                'data' => $mergedData,
                'current_stage_id' => $transition->to_stage_id,
                'stage_entered_at' => $enteredAt,
                'sla_deadline_epoch' => $slaDeadlineEpoch,
                'status' => $newStatus,
                'version' => $request->version + 1,
            ])->save();

            $this->projectionSync->sync($request);

            if ($transition->fromStage->requires_claim && $request->claimed_by !== null) {
                $this->claimService->releaseForStageChange($request, $user);
            }

            $correlationId = (string) Str::uuid();

            WorkflowHistoryEntry::create([
                'request_id' => $request->id,
                'from_stage_id' => $transition->from_stage_id,
                'to_stage_id' => $transition->to_stage_id,
                'action_code' => $transition->action?->code,
                'performed_by' => $user->id,
                'comments' => $comment,
                'correlation_id' => $correlationId,
                'created_at' => $enteredAt,
            ]);

            $fieldDiff = $this->fieldDiffBuilder->diff(
                $beforeData,
                $mergedData,
                $this->resolveSensitiveFieldKeys($request),
            );

            $this->auditService->log(
                AuditAction::STATUS_TRANSITION,
                $user,
                $request,
                [
                    'transition_id' => $transition->id,
                    'from_stage_id' => $transition->from_stage_id,
                    'to_stage_id' => $transition->to_stage_id,
                    'action_code' => $transition->action?->code,
                    'request_id' => $request->id,
                ],
                workflowInstanceId: $request->id,
                correlationId: $correlationId,
                oldValues: $fieldDiff['old_values'] !== [] ? $fieldDiff['old_values'] : null,
                newValues: $fieldDiff['new_values'] !== [] ? $fieldDiff['new_values'] : null,
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
                OperationalAlertLogger::failure('stage_hook', $e, [
                    'transition_id' => $transition->id,
                    'request_id' => $request->id,
                ]);

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

    /**
     * @return list<string>
     */
    private function resolveSensitiveFieldKeys(EngineRequest $request): array
    {
        return FieldDefinition::query()
            ->where('workflow_version_id', $request->workflow_version_id)
            ->whereIn('semantic_tag', [
                FieldSemanticTag::MERCHANT_TAX_NUMBER,
                FieldSemanticTag::AMOUNT,
                FieldSemanticTag::INVOICE_NUMBER,
                FieldSemanticTag::REQUESTED_PERCENTAGE,
            ])
            ->pluck('key')
            ->all();
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
