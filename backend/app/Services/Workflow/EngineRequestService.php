<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Exceptions\EngineException;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowVersion;
use App\Services\Audit\AuditService;
use App\Support\RequestCreationGate;
use Illuminate\Support\Facades\DB;

class EngineRequestService
{
    public function __construct(
        private StagePermissionResolver $permissionResolver,
        private StageFieldRuleValidator $fieldRuleValidator,
        private RequestProjectionSync $projectionSync,
        private AuditService $auditService,
        private EngineRequestReferenceAllocator $referenceAllocator,
    ) {}

    public function create(WorkflowVersion $version, array $data, User $actor): EngineRequest
    {
        if (! RequestCreationGate::userCanCreateRequests($actor)) {
            throw EngineException::creationNotAllowedForOrganization();
        }

        if ($version->state !== WorkflowVersionState::PUBLISHED) {
            throw EngineException::versionNotPublished();
        }

        $initialStage = $version->stages()->where('is_initial', true)->first();
        if ($initialStage === null) {
            throw EngineException::noInitialStage();
        }

        if (! $this->permissionResolver->userCanAccessStage($actor, $initialStage, StageAccessLevel::EXECUTE)) {
            throw EngineException::stageExecutionForbidden();
        }

        $fieldErrors = $this->fieldRuleValidator->validateStage(
            $initialStage,
            $data['data'] ?? [],
            [],
            false,
            $actor,
        );
        if ($fieldErrors !== []) {
            throw EngineException::stageFieldsInvalid($fieldErrors);
        }

        $resolvedBankId = $actor->bank_id;

        if (isset($data['merchant_id'])) {
            $merchant = Merchant::find($data['merchant_id']);
            if ($merchant === null) {
                throw new EngineException('Merchant not found.', 'MERCHANT_NOT_FOUND', 422);
            }
            if ($resolvedBankId === null || (int) $merchant->bank_id !== (int) $resolvedBankId) {
                throw EngineException::merchantOutOfScope();
            }
        }

        // API-003b: reserve the reference BEFORE the transaction, via the
        // atomic per-year sequence allocator (single-row increment, no race on
        // the unique-reference index). Doing it here — outside the transaction —
        // means a create rollback or deadlock-retry below leaves an unused
        // number (a harmless gap), never a reused or re-bumped one.
        $reference = $this->referenceAllocator->allocate();

        // API-003: the transaction retry ($attempts = 5) still guards the
        // remaining multi-row writes (history, projection) against an InnoDB
        // deadlock / lock-wait on those tables. The reference race that
        // originally motivated it is now gone (allocation is atomic and above),
        // but the retry is cheap insurance for the other statements.
        // See docs/audit/evidence/API-003-reference-allocator.md.
        return DB::transaction(function () use ($version, $initialStage, $data, $actor, $resolvedBankId, $reference) {
            // ARCH-002: the request enters its initial stage now; stamp the same
            // timestamp into the projection column and the CREATE history row so
            // SLA timing works from creation, not only after the first transition.
            $enteredAt = now();

            // DB-001/DB-002 follow-up: see EngineTransitionService::execute() for
            // why this is maintained alongside stage_entered_at.
            $slaDeadlineEpoch = $initialStage->sla_duration_minutes !== null
                ? $enteredAt->getTimestamp() + ((int) $initialStage->sla_duration_minutes * 60)
                : null;

            $request = EngineRequest::create([
                'reference' => $reference,
                'workflow_version_id' => $version->id,
                'current_stage_id' => $initialStage->id,
                'stage_entered_at' => $enteredAt,
                'sla_deadline_epoch' => $slaDeadlineEpoch,
                'status' => 'ACTIVE',
                'created_by' => $actor->id,
                'bank_id' => $resolvedBankId,
                'merchant_id' => $data['merchant_id'] ?? null,
                'data' => $data['data'] ?? [],
                'version' => 1,
            ]);

            $this->projectionSync->sync($request);

            WorkflowHistoryEntry::create([
                'request_id' => $request->id,
                'from_stage_id' => null,
                'to_stage_id' => $initialStage->id,
                'action_code' => 'CREATE',
                'performed_by' => $actor->id,
                'comments' => null,
                'created_at' => $enteredAt,
            ]);

            $this->auditService->log(
                AuditAction::REQUEST_CREATED,
                $actor,
                $request,
                [
                    'reference' => $request->reference,
                    'workflow_version_id' => $version->id,
                    'initial_stage_id' => $initialStage->id,
                ],
            );

            return $request->fresh(['currentStage', 'creator', 'bank', 'merchant']);
        }, 5);
    }
}
