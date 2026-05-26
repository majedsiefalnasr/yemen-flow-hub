<?php

namespace Tests\Unit\Enums;

use App\Enums\DocumentType;
use PHPUnit\Framework\TestCase;

class DocumentTypeTest extends TestCase
{
    public function test_all_4_canonical_values_exist(): void
    {
        $expected = ['REQUEST_DOC', 'SWIFT', 'FX_REQUEST', 'CUSTOMS'];
        $actual = array_column(DocumentType::cases(), 'value');

        $this->assertCount(4, $actual);
        foreach ($expected as $value) {
            $this->assertContains($value, $actual, "Missing DocumentType: {$value}");
        }
    }

    public function test_from_string_round_trips(): void
    {
        foreach (DocumentType::cases() as $type) {
            $this->assertSame($type, DocumentType::from($type->value));
        }
    }
}
