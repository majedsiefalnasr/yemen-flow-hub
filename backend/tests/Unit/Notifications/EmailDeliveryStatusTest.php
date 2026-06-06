<?php

namespace Tests\Unit\Notifications;

use Tests\TestCase;

/**
 * ATDD RED PHASE — Story 15.1 Outbox & Delivery Foundation.
 *
 * Guardrail for the EmailDeliveryStatus enum (AC1.4).
 * Fails until App\Enums\EmailDeliveryStatus exists.
 *
 * @group atdd-15-1
 */
class EmailDeliveryStatusTest extends TestCase
{
    /** T4 — status enum has exactly queued/sent/failed/bounced (AC1.4). */
    public function test_status_enum_cases(): void
    {
        $enum = 'App\\Enums\\EmailDeliveryStatus';
        $this->assertTrue(enum_exists($enum));

        $values = array_map(fn ($c) => $c->value, $enum::cases());
        sort($values);
        $this->assertSame(['bounced', 'failed', 'queued', 'sent'], $values);
    }
}
