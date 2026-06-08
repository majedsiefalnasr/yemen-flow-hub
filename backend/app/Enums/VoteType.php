<?php

namespace App\Enums;

enum VoteType: string
{
    case APPROVE = 'APPROVE';
    case REJECT = 'REJECT';
    case ABSTAIN = 'ABSTAIN';
    case AUTO_ABSTAIN_TIMEOUT = 'AUTO_ABSTAIN_TIMEOUT';

    public function label(): string
    {
        return match ($this) {
            self::APPROVE => 'موافقة / Approve',
            self::REJECT => 'غير مستوفي للشروط / Not Eligible',
            self::ABSTAIN => 'امتناع / Abstain',
            self::AUTO_ABSTAIN_TIMEOUT => 'امتناع تلقائي (انتهاء الوقت) / Auto-Abstain (Timeout)',
        };
    }
}
