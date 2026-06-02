<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\AvatarVariant;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\ImportRequest;
use App\Services\Audit\AuditService;
use App\Services\Auth\MfaService;
use App\Services\Settings\AdminSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly AdminSettingsService $settingsService,
        private readonly MfaService $mfaService,
    ) {
    }

    #[OA\Get(
        path: '/api/profile',
        tags: ['Profile'],
        summary: 'Get authenticated user profile',
        responses: [
            new OA\Response(response: 200, description: 'Profile retrieved'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(Request $request)
    {
        $user = $request->user()->loadMissing('bank');

        $user->load(['loginHistory' => function ($query) {
            $query->orderBy('logged_in_at', 'desc')->limit(3);
        }]);

        $stats = $this->computeStats($user);
        $recentActivity = $this->getRecentActivity($user);

        $mfaRequired = false;
        try {
            $mfaRequired = (bool) $this->settingsService->getSetting('mfa_required');
        } catch (\InvalidArgumentException) {
            // key not yet registered — treat as not enforced
        }

        $resource = new UserResource($user);

        return ApiResponse::success(
            array_merge($resource->resolve(), [
                'stats' => $stats,
                'recent_activity' => $recentActivity,
                'mfa_required' => $mfaRequired,
                'last_3_logins' => $user->loginHistory->map(fn ($login) => [
                    'logged_in_at' => $login->logged_in_at,
                    'ip_address' => $login->ip_address,
                    'user_agent' => $login->user_agent,
                ])->values(),
                'active_sessions_count' => $user->tokens()->whereNull('last_used_at')->orWhere('last_used_at', '>', now()->subHours(24))->count(),
            ]),
            'Profile retrieved.'
        );
    }

    #[OA\Put(
        path: '/api/profile',
        tags: ['Profile'],
        summary: 'Update authenticated user profile',
        responses: [
            new OA\Response(response: 200, description: 'Profile updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'avatar_variant' => ['nullable', Rule::in(AvatarVariant::values())],
        ]);

        $user->fill($validated)->save();

        $this->auditService->log(
            AuditAction::SETTINGS_UPDATED,
            $user,
            $user,
            ['fields' => array_keys($validated)]
        );

        return ApiResponse::success(new UserResource($user->loadMissing('bank')), 'Profile updated.');
    }

    /**
     * Avatar-only update. Lives outside `update()` so the UI can persist a
     * picker change instantly (no name/email validation, no MFA gating) and so
     * audit logs can attribute the change to a dedicated action key.
     */
    public function updateAvatar(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar_variant' => ['nullable', Rule::in(AvatarVariant::values())],
        ]);

        if (array_key_exists('avatar_variant', $validated)) {
            $user->avatar_variant = $validated['avatar_variant'] ?? 'beam';
        }
        $user->save();

        $this->auditService->log(
            AuditAction::SETTINGS_UPDATED,
            $user,
            $user,
            ['fields' => array_keys($validated), 'scope' => 'avatar']
        );

        return ApiResponse::success(new UserResource($user->loadMissing('bank')), 'Avatar updated.');
    }

    public function setPin(Request $request)
    {
        $validated = $request->validate([
            'new_pin' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'current_pin' => ['nullable', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->pin_enabled) {
            $currentPin = $validated['current_pin'] ?? null;
            if (!$currentPin || !$user->pin_code_hash || !Hash::check($currentPin, $user->pin_code_hash)) {
                return ApiResponse::error('رمز PIN الحالي غير صحيح.', [], 422);
            }
        }

        $user->pin_code_hash = Hash::make($validated['new_pin']);
        $user->pin_enabled = true;
        $user->save();

        return ApiResponse::success(
            (new UserResource($user->loadMissing('bank')))->resolve(),
            'تم حفظ رمز PIN بنجاح.'
        );
    }

    public function disablePin(Request $request)
    {
        $validated = $request->validate([
            'current_pin' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->pin_enabled || !$user->pin_code_hash) {
            return ApiResponse::error('رمز PIN غير مفعّل.', [], 422);
        }

        if (!Hash::check($validated['current_pin'], $user->pin_code_hash)) {
            return ApiResponse::error('رمز PIN الحالي غير صحيح.', [], 422);
        }

        $user->pin_code_hash = null;
        $user->pin_enabled = false;
        $user->save();

        return ApiResponse::success(
            (new UserResource($user->loadMissing('bank')))->resolve(),
            'تم تعطيل رمز PIN.'
        );
    }

    #[OA\Post(
        path: '/api/profile/mfa/toggle',
        tags: ['Profile'],
        summary: 'Toggle MFA for authenticated user',
        responses: [
            new OA\Response(response: 200, description: 'MFA toggled'),
            new OA\Response(response: 403, description: 'MFA is system-enforced'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function toggleMfa(Request $request)
    {
        $mfaRequired = false;
        try {
            $mfaRequired = (bool) $this->settingsService->getSetting('mfa_required');
        } catch (\InvalidArgumentException) {
            // key not yet registered — treat as not enforced
        }

        if ($mfaRequired) {
            return ApiResponse::error('MFA is system-enforced', [], 403);
        }

        $user = $request->user();
        $user->mfa_enabled = !$user->mfa_enabled;
        $user->save();

        $mfaRequiredFull = false;
        try {
            $mfaRequiredFull = (bool) $this->settingsService->getSetting('mfa_required');
        } catch (\InvalidArgumentException) {
        }

        return ApiResponse::success(
            array_merge((new UserResource($user->loadMissing('bank')))->resolve(), [
                'mfa_required' => $mfaRequiredFull,
            ]),
            'MFA toggled.'
        );
    }

    /**
     * Initiate TOTP authenticator setup.
     * Returns an otpauth:// provisioning URI and the base32 secret.
     * The frontend renders the URI as a QR code; the secret is shown for manual entry.
     * The secret is stored in Redis cache for 10 minutes pending verification.
     */
    public function setupTotp(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $secret = $this->mfaService->generateTotpSecret($user->email);
        $uri    = $this->mfaService->getTotpProvisioningUri($user->email, $secret);

        return ApiResponse::success([
            'provisioning_uri' => $uri,
            'secret'           => $secret,
        ]);
    }

    /**
     * Verify a TOTP code entered by the user during setup.
     * On success, saves the secret to the user and marks TOTP as enabled.
     */
    public function verifyTotpSetup(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $secret = $this->mfaService->verifyTotpSetup($user->email, $request->input('code'));

        if (!$secret) {
            return ApiResponse::error('الرمز غير صحيح أو انتهت صلاحيته. تأكد من التوقيت ثم أعد المحاولة.', [], 422);
        }

        $user->totp_secret  = $secret;
        $user->totp_enabled = true;
        $user->mfa_enabled  = true;
        $user->save();

        return ApiResponse::success(
            (new UserResource($user->loadMissing('bank')))->resolve(),
            'تم تفعيل تطبيق المصادقة بنجاح.'
        );
    }

    /**
     * Disable TOTP authenticator after verifying the current code.
     */
    public function disableTotp(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->totp_enabled || !$user->totp_secret) {
            return ApiResponse::error('تطبيق المصادقة غير مفعّل.', [], 422);
        }

        if (!$this->mfaService->verifyTotp($user->totp_secret, $request->input('code'))) {
            return ApiResponse::error('رمز التحقق غير صحيح.', [], 422);
        }

        $user->totp_secret  = null;
        $user->totp_enabled = false;
        $user->save();

        return ApiResponse::success(
            (new UserResource($user->loadMissing('bank')))->resolve(),
            'تم تعطيل تطبيق المصادقة.'
        );
    }

    /**
     * Disable TOTP using password as fallback (no authenticator code required).
     * Used when the user has lost access to their authenticator app.
     */
    public function disableTotpWithPassword(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->totp_enabled || !$user->totp_secret) {
            return ApiResponse::error('تطبيق المصادقة غير مفعّل.', [], 422);
        }

        if (!Hash::check($request->input('password'), $user->password)) {
            return ApiResponse::error('كلمة المرور غير صحيحة.', [], 422);
        }

        $user->totp_secret  = null;
        $user->totp_enabled = false;
        $user->save();

        return ApiResponse::success(
            (new UserResource($user->loadMissing('bank')))->resolve(),
            'تم تعطيل تطبيق المصادقة.'
        );
    }

    #[OA\Post(
        tags: ['Profile'],
        summary: 'Change authenticated user password',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string'),
                    new OA\Property(property: 'password', type: 'string'),
                    new OA\Property(property: 'password_confirmation', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password changed successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        $user->forceFill(['password' => Hash::make($request->string('password'))])
            ->save();

        $this->auditService->log(
            AuditAction::PASSWORD_CHANGED,
            $user,
            $user
        );

        return ApiResponse::success((object) [], 'Password changed successfully.');
    }

    private function computeStats(mixed $user): array
    {
        $query = ImportRequest::query();

        $cbySideRoles = [
            UserRole::CBY_ADMIN,
            UserRole::COMMITTEE_DIRECTOR,
            UserRole::SUPPORT_COMMITTEE,
            UserRole::SWIFT_OFFICER,
            UserRole::EXECUTIVE_MEMBER,
        ];

        if (!in_array($user->role, $cbySideRoles)) {
            $query->where('bank_id', $user->bank_id);
        }

        $terminalStatuses = [
            RequestStatus::COMPLETED->value,
            RequestStatus::EXECUTIVE_REJECTED->value,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED->value,
        ];

        $inProgressStatuses = [
            RequestStatus::DRAFT_REJECTED_INTERNAL->value,
            RequestStatus::SUBMITTED->value,
            RequestStatus::BANK_REVIEW->value,
            RequestStatus::BANK_APPROVED->value,
            RequestStatus::SUPPORT_REVIEW_PENDING->value,
            RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value,
            RequestStatus::SUPPORT_APPROVED->value,
            RequestStatus::SUPPORT_REJECTED->value,
            RequestStatus::WAITING_FOR_SWIFT->value,
            RequestStatus::SWIFT_UPLOADED->value,
            RequestStatus::WAITING_FOR_VOTING_OPEN->value,
            RequestStatus::EXECUTIVE_VOTING_OPEN->value,
            RequestStatus::EXECUTIVE_VOTING_CLOSED->value,
            RequestStatus::EXECUTIVE_APPROVED->value,
        ];

        return [
            'total'       => (clone $query)->count(),
            'in_progress' => (clone $query)
                ->whereIn('status', $inProgressStatuses)
                ->count(),
            'completed'   => (clone $query)
                ->where('status', RequestStatus::COMPLETED->value)
                ->count(),
        ];
    }

    private function getRecentActivity(mixed $user): array
    {
        return AuditLog::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(fn ($log) => [
                'id'     => $log->id,
                'action' => $log->action,
                'ref'    => $log->subject_id ?? $log->entity_id ?? null,
                'ts'     => $log->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
