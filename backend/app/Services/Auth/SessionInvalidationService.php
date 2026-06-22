<?php

namespace App\Services\Auth;

use App\Models\User;

class SessionInvalidationService
{
    public function invalidate(User $user): void
    {
        $user->tokens()->delete();
    }
}
