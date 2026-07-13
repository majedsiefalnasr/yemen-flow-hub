# API-004 — Async audit-log CSV export

## Scoping decision

The endpoint had zero live frontend callers (`exportEngineAuditLogs()` existed in `useAudit.ts` but nothing invoked it from any page/component) and was already row-bounded (`limit(10000)`). Given no UI depended on the synchronous contract, the user was asked whether to (a) minimal-fix with `lazy()`/cursor while staying synchronous, (b) build the full async job per the original recommendation, or (c) defer as Threshold-gated. **Chosen: (b), the full async job** — matching the finding's original recommendation exactly, plus wiring the frontend composable so the endpoint isn't left orphaned.

## What changed

### Backend

- **New job** `backend/app/Jobs/GenerateAuditLogExport.php` — mirrors `GenerateReportExport`'s shape (scope re-derived from the stored requester so the job can't be widened by a stale filters payload; explicit `$tries`/`$timeout`/`backoff()`; fail-closed `failed()`) but streams via `->lazy()` instead of `->get()`, since `audit_logs` is one of the largest unbounded-growth tables (ARCH-006) — holding all matching rows plus the `user` relation in memory at once is the wrong lifecycle even under a row cap.
- **`AuditLogController::export()`** rewritten: creates a `ReportExport` row (`report_type: 'audit-logs'`, reusing the existing generic table — no new migration needed, the table has no `EngineRequest`-specific columns) and dispatches `GenerateAuditLogExport::dispatch()`, returning 201 with the pending export resource instead of CSV bytes.
- **New `AuditLogController::showExport()`/`downloadExport()`** — deliberately *not* routed through the existing `ReportExportController::show()`/`download()`, because those gate on the `reports.VIEW`/`reports.EXPORT` capability while audit-log access uses a separate `audit.VIEW` capability (`AuditLogPolicy`). Branching `ReportExportController`'s shared methods on `report_type` would have mixed two authorization domains into code serving 6+ existing report types — higher regression risk for no benefit. The new methods reuse the exact same ownership-check and status-handling shape, just gated on the correct policy.
- **Route** `GET audit-logs/export` → `POST audit-logs/export` (now creates a resource); added `GET audit-logs/export/{reportExport}` (poll) and `GET audit-logs/export/{reportExport}/download`.

### Frontend

`useAudit.ts`'s `exportEngineAuditLogs()` now: POSTs to create the export, polls `GET .../export/{id}` every 500ms (matches `useReports.ts`'s existing `pollExportUntilComplete` pattern exactly) until `COMPLETED`/`FAILED`, then downloads via blob fetch from `.../export/{id}/download`. New exported functions (`requestAuditLogExport`, `fetchAuditLogExportStatus`, `pollAuditLogExportUntilComplete`, `downloadAuditLogExport`) mirror `useReports.ts`'s existing async-export composable shape for consistency.

## Test evidence

- `backend/tests/Feature/Jobs/GenerateAuditLogExportTest.php` (4 tests): job completes + writes CSV, applies the same range/entity filters as the old sync export (parity), marks FAILED on error, skips non-PENDING exports (idempotency guard against double-dispatch).
- `backend/tests/Feature/Audit/V1/AuditLogFilterPredicatesTest.php` — updated `test_export_applies_the_same_range_and_entity_filters` (previously hit the sync `GET`, now removed since that behavior moved to the job) → replaced with `test_export_creates_a_pending_export_and_dispatches_the_job` (4 tests total, all green).
- `backend/tests/Feature/Audit/V1/AuditLogControllerTest.php` — updated the two tests that exercised the old synchronous contract (`export returns csv...` → `export creates a pending export and audits the creation`; `export forbidden...` → `POST` instead of `GET`). 12 tests, all green.
- `frontend/app/tests/unit/composables/useAudit.test.ts` — 6 new tests for `requestAuditLogExport`, `fetchAuditLogExportStatus`, `pollAuditLogExportUntilComplete` (stops on COMPLETED, stops on FAILED without throwing, times out after `maxAttempts`). 19 tests total, all green.

```
GenerateAuditLogExportTest: 4 tests
AuditLogFilterPredicatesTest: 4 tests
AuditLogControllerTest: 12 tests
useAudit.test.ts: 19 tests (13 pre-existing + 6 new)
```

## Regression check

`tests/Feature/Report/ReportExportTest.php` (9), `ReportExportTruncationTest.php` (2), `Operations/PurgeOldReportExportsTest.php` (5) — all green, confirms `GenerateReportExport`/`ReportExportController` (untouched, shared `ReportExport` table) still work correctly with the new `audit-logs` report_type coexisting in the same table.

Frontend `pnpm typecheck` shows pre-existing errors in `CommandPalette.vue`, `SearchForm.vue`, `DynamicFormField.vue`, `admin/health.vue`, `notifications.vue`, `reports/index.vue`, and several unrelated test files — confirmed none reference `useAudit` (grep: 0 matches); this is the known-red baseline, not a regression from this change.

## Residual

`ReportExportController::index()` (list-my-exports) does not surface `audit-logs` exports — it queries the same `report_exports` table without a `report_type` filter, so audit-log exports would actually already appear there if a UI called it. Not adjusted since neither `index()` nor a "my exports" audit-log UI currently exists; left as-is, no code change needed to keep it working once a UI is built.
