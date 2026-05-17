<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::CBY_ADMIN)
            || ($user->hasRole(UserRole::BANK_ADMIN) && $user->bank_id !== null);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole(UserRole::CBY_ADMIN)
            || $this->canManageOwnBankUser($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::CBY_ADMIN)
            || ($user->hasRole(UserRole::BANK_ADMIN) && $user->bank_id !== null);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole(UserRole::CBY_ADMIN)
            || $this->canManageOwnBankUser($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole(UserRole::CBY_ADMIN)
            || $this->canManageOwnBankUser($user, $model);
    }

    private function canManageOwnBankUser(User $actor, User $target): bool
    {
        return $actor->hasRole(UserRole::BANK_ADMIN)
            && $actor->bank_id !== null
            && $target->bank_id === $actor->bank_id
            && $target->role?->isBankAdminManageable();
    }
}
