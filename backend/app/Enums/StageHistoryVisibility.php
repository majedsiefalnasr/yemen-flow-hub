<?php

namespace App\Enums;

enum StageHistoryVisibility
{
    case FULL;
    case SANITIZED;
    case HIDDEN;
}
