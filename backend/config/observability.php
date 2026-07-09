<?php

/**
 * OBS-001: per-request query-count/time observability. The counter always
 * runs (cheap DB::listen accumulation); the response headers are an opt-in
 * surface for staging/load-test verification and are off by default in
 * production so internal query shape is never exposed to clients.
 */
return [
    // Defaults off; explicitly enabled per-environment via .env (never true in
    // production .env). Middleware also hard-blocks the 'production' env below
    // as a second guard against internal query counts leaking to clients.
    'expose_query_metrics_headers' => (bool) env('OBS_EXPOSE_QUERY_METRICS_HEADERS', env('APP_ENV') !== 'production'),

    // OBS-001: query duration (ms) above which a query is written to the
    // slow-query log channel, independent of MySQL's own slow-query log.
    'slow_query_threshold_ms' => (int) env('OBS_SLOW_QUERY_THRESHOLD_MS', 200),
];
