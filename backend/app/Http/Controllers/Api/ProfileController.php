<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\Audit\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditService $auditService)
    {
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

        $resource = new UserResource($user);

        return ApiResponse::success(
            array_merge($resource->resolve(), [
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

    #[OA\Post(
        path: '/api/profile/change-password',
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
}
