<?php

namespace App\Exceptions;

use RuntimeException;

class DirectStatusMutationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Direct mutation of current_status is forbidden. Use WorkflowService::transition() instead.'
        );
    }
}
