<?php

namespace App\Support;

use App\Enums\FinalOutcome;

/**
 * Canonical engine request lifecycle statuses and shared terminal/eligibility sets.
 */
final class EngineRequestStatus
{
    public const ACTIVE = 'ACTIVE';

    public const CLOSED = 'CLOSED';

    public const REJECTED = 'REJECTED';

    public const CANCELLED = 'CANCELLED';

    public const ABANDONED = 'ABANDONED';

    /** @var list<string> */
    public const TERMINAL = [
        self::CLOSED,
        self::REJECTED,
        self::CANCELLED,
        self::ABANDONED,
    ];

    /** @var list<string> */
    public const CAPACITY_FREEING = [
        self::REJECTED,
        self::CANCELLED,
        self::ABANDONED,
    ];

    public static function fromFinalOutcome(?FinalOutcome $outcome): string
    {
        if ($outcome === null) {
            return self::CLOSED;
        }

        return match ($outcome) {
            FinalOutcome::COMPLETED => self::CLOSED,
            FinalOutcome::REJECTED => self::REJECTED,
            FinalOutcome::CANCELLED => self::CANCELLED,
            FinalOutcome::ABANDONED => self::ABANDONED,
        };
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }
}
