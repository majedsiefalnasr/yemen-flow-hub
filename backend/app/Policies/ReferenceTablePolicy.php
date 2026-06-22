<?php

namespace App\Policies;

use App\Models\ReferenceTable;
use App\Models\User;

class ReferenceTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('docrules.manage');
    }

    public function view(User $user, ReferenceTable $referenceTable): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ReferenceTable $referenceTable): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ReferenceTable $referenceTable): bool
    {
        return $this->viewAny($user);
    }
}
