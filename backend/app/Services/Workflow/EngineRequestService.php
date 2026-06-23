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

        if (isset($data['merchant_id'])) {
            $merchant = Merchant::find($data['merchant_id']);
            if ($merchant === null) {
                throw new EngineException('Merchant not found.', 'MERCHANT_NOT_FOUND', 422);
            }
            if ($actor->bank_id !== null && (int) $merchant->bank_id !== (int) $actor->bank_id) {
                throw EngineException::merchantOutOfScope();
            }
        }

        return DB::transaction(function () use ($version, $initialStage, $data, $actor) {
            $request = EngineRequest::create([
                'workflow_version_id' => $version->id,
                'current_stage_id' => $initialStage->id,
                'reference' => $this->generateReference(),
                'status' => 'ACTIVE',
                'created_by' => $actor->id,
                'bank_id' => $data['bank_id'] ?? $actor->bank_id,
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

    private function generateReference(): string
    {
        $year = now()->year;
        $sequence = DB::table('engine_requests')
            ->where('reference', 'like', "ENG-{$year}-%")
            ->count() + 1;

        $ref = sprintf('ENG-%d-%06d', $year, $sequence);

        while (EngineRequest::where('reference', $ref)->exists()) {
            $sequence++;
            $ref = sprintf('ENG-%d-%06d', $year, $sequence);
        }

        return $ref;
    }
}
