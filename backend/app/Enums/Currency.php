<?php

namespace App\Enums;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case SAR = 'SAR';
    case AED = 'AED';
    case CNY = 'CNY';

    public function label(): string
    {
        return match ($this) {
            self::USD => 'دولار أمريكي / US Dollar',
            self::EUR => 'يورو / Euro',
            self::SAR => 'ريال سعودي / Saudi Riyal',
            self::AED => 'درهم إماراتي / UAE Dirham',
            self::CNY => 'يوان صيني / Chinese Yuan',
        };
    }
}
