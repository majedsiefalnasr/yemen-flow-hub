# DB-001 / DB-002 — UNION-per-stage query restructure

Branch: `perf/db-001-002-sla-union-restructure`
Precedes: `docs/audit/07-roadmap.md` open gates DB-001, DB-002
Builds on: `docs/audit/evidence/DB-001-002-sla-deadline-column-followup.md`

## Problem

`docs/audit/07-roadmap.md`'s verification checklist has DB-001 and DB-002 both open. The load-run
harness (`perf:load-scenario`) proved both `GET /api/v1/engine-requests/my-queue` (DB-001) and
`GET /api/v1/engine-requests` (DB-002) pass their p95 ≤ 300ms gate at 1K/50K rows but fail at
200K rows (574ms / 367ms respectively), even after DB-001's follow-up work added the maintained,
indexed `sla_deadline_epoch` column.

The follow-up doc traced the real cause via `EXPLAIN` on the actual generated SQL: **MySQL cannot
use an index for both a multi-value `IN (...)` filter and an `ORDER BY` on a different column at
the same time.** Both endpoints filter `current_stage_id IN ($accessibleStageIds)` (a user with
access to more than one workflow stage — the realistic case) and sort by an unrelated column
(`sla_deadline_epoch` for my-queue, `created_at` for the list). Confirmed via `EXPLAIN`: a single
stage ID uses the index cleanly (`Using index condition`); two or more force a filesort
(`Using where; Using temporary; Using filesort`). This is a documented MySQL query-optimizer
limitation, not a missing-index gap.

**Second gap found during this investigation**: `perf:load-scenario`'s fixture only ever grants
one `StagePermission` (one accessible stage), so the harness has never actually exercised the
multi-stage case it's meant to gate — it reports PASS on a code path that isn't the one failing
in the field.

## Approach

Restructure both endpoints from a single `whereIn(current_stage_id, [...]) ORDER BY ...` query
into a per-stage `UNION ALL`, following the standard MySQL workaround for "IN + ORDER BY across a
composite index": one indexed, sorted, limited subquery per accessible stage ID, merged and
re-sorted in a final pass over an already-small result set.

Applies to both `myQueue()` (DB-001, sorts by `sla_deadline_epoch`/`stage_entered_at`) and
`index()` (DB-002, sorts by `created_at`) — same root cause, same fix shape, different sort
columns. Doing both now avoids a second investigation cycle through the same MySQL limitation.

Pagination stays page-number-based (no cursor/keyset change to the API contract) — a UNION
derived table with `LIMIT/OFFSET` and a matching `COUNT(*)` still avoids the filesort that's
failing today, even though OFFSET cost still scales with page depth. Queues are triaged from
page 1 in practice; a full keyset-pagination migration is a separately-scoped, cross-cutting
change (frontend included) and is not required to close these gates.

## Query construction

Both endpoints currently build an Eloquent query with `withStageEntry()` (join to
`current_stage`), `EngineRequestListQuery::applyFilters()` (arbitrary user filters), eager loads
(`with([...])`), and `whereIn('current_stage_id', $stageIds)`. A UNION can't carry Eloquent
eager-loads through it directly, so the rewrite splits into two phases:

1. **ID-resolution phase (UNION)**: for each accessible stage ID, build the same filtered,
   stage-scoped base query (`current_stage_id = $oneStageId` instead of `IN (...)`), selecting
   only `id` + the sort columns, `ORDER BY <same sort as today> LIMIT ($offset + $perPage)`.
   Each branch must emit enough rows to guarantee correctness after the merge — a requested page
   might come entirely from one stage — so the per-branch limit is `offset + perPage`, not just
   `perPage`. All branches combined via `unionAll()`, wrapped as a derived table, re-sorted by the
   same columns, then sliced to `[offset, offset + perPage)` for the final ID list.
2. **Count phase**: a sibling UNION ALL of `SELECT id` per stage (no LIMIT), wrapped in
   `SELECT COUNT(*) FROM (...) x` for the pagination `total`.
3. **Hydration phase**: `EngineRequest::query()->whereIn('id', $resolvedIds)->with([...])`, real
   Eloquent models, existing `EngineRequestResource` serialization untouched. Order must be
   restored to match the UNION's merged order (`ORDER BY FIELD(id, ...)` or re-sort in PHP after
   eager-loading) — response ordering must be identical to today's semantics.

**Threshold fallback**: if the accessible-stage count exceeds
`config('workflow.list_union_stage_threshold', 10)`, skip the UNION path entirely and run today's
`whereIn(...) ORDER BY ...` query unchanged — correct but not optimized. Protects against a
broad-access role (e.g. many-stage VIEW access) issuing dozens of subqueries per request, a shape
the existing single-stage harness fixture never tested. Threshold is configurable, not hardcoded,
so it can be tuned against load-harness results without a code change.

**Zero accessible stages**: short-circuit to an empty paginator, no query — must match existing
behavior exactly (already the case today; must not regress).

## Where this lives

New class `app/Support/UnionStagePaginator.php`: takes a stage-scoped query-builder factory
(closure receiving one stage ID, returning a filtered `Builder`), the accessible stage ID list,
an ordered list of `[column, direction]` sort pairs applied identically at both the per-branch
`ORDER BY` and the outer merge `ORDER BY` (my-queue passes its 3-clause SLA-priority order;
`index()` passes its 2-clause `created_at, id` order — same paginator, different sort arrays), and
page/perPage. Returns a `LengthAwarePaginator`. Both `EngineRequestController::myQueue()`
and `EngineRequestController::index()` call it, each supplying their own sort array; both keep
calling `EngineRequestListQuery::applyFilters()` unchanged inside the per-stage closure, so filter
behavior (search, sla_status, claimed, date range, etc.) is identical across all stage branches —
no filter-specific code changes needed.

## Harness fix (prerequisite for verifying this work)

Extend `PerfLoadScenarioCommand::buildFixture()` to create a second `WorkflowStage` +
`StagePermission` (EXECUTE and VIEW) alongside the existing one, and split `bulkInsert()`'s seeded
rows roughly 50/50 across both stages. Without this, the harness cannot fail on the regression
it's supposed to gate — it would keep reporting PASS on the single-stage path while the
multi-stage path (the one actually failing in the field) goes unexercised.

## Testing plan

- New test file (`SlaUnionQueryTest.php` or similar under `tests/Feature/Engine/`): parity
  assertions that the UNION path returns identical results (same IDs, same order, same pagination
  meta: `total`, `last_page`) to the pre-existing `whereIn` path, across: 1 stage, 2 stages,
  threshold+1 stages (confirms fallback triggers), zero stages, each existing filter applied
  individually, and page 2+ (not just page 1).
- Existing `SlaProjectionParityTest`, `SlaDeadlineEpochColumnTest`, `EngineSearchTest` must stay
  green — no behavior change to the already-passing single-stage case.
- Re-run `perf:load-scenario --rows=200000` (after the harness fix above) before/after this
  change; confirm the p95 ≤ 300ms gate now passes for both `my-queue` and the list endpoint at
  the 2-stage case. Capture new `EXPLAIN` output into `docs/audit/evidence/explain/` alongside the
  existing DB-001/DB-002 evidence files.
- Update `docs/audit/07-roadmap.md`'s DB-001/DB-002 checklist rows to closed (`[x]`) once the
  load-run harness confirms the gate, with evidence file references — matching the existing
  pattern for other closed gates in that file.

## Out of scope

- Keyset/cursor pagination (a separate, cross-cutting change touching the frontend contract).
- Any change to `EngineRequestListQuery::applyFilters()`'s filter semantics.
- API-003 (concurrent-create load test) and OBS-001 (Pulse dashboard) — separate open roadmap
  gates, not touched by this work.
