<?php

namespace App\Policies;

use App\Models\FieldDefinition;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class FieldDefinitionPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && $this->permissionService->userHasCapability($user, 'workflow_designer', 'MANAGE');
    }

    public function view(User $user, FieldDefinition $field): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, FieldDefinition $field): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, FieldDefinition $field): bool
    {
        return $this->viewAny($user);
    }
}
