<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class TeamPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && $this->permissionService->userHasCapability($user, 'roles', 'MANAGE');
    }

    public function view(User $user, Team $team): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Team $team): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->viewAny($user);
    }
}
