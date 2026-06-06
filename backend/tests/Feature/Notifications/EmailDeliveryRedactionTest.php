<?php

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * ATDD RED PHASE — Story 15.1 Outbox & Delivery Foundation.
 *
 * Guardrails for redaction (AC4) and secrets-never-persisted (AC3.12).
 *
 * For persist_body=redacted types (MFA_OTP, PASSWORD_RESET) the outbox must
 * store a MASKED body (six U+2022 BULLET) and never the live one-time code,
 * and the code must never reach the logs.
 *
 * @group atdd-15-1
 */
class EmailDeliveryRedactionTest extends TestCase
{
    use RefreshDatabase;

    private const MASK = '••••••';

    private const LIVE_CODE = '482913';

    private function service()
    {
        return app('App\\Services\\Notifications\\EmailDeliveryService');
    }

    private function mfaType()
    {
        // MFA_OTP: persist_body redacted.
        return constant('App\\Enums\\NotificationType::MFA_OTP');
    }

    /**
     * T14 — finalize() stores the masked body, never the live code (AC4.13).
     *
     * The caller (Story 15.5) is expected to pass an already-masked render for
     * redacted types. This test pins the END STATE: whatever is stored must
     * contain the mask and must NOT contain the live code.
     */
    public function test_finalize_redacted_stores_mask_not_code(): void
    {
        // recipient_user_id is REQUIRED for auth types (see NULL guardrail test).
        $delivery = $this->service()->reserve($this->mfaType(), 'MFA_OTP:issuance-1', 7, 'user@example.com', 'mail');

        // Caller supplies the masked render for storage.
        $maskedSubject = 'رمز التحقق الخاص بك';
        $maskedBody = 'رمزك هو '.self::MASK.' وينتهي خلال 10 دقائق';

        $this->service()->finalize($delivery, $maskedSubject, $maskedBody);

        $stored = $delivery->fresh();
        $this->assertStringContainsString(self::MASK, $stored->rendered_body);
        $this->assertStringNotContainsString(self::LIVE_CODE, (string) $stored->rendered_body);
        $this->assertStringNotContainsString(self::LIVE_CODE, (string) $stored->rendered_subject);
    }

    /**
     * T15 — DEFENSE IN DEPTH: even if a caller mistakenly passes a body that
     * still contains the live code, finalize() for a redacted type must NOT
     * persist the raw code (it masks/strips it). The outbox row is the
     * permanent audit artifact — a leak here is unrecoverable.
     */
    public function test_finalize_redacted_never_persists_raw_code_even_if_caller_leaks(): void
    {
        $delivery = $this->service()->reserve($this->mfaType(), 'MFA_OTP:issuance-2', 7, 'user@example.com', 'mail');

        // Caller mistake: body still carries the live code.
        $leakyBody = 'رمزك هو '.self::LIVE_CODE.' وينتهي خلال 10 دقائق';

        $this->service()->finalize($delivery, 'رمز التحقق', $leakyBody);

        $stored = $delivery->fresh();
        $this->assertStringNotContainsString(
            self::LIVE_CODE,
            (string) $stored->rendered_body,
            'A redacted type must never persist the live code, even on caller error.'
        );
    }

    /** T16 — the live code is never written to the application log (AC3.12). */
    public function test_live_code_never_logged(): void
    {
        $spy = [];
        Log::listen(function ($message) use (&$spy): void {
            $spy[] = is_string($message) ? $message : json_encode($message);
        });

        $delivery = $this->service()->reserve($this->mfaType(), 'MFA_OTP:issuance-3', 7, 'user@example.com', 'mail');
        $this->service()->finalize($delivery, 'رمز التحقق', 'رمزك هو '.self::MASK);
        $this->service()->markSent($delivery, 'provider-xyz');

        foreach ($spy as $line) {
            $this->assertStringNotContainsString(self::LIVE_CODE, (string) $line, 'OTP code leaked into logs.');
        }
    }
}
