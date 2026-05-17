<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Requests\UpdateSettingsRequest;
use App\Services\Audit\AuditService;
use App\Services\Settings\UserPreferencesService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SettingsController extends Controller
{
    public function __construct(
        private readonly UserPreferencesService $preferencesService,
        private readonly AuditService $auditService
    ) {
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
}
