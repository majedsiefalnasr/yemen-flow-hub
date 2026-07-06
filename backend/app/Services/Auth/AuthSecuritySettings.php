<?php

namespace App\Services\Auth;

use App\Models\SystemSetting;

class AuthSecuritySettings
{
    public function mfaRequired(): bool
    {
        return (bool) $this->resolve('mfa_required', 'auth_security.mfa_required');
    }

    public function lockoutAttempts(): int
    {
        return max(1, (int) $this->resolve('login_lockout_attempts', 'auth_security.login_lockout_attempts'));
    }

    public function lockoutDurationMinutes(): int
    {
        return max(1, (int) $this->resolve('login_lockout_duration', 'auth_security.login_lockout_duration'));
    }

    public function trustedDeviceTtlHours(): int
    {
        return max(1, (int) $this->resolve('trusted_device_ttl_hours', 'auth_security.trusted_device_ttl_hours'));
    }

    public function stepUpWindowMinutes(): int
    {
        return max(1, (int) $this->resolve('step_up_window_minutes', 'auth_security.step_up_window_minutes'));
    }

    public function passwordHistoryCount(): int
    {
        return max(0, (int) $this->resolve('password_history_count', 'auth_security.password_history_count'));
    }

    public function passwordMinLength(): int
    {
        return max(8, (int) $this->resolve('password_min_length', 'auth_security.password_min_length'));
    }

    private function resolve(string $settingKey, string $configKey): mixed
    {
        $stored = SystemSetting::findByKey($settingKey);

        if ($stored !== null) {
            return $stored->value;
        }

        return config($configKey);
    }
}
