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

    /**
     * Who can download the signed FX confirmation document uploaded by the director.
     * Bank users of the same bank get the final deliverable they submitted the request for.
     */
    public function downloadSignedFx(User $user, CustomsDeclaration $declaration): bool
    {
        $declaration->loadMissing('request');

        if ($declaration->request === null) {
            return false;
        }

        $requestBankId = $declaration->request->bank_id;
        $isSameBank = $user->bank_id !== null && $user->bank_id === $requestBankId;

        return match ($user->role) {
            UserRole::DATA_ENTRY,
            UserRole::BANK_REVIEWER,
            UserRole::BANK_ADMIN  => $isSameBank,
            UserRole::SUPPORT_COMMITTEE,
            UserRole::EXECUTIVE_MEMBER,
            UserRole::COMMITTEE_DIRECTOR,
            UserRole::CBY_ADMIN   => true,
            default               => false,
        };
    }
}
