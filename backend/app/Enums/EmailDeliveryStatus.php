<?php

namespace App\Enums;

/**
 * Lifecycle status of an outbox row in `email_deliveries`.
 *
 * The DB column is a plain string for forward-compat; this enum is the
 * application-layer source of truth for the allowed values.
 */
enum EmailDeliveryStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::SENT, self::FAILED], true);
    }
}
