<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('roles.manage');
    }

    public function view(User $user, Team $team): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Team $team): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->viewAny($user);
    }
}
