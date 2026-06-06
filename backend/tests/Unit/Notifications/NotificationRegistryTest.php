<?php

namespace Tests\Unit\Notifications;

use Tests\TestCase;

/**
 * ATDD RED PHASE — Story 15.1 Outbox & Delivery Foundation.
 *
 * Guardrails for NotificationType enum + NotificationRegistry (AC2).
 * Fails until App\Enums\NotificationType and
 * App\Services\Notifications\NotificationRegistry exist.
 *
 * @group atdd-15-1
 */
class NotificationRegistryTest extends TestCase
{
    private const FROZEN_KEYS = [
        'channels',
        'admin_editable',
        'persist_body',
        'source',
        'recipient_roles',
        'allowed_variables',
    ];

    private const PHASE1_TYPES = [
        'REQUEST_APPROVED',
        'REQUEST_REJECTED',
        'REQUEST_RETURNED',
        'VOTING_OPENED',
        'MFA_OTP',
        'PASSWORD_RESET',
    ];

    /** T5 — enum has the 6 Phase-1 cases, string-backed, SCREAMING_SNAKE (AC2.5). */
    public function test_notification_type_has_phase1_cases(): void
    {
        $enum = 'App\\Enums\\NotificationType';
        $this->assertTrue(enum_exists($enum));

        $cases = array_map(fn ($c) => $c->name, $enum::cases());
        foreach (self::PHASE1_TYPES as $type) {
            $this->assertContains($type, $cases, "Missing NotificationType case: {$type}");
            // String-backed value mirrors the case name.
            $this->assertSame($type, constant("{$enum}::{$type}")->value);
        }
    }

    /** T6 — every registered type provides the complete frozen key set (AC2.6, AC2.7). */
    public function test_every_type_has_complete_frozen_key_set(): void
    {
        $registry = app('App\\Services\\Notifications\\NotificationRegistry');
        $enum = 'App\\Enums\\NotificationType';

        foreach ($enum::cases() as $case) {
            $config = $registry->for($case); // typed accessor or array with guaranteed shape
            $keys = is_array($config) ? array_keys($config) : array_keys((array) $config);

            foreach (self::FROZEN_KEYS as $key) {
                $this->assertContains($key, $keys, "Type {$case->name} missing frozen key: {$key}");
            }
        }
    }

    /** T7 — locked registry values per AC2.9 table. */
    public function test_locked_registry_values(): void
    {
        $registry = app('App\\Services\\Notifications\\NotificationRegistry');
        $enum = 'App\\Enums\\NotificationType';

        $approved = (array) $registry->for(constant("{$enum}::REQUEST_APPROVED"));
        $this->assertSame(['database', 'mail'], $approved['channels']);
        $this->assertTrue($approved['admin_editable']);
        $this->assertSame('full', $approved['persist_body']);
        $this->assertSame('db', $approved['source']);

        $mfa = (array) $registry->for(constant("{$enum}::MFA_OTP"));
        $this->assertSame(['mail'], $mfa['channels']);
        $this->assertFalse($mfa['admin_editable']);
        $this->assertSame('redacted', $mfa['persist_body']);
        $this->assertSame('blade', $mfa['source']);

        $reset = (array) $registry->for(constant("{$enum}::PASSWORD_RESET"));
        $this->assertSame('redacted', $reset['persist_body']);
        $this->assertSame('blade', $reset['source']);
        $this->assertFalse($reset['admin_editable']);
    }

    /** T8 — registry rejects an unknown / partial type (AC2.7, AC2.8). */
    public function test_registry_rejects_unknown_type(): void
    {
        $registry = app('App\\Services\\Notifications\\NotificationRegistry');
        $this->assertFalse($registry->isRegistered('NOT_A_REAL_TYPE'));

        $this->expectException(\Throwable::class);
        $registry->for('NOT_A_REAL_TYPE');
    }
}
