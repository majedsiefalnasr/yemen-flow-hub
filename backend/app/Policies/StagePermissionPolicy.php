<?php

namespace App\Policies;

use App\Models\StagePermission;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class StagePermissionPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && $this->permissionService->userHasCapability($user, 'workflow_designer', 'MANAGE');
    }

    public function view(User $user, StagePermission $permission): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, StagePermission $permission): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, StagePermission $permission): bool
    {
        return $this->viewAny($user);
    }
}
