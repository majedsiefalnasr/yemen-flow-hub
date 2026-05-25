<?php

namespace App\Services\Settings;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Auth\Access\AuthorizationException;

class SystemSettingsService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    public function saveSection(User $user, string $section, array $data): array
    {
        // Check authorization
        if (!$user->hasRole(UserRole::CBY_ADMIN)) {
            throw new AuthorizationException('Only administrators can modify system settings.');
        }

        // Save to system_settings table
        $key = "settings.{$section}";
        $setting = SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $data,
                'updated_by' => $user->id,
            ]
        );

        // Log to audit
        $this->auditService->log(
            AuditAction::SETTINGS_UPDATED,
            $user,
            null,
            [
                'type' => 'system',
                'section' => $section,
                'changes' => $data,
            ]
        );

        return [
            'key' => $setting->key,
            'value' => $setting->value,
            'updated_by' => $setting->updated_by,
        ];
    }
}
