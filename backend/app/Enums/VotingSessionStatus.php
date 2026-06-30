<?php

namespace App\Enums;

enum VotingSessionStatus: string
{
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';
    case FINALIZED = 'FINALIZED';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'مفتوحة / Open',
            self::CLOSED => 'مغلقة / Closed',
            self::FINALIZED => 'مُنهاة / Finalized',
        };
    }
}
