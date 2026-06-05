<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Requests\UpdateAdminSettingRequest;
use App\Mail\TestEmailMail;
use App\Services\Audit\AuditService;
use App\Services\Settings\AdminSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly AdminSettingsService $settingsService,
        private readonly AuditService $auditService
    ) {}

    #[OA\Get(
        path: '/api/admin/settings',
        tags: ['Admin Settings'],
        summary: 'Get all system settings (CBY_ADMIN only)',
        responses: [
            new OA\Response(response: 200, description: 'System settings retrieved'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — requires CBY_ADMIN role'),
        ]
    )]
    public function index(Request $request)
    {
        Gate::authorize('cbyAdmin', $request->user());

        $settings = $this->settingsService->getAllSettings();
        $securityPolicies = $this->settingsService->getSecurityPolicies();

        return ApiResponse::success(
            array_merge($settings, $securityPolicies),
            'System settings retrieved.'
        );
    }

    #[OA\Get(
        path: '/api/admin/settings/smtp',
        tags: ['Admin Settings'],
        summary: 'Get SMTP settings (CBY_ADMIN only)',
        responses: [
            new OA\Response(response: 200, description: 'SMTP settings retrieved'),
            new OA\Response(response: 403, description: 'Forbidden — requires CBY_ADMIN role'),
        ]
    )]
    public function getSmtp(Request $request)
    {
        Gate::authorize('cbyAdmin', $request->user());

        return ApiResponse::success(
            $this->settingsService->getSmtpSettings(),
            'SMTP settings retrieved.'
        );
    }

    #[OA\Put(
        path: '/api/admin/settings/smtp',
        tags: ['Admin Settings'],
        summary: 'Update SMTP settings (CBY_ADMIN only)',
        responses: [
            new OA\Response(response: 200, description: 'SMTP settings updated'),
            new OA\Response(response: 403, description: 'Forbidden — requires CBY_ADMIN role'),
        ]
    )]
    public function updateSmtp(Request $request)
    {
        Gate::authorize('cbyAdmin', $request->user());

        $validated = $request->validate([
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'template' => ['nullable', 'string'],
        ]);

        $this->settingsService->updateSmtpSettings($validated, $request->user());

        $this->auditService->log(
            AuditAction::SETTINGS_UPDATED,
            $request->user(),
            $request->user(),
            ['setting_group' => 'smtp']
        );

        return ApiResponse::success(
            $this->settingsService->getSmtpSettings(),
            'SMTP settings updated successfully.'
        );
    }

    #[OA\Put(
        path: '/api/admin/settings/{key}',
        tags: ['Admin Settings'],
        summary: 'Update individual system setting (CBY_ADMIN only)',
        parameters: [
            new OA\Parameter(
                name: 'key',
                in: 'path',
                required: true,
                description: 'Setting key',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['value'],
                properties: [
                    new OA\Property(property: 'value', type: 'mixed'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Setting updated'),
            new OA\Response(response: 400, description: 'Invalid setting key or value'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — requires CBY_ADMIN role'),
        ]
    )]
    public function update(UpdateAdminSettingRequest $request, string $key)
    {
        Gate::authorize('cbyAdmin', $request->user());

        try {
            $value = $this->settingsService->updateSetting(
                $key,
                $request->input('value'),
                $request->user()
            );

            $this->auditService->log(
                AuditAction::SETTINGS_UPDATED,
                $request->user(),
                $request->user(),
                ['setting_key' => $key, 'new_value' => $value]
            );

            return ApiResponse::success(
                ['key' => $key, 'value' => $value],
                'Setting updated successfully.'
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 400);
        }
    }

    #[OA\Post(
        path: '/api/admin/settings/{key}/reset',
        tags: ['Admin Settings'],
        summary: 'Reset setting to default (CBY_ADMIN only)',
        parameters: [
            new OA\Parameter(
                name: 'key',
                in: 'path',
                required: true,
                description: 'Setting key',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Setting reset'),
            new OA\Response(response: 400, description: 'Invalid setting key'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — requires CBY_ADMIN role'),
        ]
    )]
    public function reset(Request $request, string $key)
    {
        Gate::authorize('cbyAdmin', $request->user());

        try {
            $defaultValue = $this->settingsService->resetSetting($key, $request->user());

            $this->auditService->log(
                AuditAction::SETTINGS_UPDATED,
                $request->user(),
                $request->user(),
                ['setting_key' => $key, 'action' => 'reset_to_default', 'default_value' => $defaultValue]
            );

            return ApiResponse::success(
                ['key' => $key, 'value' => $defaultValue],
                'Setting reset to default.'
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 400);
        }
    }

    #[OA\Post(
        path: '/api/admin/settings/email/test',
        tags: ['Admin Settings'],
        summary: 'Send a test email (CBY_ADMIN only)',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'test_address', type: 'string', format: 'email', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Test email sent'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 403, description: 'Forbidden — requires CBY_ADMIN role'),
        ]
    )]
    public function testEmail(Request $request)
    {
        Gate::authorize('cbyAdmin', $request->user());

        $validated = $request->validate([
            'test_address' => ['nullable', 'email', 'max:255'],
        ]);

        $recipient = $validated['test_address'] ?? $request->user()->email;
        $success = true;
        $errorMessage = null;

        try {
            Mail::to($recipient)->send(new TestEmailMail);
        } catch (\Throwable $e) {
            $success = false;
            $errorMessage = $e->getMessage();
        }

        $this->auditService->log(
            AuditAction::EMAIL_TEST_SENT,
            $request->user(),
            null,
            array_filter([
                'success' => $success,
                'recipient' => $recipient,
                'error_message' => $errorMessage,
            ], fn ($v) => $v !== null)
        );

        if (! $success) {
            return ApiResponse::error('Mail transport error.', [], 500, 'EMAIL_TEST_FAILED');
        }

        return ApiResponse::success(
            ['sent' => true, 'recipient' => $recipient],
            'Test email sent successfully.'
        );
    }
}
