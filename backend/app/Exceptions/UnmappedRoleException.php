<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a governance role code has no legacy UserRole mapping. The legacy
 * `users.role` column still drives authorization during the transition, so an
 * unmapped code must be rejected (fail closed) rather than defaulting to a
 * privileged role. Translated to a `ROLE_NOT_MAPPED` 422 response.
 */
class UnmappedRoleException extends RuntimeException
{
    public function __construct(public readonly string $roleCode)
    {
        parent::__construct("Role code [{$roleCode}] has no legacy mapping.");
    }
}
