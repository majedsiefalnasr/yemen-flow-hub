# User Stories Gap Analysis — USER-STORIES.md vs Current Implementation

**Date:** 2026-05-21
**Analyst:** Claude Code (Opus 4.7), invoked by user request
**Source spec:** `lovable/USER-STORIES.md` (1,163 lines, 8 roles, ~30 stories, 30 analyst-gap items)
**Companion doc:** `docs/08-prototype-gap-analysis.md` (visual-parity focus, 2026-05-19)
**Decided direction (this session):** **Hybrid — keep AGENTS.md canonical enums; port lovable UI; add missing stages on top of current enums.**

> This document is **report-only**. No code was modified. Use the sprint plan in §6 to drive story-by-story execution in subsequent sessions.

---

## 0 · TL;DR

The current Yemen Flow Hub implementation is **substantially complete** against USER-STORIES.md — far more so than the spec text would suggest. Of the 30 stories across 8 roles, **roughly 26 are implemented in full or substantially**, **3 are partial**, and **1 is intentionally remapped** (Story 5.3 "Bank Admin manages bank users" → already done via `/staff` rather than `/admin/bank-users`).

The two real categories of remaining work are:

1. **Visual parity** with lovable screenshots — already tracked in `08-prototype-gap-analysis.md`, ongoing.
2. **Workflow-completeness gaps** — five concrete additions surfaced by the USER-STORIES walk-through that are not in scope of doc 08:
   - **G1** A distinct `bank_returned` cycle (lovable has it; current collapses bank-rejection and support-rejection into the same `DRAFT_REJECTED_INTERNAL` status).
   - **G2** A `support_returned` cycle (same root cause as G1).
   - **G3** Claim-release notification to a supervisor and/or stale-claim audit visibility (claim release currently silent).
   - **G4** "Copy and resubmit" action on terminal-rejected requests (lovable §15.10).
   - **G5** Duplicate-invoice cross-bank detection (currently scoped to own bank only — lovable §15.13).

Section 15 of USER-STORIES.md ("Analyst Notes — What Is Missing") contains 30 items; **most are intentionally out of scope per `AGENTS.md` and the docs/ source-of-truth set** (e.g. SLA timers, MT103 parsing, quorum rules, external customs integration). They are listed in §5 with explicit triage so we don't have to re-evaluate them every sprint.

---

## 1 · Enum & role mapping (USER-STORIES → AGENTS.md canonical)

USER-STORIES.md uses lower_snake_case names. AGENTS.md (the source of truth) uses UPPER_SNAKE_CASE. The **chosen hybrid keeps AGENTS.md naming everywhere except UI labels.**

### 1.1 Roles

| USER-STORIES.md | AGENTS.md canonical | Notes |
|---|---|---|
| `platform_admin` | `CBY_ADMIN` | 1:1 |
| `bank_admin` | `BANK_ADMIN` | 1:1 |
| `bank_intake` | `DATA_ENTRY` | 1:1 |
| `bank_reviewer` | `BANK_REVIEWER` | 1:1 |
| `bank_swift` | `SWIFT_OFFICER` | 1:1 |
| `support_member` | `SUPPORT_COMMITTEE` | 1:1 |
| `executive_member` | `EXECUTIVE_MEMBER` | 1:1 |
| `committee_manager` | `COMMITTEE_DIRECTOR` | 1:1 |

No orphans, no merges — full bijection. ✅

### 1.2 Stages

| USER-STORIES.md (16 stages) | AGENTS.md canonical (18 statuses) | Notes |
|---|---|---|
| `draft` | `DRAFT` | |
| `bank_submitted` | `SUBMITTED` | |
| `bank_internal_review` | `BANK_REVIEW` | |
| `bank_returned` | **(no equivalent)** | Currently collapses with `support_returned` into `DRAFT_REJECTED_INTERNAL` — **gap G1**. |
| `bank_rejected` | **(no equivalent)** | Currently sent to `DRAFT_REJECTED_INTERNAL` via `bank_reject` transition (terminal in lovable, recoverable in current). **Behavioral mismatch.** |
| `bank_approved` | `BANK_APPROVED` | |
| `support_review` | `SUPPORT_REVIEW_IN_PROGRESS` | Plus `SUPPORT_REVIEW_PENDING` (auto-chained from `BANK_APPROVED`) — extra granularity in current. |
| `support_returned` | **(no equivalent)** | Currently uses `bank_return_after_support_reject` → `DRAFT_REJECTED_INTERNAL`. **Gap G2.** |
| `support_rejected` | `SUPPORT_REJECTED` | Terminal in lovable; current has recovery via `bank_return_after_support_reject`. **Behavioral mismatch.** |
| `support_approved` | `SUPPORT_APPROVED` | |
| `swift_attached` | `SWIFT_UPLOADED` | Plus `WAITING_FOR_SWIFT` auto-chained from `SUPPORT_APPROVED`. |
| `executive_voting` | `EXECUTIVE_VOTING_OPEN` | Plus `WAITING_FOR_VOTING_OPEN` and `EXECUTIVE_VOTING_CLOSED` for stronger lifecycle separation. |
| `executive_approved` | `EXECUTIVE_APPROVED` | |
| `executive_rejected` | `EXECUTIVE_REJECTED` | |
| `customs_released` | `CUSTOMS_DECLARATION_ISSUED` | |
| `completed` | `COMPLETED` | |

**Key takeaways:**

- The canonical enum is **more granular** than USER-STORIES.md (18 vs 16) — it splits SWIFT/voting waiting states explicitly. That is an improvement; keep it.
- USER-STORIES.md treats `bank_rejected` and `support_rejected` as **terminal**. The current backend treats `DRAFT_REJECTED_INTERNAL` as recoverable (intake can edit + resubmit). **This is the single largest semantic divergence** and shapes G1/G2/G4 below.

### 1.3 Recommended hybrid encoding for the missing stages

Rather than rename UPPER → lower, we add three **new** canonical statuses to express the missing semantics, plus keep `DRAFT_REJECTED_INTERNAL` for current recoverable behavior:

| Proposed new status | Replaces lovable | Purpose |
|---|---|---|
| `BANK_RETURNED` | `bank_returned` | Reviewer returned with comment, intake can edit and resubmit. Non-terminal. |
| `SUPPORT_RETURNED` | `support_returned` | Support member returned with comment, intake can edit and resubmit. Non-terminal. |
| `BANK_REJECTED` | `bank_rejected` | Reviewer rejected. Terminal, no resubmit (use "copy and resubmit" instead — G4). |

`DRAFT_REJECTED_INTERNAL` can stay as a legacy alias for one release, then deprecate.

**Migration impact:** all 33+ existing completed stories built on the current enum — adding new values is additive and safe; renaming would be destructive. The hybrid avoids the destructive path.

---

## 2 · Per-role gap matrix

Legend: ✅ implemented · 🟡 partial (cite gap) · ❌ missing · ⚪ out of scope (cite reason)

### 2.1 Bank Intake / `DATA_ENTRY` (USER-STORIES §3)

| Story | Status | Evidence / gap |
|---|---|---|
| 3.1 Create new request (4-step wizard) | ✅ | `frontend/app/components/wizard/RequestWizard.vue` + `WizardStep1..4.vue`; `frontend/app/composables/useRequestWizard.ts`; backend wizard fields exist (`add_wizard_fields_to_import_requests` migration). |
| 3.2 Save as draft & continue later | ✅ | `RequestWizard` exposes "حفظ كمسودة" path; backend `POST /api/requests` with status=DRAFT. |
| 3.3 Edit and resubmit a returned request | 🟡 | `frontend/app/pages/requests/[id]/edit.vue` exists; backend transition `submit` accepts `DRAFT_REJECTED_INTERNAL`. **Gap:** does not distinguish "returned from bank" vs "returned from support" in the UI banner. Needs G1 + G2. |
| 3.4 View my requests queue (buckets) | ✅ | `frontend/app/pages/requests/index.vue` (1,113 lines) uses `STATUS_PROGRESS`/`ROLE_BUCKETS`; story 7.3 already shipped buckets. |

### 2.2 Bank Reviewer / `BANK_REVIEWER` (USER-STORIES §4)

| Story | Status | Evidence / gap |
|---|---|---|
| 4.1 Review submitted (approve / return / reject) | 🟡 | `workflow.bank-approve` / `workflow.bank-reject` shipped; **return-to-intake-with-comment is implicit** (`bank_reject` → `DRAFT_REJECTED_INTERNAL`) rather than explicit. Needs G1. |
| 4.1 SOD: cannot approve own creation | ✅ | Story 2.3 review patches enforced `bank_reject` self-review guard. |
| 4.2 View all bank requests | ✅ | `BankReviewerDashboard.vue` + `pages/requests/index.vue` with ROLE_BUCKETS. |

### 2.3 Bank Admin / `BANK_ADMIN` (USER-STORIES §5)

| Story | Status | Evidence / gap |
|---|---|---|
| 5.1 Full lifecycle (intake + reviewer in one role) | ✅ | Backend `BANK_ADMIN` granted both privileges; story 6.3.1/2/4 shipped dashboard + KPIs + staff. |
| 5.2 Manage bank merchants | ✅ | `pages/merchants.vue` (1,042 lines); `useMerchants.ts`; `MerchantController.php`. |
| 5.3 Manage bank users | ✅ | `pages/staff.vue` (880 lines) — implemented under `/staff` instead of `/admin/bank-users` route from USER-STORIES.md. **Naming difference only.** |
| 5.4 SWIFT upload (as admin) | ✅ | `BANK_ADMIN` covered by `swift_upload` transition via role allowlist (verify in policy). |

### 2.4 Bank SWIFT / `SWIFT_OFFICER` (USER-STORIES §6)

| Story | Status | Evidence / gap |
|---|---|---|
| 6.1 Upload MT103 PDF | ✅ | `pages/requests/[id]/swift.vue` (564 lines); `DocumentController::uploadSwift`; story 3.3 shipped immutability + status guard. |
| 6.1 Data lock after upload | ✅ | `LockedBanner.vue` + backend `isTerminal()` and policy. |
| 6.2 SWIFT queue view | ✅ | `SwiftOfficerDashboard.vue`. |
| 6.1 MT103 schema validation | ⚪ | Out of scope — see §5 item 15. |

### 2.5 Support Member / `SUPPORT_COMMITTEE` (USER-STORIES §7)

| Story | Status | Evidence / gap |
|---|---|---|
| 7.1 Claim with concurrency lock | ✅ | Story 3.1 shipped `lockForUpdate`-backed claim + 15-min Redis TTL + heartbeat. |
| 7.2 Release claim | ✅ | `DELETE /api/workflow/{id}/claim-support-review`. **Gap:** no notification on release (G3). |
| 7.3 Review & approve | ✅ | `workflow.support-approve`; `SupportCommitteeDashboard.vue`. |
| 7.4 Return to bank with reason | 🟡 | Currently routes via `workflow.bank-return-after-support-reject` (post-rejection only). USER-STORIES expects a direct `support_review → support_returned` transition without going through rejection. **Gap G2.** |
| 7.5 Reject permanently | ✅ | `workflow.support-reject`. |
| 7.6 Support queue view | ✅ | `SupportCommitteeDashboard.vue` with bucket "في انتظار المراجعة" / "قيد مراجعتي" / "محجوزة من آخرين". |

### 2.6 Executive Member / `EXECUTIVE_MEMBER` (USER-STORIES §8)

| Story | Status | Evidence / gap |
|---|---|---|
| 8.1 Cast vote (approve/reject/abstain) | ✅ | `VotingService::vote`, `VotingController::vote`, story 3.5 shipped `VotingPanel.vue`. |
| 8.1 Change vote while session open | ✅ | Backend allows update; frontend optimistic UI per claude.md. |
| 8.1 Justification comment | ✅ | `request_votes` table has comment column. |
| 8.2 Voting queue view | ✅ | `ExecutiveDashboard.vue`. |

### 2.7 Committee Manager / `COMMITTEE_DIRECTOR` (USER-STORIES §9)

| Story | Status | Evidence / gap |
|---|---|---|
| 9.1 Open voting session | ✅ | `workflow.open-voting` not exposed directly; uses `voting.open` route. Story 3.4 shipped. |
| 9.2 Cast own vote | ✅ | Inherits `EXECUTIVE_MEMBER` permissions per AGENTS.md. |
| 9.3 Finalize voting (incl. tie-break) | ✅ | `VotingService::finalize()` with `lockForUpdate` + tie-break; story 3.4 patches applied. Default-reject on manager abstain ✅. |
| 9.4 Issue customs declaration (`CD-2026-####`) | ✅ | Story 3.6 shipped advisory-lock + DomPDF RTL declaration; `customs/{id}/print` page (555 lines). |
| 9.5 Customs management page | ✅ | `pages/customs/index.vue` (347 lines) with "ready" + "recent" tabs. |

### 2.8 Platform Admin / `CBY_ADMIN` (USER-STORIES §10)

| Story | Status | Evidence / gap |
|---|---|---|
| 10.1 Manage banks (entities) | ✅ | `pages/admin/entities.vue` (1,122 lines) + `BankController.php`. |
| 10.2 Manage CBY staff | ✅ | `pages/admin/cby-staff.vue` (990 lines). |
| 10.3 Manage role permissions (editable matrix) | 🟡 | `pages/admin/roles.vue` (409 lines) renders the matrix. **Verify:** confirm it's editable + persists (lovable's is read-only; current may be richer or matched). |
| 10.4 Manage document rules per stage | ✅ | `pages/admin/workflow-docs.vue` (582 lines) + `DocumentTypeController.php`. |
| 10.5 Manage all merchants platform-wide | ✅ | `pages/merchants.vue` (1,042 lines) — CBY view shows all banks. |
| 10.6 View system-wide reports + KPIs | ✅ | Story 7.8 shipped 4 chart components + 6 backend analytics fields + 5-KPI strip. |
| 10.6 Export PDF/Excel | ✅ | `ReportController::exportWorkflow` / `exportBank` (CSV/PDF), story 5.6. |
| 10.7 Audit log (3 tabs: activity / duplicates / risk) | ✅ | `AuditController::index/duplicates/riskIndicators`; `pages/audit.vue` (966 lines). |

---

## 3 · Cross-role workflow gaps (USER-STORIES §11)

| Workflow | Status |
|---|---|
| A — Full happy path | ✅ |
| B — Bank return cycle | 🟡 Needs G1 (distinct `BANK_RETURNED` status + UI signaling) |
| C — Support return cycle | 🟡 Needs G2 (distinct `SUPPORT_RETURNED` status; today it's bundled into `bank_return_after_support_reject`) |
| D — Rejection paths | 🟡 Needs `BANK_REJECTED` terminal status separate from recoverable `DRAFT_REJECTED_INTERNAL` |
| E — Voting tie-breaking | ✅ |
| F — Duplicate invoice handling | 🟡 Needs G5 (cross-bank detection) |

---

## 4 · Notifications, audit, admin (USER-STORIES §12-14)

### 4.1 Notification triggers (§12.1)

All 9 triggers are wired to the notification table per story 5.3 (commit `513bced`). Severity filtering and grouping shipped.

| Gap | Detail |
|---|---|
| Severity tone mapping | ✅ — voting=purple, critical=red, etc. already in `notifications.store.ts`. |
| Bulk actions (mark-all / clear) | ✅ — `markAllRead` shipped; "clear all" verified in `pages/notifications.vue`. |
| Direct links | ✅ — `enriched payloads` from 5.3. |
| Claim release notification | ❌ — G3. |

### 4.2 Audit (§13)

| Field | Status |
|---|---|
| `userId`, `userName`, `role` (at time of action), `ts`, `action`, `ref`, `fromStage`, `toStage`, `notes` | ✅ — `audit_logs` schema + `AuditService` shipped story 4.1. |
| `ip` | 🟡 — captured at controller level via `$request->ip()` but USER-STORIES §15.22 flags this as "real client IP w/ proxy awareness." Already partially addressed; minor follow-up. |
| `device` (UA) | 🟡 — recommend adding `user_agent` column or storing in metadata JSON. Small gap. |

### 4.3 Admin & config (§14)

All six surfaces shipped via stories 6.5, 7.7, 7.8. Executive committee membership management is handled implicitly through `pages/admin/cby-staff.vue` (role = EXECUTIVE_MEMBER / COMMITTEE_DIRECTOR).

---

## 5 · Section 15 analyst-gap triage (30 items)

Each item is tagged with one of: **ADDRESSED** (already done), **IN-SCOPE** (worth doing in this hybrid plan), **OUT-OF-SCOPE** (intentional per AGENTS.md / docs), **DEFER** (good idea, but beyond current sprint horizon).

### 15.1 Critical gaps (1–5)

| # | Item | Triage | Note |
|---|---|---|---|
| 1 | No real authentication | **ADDRESSED** | Sanctum + per-email lockout + IP throttle shipped (stories 1.2, 6.4). MFA via OTP shipped 6.4. |
| 2 | No backend / persistent storage | **ADDRESSED** | Full Laravel 11 + MySQL backend exists. |
| 3 | No real file storage | **ADDRESSED** | PDF-only, sha256 checksum, private disk (story 2.2). |
| 4 | Voting session concurrency not enforced | **ADDRESSED** | `lockForUpdate` on `VotingService::vote/close/finalize` (stories 3.4, 3.5). |
| 5 | Session timeout & inactivity lock | **IN-SCOPE (light)** | Sanctum cookie expiry exists. Inactivity-lock UI banner is a small frontend story; recommend including. |

### 15.2 Workflow gaps (6–12)

| # | Item | Triage | Note |
|---|---|---|---|
| 6 | SLA timers per stage + escalations | **DEFER** | Real ops feature, needs new tables (`stage_slas`), background jobs, escalation policies. Multi-week. |
| 7 | Delegation / absence management | **DEFER** | Requires a delegation model + UX. Not in MVP. |
| 8 | Support claim silent release | **IN-SCOPE** | **G3** — small change: emit `CLAIM_RELEASED` notification to org supervisor(s) + audit entry. |
| 9 | Re-submitted requests don't flag "after support return" | **IN-SCOPE** | Resolved by G2 (distinct `SUPPORT_RETURNED` status with a banner). |
| 10 | No "copy and resubmit" on rejection | **IN-SCOPE** | **G4** — new `POST /api/requests/{id}/clone` endpoint; pre-fills wizard from rejected request. |
| 11 | Voting: no quorum rule | **DEFER** | Real-committee feature; needs config (`min_quorum`) on a new `committee_settings` table + finalize guard. Defer unless legal-mandated. |
| 12 | Committee manager absence for customs | **DEFER** | Same family as item 7. |

### 15.3 Data integrity (13–16)

| # | Item | Triage | Note |
|---|---|---|---|
| 13 | Duplicate invoice non-blocking + cross-bank | **IN-SCOPE** | **G5** — extend duplicate scan to cross-bank; configurable "warn vs block" per `system_settings`. |
| 14 | Side-by-side amount/currency check on duplicate | **IN-SCOPE (light)** | Frontend-only widget on review screen; surfaces both rows. |
| 15 | MT103 not parsed | **DEFER** | Real MT103 parsing requires SWIFT format library + offline test fixtures. |
| 16 | Customs declaration not externally verified | **OUT-OF-SCOPE** | Per docs/02-system-architecture.md, real customs-authority API is post-MVP. Current QR/signature can be a small follow-up but is independent. |

### 15.4 UX & operational (17–21)

| # | Item | Triage | Note |
|---|---|---|---|
| 17 | No bulk actions | **DEFER** | Adds bulk-state transition guards; risky around audit log shape. |
| 18 | No print/download for request detail | **IN-SCOPE (light)** | Customs print exists; add `/requests/[id]/print.vue` mirroring its styling. |
| 19 | Reports export = stubs | **ADDRESSED** | Story 5.6 shipped CSV/PDF with audit. |
| 20 | Advanced search/filtering on request list | **IN-SCOPE (light)** | `pages/requests/index.vue` already has ref/importer/invoice/bank/currency. Add date range, amount range, assigned reviewer. |
| 21 | No in-app comment thread | **DEFER** | New `request_comments` table + UI + @mention. Useful, but a small project of its own. |

### 15.5 Security & compliance (22–26)

| # | Item | Triage | Note |
|---|---|---|---|
| 22 | IP capture is stub | **IN-SCOPE (light)** | Already uses `$request->ip()`. Add trusted-proxy config in `bootstrap/app.php`. |
| 23 | Encryption at rest | **DEFER (ops)** | DB-level encryption; deployment concern, not application. |
| 24 | Rate limiting / brute force | **ADDRESSED** | Story 1.2 (per-email lockout + IP throttle). |
| 25 | Role-change audit before/after values | **IN-SCOPE (light)** | `AuditService::log()` already takes metadata; on `UserController::update`, log old+new role in payload. |
| 26 | SOD for platform admin | **DEFER** | Dual-control workflow is a non-trivial new feature. |

### 15.6 Scalability & architecture (27–30)

| # | Item | Triage | Note |
|---|---|---|---|
| 27 | In-memory cells won't scale | **ADDRESSED** | Full DB + pagination shipped. |
| 28 | Multi-language data layer | **OUT-OF-SCOPE** | Per AGENTS.md: Arabic-first; English labels are UI-only. |
| 29 | Webhooks / external integration | **DEFER** | Post-MVP. |
| 30 | Disaster recovery / backup | **OUT-OF-SCOPE (ops)** | Infrastructure concern. |

### Triage roll-up

| Bucket | Count |
|---|---|
| **ADDRESSED** | 9 |
| **IN-SCOPE (this hybrid plan)** | 9 |
| **DEFER** | 8 |
| **OUT-OF-SCOPE (intentional)** | 4 |
| **Total** | 30 |

---

## 6 · UI parity diff: lovable vs current

The visual-parity work is already tracked in `08-prototype-gap-analysis.md` and most of it is shipped (stories 6.x and 7.x). The USER-STORIES walk surfaces only a few structural deltas vs lovable:

| Lovable artifact | Current equivalent | Delta |
|---|---|---|
| `lovable/app/components/layout/AppShell.vue` (single shell) | `AppHeader.vue` + `AppSidebar.vue` + `GlobalSearch.vue` | Split into 4 files; functional parity. **No port needed.** |
| `lovable/app/components/customs/PrintablePermit.vue` (extracted) | inlined in `pages/customs/[id]/print.vue` | Cosmetic. Extracting is optional. |
| `lovable/app/pages/requests/[id].vue` (single 831-line file) | `pages/requests/[id]/index.vue` (1,338 lines) | Current is bigger and richer. **No port needed.** |
| `lovable/app/pages/bank/users.vue` | `pages/staff.vue` | Different route name, same role/feature. Already on the **right** path per AGENTS.md. |
| `lovable/app/pages/admin/roles.vue` (read-only) | `pages/admin/roles.vue` (409 lines) | **Verify editability** — likely already richer. |
| `lovable/app/components/examples/ThemedAlert.vue` | none | Demo-only, not for port. |

**Conclusion:** there is **no significant lovable UI port left to do**. The fine-grained screenshot-by-screenshot diff is in `08-prototype-gap-analysis.md` and is already being executed there.

---

## 7 · Proposed sprint plan (story-by-story)

Ordered for low risk → high value. Each row is a separately-committable story (BMAD-compatible naming).

| # | Story | Type | Estimated effort | Dependencies |
|---|---|---|---|---|
| **S1** | Story 8.1 — Add `BANK_RETURNED` status + `bank_return_to_intake` transition; bank reviewer "إعادة للمدخل" action with comment; banner on intake side | Backend + Frontend | ~1 day | None |
| **S2** | Story 8.2 — Add `SUPPORT_RETURNED` status + `support_return_to_intake` transition; distinguishable banner ("معاد من المساندة") on intake side | Backend + Frontend | ~1 day | S1 (pattern) |
| **S3** | Story 8.3 — Split terminal `BANK_REJECTED` from recoverable `DRAFT_REJECTED_INTERNAL`; add `BANK_REJECTED` to `isTerminal()`; preserve legacy `DRAFT_REJECTED_INTERNAL` for one release as recoverable alias | Backend | ~½ day | S1 |
| **S4** | Story 8.4 — Claim release notification + audit (G3): on `DELETE /api/workflow/{id}/claim-support-review` emit a `CLAIM_RELEASED` notification to org committee leads | Backend + Frontend | ~½ day | None |
| **S5** | Story 8.5 — "Copy and resubmit" on terminal rejections (G4): new `POST /api/requests/{id}/clone` returns a new DRAFT pre-filled from the rejected request (skip documents); button on rejected-request detail | Backend + Frontend | ~1 day | None |
| **S6** | Story 8.6 — Cross-bank duplicate invoice (G5): scan `invoice_number` across all banks; surface in audit duplicates tab; configurable warn-vs-block via `system_settings.duplicate_invoice_policy` | Backend + Frontend | ~1 day | None |
| **S7** | Story 8.7 — Audit metadata polish: store `user_agent`, harden trusted-proxy `ip()`, log before/after on role changes | Backend | ~½ day | None |
| **S8** | Story 8.8 — Frontend polish: distinct banners for `BANK_RETURNED` vs `SUPPORT_RETURNED` vs `DRAFT_REJECTED_INTERNAL`; "copy and resubmit" button; side-by-side duplicate compare widget | Frontend | ~1 day | S1, S2, S5, S6 |
| **S9** | Story 8.9 — `/requests/[id]/print.vue` for request summary print | Frontend | ~½ day | None |
| **S10** | Story 8.10 — Advanced filters on `/requests`: date range, amount range, assigned reviewer | Frontend | ~½ day | None |
| **S11** | Story 8.11 — Inactivity-lock UI banner + auto-logout at Sanctum expiry | Frontend | ~½ day | None |

**Total in-scope effort:** ~8.5 dev-days (single engineer with this codebase familiarity). Roughly two sprint weeks at usual pace.

**Out-of-scope (deferred, listed for tracking):** SLA timers, delegation/absence, quorum rule, MT103 parsing, bulk actions, comment threads, dual-control admin, encryption at rest, webhooks, multi-language data layer.

---

## 8 · Acceptance checklist

When you say "we're done with USER-STORIES.md hybrid parity," these should all be true:

- [ ] All 8 roles can complete every story in their section without falling back to the lovable prototype.
- [ ] `BANK_RETURNED`, `SUPPORT_RETURNED`, `BANK_REJECTED` exist as canonical statuses and have UI banners.
- [ ] Terminal rejected requests display a "نسخ وإعادة إرسال" button that opens a pre-filled new draft.
- [ ] Cross-bank duplicate invoices surface in the audit duplicates tab with both rows side by side.
- [ ] Claim release emits a notification to committee leads and audit log records who released and when.
- [ ] Audit entries carry real client IP (proxy-aware) and `user_agent`.
- [ ] Role changes log before/after values.
- [ ] All existing 1,447 frontend tests + backend tests still green.

---

## 9 · What this report does NOT do

- Does not modify any code.
- Does not duplicate the screenshot-by-screenshot visual diff (see `docs/08-prototype-gap-analysis.md`).
- Does not address operational/infrastructure concerns (encryption at rest, DR, multi-region, monitoring).
- Does not re-evaluate canonical enums — the hybrid keeps AGENTS.md as the source of truth and adds three new statuses.

---

*End of report. To execute, drive sprint plan §7 story by story; each story is small enough to be a single BMAD `bmad-create-story` + `bmad-dev-story` session.*
