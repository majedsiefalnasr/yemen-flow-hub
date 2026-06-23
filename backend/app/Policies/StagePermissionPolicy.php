<?php

namespace App\Policies;

use App\Models\StagePermission;
use App\Models\User;

class StagePermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('workflow.design');
    }

    public function view(User $user, StagePermission $permission): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, StagePermission $permission): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, StagePermission $permission): bool
    {
        return $this->viewAny($user);
    }
}
