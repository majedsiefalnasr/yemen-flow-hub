<?php

namespace App\Policies;

use App\Models\CustomsDeclaration;
use App\Models\User;

class CustomsDeclarationPolicy
{
    public function download(User $user, CustomsDeclaration $declaration): bool
    {
        $bankId = $this->resolveBankId($declaration);

        if ($bankId === null && ! $user->hasAnyRoleCode(['committee_director', 'system_admin'])) {
            return false;
        }

        if ($user->hasAnyRoleCode(['committee_director', 'system_admin'])) {
            return true;
        }

        if ($user->hasRoleCode('internal_reviewer')) {
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

        if ($user->hasAnyRoleCode(['intake', 'internal_reviewer', 'bank_admin'])) {
            return $isSameBank;
        }

        return $user->hasAnyRoleCode(['support', 'committee_manager', 'committee_director', 'system_admin']);
    }

    private function resolveBankId(CustomsDeclaration $declaration): ?int
    {
        $declaration->loadMissing('engineRequest');

        return $declaration->engineRequest?->bank_id;
    }
}
