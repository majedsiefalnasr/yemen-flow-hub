<?php

namespace Tests\Unit\Enums;

use App\Enums\CoverageType;
use App\Enums\CurrencySource;
use App\Enums\Incoterm;
use App\Enums\InvoiceType;
use App\Enums\PaymentTermsMode;
use App\Enums\PortOfArrival;
use App\Enums\RequestType;
use PHPUnit\Framework\TestCase;

class NewEnumsLabelTest extends TestCase
{
    public function test_all_new_enum_cases_have_bilingual_labels(): void
    {
        $enumClasses = [
            RequestType::class,
            CoverageType::class,
            CurrencySource::class,
            PaymentTermsMode::class,
            InvoiceType::class,
            PortOfArrival::class,
            Incoterm::class,
        ];

        foreach ($enumClasses as $enumClass) {
            foreach ($enumClass::cases() as $case) {
                $this->assertMatchesRegularExpression('/.+\\s\\/\\s.+/', $case->label(), "{$enumClass}::{$case->name} must be bilingual");
                $this->assertSame(strtoupper($case->value), $case->value, "{$enumClass}::{$case->name} value must be SCREAMING_SNAKE_CASE");
            }
        }
    }

    public function test_coverage_type_has_exactly_full_and_partial(): void
    {
        $this->assertSame(['FULL', 'PARTIAL'], array_column(CoverageType::cases(), 'value'));
    }
}
