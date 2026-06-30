<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a publish attempt fails validate-before-publish (FR-WD9). Carries the
 * displayable, field-tagged error list; callers translate it into a 422 response.
 */
class WorkflowVersionValidationException extends RuntimeException
{
    /**
     * @param  array<int, array{code: string, target: string, message: string}>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The workflow version is not valid for publishing.');
    }
}
