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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class EngineRequestService
{
    public function __construct(
        private StagePermissionResolver $permissionResolver,
        private StageFieldRuleValidator $fieldRuleValidator,
        private RequestProjectionSync $projectionSync,
        private AuditService $auditService,
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

        // API-003: retry the whole transaction (not just the insert) on an
        // InnoDB deadlock / lock-wait timeout. Under true concurrent creation,
        // parallel inserts into the same unique-reference index race and MySQL
        // aborts one side with a 1213 deadlock — which rolls the transaction
        // back, so createWithUniqueReference()'s inner 1062 retry cannot help
        // (its transaction is already dead). Laravel's transaction($cb, $attempts)
        // re-runs the closure on a detected concurrency error (1213/1205) only,
        // not on a duplicate key (1062) — so the two retry layers compose:
        // outer handles aborted transactions, inner handles reference collision.
        // Proven by php artisan perf:load-scenario --concurrency (see
        // docs/audit/evidence/API-003-reference-allocator.md).
        return DB::transaction(function () use ($version, $initialStage, $data, $actor, $resolvedBankId) {
            // ARCH-002: the request enters its initial stage now; stamp the same
            // timestamp into the projection column and the CREATE history row so
            // SLA timing works from creation, not only after the first transition.
            $enteredAt = now();

            // DB-001/DB-002 follow-up: see EngineTransitionService::execute() for
            // why this is maintained alongside stage_entered_at.
            $slaDeadlineEpoch = $initialStage->sla_duration_minutes !== null
                ? $enteredAt->getTimestamp() + ((int) $initialStage->sla_duration_minutes * 60)
                : null;

            $request = $this->createWithUniqueReference([
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

    /**
     * Insert the request with a unique reference, retrying on a duplicate-reference
     * collision. The reference is derived from the current max sequence rather than a
     * row count (count drifts on soft-delete), and concurrent creators that compute the
     * same sequence are resolved by the unique-constraint retry instead of a 500.
     *
     * API-003: the sequence is derived from MAX(CAST(numeric suffix AS UNSIGNED)),
     * not MAX(reference) — a lexicographic string MAX mis-orders a 7-digit suffix
     * below any existing 6-digit one ('1000000' < '999999' as strings), which
     * would permanently mis-derive the sequence once a yearly count crosses
     * 999999. The numeric cast is correct at any digit width.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createWithUniqueReference(array $attributes): EngineRequest
    {
        $year = now()->year;
        $prefix = "ENG-{$year}-";

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $maxSequence = DB::table('engine_requests')
                ->where('reference', 'like', $prefix.'%')
                ->max(DB::raw('CAST(SUBSTRING(reference, '.(strlen($prefix) + 1).') AS UNSIGNED)'));

            $sequence = $maxSequence !== null ? ((int) $maxSequence) + 1 : 1;

            $reference = sprintf('ENG-%d-%06d', $year, $sequence + $attempt);

            try {
                return EngineRequest::create($attributes + ['reference' => $reference]);
            } catch (QueryException $e) {
                if (! $this->isDuplicateKey($e)) {
                    throw $e;
                }
                // Lost the race for this reference — recompute and retry.
            }
        }

        throw new EngineException('Could not allocate a unique request reference.', 'REFERENCE_ALLOCATION_FAILED', 500);
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062
            || str_contains($e->getMessage(), 'Integrity constraint violation');
    }
}
