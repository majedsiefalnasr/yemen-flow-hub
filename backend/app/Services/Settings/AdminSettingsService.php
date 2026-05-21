<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminSettingsService
{
    private const DEFAULTS = [
        // Numeric / operational
        'support_claim_ttl' => 15,
        'voting_session_timeout' => 60,
        'pdf_upload_size_limit' => 10,
        'login_lockout_duration' => 15,
        // Feature flags
        'notifications_phase_1_enabled' => false,
        'search_phase_1_enabled' => false,
        'customs_print_preview_enabled' => false,
        // Security policies (boolean)
        'mfa_required' => true,
        'password_expiry_90_days' => false,
        'lockout_after_5_attempts' => false,
        'encrypt_uploads_aes256' => false,
        'log_all_audit' => true,
        'allow_external_access' => false,
        // Approval cycle
        'support_committee_size' => 5,
        'executive_committee_size' => 6,
        'minimum_quorum' => 4,
        'review_timeout_hours' => 48,
        'secret_voting' => true,
        'director_tiebreak' => true,
    ];

    private const VALIDATION_RULES = [
        'support_claim_ttl' => ['min' => 5, 'max' => 60, 'unit' => 'minutes'],
        'voting_session_timeout' => ['min' => 15, 'max' => 120, 'unit' => 'minutes'],
        'pdf_upload_size_limit' => ['min' => 1, 'max' => 50, 'unit' => 'MB'],
        'login_lockout_duration' => ['min' => 5, 'max' => 60, 'unit' => 'minutes'],
        'support_committee_size' => ['min' => 1, 'max' => 20, 'unit' => 'members'],
        'executive_committee_size' => ['min' => 1, 'max' => 30, 'unit' => 'members'],
        'minimum_quorum' => ['min' => 1, 'max' => 30, 'unit' => 'members'],
        'review_timeout_hours' => ['min' => 1, 'max' => 720, 'unit' => 'hours'],
        'notifications_phase_1_enabled' => ['type' => 'boolean'],
        'search_phase_1_enabled' => ['type' => 'boolean'],
        'customs_print_preview_enabled' => ['type' => 'boolean'],
        'mfa_required' => ['type' => 'boolean'],
        'password_expiry_90_days' => ['type' => 'boolean'],
        'lockout_after_5_attempts' => ['type' => 'boolean'],
        'encrypt_uploads_aes256' => ['type' => 'boolean'],
        'log_all_audit' => ['type' => 'boolean'],
        'allow_external_access' => ['type' => 'boolean'],
        'secret_voting' => ['type' => 'boolean'],
        'director_tiebreak' => ['type' => 'boolean'],
    ];

    public function getDefaults(): array
    {
        return self::DEFAULTS;
    }

    public function getValidationRules(): array
    {
        return self::VALIDATION_RULES;
    }

    public function getAllSettings(): array
    {
        $settings = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $settings[$key] = $this->getSetting($key);
        }
        return $settings;
    }

    public function getSetting(string $key): mixed
    {
        $this->validateKey($key);
        $setting = SystemSetting::findByKey($key);
        return $setting?->value ?? self::DEFAULTS[$key];
    }

    public function updateSetting(string $key, mixed $value, User $actor): mixed
    {
        $this->validateKey($key);
        $this->validateValue($key, $value);

        return DB::transaction(function () use ($key, $value, $actor) {
            $setting = SystemSetting::findByKey($key);

            if (!$setting) {
                $setting = SystemSetting::query()->create([
                    'key' => $key,
                    'value' => $value,
                    'updated_by' => $actor->id,
                ]);
            } else {
                $setting->update([
                    'value' => $value,
                    'updated_by' => $actor->id,
                ]);
            }

            $this->invalidateCache($key);

            return $setting->value;
        });
    }

    public function resetSetting(string $key, User $actor): mixed
    {
        $this->validateKey($key);

        return DB::transaction(function () use ($key, $actor) {
            $default = self::DEFAULTS[$key];
            $setting = SystemSetting::findByKey($key);

            if ($setting) {
                $setting->update([
                    'value' => $default,
                    'updated_by' => $actor->id,
                ]);
            }

            $this->invalidateCache($key);

            return $default;
        });
    }

    public function getSmtpSettings(): array
    {
        $smtpPassword = SystemSetting::findByKey('smtp_password');
        $passwordMask = '';
        if ($smtpPassword) {
            $decrypted = decrypt($smtpPassword->value);
            $passwordMask = str_repeat('•', strlen($decrypted));
        }

        return [
            'host'     => SystemSetting::findByKey('smtp_host')?->value ?? '',
            'port'     => SystemSetting::findByKey('smtp_port')?->value ?? 587,
            'username' => SystemSetting::findByKey('smtp_username')?->value ?? '',
            'password' => $passwordMask,
            'template' => SystemSetting::findByKey('smtp_template')?->value ?? '',
        ];
    }

    public function updateSmtpSettings(array $data, User $actor): void
    {
        DB::transaction(function () use ($data, $actor) {
            foreach (['host', 'port', 'username', 'template'] as $field) {
                if (isset($data[$field])) {
                    $this->upsertRawSetting("smtp_{$field}", $data[$field], $actor);
                }
            }
            // Encrypt password
            if (!empty($data['password'])) {
                $this->upsertRawSetting('smtp_password', encrypt($data['password']), $actor);
            }
        });
    }

    public function getSecurityPolicies(): array
    {
        $keys = ['mfa_required', 'password_expiry_90_days', 'lockout_after_5_attempts', 'encrypt_uploads_aes256', 'log_all_audit', 'allow_external_access'];
        $result = [];
        foreach ($keys as $key) {
            $setting = SystemSetting::findByKey($key);
            $result[$key] = (bool) ($setting ? $setting->value : self::DEFAULTS[$key]);
        }
        return $result;
    }

    private function upsertRawSetting(string $key, mixed $value, User $actor): void
    {
        $setting = SystemSetting::findByKey($key);
        if (!$setting) {
            SystemSetting::query()->create(['key' => $key, 'value' => $value, 'updated_by' => $actor->id]);
        } else {
            $setting->update(['value' => $value, 'updated_by' => $actor->id]);
        }
    }

    private function validateKey(string $key): void
    {
        if (!array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException("Invalid setting key: $key");
        }
    }

    private function validateValue(string $key, mixed $value): void
    {
        $rule = self::VALIDATION_RULES[$key];

        if (isset($rule['type'])) {
            if ($rule['type'] === 'boolean' && !is_bool($value)) {
                throw new InvalidArgumentException(
                    "Setting '$key' must be a boolean."
                );
            }
        } else {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException(
                    "Setting '$key' must be a number."
                );
            }

            $value = (int) $value;
            if ($value < $rule['min'] || $value > $rule['max']) {
                throw new InvalidArgumentException(
                    "Setting '$key' must be between {$rule['min']} and {$rule['max']} {$rule['unit']}."
                );
            }
        }
    }

    private function invalidateCache(string $key): void
    {
        Cache::forget("admin_setting:$key");
        Cache::forget('admin_settings:all');
    }
}
