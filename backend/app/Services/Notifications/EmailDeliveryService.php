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
 * rendering, recipient resolution, or sending — those belong to
 * {@see SendEmailNotification}. Its job is the two-phase reserve/finalize
 * lifecycle plus terminal status writes from {@see SendEmailDelivery}.
 */
class EmailDeliveryService
{
    /** Six U+2022 BULLET — the mask stored in place of any redacted secret. */
    private const REDACTION_MASK = '••••••';

    private const REDACTED_SECRET_PLACEHOLDER = '[redacted-secret]';

    private const REDACTED_URL_PLACEHOLDER = '[redacted-url]';

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
        if ($recipientUserId === null && $this->isRedacted($type)) {
            return null;
        }

        try {
            $delivery = EmailDelivery::query()->create([
                'notification_type' => $type->value,
                'event_id' => $eventId,
                'recipient_user_id' => $recipientUserId,
                'recipient_email' => $recipientEmail,
                'channel' => $channel,
                'queued_at' => now(),
            ]);

            $delivery->forceFill([
                'status' => EmailDeliveryStatus::QUEUED,
            ])->save();

            return $delivery;
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
    public function finalize(
        EmailDelivery $delivery,
        string $renderedSubject,
        string $renderedBody,
        ?int $templateVersionId = null,
    ): EmailDelivery {
        $type = $this->typeOf($delivery);
        $isRedacted = $type !== null && $this->isRedacted($type);

        if ($isRedacted) {
            $renderedSubject = $this->maskSecrets($renderedSubject);
            $renderedBody = $this->maskSecrets($renderedBody);
        }

        $delivery->forceFill([
            'rendered_subject' => $renderedSubject,
            'rendered_body' => $renderedBody,
            'template_version_id' => $templateVersionId,
        ])->save();

        return $delivery;
    }

    public function markSent(EmailDelivery $delivery, ?string $providerMessageId = null): EmailDelivery
    {
        // Terminal-state guard: never overwrite a row that already reached a
        // terminal status (a late retry/webhook must not flip sent → failed etc.).
        if ($delivery->status->isTerminal()) {
            return $delivery;
        }

        $delivery->forceFill([
            'status' => EmailDeliveryStatus::SENT,
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId,
        ])->save();

        return $delivery;
    }

    public function markFailed(EmailDelivery $delivery, string $error): EmailDelivery
    {
        // A successful delivery must never be downgraded to failed (e.g. when a
        // concurrent attempt sent it and this attempt later exhausts its retries).
        if ($delivery->status === EmailDeliveryStatus::SENT) {
            return $delivery;
        }

        $delivery->forceFill([
            'status' => EmailDeliveryStatus::FAILED,
            'error' => $error,
            'failed_at' => now(),
        ])->save();

        return $delivery;
    }

    /**
     * Atomically claim a queued row for transport. Returns true only for the
     * single caller that wins the claim; a retry after a mid-send worker crash
     * finds dispatched_at already set and returns false, so the message is never
     * sent twice. Status stays `queued` until markSent/markFailed.
     */
    public function claimForSending(EmailDelivery $delivery): bool
    {
        $claimed = EmailDelivery::query()
            ->whereKey($delivery->getKey())
            ->whereNull('dispatched_at')
            ->update(['dispatched_at' => now()]);

        return $claimed === 1;
    }

    /**
     * Release a reserved-but-unfinalized row so its idempotency key
     * (event_id, recipient_user_id, channel) is not permanently consumed by a
     * render/finalize failure — otherwise that recipient could never be re-sent.
     */
    public function release(EmailDelivery $delivery): void
    {
        if ($delivery->status === EmailDeliveryStatus::QUEUED) {
            $delivery->delete();
        }
    }

    /** Apply the redacted-type secret masking to an arbitrary string (e.g. an error message bound for logs/audit). */
    public function redact(string $value): string
    {
        return $this->maskSecrets($value);
    }

    private function isRedacted(NotificationType $type): bool
    {
        return $this->registry->for($type)['persist_body'] === 'redacted';
    }

    /**
     * Defense in depth for redacted types: strip signed URLs, token-like values,
     * recovery-secret labels, and OTP/reset-code digit runs before persistence.
     */
    private function maskSecrets(string $value): string
    {
        $value = (string) preg_replace_callback(
            '~https?://[^\s"\'<>]+~i',
            static function (array $matches): string {
                $url = $matches[0];

                return preg_match('/[?&](?:token|signature|expires|otp|secret|reset_token)=/i', $url) === 1
                    ? self::REDACTED_URL_PLACEHOLDER
                    : $url;
            },
            $value
        );

        $value = (string) preg_replace(
            '/\b((?:password[-_\s]*)?reset[-_\s]*token|token|recovery[-_\s]*secret|backup[-_\s]*code|secret)\b\s*[:=]\s*[A-Za-z0-9+\/_=.-]{6,}/iu',
            '$1: '.self::REDACTED_SECRET_PLACEHOLDER,
            $value
        );

        // Bare high-entropy token/base64 runs (16+ chars containing BOTH a letter
        // and a digit). Includes base64 punctuation (+ / =) and is not anchored on
        // \b so signed-URL/base64 fragments are caught; plain words/Arabic text
        // (no digit) are left intact.
        $value = (string) preg_replace_callback(
            '~[A-Za-z0-9+/=_-]{16,}~',
            static function (array $matches): string {
                $token = $matches[0];

                return preg_match('/[A-Za-z]/', $token) === 1 && preg_match('/\d/', $token) === 1
                    ? self::REDACTED_SECRET_PLACEHOLDER
                    : $token;
            },
            $value
        );

        // OTP/reset-code digit runs (4+ digits). Unicode-aware (\p{Nd} covers
        // Arabic-Indic and Extended Arabic-Indic numerals — the product is
        // Arabic-first) and tolerant of single space/dot/hyphen separators so a
        // grouped code ("4 8 2 9 1 3", "482-913") is also masked.
        return (string) preg_replace('/\p{Nd}(?:[\s.\-]?\p{Nd}){3,}/u', self::REDACTION_MASK, $value);
    }

    /**
     * The DB column is a plain string for forward-compat; an unknown/removed type
     * value yields null rather than throwing, so finalize() degrades safely
     * (treats it as non-redacted) instead of failing the snapshot write.
     */
    private function typeOf(EmailDelivery $delivery): ?NotificationType
    {
        return NotificationType::tryFrom($delivery->notification_type);
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
            || str_contains($driverMessage, 'UNIQUE constraint failed')
            || str_contains($driverMessage, 'Duplicate entry');
    }
}
