<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CustomsDeclaration;
use App\Models\User;

class CustomsDeclarationPolicy
{
    public function download(User $user, CustomsDeclaration $declaration): bool
    {
        $declaration->loadMissing('request');

        if ($declaration->request === null) {
            return false;
        }

        $requestBankId = $declaration->request->bank_id;

        return match ($user->role) {
            UserRole::COMMITTEE_DIRECTOR,
            UserRole::CBY_ADMIN => true,
            UserRole::BANK_REVIEWER => $user->bank_id !== null && $user->bank_id === $requestBankId,
            default => false,
        };
    }
}
