<?php

namespace App\Services\Settings;

use App\Models\User;
use Illuminate\Support\Arr;

class UserPreferencesService
{
    private const DEFAULTS = [
        'language' => 'ar',
        'dashboard_view' => 'normal',
        'table_density' => 'normal',
        'page_size' => 25,
        'default_filters' => [],
        'notification_preferences' => [],
    ];

    public function getDefaults(): array
    {
        return self::DEFAULTS;
    }

    public function getForUser(User $user): array
    {
        $stored = $user->user_preferences ?? [];
        return array_merge(self::DEFAULTS, $stored);
    }

    public function updateForUser(User $user, array $preferences): array
    {
        $preferences = $this->validatePreferences($preferences);
        $merged = array_merge($this->getForUser($user), $preferences);
        $user->user_preferences = $merged;
        $user->save();
        return $merged;
    }

    public function resetForUser(User $user): array
    {
        $user->user_preferences = null;
        $user->save();
        return self::DEFAULTS;
    }

    public function saveSection(User $user, string $section, array $data): array
    {
        $current = $this->getForUser($user);

        if ($section === 'theming') {
            $validated = $this->validateThemingSection($data);
        } elseif ($section === 'notif') {
            $validated = $this->validateNotificationSection($data);
        } else {
            $validated = $data;
        }

        $current[$section] = $validated;
        $user->user_preferences = $current;
        $user->save();

        return $current;
    }

    private function validatePreferences(array $preferences): array
    {
        $validated = [];

        if (isset($preferences['language'])) {
            if (in_array($preferences['language'], ['ar', 'en'], true)) {
                $validated['language'] = $preferences['language'];
            }
        }

        if (isset($preferences['dashboard_view'])) {
            if (in_array($preferences['dashboard_view'], ['compact', 'normal', 'expanded'], true)) {
                $validated['dashboard_view'] = $preferences['dashboard_view'];
            }
        }

        if (isset($preferences['table_density'])) {
            if (in_array($preferences['table_density'], ['compact', 'normal', 'comfortable'], true)) {
                $validated['table_density'] = $preferences['table_density'];
            }
        }

        if (isset($preferences['page_size'])) {
            $pageSize = (int) $preferences['page_size'];
            if (in_array($pageSize, [10, 25, 50, 100], true)) {
                $validated['page_size'] = $pageSize;
            }
        }

        if (isset($preferences['default_filters']) && is_array($preferences['default_filters'])) {
            $validated['default_filters'] = $preferences['default_filters'];
        }

        if (isset($preferences['notification_preferences']) && is_array($preferences['notification_preferences'])) {
            $validated['notification_preferences'] = $preferences['notification_preferences'];
        }

        return $validated;
    }

    private function validateThemingSection(array $data): array
    {
        $validated = [];

        if (isset($data['mode'])) {
            if (in_array($data['mode'], ['light', 'dark', 'system'], true)) {
                $validated['mode'] = $data['mode'];
            }
        }

        if (isset($data['appearance']) && is_array($data['appearance'])) {
            $validated['appearance'] = $data['appearance'];
        }

        if (isset($data['accessibility']) && is_array($data['accessibility'])) {
            $validated['accessibility'] = $data['accessibility'];
        }

        return $validated;
    }

    private function validateNotificationSection(array $data): array
    {
        $validated = [];

        if (isset($data['email']) && is_bool($data['email'])) {
            $validated['email'] = $data['email'];
        }

        if (isset($data['in_app']) && is_bool($data['in_app'])) {
            $validated['in_app'] = $data['in_app'];
        }

        if (isset($data['sms']) && is_bool($data['sms'])) {
            $validated['sms'] = $data['sms'];
        }

        if (isset($data['settings']) && is_array($data['settings'])) {
            $validated['settings'] = $data['settings'];
        }

        return $validated;
    }
}
