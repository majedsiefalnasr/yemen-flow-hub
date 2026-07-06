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
}
