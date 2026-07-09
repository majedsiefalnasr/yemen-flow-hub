# ARCH-006 — audit_logs / workflow_history archival gaps

## Starting state (found before writing anything)

`audit_logs` archival already existed and was already scheduled: `AuditLogArchiveService::archiveBatch()`, `ArchiveOldAuditLogsCommand` (`audit:archive-old`, daily 03:00, heartbeat-monitored via `RecordsSchedulerHeartbeat`/`CheckSchedulerHealthCommand`), moving rows into `audit_log_archives` and self-auditing via `AUDIT_ARCHIVED`. This finding's own recommendation ("define a retention/archival policy... Block 3 designs this with rollback") was already substantially implemented pre-audit-remediation.

Two real gaps remained, both confirmed by reading the actual code before writing any fix:

1. `audit_log_archives` predates SEC-002's `audit_logs.bank_id` column. `AuditLogArchiveService::archiveBatch()` never copied it, so a row's bank scoping was silently lost the moment it archived.
2. `workflow_history` had no archival at all — confirmed via `grep` across `app/Services/Operations/` and `app/Console/Commands/`, nothing referenced it.

## Gap 1 — `audit_log_archives.bank_id`

- Migration `2026_07_09_100006_add_bank_id_to_audit_log_archives.php` adds the column + `ala_bank_archived (bank_id, archived_at)` index, and backfills existing archived rows using the exact same subject-resolution rule as `AuditService::resolveBankId()` / the original SEC-002 backfill (`User`/`Merchant`/`EngineRequest` → their own `bank_id`; `Bank` → its own id; anything else → `null`, never guessed from the acting user). Chunked by `id` range (5000/batch).
- `AuditLogArchive` model: added `bank_id` to `$fillable`.
- `AuditLogArchiveService::archiveBatch()`: the row map now carries `bank_id` from the source row into the archive insert.
- Verified against the real dev DB (`cby_imports` via `yfh-mysql`): migration applied, `SHOW CREATE TABLE` confirmed the column + index, rolled back, re-applied — clean round trip. No pre-existing archived rows existed to backfill (`audit_log_archives` was empty), so the backfill loop's no-op path was exercised, not its data path — the same resolution logic was already proven correct by SEC-002's own backfill against 258 real rows.

## Gap 2 — `workflow_history` archival (new)

**Design constraint that shaped the implementation:** `EngineRequest::withStageEntry()` (used by `ReportController::sla()`) computes the current stage's entry time via a correlated subquery against `workflow_history` for the request's *current* stage, and `ReportController::stageDuration()` joins consecutive `workflow_history` rows per request to compute per-stage duration. Both depend on a request's own history rows being present while that request is still active. Archiving early would silently corrupt live SLA/report data — so a `workflow_history` row is only eligible for archival once its **owning `engine_request.status != 'ACTIVE'`**, in addition to being past the hot-retention horizon.

- New table `workflow_history_archives` (migration `2026_07_09_100007_create_workflow_history_archives_table.php`): mirrors `workflow_history`'s columns, adds `source_id` (unique), `bank_id` (resolved from the owning request at archive time, not stored on `workflow_history` itself), and `archived_at`. Indexed on `(request_id, created_at)` and `(bank_id, archived_at)`.
- `WorkflowHistoryArchive` model (mirrors `AuditLogArchive`'s shape).
- `WorkflowHistoryArchiveService::archiveBatch()`: selects `workflow_history` rows joined to `engine_requests` where `created_at < cutoff AND status != 'ACTIVE'`, moves them to the archive table (carrying the joined `bank_id`), deletes from the hot table, self-audits via new `AuditAction::WORKFLOW_HISTORY_ARCHIVED`. Same batch/idempotent shape as `AuditLogArchiveService`.
- `ArchiveOldWorkflowHistoryCommand` (`workflow-history:archive-old`), wired into `routes/console.php` daily at 03:10 (after the audit archive job), using the same `RecordsSchedulerHeartbeat` trait.
- `config/retention.php`: added `workflow_history_hot_months` (default 12, matches `audit_hot_months`), `workflow_history_archive_batch_size` (default 500), and a `workflow-history:archive-old` staleness threshold in `scheduler_stale_minutes` (1500 min, matching the other daily archival jobs) — `CheckSchedulerHealthCommand` iterates this config generically, so no command-side change was needed there.
- Verified against the real dev DB: migration applied, table confirmed via `SHOW TABLES`, rolled back, re-applied — clean round trip.

## Why this is safe

- The `status != 'ACTIVE'` guard is proven by a dedicated test (`test_never_archives_history_for_an_active_request`) that seeds an old-but-active request's history row and asserts it survives archival untouched — this is the specific invariant protecting `withStageEntry()`/`stageDuration()` from corruption.
- Audit rows remain append-only and are never deleted, only moved — same as the pre-existing `audit_logs` pattern (archival ≠ deletion, per this finding's own security gate).

## Residual

- `ReportController::stageDuration()`'s hour-diff join and `sla()`'s breach bucketing operate on live `workflow_history` only — once a closed request's history rows archive out, that request drops out of those two reports' averages. This is the same expected trade-off already accepted for `audit_logs` archival (hot-table reports reflect the hot window); not a regression introduced here.
- No UI/API surface was added to read `workflow_history_archives` (mirrors `audit_log_archives`, which also has no dedicated read endpoint today — both are operational/compliance cold storage, not user-facing).

## Test evidence

```
ArchiveOldAuditLogsTest: 4 tests (1 new: test_archived_row_preserves_bank_id), 12 assertions
ArchiveOldWorkflowHistoryTest: 5 tests (new), 14 assertions
```

## Regression check

```
tests/Feature/Operations/, tests/Feature/Report/, tests/Feature/Audit/, tests/Feature/Admin/AuditScopeTest.php: 100 tests, 281 assertions — all pass
tests/Feature/Engine/EngineTransitionFieldDiffTest.php (workflow_history writer): 5 tests, 22 assertions — all pass
```

## Pint

All new/touched files — passed clean.
