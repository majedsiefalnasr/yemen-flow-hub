<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an edit/clone target is a non-DRAFT workflow version. PUBLISHED and
 * ARCHIVED versions are frozen runnable config; callers translate this into a
 * `WORKFLOW_IMMUTABLE_STATE` 409 response.
 */
class WorkflowVersionImmutableException extends RuntimeException
{
    public string $errorCode = 'WORKFLOW_IMMUTABLE_STATE';
}
