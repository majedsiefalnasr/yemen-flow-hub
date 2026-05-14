<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
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
        $users = User::query()
            ->with('bank')
            ->when(request()->filled('role'), fn ($q) => $q->where('role', request('role')))
            ->when(request()->filled('bank_id'), fn ($q) => $q->where('bank_id', request('bank_id')))
            ->when(request()->has('is_active'), fn ($q) => $q->where('is_active', filter_var(request('is_active'), FILTER_VALIDATE_BOOL)))
            ->latest('id')
            ->paginate(20);

        return ApiResponse::success(UserResource::collection($users), 'Users retrieved.');
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

        $payload = $request->validated();
        if (empty($payload['password'])) {
            unset($payload['password']);
        }

        $user->update($payload);

        return ApiResponse::success(new UserResource($user->refresh()->load('bank')), 'User updated successfully.');
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

        $user->update(['is_active' => false]);

        return ApiResponse::success((object) [], 'User deactivated successfully.');
    }
}
