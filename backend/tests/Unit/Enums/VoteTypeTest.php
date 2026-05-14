<?php

namespace Tests\Unit\Enums;

use App\Enums\VoteType;
use PHPUnit\Framework\TestCase;

class VoteTypeTest extends TestCase
{
    public function test_all_4_canonical_values_exist(): void
    {
        $expected = ['APPROVE', 'REJECT', 'ABSTAIN', 'AUTO_ABSTAIN_TIMEOUT'];
        $actual = array_column(VoteType::cases(), 'value');

        $this->assertCount(4, $actual);
        foreach ($expected as $value) {
            $this->assertContains($value, $actual, "Missing VoteType: {$value}");
        }
    }

    public function test_from_string_round_trips(): void
    {
        foreach (VoteType::cases() as $type) {
            $this->assertSame($type, VoteType::from($type->value));
        }
    }
}
