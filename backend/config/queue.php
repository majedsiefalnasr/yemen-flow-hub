<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],
        // Dedicated email queue (Epic 15). after_commit=true so email jobs fire only
        // after the surrounding workflow DB transaction commits — never emailing about
        // a rolled-back transition. Run a dedicated worker:
        //   php artisan queue:work --queue=emails
        // Dead-letter path reuses the existing `database-uuids` `failed` driver below.
        // Story 15.1 only adds this connection; nothing dispatches onto it yet.
        'emails' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => 'emails',
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => true,
        ],
        // QUEUE-003: exports get their own connection so a longer retry_after
        // doesn't have to apply to the shared `redis` connection's other jobs.
        // GenerateReportExport/GenerateAuditLogExport carry $timeout=300
        // (QUEUE-002); retry_after must exceed the longest job timeout on the
        // connection or a still-running export can be picked up twice.
        //   php artisan queue:work --queue=exports
        'exports' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => 'exports',
            'retry_after' => (int) env('REDIS_EXPORTS_QUEUE_RETRY_AFTER', 360),
            'block_for' => null,
            'after_commit' => false,
        ],
    ],
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
