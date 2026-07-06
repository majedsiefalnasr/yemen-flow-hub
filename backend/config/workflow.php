<?php

return [
    'support_claim_ttl_minutes' => env('SUPPORT_CLAIM_TTL_MINUTES', 15),

    // When false (default), uploads are marked clean immediately and scan status is not enforced on download.
    // Set DOCUMENT_SCAN_ENFORCED=true once antivirus infrastructure is available.
    'document_scan_enforced' => env('DOCUMENT_SCAN_ENFORCED', false),
];
