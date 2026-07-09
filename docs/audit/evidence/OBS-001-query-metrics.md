# OBS-001 — Query-count/slow-query observability

## What was built

- `backend/config/observability.php` — `expose_query_metrics_headers` (default: on outside `production`), `slow_query_threshold_ms` (default 200ms).
- `backend/app/Support/QueryMetrics.php` — request-scoped singleton counter (`count`, `totalTimeMs`, `reset`), fed by a `DB::listen` closure registered in `AppServiceProvider::boot()`. Logs a `slow_query` warning immediately for any query at/above the threshold, independent of the running totals.
- `backend/app/Http/Middleware/AttachQueryMetricsHeaders.php` — resets the counter at the start of every request (needed because the container/singleton persists across requests in the test kernel, queue workers, and Octane-style servers, not just true per-process CLI/FPM), then appends `X-Query-Count` / `X-Query-Time-Ms` response headers. Hard-blocked when `app.env === 'production'` regardless of config, as a second guard beyond the config default.
- Registered `prepend` in the `api` middleware group (`bootstrap/app.php`), ahead of `EnsureFrontendRequestsAreStateful`, so the reset happens before Sanctum/auth-resolution queries run — the header reflects the whole request, not just the controller.

## Why headers, not just logging

The roadmap's OBS-001 verification checklist requires the API-001/API-002/API-005 query-count gates to be "assert[able] via OBS-001 counter." A response header lets both automated tests (`assertHeader`, exact-count assertions) and a manual/load-test harness read the real per-request query count without attaching a profiler or parsing logs.

## Test evidence

`backend/tests/Feature/Observability/QueryMetricsHeadersTest.php` — written first (red), then implementation made it pass (green):

```
Query Metrics Headers (Tests\Feature\Observability\QueryMetricsHeaders)
 ✔ Api response carries query count and time headers
 ✔ Query count header reflects actual query volume
 ✔ Headers are absent when disabled
 ✔ Headers are disabled by default in production

Tests: 4, Assertions: 10
```

The "reflects actual query volume" test independently registers its own `DB::listen` counter in the test and asserts it matches the header value exactly (`assertSame`) — proves the header isn't a static/fake number.

## Regression check

`tests/Feature/Api/ApiDefaultThrottleTest.php` (2 tests) and `tests/Feature/Engine/{EngineRequestTest,EngineRequestStatsTest,EngineRequestCanExecuteTest}.php` (44 tests) — all green after adding the prepended middleware. No behavior change to existing endpoints; only new response headers in non-production.

`Constant PDO::MYSQL_ATTR_SSL_CA is deprecated` warnings on every test are a pre-existing baseline (present on `main` before this change, confirmed by running `ApiDefaultThrottleTest` unmodified) — not introduced by this work.

## Residual / what this does NOT do

- MySQL's own slow-query log (`slow_query_log`, `long_query_time`) is a server-config concern, not app code — not enabled here. The app-level `slow_query` log line is a substitute that works regardless of DB-server access, per finding OBS-001's recommendation ("slow-query log + per-request query count").
- No Laravel Pulse dashboard installed (finding recommends it as a stretch option: "adopt Laravel Pulse"). The header-based counter satisfies the "assertable via OBS-001 counter" requirement the roadmap gates depend on; Pulse would add a persistent dashboard on top but is not required to unblock the query-count gates. Left as a follow-up if a persistent ops dashboard is wanted later.
- `X-Query-Count`/`X-Query-Time-Ms` are exposed to any client when `expose_query_metrics_headers` is true (default outside production). Acceptable for local/staging; production default is off via both the config default and the middleware's hard `production` block.
