<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class OrganizationPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $this->permissionService->userHasCapability($user, 'roles', 'MANAGE');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->viewAny($user);
    }
}
