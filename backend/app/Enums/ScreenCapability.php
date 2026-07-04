<?php

namespace App\Enums;

enum ScreenCapability: string
{
    case VIEW = 'VIEW';
    case MANAGE = 'MANAGE';
    case EXPORT = 'EXPORT';
}
