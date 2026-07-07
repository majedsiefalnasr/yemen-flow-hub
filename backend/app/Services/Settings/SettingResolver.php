<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SettingResolver
{
    /**
     * Resolve a scalar setting value: DB row first, fallback to default.
     * Null DB values fall back to the default (treats unsetting as "use default").
     */
    public function get(string $key, mixed $default): mixed
    {
        return Cache::remember("setting:{$key}", now()->addHour(), function () use ($key, $default) {
            $stored = SystemSetting::findByKey($key);

            if ($stored !== null && $stored->value !== null) {
                return $stored->value;
            }

            return $default;
        });
    }

    public function forget(string $key): void
    {
        Cache::forget("setting:{$key}");
    }
}
