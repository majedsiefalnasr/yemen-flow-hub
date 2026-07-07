<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;

class SettingResolver
{
    /**
     * Resolve a scalar setting value: DB row first, fallback to default.
     * Null DB values fall back to the default (treats unsetting as "use default").
     */
    public function get(string $key, mixed $default): mixed
    {
        $stored = SystemSetting::findByKey($key);

        if ($stored !== null && $stored->value !== null) {
            return $stored->value;
        }

        return $default;
    }
}
