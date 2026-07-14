<?php

namespace App\Services\Workflow;

use App\DTOs\SubmissionResult;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\FieldType;
use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Exceptions\EngineException;
use App\Http\Resources\EngineRequestResource;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\FieldDefinition;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowVersion;
use App\Services\Audit\AuditService;
use App\Services\Authorization\DataScope;
use App\Services\Documents\TemporaryUploadPromotionService;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Support\RequestCreationGate;
use App\Support\SubmitTransitionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deferred-creation submission: no EngineRequest row exists before this
 * call succeeds. One idempotent, strictly-validated operation creates the
 * request, promotes its temporary uploads to permanent documents, and
 * executes the initial transition together — see docs/architecture/
 * 02-workflow-engine.md for the full design and the plan history behind it.
 *
 * Controllers stay thin: this is the entire lifecycle, called with just the
 * validated request body, the Idempotency-Key header value, and the actor.
 */
class EngineRequestSubmissionService
{
    private const OPERATION = IdempotencyCoordinator::OPERATION_ENGINE_REQUEST_CREATE;

    public function __construct(
        private IdempotencyCoordinator $idempotency,
        private TemporaryUploadEvidenceResolver $evidenceResolver,
        private TemporaryUploadReservationService $reservations,
        private TemporaryUploadPromotionService $promotion,
        private StageFieldRuleValidator $fieldRuleValidator,
        private StagePermissionResolver $permissionResolver,
        private RequestProjectionSync $projectionSync,
        private DuplicateInvoiceChecker $duplicateChecker,
        private EngineNotificationDispatcher $notificationDispatcher,
        private EngineTransitionService $transitionService,
        private AuditService $auditService,
        private EngineRequestReferenceAllocator $referenceAllocator,
    ) {}

    public function submit(array $data, ?string $idempotencyKey, User $actor): SubmissionResult
    {
        if ($idempotencyKey === null || trim($idempotencyKey) === '') {
            throw EngineException::idempotencyKeyRequired();
        }

        $leaseSeconds = (int) config('retention.submission_lease_seconds');
        $fingerprint = $this->fingerprint($data);

        // Correction 1: idempotency precedes duplicate-invoice evaluation.
        $claim = $this->idempotency->claim($actor, self::OPERATION, $idempotencyKey, $fingerprint, $leaseSeconds);

        if ($claim->isReplay()) {
            return SubmissionResult::fromStored($claim->key);
        }
        if ($claim->isInProgress()) {
            return SubmissionResult::inProgress();
        }

        // isClaimed(): a fresh attempt now owns this key. Everything below
        // either completes it (COMPLETED) or deletes it (deterministic
        // rejection) — never left dangling in PROCESSING by this attempt.
        $keyId = $claim->key->id;
        $claimToken = $claim->claimToken;

        try {
            return $this->submitClaimed($data, $actor, $keyId, $claimToken, $leaseSeconds);
        } catch (EngineException $e) {
            // Deterministic pre-creation rejection: release reservations (if
            // any were made) before deleting the claim, both claim-token
            // guarded so a since-superseded attempt's cleanup can't affect a
            // reclaiming attempt.
            $this->idempotency->deleteClaim($keyId, $claimToken);
            throw $e;
        }
    }

    private function submitClaimed(array $data, User $actor, int $keyId, string $claimToken, int $leaseSeconds): SubmissionResult
    {
        $version = WorkflowVersion::findOrFail($data['workflow_version_id']);

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

        // Correction 1: duplicate-invoice hard-block runs only after a fresh
        // claim was won — no reservation/promotion work wasted on a request
        // that will be rejected outright regardless of its files.
        $incomingInvoiceNumber = $data['data']['invoice_number'] ?? null;
        if ($incomingInvoiceNumber !== null) {
            $precheck = $this->duplicateChecker->check($incomingInvoiceNumber);
            if ($precheck !== null && $precheck['severity'] === 'block') {
                throw EngineException::duplicateInvoiceBlocked();
            }
        }

        if (isset($data['merchant_id'])) {
            $merchant = Merchant::find($data['merchant_id']);
            if ($merchant === null) {
                throw new EngineException('Merchant not found.', 'MERCHANT_NOT_FOUND', 422);
            }
            if ($actor->bank_id === null || (int) $merchant->bank_id !== (int) $actor->bank_id) {
                throw EngineException::merchantOutOfScope();
            }
        }

        if ($initialStage->requires_claim) {
            throw EngineException::initialStageRequiresClaimUnsupported();
        }

        $transitions = $version->transitions()->get();
        $resolvedTransition = SubmitTransitionResolver::resolve($transitions, $initialStage->id);
        if ($resolvedTransition === null) {
            throw EngineException::initialStageNoAdvancingSubmit();
        }

        if (isset($data['diagnostic_transition_id'])
            && (int) $data['diagnostic_transition_id'] !== (int) $resolvedTransition->id) {
            throw EngineException::transitionResolutionMismatch();
        }

        $fields = FieldDefinition::query()->where('workflow_version_id', $version->id)->get();
        $fileFields = $fields->filter(fn (FieldDefinition $f) => $f->type === FieldType::FILE);
        $nonFileFields = $fields->reject(fn (FieldDefinition $f) => $f->type === FieldType::FILE);
        $rules = $initialStage->stageFieldRules()->get()->keyBy('field_id');

        $submittedData = $data['data'] ?? [];
        $nonFileErrors = $this->fieldRuleValidator->validateData(
            $nonFileFields, $rules, $submittedData, [], true, $actor, null,
        );

        $uploadTokens = $data['upload_tokens'] ?? [];
        $resolvedUploads = $fileFields->isEmpty() && $uploadTokens === []
            ? []
            : $this->evidenceResolver->resolve($version, $submittedData, $uploadTokens, $actor);

        if ($nonFileErrors !== []) {
            throw EngineException::stageFieldsInvalid($nonFileErrors);
        }

        /** @var list<int> $tokenIds */
        $tokenIds = collect($resolvedUploads)->flatten()->pluck('id')->all();

        $this->reservations->reserve($tokenIds, $keyId, $claimToken, $leaseSeconds);

        // Correction 3: allocate the reference now — after reservation,
        // before lease renewal #1 and before any filesystem promotion —
        // so a promotion failure can never leave an allocated-but-unused
        // reference stranded mid-flow. A later rollback still leaves a
        // harmless gap (EngineRequestReferenceAllocator's documented
        // reasoning), never a reused number.
        $reference = $this->referenceAllocator->allocate();

        if (! $this->reservations->renew($tokenIds, $keyId, $claimToken, $leaseSeconds)
            || ! $this->idempotency->renewLease($keyId, $claimToken, $leaseSeconds)) {
            throw EngineException::submissionLeaseLost();
        }

        $promotedPaths = [];
        $promotedDocuments = []; // fieldKey => [['upload' => TemporaryUpload, 'path' => string]]

        try {
            foreach ($resolvedUploads as $fieldKey => $uploads) {
                foreach ($uploads as $upload) {
                    $path = $this->promotion->promote($upload);
                    $promotedPaths[] = $path;
                    $promotedDocuments[$fieldKey][] = ['upload' => $upload, 'path' => $path];
                }
            }
        } catch (EngineException $e) {
            $this->promotion->compensate($promotedPaths);
            $this->reservations->release($tokenIds, $keyId, $claimToken);
            throw $e;
        }

        if (! $this->reservations->renew($tokenIds, $keyId, $claimToken, $leaseSeconds)
            || ! $this->idempotency->renewLease($keyId, $claimToken, $leaseSeconds)) {
            $this->promotion->compensate($promotedPaths);
            $this->reservations->release($tokenIds, $keyId, $claimToken);
            throw EngineException::submissionLeaseLost();
        }

        try {
            $result = DB::transaction(function () use (
                $version, $initialStage, $resolvedTransition, $actor, $reference,
                $submittedData, $nonFileFields, $promotedDocuments, $keyId, $claimToken,
                $tokenIds, $data,
            ) {
                if (! $this->idempotency->verifyStillOwned($keyId, $claimToken)) {
                    throw EngineException::submissionLeaseLost();
                }

                $nonFileData = array_intersect_key($submittedData, array_flip($nonFileFields->pluck('key')->all()));

                $request = EngineRequest::create([
                    'reference' => $reference,
                    'workflow_version_id' => $version->id,
                    'current_stage_id' => $initialStage->id,
                    'stage_entered_at' => now(),
                    'status' => 'ACTIVE',
                    'created_by' => $actor->id,
                    'bank_id' => $actor->bank_id,
                    'merchant_id' => $data['merchant_id'] ?? null,
                    'data' => $nonFileData,
                    'version' => 1,
                ]);

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

                $canonicalData = $nonFileData;
                foreach ($promotedDocuments as $fieldKey => $entries) {
                    $ids = [];
                    foreach ($entries as $entry) {
                        $upload = $entry['upload'];
                        $doc = EngineRequestDocument::create([
                            'request_id' => $request->id,
                            'field_id' => $upload->field_id,
                            'uploaded_by' => $upload->user_id,
                            'stage_id' => $initialStage->id,
                            'original_name' => $upload->original_name,
                            'path' => $entry['path'],
                            'mime' => $upload->mime,
                            'size' => $upload->size,
                            'checksum' => $upload->checksum,
                            'scan_status' => $upload->scan_status,
                            'status' => DocumentStatus::Active,
                        ]);

                        $this->auditService->log(
                            AuditAction::DOCUMENT_UPLOADED,
                            $actor,
                            $doc,
                            ['request_id' => $request->id, 'original_name' => $doc->original_name],
                            workflowInstanceId: $request->id,
                        );

                        $ids[] = $doc->id;
                    }
                    $canonicalData[$fieldKey] = count($ids) === 1 && ! is_array($submittedData[$fieldKey] ?? null)
                        ? $ids[0]
                        : $ids;
                }

                $request->forceFill(['data' => $canonicalData])->save();
                $this->projectionSync->sync($request);

                $comment = $data['comment'] ?? null;
                $this->transitionService->execute(
                    $request, $resolvedTransition->id, $comment, $canonicalData, $request->version, $actor,
                );

                $request->refresh();
                if ((int) $request->current_stage_id === (int) $initialStage->id) {
                    throw EngineException::submissionDidNotAdvanceInitialStage();
                }

                if (! $this->reservations->consume($tokenIds, $keyId, $claimToken)) {
                    throw EngineException::submissionLeaseLost();
                }

                // Correction 2: compute the exact final response — including
                // the masked duplicate-invoice warning — now, before marking
                // COMPLETED, so the stored response_body is byte-identical to
                // what the caller receives and to every future replay.
                $unmaskedWarning = null;
                $responseBody = [
                    'success' => true,
                    'message' => 'Request created successfully.',
                    'data' => (new EngineRequestResource($request))->resolve(),
                ];

                $invoiceNumber = $request->invoice_number;
                if ($invoiceNumber !== null) {
                    $warning = $this->duplicateChecker->check($invoiceNumber, $request->id);
                    if ($warning !== null) {
                        $unmaskedWarning = $warning;
                        $responseBody['warnings'] = [$this->maskDuplicates($warning, $actor)];
                    }
                }

                if (! $this->idempotency->complete($keyId, $claimToken, $request->id, 201, $responseBody)) {
                    throw EngineException::submissionLeaseLost();
                }

                if ($unmaskedWarning !== null) {
                    DB::afterCommit(function () use ($request, $invoiceNumber, $unmaskedWarning) {
                        $this->notificationDispatcher->afterDuplicateInvoice(
                            $request->id,
                            (string) $request->reference,
                            $invoiceNumber,
                            $unmaskedWarning['duplicates'],
                        );
                    });
                }

                return [$request, $responseBody];
            }, 5);
        } catch (EngineException $e) {
            $this->promotion->compensate($promotedPaths);
            $this->reservations->release($tokenIds, $keyId, $claimToken);
            throw $e;
        }

        [$request, $responseBody] = $result;

        DB::afterCommit(function () use ($promotedDocuments) {
            foreach ($promotedDocuments as $entries) {
                foreach ($entries as $entry) {
                    Storage::disk('private-tmp')->delete($entry['upload']->path);
                }
            }
        });

        return SubmissionResult::created($responseBody);
    }

    private function fingerprint(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /** Same masking rule the controller used before this logic moved here. */
    private function maskDuplicates(array $warning, User $user): array
    {
        $scope = DataScope::forUser($user);

        if ($scope->systemWide) {
            return $warning;
        }

        $warning['duplicates'] = array_map(function ($dup) use ($scope) {
            if ($dup['bank_id'] === $scope->ownBankId) {
                return $dup;
            }

            return [
                'id' => null,
                'reference' => 'طلب مكرر في مؤسسة أخرى',
                'bank_id' => null,
            ];
        }, $warning['duplicates']);

        return $warning;
    }
}
