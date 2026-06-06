<?php

namespace App\Support;

/**
 * Builds the canonical, retry-stable idempotency `event_id` for outbound emails.
 *
 * The whole point is stability across queue retries: a retried job must reproduce
 * the SAME event_id so the outbox unique index dedups it (see EmailDeliveryService).
 * NEVER derive these ids from a wall-clock timestamp or a fresh random per send.
 */
final class EmailEventId
{
    /**
     * Workflow-triggered email: stable per (request, destination status), distinct per real transition.
     *
     * Example: forWorkflow(42, 'BANK_APPROVED') => "42:BANK_APPROVED".
     */
    public static function forWorkflow(int $requestId, string $toStatus): string
    {
        return $requestId.':'.$toStatus;
    }

    /**
     * Auth/OTP email: tied to a single OTP issuance (NOT a send attempt).
     *
     * The issuance id is minted once per OTP issuance (Story 15.5 wires MfaService to
     * mint/store it). A new issuance → new id → a new email is allowed; a retry of the
     * same send → same id → dedup.
     *
     * Example: forAuth('MFA_OTP', '9f1c...') => "MFA_OTP:9f1c...".
     */
    public static function forAuth(string $type, string $issuanceId): string
    {
        return $type.':'.$issuanceId;
    }
}
