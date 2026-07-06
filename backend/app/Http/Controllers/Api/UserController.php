<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Auth\SessionInvalidationService;
use App\Support\ApiResponse;
use App\Support\PasswordPolicy;
use App\Support\RoleCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly SessionInvalidationService $sessionInvalidationService,
    ) {}

    #[OA\Get(
        path: '/api/users',
        tags: ['Users'],
        summary: 'List users with filters',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Users retrieved')]
    )]
    public function index()
    {
        $this->authorize('viewAny', User::class);
        $actor = request()->user();
        $perPage = max(1, min(request()->integer('per_page', 20), 200));

        $users = User::query()
            ->with('bank')
            ->when(
                $actor->hasRoleCode(RoleCodes::BANK_ADMIN),
                fn ($q) => $q->where('bank_id', $actor->bank_id)
                    ->whereHas('roles', fn ($rq) => $rq->whereIn('code', RoleCodes::BANK_ADMIN_MANAGED))
            )
            ->when(request()->filled('role'), fn ($q) => $q->where('role', request('role')))
            ->when(
                request()->filled('bank_id') && ! $actor->hasRoleCode(RoleCodes::BANK_ADMIN),
                fn ($q) => $q->where('bank_id', request('bank_id'))
            )
            ->when(request()->has('is_active'), fn ($q) => $q->where('is_active', filter_var(request('is_active'), FILTER_VALIDATE_BOOL)))
            ->when(request()->filled('search'), function ($q) {
                $s = request('search');
                $q->where(fn ($x) => $x->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
            })
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::success([
            'data' => UserResource::collection($users->getCollection())->resolve(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], 'Users retrieved.');
    }

    #[OA\Post(
        path: '/api/users',
        tags: ['Users'],
        summary: 'Create user',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
                    new OA\Property(property: 'password', type: 'string', minLength: 8),
                    new OA\Property(property: 'role', type: 'string'),
                    new OA\Property(property: 'bank_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'User created')]
    )]
    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $user = User::query()->create($request->validated());
        $this->auditService->log(AuditAction::USER_CREATED, $request->user(), $user, [
            'bank_id' => $user->bank_id,
            'target_role' => $user->role?->value,
            'after' => $this->auditSnapshot($user),
        ]);

        return ApiResponse::success(new UserResource($user->load('bank')), 'User created successfully.', 201);
    }

    #[OA\Get(
        path: '/api/users/{id}',
        tags: ['Users'],
        summary: 'Get user details',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'User retrieved')]
    )]
    public function show(User $user)
    {
        $this->authorize('view', $user);

        return ApiResponse::success(new UserResource($user->load('bank')), 'User retrieved.');
    }

    #[OA\Put(
        path: '/api/users/{id}',
        tags: ['Users'],
        summary: 'Update user',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'User updated')]
    )]
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $before = $this->auditSnapshot($user);
        $payload = $request->validated();
        if (empty($payload['password'])) {
            unset($payload['password']);
        }

        $user->update($payload);
        $user->refresh();
        if (array_intersect(['role', 'bank_id', 'is_active'], array_keys($payload))) {
            $this->sessionInvalidationService->invalidate($user);
        }
        $after = $this->auditSnapshot($user);
        $changedKeys = array_keys(array_filter(
            $after,
            fn ($v, $k) => array_key_exists($k, $before) && $before[$k] !== $v,
            ARRAY_FILTER_USE_BOTH,
        ));

        $this->auditService->log(AuditAction::USER_UPDATED, $request->user(), $user, [
            'bank_id' => $user->bank_id,
            'target_role' => $user->role?->value,
            'password_reset' => array_key_exists('password', $payload),
            'before' => array_intersect_key($before, array_flip($changedKeys)),
            'after' => array_intersect_key($after, array_flip($changedKeys)),
        ]);

        return ApiResponse::success(new UserResource($user->load('bank')), 'User updated successfully.');
    }

    #[OA\Delete(
        path: '/api/users/{id}',
        tags: ['Users'],
        summary: 'Deactivate user',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'User deactivated')]
    )]
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $before = $this->auditSnapshot($user);
        $user->update(['is_active' => false]);
        $user->refresh();
        $this->sessionInvalidationService->invalidate($user);
        $this->auditService->log(AuditAction::USER_DEACTIVATED, request()->user(), $user, [
            'bank_id' => $user->bank_id,
            'target_role' => $user->role?->value,
            'before' => $before,
            'after' => $this->auditSnapshot($user),
        ]);

        return ApiResponse::success((object) [], 'User deactivated successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorize('resetPassword', $user);

        $validated = $request->validate([
            'password' => ['required', ...PasswordPolicy::rules(), 'confirmed'],
        ], PasswordPolicy::messages());

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => true,
            'temporary_password_set_at' => now(),
        ])->save();
        $this->sessionInvalidationService->invalidate($user);

        $this->auditService->log(AuditAction::PASSWORD_RESET, $request->user(), $user, [
            'mode' => 'admin_assisted',
            'target_role' => $user->role?->value,
            'target_bank_id' => $user->bank_id,
        ]);

        return ApiResponse::success(new UserResource($user->load('bank')), 'Temporary password set successfully.');
    }

    public function resetMfa(Request $request, User $user)
    {
        $this->authorize('resetMfa', $user);

        $user->forceFill([
            'totp_secret' => null,
            'totp_enabled' => false,
            'totp_recovery_codes' => null,
            'mfa_enabled' => false,
        ])->save();

        $this->auditService->log(AuditAction::MFA_RESET, $request->user(), $user, [
            'target_role' => $user->role?->value,
            'target_bank_id' => $user->bank_id,
        ]);

        return ApiResponse::success(new UserResource($user->load('bank')), 'MFA reset successfully.');
    }

    public function resetPin(Request $request, User $user)
    {
        $this->authorize('resetPin', $user);

        $user->forceFill([
            'pin_code_hash' => null,
            'pin_enabled' => false,
        ])->save();

        $this->auditService->log(AuditAction::PIN_RESET, $request->user(), $user, [
            'target_role' => $user->role?->value,
            'target_bank_id' => $user->bank_id,
        ]);

        return ApiResponse::success(new UserResource($user->load('bank')), 'PIN reset successfully.');
    }

    private function auditSnapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'bank_id' => $user->bank_id,
            'is_active' => $user->is_active,
        ];
    }
}
