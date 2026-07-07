# WP-14 Task 5 Report — Migrate useAudit to V1 audit-logs API

**Status:** Complete  
**Branch:** `worktree-wp14-legacy-cleanup`  
**Date:** 2026-07-07

## Summary

Migrated the audit page and `useAudit` composable from legacy `/api/audit*` to canonical `/api/v1/audit-logs`. Dropped legacy KPI/duplicates/risk widget panels per L-1 default (`AUDIT_LEGACY_WIDGETS=false`). `fetchAuditLogs` now delegates to the V1 engine endpoint and maps `EngineAuditLog` → `AuditLog` for the existing table UI. Legacy backend routes remain untouched (migration-first).

## TDD sequence

1. Updated `useAudit.test.ts` to expect `/api/v1/audit-logs` and V1 response shape — 2 tests failed (red).
2. Added `audit.test.ts` legacy-widget absence test — failed before page changes.
3. Implemented composable migration, page cleanup, visual bypass stub, `nuxt.config` flag — 44 tests green (2 skipped).

## Files changed

| File | Change |
|------|--------|
| `frontend/app/composables/useAudit.ts` | `fetchAuditLogs` → V1 via `fetchEngineAuditLogs`; filter mapping (`user_id`→`user`, `action`→`event`, dates→`from`/`to`); removed `fetchAuditStats`, `fetchDuplicates`, `fetchRiskIndicators` |
| `frontend/app/pages/audit.vue` | Dropped KPI grid, duplicates tab, risk tab; simplified `onMounted` to `loadAuditLogs` only; subtitle updated |
| `frontend/nuxt.config.ts` | `runtimeConfig.public.auditLegacyWidgets` from `AUDIT_LEGACY_WIDGETS` env (default `false`) |
| `frontend/app/plugins/00.visual-bypass-api.client.ts` | Stub `/api/v1/audit-logs` instead of `/api/audit*` |
| `frontend/app/tests/unit/composables/useAudit.test.ts` | V1 path/shape tests; removed legacy endpoint suites |
| `frontend/app/tests/unit/pages/audit.test.ts` | Legacy widget absence test; removed stats mock wiring |
| `frontend/app/tests/setup.ts` | Default `auditLegacyWidgets: false` in runtime config stub |

## Unchanged (by design)

- `fetchEngineAuditLogs`, `fetchEngineAuditLogDetail`, `exportEngineAuditLogs` — already V1; kept as-is.
- Anomalies tab — derived from loaded log rows (no legacy API).
- `backend/routes/api.php` — legacy `AuditController` routes retained for migration-first rollout.

## Removed

- `fetchAuditStats`, `fetchDuplicates`, `fetchRiskIndicators` exports and related types from composable.
- Legacy widget UI: KPI metric grid, duplicates tab, risk indicators tab.

## Verification

```bash
cd frontend && pnpm exec vitest run \
  app/tests/unit/composables/useAudit.test.ts \
  app/tests/unit/pages/audit.test.ts
```

**Result:** 2 files, 44 passed | 2 skipped.

```bash
pnpm exec eslint app/composables/useAudit.ts app/pages/audit.vue \
  app/plugins/00.visual-bypass-api.client.ts \
  app/tests/unit/composables/useAudit.test.ts \
  app/tests/unit/pages/audit.test.ts
```

**Result:** PASS (zero warnings).

```bash
rg "'/api/audit|\"/api/audit" frontend/app frontend/tests
```

**Result:** No legacy `/api/audit` consumer references remain.

## Commit

`refactor(frontend): migrate audit page to V1 audit-logs`
