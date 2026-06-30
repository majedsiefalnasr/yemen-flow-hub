<?php

namespace App\Policies;

use App\Models\FieldGroup;
use App\Models\User;

class FieldGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('workflow.design');
    }

    public function view(User $user, FieldGroup $group): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, FieldGroup $group): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, FieldGroup $group): bool
    {
        return $this->viewAny($user);
    }
}
