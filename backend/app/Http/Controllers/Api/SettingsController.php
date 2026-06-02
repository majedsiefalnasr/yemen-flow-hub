<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Requests\SaveSettingsSectionRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Services\Audit\AuditService;
use App\Services\Settings\SystemSettingsService;
use App\Services\Settings\UserPreferencesService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SettingsController extends Controller
{
    public function __construct(
        private readonly UserPreferencesService $preferencesService,
        private readonly SystemSettingsService $systemSettingsService,
        private readonly AuditService $auditService
    ) {
    }

    #[OA\Get(
        path: '/api/settings/public',
        tags: ['Settings'],
        summary: 'Get public system branding and general settings',
        responses: [
            new OA\Response(response: 200, description: 'Public system settings retrieved'),
        ]
    )]
    public function publicSettings()
    {
        return ApiResponse::success(
            $this->systemSettingsService->getPublicSettings(),
            'Public system settings retrieved.'
        );
    }

    #[OA\Get(
        path: '/api/settings',
        tags: ['Settings'],
        summary: 'Get authenticated user preferences with defaults',
        responses: [
            new OA\Response(response: 200, description: 'User preferences retrieved'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(Request $request)
    {
        $user = $request->user();
        $preferences = $this->preferencesService->getForUser($user);
        $preferences['system'] = $this->systemSettingsService->getPublicSettings();

        return ApiResponse::success($preferences, 'User preferences retrieved.');
    }

    #[OA\Put(
        path: '/api/settings',
        tags: ['Settings'],
        summary: 'Update authenticated user preferences',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'language', type: 'string', enum: ['ar', 'en']),
                    new OA\Property(property: 'dashboard_view', type: 'string', enum: ['compact', 'normal', 'expanded']),
                    new OA\Property(property: 'table_density', type: 'string', enum: ['compact', 'normal', 'comfortable']),
                    new OA\Property(property: 'page_size', type: 'integer', enum: [10, 25, 50, 100]),
                    new OA\Property(property: 'default_filters', type: 'array'),
                    new OA\Property(property: 'notification_preferences', type: 'array'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Preferences updated'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function update(UpdateSettingsRequest $request)
    {
        $user = $request->user();
        $preferences = $this->preferencesService->updateForUser(
            $user,
            $request->validated()
        );

        $this->auditService->log(
            AuditAction::SETTINGS_UPDATED,
            $user,
            $user,
            ['preferences' => $request->validated()]
        );

        return ApiResponse::success($preferences, 'Preferences updated successfully.');
    }

    #[OA\Post(
        path: '/api/settings/reset',
        tags: ['Settings'],
        summary: 'Reset user preferences to defaults',
        responses: [
            new OA\Response(response: 200, description: 'Preferences reset'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function reset(Request $request)
    {
        $user = $request->user();
        $defaults = $this->preferencesService->resetForUser($user);

        $this->auditService->log(
            AuditAction::SETTINGS_UPDATED,
            $user,
            $user,
            ['action' => 'reset_to_defaults']
        );

        return ApiResponse::success($defaults, 'Preferences reset to defaults.');
    }

    #[OA\Post(
        path: '/api/settings/save-section',
        tags: ['Settings'],
        summary: 'Save a settings section (system or user)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'section', type: 'string', enum: ['workflow', 'email', 'security', 'general', 'theming', 'notif'], description: 'Settings section to save'),
                    new OA\Property(property: 'data', type: 'object', description: 'Settings data'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Settings saved successfully'),
            new OA\Response(response: 403, description: 'Unauthorized - admin required for system settings'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function saveSection(SaveSettingsSectionRequest $request)
    {
        $user = $request->user();
        $section = $request->input('section');
        $subsection = $request->input('subsection');
        $data = $request->input('data');

        try {
            if ($request->isSystemSection()) {
                $result = $this->systemSettingsService->saveSection($user, $section, $data, $subsection);
            } else {
                $result = $this->preferencesService->saveSection($user, $section, $data);
                $this->auditService->log(
                    AuditAction::SETTINGS_UPDATED,
                    $user,
                    null,
                    [
                        'type' => 'user',
                        'section' => $section,
                        'changes' => $data,
                    ]
                );
            }

            return ApiResponse::success($result, "Settings saved successfully.");
        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage(), 'UNAUTHORIZED');
        } catch (\Exception $e) {
            \Log::error('Settings save error', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to save settings', [], 500, 'SETTINGS_SAVE_ERROR');
        }
    }
}
