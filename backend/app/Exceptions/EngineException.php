<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class EngineException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $httpStatus = 422,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function render(): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ];

        if ($this->errors !== []) {
            $payload['errors'] = $this->errors;
        }

        return response()->json($payload, $this->httpStatus);
    }

    public static function stageExecutionForbidden(): self
    {
        return new self('You do not have execute permission on this stage.', 'STAGE_EXECUTION_FORBIDDEN', 403);
    }

    public static function stageFieldsInvalid(array $errors): self
    {
        return new self('Field validation failed.', 'STAGE_FIELDS_INVALID', 422, $errors);
    }

    public static function merchantOutOfScope(): self
    {
        return new self('Merchant does not belong to your bank.', 'MERCHANT_OUT_OF_SCOPE', 403);
    }

    public static function requestStale(): self
    {
        return new self('Request has been modified by another user.', 'REQUEST_STALE', 409);
    }

    public static function transitionNotAvailable(): self
    {
        return new self('This transition is not available from the current stage.', 'TRANSITION_NOT_AVAILABLE', 422);
    }

    public static function commentRequired(): self
    {
        return new self('A comment is required for this action.', 'COMMENT_REQUIRED', 422);
    }

    public static function requestClosed(): self
    {
        return new self('This request is closed and cannot be modified.', 'REQUEST_CLOSED', 403);
    }

    public static function versionNotPublished(): self
    {
        return new self('Workflow version is not published.', 'VERSION_NOT_PUBLISHED', 422);
    }

    public static function noInitialStage(): self
    {
        return new self('Workflow has no initial stage.', 'NO_INITIAL_STAGE', 422);
    }

    public static function creationNotAllowedForOrganization(): self
    {
        return new self(
            'Request creation is not allowed for this organization.',
            'CREATION_NOT_ALLOWED_FOR_ORGANIZATION',
            403,
        );
    }

    public static function stageClaimed(): self
    {
        return new self('This request is already being reviewed by another user.', 'STAGE_CLAIMED', 409);
    }

    public static function claimNotHeld(): self
    {
        return new self('You do not hold the claim on this request.', 'CLAIM_NOT_HELD', 403);
    }

    public static function abandonNotAvailable(): self
    {
        return new self('This request cannot be abandoned from its current stage.', 'ABANDON_NOT_AVAILABLE', 422);
    }

    // ── Deferred-creation submission (temporary uploads + idempotent submit) ──

    public static function uploadTokenMismatch(array $errors): self
    {
        return new self('Uploaded file references do not match the submitted data.', 'UPLOAD_TOKEN_MISMATCH', 422, $errors);
    }

    public static function uploadTokenInvalid(): self
    {
        return new self('One of the referenced uploads was not found.', 'UPLOAD_TOKEN_INVALID', 422);
    }

    public static function uploadTokenForbidden(): self
    {
        return new self('You do not have access to one of the referenced uploads.', 'UPLOAD_TOKEN_FORBIDDEN', 403);
    }

    public static function uploadTokenWrongField(): self
    {
        return new self('One of the referenced uploads is not linked to the field it was submitted under.', 'UPLOAD_TOKEN_WRONG_FIELD', 422);
    }

    public static function uploadTokenWrongWorkflow(): self
    {
        return new self('One of the referenced uploads does not belong to this workflow version.', 'UPLOAD_TOKEN_WRONG_WORKFLOW', 422);
    }

    public static function uploadTokenAlreadyConsumed(): self
    {
        return new self('One of the referenced uploads has already been used.', 'UPLOAD_TOKEN_ALREADY_CONSUMED', 422);
    }

    public static function uploadTokenExpired(): self
    {
        return new self('One of the referenced uploads has expired. Please upload the file again.', 'UPLOAD_TOKEN_EXPIRED', 422);
    }

    public static function uploadTokenReserved(): self
    {
        return new self('One of the referenced uploads is being used by another submission in progress.', 'UPLOAD_TOKEN_RESERVED', 409);
    }

    public static function scanInProgress(): self
    {
        return new self(
            'جارٍ فحص أحد الملفات المرفقة. يرجى الانتظار قليلاً ثم إعادة المحاولة.',
            'SCAN_IN_PROGRESS',
            422,
        );
    }

    public static function uploadNotSafe(): self
    {
        return new self(
            'تعذّر إرفاق أحد الملفات لأسباب تتعلق بالأمان. يرجى رفع الملف مرة أخرى.',
            'UPLOAD_NOT_SAFE',
            422,
        );
    }

    public static function uploadIntegrityMismatch(): self
    {
        return new self('File integrity could not be verified during submission.', 'UPLOAD_INTEGRITY_MISMATCH', 422);
    }

    public static function filePromotionFailed(): self
    {
        return new self('Could not store one of the uploaded files. Please try again.', 'FILE_PROMOTION_FAILED', 422);
    }

    public static function submissionLeaseLost(): self
    {
        return new self('This submission took too long and must be retried.', 'SUBMISSION_LEASE_LOST', 409);
    }

    public static function idempotencyKeyRequired(): self
    {
        return new self('An Idempotency-Key header is required.', 'IDEMPOTENCY_KEY_REQUIRED', 422);
    }

    public static function idempotencyKeyReused(): self
    {
        return new self('This idempotency key was already used with a different request payload.', 'IDEMPOTENCY_KEY_REUSED', 409);
    }

    public static function transitionResolutionMismatch(): self
    {
        return new self('The submission transition no longer matches the workflow definition.', 'TRANSITION_RESOLUTION_MISMATCH', 422);
    }

    public static function initialStageRequiresClaimUnsupported(): self
    {
        return new self('This workflow cannot accept submissions because its initial stage requires a claim.', 'INITIAL_STAGE_REQUIRES_CLAIM_UNSUPPORTED', 422);
    }

    public static function initialStageNoAdvancingSubmit(): self
    {
        return new self('This workflow has no unambiguous submit transition from its initial stage.', 'INITIAL_STAGE_NO_ADVANCING_SUBMIT', 422);
    }

    public static function duplicateInvoiceBlocked(): self
    {
        return new self(
            'This invoice number matches an existing active request and cannot be submitted.',
            'DUPLICATE_INVOICE_BLOCKED',
            422,
        );
    }

    /**
     * Thrown, never assert()ed, if a submission somehow committed a request
     * that never left its initial stage — forces a transaction rollback
     * unconditionally, in every environment, regardless of zend.assertions.
     */
    public static function submissionDidNotAdvanceInitialStage(): self
    {
        return new self(
            'Submission committed an EngineRequest that did not leave its initial stage.',
            'SUBMISSION_INVARIANT_VIOLATED',
            500,
        );
    }
}
