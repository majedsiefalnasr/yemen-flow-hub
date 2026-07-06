<?php

namespace App\Support;

use App\Enums\OrganizationClassification;
use App\Models\Organization;
use App\Models\User;

class RequestCreationGate
{
    public static function userCanCreateRequests(User $user): bool
    {
        if ($user->organization_id === null || $user->bank_id === null) {
            return false;
        }

        $organization = $user->relationLoaded('organization')
            ? $user->organization
            : Organization::query()->find($user->organization_id);

        return $organization?->classification === OrganizationClassification::BANKING_SECTOR;
    }
}
