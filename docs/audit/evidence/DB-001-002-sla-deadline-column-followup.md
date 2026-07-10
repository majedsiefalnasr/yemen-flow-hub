# DB-001/DB-002 follow-up — SLA-deadline column, and why the p95 gate still doesn't close

## What was attempted

The load-run harness (previous session) proved my-queue's and the list endpoint's p95 gate (≤300ms) failed at 200K rows even though query count stayed constant — the suspected cause was `EngineRequest::scopeOrderBySlaPriority()`'s `ORDER BY` sorting on a computed expression (joined `sla_duration_minutes` + epoch conversion), which can't use a plain index for the sort.

Built on ARCH-002's precedent (`stage_entered_at` promoted from a correlated subquery to a maintained, indexed column): added `engine_requests.sla_deadline_epoch` (nullable `bigint`, UNIX epoch seconds), maintained at the same two write sites as `stage_entered_at` — `EngineRequestService::create()` and `EngineTransitionService::execute()` — where the target `WorkflowStage`'s `sla_duration_minutes` is already loaded in memory, no extra query needed. Indexed as `er_stage_sla_deadline (current_stage_id, sla_deadline_epoch)`.

Safe to maintain without invalidation-on-stage-edit: `WorkflowDesignerService::updateStage()` only allows editing a stage (including `sla_duration_minutes`) while its parent `WorkflowVersion` is `DRAFT` (`ensureEditable()`) — a published stage's SLA window never changes under a live request, so a deadline computed once at create/transition time never goes stale.

## Iteration 1: COALESCE defeats the index (found and fixed)

First cut read the new column via `COALESCE(engine_requests.sla_deadline_epoch, <fallback expression>)`, matching the existing `stageEnteredAtSql()` pattern exactly for consistency. Re-ran the load harness at 200K rows: **no improvement** (585ms vs 574ms baseline). `EXPLAIN` on the actual query showed `Using where; Using temporary; Using filesort`, with MySQL choosing an unrelated `bank_id` index over the new `er_stage_sla_deadline` index — the optimizer cannot prove `COALESCE(indexed_col, fallback_expr)` preserves the index's sort order, so it falls back to materializing and sorting the full filtered set.

Fixed by giving `scopeOrderBySlaPriority()` its own raw-column ordering (`orderBy('engine_requests.sla_deadline_epoch', 'asc')`, no COALESCE), keeping the COALESCE'd `slaDeadlineEpochSql()` only for `EngineRequestListQuery::applySlaStatusFilterInternal()`'s WHERE-clause breach/nearing/ok filtering, which doesn't have the same sort-order-preservation requirement. A null `sla_deadline_epoch` (should only occur pre-backfill or via a hypothetical write-path bug — both real write paths always populate it) sorts NULL-first in MySQL ASC, i.e. as if maximally breached — the safe-by-default direction for an operational queue.

Confirmed via isolated `EXPLAIN` with a single `current_stage_id` value: index used, no filesort (`Using index condition`, 1 row scanned on the real dev row that has this column populated).

## Iteration 2: the oldest-in-stage tiebreaker still had the same problem (found and fixed)

Re-ran the load harness: **still no improvement** (554ms). Root cause: `scopeOrderBySlaPriority()`'s *third* `ORDER BY` clause (the oldest-in-stage tiebreaker) was untouched by iteration 1's fix — it still called `UNIX_TIMESTAMP(COALESCE(stage_entered_at, <correlated subquery>))`, the exact same non-sargable pattern, just on a different column. MySQL's `ORDER BY` optimization requires *every* clause in a multi-column sort to be index-compatible, not just the first one — one bad clause defeats the whole plan.

Fixed by ordering the tiebreaker on the raw `stage_entered_at` column directly (already indexed via `er_stage_entered`, and epoch conversion is monotonic so sorting the raw timestamp produces the same relative order as sorting its epoch value).

## Iteration 3: the real, still-unresolved blocker

Re-ran again: **still no improvement** (554ms → still failing). Traced the exact generated SQL via `DB::listen` against the real fixture user and got `EXPLAIN` on it directly (not a hand-approximated query) — this revealed the actual cause:

**MySQL cannot use an index for both a multi-value `IN (...)` filter and an `ORDER BY` on a different column at the same time.** `my-queue`'s `current_stage_id IN ($executeStageIds)` — the normal case whenever a user holds EXECUTE access on more than one workflow stage — forces MySQL to choose between a range-scan-friendly index for the `IN` filter or an index-order-preserving scan for the `ORDER BY`, not both. Confirmed precisely:

- `current_stage_id IN (31, 32)` (2 stage IDs, the realistic case) → `Using where; Using temporary; Using filesort`, MySQL picks the unrelated `bank_id` index over `er_stage_sla_deadline`.
- `current_stage_id IN (31)` (1 stage ID only) → `Using index condition`, no filesort, `er_stage_sla_deadline` used directly.

This is a documented MySQL query-optimizer limitation, not a missing-index problem — no amount of additional maintained/indexed columns closes it for the multi-stage-access case, because the fundamental constraint is that a single B-tree index scan can serve either the `IN`-list's multiple ranges or a global sort order, not both simultaneously, without an explicit UNION-per-value restructure.

## Decision: stop here, keep the column work

Confirmed with the user before spending further session budget on a genuine query-shape rewrite (e.g., UNION of one sorted-and-limited subquery per accessible stage ID, merged in a final pass) — a bigger, separately-scoped change with its own testing burden, not a natural continuation of "add a maintained column."

**Kept**: the `sla_deadline_epoch` column, its write-path maintenance, its backfill migration, and the ORDER BY simplification (raw column, no COALESCE) — these are real, verified improvements:
- The single-accessible-stage case (a common real scenario — e.g. a Bank Reviewer typically holds EXECUTE on exactly one stage) now genuinely uses the index and avoids the filesort, confirmed via `EXPLAIN`.
- Full regression suite (`tests/Feature/Engine/`, `tests/Feature/Report/`) passes with zero behavior change to `orderBySlaPriority()`'s observable output — `SlaProjectionParityTest`'s pre-existing parity assertions still hold.
- The column is not wasted work even for the multi-stage case: any future UNION-per-stage rewrite would directly build on this same column and index.

**Not fixed this session**: the general multi-accessible-stage `my-queue`/list p95 gate. DB-001/DB-002 stay open.

## Recommendation for whoever picks this up next

The next real step is restructuring `my-queue`'s query, not adding more columns:

```
For each stage_id in $executeStageIds:
    SELECT ... WHERE current_stage_id = stage_id AND status = 'ACTIVE' AND bank_id = ...
    ORDER BY sla_deadline_epoch ASC, stage_entered_at ASC
    LIMIT $page_size
(UNION ALL these, then re-sort + re-limit the merged, already-small result set)
```

Each per-stage subquery gets a clean index-only scan (as proven above for the single-stage case); the final merge-and-limit only has to sort at most `page_size × count(execute_stage_ids)` rows, not the full filtered table. This is the standard MySQL workaround for "IN + ORDER BY across a composite index" and should be verified against the load-run harness (`perf:load-scenario`, already built and reusable) before/after.

## Test evidence

```
SlaDeadlineEpochColumnTest: 5 tests (new), all pass
  - create/transition populate sla_deadline_epoch correctly from the target stage
  - transition to a no-SLA stage leaves it null
  - orders by the raw column, breached-first
  - a null deadline sorts first (safe-by-default), not last
SlaProjectionParityTest: 3 tests, unchanged, all pass (ARCH-002 fallback-path behavior preserved)
SlaReportTest: 5 tests, unchanged, all pass
Full tests/Feature/Engine/ + tests/Feature/Report/: 293 tests, 1019 assertions, all pass
```

## Migration evidence

Verified against the real dev DB (`cby_imports` via `yfh-mysql`): migration applied, column + index confirmed via `SHOW CREATE TABLE`, backfill correctly resolved the one live row with an SLA-configured stage, rolled back, re-applied — clean round trip.
