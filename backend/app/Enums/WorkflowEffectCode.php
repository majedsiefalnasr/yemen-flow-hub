<?php

namespace App\Enums;

enum WorkflowEffectCode: string
{
    case FINANCING_RESERVE = 'financing.reserve';
    case FX_CONFIRMATION_PDF = 'fx.confirmation_pdf';
}
