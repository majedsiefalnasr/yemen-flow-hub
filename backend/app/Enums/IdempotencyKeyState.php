<?php

namespace App\Enums;

enum IdempotencyKeyState: string
{
    case Processing = 'PROCESSING';
    case Completed = 'COMPLETED';
}
