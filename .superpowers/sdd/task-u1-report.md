# WP-12 Task U-1 Report — Server-side lists, filters, KPIs

**Status:** Complete (Phases A, B, C)  
**Branch:** `worktree-wp12-runtime-ux`  
**Date:** 2026-07-07

## Summary

Implemented scoped `GET /api/v1/engine-requests/stats` sharing `EngineRequestListQuery` filters and DataScope visibility with list/queue endpoints. Frontend workflows index now loads KPIs from stats, passes search/status/sla/claimed filters and pagination to the API, and uses server-side DataTable pagination.

## Commits

(Single commit prepared — run `git log -1` after commit lands.)

```
feat(workflow): server-driven workflow list stats and pagination (WP-12 U-1)
```

## Phase A — Stats endpoint

- Added `EngineRequestStatsService` with aggregates: `total`, `active`, `breached_sla`, `nearing_sla`, `unclaimed_active`, `by_status`.
- Added `EngineRequestController::stats` and route `GET engine-requests/stats` (before `{engineRequest}` wildcard).
- Added `Route::pattern('engineRequest', '[0-9]+')` to prevent `"stats"` model-binding collisions.
- Fixed SQLite SLA epoch comparisons (`CAST(... AS INTEGER)`) in `EngineRequest::nowEpochSql` / `epochSql` (pre-existing test bug surfaced by stats SLA counts).
- Added `claimed` filter to `EngineRequestListQuery` for supervisor claim KPI/filter wiring.
- Fixed worktree `tests/TestCase.php` to bootstrap from worktree path (symlinked vendor inferred main `app/` otherwise).

## Phase B — Frontend KPIs

- Added `EngineRequestStats` type and `useEngineRequestStats` composable.
- Extended `useEngineRequests` exported `ListOptions` (`claimed`, `created_from`, `created_to`).
- Store: `stats`, `queueStats`, `allStats`, `loadStats()`.
- `workflows/index.vue`: supervisor/participant KPI cards read `store.stats` / dual-scope stats.

## Phase C — Server pagination & filters

- Removed client `filteredRows` search/KPI math.
- Debounced search → `load()` with `search`, `status`, `sla_status`, `claimed`, `page`, `per_page`.
- DataTable server mode: `v-model:pagination`, `:page-count`, `DataTablePagination :total-rows`.

## Test summary

| Suite | Result |
|-------|--------|
| `backend/tests/Feature/Engine/EngineRequestStatsTest.php` | **3/3 pass** |
| `frontend/.../useEngineRequestStats.test.ts` | **1/1 pass** |
| `frontend/.../workflows-index.test.ts` | **1/1 pass** |
| `pnpm typecheck` (worktree) | **Fail** — pre-existing `is_default_submit` type mismatch (unrelated U-9 work) |
| Full `php artisan test` | Not run (known ~75 unrelated reds per baseline) |

## Concerns / follow-ups

1. **Worktree dev setup:** Symlinked `vendor`, `node_modules`, and `.nuxt` from main repo required for local test runs; `composer install` / `nuxt prepare` in worktree would be cleaner.
2. **SQLite SLA fix** touches `EngineRequest` epoch SQL globally — improves test accuracy; verify MySQL parity in staging (CAST change is SQLite-only branch).
3. **Stage/bank column filters** remain client-side on the current page (name-based faceted filters); server does not yet accept stage/bank name params.
4. **Participant stats** loads queue + all scope stats in parallel on each refresh (2 extra requests); acceptable for v1, could cache until scope/filter change.
5. **API docs** (`docs/06-api-reference.md`) not updated in U-1 — deferred to U-9 gate per plan.

## Files touched

**Backend:** `EngineRequestStatsService.php`, `EngineRequestController.php`, `routes/api.php`, `EngineRequestListQuery.php`, `EngineRequest.php`, `EngineRequestStatsTest.php`, `TestCase.php`

**Frontend:** `useEngineRequestStats.ts`, `useEngineRequests.ts`, `engineRequests.store.ts`, `workflows/index.vue`, `models.ts`, unit tests
