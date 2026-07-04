<?php

namespace App\Policies;

use App\Models\Merchant;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class MerchantPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Merchant $merchant): bool
    {
        return ! $user->isBankUser() || $user->bank_id === $merchant->bank_id;
    }

    public function create(User $user): bool
    {
        return $this->permissionService->userHasCapability($user, 'merchants', 'MANAGE');
    }

    public function update(User $user, Merchant $merchant): bool
    {
        return $this->permissionService->userHasCapability($user, 'merchants', 'MANAGE')
            && (! $user->isBankUser() || $user->bank_id === $merchant->bank_id);
    }

    public function delete(User $user, Merchant $merchant): bool
    {
        return $this->update($user, $merchant);
    }
}
