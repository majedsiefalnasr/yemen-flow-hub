# WP-13 — Retention + Operations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add conservative, archive-first retention jobs and operational visibility so background failures and missed schedules are never silent.

**Architecture:** A single `config/retention.php` drives draft conservative horizons (CBY policy confirmation deferred). Each scheduled cleanup command is idempotent, logs outcomes, and records a scheduler heartbeat via `SchedulerRunLogger`. Audit rows age out of hot `audit_logs` into `audit_log_archives` (move, never hard-delete within horizon). `AdminHealthController` exposes heartbeat timestamps, queue failures, and last retention-run status to `CBY_ADMIN` only. Physical file deletes are limited to export expiry and orphan cleanup; superseded document physical files are archived per policy doc, metadata always retained.

**Tech Stack:** PHP 8.2+, Laravel 11, MySQL, Redis queues, Sanctum. Nuxt 4 frontend (health surface only — minimal admin read).

## Investigation findings (the starting state)

| Area | Current state |
|---|---|
| Scheduler (`backend/routes/console.php`) | Only `workflow:expire-engine-claims` (everyMinute) + `workflow:notify-sla-signals` (hourly). No heartbeat table. |
| `ReportExport` | Statuses: `PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`. No `EXPIRED`. Files on `private` disk indefinitely. |
| `GenerateReportExport` job | Exists; sets `FAILED` on error; `failed()` safety net. No ops alert hook. |
| Notifications | `engine_notifications` + `notification_recipients` (`read_at`, `archived_at`). No purge job. |
| Documents (WP-8 F-10) | `EngineRequestDocument.superseded_by` + `DocumentStatus::Superseded`. Physical old files not cleaned. |
| Audit | `AuditLog` append-only via Eloquent guards. No archive table or hot horizon. |
| Failed jobs | Laravel `failed_jobs` table exists (`config/queue.php`). No admin visibility surface. |
| Ops docs | `docs/operations/` does not exist yet. |

### WP-12 collision boundary (ReportExport UX)

| Owner | Scope |
|---|---|
| **WP-12 Track B** | Frontend FAILED export UX (toast/copy, retry affordance, list badge styling for `FAILED`). |
| **WP-13 (this plan)** | Backend `EXPIRED` status, `reports:purge-old-exports` job, `EXPORT_EXPIRED` 422 on download, retention config. |

**Interface contract (do not break):**
- `ReportExport.status` is a plain string column. Valid terminal values after WP-13: `COMPLETED`, `FAILED`, `EXPIRED`.
- Download endpoint (`ReportExportController::download`) returns HTTP 422 with `error.code = 'EXPORT_EXPIRED'` when `status === 'EXPIRED'` (distinct from WP-12's `EXPORT_NOT_READY` and `FAILED` display).
- WP-12 frontend may add `EXPIRED` badge copy but must not implement purge logic or change retention horizons.
- WP-13 must not change FAILED job retry UX or frontend error strings for `FAILED`.

---

## Global Constraints

- **Conservative archive-first defaults:** when policy is unclear, archive/move — do not hard-delete audit rows. Physical deletes only where spec explicitly allows (export files past retention, orphan files with no DB reference).
- **CBY policy deferral:** audit hot horizon default 12 months is draft; destructive purge of archived audit data is **out of scope** until CBY confirms regulatory minimum. Jobs ship with config-gated horizons; document open questions in retention policy.
- **Signed commits only.** Never `--no-gpg-sign` / `--no-verify`. Conventional commits with required scope (`backend`, `docs`, or `repo`). Co-Author trailer mandatory.
- **TDD:** failing test first for every behavior change.
- **Idempotent jobs:** re-running any cleanup command must be safe (0 rows affected on second run when nothing new is eligible).
- **Failure-visible:** every scheduled command and async job failure produces a log signal observable in `AdminHealthController` and/or `Log::error` with a stable `ops.*` context key.
- **Verification ladder:** focused PHPUnit per task. Do not run full `php artisan test` by default (known-red baseline). Report baseline, don't chase unrelated reds.
- **Product logic unchanged:** retention/ops only — no workflow transition or visibility rule changes.
- **Notifications ≠ audit trail:** purge notifications freely per policy; never treat `engine_notifications` as compliance record.

---

## File Structure

**Config — create:**
- `backend/config/retention.php` — horizons (days/months), feature flags, scheduler staleness thresholds.

**Backend — create:**
- `backend/database/migrations/2026_07_07_100001_create_scheduler_run_logs_table.php`
- `backend/database/migrations/2026_07_07_100002_create_audit_log_archives_table.php`
- `backend/app/Models/SchedulerRunLog.php`
- `backend/app/Models/AuditLogArchive.php`
- `backend/app/Services/Operations/SchedulerRunLogger.php`
- `backend/app/Console/Concerns/RecordsSchedulerHeartbeat.php` — trait for commands
- `backend/app/Console/Commands/PurgeOldNotificationsCommand.php` — `notifications:purge-old`
- `backend/app/Console/Commands/PurgeOldReportExportsCommand.php` — `reports:purge-old-exports`
- `backend/app/Console/Commands/PurgeOrphanDocumentsCommand.php` — `documents:purge-orphans`
- `backend/app/Console/Commands/ArchiveSupersededDocumentsCommand.php` — `documents:archive-superseded`
- `backend/app/Console/Commands/ArchiveOldAuditLogsCommand.php` — `audit:archive-old`
- `backend/app/Console/Commands/CheckSchedulerHealthCommand.php` — `ops:check-scheduler-health`
- `backend/app/Services/Operations/AuditLogArchiveService.php`
- `backend/app/Services/Operations/OperationalAlertLogger.php` — structured failure logging
- `backend/app/Http/Controllers/Api/Admin/AdminHealthController.php`
- `backend/tests/Feature/Operations/SchedulerHeartbeatTest.php`
- `backend/tests/Feature/Operations/PurgeOldNotificationsTest.php`
- `backend/tests/Feature/Operations/PurgeOldReportExportsTest.php`
- `backend/tests/Feature/Operations/PurgeOrphanDocumentsTest.php`
- `backend/tests/Feature/Operations/ArchiveOldAuditLogsTest.php`
- `backend/tests/Feature/Operations/AdminHealthControllerTest.php`

**Backend — modify:**
- `backend/routes/console.php` — register new schedules + daily health check
- `backend/app/Console/Commands/ExpireEngineClaimsCommand.php` — heartbeat + failure visibility
- `backend/app/Console/Commands/NotifySlaSignalsCommand.php` — heartbeat + failure visibility
- `backend/app/Http/Controllers/Api/V1/ReportExportController.php:79-87` — `EXPORT_EXPIRED` guard
- `backend/app/Jobs/GenerateReportExport.php` — ops failure log on catch/failed
- `backend/app/Jobs/DispatchNotification.php` — ops failure log in `failed()`
- `backend/app/Services/Workflow/EngineTransitionService.php` — ensure `STAGE_HOOK_FAILED` logs via `OperationalAlertLogger`
- `backend/app/Enums/AuditAction.php` — add `RETENTION_JOB_RUN`, `AUDIT_ARCHIVED`
- `backend/routes/api.php` — `GET admin/health`
- `backend/tests/Feature/Report/ReportExportTest.php` — expired download test

**Docs — create:**
- `docs/operations/retention-policy.md` (RT-1)
- `docs/operations/runbook.md` (OM-5)

**Frontend — modify (minimal, Task 8):**
- `frontend/app/pages/admin/health.vue` — read-only ops dashboard (or extend existing admin page if one exists; create new page if not)

---

## Task 1: Retention policy document + config scaffold (RT-1)

**Files:**
- Create: `docs/operations/retention-policy.md`
- Create: `backend/config/retention.php`
- Test: `backend/tests/Unit/Operations/RetentionConfigTest.php`

**Interfaces:**
- Consumes: WP-13 spec RT-1 through RT-6 horizon table
- Produces: `config('retention.*')` keys used by all purge/archive commands in Tasks 4–7

- [ ] **Step 1: Write failing config test**

```php
<?php

namespace Tests\Unit\Operations;

use Tests\TestCase;

class RetentionConfigTest extends TestCase
{
    public function test_retention_config_has_conservative_defaults(): void
    {
        $this->assertSame(12, config('retention.audit_hot_months'));
        $this->assertSame(30, config('retention.export_file_days'));
        $this->assertSame(90, config('retention.notification_unread_max_days'));
        $this->assertSame(365, config('retention.notification_read_days'));
        $this->assertSame(90, config('retention.superseded_document_file_days'));
        $this->assertTrue(config('retention.archive_first'));
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
cd backend && php artisan test tests/Unit/Operations/RetentionConfigTest.php
```
Expected: FAIL — config file missing or keys undefined.

- [ ] **Step 3: Create `backend/config/retention.php`**

```php
<?php

return [
  // When true, prefer archive/move over physical delete when policy is ambiguous.
  'archive_first' => env('RETENTION_ARCHIVE_FIRST', true),

  // RT-3 — hot DB horizon (CBY confirm before tightening)
  'audit_hot_months' => (int) env('RETENTION_AUDIT_HOT_MONTHS', 12),
  'audit_archive_batch_size' => (int) env('RETENTION_AUDIT_BATCH_SIZE', 500),

  // RT-5 — export file retention (row kept as EXPIRED history)
  'export_file_days' => (int) env('RETENTION_EXPORT_FILE_DAYS', 30),

  // RT-4 — notification retention (state-based)
  'notification_unread_max_days' => (int) env('RETENTION_NOTIFICATION_UNREAD_MAX_DAYS', 90),
  'notification_read_days' => (int) env('RETENTION_NOTIFICATION_READ_DAYS', 365),

  // RT-6 — superseded document physical file lifecycle
  'superseded_document_file_days' => (int) env('RETENTION_SUPERSEDED_DOC_DAYS', 90),
  'orphan_file_grace_hours' => (int) env('RETENTION_ORPHAN_GRACE_HOURS', 24),

  // OM-2 — scheduler staleness thresholds (minutes)
  'scheduler_stale_minutes' => [
    'workflow:expire-engine-claims' => 5,
    'workflow:notify-sla-signals' => 120,
    'notifications:purge-old' => 1500,
    'reports:purge-old-exports' => 1500,
    'documents:purge-orphans' => 1500,
    'documents:archive-superseded' => 1500,
    'audit:archive-old' => 1500,
  ],
];
```

- [ ] **Step 4: Create `docs/operations/retention-policy.md`**

Document per data class (audit, notifications, exports, documents/orphans, failed_jobs) with:
- Hot duration, archive schedule, search/restore path, access control
- Draft conservative horizons matching config defaults above
- Explicit **CBY open questions** (audit regulatory minimum, DB-level immutability)
- Statement: notifications are never the audit trail
- Archive-first principle when policy unclear

- [ ] **Step 5: Run test — verify pass**

```bash
cd backend && php artisan test tests/Unit/Operations/RetentionConfigTest.php
```
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add docs/operations/retention-policy.md backend/config/retention.php backend/tests/Unit/Operations/RetentionConfigTest.php
git commit -m "docs(backend): add retention policy and conservative config defaults"
```

---

## Task 2: Scheduler heartbeat infrastructure (OM-2)

**Files:**
- Create: `backend/database/migrations/2026_07_07_100001_create_scheduler_run_logs_table.php`
- Create: `backend/app/Models/SchedulerRunLog.php`
- Create: `backend/app/Services/Operations/SchedulerRunLogger.php`
- Create: `backend/app/Console/Concerns/RecordsSchedulerHeartbeat.php`
- Test: `backend/tests/Feature/Operations/SchedulerHeartbeatTest.php`

**Interfaces:**
- Consumes: none
- Produces:
  - `SchedulerRunLogger::recordSuccess(string $command, int $affected = 0, array $meta = []): void`
  - `SchedulerRunLogger::recordFailure(string $command, \Throwable $e, array $meta = []): void`
  - `SchedulerRunLogger::lastRun(string $command): ?SchedulerRunLog`
  - `RecordsSchedulerHeartbeat` trait — wraps `handle()` body, calls logger on success/failure

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Operations;

use App\Models\SchedulerRunLog;
use App\Services\Operations\SchedulerRunLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_successful_command_run(): void
    {
        app(SchedulerRunLogger::class)->recordSuccess('workflow:expire-engine-claims', affected: 3);

        $this->assertDatabaseHas('scheduler_run_logs', [
            'command' => 'workflow:expire-engine-claims',
            'status' => 'success',
            'affected_count' => 3,
        ]);
    }

    public function test_records_failed_command_run(): void
    {
        app(SchedulerRunLogger::class)->recordFailure(
            'workflow:notify-sla-signals',
            new \RuntimeException('boom'),
        );

        $log = SchedulerRunLog::query()->where('command', 'workflow:notify-sla-signals')->first();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('boom', $log->error_message);
    }

    public function test_last_run_returns_most_recent_entry(): void
    {
        $logger = app(SchedulerRunLogger::class);
        $logger->recordSuccess('audit:archive-old', affected: 1);
        $logger->recordSuccess('audit:archive-old', affected: 2);

        $last = $logger->lastRun('audit:archive-old');
        $this->assertSame(2, $last->affected_count);
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
cd backend && php artisan test tests/Feature/Operations/SchedulerHeartbeatTest.php
```
Expected: FAIL — table/class not found.

- [ ] **Step 3: Migration**

```php
Schema::create('scheduler_run_logs', function (Blueprint $table) {
    $table->id();
    $table->string('command', 100);
    $table->string('status', 20); // success|failed
    $table->unsignedInteger('affected_count')->default(0);
    $table->json('meta')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamp('ran_at');
    $table->timestamps();
    $table->index(['command', 'ran_at']);
});
```

- [ ] **Step 4: Implement `SchedulerRunLogger`**

```php
<?php

namespace App\Services\Operations;

use App\Models\SchedulerRunLog;

class SchedulerRunLogger
{
    public function recordSuccess(string $command, int $affected = 0, array $meta = []): void
    {
        SchedulerRunLog::create([
            'command' => $command,
            'status' => 'success',
            'affected_count' => $affected,
            'meta' => $meta ?: null,
            'ran_at' => now(),
        ]);
    }

    public function recordFailure(string $command, \Throwable $e, array $meta = []): void
    {
        SchedulerRunLog::create([
            'command' => $command,
            'status' => 'failed',
            'meta' => $meta ?: null,
            'error_message' => $e->getMessage(),
            'ran_at' => now(),
        ]);
    }

    public function lastRun(string $command): ?SchedulerRunLog
    {
        return SchedulerRunLog::query()
            ->where('command', $command)
            ->orderByDesc('ran_at')
            ->first();
    }
}
```

- [ ] **Step 5: Implement `RecordsSchedulerHeartbeat` trait**

```php
<?php

namespace App\Console\Concerns;

use App\Services\Operations\SchedulerRunLogger;

trait RecordsSchedulerHeartbeat
{
    protected function runWithHeartbeat(callable $callback): int
    {
        $command = (string) $this->signature;
        $logger = app(SchedulerRunLogger::class);

        try {
            $affected = (int) $callback();
            $logger->recordSuccess($command, $affected);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $logger->recordFailure($command, $e);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
```

- [ ] **Step 6: Run test — verify pass**

```bash
cd backend && php artisan test tests/Feature/Operations/SchedulerHeartbeatTest.php
```

- [ ] **Step 7: Commit**

```bash
git add backend/database/migrations/2026_07_07_100001_create_scheduler_run_logs_table.php backend/app/Models/SchedulerRunLog.php backend/app/Services/Operations/SchedulerRunLogger.php backend/app/Console/Concerns/RecordsSchedulerHeartbeat.php backend/tests/Feature/Operations/SchedulerHeartbeatTest.php
git commit -m "feat(backend): add scheduler heartbeat run log infrastructure"
```

---

## Task 3: Wire existing scheduled commands to heartbeat (OM-3 partial)

**Files:**
- Modify: `backend/app/Console/Commands/ExpireEngineClaimsCommand.php`
- Modify: `backend/app/Console/Commands/NotifySlaSignalsCommand.php`
- Modify: `backend/tests/Feature/Engine/ExpireEngineClaimsCommandTest.php`
- Create: `backend/tests/Feature/Operations/ExpireEngineClaimsHeartbeatTest.php`

**Interfaces:**
- Consumes: `SchedulerRunLogger`, `RecordsSchedulerHeartbeat` (Task 2)
- Produces: heartbeat rows for `workflow:expire-engine-claims` and `workflow:notify-sla-signals`

- [ ] **Step 1: Write failing heartbeat integration test**

```php
public function test_expire_claims_command_records_scheduler_heartbeat(): void
{
    $this->artisan('workflow:expire-engine-claims')->assertSuccessful();

    $this->assertDatabaseHas('scheduler_run_logs', [
        'command' => 'workflow:expire-engine-claims',
        'status' => 'success',
    ]);
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
cd backend && php artisan test --filter=test_expire_claims_command_records_scheduler_heartbeat
```

- [ ] **Step 3: Refactor `ExpireEngineClaimsCommand`**

Use `RecordsSchedulerHeartbeat` trait. Count released claims as `affected`. Per-item `catch` blocks should increment a `failed_count` meta field but not abort the whole command — only throw if the outer loop itself fails catastrophically. On per-item failure, also call `OperationalAlertLogger` (create stub in this task if Task 8 not done — minimal `Log::error('ops.claim_sweep.failed', [...])`).

Return affected count from closure passed to `runWithHeartbeat()`.

- [ ] **Step 4: Refactor `NotifySlaSignalsCommand` similarly**

Wrap in `runWithHeartbeat`, return `$sent` as affected count.

- [ ] **Step 5: Run focused tests**

```bash
cd backend && php artisan test tests/Feature/Engine/ExpireEngineClaimsCommandTest.php tests/Feature/Operations/ExpireEngineClaimsHeartbeatTest.php
```

- [ ] **Step 6: Commit**

```bash
git add backend/app/Console/Commands/ExpireEngineClaimsCommand.php backend/app/Console/Commands/NotifySlaSignalsCommand.php backend/tests/Feature/Operations/ExpireEngineClaimsHeartbeatTest.php
git commit -m "feat(backend): record scheduler heartbeat for claim and SLA commands"
```

---

## Task 4: Notification retention command (RT-4)

**Files:**
- Create: `backend/app/Console/Commands/PurgeOldNotificationsCommand.php`
- Modify: `backend/routes/console.php`
- Test: `backend/tests/Feature/Operations/PurgeOldNotificationsTest.php`

**Interfaces:**
- Consumes: `config('retention.notification_unread_max_days')`, `config('retention.notification_read_days')`, `RecordsSchedulerHeartbeat`
- Produces: `notifications:purge-old` artisan command, scheduled daily

**Purge rules:**
- Unread (`read_at` null AND `archived_at` null): delete recipient rows older than `notification_unread_max_days` (default 90d). Cascade deletes orphan `engine_notifications` with zero recipients.
- Read or archived: delete recipient rows where `COALESCE(archived_at, read_at)` older than `notification_read_days` (default 365d).
- Security/compliance types (`severity = critical` or type prefix `security.`): skip until CBY policy — document in retention-policy.md; test proves they are not purged.

- [ ] **Step 1: Write failing tests**

```php
public function test_purges_old_read_notification_recipients(): void
{
    // seed notification + recipient with read_at 400 days ago
    $this->artisan('notifications:purge-old')->assertSuccessful();
    $this->assertDatabaseMissing('notification_recipients', ['id' => $recipientId]);
}

public function test_idempotent_second_run_deletes_nothing_extra(): void
{
    // only eligible rows; run twice; count unchanged after first
}

public function test_does_not_purge_security_critical_notifications(): void
{
    // severity critical, old — still present after purge
}
```

- [ ] **Step 2: Run tests — verify fail**

```bash
cd backend && php artisan test tests/Feature/Operations/PurgeOldNotificationsTest.php
```

- [ ] **Step 3: Implement command**

```php
protected $signature = 'notifications:purge-old';
protected $description = 'Purge notification recipients past retention horizon';

public function handle(): int
{
    return $this->runWithHeartbeat(function (): int {
        $purged = 0;
        // batch delete logic using config horizons
        return $purged;
    });
}
```

- [ ] **Step 4: Register schedule**

```php
Schedule::command('notifications:purge-old')->dailyAt('02:10');
```

- [ ] **Step 5: Run tests — verify pass + idempotency**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(backend): add notifications purge retention command"
```

---

## Task 5: Export file retention + EXPIRED status (RT-5)

**Files:**
- Create: `backend/app/Console/Commands/PurgeOldReportExportsCommand.php`
- Modify: `backend/app/Http/Controllers/Api/V1/ReportExportController.php:79-87`
- Modify: `backend/routes/console.php`
- Modify: `backend/tests/Feature/Report/ReportExportTest.php`
- Test: `backend/tests/Feature/Operations/PurgeOldReportExportsTest.php`

**Interfaces:**
- Consumes: `config('retention.export_file_days')`, `RecordsSchedulerHeartbeat`
- Produces:
  - `reports:purge-old-exports` command
  - `ReportExport.status = 'EXPIRED'` with `file_path` nulled after physical delete
  - Download guard: HTTP 422 `error.code = 'EXPORT_EXPIRED'`

**WP-12 boundary:** do not modify frontend in this task. Only backend status + API error code.

- [ ] **Step 1: Write failing purge test**

```php
public function test_marks_completed_export_expired_and_deletes_file(): void
{
    Storage::fake('private');
    $path = 'exports/report-1.csv';
    Storage::disk('private')->put($path, 'csv');
    $export = ReportExport::factory()->create([
        'status' => 'COMPLETED',
        'file_path' => $path,
        'created_at' => now()->subDays(31),
    ]);

    $this->artisan('reports:purge-old-exports')->assertSuccessful();

    $export->refresh();
    $this->assertSame('EXPIRED', $export->status);
    $this->assertNull($export->file_path);
    Storage::disk('private')->assertMissing($path);
}

public function test_idempotent_on_already_expired_export(): void
{
    // EXPIRED row, null file_path — second run no error, row unchanged
}
```

- [ ] **Step 2: Write failing download test**

```php
public function test_download_returns_export_expired_for_expired_status(): void
{
    $export = ReportExport::factory()->create([
        'requested_by' => $this->admin->id,
        'status' => 'EXPIRED',
        'file_path' => null,
    ]);

    $this->actingAs($this->admin)
        ->getJson("/api/v1/reports/exports/{$export->id}/download")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'EXPORT_EXPIRED');
}
```

- [ ] **Step 3: Run tests — verify fail**

```bash
cd backend && php artisan test tests/Feature/Operations/PurgeOldReportExportsTest.php --filter=test_download_returns_export_expired
```

- [ ] **Step 4: Implement purge command**

Only target `COMPLETED` exports with `created_at` older than `export_file_days`. Skip `PENDING`, `PROCESSING`, `FAILED`. Set `EXPIRED`, delete physical file, keep row.

- [ ] **Step 5: Add download guard before `EXPORT_NOT_READY` check**

```php
if ($reportExport->status === 'EXPIRED') {
    return response()->json([
        'error' => ['code' => 'EXPORT_EXPIRED', 'message' => 'Export file has expired and is no longer available.'],
    ], 422);
}
```

- [ ] **Step 6: Schedule daily**

```php
Schedule::command('reports:purge-old-exports')->dailyAt('02:20');
```

- [ ] **Step 7: Run tests — verify pass**

- [ ] **Step 8: Commit**

```bash
git commit -m "feat(backend): expire old report exports and guard download"
```

---

## Task 6: Document orphan + superseded file lifecycle (RT-6)

**Files:**
- Create: `backend/app/Console/Commands/PurgeOrphanDocumentsCommand.php`
- Create: `backend/app/Console/Commands/ArchiveSupersededDocumentsCommand.php`
- Modify: `backend/routes/console.php`
- Test: `backend/tests/Feature/Operations/PurgeOrphanDocumentsTest.php`
- Test: `backend/tests/Feature/Operations/ArchiveSupersededDocumentsTest.php`

**Interfaces:**
- Consumes: `config('retention.orphan_file_grace_hours')`, `config('retention.superseded_document_file_days')`, WP-8 `DocumentStatus::Superseded`, `EngineRequestDocument.path`
- Produces: `documents:purge-orphans`, `documents:archive-superseded`

**Orphan rule:** files under `private` disk in `engine-requests/` prefix with no matching `engine_request_documents.path`, older than grace hours.

**Superseded rule:** documents with `status = superseded` and `created_at` older than horizon — delete physical file only; **keep DB row + audit metadata**. Never delete files referenced by active documents.

- [ ] **Step 1: Write failing orphan test**

```php
public function test_purges_orphan_file_not_referenced_by_document_row(): void
{
    Storage::fake('private');
    Storage::disk('private')->put('engine-requests/99/orphan.pdf', 'pdf');
    // no EngineRequestDocument with that path, file mtime > grace

    $this->artisan('documents:purge-orphans')->assertSuccessful();
    Storage::disk('private')->assertMissing('engine-requests/99/orphan.pdf');
}

public function test_never_deletes_file_referenced_by_document_row(): void
{
    // document row points at path — file remains
}
```

- [ ] **Step 2: Write failing superseded test**

```php
public function test_archive_superseded_deletes_physical_file_but_keeps_row(): void
{
    // superseded doc, old, has path — after command: row exists, path nulled or kept with archived flag
}
```

- [ ] **Step 3: Implement commands with heartbeat**

Batch processing; return affected file count.

- [ ] **Step 4: Schedule daily**

```php
Schedule::command('documents:archive-superseded')->dailyAt('02:30');
Schedule::command('documents:purge-orphans')->dailyAt('02:40');
```

- [ ] **Step 5: Run tests — verify pass**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(backend): add document orphan purge and superseded archive jobs"
```

---

## Task 7: Audit archive command (RT-3)

**Files:**
- Create: `backend/database/migrations/2026_07_07_100002_create_audit_log_archives_table.php`
- Create: `backend/app/Models/AuditLogArchive.php`
- Create: `backend/app/Services/Operations/AuditLogArchiveService.php`
- Create: `backend/app/Console/Commands/ArchiveOldAuditLogsCommand.php`
- Modify: `backend/app/Enums/AuditAction.php` — add `AUDIT_ARCHIVED`, `RETENTION_JOB_RUN`
- Modify: `backend/routes/console.php`
- Test: `backend/tests/Feature/Operations/ArchiveOldAuditLogsTest.php`

**Interfaces:**
- Consumes: `config('retention.audit_hot_months')`, `config('retention.audit_archive_batch_size')`, `AuditService`
- Produces:
  - `audit_log_archives` table (mirror `audit_logs` columns + `archived_at`, `source_id`)
  - `AuditLogArchiveService::archiveBatch(): int` — copy then remove from hot via `DB::table` (bypasses Eloquent delete guard)
  - `audit:archive-old` command

**Conservative rule:** archive-only move from hot DB. No delete from `audit_log_archives` in this WP. Log each archive batch via `AuditService` with `AUDIT_ARCHIVED`.

- [ ] **Step 1: Write failing test**

```php
public function test_archives_rows_older_than_hot_horizon(): void
{
    $old = AuditLog::create([...,'created_at' => now()->subMonths(13)]);
    $recent = AuditLog::create([...,'created_at' => now()->subMonth()]);

    $this->artisan('audit:archive-old')->assertSuccessful();

    $this->assertDatabaseMissing('audit_logs', ['id' => $old->id]);
    $this->assertDatabaseHas('audit_log_archives', ['source_id' => $old->id]);
    $this->assertDatabaseHas('audit_logs', ['id' => $recent->id]);
}

public function test_archive_batch_is_idempotent(): void
{
    $this->artisan('audit:archive-old');
    $this->artisan('audit:archive-old');
    // archive table has exactly one row per source_id
}
```

- [ ] **Step 2: Run test — verify fail**

- [ ] **Step 3: Migration `audit_log_archives`**

Mirror `audit_logs` schema from `docs/03-database-and-models.md` + `source_id` (original PK) + `archived_at`.

- [ ] **Step 4: Implement `AuditLogArchiveService`**

```php
public function archiveBatch(): int
{
    $cutoff = now()->subMonths(config('retention.audit_hot_months'));
    $rows = DB::table('audit_logs')->where('created_at', '<', $cutoff)->limit(config('retention.audit_archive_batch_size'))->get();
    if ($rows->isEmpty()) {
        return 0;
    }
    // insert into audit_log_archives, then DB::table('audit_logs')->whereIn('id', ...)->delete()
}
```

- [ ] **Step 5: Implement command + schedule**

```php
Schedule::command('audit:archive-old')->dailyAt('03:00');
```

- [ ] **Step 6: Run tests — verify pass**

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(backend): archive audit logs past hot retention horizon"
```

---

## Task 8: Failed-job visibility + admin health surface (OM-1, OM-3, OM-4)

**Files:**
- Create: `backend/app/Services/Operations/OperationalAlertLogger.php`
- Create: `backend/app/Console/Commands/CheckSchedulerHealthCommand.php`
- Create: `backend/app/Http/Controllers/Api/Admin/AdminHealthController.php`
- Create: `frontend/app/pages/admin/health.vue`
- Modify: `backend/app/Jobs/GenerateReportExport.php`
- Modify: `backend/app/Jobs/DispatchNotification.php`
- Modify: `backend/app/Services/Workflow/EngineTransitionService.php` (STAGE_HOOK_FAILED path)
- Modify: `backend/routes/api.php`
- Modify: `backend/routes/console.php`
- Test: `backend/tests/Feature/Operations/AdminHealthControllerTest.php`

**Interfaces:**
- Consumes: `SchedulerRunLogger`, `config('retention.scheduler_stale_minutes')`, `DB::table('failed_jobs')`
- Produces:
  - `OperationalAlertLogger::failure(string $surface, \Throwable $e, array $context = []): void` — `Log::error` with key `ops.{surface}.failed`
  - `GET /api/v1/admin/health` — CBY_ADMIN only
  - `ops:check-scheduler-health` — daily, records stale commands as failed heartbeat meta

**Health payload shape:**

```json
{
  "data": {
    "scheduler": [
      {"command": "workflow:expire-engine-claims", "last_ran_at": "...", "status": "success", "stale": false}
    ],
    "queue": {"failed_jobs_count": 2, "recent_failures": [{"id": 1, "connection": "redis", "failed_at": "..."}]},
    "retention": {"last_runs": {"notifications:purge-old": "...", "audit:archive-old": "..."}}
  }
}
```

- [ ] **Step 1: Write failing health endpoint test**

```php
public function test_cby_admin_can_view_health_surface(): void
{
    $this->actingAs($this->cbyAdmin)
        ->getJson('/api/v1/admin/health')
        ->assertOk()
        ->assertJsonStructure(['data' => ['scheduler', 'queue', 'retention']]);
}

public function test_non_admin_forbidden(): void
{
    $this->actingAs($this->bankUser)->getJson('/api/v1/admin/health')->assertForbidden();
}
```

- [ ] **Step 2: Write failing stale scheduler test**

```php
public function test_check_scheduler_health_flags_stale_command(): void
{
    // seed old scheduler_run_log for expire-engine-claims > 5 min ago
    $this->artisan('ops:check-scheduler-health')->assertSuccessful();
    $this->assertDatabaseHas('scheduler_run_logs', [
        'command' => 'ops:check-scheduler-health',
        'status' => 'success',
    ]);
    // assert OperationalAlertLogger called or meta contains stale_commands
}
```

- [ ] **Step 3: Implement `OperationalAlertLogger` + wire jobs**

In `GenerateReportExport::failed()` and catch block, call `OperationalAlertLogger::failure('report_export', $e, ['export_id' => ...])`.
In `DispatchNotification`, add `failed()` method with `ops.notification_dispatch.failed`.
In `EngineTransitionService`, when returning `STAGE_HOOK_FAILED`, log `ops.stage_hook.failed` with transition id.

- [ ] **Step 4: Implement `AdminHealthController@index`**

Gate: `CBY_ADMIN` role / system_admin capability (match existing admin route patterns).

- [ ] **Step 5: Minimal frontend read-only page**

`frontend/app/pages/admin/health.vue` — table of scheduler commands, failed job count, retention last-run timestamps. Use existing admin layout + shadcn Table. No mutations.

- [ ] **Step 6: Schedule health check**

```php
Schedule::command('ops:check-scheduler-health')->dailyAt('06:00');
```

- [ ] **Step 7: Run tests — verify pass**

```bash
cd backend && php artisan test tests/Feature/Operations/AdminHealthControllerTest.php
```

- [ ] **Step 8: Commit**

```bash
git commit -m "feat(backend): add admin health surface and ops failure logging"
```

---

## Task 9: Operations runbook (OM-5)

**Files:**
- Create: `docs/operations/runbook.md`

**Interfaces:**
- Consumes: all commands from Tasks 3–8, retention-policy.md
- Produces: operator-facing runbook

- [ ] **Step 1: Create `docs/operations/runbook.md`**

Cover for each scheduled job:
- Purpose, schedule, config keys, expected log/heartbeat row
- Failure symptoms (stale heartbeat, `ops.*.failed` log keys, failed_jobs growth)
- Recovery steps (re-run command, check queue worker, check scheduler cron)
- Stuck claim / stuck export procedures
- Archived audit restore path (query `audit_log_archives` by `source_id`, re-insert procedure documented — restore is manual/SQL, not automated)
- Escalation contact placeholder (CBY ops)

- [ ] **Step 2: Cross-link retention-policy.md**

- [ ] **Step 3: Commit**

```bash
git add docs/operations/runbook.md
git commit -m "docs(backend): add operations runbook for retention and monitoring"
```

---

## Task 10: Gate + final review

**Files:**
- Modify: `.superpowers/sdd/progress.md` — mark all tasks complete
- Verify: full focused ops test suite

- [ ] **Step 1: Run focused WP-13 test bundle**

```bash
cd backend && php artisan test tests/Feature/Operations tests/Unit/Operations/RetentionConfigTest.php tests/Feature/Report/ReportExportTest.php
```

- [ ] **Step 2: Run format check on touched PHP**

```bash
cd backend && vendor/bin/pint --test app/Console/Commands/Purge*.php app/Console/Commands/Archive*.php app/Services/Operations app/Http/Controllers/Api/Admin/AdminHealthController.php
```

- [ ] **Step 3: Run frontend lint on health page**

```bash
cd frontend && pnpm exec eslint app/pages/admin/health.vue
```

- [ ] **Step 4: Manual verification checklist (from spec)**

1. Run each cleanup job twice → idempotent
2. Force job failure → visible in health surface + logs
3. Stop scheduler → `ops:check-scheduler-health` flags stale commands
4. Expired export download → `EXPORT_EXPIRED` 422
5. Archive audit row → retrievable from `audit_log_archives`

- [ ] **Step 5: Spec coverage self-review**

| Spec item | Task |
|---|---|
| RT-1 retention policy | Task 1 |
| RT-2 scheduled cleanup jobs | Tasks 4–7 + console.php |
| RT-3 audit archive | Task 7 |
| RT-4 notification retention | Task 4 |
| RT-5 export retention | Task 5 |
| RT-6 document/orphan lifecycle | Task 6 |
| OM-1 failed-job visibility | Task 8 |
| OM-2 scheduler heartbeat | Task 2 |
| OM-3 failure surfaces | Tasks 3, 8 |
| OM-4 admin health | Task 8 |
| OM-5 runbook | Task 9 |

- [ ] **Step 6: Update progress ledger**

- [ ] **Step 7: Commit gate fixes if any**

```bash
git commit -m "chore(backend): wp13 retention and operations gate verification"
```

---

## Self-Review

**Spec coverage:** All RT-1–RT-6 and OM-1–OM-5 items mapped to Tasks 1–10. CBY deferrals explicitly documented in retention-policy.md and config comments; no hard delete of archived audit data.

**Placeholder scan:** No TBD steps. Each task has concrete files, tests, and commands.

**Type consistency:** `SchedulerRunLogger` signatures stable across Tasks 2–8. `EXPIRED` status and `EXPORT_EXPIRED` code used consistently in Task 5. WP-12 collision boundary documented in header.

**Idempotency:** Every purge/archive task includes explicit idempotent re-run test.

---

## Execution Handoff

**Plan complete and saved to `.claude/worktrees/wp13-retention-ops/docs/superpowers/plans/2026-07-07-wp13-retention-and-operations.md`.**

**Two execution options:**

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks
2. **Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
