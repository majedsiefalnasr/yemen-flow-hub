<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    private const LOCKOUT_THRESHOLD = 10;
    private const LOCKOUT_MINUTES = 15;

    public function __construct(private readonly AuditService $auditService)
    {
    }

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
            new OA\Response(response: 403, description: 'Inactive account or account locked'),
            new OA\Response(response: 429, description: 'Too many requests — per-IP throttle via throttle:login middleware'),
        ]
    )]
    public function login(LoginRequest $request)
    {
        $ip = $request->ip();
        // Normalize email for consistent counter keys and DB lookup
        $email = $request->string('email')->lower()->toString();
        $failKey = 'login_fail:' . $email;

        // Per-email account lockout: 10 consecutive failures → 15 min lock
        if (RateLimiter::tooManyAttempts($failKey, self::LOCKOUT_THRESHOLD)) {
            $this->logFailedLogin($email, 'LOCKED', $ip);
            return ApiResponse::lockedOut();
        }

        $user = User::query()->where('email', $email)->first();

        // Check is_active before password — inactive is an admin state, not an auth failure
        if ($user && !$user->is_active) {
            $this->logFailedLogin($email, 'INACTIVE', $ip);
            return ApiResponse::forbidden('Account is inactive.');
        }

        if (!$user || !Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($failKey, self::LOCKOUT_MINUTES * 60);
            $this->logFailedLogin($email, 'WRONG_CREDENTIALS', $ip);
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Success: clear only the per-email failure counter; IP window decays naturally
        RateLimiter::clear($failKey);

        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $this->auditService->log(
            AuditAction::LOGIN,
            $user,
            $user,
            ['mode' => $request->hasSession() ? 'cookie' : 'token']
        );

        if ($request->hasSession()) {
            return ApiResponse::success([
                'user' => new UserResource($user->fresh('bank')),
                'token' => null,
                'token_type' => null,
                'mode' => 'cookie',
            ], 'Login successful.');
        }

        $token = $user->createToken($request->string('device_name')->toString() ?: 'api-client')->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user->fresh('bank')),
            'token' => $token,
            'token_type' => 'Bearer',
            'mode' => 'token',
        ], 'Login successful.');
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
            new UserResource($request->user()->loadMissing('bank')),
            'User profile retrieved.'
        );
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

    private function logFailedLogin(string $email, string $reason, string $ip): void
    {
        $this->auditService->log(
            AuditAction::LOGIN_FAILED,
            null,
            null,
            ['email' => $email, 'reason' => $reason, 'ip' => $ip]
        );
    }
}
