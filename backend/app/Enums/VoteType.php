<?php

namespace App\Enums;

enum VoteType: string
{
    case APPROVE = 'APPROVE';
    case REJECT = 'REJECT';
    case ABSTAIN = 'ABSTAIN';

    public function label(): string
    {
        return match ($this) {
            self::APPROVE => 'Approve / موافقة',
            self::REJECT => 'Reject / رفض',
            self::ABSTAIN => 'Abstain / امتناع',
        };
    }
}
