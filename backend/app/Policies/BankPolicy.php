<?php

namespace App\Policies;

use App\Models\Bank;
use App\Models\User;
use App\Support\RoleCodes;

class BankPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Bank $bank): bool
    {
        return (bool) $user->is_active
            && (
                $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
                || ($user->hasAnyRoleCode(RoleCodes::BANK_ROLES) && $user->bank_id === $bank->id)
            );
    }

    public function create(User $user): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN);
    }

    public function update(User $user, Bank $bank): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || ($user->hasRoleCode(RoleCodes::BANK_ADMIN) && $user->bank_id === $bank->id);
    }

    public function delete(User $user, Bank $bank): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN);
    }
}
