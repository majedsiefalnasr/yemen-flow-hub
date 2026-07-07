# WP-14 Task 1 Report — V1 Report Presets API

**Status:** Complete  
**Branch:** `worktree-wp14-legacy-cleanup`  
**Date:** 2026-07-07

## Summary

Added canonical `/api/v1/report-presets` endpoints (list, save, delete) mirroring legacy `ReportPresetsController` behavior. Presets remain user-scoped via `users.user_preferences.report_presets` JSON storage. Legacy `/api/report-presets` routes are **unchanged** (migration-first).

## TDD sequence

1. Created `ReportPresetTest` with list + save/delete cases — 2 tests failed with HTTP 404 (red).
2. Implemented `ReportPresetController` + V1 routes — 2 tests passed (green).

## Files changed

| File | Change |
|------|--------|
| `backend/app/Http/Controllers/Api/V1/ReportPresetController.php` | New V1 controller; copies legacy preset CRUD; rich R9 envelope on 422 validation |
| `backend/routes/api.php` | Register GET/POST/DELETE under `v1` group after report exports block |
| `backend/tests/Feature/V1/ReportPresetTest.php` | Feature tests for list, save, delete |

## API contract

| Method | Path | Response |
|--------|------|----------|
| `GET` | `/api/v1/report-presets` | `{ success, message, data: ReportPreset[] }` |
| `POST` | `/api/v1/report-presets` | `{ success, message, data: ReportPreset[] }` |
| `DELETE` | `/api/v1/report-presets/{id}` | `{ success, message, data: ReportPreset[] }` |

Validation errors (422): `{ error: { code, message, fields, request_id } }`.

**Data scope:** Not applicable — presets are read/written only on `$request->user()->user_preferences`; no cross-user or org-wide query.

## Unchanged (by design)

- `backend/app/Http/Controllers/Api/ReportPresetsController.php` — legacy controller retained
- Legacy routes at `/api/report-presets` — still registered for `useReports.ts` until Task 6

## Verification

```bash
cd backend && php artisan test tests/Feature/V1/ReportPresetTest.php
```

**Result:** 2 tests, 6 assertions — PASS (PDO deprecation warnings only; known PHP 8.5 baseline).

```bash
cd backend && php artisan route:list --path=report-presets
```

**Result:** 6 routes — 3 legacy + 3 V1.

```bash
cd backend && vendor/bin/pint --test \
  app/Http/Controllers/Api/V1/ReportPresetController.php \
  tests/Feature/V1/ReportPresetTest.php \
  routes/api.php
```

**Result:** PASS

## Commit

See git log for `feat(backend): add V1 report presets endpoints`.
