<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside a locked governance update transaction when the client-supplied
 * optimistic-lock `version` no longer matches the freshly locked row. Callers
 * translate it into a `STALE_RESOURCE` 409 response.
 */
class StaleResourceException extends RuntimeException {}
