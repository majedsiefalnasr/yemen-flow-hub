<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\User;

class BankPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Bank $bank): bool
    {
        return (bool) $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::CBY_ADMIN);
    }

    public function update(User $user, Bank $bank): bool
    {
        return $user->hasRole(UserRole::CBY_ADMIN);
    }

    public function delete(User $user, Bank $bank): bool
    {
        return $user->hasRole(UserRole::CBY_ADMIN);
    }
}
