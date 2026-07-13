# Load-run harness and best-effort local results

## What was built

`backend/app/Console/Commands/PerfLoadScenarioCommand.php` — `php artisan perf:load-scenario --rows=N`. No load-test harness existed before this (the roadmap's Phase A named it as a dependency; `EngineRequestBulkSeeder` is a 250-row demo/UI seeder that goes through the full workflow engine per row and is too slow to scale to load-test volumes).

The harness:

1. Refuses to run unless the active DB connection is `mysql` — the gates being measured are MySQL query-plan/index gates; SQLite (the default test-suite connection) does not represent them, as ARCH-002/API-006 already demonstrated in this same remediation pass.
2. Builds a small, fully self-contained fixture (bank, workflow version, two stages, one EXECUTE stage-permission row, one `bank_admin` user) tagged so it can be identified and removed.
3. Bulk-inserts N `engine_requests` rows directly via chunked `DB::table()->insert()` (2000/chunk, no Eloquent events, no workflow engine) — all in the same bank, all on the same hot stage, spread across a 400-day `stage_entered_at` window. Every row's `reference` carries a fixed `PERF-LOAD-` prefix.
4. Dispatches real HTTP requests through the actual Laravel HTTP kernel (`$kernel->handle()`) — not a mocked call — authenticated as the fixture user, and reads `App\Support\QueryMetrics` (the OBS-001 counter) directly for an exact per-request query count, plus wall-clock timing.
5. Cleans up all seeded rows and fixture records in a `finally` block, and again defensively at the start of every run (a genuine out-of-memory fatal error was hit during development — PHP cannot run `finally` after an unrecoverable OOM, so a `--cleanup-only` flag and an unconditional pre-run cleanup were added so a crashed run never leaves the shared dev DB dirty).

Verified reversibility directly: after every run in this session (including the one that crashed), `SELECT COUNT(*) FROM engine_requests WHERE reference LIKE 'PERF-LOAD-%'` and `SELECT COUNT(*) FROM banks WHERE code='PERFLOAD'` both returned 0 before moving on.

## Runs performed (real dev MySQL, `yfh-mysql` container, this session)

| Rows | my-queue p95 (target ≤300ms) | list p95 (target ≤300ms) | API-001 (query count constant across page sizes) | API-002/005 (reports/summary: one grouped query) |
| --- | --- | --- | --- | --- |
| 1,000 | 42.19 ms — **PASS** | 39.7 ms — **PASS** | 16/16/16 — **PASS** | 6 queries, cold-cache — **PASS** |
| 50,000 | 180.54 ms — **PASS** | 111.95 ms — **PASS** | 16/16/16 — **PASS** | 6 queries, cold-cache — **PASS** |
| 200,000 | 574.05 ms — **FAIL** | 367.48 ms — **FAIL** | 16/16/16 — **PASS** | 6 queries, cold-cache — **PASS** |

Query count was constant (16 for both my-queue and the list endpoint, at every row count and every page size tested) in every run — API-001's gate ("query count per list page is constant regardless of page size") and API-002/API-005's gate ("one grouped query, not N passes" — confirmed as 6 total queries including auth/permission lookups, not 7 separate aggregate scans) both hold at every scale tested, not just at small volumes.

## Honest result: the two p95 gates did not hold at 200K rows

This is not the 1M-row target the original findings named — a genuine full 1M-row run was not completed this session (see below). But at 200,000 rows, in the worst-case shape (every seeded row on the *same* hot stage, since `my-queue`'s SLA-priority ordering scope has to sort within a stage), both endpoints exceeded 300ms:

- **my-queue**: 574ms p95 (up from 43ms at 1K, 181ms at 50K — roughly linear-to-slightly-superlinear scaling with row count within the hot stage).
- **list**: 367ms p95 (up from 40ms at 1K, 112ms at 50K).

Both endpoints kept a **constant 16-query count** across all three scales — the query-count fixes (API-001/ARCH-001) hold. The remaining cost is query *execution time*, not query *count* — consistent with `EngineRequest::scopeOrderBySlaPriority()`'s `ORDER BY` sorting on a computed SQL expression (`CASE WHEN ... deadline calc ... stage_entered_at epoch calc`), which cannot use a plain B-tree index for the sort itself even though the DB-001 `wh_req_tostage_created` index and the WHERE-clause indexes (`engine_requests_status_current_stage_id_index`, `er_stage_entered`) do correctly narrow the row set before the sort.

## Why 1M rows was not reached

- A genuine out-of-memory crash occurred attempting a large bulk insert with the CLI default 128M memory limit — fixed (`ini_set('memory_limit', '1024M')` inside the command, disabled query log) after the fact, but this consumed the investigation budget for this pass.
- 200K rows already demonstrated the gate failing with a clear, consistent trend; running a full 1M-row insert (estimated ~5 minutes of insert time alone, extrapolating from the 200K run's ~54s total) against a shared dev database, on top of the time already spent building and debugging the harness, was judged not to add proportional value to what's already a clear, reproducible finding — the gate is provably not met at a smaller scale, so it will not be met at 1M rows either.
- Per this task's explicit instruction ("best-effort local run, report honestly rather than skip entirely"), this is reported as an incomplete run at the originally-named scale, not as a passed or silently-skipped gate.

## Roadmap gate updates

- **DB-001** (`my-queue p95 ≤ 300ms at 1M rows`): stays **open**. Not just "measurement pending" anymore — the harness now exists and a real (if smaller-scale) run shows the gate failing at 200K rows. The index/projection-column work already shipped is necessary but evidently not sufficient at this row count in the single-hot-stage worst case.
- **DB-002 + ARCH-004** (`list p95 ≤ 300ms`): same status — harness exists, 200K-row run shows the gate failing.
- **API-001** (`query count constant across page sizes`): **now closed**. Directly measured and confirmed constant (16 queries) at 1K/50K/200K rows and across per_page=10/50/200.
- **API-002 / API-005** (`stats/summary: one grouped query`): **now closed**. Directly measured: 6 total queries (auth + permission + the one grouped aggregate query + cache-layer overhead), constant across all three row counts, confirming no N-pass regression at scale.

## Recommendation for whoever picks this up next

1. Re-run `php artisan perf:load-scenario --rows=1000000` directly (the harness is built, tested, and idempotent-safe against crashes) to get the true 1M-row number — expect it to still fail p95, likely worse than 574ms, given the trend.
2. Investigate whether `scopeOrderBySlaPriority()`'s computed-expression `ORDER BY` can be made index-assisted — e.g. a generated/stored column for the SLA deadline epoch that can be indexed directly, similar to how `stage_entered_at` itself was already promoted from a correlated subquery to a maintained column in ARCH-002. This is the most likely next fix, not investigated further here since it's a new implementation task, not part of this load-run finding.
3. The harness itself (`perf:load-scenario`) is reusable for verifying that follow-up fix — re-run at the same row counts before/after to get a real before/after comparison.
