# Task 4 Report: Wire `UnionStagePaginator` into `index()` (DB-002)

## Status: DONE

## Summary

Implemented per `.superpowers/sdd/task-4-brief.md` Steps 1-7, on branch `perf/db-001-002-sla-union-restructure`. An earlier implementer subagent session (interrupted twice by connection drops — once mid-investigation of an unrelated timestamp bug, once mid-dispatch before doing any work) got as far as writing the failing parity test (Step 1) plus a scratch debug test used to diagnose an odd `created_at` ordering issue. The controller (me) verified both pieces of uncommitted work, resolved the root cause, deleted the scratch file, and completed Steps 3-7 directly.

## Pre-existing repo state (before I resumed)

`git status --short` from repo root showed:
```
M .superpowers/sdd/progress.md
M .superpowers/sdd/task-1-report.md
M .superpowers/sdd/task-6-report.md
M .superpowers/sdd/task-7-report.md
M .superpowers/sdd/task-8-report.md
?? backend/tests/Feature/Engine/DebugListOrderTest.php
?? backend/tests/Feature/Engine/ListEndpointUnionParityTest.php
```
`.superpowers/sdd/*` dirty files pre-existed from a prior unrelated plan, out of scope, left untouched. `backend/app/Http/Controllers/Api/V1/EngineRequestController.php` was clean (still at Task 3's `625d5099`), confirming no partial edit to the controller had landed.

Note: `.superpowers/sdd/*` files are tracked (not gitignored) and their committed HEAD content is a stale report from an unrelated WP-14 plan (`bcac5e01`/`f19c5e62`). Working-tree edits to these files (this report, `progress.md`) are session-scratch and do not get committed as part of this plan's backend-only commits — an external reset (observed mid-session) reverted this file's working-tree content back to that stale committed baseline once already; this content is being rewritten to reflect Task 4's actual work.

## Step 1 (from prior interrupted session, verified correct)

`ListEndpointUnionParityTest.php` was transcribed verbatim from the brief's Step 1 code. Confirmed correct on read — no deviation from the brief.

## Investigation artifact found and resolved: `created_at` not sticking in test fixtures

`DebugListOrderTest.php` (untracked scratch file, not part of the plan) showed the prior session was debugging why `makeRequest()`'s explicit `created_at`/`updated_at` values weren't taking effect — every row ended up with the same timestamp regardless of the `subDays()` offset requested, breaking the ordering assertion.

Root-caused directly: `EngineRequest::$fillable` (`app/Models/EngineRequest.php:20-41`) does **not** include `created_at`/`updated_at`. Laravel's `create()` silently discards non-fillable keys, so `EngineRequest::create(['created_at' => $createdAt, ...])` never persists the intended timestamp — Eloquent's own timestamp behavior then stamps every row with the same "now" value. This is the same bug Task 1's implementer independently discovered and fixed in its own test fixtures.

**Fix**: updated `ListEndpointUnionParityTest.php`'s `makeRequest()` helper to call `create()` without the timestamp keys, then `forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save()` afterward. Verified: `php artisan test --filter=ListEndpointUnionParityTest` → 3/3 passing (9 assertions) against the pre-Task-4 controller code, confirming Step 2's expectation ("likely PASS already, correctness was never broken pre-change, only p95 at scale").

Deleted `DebugListOrderTest.php` (pure investigation scratch, asserts only `assertTrue(true)`, not part of the plan's deliverables).

## Steps 3-7 (completed by controller)

**Step 3** — Replaced `EngineRequestController::index()` with the brief's corrected Step 3 code: `SYSTEM_ADMIN` branch kept byte-identical (plain query, `orderByDesc('created_at')`, no union — that role has no stage filter to union across), non-admin branch now builds a per-stage `$branchFactory` closure (`withStageEntry()` → `forUser($user)` → single-stage `where` → `applyFilters()`) and calls `UnionStagePaginator::paginate()` with a `[['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']]` sort spec, then `$page->load([...])` for the same eager-load relations `index()` originally loaded via `->with()` (deliberately NOT copying `myQueue()`'s eager-load list, which additionally includes `claimedBy` — `index()` never loaded that relation). No import changes needed — `App\Support\UnionStagePaginator` and `Illuminate\Database\Eloquent\Builder` were already present from Task 3.

**Step 4** — `php artisan test --filter=ListEndpointUnionParityTest`: 3 passed (9 assertions), 0 failed.

**Step 5** — `php artisan test --filter=EngineSearchTest`: 7 passed (20 assertions), 0 failed — no regression to bank-scoping, search, or date-range filtering.

**Additional verification (not in the brief)** — Given Task 3's review found real bugs in `UnionStagePaginator` (missing `withStageEntry()` in hydration, bare-array empty-paginator breaking `->load()`) that only surfaced via the full Engine test suite, ran `php artisan test tests/Feature/Engine/` as an extra check since `index()` shares the same `UnionStagePaginator` class and `->load()` call pattern as `myQueue()`: **273 tests, 956 assertions, exit code 0** — no failures (up from Task 3's post-fix baseline of 270/947 by exactly 3 new tests). Confirms Task 3's Collection-type fix already covers `index()`'s empty-result path too.

**Step 6** — `vendor/bin/pint app/Http/Controllers/Api/V1/EngineRequestController.php tests/Feature/Engine/ListEndpointUnionParityTest.php --test` → passed on first pass, no fixes needed.

**Step 7** — Committed `bdae5f4d` (signed, conventional format, scope `backend`, co-authored by Claude). Files: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php` (modified), `backend/tests/Feature/Engine/ListEndpointUnionParityTest.php` (new).

## Test results

| Command | Result |
|---|---|
| `ListEndpointUnionParityTest` (pre-change, post fixture-fix) | PASS — 3 tests, 9 assertions |
| `ListEndpointUnionParityTest` (post-change) | PASS — 3 tests, 9 assertions |
| `EngineSearchTest` | PASS — 7 tests, 20 assertions |
| `tests/Feature/Engine/` full | PASS — 273 tests, 956 assertions, exit 0 |
| Pint | passed, no fixes needed |

## Commit

- Hash: `bdae5f4d`
- Message: `perf(backend): wire UnionStagePaginator into engine-requests list (DB-002)`
- Signed and verified.

## Concerns

None regarding the code itself — task review (dispatched separately) confirmed spec compliance on every checkable point: `SYSTEM_ADMIN` branch byte-identical, non-admin branch preserves filter/scope/order semantics, eager-load list correctly matches `index()`'s original (not `myQueue()`'s), test genuinely exercises the union path. The review's one Important finding was that this report file (at review time) still held stale WP-14 content rather than this task's actual report — now corrected. If this file reverts again, treat the git-committed backend code (commit `bdae5f4d` and its ancestors) as the source of truth; this report is regenerable from that history.
