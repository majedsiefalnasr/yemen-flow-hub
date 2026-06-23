<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowTransition;

class WorkflowTransitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('workflow.design');
    }

    public function view(User $user, WorkflowTransition $transition): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, WorkflowTransition $transition): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, WorkflowTransition $transition): bool
    {
        return $this->viewAny($user);
    }
}
