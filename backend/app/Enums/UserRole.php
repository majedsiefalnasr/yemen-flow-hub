<?php

namespace App\Enums;

enum UserRole: string
{
    case DATA_ENTRY = 'DATA_ENTRY';
    case BANK_REVIEWER = 'BANK_REVIEWER';
    case BANK_ADMIN = 'BANK_ADMIN';
    case SWIFT_OFFICER = 'SWIFT_OFFICER';
    case SUPPORT_COMMITTEE = 'SUPPORT_COMMITTEE';
    case EXECUTIVE_MEMBER = 'EXECUTIVE_MEMBER';
    case COMMITTEE_DIRECTOR = 'COMMITTEE_DIRECTOR';
    case CBY_ADMIN = 'CBY_ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::DATA_ENTRY => 'موظف إدخال البنك / Bank Data Entry',
            self::BANK_REVIEWER => 'مراجع داخلي بالبنك / Bank Internal Reviewer',
            self::BANK_ADMIN => 'مسؤول البنك / Bank Admin',
            self::SWIFT_OFFICER => 'موظف السويفت بالبنك / Bank SWIFT Officer',
            self::SUPPORT_COMMITTEE => 'عضو اللجنة المساندة / Support Committee Member',
            self::EXECUTIVE_MEMBER => 'عضو اللجنة التنفيذية / Executive Committee Member',
            self::COMMITTEE_DIRECTOR => 'مدير اللجنة التنفيذية / Committee Director',
            self::CBY_ADMIN => 'مسؤول النظام (CBY) / CBY Admin',
        };
    }

    public function isBankRole(): bool
    {
        return in_array($this, [self::DATA_ENTRY, self::BANK_REVIEWER, self::BANK_ADMIN, self::SWIFT_OFFICER], true);
    }

    public function isBankOperationalRole(): bool
    {
        return in_array($this, [self::DATA_ENTRY, self::BANK_REVIEWER, self::SWIFT_OFFICER], true);
    }

    public function isBankAdminManageable(): bool
    {
        return in_array($this, [self::DATA_ENTRY, self::BANK_REVIEWER], true);
    }

    public function isCbyRole(): bool
    {
        return in_array($this, [
            self::CBY_ADMIN,
            self::SUPPORT_COMMITTEE,
            self::EXECUTIVE_MEMBER,
            self::COMMITTEE_DIRECTOR,
        ], true);
    }
}
