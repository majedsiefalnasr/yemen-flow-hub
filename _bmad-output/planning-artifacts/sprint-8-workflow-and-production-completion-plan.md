# Sprint 8 Plan: Workflow & Production Completion

Date: 2026-05-21
Status: Planned
BMAD Epic: Epic 8 — Workflow & Production Completion

## Sprint Goal

Close the residual workflow-completeness and production-readiness gaps surfaced in `docs/09-user-stories-gap-analysis.md`. This is **not** a new architecture phase: all prior sprint output (Epics 1–7) is canonical and complete. Sprint 8 organizes the in-scope gaps S1–S10 into executable stories with explicit dependencies and implementation order.

## Sprint Theme

Workflow completeness + audit fidelity + UX safety.

The sprint must preserve every governance invariant established by prior sprints:

- No admin role may override workflow rules.
- No feature may bypass organization scoping.
- All transitions continue to flow through `WorkflowService::transition()`.
- Every transition writes to both `request_stage_history` and `audit_logs`.
- The canonical 18-status enum is **extended**, never renamed or replaced — three new statuses are added (`BANK_RETURNED`, `SUPPORT_RETURNED`, `BANK_REJECTED`).

## Source Inputs

| Input | Path |
|---|---|
| Sprint scope + acceptance | `docs/09-user-stories-gap-analysis.md` (§6 S1–S10) |
| Visual parity reference (NOT reopened) | `docs/08-prototype-gap-analysis.md` |
| Workflow business rules | `docs/01-workflow-and-business-rules.md` |
| Canonical enums and schema | `docs/03-database-and-models.md` |
| Project AI charter | `AGENTS.md` |

## Hard Scope Boundaries

- Treat all stories in Epics 1–7 as **canonical and completed**. Do not reopen.
- Do **not** reopen Lovable parity work — `docs/08` is referenced only as visual context for stories that touch existing components (e.g. S5 button placement, S8 print page styling).
- No deferred or out-of-scope items from `docs/09` §5 — that list is preserved as-is for future planning.
- No new MVP scope expansion.
- No architecture rewrites.
- No removal of legacy statuses (`DRAFT_REJECTED_INTERNAL`, legacy `bank_reject` transition) in this sprint — they are deprecation candidates, but removal is deferred by one release window.

## Planned Stories

### Story 8.1 — BANK_RETURNED Status & Return-to-Intake Transition

**Goal:** Add a non-terminal `BANK_RETURNED` status with mandatory comment, enabling bank reviewers to return requests for correction without the request being treated as rejected.

Primary scope:
- New canonical status `BANK_RETURNED` (extends, does not replace, existing enum)
- New transition `bank_return_to_intake` (`BANK_REVIEW → BANK_RETURNED`)
- New endpoint `POST /api/workflow/{id}/bank-return` with mandatory comment
- Frontend banner variant: "إعادة من المراجع"
- Intake can edit + resubmit; submit transition accepts `BANK_RETURNED` as a valid `from` state

Out of scope:
- Support-return-to-intake (Story 8.2)
- Terminal bank rejection (Story 8.3)

Depends on: none.

### Story 8.2 — SUPPORT_RETURNED Status & Direct Return-from-Support Transition

**Goal:** Add a non-terminal `SUPPORT_RETURNED` status enabling support members to return directly to intake with a comment, instead of routing through the legacy reject-then-bank-return path.

Primary scope:
- New canonical status `SUPPORT_RETURNED`
- New transition `support_return_to_intake` (`SUPPORT_REVIEW_IN_PROGRESS → SUPPORT_RETURNED`); atomically releases support claim
- New endpoint `POST /api/workflow/{id}/support-return` with mandatory comment
- Frontend banner variant: "إعادة من لجنة المساندة"
- Re-submission re-enters `BANK_REVIEW` first

Out of scope:
- Removing legacy `bank_return_after_support_reject` (deferred one release)

Depends on: Story 8.1 (banner pattern reuse).

### Story 8.3 — Terminal BANK_REJECTED Status

**Goal:** Introduce a terminal `BANK_REJECTED` status distinct from the recoverable `DRAFT_REJECTED_INTERNAL`, modeling real bank-side rejection correctly.

Primary scope:
- New canonical status `BANK_REJECTED` (terminal, immutable)
- New transition `bank_reject_terminal` (`BANK_REVIEW → BANK_REJECTED`)
- New endpoint `POST /api/workflow/{id}/bank-reject-terminal`
- Mutations on `BANK_REJECTED` return 403 `WORKFLOW_IMMUTABLE_STATE`
- Frontend split UI: "إعادة للمدخل" (S1) vs "رفض نهائي" (S3) with destructive-confirm dialog

Out of scope:
- Deletion of `bank_reject` legacy route or `DRAFT_REJECTED_INTERNAL` status

Depends on: Story 8.1 (split UI pattern).

### Story 8.4 — Claim Release Notification + Audit

**Goal:** Make support claim releases (manual + TTL) visible to committee leads via notification + audit log.

Primary scope:
- New `ClaimReleasedNotification`
- New `AuditAction::CLAIM_RELEASED`
- Dispatch on manual `DELETE` release and on `ExpireClaimsCommand` TTL path
- Frontend notification icon variant for `claim_released`

Out of scope:
- SLA timers on release frequency, supervisor-required reason after N releases (deferred)

Depends on: none.

### Story 8.5 — Copy & Resubmit on Terminal Rejections

**Goal:** Let intake clone a terminally-rejected request into a new draft, pre-filling wizard fields (no documents), avoiding manual re-keying.

Primary scope:
- New endpoint `POST /api/requests/{id}/clone`
- Authorization: DATA_ENTRY or BANK_ADMIN in source bank; source must be terminal-rejected
- Audit entry with `cloned_from` metadata
- Frontend "نسخ وإعادة إرسال" button on terminal-rejected detail views; wizard accepts `?clone_of=<id>`

Out of scope:
- Bulk clone, partial-field clone, document carry-over

Depends on: Story 8.3 (defines `BANK_REJECTED` as a clone source; without it, only `SUPPORT_REJECTED` and `EXECUTIVE_REJECTED` would be cloneable).

### Story 8.6 — Cross-Bank Duplicate Invoice Detection

**Goal:** Detect duplicate `invoice_number` across all banks (not only within actor's own bank), with a configurable warn-vs-block policy.

Primary scope:
- Composite index `(invoice_number, deleted_at)` for fast scans
- New `DuplicateDetectionService` (no scope filter on scans)
- `duplicate_warnings` array exposed in request detail for reviewer/auditor roles
- `audit.vue` duplicates tab uses cross-bank scan
- `system_settings.duplicate_invoice_policy` toggle (`warn` default, `block` optional)
- Side-by-side compare widget on detail page

Out of scope:
- Full side-by-side amount/currency diff highlighting (fast-follow once both rows render)

Depends on: none.

### Story 8.7 — Audit Metadata Polish

**Goal:** Capture real client IP (proxy-aware), `user_agent`, and before/after values on role/permission changes in `audit_logs`.

Primary scope:
- Configure `TrustProxies` in `bootstrap/app.php`
- Add nullable `user_agent` column to `audit_logs`
- `AuditService` populates `ip` + `user_agent`; before/after on admin updates
- `audit.vue` row expansion renders UA + diff

Out of scope:
- Geolocation lookup, signed audit entries, audit immutability proof

Depends on: none.

### Story 8.8 — Request Detail Print Page

**Goal:** Add `/requests/{id}/print` mirroring the existing customs print page pattern, for offline review.

Primary scope:
- `pages/requests/[id]/print.vue` + extracted `RequestPrintable.vue` component
- "طباعة" button in request detail header
- Print-only CSS (`@media print`) hides nav/sidebar; A4 portrait RTL
- Reuses existing `GET /api/requests/{id}` + history endpoints (no API changes)

Out of scope:
- Server-side PDF generation

Depends on: none.

### Story 8.9 — Advanced Request List Filters

**Goal:** Add date range, amount range, and assigned-reviewer filters to `/requests`.

Primary scope:
- Backend `index()` accepts `created_from`, `created_to`, `amount_min`, `amount_max`, `assigned_reviewer_id`
- Frontend filter UI extending existing `requests/index.vue` filter bar
- URL query-param persistence for shareable links

Out of scope:
- Risk-level filter (no risk model exists yet), global full-text search

Depends on: none.

### Story 8.10 — Inactivity-Lock UI Banner

**Goal:** Show a warning at T-2min before inactivity logout and safely force-logout at T-0, with a friendly login-page message.

Primary scope:
- `useInactivityTimer` composable
- `InactivityBanner.vue` mounted in `default.vue`
- Auth store `extendSession()` + `forceLogout()`
- Login page renders info banner on `?reason=inactivity`
- 15-minute default threshold, warning at T-2min

Out of scope:
- Multi-tab activity sync, keystroke-level idle detection

Depends on: none.

## Implementation Order & Dependency Graph

```
Independent / start any time:
  ├── S1  BANK_RETURNED            (no deps)
  ├── S4  Claim release audit      (no deps)
  ├── S6  Cross-bank duplicates    (no deps)
  ├── S7  Audit metadata polish    (no deps)
  ├── S8  Request print page       (no deps)
  ├── S9  Advanced filters         (no deps)
  └── S10 Inactivity banner        (no deps)

Sequenced:
  S1 → S2  SUPPORT_RETURNED        (reuses S1's banner pattern)
  S1 → S3  BANK_REJECTED           (reuses S1's split-UI pattern)
  S3 → S5  Copy & resubmit         (S5 lists BANK_REJECTED as a clone source)
```

**Recommended single-developer order:**

1. **S1** BANK_RETURNED (establishes the return pattern)
2. **S2** SUPPORT_RETURNED (reuses S1)
3. **S3** BANK_REJECTED (extends S1's UI)
4. **S5** Copy & resubmit (depends on S3)
5. **S4** Claim release notification
6. **S6** Cross-bank duplicates
7. **S7** Audit metadata polish
8. **S8** Request print page
9. **S9** Advanced filters
10. **S10** Inactivity banner

**Parallel-track option (two developers):**

- Track A (workflow): S1 → S2 → S3 → S5
- Track B (cross-cutting): S4, S6, S7, S8, S9, S10 in any order

## Estimated Effort

| Story | Effort | Cumulative |
|---|---|---|
| S1  | 1 day | 1 |
| S2  | 1 day | 2 |
| S3  | ½ day | 2.5 |
| S5  | 1 day | 3.5 |
| S4  | ½ day | 4 |
| S6  | 1 day | 5 |
| S7  | ½ day | 5.5 |
| S8  | ½ day | 6 |
| S9  | ½ day | 6.5 |
| S10 | ½ day | 7 |

**Total in-scope effort:** ~7 dev-days for a single engineer familiar with this codebase, or ~4 calendar-days with two engineers running parallel tracks.

## Sprint-Level Acceptance Criteria

When Sprint 8 is "done", all of these are true:

- [ ] Three new canonical statuses (`BANK_RETURNED`, `SUPPORT_RETURNED`, `BANK_REJECTED`) are live in `RequestStatus` enum, in `TransitionMap`, and rendered in the frontend.
- [ ] Terminal rejected requests display "نسخ وإعادة إرسال" and clone successfully into pre-filled drafts.
- [ ] Cross-bank duplicate invoices surface in the audit duplicates tab and in request detail for reviewer/auditor roles.
- [ ] Claim release (manual + TTL) dispatches `ClaimReleasedNotification` to committee leads and writes a `CLAIM_RELEASED` audit row.
- [ ] Audit entries carry real client IP (proxy-aware) and `user_agent`; admin updates log before/after.
- [ ] `/requests/{id}/print` renders cleanly under `@media print` (A4, RTL).
- [ ] Advanced filters (date/amount/reviewer) work on `/requests` with URL persistence.
- [ ] Inactivity warning + safe logout flow works end-to-end with a friendly login banner.
- [ ] All existing ~1,447 frontend tests and ~564 backend tests still green.
- [ ] Each story carries its own targeted tests, written per BMAD dev-story flow.
- [ ] `sprint-status.yaml` reflects epic-8 status accurately.

## Out-of-Scope (Explicit)

These items from `docs/09-user-stories-gap-analysis.md` §5 are intentionally **deferred or out of scope** and are NOT part of Sprint 8:

- SLA timers per stage + escalations (defer)
- Delegation / absence management (defer)
- Quorum rule on voting (defer)
- MT103 parsing (defer)
- External customs authority integration (OOS — post-MVP)
- Bulk actions on request list (defer)
- In-app comment thread (defer)
- Encryption at rest (OOS — infrastructure)
- Multi-language data layer (OOS — Arabic-first)
- Webhooks / external integration points (defer)
- Disaster recovery / backup plan (OOS — ops)
- Dual-control / 4-eyes for platform admin (defer)
- Customs declaration external verification (OOS — post-MVP)
- Side-by-side full diff highlighting on duplicates (fast-follow on S6)
- Removal of legacy `DRAFT_REJECTED_INTERNAL` and `bank_reject` route (deferred one release)

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Enum extension breaks frontend `STATUS_LABEL` / `STATUS_PROGRESS` tables | Medium | High | S1 includes a unit test that asserts every `RequestStatus` enum case has a label, progress %, and bucket entry. Same test reused in S2 and S3. |
| Cloning a terminal-rejected request leaks foreign-key references to original documents | Low | Medium | Clone explicitly does NOT copy documents; S5 acceptance requires the clone's `request_documents` count = 0 on creation. |
| Cross-bank duplicate scan adds latency to request creation | Medium | Low | S6 adds composite index; load-test threshold: scan must complete in < 50 ms on 10k-row dataset. |
| TrustProxies misconfiguration leaks internal IPs into audit log | Low | Medium | S7 includes a regression test asserting `ip` matches the original client header in a proxied test. |
| Inactivity timer fires during long file uploads | Low | High | S10's composable also listens to network activity events (XHR completion) — explicit acceptance check. |
| Legacy `bank_reject` route still hit by older frontend builds during gradual deploy | Low | Low | Legacy route preserved; lands in `DRAFT_REJECTED_INTERNAL` (recoverable). Deletion deferred. |

## References

- `docs/09-user-stories-gap-analysis.md`
- `docs/08-prototype-gap-analysis.md`
- `docs/01-workflow-and-business-rules.md`
- `docs/03-database-and-models.md`
- `AGENTS.md` (canonical enums + workflow rules)
- `_bmad-output/planning-artifacts/epics.md` (Epic 8 entry)
- Prior sprint plans: `sprint-5-institutional-operations-platform-plan.md`, `sprint-change-proposal-2026-05-19.md`
