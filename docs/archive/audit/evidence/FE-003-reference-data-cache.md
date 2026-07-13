# FE-003 — Client cache for stable reference data

## What changed

New `frontend/app/stores/referenceCache.store.ts` — a small Pinia store providing `remember(key, fetcher, ttlMs = 5min)`, `invalidate(key)`, and `clear()`. `useBanks().fetchBanks()` (the read-only, unfiltered "all banks for a dropdown/selector" helper) now routes through `remember('banks:dropdown', ...)`; `createBank()`/`updateBank()` call `invalidate('banks:dropdown')` on success so a caller that just mutated a bank never sees a stale dropdown on its next read.

## Why `useBanks().fetchBanks()` specifically (not the other candidates investigated)

The finding named `useReferenceData.ts` "and peers" without pinning down which composables were the actual repeat-fetch offenders. Investigated before writing any code:

- **`useReferenceData.ts`** (admin CRUD for reference tables/values — paginated, searchable, sortable, with create/update/delete/activate) — **not** cached. It's a live-editing admin screen, not a stable lookup; every call is already parameterized by page/search/sort, so there's no repeated identical fetch to deduplicate, and caching an actively-mutated list would risk showing stale rows right after an edit.
- **`useGovernanceBanks.ts`** — a separate, full CRUD composable for banks (create/update/delete/activate) used by its own admin surface. Also not cached, same reasoning as above.
- **`useBanks().fetchBanks()`** — genuinely read-only (no mutation methods on the composable itself besides `createBank`/`updateBank`, which exist for the caller that also happens to manage banks). Confirmed via grep that it's called independently, with no shared cache, from three separate pages/components in the same session: `IdentityUsersPage.vue`, `merchants.vue`, and `admin/banks.vue` (there, only for a dropdown/filter — the page's own table uses local `ref` state and `fetchBanksPaginated()`/direct mutation splicing, not `fetchBanks()`, so it was never re-fetching after its own edits anyway). This is the exact "stable reference data refetched every use" pattern the finding describes.

## Why a TTL + explicit invalidation, not just a TTL

Per the finding's own recommendation wording exactly: "a modest TTL **or** explicit invalidation on admin edit." Implemented both — a 5-minute default TTL bounds staleness even if an edit happens through some path that doesn't call `invalidate()`, and explicit invalidation on `createBank`/`updateBank` gives immediate consistency for the one page (`admin/banks.vue`) that both reads and writes banks in the same session.

## Test evidence

`frontend/app/tests/unit/stores/referenceCache.store.test.ts` (6 new tests): cache miss calls the fetcher; cache hit within TTL skips it; TTL expiry re-fetches; separate keys don't collide; `invalidate()` forces a re-fetch; `clear()` invalidates every key.

`frontend/app/tests/unit/composables/useBanks.test.ts` (2 new tests, added to the existing 4): a second `fetchBanks()` call in the same session does not hit the API again; `createBank()` invalidates the cache so the next `fetchBanks()` call does refetch. Confirmed red on unmodified code first (`fetchBanks()`'s "caches the result" test failed — a second identical `GET` was attempted — proving the pre-fix repeated-fetch behavior).

```
referenceCache.store.test.ts: 6 tests, all pass
useBanks.test.ts: 6 tests (2 new), all pass
```

## Regression check

No dedicated component tests exist for the three consuming pages (`IdentityUsersPage.vue`, `merchants.vue`, `admin/banks.vue`). `pnpm typecheck` was run per the verification ladder (shared store + composable contract change) — reports the same pre-existing baseline failures as before this change (confirmed via `git status`, none reference `useBanks.ts` or `referenceCache.store.ts`); no new errors introduced.

## ESLint / Prettier

All touched files pass clean (one `@typescript-eslint/no-dynamic-delete` fix applied in the store: object-rest destructuring instead of `delete obj[key]`).
