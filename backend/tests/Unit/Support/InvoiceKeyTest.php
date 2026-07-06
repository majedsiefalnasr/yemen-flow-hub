<?php

namespace Tests\Unit\Support;

use App\Services\Workflow\Engine\EngineFinancingLedger;
use App\Support\InvoiceKey;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceKeyTest extends TestCase
{
    #[DataProvider('normalizeCases')]
    public function test_normalize_trims_whitespace(string $input, string $expected): void
    {
        $this->assertSame($expected, InvoiceKey::normalize($input));
    }

    public function test_ledger_normalize_key_delegates_to_invoice_key(): void
    {
        $this->assertSame(InvoiceKey::normalize('  INV-42  '), EngineFinancingLedger::normalizeKey('  INV-42  '));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizeCases(): array
    {
        return [
            'unchanged' => ['INV-001', 'INV-001'],
            'leading space' => ['  INV-001', 'INV-001'],
            'trailing space' => ['INV-001  ', 'INV-001'],
            'both sides' => [" \tINV-001\n", 'INV-001'],
            'empty' => ['', ''],
            'whitespace only' => ['   ', ''],
        ];
    }
}
