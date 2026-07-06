<?php

namespace App\Enums;

enum WorkflowTransitionType: string
{
    case FORWARD = 'FORWARD';
    case RETURN = 'RETURN';
    case REJECT = 'REJECT';
    case CLOSE = 'CLOSE';
    case CUSTOM = 'CUSTOM';
}
