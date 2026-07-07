<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a governance role code has no UserRole enum mapping for API serialization.
 * Translated to a `ROLE_NOT_MAPPED` 422 response.
 */
class UnmappedRoleException extends RuntimeException
{
    public function __construct(public readonly string $roleCode)
    {
        parent::__construct("Role code [{$roleCode}] has no UserRole mapping.");
    }
}
