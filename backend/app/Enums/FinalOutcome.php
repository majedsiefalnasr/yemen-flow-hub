<?php

namespace App\Enums;

use App\Support\EngineRequestStatus;

enum FinalOutcome: string
{
    case COMPLETED = 'COMPLETED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
    case ABANDONED = 'ABANDONED';

    public function toRequestStatus(): string
    {
        return EngineRequestStatus::fromFinalOutcome($this);
    }
}
