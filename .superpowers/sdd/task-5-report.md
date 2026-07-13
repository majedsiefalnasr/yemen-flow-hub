# Task 5: Confirm the process rail degrades gracefully with filtered graph data

**Status:** DONE

**Plan:** `docs/superpowers/plans/2026-07-14-per-request-stage-visibility.md`

---

## Summary

Added regression tests confirming that `buildStagePath()` in `frontend/app/composables/useEngineStagePath.ts` already handles filtered/redacted inputs safely without requiring any implementation changes.

---

## Work Done

### Step 1: File Review
- Read `frontend/app/tests/unit/composables/useEngineStagePath.test.ts` (existing test file) — confirmed fixture style and existing test patterns
- Read `frontend/app/composables/useEngineStagePath.ts` (implementation) — verified the brief's claims:
  - Line 26: Safe optional chaining on `entry.to_stage?.id` already handles `null to_stage`
  - Line 20: Direct iteration over `graph.nodes` means filtered/shorter arrays produce fewer steps, not errors
- Confirmed `EngineHistoryEntry` type at `frontend/app/types/models.ts:886-896` has both required fields: `restricted: boolean` and `restricted_label: string | null`

### Step 2: Test Cases Added

Appended two new test cases to the existing `describe('buildStagePath')` block:

1. **"omits a step for a stage the graph no longer includes (filtered by backend access control)"**
   - Simulates backend Task 3 filtering: only DATA_ENTRY (id=1) and REVIEW (id=2) nodes returned; COMPLETED node absent
   - Calls `buildStagePath(g, 2, [])` with current stage = 2
   - Asserts: length is 2, stages appear in correct order [1, 2]
   - Verifies: shorter node list produces fewer steps, not an error

2. **"does not throw when a history entry references a stage absent from the filtered graph"**
   - Simulates backend Task 2 redaction: history entry with `from_stage: null`, `to_stage: null`, `restricted: true`
   - Graph contains only DATA_ENTRY (id=1)
   - Calls `buildStagePath(g, 1, sanitizedHistory)` 
   - Asserts: no exception thrown
   - Verifies: null `to_stage` is safely handled by optional chaining

### Step 3: Test Results

```
Test Files  1 passed (1)
     Tests  9 passed (9)
  Start at  01:41:47
   Duration  299ms
```

All 9 tests pass, including both new cases (tests 8 and 9):
- Test 1: "orders nodes by sort_order and marks visited/current/upcoming" ✓
- Test 2: "prefers display_label over name" ✓
- Test 3: "marks history-visited stages even when sort_order is unusual" ✓
- Test 4: "returns [] for a null graph" ✓
- Test 5: "marks only the current stage as "yours" when the user can execute it" ✓
- Test 6: "does not mark the current stage when the user cannot execute it" ✓
- Test 7: "marks every stage in execute_stage_ids as "yours", not just the current one" ✓
- **Test 8: "omits a step for a stage the graph no longer includes (filtered by backend access control)" ✓ [NEW]**
- **Test 9: "does not throw when a history entry references a stage absent from the filtered graph" ✓ [NEW]**

### Step 4: Lint & Format

- ESLint: passed with zero errors
- Prettier: failed initially on formatting; ran `--write` and passed on re-check
- Final verification: all 9 tests still pass after formatting

### Step 5: Commit

```
[feat/per-request-stage-visibility 3c4b30e5]
test(workflow): cover process rail against filtered graph and redacted history

Confirms buildStagePath already degrades gracefully when the backend
omits inaccessible stages (Task 3) or returns a sanitized history entry
with a null to_stage (Task 2) — no rail code changes required.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
```

**Hash:** `3c4b30e5`

**File changed:**
- `frontend/app/tests/unit/composables/useEngineStagePath.test.ts` (+71 lines)

---

## Verification

**Test Results:**
- All 9 useEngineStagePath tests pass (9/9)
- Both new test cases pass immediately without implementation changes
- ESLint: zero errors
- Prettier: formatting check PASSED

**Commits:** 1 (signed, conventional format, co-authored)

**Notes:**
- No implementation changes required—the existing code is already resilient to filtered graphs and null to_stage values
- Tests confirm the plan's design analysis prediction: `buildStagePath()` handles both scenarios safely via existing code
- No concerns or edge cases revealed by testing
