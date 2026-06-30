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
        );
        if ($fieldErrors !== []) {
            throw EngineException::stageFieldsInvalid($fieldErrors);
        }

        $resolvedBankId = $actor->bank_id ?? ($data['bank_id'] ?? null);

        if (isset($data['merchant_id'])) {
            $merchant = Merchant::find($data['merchant_id']);
            if ($merchant === null) {
                throw new EngineException('Merchant not found.', 'MERCHANT_NOT_FOUND', 422);
            }
            // The merchant must belong to the request's resolved bank. This guards
            // both bank users (resolved bank = their bank) and CBY users who select
            // a bank explicitly — preventing a merchant being bound to a foreign bank.
            if ($resolvedBankId !== null && (int) $merchant->bank_id !== (int) $resolvedBankId) {
                throw EngineException::merchantOutOfScope();
            }
        }

        return DB::transaction(function () use ($version, $initialStage, $data, $actor, $resolvedBankId) {
            $request = $this->createWithUniqueReference([
                'workflow_version_id' => $version->id,
                'current_stage_id' => $initialStage->id,
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
                'created_at' => now(),
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
        });
    }

    /**
     * Insert the request with a unique reference, retrying on a duplicate-reference
     * collision. The reference is derived from the current max sequence rather than a
     * row count (count drifts on soft-delete), and concurrent creators that compute the
     * same sequence are resolved by the unique-constraint retry instead of a 500.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createWithUniqueReference(array $attributes): EngineRequest
    {
        $year = now()->year;
        $prefix = "ENG-{$year}-";

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $maxReference = DB::table('engine_requests')
                ->where('reference', 'like', $prefix.'%')
                ->max('reference');

            $sequence = $maxReference !== null
                ? ((int) substr($maxReference, strlen($prefix))) + 1
                : 1;

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
