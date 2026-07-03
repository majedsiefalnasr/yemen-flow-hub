<?php

namespace App\Policies;

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
        return (bool) $user->is_active
            && (
                $user->hasRoleCode('system_admin')
                || ($user->hasAnyRoleCode(['intake', 'internal_reviewer', 'bank_admin', 'fx_swift']) && $user->bank_id === $bank->id)
            );
    }

    public function create(User $user): bool
    {
        return $user->hasRoleCode('system_admin');
    }

    public function update(User $user, Bank $bank): bool
    {
        return $user->hasRoleCode('system_admin')
            || ($user->hasRoleCode('bank_admin') && $user->bank_id === $bank->id);
    }

    public function delete(User $user, Bank $bank): bool
    {
        return $user->hasRoleCode('system_admin');
    }
}
