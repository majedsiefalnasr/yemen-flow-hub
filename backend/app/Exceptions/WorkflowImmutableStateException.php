<?php

namespace App\Exceptions;

use App\Enums\RequestStatus;
use RuntimeException;

class WorkflowImmutableStateException extends RuntimeException
{
    public function __construct(public readonly RequestStatus $currentStatus)
    {
        parent::__construct('Request is in a terminal state and cannot be modified.');
    }
}
