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
     * Workflow-triggered email: stable per real transition, distinct across transitions.
     *
     * The optional transition id (the request_stage_history row id for this
     * transition) disambiguates a request that re-enters the SAME status more than
     * once — e.g. BANK_RETURNED → resubmitted → BANK_RETURNED again — so the second
     * legitimate email is not suppressed as a duplicate. It is stable across queue
     * retries of the same transition (the same stage-history row), preserving dedup.
     * When no transition id is available the id collapses to the legacy
     * "{request}:{status}" form.
     *
     * Example: forWorkflow(42, 'BANK_APPROVED', 7) => "42:BANK_APPROVED:7".
     */
    public static function forWorkflow(int $requestId, string $toStatus, ?int $transitionId = null): string
    {
        $base = $requestId.':'.$toStatus;

        return $transitionId === null ? $base : $base.':'.$transitionId;
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
