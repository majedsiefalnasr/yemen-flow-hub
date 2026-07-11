# Task 6: Extend `perf:load-scenario` to seed a second accessible stage

## Status: DONE

## Summary

Modified `backend/app/Console/Commands/PerfLoadScenarioCommand.php` to seed two accessible stages instead of one, enabling the load-test harness to exercise the multi-stage-access query regression Tasks 1-5 built `UnionStagePaginator` to fix. The harness's own smoke test then surfaced a **real, previously-undetected MySQL-only bug** in `UnionStagePaginator` — exactly the kind of gap this task exists to close — which was root-caused and fixed as a follow-up commit.

## Changes Applied (implementer subagent, commit `39476b26`)

### Step 1: Updated `buildFixture()`
- Return type changed from a single `stage` to a `stages` array of two `WorkflowStage` instances (`$execStageA`, `sla_duration_minutes: 1440`; `$execStageB`, `sla_duration_minutes: 720`).
- `StagePermission` created for both stages in a loop.
- Return array updated to `['stages' => [$execStageA, $execStageB], ...]`.

### Step 2: Updated `bulkInsert()`
- Parameter/destructure changed to `[$stageA, $stageB]`.
- Alternating stage assignment: `$stage = $seq % 2 === 0 ? $stageA : $stageB`.
- Each row's `sla_deadline_epoch` computed from its own stage's `sla_duration_minutes`.

### Step 3: Verified `cleanup()`
No change needed — it already deletes by `workflow_version_id`, which correctly covers both stages under that version.

### Step 4: Updated status messages
Both gate-header strings now note "2 accessible stages".

## Step 5: Smoke test — found a real bug, not an environmental artifact

The implementer's initial smoke test (`php artisan perf:load-scenario --rows=1000`) reported HTTP 500 on both `my-queue` and the list endpoint, and characterized this in the original report as a "pre-existing environmental issue... unrelated to the stage-splitting changes." **This characterization was wrong** — the controller re-ran the harness directly (`--rows=200`) and traced the actual exception via `storage/logs/laravel.log`:

```
PDOException: SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'id'
  at UnionStagePaginator.php:230 (the merged-ID pluck)
  called from UnionStagePaginator.php:58 (paginateUnion)
  called from EngineRequestController.php:300 (myQueue -> UnionStagePaginator::paginate)
```

**Root cause**: `myQueue()`'s sort spec ends with `['engine_requests.id', 'asc']` (the standard tiebreaker, also used by `index()`'s sort spec). `paginateUnion()`'s per-branch loop unconditionally `select('engine_requests.id')` first, then for EVERY sort-spec column — including one that is itself `engine_requests.id` — called `addSelect($column . ' as ' . last(explode('.', $column)))`, producing a second, differently-cased-but-same-named `id` column in the SELECT list (`... as id`). MySQL rejects a duplicate column name in a single SELECT outright; SQLite silently tolerates it. This is exactly why the SQLite-backed PHPUnit suite (274 tests, all passing throughout) never caught it, and why this task's entire purpose — a harness that actually exercises the 2-stage path against real MySQL — is what surfaced it.

**Fix** (`backend/app/Support/UnionStagePaginator.php`, commit `5e7549d6`, separate from `39476b26` since it's a distinct bug fix, not part of the harness-seeding scope): skip the redundant `addSelect` when the sort-spec column is already `engine_requests.id` (already unconditionally selected). The `orderBy()` call is unaffected and still applies.

**Verification of the fix**:
- Re-ran `php artisan perf:load-scenario --rows=200` against real MySQL: my-queue and list both now return `200` (previously `500`), both gates report `PASS` (p95 ~46-50ms, well under the 300ms gate at this small scale — the real 200K-row verification is Task 7's job).
- `php artisan test --filter=UnionStagePaginatorTest` (9 tests, 21 assertions), `MyQueueUnionParityTest` (3/9), `ListEndpointUnionParityTest` (3/9): all still pass — no SQLite-side regression from the fix.
- `php artisan test tests/Feature/Engine/`: 274 tests, 957 assertions, exit 0 — no regression anywhere else.
- Pint: `app/Support/UnionStagePaginator.php` passed clean.

## Step 6: Pint Format Check (Task 6's own files)
```
{"tool":"pint","result":"passed"}
```

## Step 7: Commits

```
39476b26 perf(backend): seed 2 accessible stages in the load-run harness
5e7549d6 fix(backend): avoid duplicate id column in UnionStagePaginator union branches
```

Both signed, conventional format, scope `backend`, co-authored by Claude.

## Files Modified

- `backend/app/Console/Commands/PerfLoadScenarioCommand.php` (harness seeding, commit `39476b26`)
- `backend/app/Support/UnionStagePaginator.php` (bug fix, commit `5e7549d6`)

## Concerns (original)

None outstanding at the time. The harness fix itself was correct and complete per the brief on the first pass. The real finding here is that this task's smoke-test step did its job exactly as intended — a MySQL-only bug that no PHPUnit test (SQLite-backed) could ever catch was caught the moment a real 2-stage fixture ran against real MySQL. Worth noting for Task 7: this confirms the harness is now a meaningful gate, not just a seeding mechanism — the 200K-row run should be trusted as real verification, not a formality.

## Addendum: closing the regression-test gap (commit `b40a0a8c`)

The task review of `81f7c3b7..5e7549d6` approved both commits but raised one Important finding: no PHPUnit test (they all run on SQLite per `phpunit.xml`) would ever fail if the duplicate-column bug were reintroduced, since SQLite silently tolerates the exact SQL shape MySQL rejects. Every existing `UnionStagePaginatorTest` test already uses `engine_requests.id` as its tiebreaker and none of them caught the original bug — the review correctly identified this as residual risk carried forward, not something Task 6 was strictly scoped to close, but worth fixing before moving on.

Added `test_union_branch_select_never_duplicates_the_id_column` to `UnionStagePaginatorTest.php`: calls the real `UnionStagePaginator::paginate()` entrypoint (not a reimplementation of its private branch-building logic), captures the actual generated SQL via `DB::listen()`, and asserts no `select ... from` clause anywhere in that SQL — including the per-branch selects nested inside the `UNION ALL` — aliases two columns to the same name. This is driver-independent: it inspects the SQL text Laravel generates, not runtime behavior, so it fails on SQLite too if the bug is reintroduced.

**Verified the test actually proves the fix**, not just that it passes: temporarily reverted the `if ($column !== 'engine_requests.id')` guard in `paginateUnion()`, re-ran the new test in isolation — failed with a genuine assertion failure (duplicate `id` alias detected in the captured SQL, matching the exact shape from the live MySQL trace: `` "engine_requests"."id", "engine_requests"."created_at" as "created_at", "engine_requests"."id" as "id" ``). Restored the real fix, re-ran — passed (12 assertions, since 3 queries are captured and each is checked, plus the earlier `assertNotEmpty`).

Also had to fix the regex logic mid-development: my first draft only matched the *outermost* `select ... from` in the captured SQL, which for a union query is just `select "id" from (...)` — the actual duplicate lives in a *nested* per-branch select buried inside parens. Switched from a single anchored match to `preg_match_all` over every `select ... from` occurrence in the string, which correctly reaches the nested branch selects.

**Re-verification after the addendum**:
- `php artisan test --filter=UnionStagePaginatorTest`: 10 tests (was 9), 33 assertions (was 21), 0 failures.
- `php artisan test tests/Feature/Engine/`: 275 tests (was 274), 969 assertions (was 957), exit 0 — no regression.
- Pint: one auto-fix (`single_quote`), then clean.
- Removed a stray `.debug` scratch file created during the regex-debugging process before committing — confirmed not staged.

## Concerns (final)

None outstanding. The residual risk the review flagged is now closed: a duplicate-column regression in `UnionStagePaginator::paginateUnion()` will now fail on SQLite (the suite's actual test driver), not just be silently missed until a live MySQL run.
