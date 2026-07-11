# DB-001/DB-002 UNION-restructure — final results

Sequel to `docs/audit/evidence/DB-001-002-sla-deadline-column-followup.md`, which stopped at the
recommendation to restructure `my-queue`'s and the list endpoint's queries into a per-stage
UNION rather than adding more maintained columns. This document records what that restructure
actually shipped, the two follow-up fixes the 200K-row load harness surfaced, and the final
passing load-run evidence.

## Approach taken

Implemented `App\Support\UnionStagePaginator` (backend commits on branch
`perf/db-001-002-sla-union-restructure`): for a user with EXECUTE/VIEW access to N workflow
stages, builds one indexed, sorted, limited subquery per accessible stage (`current_stage_id =
$stageId`, a single equality, not the `IN (...)` that defeated the index in the prior session's
investigation), `UNION ALL`s them, and re-sorts/re-slices the small merged result to resolve a
page of IDs — then hydrates real Eloquent models for just those IDs. Falls back to the original
single `whereIn(...)` query above a configurable stage-count threshold
(`config('workflow.list_union_stage_threshold', 10)`), so a broad-access role never issues dozens
of subqueries per request.

Both `EngineRequestController::myQueue()` (DB-001) and `::index()` (DB-002, non-`SYSTEM_ADMIN`
branch only — that role has no stage filter to union across) now route through
`UnionStagePaginator::paginate()`.

## Threshold-fallback design

`UnionStagePaginator::paginate()` short-circuits to the pre-existing single `whereIn(...)` query
(via `paginateWhereIn()`) whenever `count($stageIds) > $threshold`. This bounds worst-case query
fan-out for a role with very broad stage access — correct either way, just not the optimized path
above the threshold. `paginateWhereIn()` required its own binding-array-desync fix (positional,
not by-value, binding-slot removal — see commit `15663e3d`) since Laravel keeps a query's `wheres`
and `bindings['where']` as two independently-appended arrays.

## Harness fix (2-stage fixture)

`perf:load-scenario`'s fixture originally granted exactly one `StagePermission`, so
`current_stage_id IN (...)` always degenerated to a single-value `IN` — the harness had never
actually exercised the multi-stage-access regression these gates are about. Extended
`buildFixture()`/`bulkInsert()` to seed two accessible stages (`EXEC_A`/`EXEC_B`, distinct SLA
durations) and split seeded rows evenly across both (commit `39476b26`). This immediately
surfaced two real, previously-undetected bugs invisible to the SQLite-backed PHPUnit suite:

1. **Duplicate-column SQL error** (commit `5e7549d6`): `paginateUnion()`'s per-branch `SELECT`
   re-added `engine_requests.id` under a second `id` alias whenever the sort-spec's tiebreaker
   was itself `engine_requests.id` (both endpoints' sort specs end this way) — MySQL rejects a
   duplicate column name outright (`SQLSTATE[42S21]`); SQLite silently tolerates it. A driver-
   agnostic regression test (commit `b40a0a8c`) now inspects the real generated SQL via
   `DB::listen()` so this class of bug fails on SQLite too, not just a live MySQL run.
2. **Empty-paginator `Collection` type mismatch** (commit `625d5099`): both of
   `paginateUnion()`'s empty-result branches constructed `LengthAwarePaginator` from a bare PHP
   array, which Laravel wraps in a base `Illuminate\Support\Collection` — a class with no
   `load()` method. Any zero-row `my-queue`/list call (a very common real scenario) threw a live
   500 the moment `$page->load([...])` ran. Fixed by constructing an empty
   `Illuminate\Database\Eloquent\Collection` instead, which does define `load()` as a safe no-op.

## p95 gate: before vs. after (200K rows)

| Endpoint | Prior session baseline (`whereIn`, multi-stage) | Post-UNION-restructure, pre-final-fix | **Final** |
| --- | --- | --- | --- |
| my-queue (DB-001) | 574ms → 554ms (follow-up doc) | 462ms | **246.51ms** ✅ |
| engine-requests list (DB-002) | 367ms → ~374ms (follow-up doc) | 586ms → 217ms (after index fix alone) | **221.74ms** ✅ |

Both under the 300ms gate. The UNION restructure alone was not sufficient — two more real,
measured causes remained, both found by running the actual 200K-row harness against MySQL and
tracing the generated SQL (not hand-approximating it):

1. **Missing/incomplete covering indexes.** `er_stage_sla_deadline` only covered
   `(current_stage_id, sla_deadline_epoch)` — the leading sort column, not the full ORDER BY key
   (`sla_deadline_epoch, stage_entered_at, id`), so ties on `sla_deadline_epoch` at scale forced a
   filesort. No index at all covered `(current_stage_id, created_at, id)` for the list endpoint.
   Migration `2026_07_11_100001_widen_stage_scoped_sort_indexes_on_engine_requests` widens
   `er_stage_sla_deadline` to the full 4-column key and adds `er_stage_created` — the latter via
   raw DDL with an explicit `created_at DESC, id ASC` per-column direction (Laravel's Blueprint
   has no fluent API for this; a plain ascending composite index still filesorts on a mixed-
   direction sort, confirmed via `EXPLAIN`, and changing the `id` tiebreaker to `DESC` to match
   would be an observable pagination-order change the byte-identical-response constraint rules
   out — `id ASC` is the endpoint's original, pre-restructure tiebreaker direction).
2. **MySQL's derived-table optimizer quirk.** The identical per-branch query costed and planned
   differently depending on whether it ran standalone (correctly used the covering index, ~3ms)
   or nested inside `paginateUnion()`'s `UNION ALL` (picked an unrelated `bank_id` index instead,
   scanning the full stage's row set, ~150–180ms per branch) — confirmed precisely via `EXPLAIN
   ANALYZE` on both shapes of the same real query. `UnionStagePaginator::paginate()` gained an
   optional `$forceIndex` parameter (Laravel's native `forceIndex()`), applied by both real
   callers with their respective covering index name; ignored on non-MySQL drivers.
3. **(my-queue only) A computed leading sort clause.** `EngineRequest::slaOrderSpec()`'s
   `CASE WHEN current_stage.sla_duration_minutes IS NULL THEN 1 ELSE 0 END` tiebreaker is a
   computed expression MySQL cannot use an index for, even with the covering index forced. It's
   provably constant within any single stage's branch (the value comes from the joined
   `current_stage` row, not a per-row `engine_requests` column), so a new opt-in
   `$stageInvariant` flag on raw sort-spec entries (4th tuple element, default `false`) excludes
   it from the *per-branch* `ORDER BY` only — still selected, still included in the outer merge's
   `ORDER BY`, where it's needed to correctly interleave rows from an SLA-configured stage against
   a no-SLA stage. This closed the last ~150x-per-branch gap with zero output change (existing
   ordering tests, including the specific "SLA row sorts before no-SLA row" case, stayed green
   throughout).

See `docs/audit/evidence/explain/DB-001-002-union-per-stage.txt` for the full EXPLAIN/EXPLAIN
ANALYZE trail across all three fixes, each captured against the actual generated SQL (via
`DB::listen()`), not a hand-approximated query.

## Test evidence

```
UnionStagePaginatorTest: 10 tests, 33 assertions, all pass (includes the duplicate-column
  regression test and the stage-entered-at COALESCE-fallback hydration test)
MyQueueUnionParityTest: 3 tests, 9 assertions, all pass
ListEndpointUnionParityTest: 3 tests, 9 assertions, all pass
SlaDeadlineEpochColumnTest: 5 tests, 13 assertions, unchanged, all pass
SlaProjectionParityTest: 3 tests, 5 assertions, unchanged, all pass
EngineSearchTest: 7 tests, 20 assertions, unchanged, all pass
Full tests/Feature/Engine/: 275 tests, 969 assertions, all pass
Full backend suite (php artisan test): 1298 tests, 4575 assertions, exit code 0, zero failures
```

## Migration evidence

Verified against the real dev DB (MySQL 8.4.9, `yfh-mysql` connection): migration applied via
`php artisan migrate` (not raw ad-hoc DDL), `er_stage_sla_deadline` confirmed widened to 4 columns
and `er_stage_created` confirmed created with its explicit `DESC`/`ASC` per-column direction via
direct `EXPLAIN` inspection post-migration — both new indexes are the ones actually selected by
the query planner for their respective endpoint's per-branch query, per the EXPLAIN evidence file.

## Load-run evidence

`php artisan perf:load-scenario --rows=200000` (2-stage fixture, 100,000 rows per stage, 20 runs
per endpoint):

```
=== my-queue (DB-001 gate: p95 <= 300ms, 2 accessible stages) ===
  status: 200 (20 runs)
  wall clock — min: 215.65 ms · median: 231.96 ms · p95: 246.51 ms · max: 251.1 ms
  query count — constant at 17
  gate (p95 <= 300ms): PASS

=== engine-requests list (DB-002/ARCH-004 gate: p95 <= 300ms, 2 accessible stages) ===
  status: 200 (20 runs)
  wall clock — min: 200.19 ms · median: 207.19 ms · p95: 221.74 ms · max: 222.76 ms
  query count — constant at 17
  gate (p95 <= 300ms): PASS

=== API-001 gate: query count constant across page sizes (my-queue) ===
  per_page=10/50/200: query count = 17 (all three) — PASS
```

Both DB-001 and DB-002 p95 gates now pass at 200K rows with 2 accessible stages — the realistic
multi-stage-access scenario the prior session's follow-up explicitly could not close.
