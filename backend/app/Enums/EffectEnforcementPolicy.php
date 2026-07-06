<?php

namespace App\Enums;

enum EffectEnforcementPolicy: string
{
    case FAIL = 'FAIL';
    case WARN = 'WARN';
}
