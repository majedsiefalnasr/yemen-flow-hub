<?php

namespace App\Enums;

enum PortOfArrival: string
{
    case ADEN = 'ADEN';
    case HODEIDAH = 'HODEIDAH';
    case MUKALLA = 'MUKALLA';
    case MOKHA = 'MOKHA';
    case NISHTUN = 'NISHTUN';

    public function label(): string
    {
        return match ($this) {
            self::ADEN => 'ميناء عدن / Aden Port',
            self::HODEIDAH => 'ميناء الحديدة / Hodeidah Port',
            self::MUKALLA => 'ميناء المكلا / Mukalla Port',
            self::MOKHA => 'ميناء المخا / Mokha Port',
            self::NISHTUN => 'ميناء نشطون / Nishtun Port',
        };
    }
}
