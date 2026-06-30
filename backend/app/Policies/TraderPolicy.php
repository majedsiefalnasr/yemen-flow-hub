<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Trader;
use App\Models\User;

class TraderPolicy
{
    /**
     * Roles permitted to manage and view trader records (code-review 17-B
     * decision #9 — least privilege; trader owner PII is not exposed broadly).
     */
    private const TRADER_ROLES = [
        UserRole::DATA_ENTRY,
        UserRole::BANK_REVIEWER,
        UserRole::BANK_ADMIN,
    ];

    public function viewAny(User $user): bool
    {
        return $this->hasTraderRole($user);
    }

    public function view(User $user, Trader $trader): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasTraderRole($user);
    }

    public function update(User $user, Trader $trader): bool
    {
        return $this->hasTraderRole($user);
    }

    /**
     * Gate for owner identification PII (nationality, identification_number).
     */
    public function viewPii(User $user): bool
    {
        return $this->hasTraderRole($user);
    }

    private function hasTraderRole(User $user): bool
    {
        return $user->is_active && in_array($user->role, self::TRADER_ROLES, true);
    }
}
