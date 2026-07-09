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

- [ ] **DB-001:** EXPLAIN shows covering index lookup on the SLA subquery; my-queue p95 ≤ 300 ms at 1M rows.
- [ ] **DB-002 + ARCH-004:** EXPLAIN shows covering range scan (no `cast(... as date)` in the plan); list p95 ≤ 300 ms.
- [ ] **API-001:** query count per list page is constant regardless of page size (assert via OBS-001 counter).
- [ ] **API-002 / API-005:** stats/summary issue one grouped query, not N passes (query-count assertion).
- [ ] **ARCH-001:** permission-resolution issues a bounded SQL query, not a whole-table hydrate; **parity test** vs the old PHP evaluator passes for every identity shape.
- [ ] **ARCH-003:** authenticated endpoints return 429 past the limit; unauthenticated 403 storm is rate-capped.
- [ ] **API-003:** concurrent-create test yields unique references, zero `REFERENCE_ALLOCATION_FAILED`; behaves correctly past a simulated 7-digit sequence.
- [ ] **CACHE-001:** two banks' dashboards never share a cache entry (cross-bank leakage test); Redis-down → live compute, no 500.
- [ ] **QUEUE-001:** forced scan failure marks the document fail-closed (not clean/available); alert emitted.
- [ ] **OBS-001:** Pulse shows per-endpoint p50/p95/p99 + query counts; slow-query log captures the pre-fix hot queries.
- [ ] **SEC-002:** after `bank_id` backfill, a bank admin sees only their bank's audit rows; a cross-bank id returns 403.
- [ ] **Every optimized query:** re-verify it still applies `forUser`/accessible-stage scoping (Block 5 gate conditions).

## Seeder / EXPLAIN harness — keep or discard recommendation

**Recommendation: keep, promoted to a documented performance-testing asset — but only after hardening.** The harness produced real design-target evidence and will be needed again for Phase F. However, as-built it is scratchpad-quality (tinker `--execute`, hardcoded offsets, the PHP-memory fallback to set-based SQL).

Convert it into `backend/tests/Performance/` (or `tools/performance/`) **only if** it is made: (a) deterministic (already seeds with `mt_srand(42)`), (b) idempotent + environment-guarded (already refuses any DB ≠ `yfh_audit` — extend to refuse `production`), (c) documented (row targets, distributions, run command), (d) isolated from normal seeders (never in `DatabaseSeeder`), (e) memory-safe (adopt the set-based SQL multiply as the primary path, not a fallback). A default-off CI performance profile can invoke it for Phase F regression runs. **Until hardened, keep it local-only** (current state) — do not commit the scratchpad version.

## Coverage note

All 11 prompt phases map to a document: Phase 1→`01`, 2+5→`04`, 3+4→`03`, 6+7+8→`05`, 9+10→`06`, 11→`08`, deliverables→`02`/`07`/README. All 29 findings carry the full lifecycle schema. See README executive summary.
