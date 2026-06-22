<?php

namespace App\Policies;

use App\Models\ReferenceValue;
use App\Models\User;

class ReferenceValuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('docrules.manage');
    }

    public function view(User $user, ReferenceValue $referenceValue): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ReferenceValue $referenceValue): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ReferenceValue $referenceValue): bool
    {
        return $this->viewAny($user);
    }
}
