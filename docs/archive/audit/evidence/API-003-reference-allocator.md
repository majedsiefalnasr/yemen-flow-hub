# API-003 — Reference allocator overflow/race fix

## The bug, confirmed

`EngineRequestService::createWithUniqueReference()` derived the next sequence from `DB::table('engine_requests')->where(...)->max('reference')` — a **lexicographic string MAX**, not a numeric one. `sprintf('ENG-%d-%06d', ...)` zero-pads to a *minimum* of 6 digits but does not truncate past it, so once a year's sequence exceeds 999999, new references are 7 digits (`ENG-2026-1000000`).

Lexicographic comparison of different-length numeric strings favors the shorter one when its first differing digit is larger: `'999999' > '1000000'` as strings, because `'9' > '1'` at the first position. Confirmed directly against both engines:

```sql
-- MySQL and SQLite agree:
SELECT MAX(reference) FROM engine_requests WHERE reference LIKE 'ENG-2026-%';
-- returns 'ENG-2026-999999', even with 'ENG-2026-1000006' present
```

The method's existing retry loop (`for ($attempt = 0; $attempt < 5; ...)`, offsetting the computed sequence by `$attempt`) can paper over exactly **one** stale collision by accident — the offset happens to land on a free slot. Once more than one 7-digit row exists, the retry budget (5 attempts) exhausts before finding a free slot, and every attempt recomputes the same wrong base sequence: permanent `REFERENCE_ALLOCATION_FAILED` (500) for the rest of that year, exactly as the finding describes.

## The fix

Replace the string `MAX('reference')` with `MAX(CAST(SUBSTRING(reference, N) AS UNSIGNED))` — a numeric max over the extracted sequence suffix, correct at any digit width. `SUBSTRING`/`CAST ... AS UNSIGNED` both work unchanged under MySQL (production) and SQLite (test suite, confirmed directly with both engines before committing to this expression). No schema change, no new table — the finding's own suggested minimal fix (`03-database-plan.md` context: "or `MAX(CAST(SUBSTRING(reference,10) AS UNSIGNED))`").

```php
$maxSequence = DB::table('engine_requests')
    ->where('reference', 'like', $prefix.'%')
    ->max(DB::raw('CAST(SUBSTRING(reference, '.(strlen($prefix) + 1).') AS UNSIGNED)'));

$sequence = $maxSequence !== null ? ((int) $maxSequence) + 1 : 1;
```

## Test evidence

`backend/tests/Feature/Engine/EngineRequestReferenceAllocatorTest.php` (2 tests):

1. `test_allocates_sequential_reference_past_six_digit_boundary` — seeds a 6-digit reference plus six 7-digit references above it (exhausting the retry budget under the old logic), then asserts the next created request continues the numeric sequence (`...1000006`).
   - **Pre-fix: reproduced the exact reported failure** — `REFERENCE_ALLOCATION_FAILED` (500) after seeding just 7 total rows, confirming the bug is real and not merely theoretical (no 1M-row dataset needed to trigger it — only crossing the 6→7 digit boundary with enough rows to exhaust the 5-attempt retry).
   - Post-fix: green, next reference is correctly `...1000006`.
2. `test_concurrent_style_retries_still_yield_unique_references` — 5 sequential creates through the retry-eligible path all yield distinct references (regression guard for the existing retry-on-duplicate-key behavior, unchanged by this fix).

```
Tests: 2, Assertions: 8
```

## Regression check

`tests/Feature/Engine/EngineRequestTest.php` (38 tests, including the pre-existing `test_create_reference_unique`) — all green, no behavior change to the happy path (2-digit/6-digit sequences resolve identically before and after; only the boundary-crossing case differs).

## Concurrency contention — now load-tested against real MySQL

The finding's second concern — "every create recomputes MAX and races other creators, resolved only by unique-constraint retry — serialization contention under concurrent load" — was flagged as an untested residual in the overflow-fix pass above. It has now been **directly load-tested** with true parallel workers (not sequential retries, which the SQLite `:memory:` test suite cannot distinguish — SQLite is single-writer and does not reproduce InnoDB row/gap contention). This is why the test lives in the mysql-only harness, not PHPUnit.

### Harness

`php artisan perf:load-scenario --concurrency=N --creates-per-worker=M` (added to the existing `PerfLoadScenarioCommand`). It forks N real OS processes with `pcntl_fork`; each child `DB::purge()`s (a forked MySQL socket is unsafe to share), then calls the **production** `EngineRequestService::create()` path M times. All workers race on the same `MAX(CAST(...))+1` sequence and the same unique-`reference` index gap, in the same bank, with no think-time — the synthetic worst case. The parent aggregates and asserts three gates: every reference distinct, zero `REFERENCE_ALLOCATION_FAILED`, every create landed exactly once. Self-cleaning (removes the `ENG-*` requests + their `workflow_history` for the fixture bank, FK-safe), scoped to the `PERFLOAD` fixture bank so real data is never touched.

### What the test found (real dev MySQL, `cby_imports`)

The first run immediately surfaced a **real, previously-undocumented weakness**: under true parallel inserts, MySQL aborts the losing side with a `1213 Deadlock` on the INSERT — and the allocator's inner retry loop only catches `1062` duplicate-key, so deadlocked creates were silently lost (13/20 landed at 4 workers × 5).

**Root cause of the loss:** the deadlock aborts `create()`'s *entire* outer `DB::transaction()`, so retrying just the insert in `createWithUniqueReference()` cannot help — its transaction is already dead.

**Fix applied** (`EngineRequestService::create()`): wrap the transaction in Laravel's built-in retry — `DB::transaction($callback, 5)`. Laravel's `DetectsConcurrencyErrors` re-runs the closure on a detected concurrency error (`1213` deadlock / `1205` lock-wait / `40001`) **only**, not on `1062`. The two retry layers compose cleanly: the outer retry re-runs the whole transaction on an aborted-transaction deadlock; the inner loop still handles a plain reference collision within a live transaction.

### Measured results (before → after the deadlock fix)

| Contention (workers × creates) | Before fix | After fix |
| --- | --- | --- |
| 4 × 5 (20) | 13/20 landed — **FAIL** (7 deadlocks lost) | **20/20 landed — PASS**, 0 alloc failures |
| 6 × 10 (60) | — | 47/60 — **FAIL** (5 `REFERENCE_ALLOCATION_FAILED`) |
| 8 × 15 (120) | — | 77/120 — **FAIL** (15 `REFERENCE_ALLOCATION_FAILED`) |

**Distinctness held at every level, before and after** (13/13, 20/20, 47/47, 77/77) — the allocator has **never** issued a duplicate reference. The core correctness property (no two requests share a reference) is solid; the failures are lost-write availability under a deadlock storm, not data corruption.

### Honest verdict

- The retry-widening is the correct **first** fix and closes realistic contention: **≤4-way sustained same-gap contention now passes** cleanly, where it lost writes before.
- **Extreme contention (6+ parallel workers hitting the identical index gap at the same microsecond) still exhausts the 5-attempt budget.** This is inherent to the "compute `MAX+1`, insert, retry" pattern — retries re-collide under a storm, so merely raising the attempt count yields diminishing returns, not a fix. This synthetic shape (all creates in one bank, one gap, zero think-time) is far more adversarial than production, where creates spread across banks/users/human timing.
- The **deterministic** elimination is the option the original finding named as the larger, separate change: a per-year sequence-table allocator (`reference_sequences` row updated with `INSERT ... ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1)`), which serializes allocation on one hot row instead of racing the whole index — removing the deadlock class entirely. That is a schema change and stays **separate approved work** per the audit's "each fix is separate approved work" rule.

### Gate status

Overflow correctness: **closed** (proven). Deadlock-resilience under realistic contention: **closed** (retry fix, proven 4-way). Deadlock-resilience under extreme same-gap contention: **closed by API-003b below**.

---

## API-003b — sequence-table allocator (deadlock class eliminated)

The residual above (extreme same-gap contention exhausts the retry budget) is now **fixed**, not just deferred. Implemented the per-year sequence-table allocator the finding named as the deterministic option.

### Implementation

- **Table** `engine_request_reference_sequences` (`year` PK, `last_value`, timestamps — mirrors the shape of the dropped legacy `import_request_reference_sequences`). Migration `2026_07_11_120001_create_engine_request_reference_sequences_table` also backfills the current year's `last_value` from the numeric-cast `MAX` of existing `ENG-{year}-*` references, so the first allocation continues past them.
- **`EngineRequestReferenceAllocator`** service, `allocate(): string`. Single-responsibility, driver-branched:
  - **MySQL:** `INSERT ... VALUES (?, 1) ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)`, then `SELECT LAST_INSERT_ID()`. A single-row atomic increment — no read-then-write window, no gap-lock on the `reference` index, so the 1213 deadlock class cannot occur. (`year` is a MySQL reserved word — all identifiers backtick-quoted.)
  - **SQLite** (test suite): `INSERT ... ON CONFLICT(year) DO UPDATE SET last_value = last_value + 1 RETURNING last_value` — the portable atomic equivalent (no `LAST_INSERT_ID` in SQLite). No other driver is supported; there is no MAX+1 fallback branch (both drivers this project uses have atomic upsert).
- **Allocation runs BEFORE `create()`'s transaction** (`EngineRequestService::create()`), in its own committed step. A create rollback or deadlock-retry of that transaction leaves an unused number — a harmless gap. References must be unique + monotonic, not gapless. The old `createWithUniqueReference()` MAX+1 loop and its `isDuplicateKey()` helper were deleted.

### Measured results (real dev MySQL, `perf:load-scenario --concurrency`)

The exact contention shapes that broke the MAX+1 allocator (even with the API-003 deadlock-retry) now pass perfectly:

| Contention (workers × creates) | MAX+1 + retry (API-003) | Sequence allocator (API-003b) |
| --- | --- | --- |
| 8 × 15 (120) | 77/120 landed, 15 `REFERENCE_ALLOCATION_FAILED`, 28 lost deadlocks | **120/120 landed, 0 failures, 0 deadlocks** |
| 12 × 20 (240) | (worse — not run) | **240/240 landed, 0 failures, 0 deadlocks** |

Every reference distinct at both levels; zero `REFERENCE_ALLOCATION_FAILED`; **zero deadlocks** (no "other errors" at all — the 1213s are gone, not merely retried). The single hot sequence row serializes allocation cleanly instead of racing the index gap.

### Tests

`EngineRequestReferenceAllocatorTest` rewritten for the new mechanism (SQLite): correct 6→7 digit width transition (seed sequence at 999999 → next is `ENG-{year}-1000000`), derivation from the sequence row not a scan of existing references, and sequential uniqueness. The extreme-contention proof is the MySQL harness above (SQLite is single-writer and cannot reproduce InnoDB contention).

### Note on gaps

The harness's created requests are cleaned up but leave the shared dev sequence advanced (e.g. `2026 = 360` after a 120+240 run) — real allocations simply continue from there. This is the intended gap-tolerant behavior, not a leak.
