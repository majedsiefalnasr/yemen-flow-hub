<?php

namespace Tests\Unit\Notifications;

use Tests\TestCase;

/**
 * ATDD RED PHASE — Story 15.1 Outbox & Delivery Foundation.
 *
 * Guardrail for the dedicated `emails` queue connection (AC7).
 *
 * The `emails` connection MUST set after_commit=true so that email jobs
 * dispatched onto it (Story 15.4) only fire after the surrounding workflow DB
 * transaction commits — never emailing about a rolled-back transition. The
 * existing `default` redis connection must stay untouched (after_commit false).
 *
 * @group atdd-15-1
 */
class EmailsQueueConfigTest extends TestCase
{
    /** T26 — emails connection exists, redis driver, queue name emails, after_commit=true. */
    public function test_emails_connection_has_after_commit_true(): void
    {
        $conn = config('queue.connections.emails');
        $this->assertIsArray($conn, 'queue.connections.emails must be defined.');
        $this->assertSame('redis', $conn['driver']);
        $this->assertSame('emails', $conn['queue']);
        $this->assertTrue($conn['after_commit'] ?? false, 'emails connection must set after_commit=true.');
    }

    /** T27 — default connection left unchanged (after_commit not flipped to true). */
    public function test_default_connection_after_commit_unchanged(): void
    {
        $default = config('queue.connections.redis');
        $this->assertIsArray($default);
        $this->assertNotTrue(
            $default['after_commit'] ?? false,
            'Do not globally flip after_commit on the default redis connection.'
        );
    }
}
