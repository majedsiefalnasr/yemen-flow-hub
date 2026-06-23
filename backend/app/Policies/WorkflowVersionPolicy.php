<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowVersion;

class WorkflowVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('workflow.design');
    }

    public function view(User $user, WorkflowVersion $version): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, WorkflowVersion $version): bool
    {
        return $this->viewAny($user);
    }

    public function clone(User $user, WorkflowVersion $version): bool
    {
        return $this->viewAny($user);
    }
}
