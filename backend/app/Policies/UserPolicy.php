<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleCode('system_admin')
            || ($user->hasRoleCode('bank_admin') && $user->bank_id !== null);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRoleCode('system_admin')
            || $this->canManageOwnBankUser($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasRoleCode('system_admin')
            || ($user->hasRoleCode('bank_admin') && $user->bank_id !== null);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRoleCode('system_admin')
            || $this->canManageOwnBankUser($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRoleCode('system_admin')
            || $this->canManageOwnBankUser($user, $model);
    }

    public function cbyAdmin(User $user): bool
    {
        return $user->hasRoleCode('system_admin');
    }

    public function resetPassword(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->hasRoleCode('system_admin')) {
            return $model->hasAnyRoleCode(['system_admin', 'support', 'committee_manager', 'committee_director', 'bank_admin']);
        }

        return $this->canManageOwnBankUser($user, $model);
    }

    public function resetMfa(User $user, User $model): bool
    {
        return $user->id !== $model->id
            && ($user->hasRoleCode('system_admin') || $this->canManageOwnBankUser($user, $model));
    }

    public function resetPin(User $user, User $model): bool
    {
        return $user->id !== $model->id
            && ($user->hasRoleCode('system_admin') || $this->canManageOwnBankUser($user, $model));
    }

    private function canManageOwnBankUser(User $actor, User $target): bool
    {
        return $actor->hasRoleCode('bank_admin')
            && $actor->bank_id !== null
            && $target->bank_id === $actor->bank_id
            && $target->hasAnyRoleCode(['intake', 'internal_reviewer']);
    }
}
