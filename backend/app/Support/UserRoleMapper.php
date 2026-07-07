<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Exceptions\UnmappedRoleException;

/**
 * Maps governance pivot role codes (intake, system_admin, …) to the application
 * UserRole enum exposed in API responses and frontend contracts.
 */
class UserRoleMapper
{
    public static function toUserRoleValue(string $roleCode): string
    {
        return self::toUserRole($roleCode)->value;
    }

    public static function toUserRole(string $roleCode): UserRole
    {
        return match ($roleCode) {
            RoleCodes::INTAKE => UserRole::DATA_ENTRY,
            RoleCodes::INTERNAL_REVIEWER => UserRole::BANK_REVIEWER,
            RoleCodes::BANK_ADMIN => UserRole::BANK_ADMIN,
            RoleCodes::FX_SWIFT => UserRole::SWIFT_OFFICER,
            RoleCodes::SUPPORT => UserRole::SUPPORT_COMMITTEE,
            RoleCodes::SYSTEM_ADMIN => UserRole::CBY_ADMIN,
            RoleCodes::COMMITTEE_MANAGER => UserRole::EXECUTIVE_MEMBER,
            RoleCodes::COMMITTEE_DIRECTOR => UserRole::COMMITTEE_DIRECTOR,
            RoleCodes::FX_CONFIRM => UserRole::EXECUTIVE_MEMBER,
            default => throw new UnmappedRoleException($roleCode),
        };
    }

    public static function roleCodeFor(UserRole $userRole): string
    {
        return match ($userRole) {
            UserRole::DATA_ENTRY => RoleCodes::INTAKE,
            UserRole::BANK_REVIEWER => RoleCodes::INTERNAL_REVIEWER,
            UserRole::BANK_ADMIN => RoleCodes::BANK_ADMIN,
            UserRole::SWIFT_OFFICER => RoleCodes::FX_SWIFT,
            UserRole::SUPPORT_COMMITTEE => RoleCodes::SUPPORT,
            UserRole::EXECUTIVE_MEMBER => RoleCodes::COMMITTEE_MANAGER,
            UserRole::COMMITTEE_DIRECTOR => RoleCodes::COMMITTEE_DIRECTOR,
            UserRole::CBY_ADMIN => RoleCodes::SYSTEM_ADMIN,
        };
    }

    public static function labelForCode(?string $roleCode): ?string
    {
        if ($roleCode === null) {
            return null;
        }

        try {
            return self::toUserRole($roleCode)->label();
        } catch (UnmappedRoleException) {
            return $roleCode;
        }
    }
}
