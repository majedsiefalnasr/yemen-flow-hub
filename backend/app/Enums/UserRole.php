<?php

namespace App\Enums;

enum UserRole: string
{
    case CBY_ADMIN = 'CBY_ADMIN';
    case BANK_MANAGER = 'BANK_MANAGER';
    case DATA_ENTRY = 'DATA_ENTRY';
    case BANK_REVIEWER = 'BANK_REVIEWER';
    case SWIFT_OFFICER = 'SWIFT_OFFICER';
    case SUPPORT_COMMITTEE = 'SUPPORT_COMMITTEE';
    case EXECUTIVE_MEMBER = 'EXECUTIVE_MEMBER';
    case EXECUTIVE_DIRECTOR = 'EXECUTIVE_DIRECTOR';

    public function label(): string
    {
        return match ($this) {
            self::CBY_ADMIN => 'مسؤول النظام (CBY) / CBY Admin',
            self::BANK_MANAGER => 'مسؤول البنك / Bank Manager',
            self::DATA_ENTRY => 'موظف إدخال البنك / Bank Data Entry',
            self::BANK_REVIEWER => 'مراجع داخلي بالبنك / Bank Internal Reviewer',
            self::SWIFT_OFFICER => 'موظف السويفت بالبنك / Bank SWIFT Officer',
            self::SUPPORT_COMMITTEE => 'عضو اللجنة المساندة / Support Committee Member',
            self::EXECUTIVE_MEMBER => 'عضو اللجنة التنفيذية / Executive Committee Member',
            self::EXECUTIVE_DIRECTOR => 'مدير اللجنة التنفيذية / Executive Committee Director',
        };
    }

    public function isBankRole(): bool
    {
        return in_array($this, [self::DATA_ENTRY, self::BANK_REVIEWER, self::SWIFT_OFFICER, self::BANK_MANAGER], true);
    }

    public function isCbyRole(): bool
    {
        return in_array($this, [self::CBY_ADMIN, self::SUPPORT_COMMITTEE, self::EXECUTIVE_MEMBER, self::EXECUTIVE_DIRECTOR], true);
    }
}
