<?php

namespace App\Enums;

enum GovernanceReferenceEntityType: string
{
    case ORGANIZATION = 'organization';
    case TEAM = 'team';
    case ROLE = 'role';
    case USER = 'user';
    case REFERENCE_TABLE = 'reference_table';
    case REFERENCE_VALUE = 'reference_value';

    public function permissionColumn(): ?string
    {
        return match ($this) {
            self::ORGANIZATION => 'organization_id',
            self::TEAM => 'team_id',
            self::ROLE => 'role_id',
            self::USER => 'user_id',
            self::REFERENCE_TABLE, self::REFERENCE_VALUE => null,
        };
    }

    public function deleteBlockedErrorCode(): string
    {
        return match ($this) {
            self::ORGANIZATION => 'ORGANIZATION_REFERENCED_BY_PUBLISHED_WORKFLOW',
            self::TEAM => 'TEAM_REFERENCED_BY_PUBLISHED_WORKFLOW',
            self::ROLE => 'ROLE_REFERENCED_BY_PUBLISHED_WORKFLOW',
            self::USER => 'USER_REFERENCED_BY_PUBLISHED_WORKFLOW',
            self::REFERENCE_TABLE => 'REFERENCE_TABLE_PROTECTED',
            self::REFERENCE_VALUE => 'REFERENCE_VALUE_PROTECTED',
        };
    }

    public function deactivateBlockedErrorCode(): string
    {
        return match ($this) {
            self::ORGANIZATION => 'ORGANIZATION_WOULD_BREAK_EXECUTOR',
            self::TEAM => 'TEAM_WOULD_BREAK_EXECUTOR',
            self::ROLE => 'ROLE_WOULD_BREAK_EXECUTOR',
            self::USER => 'USER_WOULD_BREAK_EXECUTOR',
            self::REFERENCE_TABLE => 'REFERENCE_TABLE_PROTECTED',
            self::REFERENCE_VALUE => 'REFERENCE_VALUE_PROTECTED',
        };
    }
}
