<?php

namespace Tests\Unit\Notifications;

use App\Support\EmailEventId;
use Tests\TestCase;

/**
 * ATDD RED PHASE — Story 15.1 Outbox & Delivery Foundation.
 *
 * Guardrail for the idempotency event_id contract (AC5.15, AC5.16, AC5.17).
 * Fails until App\Support\EmailEventId exists.
 *
 * The auth format MUST be derived from a stable per-issuance id, NOT a
 * wall-clock timestamp or a fresh random per send. Otherwise a queue retry
 * of an OTP email would bypass dedup and double-send a one-time code.
 *
 * @group atdd-15-1
 */
class EmailEventIdTest extends TestCase
{
    /** T18 — workflow event_id = "{request_id}:{to_status}" (AC5.15). */
    public function test_workflow_event_id_format(): void
    {
        $id = EmailEventId::forWorkflow(42, 'BANK_APPROVED');
        $this->assertSame('42:BANK_APPROVED', $id);
    }

    /** T19 — auth event_id = "{type}:{issuance_id}" (AC5.16). */
    public function test_auth_event_id_format(): void
    {
        $issuanceId = '550e8400-e29b-41d4-a716-446655440000';
        $id = EmailEventId::forAuth('MFA_OTP', $issuanceId);
        $this->assertSame("MFA_OTP:{$issuanceId}", $id);
    }

    /**
     * T20 — auth event_id is STABLE for the same issuance, not timestamp/random.
     *
     * Two calls with the same (type, issuance_id) must yield the identical
     * string so a queue retry dedups. If the helper folded in time()/rand(),
     * these would differ.
     */
    public function test_auth_event_id_is_stable_for_same_issuance(): void
    {
        $issuanceId = 'fixed-issuance-uuid';
        $first = EmailEventId::forAuth('PASSWORD_RESET', $issuanceId);
        $second = EmailEventId::forAuth('PASSWORD_RESET', $issuanceId);

        $this->assertSame($first, $second, 'Auth event_id must be deterministic per issuance.');
        $this->assertStringNotContainsString((string) time(), $first, 'Auth event_id must not embed a timestamp.');
    }

    /** T20b — different issuance ids yield different event ids (new OTP => new send allowed). */
    public function test_distinct_issuance_yields_distinct_event_id(): void
    {
        $a = EmailEventId::forAuth('MFA_OTP', 'issuance-a');
        $b = EmailEventId::forAuth('MFA_OTP', 'issuance-b');
        $this->assertNotSame($a, $b);
    }
}
