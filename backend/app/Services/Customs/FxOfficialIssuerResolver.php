<?php

namespace App\Services\Customs;

use App\Models\User;
use App\Support\RoleCodes;

/**
 * Resolves the official business issuer for FX confirmation artifacts (F-14).
 * The transition actor is recorded separately as generated_by.
 */
class FxOfficialIssuerResolver
{
    public function resolve(): ?User
    {
        return User::query()
            ->where('is_active', true)
            ->withActiveRoleCode(RoleCodes::COMMITTEE_DIRECTOR)
            ->orderBy('id')
            ->first();
    }
}
