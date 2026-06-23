<?php

namespace App\Enums;

/**
 * Allowed sources for a DYNAMIC_SELECT field (DI-5). MERCHANTS and
 * MERCHANT_COMPANIES resolve from the merchant module; REFERENCE_DATA resolves
 * from a reference_table (the field's reference_table_id).
 */
enum DynamicFieldSource: string
{
    case MERCHANTS = 'MERCHANTS';
    case MERCHANT_COMPANIES = 'MERCHANT_COMPANIES';
    case REFERENCE_DATA = 'REFERENCE_DATA';
}
