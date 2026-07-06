<?php

namespace App\Policies;

use App\Models\CustomsDeclaration;
use App\Models\User;
use App\Support\RoleCodes;

class CustomsDeclarationPolicy
{
    public function download(User $user, CustomsDeclaration $declaration): bool
    {
        $bankId = $this->resolveBankId($declaration);

        if ($bankId === null && ! $user->hasAnyRoleCode([RoleCodes::COMMITTEE_DIRECTOR, RoleCodes::SYSTEM_ADMIN])) {
            return false;
        }

        if ($user->hasAnyRoleCode([RoleCodes::COMMITTEE_DIRECTOR, RoleCodes::SYSTEM_ADMIN])) {
            return true;
        }

        if ($user->hasRoleCode(RoleCodes::INTERNAL_REVIEWER)) {
            return $user->bank_id !== null && $user->bank_id === $bankId;
        }

        return false;
    }

    /**
     * Who can download the signed FX confirmation document uploaded by the director.
     * Bank users of the same bank get the final deliverable they submitted the request for.
     */
    public function downloadSignedFx(User $user, CustomsDeclaration $declaration): bool
    {
        $bankId = $this->resolveBankId($declaration);
        $isSameBank = $user->bank_id !== null && $user->bank_id === $bankId;

        if ($user->hasAnyRoleCode([RoleCodes::INTAKE, RoleCodes::INTERNAL_REVIEWER, RoleCodes::BANK_ADMIN])) {
            return $isSameBank;
        }

        return $user->hasAnyRoleCode(RoleCodes::OVERSIGHT_ROLES);
    }

    private function resolveBankId(CustomsDeclaration $declaration): ?int
    {
        $declaration->loadMissing('engineRequest');

        return $declaration->engineRequest?->bank_id;
    }
}
