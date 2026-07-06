<?php

namespace Tests\Unit\Support;

use App\Services\Workflow\Engine\EngineFinancingLedger;
use App\Support\InvoiceKey;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceKeyTest extends TestCase
{
    #[DataProvider('normalizeCases')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, InvoiceKey::normalize($input));
    }

    public function test_ledger_normalize_key_delegates_to_invoice_key(): void
    {
        $this->assertSame(InvoiceKey::normalize('  INV-42  '), EngineFinancingLedger::normalizeKey('  INV-42  '));
    }

    public function test_inv1_variants_collapse_to_same_key(): void
    {
        $this->assertSame(InvoiceKey::normalize('INV-1'), InvoiceKey::normalize('inv-1'));
        $this->assertSame('INV-1', InvoiceKey::normalize('INV-1'));
        $this->assertSame('INV-1', InvoiceKey::normalize('inv-1'));
        $this->assertSame('INV 1', InvoiceKey::normalize('INV 1'));
        $this->assertSame('INV 1', InvoiceKey::normalize('inv 1'));
        $this->assertSame('INV 1', InvoiceKey::normalize('INV  1'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizeCases(): array
    {
        return [
            'unchanged uppercase' => ['INV-001',          'INV-001'],
            'lowercased to upper' => ['inv-001',          'INV-001'],
            'leading space' => ['  INV-001',         'INV-001'],
            'trailing space' => ['INV-001  ',         'INV-001'],
            'both sides mixed case' => [" \tinv-001\n",      'INV-001'],
            'internal multi-space' => ['INV  001',          'INV 001'],
            'internal tab' => ["INV\t001",          'INV 001'],
            'lowercase internal space' => ['inv 1',             'INV 1'],
            'mixed case internal spaces' => ['  Inv  1  ',        'INV 1'],
            'empty' => ['',                  ''],
            'whitespace only' => ['   ',               ''],
        ];
    }
}
