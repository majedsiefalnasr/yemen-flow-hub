# WP-14 Task 1 Report — Migrate useUsers to V1 users API

**Status:** Complete  
**Branch:** `worktree-wp14-legacy-cleanup`  
**Date:** 2026-07-07

## Summary

Migrated the `useUsers` composable and related frontend stubs from legacy `/api/users` to canonical `/api/v1/users` per WP-14 L-2 (D23-N3). Legacy backend routes remain untouched (migration-first).

## TDD sequence

1. Updated `useUsers.test.ts` expectations to `/api/v1/users` paths — 7 tests failed (red).
2. Updated composable + stubs — 12 tests green.

## Files changed

| File | Change |
|------|--------|
| `frontend/app/tests/unit/composables/useUsers.test.ts` | Expect `/api/v1/users` on all GET/POST/PUT/reset calls |
| `frontend/app/composables/useUsers.ts` | All 9 endpoint paths → `/api/v1/users` |
| `frontend/app/plugins/00.visual-bypass-api.client.ts` | Stub path `/api/v1/users` |
| `frontend/tests/e2e/account-recovery.spec.ts` | Route mocks for list + reset endpoints |

## Unchanged (by design)

- `AccountRecoveryDialog.vue` — uses `useUsers()` only; no direct API paths.
- `staff.vue` — consumes `useUsers()`; inherits V1 paths automatically.
- `backend/routes/api.php` — legacy `UserController` routes retained for migration-first rollout.

## Verification

```bash
cd frontend && pnpm exec vitest run \
  app/tests/unit/composables/useUsers.test.ts \
  app/tests/unit/components/security/AccountRecoveryDialog.test.ts
```

**Result:** 2 files, 12 tests — all passed.

```bash
pnpm exec eslint app/composables/useUsers.ts \
  app/tests/unit/composables/useUsers.test.ts \
  app/plugins/00.visual-bypass-api.client.ts
```

**Result:** PASS (zero warnings).

```bash
rg '/api/users' frontend --glob '!node_modules'
```

**Result:** No legacy `/api/users` references remain in frontend.

## Commit

```
refactor(frontend): migrate useUsers to v1 users API (WP-14)
```
