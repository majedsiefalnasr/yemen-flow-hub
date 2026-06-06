<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use InvalidArgumentException;

/**
 * Pure, I/O-free registry of the Phase-1 notification types (Epic 15, Story 15.1).
 *
 * Every {@see NotificationType} case MUST be declared here with the full frozen key
 * set; a partial entry throws. These values are the contract every later Epic-15
 * story plugs into (recipient resolution, template source, redaction, channels).
 *
 * The registry performs NO rendering, recipient resolution, or sending. `for()`
 * returns a guaranteed-shape associative array keyed by the frozen key set.
 */
class NotificationRegistry
{
    /** The frozen key set every registry entry must declare in full. */
    public const KEYS = [
        'channels',
        'admin_editable',
        'persist_body',
        'source',
        'recipient_roles',
        'allowed_variables',
    ];

    /** Variables shared by the three editable request-lifecycle emails. */
    private const REQUEST_VARIABLES = [
        'reference_number',
        'bank_name',
        'importer_name',
        'amount',
        'currency',
        'status',
        'action_url',
        'user_name',
    ];

    /** Variables shared by the OTP/reset (redacted) emails. */
    private const OTP_VARIABLES = ['user_name', 'otp_code', 'ttl_minutes'];

    /**
     * Raw locked configuration (AC2 table). Keep in lockstep with the story —
     * changing these values changes the behavior of every later Epic-15 story.
     *
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            NotificationType::REQUEST_APPROVED->value => [
                'channels' => ['database', 'mail'],
                'admin_editable' => true,
                'persist_body' => 'full',
                'source' => 'db',
                'recipient_roles' => [UserRole::DATA_ENTRY, UserRole::BANK_REVIEWER],
                'allowed_variables' => self::REQUEST_VARIABLES,
            ],
            NotificationType::REQUEST_REJECTED->value => [
                'channels' => ['database', 'mail'],
                'admin_editable' => true,
                'persist_body' => 'full',
                'source' => 'db',
                'recipient_roles' => [UserRole::DATA_ENTRY, UserRole::BANK_REVIEWER],
                'allowed_variables' => self::REQUEST_VARIABLES,
            ],
            NotificationType::REQUEST_RETURNED->value => [
                'channels' => ['database', 'mail'],
                'admin_editable' => true,
                'persist_body' => 'full',
                'source' => 'db',
                'recipient_roles' => [UserRole::DATA_ENTRY],
                'allowed_variables' => self::REQUEST_VARIABLES,
            ],
            NotificationType::VOTING_OPENED->value => [
                'channels' => ['database', 'mail'],
                'admin_editable' => false,
                'persist_body' => 'full',
                'source' => 'blade',
                'recipient_roles' => [UserRole::EXECUTIVE_MEMBER, UserRole::COMMITTEE_DIRECTOR],
                'allowed_variables' => ['reference_number', 'amount', 'currency', 'action_url'],
            ],
            NotificationType::MFA_OTP->value => [
                'channels' => ['mail'],
                'admin_editable' => false,
                'persist_body' => 'redacted',
                'source' => 'blade',
                'recipient_roles' => [],
                'allowed_variables' => self::OTP_VARIABLES,
            ],
            NotificationType::PASSWORD_RESET->value => [
                'channels' => ['mail'],
                'admin_editable' => false,
                'persist_body' => 'redacted',
                'source' => 'blade',
                'recipient_roles' => [],
                'allowed_variables' => self::OTP_VARIABLES,
            ],
        ];
    }

    /**
     * Whether a type is registered. Accepts a {@see NotificationType} or its raw
     * string value; an unknown string is simply not registered (no throw).
     */
    public function isRegistered(NotificationType|string $type): bool
    {
        $value = $type instanceof NotificationType ? $type->value : $type;

        return array_key_exists($value, $this->definitions());
    }

    /**
     * Guaranteed-shape config for a type, keyed by the frozen key set (self::KEYS).
     *
     * @return array{
     *     channels: array<int, string>,
     *     admin_editable: bool,
     *     persist_body: 'full'|'redacted',
     *     source: 'db'|'blade',
     *     recipient_roles: array<int, UserRole>,
     *     allowed_variables: array<int, string>
     * }
     *
     * @throws InvalidArgumentException When the type is unregistered or its entry is partial.
     */
    public function for(NotificationType|string $type): array
    {
        $value = $type instanceof NotificationType ? $type->value : $type;
        $definition = $this->definitions()[$value] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Notification type [{$value}] is not registered.");
        }

        $this->assertComplete($value, $definition);

        return $definition;
    }

    /**
     * Every registered type's config, keyed by enum value.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $configs = [];

        foreach (NotificationType::cases() as $type) {
            $configs[$type->value] = $this->for($type);
        }

        return $configs;
    }

    /**
     * Reject partial entries: the entry must declare exactly the frozen key set.
     *
     * @param  array<string, mixed>  $definition
     *
     * @throws InvalidArgumentException
     */
    private function assertComplete(string $value, array $definition): void
    {
        $keys = array_keys($definition);
        sort($keys);

        $expected = self::KEYS;
        sort($expected);

        if ($keys !== $expected) {
            throw new InvalidArgumentException(
                "Notification type [{$value}] has an incomplete registry entry. ".
                'Expected keys: '.implode(', ', self::KEYS).'.'
            );
        }
    }
}
