<?php

namespace App\Support;

class InvoiceKey
{
    public static function normalize(string $value): string
    {
        return trim($value);
    }
}
