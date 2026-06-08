<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Trader;
use App\Models\User;

class TraderPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Trader $trader): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Trader $trader): bool
    {
        return $this->canWrite($user);
    }

    private function canWrite(User $user): bool
    {
        return $user->is_active && in_array($user->role, [
            UserRole::DATA_ENTRY,
            UserRole::BANK_REVIEWER,
            UserRole::BANK_ADMIN,
        ], true);
    }
}
