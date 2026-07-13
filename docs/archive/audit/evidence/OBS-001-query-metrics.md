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

## Laravel Pulse dashboard — installed (was the first open residual)

`composer require laravel/pulse` (v1.7.4). Config published (`config/pulse.php`), migrations published + run — `pulse_aggregates`, `pulse_entries`, `pulse_values` created. Uses the **database** storage driver (default) with the **storage** ingest driver, so it records synchronously on request termination with no separate `pulse:work` worker or supervisor — self-contained for a pre-production deliverable. (Redis ingest + a `pulse:work` worker is the production-scale upgrade; documented below, not wired here.)

**Authorization gate** (`AuthServiceProvider::boot()`): `viewPulse` restricted to system admins (`isSystemAdmin()`), mirroring the existing `viewHorizon` gate — Pulse's default gate only allows the `local` env, which would lock the dashboard out of staging/production. Verified against real dev data:

```
viewPulse(admin@cby.gov.ye  / isSystemAdmin) = true
viewPulse(support1@cby.gov.ye / non-admin)   = false
viewPulse(guest / null)                      = false
```

**Recording proven live** — drove a real request through the HTTP kernel; `pulse_entries` grew by one `slow_request` row keyed `["GET","/up","Closure"]` with the duration in ms (the value Pulse aggregates into per-endpoint p50/p95/p99). The verification probe entries were then deleted so no artificially-thresholded rows are left in dev. The `SlowRequests` recorder's default 1000ms threshold means only genuinely slow endpoints appear on the dashboard under normal traffic — the 200K-row `my-queue`/`list` load-run shapes (DB-001/DB-002) exceed it and will surface there.

**Testing:** `PULSE_ENABLED=false` in `phpunit.xml`. Pulse's query recorders + ingest writes add non-deterministic queries per request, which broke the exact `X-Query-Count` assertion in `QueryMetricsHeadersTest` (header 3 vs. a raw `DB::listen` count of 5 — the 2 extra were Pulse's own bookkeeping writes at termination). Disabling Pulse in the test env restores deterministic query counts suite-wide; Pulse recording is verified against real MySQL (above), not in the SQLite suite. Full `QueryMetricsHeadersTest` + engine allocator/request suites green after the change (44 tests, 185 assertions).

## MySQL server-level slow-query log — enabled + captured on dev (was the second open residual)

Enabled on the `yfh-mysql` container (MySQL 8.4) via `SET GLOBAL`:

```sql
SET GLOBAL slow_query_log = ON;
SET GLOBAL long_query_time = 0.1;              -- 100ms (was 10s)
SET GLOBAL log_queries_not_using_indexes = ON;
-- slow_query_log_file = /var/lib/mysql/41f8d7e7160e-slow.log
```

Captured real entries end-to-end (from the log file) — both catch mechanisms proven:

```
# Query_time: 0.000693  Rows_examined: 57
SELECT COUNT(*) FROM engine_requests WHERE data LIKE "%zzz-no-such-value%";
  -- caught by log_queries_not_using_indexes (full scan), despite being fast

# Query_time: 0.201105  Rows_sent: 1  Rows_examined: 1
SELECT SLEEP(0.2);
  -- caught by long_query_time=0.1 (exceeded the 100ms threshold)
```

**Runtime vs. persistent:** these are `SET GLOBAL`s and reset on container restart. The persistent production form belongs in `my.cnf` / the MySQL config:

```ini
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 0.1
log_queries_not_using_indexes = 1
```

Enabling on the managed production DB is ultimately a DBA task (server config, not app code), but the mechanism is now proven to capture the exact query shapes the audit's hot-path findings are about.

## Remaining note

- `X-Query-Count`/`X-Query-Time-Ms` are exposed to any client when `expose_query_metrics_headers` is true (default outside production). Acceptable for local/staging; production default is off via both the config default and the middleware's hard `production` block.
