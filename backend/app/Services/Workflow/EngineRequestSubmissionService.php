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
use App\Services\Operations\OperationalAlertLogger;
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
        private StageFieldOutputFilter $stageFieldOutputFilter,
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
            return SubmissionResult::inProgress($claim->retryAfterSeconds ?? 1);
        }

        // isClaimed(): a fresh attempt now owns this key. Everything below
        // either completes it (COMPLETED) or deletes it (deterministic
        // rejection) — never left dangling in PROCESSING by this attempt.
        $keyId = $claim->key->id;
        $claimToken = $claim->claimToken;

        try {
            return $this->submitClaimed($data, $actor, $keyId, $claimToken, $leaseSeconds);
        } catch (\Throwable $e) {
            // Deterministic pre-creation rejection (or any other pre-commit
            // failure — submitClaimed()'s own inner catches already released
            // reservations/compensated files before rethrowing): delete the
            // claim so a clean retry can reclaim it immediately rather than
            // waiting out the abandoned-PROCESSING purge margin. Claim-token
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

        // Defensive: WorkflowVersionValidator blocks publishing an initial
        // stage with a multiple:true FILE field (INITIAL_STAGE_UNSUPPORTED_
        // MULTI_FILE_FIELD), since useTemporaryUploadLifecycle.ts tracks one
        // upload entry per field key. This still checks at submission time
        // rather than trusting that guard alone — a version published before
        // that rule existed, or edited in a way the validator didn't
        // re-run against, must not silently reach the (single-file) upload
        // lifecycle and corrupt tracking.
        $hasUnsupportedMultiFileField = $this->stageFieldOutputFilter
            ->visibleFieldsForStage($version->id, $initialStage)
            ->contains(fn (FieldDefinition $field) => $field->type === FieldType::FILE && $field->multiple);
        if ($hasUnsupportedMultiFileField) {
            throw EngineException::initialStageUnsupportedMultiFileField();
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
        } catch (\Throwable $e) {
            // Correction 4: compensate on ANY pre-commit failure, not only
            // EngineException — a driver error, OOM, or other unexpected
            // throwable must not leak a promoted file or a stale reservation.
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
                    // Correction 3: recipient/audience resolution (reads) runs
                    // now, before commit — only the actual notification
                    // dispatch is deferred, inside afterDuplicateInvoice()'s
                    // own dispatchAfterCommit() call. Do not wrap this whole
                    // method in an outer afterCommit(): that would defer its
                    // DB reads too, and a read-only failure here has nothing
                    // to do with whether the request was created.
                    $this->notificationDispatcher->afterDuplicateInvoice(
                        $request->id,
                        (string) $request->reference,
                        $invoiceNumber,
                        $unmaskedWarning['duplicates'],
                    );
                }

                return [$request, $responseBody];
            }, 5);
        } catch (\Throwable $e) {
            // Correction 4: DB::transaction() already rolled back the DB side
            // of a failed attempt regardless of exception type — but the
            // filesystem promotion and the reservation rows are NOT part of
            // that DB transaction (deliberately: cross-disk file writes are
            // compensated explicitly, never claimed as atomic with the DB).
            // Compensate on any pre-commit throwable here too, not only
            // EngineException. This catch can only see a pre-commit failure:
            // the one post-commit callback registered inside the closure
            // (the duplicate-invoice notification dispatch) already catches
            // and logs its own throwables internally and never rethrows, so
            // it can never reach this catch and trigger a delete of files
            // that were already committed.
            $this->promotion->compensate($promotedPaths);
            $this->reservations->release($tokenIds, $keyId, $claimToken);
            throw $e;
        }

        [$request, $responseBody] = $result;

        DB::afterCommit(function () use ($promotedDocuments) {
            $disk = Storage::disk('private-tmp');
            foreach ($promotedDocuments as $entries) {
                foreach ($entries as $entry) {
                    $path = $entry['upload']->path;
                    // The TemporaryUpload row already has consumed_at set
                    // (committed above) — a delete failure here leaves a
                    // ROW-BACKED orphan, not an untracked one, so it's still
                    // recoverable by the scheduled sweep's consumed-missed-
                    // callback branch. Still worth logging: this IS the
                    // normal cleanup path, and a failure here means that
                    // sweep is the only thing standing between this file and
                    // permanent leakage.
                    if ($disk->exists($path) && ! $disk->delete($path)) {
                        OperationalAlertLogger::failure(
                            'temporary_upload_submission_cleanup',
                            new \RuntimeException("Failed to delete promoted temporary upload file after commit: {$path}"),
                            ['path' => $path, 'temporary_upload_id' => $entry['upload']->id],
                        );
                    }
                }
            }
        });

        return SubmissionResult::created($responseBody);
    }

    private function fingerprint(array $data): string
    {
        return hash('sha256', json_encode($this->canonicalize($data), JSON_THROW_ON_ERROR));
    }

    /**
     * Recursively sort associative-array keys so two payloads that differ
     * only in nested object key order fingerprint identically — a client
     * re-encoding the same logical request must never trip
     * IDEMPOTENCY_KEY_REUSED. Sequential-integer-keyed (list) arrays keep
     * their element order: [1, 2] and [2, 1] are genuinely different data.
     */
    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $canonicalized = array_map($this->canonicalize(...), $value);

        if (! $isList) {
            ksort($canonicalized);
        }

        return $canonicalized;
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
