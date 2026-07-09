# 07 — Roadmap, Before/After, Verification Checklist

Block 6 deliverable. Synthesizes the 29 findings into a phased, tiered plan. Tiers: **Pre-production** (before go-live), **Threshold-gated** (at a stated measurable threshold), **Optional**. Every fix is separate approved work with its own plan/migration/rollback — this roadmap sequences them; it does not implement them.

## Quick wins (low risk, high value, mostly independent)

| Fix | Finding | Why quick |
| --- | --- | --- |
| Add `wh_req_tostage_created` index | DB-001 | One migration; 2.6 s → 0.7 s on my-queue (measured). No code change. |
| Add `er_bank_created` composite | DB-002 | One migration (pairs with the range-date one-liner). Covering scan. |
| `reports/summary`: 7 counts → 1 grouped pass | API-005 | Single-method rewrite; ~0.96 s → ~0.33 s (measured). |
| Default authenticated throttle | ARCH-003 | Middleware config; caps every other perf risk + SEC-003. |
| `whereDate` → range bounds | ARCH-004, API-007 | Mechanical; unlocks DB-002/DB-003 index use. |
| Scan job `failed()` + timeout (fail-closed) | QUEUE-001 | Small job-class change; closes a security gap. |
| Slow-query log + per-request query count | OBS-001 (partial) | Config + a `DB::listen` counter; makes everything else measurable. |

## High-priority improvements (failure/timeout/overload risk at scale)

1. **ARCH-001** — SQL-ify stage-permission resolution (kills the per-request whole-table load feeding API-001/002 and every list/queue). Highest leverage.
2. **ARCH-002 + DB-001** — my-queue SLA ordering (index now; projection-column to remove the 96k-row sort next).
3. **API-001** — batch `fx_panel`/`can_execute` (removes ~2 queries/row on the hottest lists).
4. **API-002** — collapse the 6-pass stats query.
5. **OBS-001** — observability (can't safely operate or validate anything without it).
6. **API-003** — reference allocator (overflow correctness + creation contention).

## Architecture improvement plan

The architecture is **fundamentally sound** — no rewrite is warranted. Service-oriented, single data-access idiom (no repository sprawl), mandatory `EngineTransitionService`, query-level scoping, append-only audit, idempotent transitions. Structural recommendations are targeted, not sweeping:

- **Projection over correlated subquery** (ARCH-002): extend the existing `RequestProjectionSync` pattern to maintain `stage_entered_at` on `engine_requests`, so SLA ordering/filtering hits an indexed column. This is a deepening of a pattern already in the codebase, not a new abstraction.
- **Push authorization set-resolution into SQL** (ARCH-001): the one place the app hydrates a whole table to filter in PHP.
- **Shorten the transition critical section** (ARCH-007): keep atomicity, move PDF CPU out of the lock span.
- **Read-through cache for aggregates** (CACHE-001): the only genuinely missing caching layer, scoped by org+bank.
- Everything else is index/query/config/job tuning, not architecture.

## Phased roadmap (prompt Phases A–F)

### Phase A — Measurement & safety *(Pre-production)*
- **Tasks:** OBS-001 (slow-query log, per-request query count, Laravel Pulse); establish the load-test harness (Block 6 seeder recommendation below).
- **Dependencies:** none. **Do this first** — it validates every later phase.
- **Risk:** low (additive). **Validation:** Pulse shows per-endpoint latency + query counts; slow-query log populated in staging.

### Phase B — Critical fixes *(Pre-production)*
- **Tasks:** QUEUE-001 (scan fail-closed); ARCH-003 (throttle, also fixes SEC-003); reference allocator API-003.
- **Dependencies:** A (to measure). **Risk:** low–medium. **Validation:** scan-failure test leaves doc fail-closed; throttle returns 429 past limit; concurrent-create test (load scenario 5) yields unique refs.

### Phase C — Database & API optimization *(Pre-production for the indexed hot paths)*
- **Tasks:** DB-001, DB-002 (+ range-date code ARCH-004/API-007), API-005 grouped summary, API-001 batch serialization, API-002 stats collapse, ARCH-001 SQL-ify permissions.
- **Dependencies:** A (before/after proof). ARCH-001 needs parity tests (gate condition). **Risk:** medium (query rewrites — parity-test the permission and SLA logic). **Validation:** load scenario 1; p95 ≤ 300 ms, queries/req ≤ 15; before/after EXPLAIN matches Block 3 evidence on production hardware.

### Phase D — Caching & queues *(Threshold-gated, some Pre-production)*
- **Tasks:** CACHE-001 (org+bank-scoped aggregate cache — Pre-production if dashboards are launch-critical); QUEUE-002/003 (explicit job config, queue separation, Horizon/OBS-002).
- **Dependencies:** C (don't cache a still-slow query). **Risk:** medium (cache-key correctness = security). **Validation:** load scenario 2; cache hit-ratio measured; **zero cross-bank leakage** (hard gate); Redis-outage scenario 8 returns no 500s.

### Phase E — Scalability improvements *(Threshold-gated / Optional)*
- **Tasks:** ARCH-002 projection-column sort fix; ARCH-006 archival/retention for `audit_logs`/`workflow_history`; SEC-002 `audit_logs.bank_id` + DB-003; ARCH-007 lock-span reduction; FE-001/003 (cancellation, reference cache).
- **Dependencies:** A, C. **Infrastructure trigger rule:** partitioning/read-replicas only if archival proves insufficient — not scheduled here. **Risk:** medium (data migrations — backfill + rollback). **Validation:** scenarios 3/6/9; archival keeps rows queryable; audit backfill exposes no cross-bank rows.

### Phase F — Load testing & validation *(gates release)*
- **Tasks:** execute `08-load-test-plan.md` on production-representative hardware, before/after each of B–E.
- **Dependencies:** A–E. **Risk:** low (measurement). **Validation:** all success criteria in the load-test plan met; no critical failure thresholds tripped.

## Before/after examples (from captured evidence)

### 1. my-queue SLA ordering (ARCH-002 / DB-001)
**Before** — correlated subquery in ORDER BY, 96,186-row sort before LIMIT, per-row `workflow_history` lookup:
```sql
ORDER BY ... (UNIX_TIMESTAMP((SELECT MAX(created_at) FROM workflow_history
  WHERE request_id = engine_requests.id AND to_stage_id = engine_requests.current_stage_id))
  + current_stage.sla_duration_minutes*60) ASC
-- ~2.6 s; subquery cost 4.31/row (evidence/explain/ARCH-002-my-queue-sla.txt)
```
**After** — covering index `workflow_history(request_id, to_stage_id, created_at)`:
```sql
-- subquery → covering index lookup, cost 1.14; ~0.7 s (~3.7x)
-- next step: maintain stage_entered_at projection column to also remove the 96k-row sort
```

### 2. Engine list (ARCH-004 / DB-002)
**Before** — `whereDate` + leading-wildcard + deep offset: 492,814-row filter+sort, ~0.3 s.
**After** — range dates + `engine_requests(bank_id, created_at, id)`: covering range scan, ~0.08 s (~4x). (`evidence/explain/ARCH-004-*.txt`)

### 3. reports/summary (API-005)
**Before** — 7 separate `COUNT`/`SUM` scans, ~0.96 s.
**After** — one `GROUP BY status` + conditional `SUM`, ~0.33 s.

### 4. List serialization (API-001)
**Before** — `fx_panel` → `panelCapabilities()` per row = ~2 uncached queries/row (~200/page).
**After** — batch-resolve once per page (mirrors existing `StageFieldOutputFilter` singleton cache) → O(1) queries for the panel.

## Verification checklist (per fix — how to confirm it worked)

- [ ] **DB-001:** EXPLAIN shows covering index lookup on the SLA subquery; my-queue p95 ≤ 300 ms at 1M rows. *(Index + code done — DB-001 index applied and ARCH-002 `stage_entered_at` projection column swapped in; `SlaProjectionParityTest`, `evidence/explain/ARCH-002-stage-entered-at.txt`. **Load-run harness now exists and was executed** (`perf:load-scenario`, real MySQL dev DB — `evidence/LOAD-RUN-harness-and-results.md`): p95 held at 1K rows (42ms) and 50K rows (181ms) but **failed at 200K rows (574ms, worst-case single hot stage)**. Query count stayed constant (16) at every scale — the remaining cost is the `ORDER BY`'s computed-expression sort, not query count. 1M rows was not reached this pass (see evidence doc); gate stays open, now with a real failing data point instead of "pending measurement.")*
- [ ] **DB-002 + ARCH-004:** EXPLAIN shows covering range scan (no `cast(... as date)` in the plan); list p95 ≤ 300 ms. *(Code + index done — `er_bank_created` index and half-open date range in `EngineRequestListQuery` (no `whereDate`); `EngineSearchTest` covers the range boundaries. **Load-run harness executed**: p95 held at 1K (40ms) and 50K (112ms) but **failed at 200K rows (367ms)**. Same harness/evidence as DB-001. Gate stays open.)*
- [x] **API-001:** query count per list page is constant regardless of page size (assert via OBS-001 counter). *(Done — FX request scope resolved in memory, no per-row `forUser()` query; `FxConfirmationRequestScopeTest` pins parity. **Load-run harness confirmed the constant-across-page-sizes claim directly**: my-queue held at 16 queries for `per_page=10/50/200`, and at 1K/50K/200K row counts — `evidence/LOAD-RUN-harness-and-results.md`.)*
- [x] **API-002 / API-005:** stats/summary issue one grouped query, not N passes (query-count assertion). *(Done — stats collapsed to fewer grouped passes and `reports/summary` to one grouped query; `EngineRequestStatsTest`/report tests green. **Load-run harness confirmed directly**: `reports/summary` issued a constant 6 total queries (auth + permission + the one grouped aggregate + cache overhead) at 1K/50K/200K rows, cold-cache each time — `evidence/LOAD-RUN-harness-and-results.md`.)*
- [x] **ARCH-001:** permission-resolution issues a bounded SQL query, not a whole-table hydrate; **parity test** vs the old PHP evaluator passes for every identity shape. *(Done — `AccessibleStageIdsParityTest`; plan in `evidence/explain/ARCH-001-accessible-stage-ids.txt`.)*
- [x] **ARCH-003:** authenticated endpoints return 429 past the limit *(Done — `throttle:api-default` on the v1/profile/admin groups; `ApiDefaultThrottleTest` proves the cap and per-user bucket)*. Unauthenticated auth endpoints keep their existing per-route throttles (login 5/1, OTP 10/1, etc.).
- [x] **ARCH-006:** `audit_logs`/`workflow_history` have a working archival/retention path; archived rows preserve `bank_id`; an in-flight request's history is never archived. *(Done — `audit_logs` archival already existed pre-remediation (`AuditLogArchiveService`, scheduled); this fix closed two real gaps: `audit_log_archives.bank_id` (was silently dropped on archive) and net-new `workflow_history` archival (`WorkflowHistoryArchiveService`/`workflow-history:archive-old`, gated on the owning request being non-`ACTIVE` so `withStageEntry()`/`stageDuration()` never see corrupted live data). `ArchiveOldAuditLogsTest::test_archived_row_preserves_bank_id`, `ArchiveOldWorkflowHistoryTest` (5 tests incl. `test_never_archives_history_for_an_active_request`). `evidence/ARCH-006-audit-workflow-history-archival.md`.)*
- [ ] **API-003:** concurrent-create test yields unique references, zero `REFERENCE_ALLOCATION_FAILED`; behaves correctly past a simulated 7-digit sequence. *(Overflow half done — `MAX` derivation switched from lexicographic string to numeric-cast; `EngineRequestReferenceAllocatorTest` reproduces the pre-fix `REFERENCE_ALLOCATION_FAILED` past the 6→7 digit boundary and confirms it resolves correctly post-fix. Sequential-retry uniqueness also covered. True concurrent-create contention (parallel workers, not sequential) has not been load-tested — stays open pending that specific scenario.)*
- [x] **CACHE-001:** two banks' dashboards never share a cache entry (cross-bank leakage test); Redis-down → live compute, no 500. *(Done — `ReportAggregateCache` wired into all 10 `ReportController` pure-aggregate endpoints; `ReportAggregateCacheTest::test_two_banks_never_share_a_cache_entry` and `test_falls_through_to_live_compute_when_the_cache_store_is_unavailable` prove both halves. `evidence/CACHE-001-report-aggregate-cache.md`. `DashboardStatsService` (`GET /api/dashboard/stats`) was not in scope for this fix and remains uncached.)*
- [x] **QUEUE-001:** forced scan failure marks the document fail-closed (not clean/available); alert emitted. *(Done — `ScanEngineRequestDocument` gets `$tries`/`$timeout`/`backoff`/`failed()`; `ScanEngineRequestDocumentTest` proves Failed-not-Clean, resolved-doc guard, and the operational alert.)*
- [ ] **OBS-001:** Pulse shows per-endpoint p50/p95/p99 + query counts; slow-query log captures the pre-fix hot queries. *(Partially done — request-scoped query-count/time counter shipped as `X-Query-Count`/`X-Query-Time-Ms` response headers (`QueryMetrics` + `AttachQueryMetricsHeaders`, off by default in production) plus an app-level `slow_query` log line per over-threshold query; `QueryMetricsHeadersTest`, `evidence/OBS-001-query-metrics.md`. Not done: Laravel Pulse dashboard (per-endpoint p50/p95/p99, persistent history) and MySQL server-level slow-query log (`slow_query_log`/`log_queries_not_using_indexes` — a DB-server config change, not app code). Gate stays open until those two remain done or are explicitly descoped.)*
- [x] **SEC-002:** after `bank_id` backfill, a bank admin sees only their bank's audit rows; a cross-bank id returns 403. *(Done — `AuditLogControllerTest::test_show_returns_own_bank_log_but_denies_cross_bank_log` proves both halves; backfill verified against the real dev DB, 258 rows.)*
- [x] **ARCH-007:** PDF CPU/IO time does not run inside the transition's row lock; a committed FX confirmation still always gets its document; an unresolvable semantic mapping still rolls back the transition. *(Done — `CustomsFxPdfEffect` split at the hook boundary: cheap, must-abort-capable `snapshot()` stays inside `EngineTransitionService::execute()`'s lock; the DomPDF render/disk-write/declaration-creation moved to a `DB::afterCommit()` closure, confirmed to run synchronously within the same request in both production and `RefreshDatabase`-wrapped tests. `EngineDomainHooksTest::test_customs_pdf_render_runs_outside_the_transition_lock` (red on unmodified code, green after) and `test_unresolvable_semantic_mapping_still_rolls_back_the_transition`. `evidence/ARCH-007-fx-pdf-outside-lock.md`. No load test quantifies the lock-hold-time reduction under concurrency — the qualitative gate (zero PDF time inside the lock) is met; throughput was not benchmarked.)*
- [ ] **Every optimized query:** re-verify it still applies `forUser`/accessible-stage scoping (Block 5 gate conditions).

## Seeder / EXPLAIN harness — keep or discard recommendation

**Recommendation: keep, promoted to a documented performance-testing asset — but only after hardening.** The harness produced real design-target evidence and will be needed again for Phase F. However, as-built it is scratchpad-quality (tinker `--execute`, hardcoded offsets, the PHP-memory fallback to set-based SQL).

Convert it into `backend/tests/Performance/` (or `tools/performance/`) **only if** it is made: (a) deterministic (already seeds with `mt_srand(42)`), (b) idempotent + environment-guarded (already refuses any DB ≠ `yfh_audit` — extend to refuse `production`), (c) documented (row targets, distributions, run command), (d) isolated from normal seeders (never in `DatabaseSeeder`), (e) memory-safe (adopt the set-based SQL multiply as the primary path, not a fallback). A default-off CI performance profile can invoke it for Phase F regression runs. **Until hardened, keep it local-only** (current state) — do not commit the scratchpad version.

## Coverage note

All 11 prompt phases map to a document: Phase 1→`01`, 2+5→`04`, 3+4→`03`, 6+7+8→`05`, 9+10→`06`, 11→`08`, deliverables→`02`/`07`/README. All 29 findings carry the full lifecycle schema. See README executive summary.
