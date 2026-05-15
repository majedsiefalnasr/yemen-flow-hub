<?php

namespace App\Policies;

use App\Models\ImportRequest;
use App\Models\User;

class ImportRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, ImportRequest $importRequest): bool
    {
        return $user->isCbyUser() || $user->bank_id === $importRequest->bank_id;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasPermission('request.create');
    }

    public function update(User $user, ImportRequest $importRequest): bool
    {
        return $user->hasPermission('request.create')
            && $user->bank_id === $importRequest->bank_id;
    }

    public function delete(User $user, ImportRequest $importRequest): bool
    {
        return $user->hasPermission('request.create')
            && $user->bank_id === $importRequest->bank_id;
    }
}
