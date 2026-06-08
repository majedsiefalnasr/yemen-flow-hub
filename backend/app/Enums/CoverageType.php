<?php

namespace App\Enums;

enum CoverageType: string
{
    case FULL = 'FULL';
    case PARTIAL = 'PARTIAL';

    public function label(): string
    {
        return match ($this) {
            self::FULL => 'تغطية كاملة / Full Coverage',
            self::PARTIAL => 'تغطية جزئية / Partial Coverage',
        };
    }
}
