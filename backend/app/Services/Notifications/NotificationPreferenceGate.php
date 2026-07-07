<?php

namespace App\Services\Notifications;

use App\Models\User;

class NotificationPreferenceGate
{
    /** @var list<string> */
    private const MANDATORY_TYPES = [
        'sla.breached',
        'permission.changed',
        'compliance.duplicate_invoice',
    ];

    /** @var list<string> */
    private const MANDATORY_PREFERENCE_KEYS = [
        'request_rejected',
        'request_returned',
        'request_approved',
    ];

    public function shouldDeliver(User $user, string $type, string $severity): bool
    {
        if ($this->isMandatoryType($type)) {
            return true;
        }

        if (str_starts_with($type, 'account.')) {
            return true;
        }

        $preferenceKey = $this->preferenceKeyFor($type, $severity);
        if ($preferenceKey === null) {
            return true;
        }

        if (in_array($preferenceKey, self::MANDATORY_PREFERENCE_KEYS, true)) {
            return true;
        }

        $preferences = $user->user_preferences['notification_preferences'] ?? [];

        return ($preferences[$preferenceKey] ?? true) === true;
    }

    private function isMandatoryType(string $type): bool
    {
        return in_array($type, self::MANDATORY_TYPES, true);
    }

    private function preferenceKeyFor(string $type, string $severity): ?string
    {
        return match ($type) {
            'transition' => $severity === 'info' ? 'request_submitted' : null,
            'claim.released' => 'claim_released',
            default => null,
        };
    }
}
