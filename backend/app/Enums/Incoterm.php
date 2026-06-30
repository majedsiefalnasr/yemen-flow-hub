<?php

namespace App\Enums;

enum Incoterm: string
{
    case EXW = 'EXW';
    case FCA = 'FCA';
    case CPT = 'CPT';
    case CIP = 'CIP';
    case DAP = 'DAP';
    case DPU = 'DPU';
    case DDP = 'DDP';
    case FAS = 'FAS';
    case FOB = 'FOB';
    case CFR = 'CFR';
    case CIF = 'CIF';

    public function label(): string
    {
        return match ($this) {
            self::EXW => 'تسليم في المصنع / Ex Works',
            self::FCA => 'تسليم للناقل / Free Carrier',
            self::CPT => 'النقل مدفوع إلى / Carriage Paid To',
            self::CIP => 'النقل والتأمين مدفوعان إلى / Carriage and Insurance Paid To',
            self::DAP => 'تسليم في المكان / Delivered at Place',
            self::DPU => 'تسليم في المكان مع التفريغ / Delivered at Place Unloaded',
            self::DDP => 'تسليم خالص الرسوم / Delivered Duty Paid',
            self::FAS => 'تسليم جانب السفينة / Free Alongside Ship',
            self::FOB => 'تسليم على ظهر السفينة / Free on Board',
            self::CFR => 'التكلفة والشحن / Cost and Freight',
            self::CIF => 'التكلفة والتأمين والشحن / Cost Insurance and Freight',
        };
    }
}
