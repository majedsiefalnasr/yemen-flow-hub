<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowStage;

class WorkflowStagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('workflow.design');
    }

    public function view(User $user, WorkflowStage $stage): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, WorkflowStage $stage): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, WorkflowStage $stage): bool
    {
        return $this->viewAny($user);
    }
}
