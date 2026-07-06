<?php

namespace App\DTOs\Authorization;

readonly class DataScopeContext
{
    public function __construct(
        public bool $systemWide,
        public ?int $ownBankId = null,
    ) {}
}
