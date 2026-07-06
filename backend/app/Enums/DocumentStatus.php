<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Active = 'active';
    case Superseded = 'superseded';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
