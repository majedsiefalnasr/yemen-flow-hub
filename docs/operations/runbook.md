# Operations Runbook — Yemen Flow Hub

**Status:** Baseline (WP-13 OM-5)  
**Audience:** CBY platform operators and on-call engineers  
**Related:** [Retention Policy](./retention-policy.md) — horizons, data classes, CBY confirmation checklist

---

## Quick health check

| Surface | How to check |
|---------|----------------|
| **Admin UI** | Log in as `CBY_ADMIN` → `/admin/health` |
| **API** | `GET /api/v1/admin/health` (Sanctum, `CBY_ADMIN` only) |
| **Scheduler heartbeats** | `scheduler_run_logs` table or health payload `data.scheduler[]` |
| **Queue failures** | Health payload `data.queue.failed_jobs_count` + `failed_jobs` table |
| **Retention last runs** | Health payload `data.retention.last_runs` |
| **Structured ops logs** | Application log for keys matching `ops.*.failed` |

**First response when something looks wrong:** open `/admin/health`, note any `stale: true` scheduler rows and `failed_jobs_count > 0`, then grep logs for `ops.` failures.

---

## Scheduled jobs reference

All scheduled commands record a heartbeat in `scheduler_run_logs` on success or failure. Staleness thresholds are in `config/retention.php` → `scheduler_stale_minutes`.

| Command | Schedule | Purpose | Config keys |
|---------|----------|---------|-------------|
| `workflow:expire-engine-claims` | Every minute | Release support claims past TTL (`claim_expires_at`); notify CBY admins | `workflow.support_claim_ttl_minutes` (15 min default) |
| `workflow:notify-sla-signals` | Hourly | Scan and dispatch SLA warning notifications | — |
| `notifications:purge-old` | Daily 02:10 | Purge notification recipients past retention horizon | `notification_unread_max_days`, `notification_read_days` |
| `reports:purge-old-exports` | Daily 02:20 | Delete expired export files; mark `ReportExport` as `EXPIRED` | `export_file_days` |
| `documents:archive-superseded` | Daily 02:30 | Remove physical files for superseded documents past horizon; keep DB row | `superseded_document_file_days` |
| `documents:purge-orphans` | Daily 02:40 | Delete orphan files under `engine-requests/` with no document row | `orphan_file_grace_hours` |
| `audit:archive-old` | Daily 03:00 | Move audit rows older than hot horizon to `audit_log_archives` | `audit_hot_months`, `audit_archive_batch_size` |
| `ops:check-scheduler-health` | Daily 06:00 | Flag commands that missed their run window | `scheduler_stale_minutes` |

**Manual re-run (safe — all jobs are idempotent):**

```bash
cd backend
php artisan workflow:expire-engine-claims
php artisan workflow:notify-sla-signals
php artisan notifications:purge-old
php artisan reports:purge-old-exports
php artisan documents:archive-superseded
php artisan documents:purge-orphans
php artisan audit:archive-old
php artisan ops:check-scheduler-health
```

After a manual run, confirm a new row in `scheduler_run_logs` with `status = success` for that command.

---

## Failure surfaces and log keys

| Failure class | Log key | Typical cause |
|---------------|---------|---------------|
| Claim sweep (per-request) | `ops.claim_sweep.failed` | DB lock, missing request, notification dispatch error |
| Report export job | `ops.report_export.failed` | Disk full, CSV generation error, queue worker down |
| Notification dispatch | `ops.notification_dispatch.failed` | Mail/queue failure, invalid recipient |
| Stage hook rollback | `ops.stage_hook.failed` | Hook threw during workflow transition |
| Stale scheduler | `ops.scheduler_stale.failed` | Cron/scheduler stopped, or command crashed before heartbeat |

**Queue failures:** inspect `failed_jobs` (payload, exception). Retry after fixing root cause:

```bash
php artisan queue:retry <id>      # single job
php artisan queue:retry all       # all failed (use with care)
php artisan queue:flush           # discard after investigation
```

---

## Recovery procedures

### Scheduler stopped or stale heartbeats

**Symptoms:** `/admin/health` shows `stale: true` for one or more commands; `ops.scheduler_stale.failed` in logs; claims not expiring; retention jobs not running.

**Steps:**

1. Confirm Laravel scheduler cron is active on the app server:
   ```bash
   * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
   ```
2. Run `php artisan schedule:list` — verify all WP-13 commands appear.
3. Manually run the stale command(s) and confirm heartbeat rows update.
4. If cron is correct but commands fail, read `scheduler_run_logs.error_message` for the failed command.
5. Run `php artisan ops:check-scheduler-health` to clear stale alerts after recovery.

### Queue worker down

**Symptoms:** `failed_jobs_count` rising; exports stuck in `PENDING`/`PROCESSING`; notifications not delivered.

**Steps:**

1. Restart queue workers (`php artisan queue:work` or supervisor/systemd unit).
2. Inspect recent `failed_jobs` entries.
3. Retry or flush as appropriate after fixing the underlying error (disk, mail, DB).

### Mail transport failure

**Symptoms:** Notifications queued but not received; mail-related exceptions in `failed_jobs` or logs.

**Steps:**

1. Check health payload `data.mail.driver` matches expected transport.
2. Verify `.env` mail settings (`MAIL_MAILER`, credentials, host).
3. Test with `php artisan tinker` or a controlled notification in staging.
4. Failed notification jobs log `ops.notification_dispatch.failed`.

---

## Stuck support claim

**Symptoms:** Request shows claimed by a user who is inactive; `claim_expires_at` in the past but claim not released; Support Committee cannot claim.

**Steps:**

1. Check `/admin/health` — is `workflow:expire-engine-claims` stale?
2. If scheduler is healthy, inspect the request: `claim_expires_at`, `claimed_by_user_id`.
3. Manually run: `php artisan workflow:expire-engine-claims`.
4. If a specific request fails, grep logs for `ops.claim_sweep.failed` with `request_id`.
5. After repeated per-request failures, investigate DB constraints or `EngineClaimService` errors; escalate if manual release is required (DB-level fix only with CBY approval).

**Prevention:** Claim TTL is 15 minutes; heartbeat runs every minute. A healthy scheduler should auto-release expired claims.

---

## Stuck or failed report export

| Status | Meaning | Operator action |
|--------|---------|-----------------|
| `PENDING` / `PROCESSING` | Job not finished | Check queue worker; inspect `failed_jobs` for `GenerateReportExport` |
| `FAILED` | Generation error | User may retry from UI (WP-12); ops inspect `ops.report_export.failed` logs |
| `EXPIRED` | File past retention | **Not recoverable** — user must request a new export |
| `COMPLETED` | File available | Normal — download via reports UI |

**Stuck in PROCESSING:**

1. Confirm queue worker is running.
2. Check `failed_jobs` for the export job UUID.
3. If worker died mid-job, retry: `php artisan queue:retry <id>`.
4. If row is orphaned (PROCESSING with no job), set status to `FAILED` only after CBY ops approval and document the change in audit notes.

**Expired download:** API returns HTTP 422 `EXPORT_EXPIRED`. Direct user to generate a new export.

---

## Archived audit log restore

Audit rows older than `audit_hot_months` (default 12) are moved from `audit_logs` to `audit_log_archives`. Archive is **move-only** — no hard delete within the retention horizon.

### Search archive

```sql
SELECT * FROM audit_log_archives
WHERE source_id = :original_audit_log_id;

-- Or by subject
SELECT * FROM audit_log_archives
WHERE subject_type = 'App\\Models\\EngineRequest'
  AND subject_id = :request_id
ORDER BY created_at DESC;
```

### Manual restore to hot DB (CBY_ADMIN + documented ops ticket only)

Restore is **manual SQL**, not an automated command. Access is restricted to `CBY_ADMIN`; log the restore action in a new `audit_logs` row.

1. Locate the row in `audit_log_archives` by `source_id`.
2. Verify `source_id` is not already present in `audit_logs` (avoid duplicates).
3. Insert back into `audit_logs` using the archived columns (`user_id`, `action`, `subject_type`, `subject_id`, `metadata`, `old_values`, `new_values`, `created_at`, etc.). **Do not** copy `archived_at` or archive table `id`.
4. Optionally remove from `audit_log_archives` after successful restore (keep a ticket reference).
5. Record an `AUDIT_ARCHIVED` or ops restore note via normal audit logging.

**Escalation:** If restore is needed for compliance investigation, involve CBY compliance before modifying archive tables.

---

## Retention job failures

| Command | If it fails | Data impact |
|---------|-------------|-------------|
| `notifications:purge-old` | Notifications accumulate | No compliance impact (notifications ≠ audit trail) |
| `reports:purge-old-exports` | Export files remain on disk longer | Disk usage grows; re-run when fixed |
| `documents:purge-orphans` | Orphan files remain | Disk usage grows; safe to re-run |
| `documents:archive-superseded` | Superseded files remain on disk | Disk usage grows; metadata intact |
| `audit:archive-old` | Hot `audit_logs` grows | **Priority** — re-run promptly; batch size limits per run |

All retention commands are idempotent. After fixing the error, re-run the command; second run with no eligible rows affects 0 records.

---

## Escalation

| Level | Contact | When |
|-------|---------|------|
| L1 | CBY platform on-call | Stale scheduler, queue failures, stuck claims/exports |
| L2 | CBY infrastructure / DBA | DB-level issues, audit restore, mail relay |
| L3 | CBY compliance | Audit retention policy, archive restore for investigations |

> **Placeholder:** Replace with actual CBY ops contact details before production handoff.

---

## External monitoring (optional)

The in-app health surface (`/admin/health`) is the baseline. Deployments may additionally wire:

- Log monitor alert on `ops.*.failed`
- Cron monitor on `php artisan schedule:run`
- Queue depth / `failed_jobs` count threshold alerts

See [Retention Policy](./retention-policy.md) for horizon defaults and CBY confirmation checklist before enabling enforcement in production.
