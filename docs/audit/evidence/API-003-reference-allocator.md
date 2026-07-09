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

## Residual — concurrency contention not addressed

The finding's second concern — "every create recomputes MAX and races other creators, resolved only by unique-constraint retry — serialization contention under concurrent load" — is **not fixed here**. The numeric-cast MAX fix corrects the overflow/correctness bug; it does not change the recompute-per-create pattern or add a dedicated sequence row/table. The finding's own recommendation offered the numeric-cast fix specifically as the lower-risk option ("casting keeps schema unchanged but still recomputes MAX") versus a per-year sequence-table allocator (bigger change, deterministic under contention, "Decide with Block 3 evidence"). Given no production contention evidence exists (pre-production, no real traffic), the schema-changing allocator is not justified here — this fix closes the correctness defect (the part with a concrete, reproducible failure) without the larger, harder-to-roll-back migration. Flagged as an explicit residual, not silently dropped.
