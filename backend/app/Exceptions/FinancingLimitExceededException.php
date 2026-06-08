<?php

namespace App\Exceptions;

use RuntimeException;

class FinancingLimitExceededException extends RuntimeException
{
    public const ERROR_CODE = 'FINANCING_LIMIT_EXCEEDED';

    public function __construct(
        string $message = 'Global financing limit for this invoice key would be exceeded.',
        public readonly float $usedPercent = 0.0,
        public readonly float $requestedPercent = 0.0,
    ) {
        parent::__construct($message);
    }
}
