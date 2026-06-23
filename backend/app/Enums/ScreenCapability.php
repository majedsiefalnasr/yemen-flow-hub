<?php

namespace App\Enums;

enum ScreenCapability: string
{
    case VIEW = 'VIEW';
    case CREATE = 'CREATE';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
    case EXPORT = 'EXPORT';
    case MANAGE = 'MANAGE';
}
