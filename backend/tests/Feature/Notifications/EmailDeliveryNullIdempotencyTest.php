<?php

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ATDD RED PHASE — Story 15.1 Outbox & Delivery Foundation.
 *
 * ★ CRITICAL GUARDRAIL: MySQL NULL-in-unique-index behavior ★
 *
 * MySQL (and SQLite) treat NULL as DISTINCT inside a unique index. So a unique
 * index on (event_id, recipient_user_id, channel) does NOT deduplicate two rows
 * that share event_id + channel but both have recipient_user_id = NULL. Relying
 * on the index alone would let an auth/security email (MFA_OTP, PASSWORD_RESET)
 * be delivered twice whenever the recipient user id is null.
 *
 * Therefore the SERVICE must compensate: for auth/security notification types,
 * reserve() must REQUIRE a resolved recipient_user_id and must not silently
 * permit a duplicate auth delivery via a null id.
 *
 * These tests pin that contract. They fail until EmailDeliveryService enforces
 * the resolved-user-id rule for redacted/auth types.
 *
 * @group atdd-15-1
 */
class EmailDeliveryNullIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function service()
    {
        return app('App\\Services\\Notifications\\EmailDeliveryService');
    }

    private function mfaType()
    {
        return constant('App\\Enums\\NotificationType::MFA_OTP');
    }

    private function workflowType()
    {
        return constant('App\\Enums\\NotificationType::REQUEST_APPROVED');
    }

    /**
     * T21 — POSITIVE: duplicate reserve WITH a resolved recipient_user_id
     * deduplicates correctly. This is the path auth emails MUST use.
     */
    public function test_duplicate_reserve_with_resolved_user_id_deduplicates(): void
    {
        $first = $this->service()->reserve($this->mfaType(), 'MFA_OTP:issuance-1', 7, 'user@example.com', 'mail');
        $this->assertNotNull($first);

        $second = $this->service()->reserve($this->mfaType(), 'MFA_OTP:issuance-1', 7, 'user@example.com', 'mail');
        $this->assertNull($second, 'Same issuance + resolved user id must dedup.');

        $this->assertDatabaseCount('email_deliveries', 1);
    }

    /**
     * T22 — NEGATIVE / CORE GUARDRAIL: an auth/security type reserved with a
     * NULL recipient_user_id must be GUARDED. The service must not silently
     * accept it (which would let the DB's NULL-distinct semantics permit a
     * duplicate auth delivery). Acceptable implementations: throw, or return
     * null without inserting. It must NOT create a row that could later be
     * duplicated.
     */
    public function test_auth_type_with_null_user_id_is_guarded_not_silently_accepted(): void
    {
        $threw = false;
        $result = null;
        try {
            $result = $this->service()->reserve($this->mfaType(), 'MFA_OTP:issuance-x', null, 'user@example.com', 'mail');
        } catch (\Throwable $e) {
            $threw = true;
        }

        // Either it threw, or it returned null — but it must NOT have inserted a row.
        $this->assertTrue($threw || $result === null, 'Auth type with null user id must be guarded.');
        $this->assertDatabaseCount('email_deliveries', 0);
    }

    /**
     * T22b — PROVE THE DB ALONE IS INSUFFICIENT: two raw inserts with the same
     * (event_id, channel) but NULL user_id both succeed at the DB layer. This
     * documents WHY the service-level guard (T22/T23) is required.
     */
    public function test_db_unique_index_does_not_dedup_null_user_id(): void
    {
        $now = now();
        $row = [
            'notification_type' => 'MFA_OTP',
            'event_id' => 'MFA_OTP:issuance-y',
            'recipient_user_id' => null,
            'recipient_email' => 'user@example.com',
            'channel' => 'mail',
            'status' => 'queued',
            'queued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        \DB::table('email_deliveries')->insert($row);
        \DB::table('email_deliveries')->insert($row); // NOT rejected — NULLs are distinct

        $this->assertDatabaseCount('email_deliveries', 2);
        // ↑ This is the hazard the service-level guard exists to neutralize.
    }

    /**
     * T23 — auth/security types (MFA_OTP, PASSWORD_RESET) REQUIRE a resolved
     * recipient_user_id; workflow types MAY allow a null user id (documented).
     */
    public function test_auth_types_require_resolved_user_id_workflow_types_may_not(): void
    {
        // Auth: null id rejected (no row).
        $mfa = null;
        try {
            $mfa = $this->service()->reserve($this->mfaType(), 'MFA_OTP:issuance-z', null, 'u@example.com', 'mail');
        } catch (\Throwable $e) {
            $mfa = null;
        }
        $this->assertNull($mfa);

        $reset = null;
        try {
            $resetType = constant('App\\Enums\\NotificationType::PASSWORD_RESET');
            $reset = $this->service()->reserve($resetType, 'PASSWORD_RESET:issuance-z', null, 'u@example.com', 'mail');
        } catch (\Throwable $e) {
            $reset = null;
        }
        $this->assertNull($reset);

        // No auth rows were inserted.
        $this->assertDatabaseCount('email_deliveries', 0);

        // Workflow type with a resolved id still works normally (sanity anchor).
        $ok = $this->service()->reserve($this->workflowType(), '42:BANK_APPROVED', 5, 'creator@example.com', 'mail');
        $this->assertNotNull($ok);
        $this->assertDatabaseCount('email_deliveries', 1);
    }
}
