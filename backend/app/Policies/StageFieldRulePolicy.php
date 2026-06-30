<?php

namespace App\Policies;

use App\Models\StageFieldRule;
use App\Models\User;

class StageFieldRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('workflow.design');
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
