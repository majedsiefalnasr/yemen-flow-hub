<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\AuthMeResource;
use App\Http\Resources\DemoUserResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthSecuritySettings;
use App\Services\Auth\MfaService;
use App\Services\Auth\PasswordRecoveryService;
use App\Services\Auth\StepUpService;
use App\Services\Auth\TrustedDeviceService;
use App\Services\Authorization\PermissionService;
use App\Support\ApiResponse;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly MfaService $mfaService,
        private readonly PasswordRecoveryService $passwordRecoveryService,
        private readonly AuthSecuritySettings $authSecurity,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly StepUpService $stepUpService,
    ) {}

    #[OA\Post(
        path: '/api/auth/login',
        tags: ['Auth'],
        summary: 'Login with Sanctum cookie mode or bearer token mode',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string'),
                    new OA\Property(property: 'device_name', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 403, description: 'Inactive account'),
            new OA\Response(response: 429, description: 'Too many requests or account/source lockout'),
        ]
    )]
    public function login(LoginRequest $request)
    {
        $ip = (string) $request->ip();
        $email = $request->string('email')->lower()->toString();

        if ($this->isLockedOut('login_fail', $email, $ip)) {
            $this->logFailedLogin($email, 'LOCKED', $ip);

            return $this->lockedOutResponse('login_fail', $email, $ip);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user && ! $user->is_active) {
            $this->logFailedLogin($email, 'INACTIVE', $ip);

            return ApiResponse::forbidden('Account is inactive.');
        }

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            $this->recordFailedAttempt('login_fail', $email, $ip, $user);
            $this->logFailedLogin($email, 'WRONG_CREDENTIALS', $ip);
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $this->clearLockout('login_fail', $email, $ip);

        return $this->completeLoginAfterFirstFactor($request, $user);
    }

    public function loginWithPin(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'pin' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        $ip = (string) $request->ip();
        $email = $request->string('email')->lower()->toString();
        $pin = $request->string('pin')->toString();

        if ($this->isLockedOut('login_pin_fail', $email, $ip)) {
            $this->logFailedLogin($email, 'PIN_LOCKED', $ip);

            return $this->lockedOutResponse('login_pin_fail', $email, $ip);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user && ! $user->is_active) {
            $this->logFailedLogin($email, 'INACTIVE', $ip);

            return ApiResponse::forbidden('Account is inactive.');
        }

        if ($user && (! $user->pin_enabled || ! $user->pin_code_hash)) {
            $this->recordFailedAttempt('login_pin_fail', $email, $ip, $user);
            $this->logFailedLogin($email, 'PIN_NOT_CONFIGURED', $ip);
            throw ValidationException::withMessages([
                'pin' => ['لا يوجد رمز PIN مفعّل لهذا الحساب. استخدم كلمة المرور ثم أنشئ PIN من الملف الشخصي.'],
            ]);
        }

        if (
            ! $user
            || ! Hash::check($pin, $user->pin_code_hash)
        ) {
            $this->recordFailedAttempt('login_pin_fail', $email, $ip, $user);
            $this->logFailedLogin($email, 'WRONG_PIN', $ip);
            throw ValidationException::withMessages([
                'pin' => ['رمز PIN غير صحيح. يرجى المحاولة مرة أخرى.'],
            ]);
        }

        $this->clearLockout('login_pin_fail', $email, $ip);

        return $this->completeLoginAfterFirstFactor($request, $user);
    }

    #[OA\Get(
        path: '/api/auth/me',
        tags: ['Auth'],
        summary: 'Get authenticated user profile',
        responses: [
            new OA\Response(response: 200, description: 'User profile retrieved'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function me(Request $request)
    {
        return ApiResponse::success(
            new AuthMeResource(
                $request->user()->loadMissing(['organization', 'teams', 'roles', 'bank'])
            ),
            'User profile retrieved.'
        );
    }

    public function permissions(Request $request)
    {
        $permissions = app(PermissionService::class);

        return ApiResponse::success([
            'screen_permissions' => $permissions->screenPermissionsForUser($request->user()),
            'capabilities' => $permissions->capabilitiesForUser($request->user()),
        ], 'Permissions retrieved.');
    }

    #[OA\Post(
        path: '/api/auth/logout',
        tags: ['Auth'],
        summary: 'Logout from session and revoke current token',
        responses: [
            new OA\Response(response: 200, description: 'Logout successful'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(Request $request)
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $this->trustedDeviceService->revokeFromRequest($user, $request);

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $this->auditService->log(
            AuditAction::LOGOUT,
            $user,
            $user
        );

        return ApiResponse::success((object) [], 'Logged out successfully.')
            ->withCookie($this->trustedDeviceService->forgetCookie());
    }

    #[OA\Post(
        path: '/api/auth/verify-otp',
        tags: ['Auth'],
        summary: 'Verify MFA OTP code and complete login',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'otp', 'challenge_id'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'otp', type: 'string', minLength: 6, maxLength: 6),
                    new OA\Property(property: 'challenge_id', type: 'string', format: 'uuid'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OTP verified, login complete'),
            new OA\Response(response: 422, description: 'Invalid or expired OTP'),
            new OA\Response(response: 429, description: 'Too many OTP attempts'),
        ]
    )]
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'min:6', 'max:16', 'regex:/^[A-Za-z0-9-]+$/'],
            'challenge_id' => ['required', 'string', 'uuid'],
            'trust_device' => ['sometimes', 'boolean'],
        ]);

        $email = strtolower($request->string('email')->toString());
        $otp = $request->string('otp')->toString();
        $challengeId = $request->string('challenge_id')->toString();

        if (! $this->mfaService->verify($email, $otp, $challengeId)) {
            $this->throwInvalidOtp();
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user || ! $user->is_active) {
            $this->throwInvalidOtp();
        }

        $this->stepUpService->recordStepUp($user);

        $response = $this->issueSession($request, $user);

        if ($request->boolean('trust_device')) {
            return $response->withCookie($this->trustedDeviceService->issue($user, $request));
        }

        return $response;
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $this->passwordRecoveryService->request($validated['email']);

        return ApiResponse::success((object) [], $this->passwordRecoveryService->genericMessage());
    }

    public function verifyPasswordResetOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        if (! $this->passwordRecoveryService->verify($validated['email'], $validated['otp'])) {
            $this->throwInvalidRecoveryCode();
        }

        return ApiResponse::success((object) [], 'Recovery code verified.');
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'password' => ['required', ...PasswordPolicy::rules(), 'confirmed'],
        ], PasswordPolicy::messages());

        $user = User::query()->where('email', strtolower($validated['email']))->first();
        if ($user !== null) {
            $policyErrors = PasswordPolicy::validate($user, $validated['password']);
            if ($policyErrors !== []) {
                throw ValidationException::withMessages($policyErrors);
            }
        }

        if (! $this->passwordRecoveryService->reset(
            $validated['email'],
            $validated['otp'],
            $validated['password']
        )) {
            $this->throwInvalidRecoveryCode();
        }

        return ApiResponse::success((object) [], 'Password reset successfully.');
    }

    #[OA\Get(
        path: '/api/auth/demo-users',
        tags: ['Auth'],
        summary: 'List active demo users available for quick session switching',
        responses: [
            new OA\Response(response: 200, description: 'List of demo users'),
            new OA\Response(response: 403, description: 'Demo role switching disabled'),
        ]
    )]
    public function demoUsers(Request $request)
    {
        if (! config('demo.allow_role_switch', false)) {
            return ApiResponse::forbidden('Demo role switching is disabled.');
        }

        $users = User::query()
            ->where('is_active', true)
            ->with(['organization', 'teams', 'bank'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(['users' => DemoUserResource::collection($users)]);
    }

    #[OA\Post(
        path: '/api/auth/switch-demo-role',
        tags: ['Auth'],
        summary: 'Switch authenticated session to a demo user role',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['role'],
                properties: [
                    new OA\Property(property: 'role', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Role switched'),
            new OA\Response(response: 403, description: 'Demo role switching disabled'),
            new OA\Response(response: 404, description: 'No demo account found for role'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function switchDemoRole(Request $request)
    {
        if (! config('demo.allow_role_switch', false)) {
            return ApiResponse::forbidden('Demo role switching is disabled.');
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(array_map(
                static fn (UserRole $role): string => $role->value,
                UserRole::cases()
            ))],
        ]);

        $role = UserRole::from($validated['role']);
        $user = User::query()
            ->where('role', $role->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $user) {
            return ApiResponse::notFound('No active demo account found for selected role.');
        }

        return $this->issueSession($request, $user);
    }

    #[OA\Post(
        path: '/api/auth/switch-demo-user',
        tags: ['Auth'],
        summary: 'Switch authenticated session to a specific demo user by id',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Session switched'),
            new OA\Response(response: 403, description: 'Demo role switching disabled'),
            new OA\Response(response: 404, description: 'No active demo user found for the given id'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function switchDemoUser(Request $request)
    {
        if (! config('demo.allow_role_switch', false)) {
            return ApiResponse::forbidden('Demo role switching is disabled.');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $user = User::query()
            ->where('id', $validated['user_id'])
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return ApiResponse::notFound('No active demo account found for the selected user.');
        }

        return $this->issueSession($request, $user);
    }

    private function completeLoginAfterFirstFactor(Request $request, User $user)
    {
        if ($this->mfaRequiredFor($user) && $this->trustedDeviceService->findValid($user, $request) === null) {
            return $this->beginMfaChallenge($user);
        }

        return $this->issueSession($request, $user);
    }

    private function beginMfaChallenge(User $user)
    {
        $email = $user->email;
        $hasTotp = $this->mfaService->hasTotpConfigured($user);
        $challenge = $this->mfaService->generateOrReuse($email);
        $challengeId = $this->mfaService->getChallengeId($email);

        if ($challengeId === null) {
            return ApiResponse::error('Unable to initialize MFA challenge.', [], 500);
        }

        if ($challenge['sent'] && ! $hasTotp) {
            $ttlMinutes = (int) ceil(config('mfa.otp_ttl_seconds', 600) / 60);
            $this->mfaService->sendOtpEmail($user, $challenge['otp'], $ttlMinutes);
        }

        return ApiResponse::success([
            'requires_mfa' => true,
            'email' => $email,
            'challenge_id' => $challengeId,
            'challenge_reused' => $challenge['reused'],
        ], $challenge['reused'] ? 'Complete MFA to continue.' : 'OTP sent. Complete MFA to continue.');
    }

    private function mfaRequiredFor(User $user): bool
    {
        return $this->authSecurity->mfaRequired()
            || $this->mfaService->hasTotpConfigured($user);
    }

    private function issueSession(Request $request, User $user)
    {
        $cookieMode = $request->hasSession();

        if ($cookieMode) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        $user->updateQuietly(['last_login_at' => now()]);

        $this->auditService->log(
            AuditAction::LOGIN,
            $user,
            $user,
            ['mode' => $cookieMode ? 'cookie' : 'token']
        );

        $payload = [
            'user' => new UserResource($user->fresh('bank')),
            'requires_mfa' => false,
            'mode' => $cookieMode ? 'cookie' : 'token',
        ];

        if ($cookieMode) {
            $payload['token'] = null;
            $payload['token_type'] = null;
        } else {
            $payload['token'] = $user->createToken($request->string('device_name')->toString() ?: 'api-client')->plainTextToken;
            $payload['token_type'] = 'Bearer';
        }

        return ApiResponse::success($payload, 'Login successful.');
    }

    private function logFailedLogin(string $email, string $reason, string $ip): void
    {
        $this->auditService->log(
            AuditAction::LOGIN_FAILED,
            null,
            null,
            ['email' => $email, 'reason' => $reason, 'ip' => $ip]
        );
    }

    private function lockoutKey(string $prefix, string $email, string $ip): string
    {
        return $prefix.':'.strtolower($email).'|'.$ip;
    }

    private function accountLockoutKey(string $prefix, string $email): string
    {
        return $prefix.'_account:'.strtolower($email);
    }

    private function lockoutThreshold(): int
    {
        return $this->authSecurity->lockoutAttempts();
    }

    private function lockoutDurationSeconds(): int
    {
        return $this->authSecurity->lockoutDurationMinutes() * 60;
    }

    private function isLockedOut(string $prefix, string $email, string $ip): bool
    {
        $threshold = $this->lockoutThreshold();

        return RateLimiter::tooManyAttempts($this->lockoutKey($prefix, $email, $ip), $threshold)
            || RateLimiter::tooManyAttempts($this->accountLockoutKey($prefix, $email), $threshold);
    }

    private function recordFailedAttempt(string $prefix, string $email, string $ip, ?User $user): void
    {
        $duration = $this->lockoutDurationSeconds();
        $threshold = $this->lockoutThreshold();
        $ipKey = $this->lockoutKey($prefix, $email, $ip);
        $accountKey = $this->accountLockoutKey($prefix, $email);

        RateLimiter::hit($ipKey, $duration);
        RateLimiter::hit($accountKey, $duration);

        if (RateLimiter::tooManyAttempts($ipKey, $threshold) || RateLimiter::tooManyAttempts($accountKey, $threshold)) {
            $this->auditService->log(
                AuditAction::ACCOUNT_LOCKED,
                null,
                $user,
                ['email' => $email, 'ip' => $ip, 'prefix' => $prefix]
            );
        }
    }

    private function clearLockout(string $prefix, string $email, string $ip): void
    {
        RateLimiter::clear($this->lockoutKey($prefix, $email, $ip));
        RateLimiter::clear($this->accountLockoutKey($prefix, $email));
    }

    private function lockedOutResponse(string $prefix, string $email, string $ip)
    {
        $ipRetry = RateLimiter::availableIn($this->lockoutKey($prefix, $email, $ip));
        $accountRetry = RateLimiter::availableIn($this->accountLockoutKey($prefix, $email));

        return ApiResponse::lockedOut(retryAfter: max($ipRetry, $accountRetry));
    }

    private function throwInvalidOtp(): void
    {
        throw ValidationException::withMessages([
            'otp' => ['الرمز المدخل غير صحيح أو منتهي الصلاحية.'],
        ]);
    }

    private function throwInvalidRecoveryCode(): void
    {
        throw ValidationException::withMessages([
            'otp' => ['رمز الاستعادة غير صحيح أو منتهي الصلاحية.'],
        ]);
    }
}
