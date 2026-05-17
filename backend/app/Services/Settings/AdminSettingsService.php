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
        'voting_session_timeout' => 60,
        'pdf_upload_size_limit' => 10,
        'login_lockout_duration' => 15,
        'notifications_phase_1_enabled' => false,
        'search_phase_1_enabled' => false,
        'customs_print_preview_enabled' => false,
    ];

    private const VALIDATION_RULES = [
        'support_claim_ttl' => ['min' => 5, 'max' => 60, 'unit' => 'minutes'],
        'voting_session_timeout' => ['min' => 15, 'max' => 120, 'unit' => 'minutes'],
        'pdf_upload_size_limit' => ['min' => 1, 'max' => 50, 'unit' => 'MB'],
        'login_lockout_duration' => ['min' => 5, 'max' => 60, 'unit' => 'minutes'],
        'notifications_phase_1_enabled' => ['type' => 'boolean'],
        'search_phase_1_enabled' => ['type' => 'boolean'],
        'customs_print_preview_enabled' => ['type' => 'boolean'],
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
