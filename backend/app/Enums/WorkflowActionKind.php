<?php

namespace App\Enums;

enum WorkflowActionKind: string
{
    case DRAFT = 'DRAFT';
    case APPROVE = 'APPROVE';
    case REJECT = 'REJECT';
    case RETURN = 'RETURN';
    case CLOSE = 'CLOSE';
    case INFO = 'INFO';
    case CUSTOM = 'CUSTOM';
}
