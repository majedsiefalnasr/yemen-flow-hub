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

    public function cbyAdmin(User $user): bool
    {
        return $user->hasRole(UserRole::CBY_ADMIN);
    }

    public function resetPassword(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->hasRole(UserRole::CBY_ADMIN)) {
            return $model->role?->isCbyRole() || $model->hasRole(UserRole::BANK_ADMIN);
        }

        return $this->canManageOwnBankUser($user, $model);
    }

    public function resetMfa(User $user, User $model): bool
    {
        return $user->id !== $model->id
            && ($user->hasRole(UserRole::CBY_ADMIN) || $this->canManageOwnBankUser($user, $model));
    }

    public function resetPin(User $user, User $model): bool
    {
        return $user->id !== $model->id
            && ($user->hasRole(UserRole::CBY_ADMIN) || $this->canManageOwnBankUser($user, $model));
    }

    private function canManageOwnBankUser(User $actor, User $target): bool
    {
        return $actor->hasRole(UserRole::BANK_ADMIN)
            && $actor->bank_id !== null
            && $target->bank_id === $actor->bank_id
            && $target->role?->isBankAdminManageable();
    }
}
