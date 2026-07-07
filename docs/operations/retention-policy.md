# Retention Policy — Yemen Flow Hub

**Status:** Draft — conservative defaults; **CBY policy confirmation required** before enforcement  
**Config source:** `backend/config/retention.php` (`config('retention.*')`)  
**Traceability:** WP-13 RT-1 through RT-6; D17-N6, D18-N4, D19-N6, D10-N6/N7

---

## Principles

1. **Archive-first:** When policy is ambiguous, prefer archive/move over physical delete (`archive_first = true`). Hard deletes require explicit CBY approval.
2. **Audit trail integrity:** `audit_logs` is the compliance record. **Notifications are never the audit trail** — they are operational signals and age out independently.
3. **Hot DB is bounded:** Every data class has a hot-DB horizon; older data moves to archive/cold storage and remains retrievable.
4. **Cleanup is observable:** All scheduled retention jobs are idempotent, logged, auditable, and failure-visible (OM-1/OM-3).

---

## Data classes

### Audit logs (`audit_logs`) — RT-3

| Aspect | Policy |
|--------|--------|
| **Hot duration** | 12 months (`audit_hot_months`) — **CBY confirm regulatory minimum** |
| **Archive schedule** | Daily `audit:archive-old`; batch size 500 rows (`audit_archive_batch_size`) |
| **Archive target** | Cold storage / separate archive table / export bundle (implementation in Task 4) |
| **Search / restore** | Archived rows searchable by ops; restore path documented in runbook (OM-5) |
| **Access control** | Archive access restricted to `CBY_ADMIN`; all archive/restore operations audited |
| **Delete policy** | **Never delete** within retention horizon; archive only |

**CBY open question:** What is the regulatory minimum retention for audit logs? Draft default is 12 months hot + indefinite archive. Confirm before tightening or enabling hard deletes.

**CBY open question:** Is DB-level immutability (no UPDATE/DELETE grants for app DB user, or triggers) required, or is app-layer append-only sufficient?

---

### Notifications — RT-4

| Aspect | Policy |
|--------|--------|
| **Unread hot duration** | Up to 90 days (`notification_unread_max_days`); kept until read/archived or max reached |
| **Read/archived hot duration** | 365 days (`notification_read_days`) |
| **Archive schedule** | Daily `notifications:purge-old` |
| **Search / restore** | Not required — notifications are operational, not compliance evidence |
| **Access control** | Per-user scope; purge job runs system-wide |
| **Delete policy** | Archive-first where possible; physical delete after archive or when policy permits |

**Reminder:** Notifications are **not** the audit trail. Compliance actions are recorded in `audit_logs`.

---

### Export files (`ReportExport`) — RT-5

| Aspect | Policy |
|--------|--------|
| **Hot duration** | 30 days (`export_file_days`) |
| **Archive schedule** | Daily `reports:purge-old-exports` |
| **Row retention** | `ReportExport` row kept indefinitely with `EXPIRED` status after file removal |
| **Search / restore** | Row history searchable; expired download returns `EXPORT_EXPIRED` (422) |
| **Access control** | Download policy enforced by existing report permissions |
| **Delete policy** | Physical file deleted/archived after expiry; metadata row retained |

---

### Documents — superseded & terminal — RT-6

| Aspect | Policy |
|--------|--------|
| **Superseded file hot duration** | 90 days (`superseded_document_file_days`) after supersession (WP-8 F-10) |
| **Orphan grace period** | 24 hours (`orphan_file_grace_hours`) before orphan cleanup |
| **Archive schedule** | Daily `documents:archive-superseded`, `documents:purge-orphans` |
| **Metadata retention** | Document metadata + audit entries **always retained** |
| **Terminal-request documents** | Retained per audit/evidence policy; physical lifecycle follows archive-first |
| **Search / restore** | Metadata always in hot DB; physical file restore from archive per runbook |
| **Access control** | Existing document download policy; archive ops restricted to ops/CBY_ADMIN |
| **Delete policy** | Orphan cleanup **never** deletes files referenced by document records |

---

### Failed queue jobs (`failed_jobs`) — OM-1

| Aspect | Policy |
|--------|--------|
| **Hot duration** | Retain per Laravel default until manually resolved or purged by ops |
| **Archive schedule** | No automatic purge in WP-13 Task 1; visibility via admin health surface (OM-4) |
| **Search / restore** | Query `failed_jobs` table; retry via `php artisan queue:retry` |
| **Access control** | Ops/CBY_ADMIN only |
| **Delete policy** | Manual purge after investigation; archive-first if export-before-delete is adopted later |

---

## Configuration reference

All horizons are overridable via environment variables (see `backend/config/retention.php`):

| Key | Default | Env var |
|-----|---------|---------|
| `archive_first` | `true` | `RETENTION_ARCHIVE_FIRST` |
| `audit_hot_months` | `12` | `RETENTION_AUDIT_HOT_MONTHS` |
| `audit_archive_batch_size` | `500` | `RETENTION_AUDIT_BATCH_SIZE` |
| `export_file_days` | `30` | `RETENTION_EXPORT_FILE_DAYS` |
| `notification_unread_max_days` | `90` | `RETENTION_NOTIFICATION_UNREAD_MAX_DAYS` |
| `notification_read_days` | `365` | `RETENTION_NOTIFICATION_READ_DAYS` |
| `superseded_document_file_days` | `90` | `RETENTION_SUPERSEDED_DOC_DAYS` |
| `orphan_file_grace_hours` | `24` | `RETENTION_ORPHAN_GRACE_HOURS` |

Scheduler staleness thresholds (`scheduler_stale_minutes`) are defined for OM-2 heartbeat monitoring.

---

## CBY confirmation checklist

Before enabling enforcement (scheduled purge/archive jobs in production):

- [ ] **Audit regulatory minimum** — confirm hot and archive horizons with CBY compliance
- [ ] **DB-level immutability** — confirm whether infra must enforce append-only at DB layer
- [ ] **Notification read horizon** — confirm 365-day default vs 6–12 month policy range
- [ ] **Export file retention** — confirm 30-day default meets operational needs
- [ ] **Document evidence retention** — confirm terminal-request physical file lifecycle
- [ ] **Hard delete approval** — any deviation from archive-first requires written CBY sign-off

---

## Scheduled jobs (RT-2)

| Command | Data class | Task |
|---------|------------|------|
| `audit:archive-old` | Audit logs | Task 4 |
| `notifications:purge-old` | Notifications | Task 5 |
| `reports:purge-old-exports` | Export files | Task 6 |
| `documents:archive-superseded` | Superseded documents | Task 7 |
| `documents:purge-orphans` | Orphan files | Task 7 |
| `workflow:expire-engine-claims` | Claims (existing) | Verify OM visibility |
