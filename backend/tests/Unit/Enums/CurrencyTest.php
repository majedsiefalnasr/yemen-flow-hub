<?php

namespace Tests\Unit\Enums;

use App\Enums\Currency;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    public function test_all_5_canonical_values_exist(): void
    {
        $expected = ['USD', 'EUR', 'SAR', 'AED', 'CNY'];
        $actual = array_column(Currency::cases(), 'value');

        $this->assertCount(5, $actual);
        foreach ($expected as $value) {
            $this->assertContains($value, $actual, "Missing Currency: {$value}");
        }
    }

    public function test_from_string_round_trips(): void
    {
        foreach (Currency::cases() as $currency) {
            $this->assertSame($currency, Currency::from($currency->value));
        }
    }
}
