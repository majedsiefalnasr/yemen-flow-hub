# WP-12 Task U-1 Fix Report — Review findings (c1ae1c2c)

**Status:** Complete  
**Branch:** `worktree-wp12-runtime-ux`  
**Commit:** `fix(workflow): address WP-12 U-1 review findings`

## Findings addressed

| # | Finding | Fix |
|---|---------|-----|
| 1 | `store.stats` race when parallel `loadStats` calls finish out of order | Removed mutable `store.stats`; KPIs derive from `queueStats` / `allStats` via `viewScopedStats` |
| 2 | Stage/bank/workflow faceted filters client-only | Wired `stage_id`, `bank_id`, `workflow_version_id` through `buildListParams()` → `EngineRequestListQuery` |
| 3 | Search placeholder promised stage/bank/merchant search | Placeholder aligned to server fields: «بحث بالمرجع أو رقم الفاتورة» |
| 4 | `workflows-index.test.ts` static grep only | Restored behavioral mount test verifying `fetchList` receives `search` after debounce |

## Test summary

| Suite | Result |
|-------|--------|
| `backend/tests/Feature/Engine/EngineRequestStatsTest.php` | **3/3 pass** |
| `frontend/.../workflows-index.test.ts` | **1/1 pass** |
| `frontend/.../useEngineRequestStats.test.ts` | **1/1 pass** |

## Notes

- Faceted filter option labels still derive from the current page rows (for discoverability); filter **values** are numeric IDs sent to the API.
- Removed client `filterFn` on stage/bank/workflow columns; server-side `manualFiltering` handles filtering.
