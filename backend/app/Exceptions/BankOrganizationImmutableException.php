<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside a locked bank update transaction when a caller attempts to change
 * `organization_id` on a bank that is already in use. Translated to a
 * `BANK_ORGANIZATION_IMMUTABLE` 422 response.
 */
class BankOrganizationImmutableException extends RuntimeException {}
