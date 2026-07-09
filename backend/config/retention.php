<?php

return [
    // When true, prefer archive/move over physical delete when policy is ambiguous.
    'archive_first' => env('RETENTION_ARCHIVE_FIRST', true),

    // RT-3 — hot DB horizon (CBY confirm before tightening)
    'audit_hot_months' => (int) env('RETENTION_AUDIT_HOT_MONTHS', 12),
    'audit_archive_batch_size' => (int) env('RETENTION_AUDIT_BATCH_SIZE', 500),

    // ARCH-006 — workflow_history hot DB horizon; only applies to rows whose
    // owning engine_request is no longer ACTIVE (see WorkflowHistoryArchiveService).
    'workflow_history_hot_months' => (int) env('RETENTION_WORKFLOW_HISTORY_HOT_MONTHS', 12),
    'workflow_history_archive_batch_size' => (int) env('RETENTION_WORKFLOW_HISTORY_BATCH_SIZE', 500),

    // RT-5 — export file retention (row kept as EXPIRED history)
    'export_file_days' => (int) env('RETENTION_EXPORT_FILE_DAYS', 30),

    // RT-4 — notification retention (state-based)
    'notification_unread_max_days' => (int) env('RETENTION_NOTIFICATION_UNREAD_MAX_DAYS', 90),
    'notification_read_days' => (int) env('RETENTION_NOTIFICATION_READ_DAYS', 365),

    // RT-6 — superseded document physical file lifecycle
    'superseded_document_file_days' => (int) env('RETENTION_SUPERSEDED_DOC_DAYS', 90),
    'orphan_file_grace_hours' => (int) env('RETENTION_ORPHAN_GRACE_HOURS', 24),

    // OM-2 — scheduler staleness thresholds (minutes)
    'scheduler_stale_minutes' => [
        'workflow:expire-engine-claims' => 5,
        'workflow:notify-sla-signals' => 120,
        'notifications:purge-old' => 1500,
        'reports:purge-old-exports' => 1500,
        'documents:purge-orphans' => 1500,
        'documents:archive-superseded' => 1500,
        'audit:archive-old' => 1500,
        'workflow-history:archive-old' => 1500,
    ],
];
