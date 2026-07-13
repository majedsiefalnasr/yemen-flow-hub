<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\PermissionService;
use App\Support\RoleCodes;

class UserPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || $this->canManageBankStaff($user);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || $this->canManageOwnBankUser($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || $this->canManageBankStaff($user);
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

    private function canManageBankStaff(User $actor): bool
    {
        return $actor->bank_id !== null
            && $this->permissions->userHasCapability($actor, 'staff', 'VIEW');
    }

    private function canManageOwnBankUser(User $actor, User $target): bool
    {
        return $this->canManageBankStaff($actor)
            && $target->bank_id === $actor->bank_id
            && $target->hasAnyRoleCode(RoleCodes::BANK_ADMIN_MANAGED);
    }
}
