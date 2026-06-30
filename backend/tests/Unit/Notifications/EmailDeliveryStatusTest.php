<?php

namespace Tests\Unit\Notifications;

use App\Enums\EmailDeliveryStatus;
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
    /** T4 — status enum has exactly queued/sent/failed (AC1.4). */
    public function test_status_enum_cases(): void
    {
        $values = array_map(fn (EmailDeliveryStatus $case) => $case->value, EmailDeliveryStatus::cases());
        sort($values);
        $this->assertSame(['failed', 'queued', 'sent'], $values);
    }

    public function test_only_sent_and_failed_are_terminal(): void
    {
        $this->assertFalse(EmailDeliveryStatus::QUEUED->isTerminal());
        $this->assertTrue(EmailDeliveryStatus::SENT->isTerminal());
        $this->assertTrue(EmailDeliveryStatus::FAILED->isTerminal());
    }
}
