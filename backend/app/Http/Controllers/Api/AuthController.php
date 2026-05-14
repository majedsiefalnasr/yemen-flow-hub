<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
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
            new OA\Response(response: 403, description: 'Inactive account'),
        ]
    )]
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $user = User::query()->where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (!$user->is_active) {
            return ApiResponse::forbidden('Account is inactive.');
        }

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
        $user->currentAccessToken()?->delete();

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
}
