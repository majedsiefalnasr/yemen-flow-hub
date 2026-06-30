<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a workflow design element cannot be mutated because it is bound to
 * downstream config or runtime data (e.g. a stage referenced by a transition or a
 * request). Callers translate it into a 422 response carrying `errorCode`.
 */
class WorkflowDesignProtectionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
