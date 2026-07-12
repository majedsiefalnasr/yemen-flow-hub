<?php

namespace App\Enums;

enum StageSemanticRole: string
{
    case INITIAL_ENTRY = 'INITIAL_ENTRY';
    case BANK_REVIEW = 'BANK_REVIEW';
    case SUPPORT_REVIEW = 'SUPPORT_REVIEW';
    case SWIFT = 'SWIFT';
    case EXECUTIVE_REVIEW = 'EXECUTIVE_REVIEW';
    case FINANCE_RESERVE = 'FINANCE_RESERVE';
    case FX_CONFIRMATION = 'FX_CONFIRMATION';
    case FINAL = 'FINAL';
}
