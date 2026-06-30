<?php

namespace App\Enums;

enum RequestType: string
{
    case GOODS_IMPORT = 'GOODS_IMPORT';
    case RAW_MATERIAL_IMPORT = 'RAW_MATERIAL_IMPORT';
    case EQUIPMENT_IMPORT = 'EQUIPMENT_IMPORT';

    public function label(): string
    {
        return match ($this) {
            self::GOODS_IMPORT => 'استيراد بضائع / Goods Import',
            self::RAW_MATERIAL_IMPORT => 'استيراد مواد خام / Raw Material Import',
            self::EQUIPMENT_IMPORT => 'استيراد معدات / Equipment Import',
        };
    }
}
