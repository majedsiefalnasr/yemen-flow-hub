<?php

namespace App\Policies;

use App\Models\ReferenceTable;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class ReferenceTablePolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && $this->permissionService->userHasCapability($user, 'reference_data', 'MANAGE');
    }

    public function view(User $user, ReferenceTable $referenceTable): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ReferenceTable $referenceTable): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ReferenceTable $referenceTable): bool
    {
        return $this->viewAny($user);
    }
}
