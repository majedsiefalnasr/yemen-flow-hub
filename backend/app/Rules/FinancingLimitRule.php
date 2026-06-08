<?php

namespace App\Rules;

use App\Exceptions\FinancingLimitExceededException;
use App\Services\FinancingLedgerService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FinancingLimitRule implements ValidationRule
{
    public function __construct(
        private readonly ?string $taxNumber,
        private readonly ?string $invoiceNumber,
        private readonly ?int $excludeRequestId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->taxNumber === null || $this->invoiceNumber === null || ! is_numeric($value)) {
            return;
        }

        if (app(FinancingLedgerService::class)->wouldExceed(
            $this->taxNumber,
            $this->invoiceNumber,
            (float) $value,
            $this->excludeRequestId,
        )) {
            throw new FinancingLimitExceededException;
        }
    }
}
