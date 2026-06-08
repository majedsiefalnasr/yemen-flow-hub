<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when the financing invoice-key named lock cannot be acquired within the
 * timeout (contention). Surfaced as a retryable 409 rather than an opaque 500
 * (code-review 17-D).
 */
class FinancingLockTimeoutException extends RuntimeException
{
    public const ERROR_CODE = 'FINANCING_LOCK_TIMEOUT';

    public function __construct(
        string $message = 'The financing record is busy. Please retry in a moment.',
    ) {
        parent::__construct($message);
    }
}
