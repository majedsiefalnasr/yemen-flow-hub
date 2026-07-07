<?php

/**
 * Frozen Import Financing workflow v1 CI contract.
 *
 * External reference: dynamic-workflow-engine/src/lib/workflow-engine/seed.ts
 * YFH data-fix: split single CLOSED into CLOSED_COMPLETED + CLOSED_REJECTED;
 * SUPPORT requires_claim; FX_CONFIRM bank VIEW permission.
 *
 * Parity tests assert DB state against this file — not against seed.ts at runtime.
 */
return [
    'definition_code' => 'IMPORT_FINANCING',
    'definition_name' => 'تمويل الواردات',
    'version_number' => 1,

    'terminal_stage_codes' => [
        'completed' => 'CLOSED_COMPLETED',
        'rejected' => 'CLOSED_REJECTED',
    ],

    'stages' => [
        ['code' => 'CREATE', 'sort_order' => 1, 'is_initial' => true, 'is_final' => false, 'final_outcome' => null, 'requires_claim' => false],
        ['code' => 'INTERNAL', 'sort_order' => 2, 'is_initial' => false, 'is_final' => false, 'final_outcome' => null, 'requires_claim' => false],
        ['code' => 'SUPPORT', 'sort_order' => 3, 'is_initial' => false, 'is_final' => false, 'final_outcome' => null, 'requires_claim' => true],
        ['code' => 'EXEC', 'sort_order' => 4, 'is_initial' => false, 'is_final' => false, 'final_outcome' => null, 'requires_claim' => false],
        ['code' => 'FX', 'sort_order' => 5, 'is_initial' => false, 'is_final' => false, 'final_outcome' => null, 'requires_claim' => false],
        ['code' => 'FX_CONFIRM', 'sort_order' => 6, 'is_initial' => false, 'is_final' => false, 'final_outcome' => null, 'requires_claim' => false],
        ['code' => 'FINAL', 'sort_order' => 7, 'is_initial' => false, 'is_final' => false, 'final_outcome' => null, 'requires_claim' => false],
        ['code' => 'CLOSED_COMPLETED', 'sort_order' => 98, 'is_initial' => false, 'is_final' => true, 'final_outcome' => 'COMPLETED', 'requires_claim' => false],
        ['code' => 'CLOSED_REJECTED', 'sort_order' => 99, 'is_initial' => false, 'is_final' => true, 'final_outcome' => 'REJECTED', 'requires_claim' => false],
    ],

    'transitions' => [
        ['from' => 'CREATE', 'to' => 'INTERNAL', 'action' => 'APPROVE'],
        ['from' => 'INTERNAL', 'to' => 'SUPPORT', 'action' => 'APPROVE'],
        ['from' => 'INTERNAL', 'to' => 'CREATE', 'action' => 'REJECT'],
        ['from' => 'SUPPORT', 'to' => 'EXEC', 'action' => 'APPROVE'],
        ['from' => 'SUPPORT', 'to' => 'SUPPORT', 'action' => 'ADD_NOTES'],
        ['from' => 'EXEC', 'to' => 'FX', 'action' => 'APPROVE'],
        ['from' => 'EXEC', 'to' => 'CLOSED_REJECTED', 'action' => 'REJECT_FINAL'],
        ['from' => 'FX', 'to' => 'FX_CONFIRM', 'action' => 'APPROVE'],
        ['from' => 'FX_CONFIRM', 'to' => 'FINAL', 'action' => 'APPROVE'],
        ['from' => 'FX_CONFIRM', 'to' => 'FX', 'action' => 'REJECT'],
        ['from' => 'FINAL', 'to' => 'CLOSED_COMPLETED', 'action' => 'FINAL_APPROVE'],
        ['from' => 'FINAL', 'to' => 'FX_CONFIRM', 'action' => 'REJECT'],
    ],

    'field_groups' => [
        ['key' => 'basic', 'label' => 'المعلومات الأساسية', 'sort_order' => 1],
        ['key' => 'invoice', 'label' => 'بيانات الفاتورة', 'sort_order' => 2],
        ['key' => 'shipping', 'label' => 'بيانات الشحن', 'sort_order' => 3],
        ['key' => 'docs', 'label' => 'الوثائق المطلوبة', 'sort_order' => 4],
    ],

    'field_keys' => [
        'taxNumber', 'importerName', 'linkedCompany', 'taxCardExpiry', 'commercialRegistration', 'commercialRegistrationExpiry', 'owners',
        'requestType', 'coverageType', 'foreignCurrencySource', 'paymentTerms', 'requestCurrency', 'request_percentage',
        'invoiceType', 'amount', 'currency', 'invoice_number', 'invoiceDate', 'quantity', 'unit', 'invoiceTotal',
        'importType', 'supplierName', 'supplierLocation', 'originCountry',
        'shippingDate', 'arrivalDate', 'shippingPort', 'arrivalPort', 'deliveryTerms', 'finalDestination',
        'docYemeniRialSharia', 'docSaudiRialSharia', 'docUsdSharia', 'docTaxAndCr', 'docCommercialInvoice', 'docLicenses', 'docExtra',
    ],

    'required_on_create' => [
        'taxNumber', 'importerName', 'linkedCompany', 'taxCardExpiry', 'commercialRegistration', 'commercialRegistrationExpiry',
        'requestType', 'coverageType', 'foreignCurrencySource', 'paymentTerms', 'requestCurrency', 'request_percentage',
        'invoiceType', 'amount', 'currency', 'invoice_number', 'invoiceDate', 'quantity', 'unit', 'invoiceTotal',
        'importType', 'supplierName', 'supplierLocation', 'originCountry',
        'shippingDate', 'arrivalDate', 'shippingPort', 'arrivalPort', 'deliveryTerms', 'finalDestination',
        'docYemeniRialSharia', 'docSaudiRialSharia', 'docUsdSharia', 'docTaxAndCr', 'docCommercialInvoice',
    ],

    'stage_permissions' => [
        ['stage' => 'CREATE', 'org' => 'commercial_banks', 'team' => 'entry', 'role' => null, 'access' => 'EXECUTE'],
        ['stage' => 'INTERNAL', 'org' => 'commercial_banks', 'team' => 'internal_review', 'role' => null, 'access' => 'EXECUTE'],
        ['stage' => 'SUPPORT', 'org' => 'national_committee', 'team' => 'support', 'role' => null, 'access' => 'EXECUTE'],
        ['stage' => 'EXEC', 'org' => 'national_committee', 'team' => 'executive', 'role' => 'committee_manager', 'access' => 'EXECUTE'],
        ['stage' => 'FX', 'org' => 'commercial_banks', 'team' => 'fx_ops', 'role' => null, 'access' => 'EXECUTE'],
        ['stage' => 'FX_CONFIRM', 'org' => 'national_committee', 'team' => 'fx_confirmation', 'role' => null, 'access' => 'EXECUTE'],
        ['stage' => 'FX_CONFIRM', 'org' => 'commercial_banks', 'team' => null, 'role' => null, 'access' => 'VIEW'],
        ['stage' => 'FINAL', 'org' => 'national_committee', 'team' => 'executive', 'role' => 'committee_manager', 'access' => 'EXECUTE'],
        ['stage' => 'CLOSED_COMPLETED', 'org' => 'national_committee', 'team' => 'executive', 'role' => 'committee_manager', 'access' => 'VIEW'],
        ['stage' => 'CLOSED_REJECTED', 'org' => 'national_committee', 'team' => 'executive', 'role' => 'committee_manager', 'access' => 'VIEW'],
    ],

    'yfh_deltas' => [
        'split_terminal_stages' => ['CLOSED_COMPLETED', 'CLOSED_REJECTED'],
        'support_requires_claim' => true,
        'fx_confirm_bank_view' => true,
        'field_key_mapping' => [
            'financeAmount' => 'amount',
            'invoiceNumber' => 'invoice_number',
            'requestPercentage' => 'request_percentage',
        ],
    ],
];
