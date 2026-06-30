<?php

namespace App\Policies;

use App\Models\FieldDefinition;
use App\Models\User;

class FieldDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('workflow.design');
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
