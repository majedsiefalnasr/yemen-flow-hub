<?php

namespace App\Services\Notifications;

use App\Enums\EmailDeliveryStatus;
use App\Enums\NotificationType;
use App\Models\EmailDelivery;
use Illuminate\Database\QueryException;

/**
 * Persistence-only writer for the `email_deliveries` outbox (Epic 15, Story 15.1).
 *
 * This service is the ONLY writer of `email_deliveries` rows. It performs no
 * rendering, recipient resolution, or sending — those belong to the future
 * SendEmailNotification orchestrator (Story 15.4). Its job is the two-phase
 * reserve/finalize lifecycle plus terminal status writes.
 */
class EmailDeliveryService
{
    /** Six U+2022 BULLET — the mask stored in place of any redacted secret. */
    private const REDACTION_MASK = '••••••';

    /** SQLSTATE for an integrity-constraint (incl. unique) violation, shared by MySQL and SQLite. */
    private const SQLSTATE_INTEGRITY_VIOLATION = '23000';

    /** MySQL driver error code for a duplicate-key (unique) violation. */
    private const MYSQL_DUPLICATE_ENTRY = 1062;

    public function __construct(private readonly NotificationRegistry $registry) {}

    /**
     * Reserve an outbox slot for one send.
     *
     * Insert-first by design: the idempotency guarantee comes from the DB unique
     * index on (event_id, recipient_user_id, channel), NOT a race-prone exists()
     * pre-check. A duplicate insert is caught and yields null (no second send).
     *
     * NULL-idempotency guard: MySQL/SQLite treat NULL as distinct in a unique
     * index, so a redacted/auth type (MFA_OTP, PASSWORD_RESET) reserved with a
     * null recipient_user_id could be delivered twice. For those types we require
     * a resolved user id and refuse the reservation (return null, no insert).
     */
    public function reserve(
        NotificationType $type,
        string $eventId,
        ?int $recipientUserId,
        string $recipientEmail,
        string $channel,
    ): ?EmailDelivery {
        if ($recipientUserId === null && $this->requiresResolvedUserId($type)) {
            return null;
        }

        try {
            return EmailDelivery::query()->create([
                'notification_type' => $type->value,
                'event_id' => $eventId,
                'recipient_user_id' => $recipientUserId,
                'recipient_email' => $recipientEmail,
                'channel' => $channel,
                'status' => EmailDeliveryStatus::QUEUED,
                'queued_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Write the rendered snapshot, applying redaction per the registry persist_body.
     *
     * - full     → store subject/body as-is.
     * - redacted → store a masked render only (defense in depth: any OTP-shaped
     *   digit run is masked, so a live code can never be persisted). Status stays queued.
     */
    public function finalize(EmailDelivery $delivery, string $renderedSubject, string $renderedBody): EmailDelivery
    {
        if ($this->isRedacted($this->typeOf($delivery))) {
            $renderedSubject = $this->maskSecrets($renderedSubject);
            $renderedBody = $this->maskSecrets($renderedBody);
        }

        $delivery->forceFill([
            'rendered_subject' => $renderedSubject,
            'rendered_body' => $renderedBody,
        ])->save();

        return $delivery;
    }

    public function markSent(EmailDelivery $delivery, ?string $providerMessageId = null): EmailDelivery
    {
        $delivery->forceFill([
            'status' => EmailDeliveryStatus::SENT,
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId,
        ])->save();

        return $delivery;
    }

    public function markFailed(EmailDelivery $delivery, string $error): EmailDelivery
    {
        $delivery->forceFill([
            'status' => EmailDeliveryStatus::FAILED,
            'error' => $error,
        ])->save();

        return $delivery;
    }

    /** Redacted (auth/security) types require a resolved recipient_user_id to dedup safely. */
    private function requiresResolvedUserId(NotificationType $type): bool
    {
        return $this->isRedacted($type);
    }

    private function isRedacted(NotificationType $type): bool
    {
        return $this->registry->for($type)['persist_body'] === 'redacted';
    }

    /**
     * Defense in depth for redacted types: replace any run of 4+ digits (OTP/reset
     * code shape) with the bullet mask so a live code is never stored, even if a
     * caller mistakenly passes an unmasked body.
     */
    private function maskSecrets(string $value): string
    {
        return (string) preg_replace('/\d{4,}/', self::REDACTION_MASK, $value);
    }

    private function typeOf(EmailDelivery $delivery): NotificationType
    {
        return NotificationType::from($delivery->notification_type);
    }

    /**
     * Narrowly detect a UNIQUE-constraint violation (the idempotency collision)
     * without masking other integrity errors as a silent dedup. Both MySQL and
     * SQLite report SQLSTATE 23000; we disambiguate via the driver code/message.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        if ((string) $e->getCode() !== self::SQLSTATE_INTEGRITY_VIOLATION) {
            return false;
        }

        $driverCode = $e->errorInfo[1] ?? null;
        $driverMessage = (string) ($e->errorInfo[2] ?? $e->getMessage());

        return $driverCode === self::MYSQL_DUPLICATE_ENTRY
            || stripos($driverMessage, 'UNIQUE constraint failed') !== false
            || stripos($driverMessage, 'Duplicate entry') !== false;
    }
}
