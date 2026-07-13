# Production Guide

**Verified:** 2026-07-13, against `backend/routes/api.php`,
`backend/routes/console.php`, `backend/config/{retention,demo}.php`,
and the registered command signatures directly. Merged from
`docs/operations/runbook.md`, `docs/operations/retention-policy.md`,
and `docs/audit-functional/21-audit-closure-report.md`'s deployment/
rollback checklists (§13–14) — those three source files are **not**
deleted or moved by this step; they remain in place pending later
archival.

---

## Quick health check

| Surface              | How to check                                                        |
| -------------------- | ------------------------------------------------------------------- |
| Admin UI             | Log in as `CBY_ADMIN` → `/admin/health`                             |
| API                  | `GET /api/admin/health` (Sanctum, unversioned — not `/api/v1/...`)  |
| Scheduler heartbeats | `scheduler_run_logs` table or health payload `data.scheduler[]`     |
| Queue failures       | Health payload `data.queue.failed_jobs_count` + `failed_jobs` table |
| Retention last runs  | Health payload `data.retention.last_runs`                           |
| Structured ops logs  | Application log for keys matching `ops.*.failed`                    |

First response: open `/admin/health`, note any `stale: true` scheduler
rows and `failed_jobs_count > 0`, then grep logs for `ops.` failures.

---

## Scheduled jobs

All scheduled commands record a heartbeat in `scheduler_run_logs`.
Staleness thresholds are in `config/retention.php` →
`scheduler_stale_minutes`. Verified directly against
`backend/routes/console.php`'s `Schedule::command(...)` registrations
— **9 jobs, not 8**: the source runbook predates
`workflow-history:archive-old`, added alongside a
`workflow_history_hot_months`/`workflow_history_archive_batch_size`
config pair not documented in the source retention policy either.

| Command                         | Schedule     | Purpose                                                                  | Config keys                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| ------------------------------- | ------------ | ------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `workflow:expire-engine-claims` | Every minute | Release support claims past TTL (`claim_expires_at`); notify CBY admins  | none — the command only filters on the persisted `claim_expires_at` column; the TTL is baked in earlier, at claim/heartbeat time, by `EngineClaimService::ttlMinutes()` reading the admin `support_claim_ttl` setting via `SettingResolver` (see [`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)). `workflow.support_claim_ttl_minutes` is a legacy config key read only by the `EngineRequestScenarioBuilder` seeder, not by this command |
| `workflow:notify-sla-signals`   | Hourly       | Scan and dispatch SLA warning notifications                              | —                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `notifications:purge-old`       | Daily 02:10  | Purge notification recipients past retention horizon                     | `notification_unread_max_days`, `notification_read_days`                                                                                                                                                                                                                                                                                                                                                                                                              |
| `reports:purge-old-exports`     | Daily 02:20  | Delete expired export files; mark `ReportExport` as `EXPIRED`            | `export_file_days`                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `documents:archive-superseded`  | Daily 02:30  | Remove physical files for superseded documents past horizon; keep DB row | `superseded_document_file_days`                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `documents:purge-orphans`       | Daily 02:40  | Delete orphan files under `engine-requests/` with no document row        | `orphan_file_grace_hours`                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| `audit:archive-old`             | Daily 03:00  | Move audit rows older than hot horizon to `audit_log_archives`           | `audit_hot_months`, `audit_archive_batch_size`                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `workflow-history:archive-old`  | Daily 03:10  | Move `workflow_history` rows older than hot horizon to archive           | `workflow_history_hot_months`, `workflow_history_archive_batch_size`                                                                                                                                                                                                                                                                                                                                                                                                  |
| `ops:check-scheduler-health`    | Daily 06:00  | Flag commands that missed their run window                               | `scheduler_stale_minutes`                                                                                                                                                                                                                                                                                                                                                                                                                                             |

**Manual re-run (idempotent):**

```bash
cd backend
php artisan workflow:expire-engine-claims
php artisan workflow:notify-sla-signals
php artisan notifications:purge-old
php artisan reports:purge-old-exports
php artisan documents:archive-superseded
php artisan documents:purge-orphans
php artisan audit:archive-old
php artisan workflow-history:archive-old
php artisan ops:check-scheduler-health
```

After a manual run, confirm a new row in `scheduler_run_logs` with
`status = success` for that command.

---

## Failure surfaces and log keys

| Failure class             | Log key                            | Typical cause                                               |
| ------------------------- | ---------------------------------- | ----------------------------------------------------------- |
| Claim sweep (per-request) | `ops.claim_sweep.failed`           | DB lock, missing request, notification dispatch error       |
| Report export job         | `ops.report_export.failed`         | Disk full, CSV generation error, queue worker down          |
| Notification dispatch     | `ops.notification_dispatch.failed` | Mail/queue failure, invalid recipient                       |
| Stage hook rollback       | `ops.stage_hook.failed`            | Hook threw during workflow transition                       |
| Stale scheduler           | `ops.scheduler_stale.failed`       | Cron/scheduler stopped, or command crashed before heartbeat |

**Queue failures:**

```bash
php artisan queue:retry <id>      # single job
php artisan queue:retry all       # all failed (use with care)
php artisan queue:flush           # discard after investigation
```

---

## Recovery procedures

### Scheduler stopped or stale heartbeats

**Symptoms:** `/admin/health` shows `stale: true`; claims not
expiring; retention jobs not running.

1. Confirm the Laravel scheduler cron is active on the app server:
   ```
   * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
   ```
2. Run `php artisan schedule:list` — verify all 9 commands above
   appear.
3. Manually run the stale command(s) and confirm heartbeat rows
   update.
4. If cron is correct but commands fail, read
   `scheduler_run_logs.error_message`.
5. Run `php artisan ops:check-scheduler-health` to clear stale alerts
   after recovery.

### Queue worker down

**Symptoms:** `failed_jobs_count` rising; exports stuck in
`PENDING`/`PROCESSING`; notifications not delivered.

1. Restart queue workers (`php artisan queue:work` or
   supervisor/systemd unit).
2. Inspect recent `failed_jobs` entries.
3. Retry or flush after fixing the underlying error (disk, mail, DB).

### Mail transport failure

**Symptoms:** notifications queued but not received; mail-related
exceptions in `failed_jobs` or logs.

1. Check health payload `data.mail.driver` matches expected transport.
2. Verify `.env` mail settings (`MAIL_MAILER`, credentials, host).
3. Test with `php artisan tinker` or a controlled notification in
   staging.
4. Failed notification jobs log `ops.notification_dispatch.failed`.

---

## Stuck support claim

**Symptoms:** request shows claimed by an inactive user;
`claim_expires_at` in the past but claim not released; Support
Committee cannot claim.

1. Check `/admin/health` — is `workflow:expire-engine-claims` stale?
2. If scheduler is healthy, inspect the request:
   `claim_expires_at`/`claimed_by`.
3. Manually run: `php artisan workflow:expire-engine-claims`.
4. If a specific request fails, grep logs for `ops.claim_sweep.failed`
   with `request_id`.
5. After repeated per-request failures, investigate DB constraints or
   `EngineClaimService` errors; escalate if manual release is required
   (DB-level fix only with CBY approval).

**Prevention:** the claim TTL is admin-configurable
(`support_claim_ttl`, default 15 minutes) — see
[`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)'s
Claim lifecycle section for the exact source, not a Redis key. A
healthy scheduler auto-releases expired claims via
`workflow:expire-engine-claims`.

---

## Stuck or failed report export

| Status                   | Meaning             | Operator action                                                     |
| ------------------------ | ------------------- | ------------------------------------------------------------------- |
| `PENDING` / `PROCESSING` | Job not finished    | Check queue worker; inspect `failed_jobs` for the export job        |
| `FAILED`                 | Generation error    | User may retry from UI; ops inspect `ops.report_export.failed` logs |
| `EXPIRED`                | File past retention | **Not recoverable** — user must request a new export                |
| `COMPLETED`              | File available      | Normal — download via reports UI                                    |

**Stuck in PROCESSING:**

1. Confirm queue worker is running.
2. Check `failed_jobs` for the export job UUID.
3. If the worker died mid-job, retry: `php artisan queue:retry <id>`.
4. If the row is orphaned (PROCESSING with no job), set status to
   `FAILED` only after CBY ops approval and document the change in
   audit notes.

**Expired download:** API returns HTTP 422 `EXPORT_EXPIRED`. Direct
the user to generate a new export.

---

## Archived audit log restore

Audit rows older than `audit_hot_months` (default 12) move from
`audit_logs` to `audit_log_archives`. Archive is move-only — no hard
delete within the retention horizon. Workflow history rows follow the
same pattern via `workflow-history:archive-old` and
`workflow_history_hot_months`.

### Search archive

```sql
SELECT * FROM audit_log_archives WHERE source_id = :original_audit_log_id;

SELECT * FROM audit_log_archives
WHERE subject_type = 'App\\Models\\EngineRequest'
  AND subject_id = :request_id
ORDER BY created_at DESC;
```

### Manual restore to hot DB (`CBY_ADMIN` + documented ops ticket only)

Restore is manual SQL, not an automated command.

1. Locate the row in `audit_log_archives` by `source_id`.
2. Verify `source_id` is not already present in `audit_logs`.
3. Insert back into `audit_logs` using the archived columns
   (`user_id`, `action`, `subject_type`, `subject_id`, `metadata`,
   `old_values`, `new_values`, `created_at`, etc.). Do not copy
   `archived_at` or the archive table's `id`.
4. Optionally remove from `audit_log_archives` after successful
   restore (keep a ticket reference).
5. Record an audit-restore note via normal audit logging.

**Escalation:** if restore is needed for a compliance investigation,
involve CBY compliance before modifying archive tables.

---

## Retention job failures

| Command                        | If it fails                        | Data impact                                               |
| ------------------------------ | ---------------------------------- | --------------------------------------------------------- |
| `notifications:purge-old`      | Notifications accumulate           | No compliance impact (notifications ≠ audit trail)        |
| `reports:purge-old-exports`    | Export files remain on disk longer | Disk usage grows; re-run when fixed                       |
| `documents:purge-orphans`      | Orphan files remain                | Disk usage grows; safe to re-run                          |
| `documents:archive-superseded` | Superseded files remain on disk    | Disk usage grows; metadata intact                         |
| `audit:archive-old`            | Hot `audit_logs` grows             | **Priority** — re-run promptly; batch size limits per run |
| `workflow-history:archive-old` | Hot `workflow_history` grows       | Re-run promptly; batch size limits per run                |

All retention commands are idempotent — a re-run with no eligible rows
affects 0 records.

---

## Escalation

| Level | Contact                  | When                                                       |
| ----- | ------------------------ | ---------------------------------------------------------- |
| L1    | CBY platform on-call     | Stale scheduler, queue failures, stuck claims/exports      |
| L2    | CBY infrastructure / DBA | DB-level issues, audit restore, mail relay                 |
| L3    | CBY compliance           | Audit retention policy, archive restore for investigations |

Replace with actual CBY ops contact details before production
handoff — not yet finalized in source material.

---

## Retention: data classes and horizons

Full principles, per-class policy tables, and the CBY confirmation
checklist live in `docs/operations/retention-policy.md` (not yet
archived, still the source of full detail) — summarized here for
convenience, config keys re-verified against
`backend/config/retention.php`:

| Data class                    | Hot duration                               | Delete policy                                               | Config key(s)                                                        |
| ----------------------------- | ------------------------------------------ | ----------------------------------------------------------- | -------------------------------------------------------------------- |
| Audit logs (`audit_logs`)     | 12 months (`audit_hot_months`)             | Never delete within horizon; archive only                   | `audit_hot_months`, `audit_archive_batch_size`                       |
| Workflow history              | 12 months (`workflow_history_hot_months`)  | Never delete within horizon; archive only                   | `workflow_history_hot_months`, `workflow_history_archive_batch_size` |
| Notifications (unread)        | 90 days (`notification_unread_max_days`)   | Physical delete after horizon — operational, not compliance | `notification_unread_max_days`                                       |
| Notifications (read/archived) | 365 days (`notification_read_days`)        | Physical delete after horizon                               | `notification_read_days`                                             |
| Export files                  | 30 days (`export_file_days`)               | File deleted; `ReportExport` row kept `EXPIRED`             | `export_file_days`                                                   |
| Superseded documents          | 90 days (`superseded_document_file_days`)  | File archived; metadata always retained                     | `superseded_document_file_days`                                      |
| Orphan files                  | 24 hours grace (`orphan_file_grace_hours`) | Never deletes files referenced by a document record         | `orphan_file_grace_hours`                                            |

Security/compliance notifications (`severity = critical` or type
prefix `security.`) are exempt from the notification purge until CBY
policy confirms a separate horizon.

**CBY confirmation still required before enabling enforcement in
production** (unchanged from the source policy — this is a policy
decision, not a technical fact this consolidation can resolve):
regulatory minimum for audit/workflow-history retention, DB-level
immutability requirement, notification/export/document horizon
sign-off, and any hard-delete deviation from archive-first.

---

## Production deployment checklist

Verify each item against the actual target environment/infrastructure
— none of these were checked by the functional/RBAC audit that
originated this checklist, which ran against local dev/MySQL:

1. [ ] `APP_ENV=production`
2. [ ] `APP_DEBUG=false`
3. [ ] `APP_DEMO_ROLE_SWITCH=false`
4. [ ] `NUXT_PUBLIC_VISUAL_BYPASS` unset or `false`
5. [ ] Production demo seeding hard-blocked — confirm
       `demo.seed_demo_data`/`demo.allowed_seed_environments`
       (`backend/config/demo.php`) excludes `production` (verified
       directly: `allowed_environments` and `allowed_seed_environments`
       both currently list only `local`/`staging`/`testing`)
6. [ ] Database backup and restore tested
7. [ ] Deployment rollback documented and tested
8. [ ] Queue workers and Horizon configuration verified
9. [ ] Redis persistence and failure behavior understood
10. [ ] Scheduler and cron execution verified
11. [ ] Storage and private-document permissions verified
12. [ ] Logging, alerting, and slow-query monitoring enabled
13. [ ] Production secrets and encryption keys managed securely
14. [ ] Rate limiting and trusted-proxy configuration verified
15. [ ] HTTPS, cookies, Sanctum domains, CORS, and session settings
        correct
16. [ ] Database migrations tested on a production-like snapshot
17. [ ] Health checks and post-deployment smoke tests prepared
18. [ ] Production workflow version and permissions confirmed (the
        intended `PUBLISHED`/`ARCHIVED` versions are in the production DB
        — not a dev-only state)
19. [ ] System-admin recovery path tested
20. [ ] No local or synthetic seed data included unintentionally

---

## Rollback and incident-response checklist

- [ ] Deployment rollback procedure documented and rehearsed (item 7
      above)
- [ ] Database rollback/point-in-time-restore procedure tested
      independent of application rollback
- [ ] Workflow version rollback path understood: if a published
      version has a defect discovered post-launch, re-archiving it and
      re-publishing the prior version (or a corrected new version) via
      the Designer's clone→correct→publish→archive lifecycle — verify
      this specific reversion path against the target environment
      before relying on it in an incident
- [ ] Incident escalation path defined for: authorization bypass
      discovered in production, workflow transition stuck/corrupted
      mid-flight, claim-lock contention beyond tested concurrency
      levels
- [ ] Audit-log/`workflow_history` integrity check procedure exists
      for post-incident forensics
- [ ] Contact/ownership for each externally-facing system (backend
      API, frontend Nuxt app, MySQL/Redis infra) documented for
      on-call response

---

## What this document removes from the source material

Not carried forward from `docs/operations/runbook.md`: the `GET
/api/v1/admin/health` path, which is inaccurate — `admin/health` is
registered unversioned (`GET /api/admin/health`), verified directly
against `backend/routes/api.php`. No Redis-backed claim-TTL language
was present in the source runbook to begin with — its claim-TTL
prevention note already correctly pointed at the scheduled command;
this document tightens that note to link the admin-setting source
explicitly rather than leaving TTL as an unsourced "15 minutes."
