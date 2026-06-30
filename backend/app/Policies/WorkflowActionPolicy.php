<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowAction;

class WorkflowActionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('workflow.design');
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
