<?php

namespace App\Policies;

use App\Models\StageFieldRule;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class StageFieldRulePolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && $this->permissionService->userHasCapability($user, 'workflow_designer', 'MANAGE');
    }

    public function view(User $user, StageFieldRule $rule): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, StageFieldRule $rule): bool
    {
        return $this->viewAny($user);
    }
}
