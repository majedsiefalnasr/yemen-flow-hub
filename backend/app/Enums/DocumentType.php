<?php

namespace App\Enums;

enum DocumentType: string
{
    case REQUEST_DOC = 'REQUEST_DOC';
    case SWIFT = 'SWIFT';
    case FX_REQUEST = 'FX_REQUEST';
    case CONFIRMATION_REQUEST = 'CONFIRMATION_REQUEST';
    case CUSTOMS = 'CUSTOMS';

    public function label(): string
    {
        return match ($this) {
            self::REQUEST_DOC => 'مستند الطلب / Request Document',
            self::SWIFT => 'وثيقة السويفت / SWIFT Document',
            self::FX_REQUEST => 'طلب تأكيد المصارفة الخارجية / External FX Confirmation Request',
            self::CONFIRMATION_REQUEST => 'طلب وثيقة التأكيد / Confirmation Request',
            self::CUSTOMS => 'البيان الجمركي / Customs Declaration',
        };
    }
}
