<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\AvatarVariant;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthSecuritySettings;
use App\Services\Auth\MfaService;
use App\Services\Auth\SessionInvalidationService;
use App\Services\Auth\StepUpService;
use App\Services\Auth\TrustedDeviceService;
use App\Support\ApiResponse;
use App\Support\EngineRequestReadModel;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly AuthSecuritySettings $authSecurity,
        private readonly MfaService $mfaService,
        private readonly StepUpService $stepUpService,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly SessionInvalidationService $sessionInvalidationService,
    ) {}

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
        $mfaRequired = $this->authSecurity->mfaRequired();
        $recoveryCodesRemaining = $this->mfaService->remainingRecoveryCodeCount($user);

        $resource = new UserResource($user);

        return ApiResponse::success(
            array_merge($resource->resolve(), [
                'stats' => $stats,
                'recent_activity' => $recentActivity,
                'mfa_required' => $mfaRequired,
                'recovery_codes_remaining' => $recoveryCodesRemaining,
                'recovery_codes_low' => $user->totp_enabled && $recoveryCodesRemaining <= 2,
                'last_3_logins' => $user->loginHistory->map(fn ($login) => [
                    'logged_in_at' => $login->logged_in_at,
                    'ip_address' => $login->ip_address,
                    'user_agent' => $login->user_agent,
                ])->values(),
                'active_sessions_count' => $user->tokens()
                    ->where(fn ($query) => $query
                        ->whereNull('last_used_at')
                        ->orWhere('last_used_at', '>', now()->subHours(24)))
                    ->count(),
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
            'name' => ['required', 'string', 'max:255'],
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

    public function listSessions(Request $request)
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $sessions = $user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'ip_address' => $token->ip_address ?? null,
                'user_agent' => $token->user_agent ?? null,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'is_current' => $currentTokenId !== null && (int) $token->id === (int) $currentTokenId,
            ])
            ->values();

        return ApiResponse::success(['sessions' => $sessions], 'Sessions retrieved.');
    }

    public function revokeSession(Request $request, int $tokenId)
    {
        $user = $request->user();
        $token = $user->tokens()->where('id', $tokenId)->first();

        if (! $token) {
            return ApiResponse::notFound('Session not found.');
        }

        $token->delete();

        return ApiResponse::success((object) [], 'Session revoked.');
    }

    public function revokeAllSessions(Request $request)
    {
        $user = $request->user();

        $this->sessionInvalidationService->revokeAllSessions($user);

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $this->auditService->log(
            AuditAction::SESSIONS_REVOKED,
            $user,
            $user,
            ['scope' => 'all']
        );

        return ApiResponse::success((object) [], 'All sessions revoked.')
            ->withCookie($this->trustedDeviceService->forgetCookie());
    }

    public function initiateStepUp(Request $request)
    {
        $user = $request->user();

        return ApiResponse::success(
            $this->stepUpService->initiateEmailChallenge($user),
            'Step-up challenge initiated.'
        );
    }

    public function verifyStepUp(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:16', 'regex:/^[A-Za-z0-9-]+$/'],
            'challenge_id' => ['nullable', 'string', 'uuid'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! $this->stepUpService->verify(
            $user,
            $validated['code'],
            $validated['challenge_id'] ?? null
        )) {
            return ApiResponse::error('رمز التحقق غير صحيح أو منتهي الصلاحية.', [], 422);
        }

        return ApiResponse::success((object) [], 'Step-up verified.');
    }

    public function setPin(Request $request)
    {
        if ($stepUp = $this->ensureStepUp($request)) {
            return $stepUp;
        }

        $validated = $request->validate([
            'new_pin' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'current_pin' => ['nullable', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $isChange = (bool) $user->pin_enabled;

        if ($user->pin_enabled) {
            $currentPin = $validated['current_pin'] ?? null;
            if (! $currentPin || ! $user->pin_code_hash || ! Hash::check($currentPin, $user->pin_code_hash)) {
                $this->auditService->log(
                    AuditAction::AUTHORIZATION_FAILURE,
                    $user,
                    $user,
                    ['action' => 'pin_change', 'reason' => 'invalid_current_pin']
                );

                return ApiResponse::error('رمز PIN الحالي غير صحيح.', [], 422);
            }
        }

        $user->pin_code_hash = Hash::make($validated['new_pin']);
        $user->pin_enabled = true;
        $user->save();

        $this->auditService->log(
            $isChange ? AuditAction::PIN_CHANGED : AuditAction::PIN_SET,
            $user,
            $user
        );

        return ApiResponse::success(
            (new UserResource($user->loadMissing('bank')))->resolve(),
            'تم حفظ رمز PIN بنجاح.'
        );
    }

    public function disablePin(Request $request)
    {
        if ($stepUp = $this->ensureStepUp($request)) {
            return $stepUp;
        }

        $validated = $request->validate([
            'current_pin' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! $user->pin_enabled || ! $user->pin_code_hash) {
            return ApiResponse::error('رمز PIN غير مفعّل.', [], 422);
        }

        if (! Hash::check($validated['current_pin'], $user->pin_code_hash)) {
            $this->auditService->log(
                AuditAction::AUTHORIZATION_FAILURE,
                $user,
                $user,
                ['action' => 'pin_disable', 'reason' => 'invalid_current_pin']
            );

            return ApiResponse::error('رمز PIN الحالي غير صحيح.', [], 422);
        }

        $user->pin_code_hash = null;
        $user->pin_enabled = false;
        $user->save();

        $this->auditService->log(AuditAction::PIN_DISABLED, $user, $user);

        return ApiResponse::success(
            (new UserResource($user->loadMissing('bank')))->resolve(),
            'تم تعطيل رمز PIN.'
        );
    }

    public function toggleMfa(Request $request)
    {
        if ($this->authSecurity->mfaRequired()) {
            return ApiResponse::error('MFA is system-enforced', [], 403);
        }

        $user = $request->user();
        $user->mfa_enabled = ! $user->mfa_enabled;
        $user->save();

        return ApiResponse::success(
            array_merge((new UserResource($user->loadMissing('bank')))->resolve(), [
                'mfa_required' => $this->authSecurity->mfaRequired(),
            ]),
            'MFA toggled.'
        );
    }

    public function setupTotp(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $secret = $this->mfaService->generateTotpSecret($user->email);
        $uri = $this->mfaService->getTotpProvisioningUri($user->email, $secret);

        return ApiResponse::success([
            'provisioning_uri' => $uri,
            'secret' => $secret,
        ]);
    }

    public function verifyTotpSetup(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        /** @var User $user */
        $user = $request->user();

        $secret = $this->mfaService->verifyTotpSetup($user->email, $request->input('code'));

        if (! $secret) {
            return ApiResponse::error('الرمز غير صحيح أو انتهت صلاحيته. تأكد من التوقيت ثم أعد المحاولة.', [], 422);
        }

        $user->totp_secret = $secret;
        $user->totp_enabled = true;
        $user->mfa_enabled = true;
        $recoveryCodes = $this->mfaService->generateRecoveryCodes();
        $user->totp_recovery_codes = $this->mfaService->hashRecoveryCodes($recoveryCodes);
        $user->save();

        return ApiResponse::success(
            array_merge((new UserResource($user->loadMissing('bank')))->resolve(), [
                'recovery_codes' => $recoveryCodes,
            ]),
            'تم تفعيل تطبيق المصادقة بنجاح.'
        );
    }

    public function disableTotp(Request $request)
    {
        if ($stepUp = $this->ensureStepUp($request)) {
            return $stepUp;
        }

        $request->validate(['code' => 'required|string|size:6']);

        /** @var User $user */
        $user = $request->user();

        if (! $user->totp_enabled || ! $user->totp_secret) {
            return ApiResponse::error('تطبيق المصادقة غير مفعّل.', [], 422);
        }

        if (! $this->mfaService->verifyTotp($user->totp_secret, $request->input('code'))) {
            return ApiResponse::error('رمز التحقق غير صحيح.', [], 422);
        }

        $user->totp_secret = null;
        $user->totp_enabled = false;
        $user->totp_recovery_codes = null;
        $user->save();

        $this->trustedDeviceService->revokeAll($user);
        $this->stepUpService->clearStepUp($user);

        return ApiResponse::success(
            (new UserResource($user->loadMissing('bank')))->resolve(),
            'تم تعطيل تطبيق المصادقة.'
        );
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        if ($stepUp = $this->ensureStepUp($request)) {
            return $stepUp;
        }

        /** @var User $user */
        $user = $request->user();

        if (! $user->totp_enabled) {
            return ApiResponse::error('تطبيق المصادقة غير مفعّل.', [], 422);
        }

        $recoveryCodes = $this->mfaService->generateRecoveryCodes();
        $user->totp_recovery_codes = $this->mfaService->hashRecoveryCodes($recoveryCodes);
        $user->save();

        $this->trustedDeviceService->revokeAll($user);

        $this->auditService->log(
            AuditAction::RECOVERY_CODES_REGENERATED,
            $user,
            $user
        );

        return ApiResponse::success(
            ['recovery_codes' => $recoveryCodes],
            'تم إنشاء رموز استعادة جديدة.'
        );
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        if ($stepUp = $this->ensureStepUp($request)) {
            return $stepUp;
        }

        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->string('password')),
            'must_change_password' => false,
            'temporary_password_set_at' => null,
            'password_changed_at' => now(),
        ])->save();

        PasswordPolicy::recordHistory($user);
        $this->trustedDeviceService->revokeAll($user);
        $this->stepUpService->clearStepUp($user);

        $this->auditService->log(
            AuditAction::PASSWORD_CHANGED,
            $user,
            $user
        );

        return ApiResponse::success((object) [], 'Password changed successfully.');
    }

    public function changeTemporaryPassword(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', ...PasswordPolicy::rules(), 'confirmed'],
        ], PasswordPolicy::messages());

        /** @var User $user */
        $user = $request->user();

        if (! $user->must_change_password) {
            return ApiResponse::error('Temporary password change is not required.', [], 422);
        }

        $policyErrors = PasswordPolicy::validate($user, $validated['password']);
        if ($policyErrors !== []) {
            throw ValidationException::withMessages($policyErrors);
        }

        if (Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error('The new password must be different from the temporary password.', [], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'temporary_password_set_at' => null,
            'password_changed_at' => now(),
        ])->save();

        PasswordPolicy::recordHistory($user);
        $this->trustedDeviceService->revokeAll($user);

        $this->auditService->log(
            AuditAction::PASSWORD_CHANGED,
            $user,
            $user,
            ['mode' => 'temporary_password']
        );

        return ApiResponse::success(
            (new UserResource($user->loadMissing('bank')))->resolve(),
            'Password changed successfully.'
        );
    }

    private function ensureStepUp(Request $request)
    {
        $user = $request->user();
        if ($user === null || $this->stepUpService->hasValidStepUp($user)) {
            return null;
        }

        return ApiResponse::stepUpRequired();
    }

    private function computeStats(mixed $user): array
    {
        $base = EngineRequestReadModel::queryFor($user);

        return [
            'total' => (clone $base)->count(),
            'in_progress' => (clone $base)->where(EngineRequestReadModel::bucket('active'))->count(),
            'completed' => (clone $base)->where(EngineRequestReadModel::bucket('completed'))->count(),
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
                'id' => $log->id,
                'action' => $log->action,
                'ref' => $log->subject_id ?? $log->entity_id ?? null,
                'ts' => $log->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
