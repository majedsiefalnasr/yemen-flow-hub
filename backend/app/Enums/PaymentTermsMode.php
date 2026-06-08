<?php

namespace App\Enums;

enum PaymentTermsMode: string
{
    case ADVANCE_PAYMENT = 'ADVANCE_PAYMENT';
    case LETTER_OF_CREDIT = 'LETTER_OF_CREDIT';
    case DOCUMENTARY_COLLECTION = 'DOCUMENTARY_COLLECTION';
    case DEFERRED_PAYMENT = 'DEFERRED_PAYMENT';

    public function label(): string
    {
        return match ($this) {
            self::ADVANCE_PAYMENT => 'دفع مقدم / Advance Payment',
            self::LETTER_OF_CREDIT => 'اعتماد مستندي / Letter of Credit',
            self::DOCUMENTARY_COLLECTION => 'تحصيل مستندي / Documentary Collection',
            self::DEFERRED_PAYMENT => 'دفع مؤجل / Deferred Payment',
        };
    }
}
