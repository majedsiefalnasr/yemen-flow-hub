<?php

namespace Tests\Unit\Notifications;

use Tests\TestCase;

/**
 * ATDD RED PHASE — Story 15.1 Outbox & Delivery Foundation.
 *
 * Guardrail for NotificationType naming convention (AC2.5).
 * Fails until App\Enums\NotificationType exists.
 *
 * @group atdd-15-1
 */
class NotificationTypeTest extends TestCase
{
    /** T5 — all cases are SCREAMING_SNAKE and string-backed mirroring the case name. */
    public function test_cases_are_screaming_snake_string_backed(): void
    {
        $enum = 'App\\Enums\\NotificationType';
        $this->assertTrue(enum_exists($enum));

        foreach ($enum::cases() as $case) {
            $this->assertMatchesRegularExpression(
                '/^[A-Z][A-Z0-9_]*$/',
                $case->name,
                "NotificationType case {$case->name} is not SCREAMING_SNAKE."
            );
            $this->assertSame($case->name, $case->value, 'Backed value must mirror the case name.');
        }
    }
}
