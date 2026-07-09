# API-007 + DB-003 — Index-defeating audit/report filter predicates

## What changed (API-007, code fix)

Replaced `whereDate()` (wraps `created_at` in `DATE()`, defeating any index) with half-open range bounds (`>= from.startOfDay()` AND `< to.addDay().startOfDay()`) — the same pattern already applied to `EngineRequestListQuery` for ARCH-004:

- `AuditLogController::index()` and `::export()` — `from`/`to` filters on `audit_logs.created_at`.
- `ReportController::applyFilters()` (shared by `summary`, `by-workflow-stage`, `by-bank`, `by-currency`, `sla`) — `from`/`to` filters on `engine_requests.created_at`.
- `ReportController::stageDuration()` — `from`/`to` filters on `h1.created_at` (a `workflow_history` join alias).

Replaced infix `subject_type LIKE '%X%'` (also in `AuditLogController::index()`/`::export()`) with an exact match (`subject_type = ?`). Per the finding's own recommendation ("exact `subject_type = ?` (fully-qualified class)"), the caller now passes the full class name (e.g. `App\Models\EngineRequest`) rather than a basename fragment. No frontend caller sends this filter today (verified: `entity` appears only in the `useAudit.ts` type declaration, never populated or sent) — no client contract to coordinate.

## What changed (DB-003, index)

New migration `2026_07_09_100004_add_subject_created_index_to_audit_logs.php` adds `al_subject_created` on `audit_logs(subject_type, created_at)` — gated on the API-007 code fix per the finding ("Only after switching to exact `subject_type = ?` + range dates... Unused without the code fix"), which is now in place.

```php
Schema::table('audit_logs', function (Blueprint $table): void {
    $table->index(['subject_type', 'created_at'], 'al_subject_created');
});
```

Migration tested: `migrate` → verified index present (`SHOW INDEX`) → `migrate:rollback` → verified index dropped → re-migrated to leave the final state applied.

## EXPLAIN evidence

`docs/audit/evidence/explain/DB-003-audit-entity-date-range.txt` — captured against the local dev DB (`cby_imports`, 257 `audit_logs` rows, per `00-scope-and-method.md`'s known evidence limitations: local/Docker, disk-bound, plan shape is the primary evidence, not absolute timing):

- Default plan: optimizer picks the pre-existing `(subject_type, subject_id)` index over the new one at this row count (cost-based choice on a near-empty table — expected and non-alarming; the old index is 1 rows to scan either way).
- `FORCE INDEX (al_subject_created)`: `type: range`, `Using index condition`, `filtered: 100.00` — confirms the new index is structurally correct and usable for the entity+date-range shape. At design-target row counts (`audit_logs` is one of the largest tables per ARCH-006), the composite range index becomes the cost-favored choice since it lets MySQL seek directly within the date range for a given entity, rather than scanning all rows for that entity and filtering dates.

## Test evidence

- `backend/tests/Feature/Audit/V1/AuditLogFilterPredicatesTest.php` (4 tests): `to` filter inclusive of the whole day, `from` filter excludes earlier days, `entity` filter matches exact `subject_type` only (proves a substring-colliding class like `EngineRequestDocument` no longer false-matches `EngineRequest`), `export()` applies the same range+entity filters.
- `backend/tests/Feature/Report/V1ReportsTest.php` — added `test_stage_duration_to_filter_is_inclusive_of_the_whole_day` (new coverage; `stage-duration`'s date filter had no prior test).

```
AuditLogFilterPredicatesTest: 4 tests, 12 assertions
V1ReportsTest: 10 tests (1 new), 29 assertions
```

## Regression check

`tests/Feature/Audit/V1/AuditLogControllerTest.php` (12 tests, including the pre-existing `index filters by date range`) — all green, confirms the range-bound rewrite is behavior-compatible for the already-tested date-filter path.

## Residual

Only the `from`/`to`/`entity` filters were addressed (API-007's explicit scope). `ip`/`correlation_id`/`user`/`role`/`event`/`request` filters were already exact-match and untouched. `ReportController::sla()`'s unbounded `->get()` and `stageDuration()`/`teamPerformance()`'s lack of a default date window are API-006's scope, not this fix's — left open for that task.
