<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Exceptions\UnmappedRoleException;

/**
 * Maps governance pivot role codes to the legacy users.role enum during RM-1/RM-2.
 * Dual-write shim and API backward-compat scalars use this until RM-3 drops the column.
 */
class LegacyRoleMapper
{
    public static function toLegacyValue(string $roleCode): string
    {
        return self::toLegacyEnum($roleCode)->value;
    }

    public static function toLegacyEnum(string $roleCode): UserRole
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

    public static function labelForCode(?string $roleCode): ?string
    {
        if ($roleCode === null) {
            return null;
        }

        try {
            return self::toLegacyEnum($roleCode)->label();
        } catch (UnmappedRoleException) {
            return $roleCode;
        }
    }
}
