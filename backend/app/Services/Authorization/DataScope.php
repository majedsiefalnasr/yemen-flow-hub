<?php

namespace App\Services\Authorization;

use App\DTOs\Authorization\DataScopeContext;
use App\Enums\OrganizationClassification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DataScope
{
    /**
     * Resolve the data scope for a given user based on their organization classification.
     */
    public static function forUser(User $user): DataScopeContext
    {
        $organization = $user->organization;

        if (! $organization) {
            return new DataScopeContext(systemWide: false, ownBankId: null);
        }

        return match ($organization->classification) {
            OrganizationClassification::NATIONAL_COMMITTEE => new DataScopeContext(
                systemWide: true,
                ownBankId: null
            ),
            OrganizationClassification::BANKING_SECTOR => new DataScopeContext(
                systemWide: false,
                ownBankId: $user->bank_id
            ),
            default => new DataScopeContext(
                systemWide: false,
                ownBankId: null
            ),
        };
    }

    /**
     * Apply the data scope filters to an Eloquent query builder.
     */
    public static function applyTo(Builder $query, DataScopeContext $scope, string $bankColumn = 'bank_id'): Builder
    {
        if ($scope->systemWide) {
            return $query;
        }

        if ($scope->ownBankId !== null) {
            return $query->where($bankColumn, $scope->ownBankId);
        }

        // If not system-wide and no bank ID is set, the user should see nothing.
        return $query->whereRaw('1 = 0');
    }
}
