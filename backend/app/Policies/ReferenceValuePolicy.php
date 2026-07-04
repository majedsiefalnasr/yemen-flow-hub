<?php

namespace App\Policies;

use App\Models\ReferenceValue;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class ReferenceValuePolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && $this->permissionService->userHasCapability($user, 'reference_data', 'MANAGE');
    }

    public function view(User $user, ReferenceValue $referenceValue): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ReferenceValue $referenceValue): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ReferenceValue $referenceValue): bool
    {
        return $this->viewAny($user);
    }
}
