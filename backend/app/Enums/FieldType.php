<?php

namespace App\Enums;

enum FieldType: string
{
    case TEXT = 'TEXT';
    case NUMBER = 'NUMBER';
    case DATE = 'DATE';
    case SELECT = 'SELECT';
    case DYNAMIC_SELECT = 'DYNAMIC_SELECT';
    case TEXTAREA = 'TEXTAREA';
    case FILE = 'FILE';
    case CURRENCY = 'CURRENCY';
    case CHECKBOX = 'CHECKBOX';
}
