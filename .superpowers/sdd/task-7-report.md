# Task 7 Report: Full-suite verification, 200K-row load run, roadmap closure

## Status: DONE

## Summary

Executed per `.superpowers/sdd/task-7-brief.md` Steps 1-6, controller-driven (not delegated —
this is the plan's final, highest-stakes verification task, done directly rather than via
subagent). The 200K-row load run initially FAILED both gates even after the UNION restructure
(Tasks 1-6); root-caused via `EXPLAIN ANALYZE` to three additional, real, fixable issues (not the
fundamental MySQL IN+ORDER BY limitation the UNION restructure already solved) and fixed all
three before both gates genuinely passed.

## Step 1: full backend test suite

`php artisan test`: **1298 tests (1245 deprecated-marker + 53 clean), 4575 assertions, exit code
0, zero failures.** Confirmed via `grep -iE "FAIL|failed|error"` on the full output (excluding
the pre-existing PHP 8.5 PDO deprecation noise) — no matches.

## Step 2-3: 200K-row load run — first attempt FAILED, root-caused, fixed

First `perf:load-scenario --rows=200000` run (on top of Tasks 1-6's UNION restructure alone):
**both gates FAILED** — my-queue p95 462.88ms, list p95 586.47ms (target ≤300ms). Per the brief's
own instructions, did not mark the gate closed; instead traced the actual generated SQL via
`DB::listen()` against a real `myQueue()`/`index()` call and ran `EXPLAIN`/`EXPLAIN ANALYZE`
directly against the real dev MySQL DB (8.4.9). Found three distinct, real causes across three
fix iterations, each verified via a fresh `perf:load-scenario` run before moving to the next:

1. **Missing/incomplete covering indexes** — `er_stage_sla_deadline` only covered
   `(current_stage_id, sla_deadline_epoch)`, not the full multi-column sort key each endpoint's
   `UnionStagePaginator` branch actually orders by; no index at all covered
   `(current_stage_id, created_at, id)` for the list endpoint. New migration
   `2026_07_11_100001_widen_stage_scoped_sort_indexes_on_engine_requests` widens/adds both,
   the latter via raw DDL for an explicit `created_at DESC, id ASC` per-column direction (no
   Laravel Blueprint fluent equivalent; a plain ascending index still filesorts on the endpoint's
   original mixed-direction sort, confirmed via `EXPLAIN`). Applied via `php artisan migrate`
   (not raw ad-hoc DDL — an earlier attempt at raw tinker `DROP INDEX`/`CREATE INDEX` was
   correctly blocked by the permission system as schema drift outside the migration file; rolled
   back cleanly and redone through the migration).
2. **MySQL derived-table optimizer quirk** — the identical per-branch query planned differently
   standalone (correct index, ~3ms) vs. nested inside `paginateUnion()`'s `UNION ALL` (wrong
   index, ~150-180ms per branch), confirmed via `EXPLAIN ANALYZE` on both shapes of the real
   query. Added an optional `$forceIndex` parameter to `UnionStagePaginator::paginate()`
   (Laravel's native `forceIndex()`), applied by both real call sites with their respective
   index name; ignored on non-MySQL drivers.
3. **(my-queue only) computed leading sort clause** — `EngineRequest::slaOrderSpec()`'s
   `CASE WHEN current_stage.sla_duration_minutes IS NULL...` tiebreaker defeats even a forced
   covering index for the ORDER BY, but is provably constant within any single stage's branch
   (a joined stage-level column, not per-row). Added an opt-in `$stageInvariant` flag (4th
   sort-spec tuple element, default `false`) that excludes such a raw entry from the per-branch
   `ORDER BY` only — still selected, still included in the merge sort where it's actually needed.
   Closed the last ~150x-per-branch gap with zero output change; verified the existing ordering
   tests (including the specific "SLA row sorts before no-SLA row across a merge" case) stayed
   green throughout.

Each fix was presented to the user as a decision point before implementing (schema migration,
then the deeper query-planner quirk) rather than assumed unilaterally, since both cross from
"tune the paginator" into "change the database schema" / "add a non-obvious correctness-adjacent
optimization" territory beyond the original 7-task plan's literal scope.

**Final load-run result** (`perf:load-scenario --rows=200000`, 2 accessible stages, 20 runs/endpoint):

```
=== my-queue (DB-001 gate: p95 <= 300ms) ===
  p95: 246.51 ms — PASS (was 574ms/554ms in prior sessions' whereIn attempts, 462.88ms after
  the UNION restructure alone)

=== engine-requests list (DB-002/ARCH-004 gate: p95 <= 300ms) ===
  p95: 221.74 ms — PASS (was 367ms/~374ms in prior sessions, 586.47ms after UNION alone)

=== API-001 gate: query count constant across page sizes ===
  PASS (17 queries at per_page=10/50/200)
```

Both gates genuinely pass. Fix commit: `0557b466`.

## Step 4: EXPLAIN evidence

`docs/audit/evidence/explain/DB-001-002-union-per-stage.txt` — captures the full diagnostic trail
across all three fixes (before/after EXPLAIN and EXPLAIN ANALYZE output), each against the real
generated SQL, not hand-approximated.

## Step 5: results summary

`docs/audit/evidence/DB-001-002-union-restructure-results.md` — documents the UNION-per-stage
approach, threshold-fallback design, harness fix, before/after p95 table, all three follow-up
fixes with their root causes, test evidence, and load-run evidence. Explicit sequel to
`evidence/DB-001-002-sla-deadline-column-followup.md` (the prior session's stopping point).

## Step 6: roadmap update

`docs/audit/07-roadmap.md`'s DB-001 and DB-002 checklist rows changed from `- [ ]` to `- [x]`,
each citing the closing commits/evidence files, following the existing closed-gate prose style
(state what was done and cite evidence, not a narrative — the narrative lives in the evidence doc).

## Commits (Task 7)

```
0557b466 perf(backend): close DB-001/DB-002 p95 gate with covering indexes + forceIndex
3f5d4cf6 docs(docs): close DB-001/DB-002 gates with UNION-per-stage load-run evidence
```

Both signed, conventional format, co-authored by Claude.

## Test evidence (final)

```
php artisan test: 1298 tests, 4575 assertions, exit code 0, zero failures
tests/Feature/Engine/: 275 tests, 969 assertions, zero failures (re-confirmed after the
  stageInvariant fix, no test count change since it modified existing behavior, not added tests)
UnionStagePaginatorTest: 10 tests, 33 assertions
MyQueueUnionParityTest: 3 tests, 9 assertions
ListEndpointUnionParityTest: 3 tests, 9 assertions
Pint: clean on all touched files (app/Support/UnionStagePaginator.php,
  app/Models/EngineRequest.php, app/Http/Controllers/Api/V1/EngineRequestController.php,
  the new migration)
```

## Session interruption note

A sustained safety-classifier outage (~15-20 minutes) blocked all Bash/Write/Edit tool calls
partway through Step 6 (after the roadmap doc was edited on disk but before it could be staged/
committed). File content was unaffected (verified readable throughout); resumed the mechanical
git add/commit/cleanup once tooling recovered. No rework needed.

## Concerns

None outstanding on the code/verification itself. Two decision points (schema migration; the
deeper MySQL-derived-table-optimizer-quirk fix) were surfaced to the user before implementing
since they exceeded the original plan's literal scope — both approved, both verified working via
the real load harness before being considered closed. The plan's remaining open roadmap items
(API-003 concurrent-create load test, OBS-001 Pulse dashboard, the "every optimized query still
applies forUser/accessible-stage scoping" re-verification checklist item) are explicitly out of
scope for this 7-task plan per its own "Out of scope" section and were not touched.
