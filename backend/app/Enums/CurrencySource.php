<?php

namespace App\Enums;

enum CurrencySource: string
{
    case OWN_FUNDS = 'OWN_FUNDS';
    case BANK_FINANCING = 'BANK_FINANCING';
    case EXTERNAL_FINANCING = 'EXTERNAL_FINANCING';

    public function label(): string
    {
        return match ($this) {
            self::OWN_FUNDS => 'موارد ذاتية / Own Funds',
            self::BANK_FINANCING => 'تمويل بنكي / Bank Financing',
            self::EXTERNAL_FINANCING => 'تمويل خارجي / External Financing',
        };
    }
}
