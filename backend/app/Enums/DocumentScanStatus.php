<?php

namespace App\Enums;

enum DocumentScanStatus: string
{
    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';

    public function isDownloadable(bool $enforced): bool
    {
        if (! $enforced) {
            return true;
        }

        return $this === self::Clean;
    }
}
