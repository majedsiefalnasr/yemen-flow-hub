<?php

namespace App\Enums;

enum InvoiceType: string
{
    case PROFORMA = 'PROFORMA';
    case COMMERCIAL = 'COMMERCIAL';
    case FINAL = 'FINAL';

    public function label(): string
    {
        return match ($this) {
            self::PROFORMA => 'فاتورة مبدئية / Proforma Invoice',
            self::COMMERCIAL => 'فاتورة تجارية / Commercial Invoice',
            self::FINAL => 'فاتورة نهائية / Final Invoice',
        };
    }
}
