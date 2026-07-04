<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowAction;
use App\Services\Authorization\PermissionService;

class WorkflowActionPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && $this->permissionService->userHasCapability($user, 'workflow_designer', 'MANAGE');
    }

    public function view(User $user, WorkflowAction $action): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, WorkflowAction $action): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, WorkflowAction $action): bool
    {
        return $this->viewAny($user);
    }
}
