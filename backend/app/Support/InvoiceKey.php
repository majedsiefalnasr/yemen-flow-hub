<?php

namespace App\Support;

class InvoiceKey
{
    public static function normalize(string $value): string
    {
        return (string) preg_replace('/\s+/', ' ', strtoupper(trim($value)));
    }
}
