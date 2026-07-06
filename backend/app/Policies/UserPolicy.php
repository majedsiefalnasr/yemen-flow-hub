<?php

namespace App\Policies;

use App\Models\User;
use App\Support\RoleCodes;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || ($user->hasRoleCode(RoleCodes::BANK_ADMIN) && $user->bank_id !== null);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || $this->canManageOwnBankUser($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || ($user->hasRoleCode(RoleCodes::BANK_ADMIN) && $user->bank_id !== null);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || $this->canManageOwnBankUser($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || $this->canManageOwnBankUser($user, $model);
    }

    public function cbyAdmin(User $user): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN);
    }

    public function resetPassword(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            return true;
        }

        return $this->canManageOwnBankUser($user, $model);
    }

    public function resetMfa(User $user, User $model): bool
    {
        return $user->id !== $model->id
            && ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN) || $this->canManageOwnBankUser($user, $model));
    }

    public function resetPin(User $user, User $model): bool
    {
        return $user->id !== $model->id
            && ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN) || $this->canManageOwnBankUser($user, $model));
    }

    private function canManageOwnBankUser(User $actor, User $target): bool
    {
        return $actor->hasRoleCode(RoleCodes::BANK_ADMIN)
            && $actor->bank_id !== null
            && $target->bank_id === $actor->bank_id
            && $target->hasAnyRoleCode(RoleCodes::BANK_ADMIN_MANAGED);
    }
}
