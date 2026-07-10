<?php

return [
    'support_claim_ttl_minutes' => env('SUPPORT_CLAIM_TTL_MINUTES', 15),

    // When false (default), uploads are marked clean immediately and scan status is not enforced on download.
    // Set DOCUMENT_SCAN_ENFORCED=true once antivirus infrastructure is available.
    'document_scan_enforced' => env('DOCUMENT_SCAN_ENFORCED', false),

    // DB-001/DB-002: UnionStagePaginator (app/Support/UnionStagePaginator.php)
    // uses one subquery per accessible stage to avoid MySQL's IN+ORDER BY
    // filesort limitation. Above this many accessible stage IDs, it falls
    // back to the original single whereIn(...) query instead of issuing
    // this many subqueries -- correct either way, this just bounds worst-case
    // query fan-out for a broad-access role.
    'list_union_stage_threshold' => env('LIST_UNION_STAGE_THRESHOLD', 10),
];
