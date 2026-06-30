<?php

namespace Tests\Unit\Enums;

use App\Enums\VotingSessionStatus;
use PHPUnit\Framework\TestCase;

class VotingSessionStatusTest extends TestCase
{
    public function test_all_3_canonical_values_exist(): void
    {
        $expected = ['OPEN', 'CLOSED', 'FINALIZED'];
        $actual = array_column(VotingSessionStatus::cases(), 'value');

        $this->assertCount(3, $actual);
        foreach ($expected as $value) {
            $this->assertContains($value, $actual, "Missing VotingSessionStatus: {$value}");
        }
    }

    public function test_from_string_round_trips(): void
    {
        foreach (VotingSessionStatus::cases() as $status) {
            $this->assertSame($status, VotingSessionStatus::from($status->value));
        }
    }
}
