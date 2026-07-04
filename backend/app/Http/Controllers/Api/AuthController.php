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
use App\Services\Auth\MfaService;
use App\Services\Auth\PasswordRecoveryService;
use App\Services\Authorization\PermissionService;
use App\Support\ApiResponse;
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
    private const LOCKOUT_THRESHOLD = 10;

    private const LOCKOUT_MINUTES = 15;

    public function __construct(
        private readonly AuditService $auditService,
        private readonly MfaService $mfaService,
        private readonly PasswordRecoveryService $passwordRecoveryService,
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
        // Normalize email for consistent counter keys and DB lookup
        $email = $request->string('email')->lower()->toString();
        $failKey = $this->lockoutKey('login_fail', $email, $ip);

        // Account/source lockout: 10 consecutive failures from the same IP -> 15 min lock.
        if (RateLimiter::tooManyAttempts($failKey, self::LOCKOUT_THRESHOLD)) {
            $this->logFailedLogin($email, 'LOCKED', $ip);

            return ApiResponse::lockedOut(retryAfter: RateLimiter::availableIn($failKey));
        }

        $user = User::query()->where('email', $email)->first();

        // Check is_active before password — inactive is an admin state, not an auth failure
        if ($user && ! $user->is_active) {
            $this->logFailedLogin($email, 'INACTIVE', $ip);

            return ApiResponse::forbidden('Account is inactive.');
        }

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($failKey, self::LOCKOUT_MINUTES * 60);
            $this->logFailedLogin($email, 'WRONG_CREDENTIALS', $ip);
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Success clears the failure counter for this account/source pair.
        RateLimiter::clear($failKey);

        // MFA gate:
        // - system-level MFA switch
        // - OR user already configured authenticator (TOTP) and must verify code on login
        $hasTotp = $this->mfaService->hasTotpConfigured($user);
        if (config('mfa.enabled', false) || $hasTotp) {
            $otp = $this->mfaService->generate($email);
            $challengeId = $this->mfaService->getChallengeId($email);
            if ($challengeId === null) {
                return ApiResponse::error('Unable to initialize MFA challenge.', [], 500);
            }

            if (! $hasTotp) {
                $ttlMinutes = (int) ceil(config('mfa.otp_ttl_seconds', 600) / 60);
                $this->mfaService->sendOtpEmail($user, $otp, $ttlMinutes);
            }

            return ApiResponse::success([
                'requires_mfa' => true,
                'email' => $email,
                'challenge_id' => $challengeId,
            ], 'OTP sent. Complete MFA to continue.');
        }

        return $this->issueSession($request, $user);
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
        $failKey = $this->lockoutKey('login_pin_fail', $email, $ip);

        if (RateLimiter::tooManyAttempts($failKey, self::LOCKOUT_THRESHOLD)) {
            $this->logFailedLogin($email, 'PIN_LOCKED', $ip);

            return ApiResponse::lockedOut(retryAfter: RateLimiter::availableIn($failKey));
        }

        $user = User::query()->where('email', $email)->first();

        if ($user && ! $user->is_active) {
            $this->logFailedLogin($email, 'INACTIVE', $ip);

            return ApiResponse::forbidden('Account is inactive.');
        }

        if ($user && (! $user->pin_enabled || ! $user->pin_code_hash)) {
            RateLimiter::hit($failKey, self::LOCKOUT_MINUTES * 60);
            $this->logFailedLogin($email, 'PIN_NOT_CONFIGURED', $ip);
            throw ValidationException::withMessages([
                'pin' => ['لا يوجد رمز PIN مفعّل لهذا الحساب. استخدم كلمة المرور ثم أنشئ PIN من الملف الشخصي.'],
            ]);
        }

        if (
            ! $user
            || ! Hash::check($pin, $user->pin_code_hash)
        ) {
            RateLimiter::hit($failKey, self::LOCKOUT_MINUTES * 60);
            $this->logFailedLogin($email, 'WRONG_PIN', $ip);
            throw ValidationException::withMessages([
                'pin' => ['رمز PIN غير صحيح. يرجى المحاولة مرة أخرى.'],
            ]);
        }

        RateLimiter::clear($failKey);

        return $this->issueSession($request, $user);
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

        return ApiResponse::success((object) [], 'Logged out successfully.');
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

        return $this->issueSession($request, $user);
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
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/'],
        ], [
            'password.min' => 'Password must be at least 8 characters long.',
            'password.regex' => 'Password must contain uppercase letters, lowercase letters, and numbers.',
        ]);

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
