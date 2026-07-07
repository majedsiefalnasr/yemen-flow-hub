<?php

namespace App\Services\Settings;

use App\Enums\AuditAction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Support\RoleCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;

class SystemSettingsService
{
    private const DEFAULT_GENERAL = [
        'platformName' => 'اللجنة الوطنية لتنظيم وتمويل الواردات',
        'platformNameEn' => 'The National Committee for Regulating & Financing Imports',
        'authority' => 'اللجنة الوطنية لتنظيم وتمويل الواردات',
        'authorityEn' => 'The National Committee for Regulating & Financing Imports',
        'language' => 'ar',
        'timeZone' => 'GMT+3',
    ];

    private const DEFAULT_BRANDING = [
        'brandColor' => '#0066cc',
        'brandLogoName' => 'yemen-emblem.svg',
        'brandLogoPath' => '/brand/yemen-emblem.svg',
        'brandingPublished' => true,
        'brandingChannels' => [
            'securityQuestionnaires' => false,
            'emails' => true,
            'vendorReports' => true,
        ],
    ];

    public function __construct(
        private readonly AuditService $auditService,
        private readonly LogoStorageService $logoStorageService,
    ) {}

    public function saveSection(User $user, string $section, array $data, ?string $subsection = null): array
    {
        // Check authorization
        if (! $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            throw new AuthorizationException('Only administrators can modify system settings.');
        }

        // Save to system_settings table
        $key = $this->settingKey($section, $subsection);
        if ($section === 'theming' && $subsection === 'branding') {
            $existing = $this->arrayValue(SystemSetting::query()->where('key', $key)->value('value'));
            $data = array_merge($existing, $data);
        }
        $value = $this->normalizeSectionData($section, $data, $subsection);
        $setting = SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
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
                'subsection' => $subsection,
                'changes' => $value,
            ]
        );

        if ($section === 'email' && isset($value['templates']) && is_array($value['templates'])) {
            foreach (array_keys($value['templates']) as $type) {
                $this->auditService->log(
                    AuditAction::EMAIL_TEMPLATE_UPDATED,
                    $user,
                    null,
                    ['template_type' => $type, 'changed_by' => $user->id]
                );
            }
        }

        return [
            'key' => $setting->key,
            'value' => $setting->value,
            'updated_by' => $setting->updated_by,
            'updated_at' => $setting->updated_at?->toJSON(),
        ];
    }

    public function getPublicSettings(): array
    {
        $settings = SystemSetting::query()
            ->whereIn('key', ['settings.general', 'settings.branding'])
            ->get()
            ->keyBy('key');

        $version = $settings
            ->pluck('updated_at')
            ->filter()
            ->sortDesc()
            ->first();

        $storedBranding = $this->arrayValue($settings->get('settings.branding')?->value);
        $branding = array_merge(self::DEFAULT_BRANDING, $storedBranding);
        $branding = $this->exposePublicBranding($branding, $storedBranding);

        return [
            'version' => $version?->toJSON() ?? 'defaults-v1',
            'general' => array_merge(
                self::DEFAULT_GENERAL,
                $this->arrayValue($settings->get('settings.general')?->value)
            ),
            'branding' => $branding,
        ];
    }

    private function settingKey(string $section, ?string $subsection): string
    {
        if ($section === 'theming' && $subsection === 'branding') {
            return 'settings.branding';
        }

        return "settings.{$section}";
    }

    private function normalizeSectionData(string $section, array $data, ?string $subsection): array
    {
        if ($section === 'general') {
            return array_merge(self::DEFAULT_GENERAL, $data);
        }

        if ($section === 'theming' && $subsection === 'branding') {
            $normalized = array_merge(self::DEFAULT_BRANDING, $data);

            if (isset($normalized['brandLogoFile']) && $normalized['brandLogoFile'] instanceof UploadedFile) {
                $normalized['brandLogoPath'] = $this->logoStorageService->store($normalized['brandLogoFile']);
            }

            unset($normalized['brandLogoFile'], $normalized['brandLogoDataUrl']);

            return $normalized;
        }

        if ($section === 'email') {
            $types = ['approved', 'rejected', 'returned'];
            $normalizedTemplates = [];
            if (isset($data['templates']) && is_array($data['templates'])) {
                foreach ($types as $type) {
                    if (isset($data['templates'][$type]) && is_array($data['templates'][$type])) {
                        $subject = is_string($data['templates'][$type]['subject'] ?? null)
                            ? trim($data['templates'][$type]['subject'])
                            : '';
                        $body = is_string($data['templates'][$type]['body'] ?? null)
                            ? trim($data['templates'][$type]['body'])
                            : '';
                        $normalizedTemplates[$type] = compact('subject', 'body');
                    }
                }
            }

            return ['templates' => $normalizedTemplates];
        }

        return $data;
    }

    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function exposePublicBranding(array $branding, array $storedBranding = []): array
    {
        if (
            ! array_key_exists('brandLogoPath', $storedBranding)
            && isset($storedBranding['brandLogoDataUrl'])
            && is_string($storedBranding['brandLogoDataUrl'])
            && str_starts_with($storedBranding['brandLogoDataUrl'], 'data:')
        ) {
            $branding['brandLogoUrl'] = $storedBranding['brandLogoDataUrl'];
        } else {
            $path = $branding['brandLogoPath'] ?? self::DEFAULT_BRANDING['brandLogoPath'];
            $branding['brandLogoUrl'] = $this->logoStorageService->url($path);
        }

        unset($branding['brandLogoPath'], $branding['brandLogoDataUrl']);

        return $branding;
    }
}
