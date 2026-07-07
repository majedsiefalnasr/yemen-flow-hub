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
        'support_claim_ttl' => 15,
        'pdf_upload_size_limit' => 10,
        'login_lockout_attempts' => 5,
        'login_lockout_duration' => 15,
        'mfa_required' => false,
        'duplicate_invoice_policy' => 'warn',
        'trusted_device_ttl_hours' => 24,
        'step_up_window_minutes' => 10,
    ];

    private const VALIDATION_RULES = [
        'support_claim_ttl' => ['min' => 5, 'max' => 60, 'unit' => 'minutes'],
        'pdf_upload_size_limit' => ['min' => 1, 'max' => 50, 'unit' => 'MB'],
        'login_lockout_attempts' => ['min' => 1, 'max' => 20, 'unit' => 'attempts'],
        'login_lockout_duration' => ['min' => 5, 'max' => 60, 'unit' => 'minutes'],
        'mfa_required' => ['type' => 'boolean'],
        'duplicate_invoice_policy' => ['type' => 'enum', 'values' => ['warn', 'block']],
        'trusted_device_ttl_hours' => ['min' => 1, 'max' => 720, 'unit' => 'hours'],
        'step_up_window_minutes' => ['min' => 1, 'max' => 120, 'unit' => 'minutes'],
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

    public function getEmailTemplates(): array
    {
        $emailSettings = SystemSetting::getValueByKey('settings.email', []);
        $templates = is_array($emailSettings['templates'] ?? null) ? $emailSettings['templates'] : [];

        $empty = ['subject' => '', 'body' => ''];

        return [
            'approved' => is_array($templates['approved'] ?? null) ? $templates['approved'] : $empty,
            'rejected' => is_array($templates['rejected'] ?? null) ? $templates['rejected'] : $empty,
            'returned' => is_array($templates['returned'] ?? null) ? $templates['returned'] : $empty,
        ];
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

            if (! $setting) {
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
            'host' => SystemSetting::findByKey('smtp_host')?->value ?? '',
            'port' => SystemSetting::findByKey('smtp_port')?->value ?? 587,
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
            if (! empty($data['password'])) {
                $this->upsertRawSetting('smtp_password', encrypt($data['password']), $actor);
            }
        });
    }

    private function upsertRawSetting(string $key, mixed $value, User $actor): void
    {
        $setting = SystemSetting::findByKey($key);
        if (! $setting) {
            SystemSetting::query()->create(['key' => $key, 'value' => $value, 'updated_by' => $actor->id]);
        } else {
            $setting->update(['value' => $value, 'updated_by' => $actor->id]);
        }
    }

    private function validateKey(string $key): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException("Invalid setting key: $key");
        }
    }

    private function validateValue(string $key, mixed $value): void
    {
        $rule = self::VALIDATION_RULES[$key];

        if (isset($rule['type'])) {
            if ($rule['type'] === 'boolean' && ! is_bool($value)) {
                throw new InvalidArgumentException(
                    "Setting '$key' must be a boolean."
                );
            }
            if ($rule['type'] === 'enum' && ! in_array($value, $rule['values'], true)) {
                $allowed = implode(', ', $rule['values']);
                throw new InvalidArgumentException(
                    "Setting '$key' must be one of: $allowed."
                );
            }
        } else {
            if (! is_numeric($value)) {
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
