<?php

namespace App\Exceptions;

use RuntimeException;

class WorkflowLockedStateException extends RuntimeException
{
    public function __construct(string $message = 'Request is in a locked state and cannot be modified.')
    {
        parent::__construct($message);
    }
}
