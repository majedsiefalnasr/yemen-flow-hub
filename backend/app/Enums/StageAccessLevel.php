<?php

namespace App\Enums;

enum StageAccessLevel: string
{
    case VIEW = 'VIEW';
    case EXECUTE = 'EXECUTE';

    /**
     * EXECUTE implies VIEW. A row at $this satisfies a request for $required when
     * it is at least as privileged.
     */
    public function satisfies(self $required): bool
    {
        if ($required === self::VIEW) {
            return true; // both VIEW and EXECUTE grant VIEW
        }

        return $this === self::EXECUTE;
    }
}
