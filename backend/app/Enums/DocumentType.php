<?php

namespace App\Enums;

enum DocumentType: string
{
    case REQUEST_DOC = 'REQUEST_DOC';
    case SWIFT = 'SWIFT';
    case CUSTOMS = 'CUSTOMS';

    public function label(): string
    {
        return match ($this) {
            self::REQUEST_DOC => 'مستند الطلب / Request Document',
            self::SWIFT => 'وثيقة السويفت / SWIFT Document',
            self::CUSTOMS => 'البيان الجمركي / Customs Declaration',
        };
    }
}
