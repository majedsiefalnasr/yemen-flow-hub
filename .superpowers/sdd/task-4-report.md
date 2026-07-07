# WP-14 Task 4 Report — Migrate useBanks to V1 banks API

**Status:** Complete  
**Branch:** `worktree-wp14-legacy-cleanup`  
**Date:** 2026-07-07

## Summary

Migrated the `useBanks` composable and related frontend stubs from legacy `/api/banks` to canonical `/api/v1/banks` per WP-14 L-2 (D23-N3). Removed unused `resetBankAdminPassword` (legacy-only endpoint with no page consumers). Legacy backend routes remain untouched (migration-first).

## TDD sequence

1. Updated `useBanks.test.ts` expectations to `/api/v1/banks` paths and removed `resetBankAdminPassword` tests — 3 tests failed (red).
2. Updated composable + stubs — 4 tests green.

## Files changed

| File | Change |
|------|--------|
| `frontend/app/tests/unit/composables/useBanks.test.ts` | Expect `/api/v1/banks` on GET/POST/PUT; drop `resetBankAdminPassword` suite |
| `frontend/app/composables/useBanks.ts` | All 4 endpoint paths → `/api/v1/banks`; remove dead `resetBankAdminPassword` |
| `frontend/app/plugins/00.visual-bypass-api.client.ts` | Stub path `/api/v1/banks` |
| `frontend/tests/visual/helpers.ts` | Route mock `/api/v1/banks` |
| `frontend/tests/e2e/account-recovery.spec.ts` | Route mock `/api/v1/banks` |

## Unchanged (by design)

- `admin/banks.vue`, `merchants.vue` — consume `useBanks()` only; inherit V1 paths automatically.
- `CbyAdminPages.test.ts` — mocks `useBanks()` entirely; excluded from default vitest run (known baseline).
- `backend/routes/api.php` — legacy `BankController` routes retained for migration-first rollout.

## Removed

- `resetBankAdminPassword()` — no frontend consumers; legacy `POST /api/banks/{id}/admin/reset-password` not promoted to V1 in this wave.

## Verification

```bash
cd frontend && pnpm exec vitest run app/tests/unit/composables/useBanks.test.ts
```

**Result:** 1 file, 4 tests — all passed.

```bash
pnpm exec eslint app/composables/useBanks.ts \
  app/tests/unit/composables/useBanks.test.ts \
  app/plugins/00.visual-bypass-api.client.ts
```

**Result:** PASS (zero warnings).

```bash
rg "'/api/banks|\"/api/banks" frontend --glob '!node_modules'
rg "resetBankAdminPassword" frontend/
```

**Result:** No legacy `/api/banks` or `resetBankAdminPassword` references remain in frontend.

## Commit

TBD — `refactor(frontend): migrate useBanks to v1 banks API (WP-14)`
