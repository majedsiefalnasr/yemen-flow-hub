<?php

namespace Tests\Unit\Enums;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_all_8_canonical_values_exist(): void
    {
        $expected = [
            'DATA_ENTRY',
            'BANK_REVIEWER',
            'BANK_ADMIN',
            'SWIFT_OFFICER',
            'SUPPORT_COMMITTEE',
            'EXECUTIVE_MEMBER',
            'COMMITTEE_DIRECTOR',
            'CBY_ADMIN',
        ];

        $actual = array_column(UserRole::cases(), 'value');

        $this->assertCount(8, $actual);
        foreach ($expected as $value) {
            $this->assertContains($value, $actual, "Missing canonical role: {$value}");
        }
    }

    public function test_non_canonical_roles_do_not_exist(): void
    {
        $nonCanonical = ['BANK_MANAGER', 'EXECUTIVE_DIRECTOR'];
        $actual = array_column(UserRole::cases(), 'value');

        foreach ($nonCanonical as $value) {
            $this->assertNotContains($value, $actual, "Non-canonical role must not exist: {$value}");
        }
    }

    public function test_from_string_round_trips(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertSame($role, UserRole::from($role->value));
        }
    }
}
