<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowDefinition;

class WorkflowDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('workflow.design');
    }

    public function view(User $user, WorkflowDefinition $definition): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, WorkflowDefinition $definition): bool
    {
        return $this->viewAny($user);
    }
}
