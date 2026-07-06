<?php

namespace App\Support;

class RoleCodes
{
    public const INTAKE = 'intake';

    public const INTERNAL_REVIEWER = 'internal_reviewer';

    public const BANK_ADMIN = 'bank_admin';

    public const FX_SWIFT = 'fx_swift';

    public const SUPPORT = 'support';

    public const COMMITTEE_MANAGER = 'committee_manager';

    public const COMMITTEE_DIRECTOR = 'committee_director';

    /** FX confirmation officer; no legacy UserRole equivalent (maps to EXECUTIVE_MEMBER during transition). */
    public const FX_CONFIRM = 'fx_confirm';

    public const SYSTEM_ADMIN = 'system_admin';

    public const BANK_ROLES = [
        self::INTAKE,
        self::INTERNAL_REVIEWER,
        self::BANK_ADMIN,
        self::FX_SWIFT,
    ];

    public const BANK_ADMIN_MANAGED = [
        self::INTAKE,
        self::INTERNAL_REVIEWER,
    ];

    public const OVERSIGHT_ROLES = [
        self::SUPPORT,
        self::COMMITTEE_MANAGER,
        self::COMMITTEE_DIRECTOR,
        self::SYSTEM_ADMIN,
    ];
}
