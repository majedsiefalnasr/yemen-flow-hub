<?php

/**
 * WP-6 auth hardening fallbacks (WP-11 consumes the same keys when registered).
 * AuthSecuritySettings reads system_settings when present, else these values.
 */
return [
    'mfa_required' => false,
    'login_lockout_attempts' => 5,
    'login_lockout_duration' => 15,
    'trusted_device_ttl_hours' => 24,
    'step_up_window_minutes' => 10,
    'password_history_count' => 4,
    'password_min_length' => 8,
    'trusted_device_cookie' => 'trusted_device',
    'blacklisted_passwords' => [
        'password',
        'password1',
        'password123',
        '12345678',
        '123456789',
        'qwerty123',
        'admin123',
        'letmein1',
        'welcome1',
        'changeme',
        'yemenflow1',
    ],
];
