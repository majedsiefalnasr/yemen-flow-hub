# WP-13 — Retention + Operations

**Status:** Draft for review (Phase 6) — parallel-capable; **needs CBY policy input** for retention horizons
**Source of authority:** `2026-07-05-feature-review-notes.md` — Phase 4 SW-10 (retention) + SW-11 (operational monitoring)
**Traceability:** D17-N6 (audit retention + immutability), D18-N4 (export file retention), D19-N6 (notification retention), D10-N6/N7 (orphan-file + document storage lifecycle), D22-N4 (financing capacity interim note). SW-11 monitoring baseline.
**Dependencies:** none hard (parallel-capable). Consumes WP-8 document-replacement model (F-10) for physical cleanup rules.
**Enables:** defensible storage lifecycle; operational visibility (no silent degradation).
**Overall risk:** low — additive jobs + monitoring; retention horizons are the policy-sensitive part.

## Change classification

| Item | Kind |
|------|------|
| RT-1 retention policy document + horizons | Policy + approved functional (SW-10) |
| RT-2 scheduled cleanup jobs | New operational requirement (SW-10) |
| RT-3 audit retention + archival boundary | Approved functional (D17-N6) |
| RT-4 notification retention | Approved functional (D19-N6) |
| RT-5 export file retention | Approved functional (D18-N4) |
| RT-6 document + orphan-file lifecycle | Approved functional (D10-N6/N7) |
| OM-1 failed-job visibility | New operational requirement (SW-11) |
| OM-2 scheduler heartbeat | New operational requirement (SW-11) |
| OM-3 failure-surface visibility (hooks/mail/export/SLA/claim) | New operational requirement (SW-11) |
| OM-4 admin health surface | New operational requirement (SW-11) |
| OM-5 minimal runbook | Documentation |

**Explicitly out of scope:** two-layer visibility (WP-7); placebo settings (WP-11); legacy cleanup (WP-14).

---

## Part A — Retention (SW-10)

### RT-1 — Retention policy document

**Required:** `docs/operations/retention-policy.md` defining, per data class, hot-DB duration, archive schedule, archive search/restore path, archive access control. **Needs CBY policy input** for the audit horizon (regulatory minimum). Draft default horizons below; confirm against CBY rules before enforcement.

### RT-2 — Scheduled cleanup jobs

**Required:** one Laravel scheduler entry per cleanup, each idempotent, logged, auditable, and failure-visible (OM-1). Runs:
- `workflow:expire-engine-claims` (exists — verify failure visibility).
- `notifications:purge-old` (RT-4).
- `reports:purge-old-exports` (RT-5).
- `documents:archive-superseded` / `documents:purge-orphans` (RT-6).
- `audit:archive-old` (RT-3) — archive, never delete within horizon.

### RT-3 — Audit retention + archival boundary (D17-N6)

**Current:** `audit_logs` append-only, app-layer immutability only; grows unbounded; no retention/archive.
**Required:**
- Hot-DB horizon (default suggestion: 12 months — **CBY confirm**); older rows archived (cold storage / separate table / export bundle).
- Archive searchable/restorable; archive access restricted + audited.
- DB-level immutability considered (no UPDATE/DELETE grants for the app DB user on audit tables, or triggers) if CBY policy requires — **infra decision**.
- Archive/export operations themselves audited.
- **Notifications are never the audit trail** — `audit_logs` is the compliance record.
**Acceptance:** audit rows age out of hot DB per horizon; archived retrievable; immutability holds.

### RT-4 — Notification retention (D19-N6)

**Required:** unread kept until read/archive or long max (e.g. 90 days); read/archived kept configurable (6–12 months); security/compliance-relevant kept longer if policy requires. Scheduled purge/archive job. Notifications ≠ audit trail.
**Acceptance:** notifications age out per state-based policy.

### RT-5 — Export file retention (D18-N4)

**Current:** generated export files on private disk indefinitely.
**Required:** configurable retention (default 30 days); physical file deleted/archived after expiry; `ReportExport` row kept as history with `EXPIRED` status; expired download → clear error; scheduled cleanup; cleanup auditable/logged.
**Acceptance:** export files age out; rows retained as history.

### RT-6 — Document + orphan-file lifecycle (D10-N6/N7)

**Required:**
- Orphan files (stored but row-create failed) cleaned by scheduled job; never deletes files referenced by document records.
- Superseded documents (WP-8 F-10): physical old-file cleanup per retention policy (archive/compress/cheaper storage), metadata + audit always retained.
- Terminal-request documents retained per audit/evidence policy; physical storage lifecycle documented.
**Acceptance:** orphan files purged; superseded physical files aged per policy; evidence metadata retained.

---

## Part B — Operational Monitoring (SW-11)

### OM-1 — Failed-job visibility

**Required:** failed queue jobs (notifications dispatch, report exports, any async) visible to operators — failed-job log/retention, alerting hook (email/Slack/log monitor), not silent. Laravel `failed:jobs` table retained per policy.
**Acceptance:** a failed dispatch/export is observable by ops.

### OM-2 — Scheduler heartbeat

**Required:** scheduler heartbeat / missed-schedule detection — each scheduled command (claim sweep, SLA scan, retention jobs) records last-run; a monitor alerts on missed schedules (e.g. a daily check that expected commands ran).
**Acceptance:** a stopped scheduler is detected, not silent.

### OM-3 — Failure-surface visibility

**Required:** explicit visibility (log + alert) for:
- Notification dispatch failures.
- Report export job failures.
- SLA scan failures.
- Claim sweep failures.
- Mail transport failures.
- Stage hook failures (`STAGE_HOOK_FAILED` already rolls back; ensure the event is observable/alertable).
**Acceptance:** each failure class produces an observable signal.

### OM-4 — Admin health surface

**Required:** admin-facing health/status surface (or documented external-monitor integration) showing: scheduler heartbeat, queue depth/failures, last retention-run timestamps, mail transport status. Sensitive internals stay in logs/monitoring, not exposed to normal users.
**Acceptance:** an admin can see background-process health in one place.

### OM-5 — Minimal runbook

**Required:** `docs/operations/runbook.md` covering: what each scheduled job does, what to do when it fails, how to restore archived audit, how to handle a stuck claim/export, contact/escalation. Minimal — no observability over-engineering.
**Acceptance:** runbook exists and covers the failure surfaces.

---

## Business rules (consolidated)

1. Every data class has a retention horizon; hot DB is bounded; archives are retrievable.
2. Audit logs are the compliance record; notifications are operational and age out.
3. Cleanup jobs are idempotent, logged, auditable, and failure-visible.
4. No background failure is silent — scheduler, queue, hooks, mail, scans all observable.
5. Minimal baseline first; observability grows with confirmed need.

## Error cases

| Case | Response |
|------|----------|
| Expired export download | 422 `EXPORT_EXPIRED` |
| Retention job failure | logged + alertable (OM-1/OM-3) |
| Missed schedule | heartbeat alert (OM-2) |

## Acceptance criteria

1. Retention policy doc exists; horizons CBY-confirmed; per-class cleanup jobs scheduled + idempotent + logged.
2. Audit ages to archive per horizon; retrievable; immutability holds.
3. Notifications/exports/documents/orphans age per policy.
4. Every failure surface observable; scheduler heartbeat detects missed runs; admin health surface renders.
5. Runbook covers failure handling.
6. All WP-0 suites green (no behavior change to product logic).

## Test cases

- **Feature (jobs):** each cleanup job idempotent (re-run safe); purge respects horizons; archive searchable; orphan cleanup never deletes referenced files.
- **Integration (monitoring):** failed job → observable; missed schedule → heartbeat alert; health surface renders status.
- **Regression:** product logic unchanged; only lifecycle/ops added.

## Manual verification steps

1. Run each cleanup job twice → idempotent; rows/files aged correctly.
2. Force a job failure → observable in health surface + alert path.
3. Stop scheduler → heartbeat detects missed run.
4. Attempt expired-export download → clear error.
5. Restore an archived audit row → retrievable per access control.

## Rollback considerations

Retention jobs are additive (disable schedule = stop aging). Archive moves are reversible (rows/files restorable). Monitoring is additive. The only caution: an overly-aggressive purge horizon could delete data prematurely — confirm horizons with CBY before enabling hard deletes; default to archive-over-delete where policy is unclear.

## Open questions

1. **RT-1/RT-3 audit horizon:** what is the CBY regulatory minimum for audit-log retention? Drives hot-vs-archive boundary. **Needs policy input.**
2. **RT-3 DB-level immutability:** is restricted DB-user/triggers required by CBY policy, or is app-layer append-only sufficient? **Infra/policy decision.**
3. **OM-4 admin health surface:** in-app admin panel vs external monitor integration — which does the deployment use? Recommend in-app minimal panel + documented external hook.
