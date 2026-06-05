<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;

class UserPreferencesService
{
    private const CANONICAL_NOTIFICATION_IDS = [
        'request_approved',
        'request_rejected',
        'request_returned',
        'voting_opened',
        'request_submitted',
        'swift_upload_requested',
        'claim_released',
    ];

    private const DEFAULTS = [
        'language' => 'ar',
        'dashboard_view' => 'normal',
        'table_density' => 'normal',
        'page_size' => 25,
        'default_filters' => [],
        'notification_preferences' => [],
        'email_notifications' => false,
        'theming' => [
            'mode' => 'system',
            'font' => 'IBM Plex Sans Arabic',
            'layout' => 'boxed',
            'sidebarVariant' => 'sidebar',
            'sidebarCollapsible' => 'icon',
            'radius' => 'md',
            'density' => 'comfortable',
            'reducedMotion' => 'system',
        ],
    ];

    public function getDefaults(): array
    {
        return self::DEFAULTS;
    }

    private function getSystemTheming(): array
    {
        $stored = SystemSetting::getValueByKey('settings.theming', []);

        if (isset($stored['appearance']) && is_array($stored['appearance'])) {
            $stored = array_merge($stored, $stored['appearance']);
            unset($stored['appearance']);
        }

        return array_merge(
            self::DEFAULTS['theming'],
            $this->validateThemingSection(is_array($stored) ? $stored : [])
        );
    }

    public function getForUser(User $user): array
    {
        $stored = $user->user_preferences ?? [];
        $merged = array_merge(self::DEFAULTS, $stored);
        $merged['theming'] = array_merge(
            $this->getSystemTheming(),
            is_array($stored['theming'] ?? null) ? $stored['theming'] : []
        );

        return $merged;
    }

    public function updateForUser(User $user, array $preferences): array
    {
        $preferences = $this->validatePreferences($preferences);
        $merged = array_merge($user->user_preferences ?? [], $preferences);
        $user->user_preferences = $merged;
        $user->save();

        return $this->getForUser($user);
    }

    public function resetForUser(User $user): array
    {
        $user->user_preferences = null;
        $user->save();

        return $this->getForUser($user);
    }

    public function saveSection(User $user, string $section, array $data): array
    {
        $current = $this->getForUser($user);
        $stored = $user->user_preferences ?? [];

        if ($section === 'theming') {
            $base = $this->getSystemTheming();
            $overrides = is_array($stored['theming'] ?? null) ? $stored['theming'] : [];
            $validated = array_merge($overrides, $this->validateThemingSection($data));
            $validated = array_filter(
                $validated,
                static fn ($value, $key) => ! array_key_exists($key, $base) || $base[$key] !== $value,
                ARRAY_FILTER_USE_BOTH
            );
        } elseif ($section === 'notif') {
            $validated = $this->validateNotificationSection($data);
            // Store into the canonical top-level keys that shouldNotify() reads from,
            // not under 'notif' which is the route section name only.
            $stored['notification_preferences'] = $validated['notification_preferences'] ?? [];
            $stored['email_notifications'] = $validated['email_notifications'] ?? false;

            if ($stored === []) {
                $stored = null;
            }

            $current['notification_preferences'] = $stored['notification_preferences'] ?? [];
            $current['email_notifications'] = $stored['email_notifications'] ?? false;
            $user->user_preferences = $stored;
            $user->save();

            return $current;
        } else {
            $validated = $data;
        }

        if ($section === 'theming' && $validated === []) {
            unset($stored[$section]);
        } else {
            $stored[$section] = $validated;
        }

        if ($stored === []) {
            $stored = null;
        }

        $current[$section] = $section === 'theming'
            ? array_merge($this->getSystemTheming(), $validated)
            : $validated;
        $user->user_preferences = $stored;
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

        if (isset($data['font']) && is_string($data['font']) && trim($data['font']) !== '') {
            $validated['font'] = trim($data['font']);
        }

        if (isset($data['layout']) && in_array($data['layout'], ['boxed', 'full'], true)) {
            $validated['layout'] = $data['layout'];
        }

        if (isset($data['sidebarVariant']) && in_array($data['sidebarVariant'], ['sidebar', 'floating', 'inset'], true)) {
            $validated['sidebarVariant'] = $data['sidebarVariant'];
        }

        if (isset($data['sidebarCollapsible']) && in_array($data['sidebarCollapsible'], ['offcanvas', 'icon', 'none'], true)) {
            $validated['sidebarCollapsible'] = $data['sidebarCollapsible'];
        }

        if (isset($data['radius']) && in_array($data['radius'], ['none', 'sm', 'md', 'lg', 'xl'], true)) {
            $validated['radius'] = $data['radius'];
        }

        if (isset($data['density']) && in_array($data['density'], ['comfortable', 'compact'], true)) {
            $validated['density'] = $data['density'];
        }

        if (isset($data['reducedMotion']) && in_array($data['reducedMotion'], ['system', 'always'], true)) {
            $validated['reducedMotion'] = $data['reducedMotion'];
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
        $prefs = [];

        if (isset($data['notification_preferences']) && is_array($data['notification_preferences'])) {
            foreach ($data['notification_preferences'] as $key => $value) {
                if (in_array($key, self::CANONICAL_NOTIFICATION_IDS, true) && is_bool($value)) {
                    $prefs[$key] = $value;
                }
            }
        }

        $emailNotifications = isset($data['email_notifications']) && is_bool($data['email_notifications'])
            ? $data['email_notifications']
            : false;

        return [
            'notification_preferences' => $prefs,
            'email_notifications' => $emailNotifications,
        ];
    }
}
