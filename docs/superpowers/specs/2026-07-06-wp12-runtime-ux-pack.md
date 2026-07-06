# WP-12 — Runtime UX Pack

**Status:** Draft for review (Phase 6)
**Source of authority:** `2026-07-05-feature-review-notes.md`
**Traceability:** D3-N1 (server-side lists/KPIs + R10 store rework), D2-N4 (confirmation dialogs), D2-N5 (transition audit diffs), D2-N7 (required-comment UX), D17-N5 (export truncation), D18-N8 (failed export UX), D19-N4 (preference wiring), D19-N7 (readAll semantics), D1-N1 runtime (submit-transition flag read), D21-N5 (search LIKE escaping — rides WP-7 but UX-adjacent).
**Dependencies:** WP-0; WP-R (R2 controller split, R1 dashboard extraction). Consumes WP-3 V-1 (`is_default_submit`), WP-5 CL-5 (claim-loss UI exists), WP-11 settings where applicable.
**Enables:** cleaner operator surfaces; nothing downstream blocked.
**Overall risk:** low-medium — mostly frontend + read endpoints; the server-side list/KPI rework (D3-N1) is the substantive piece.

## Change classification

All items: **approved functional changes** (D-notes). D3-N1 includes R10 store rework (frontend).

**Explicitly out of scope:** claim behavior (WP-5); two-layer scope (WP-7 — lists consume DataScope but the primitive ships there); document/field visibility (WP-8); API envelope (WP-14/R9).

---

## U-1 — Server-side lists, filters, KPIs (D3-N1 + R10)

**Current:** `/workflows` list loads page 1 only (25 rows); search/facets/KPIs/export client-side on that page; supervisor KPIs from newest 25.
**Required:**
- Pagination via backend; search/filters sent to backend (server filter set already exists in `EngineRequestController::applyFilters` post-R2).
- Supervisor KPIs from full authorized dataset — dedicated stats/aggregates endpoint (total visible, breached SLA, nearing SLA, unclaimed, by status, by stage/workflow), scoped via WP-7 DataScope.
- Frontend store (`engineRequests.store`) + composables reworked (R10): server-driven pagination/filters; client-side only for already-loaded-page interactions; DataTable wired to server state.
**Acceptance:** list pages through full dataset; KPIs reflect full scope; filters honored server-side.

## U-2 — Transition confirmation dialogs (D2-N4)

**Current:** `confirmation_message` stored, never shown; destructive transitions run without confirm.
**Required:**
- Transition with `confirmation_message` → AlertDialog with the configured message; explicit confirm before submit.
- Required for: reject transitions, final/completion transitions, lifecycle-significant return transitions, any designer-marked destructive transition (WP-3 V-5).
- `EngineActionsRail` + `[id].vue` runAction gated by the dialog.
**Acceptance:** destructive transition prompts; non-destructive proceeds without prompt.

## U-3 — Transition audit diffs (D2-N5)

**Current:** transition audit logs stage ids + action code; data-patch changes not diffed.
**Required:**
- When a transition includes a data patch, audit captures changed fields: key, old value, new value, actor, request id, transition id, from/to stage, correlation id, timestamp.
- Sensitive fields maskable; change traceability preserved.
- `EngineTransitionService::execute` (+ `saveDraft` for parity) writes the diff via `AuditService`.
**Acceptance:** transition with a data patch produces a field-level audit diff; sensitive fields masked.

## U-4 — Required-comment UX (D2-N7)

**Current:** required-comment miss → silent no-op in UI (button does nothing).
**Required:**
- If a transition requires a comment, the UI asks for it before submission; inline validation when missing; action button disabled-with-reason until satisfied.
**Acceptance:** required-comment transition with empty comment → clear inline error, no silent no-op.

## U-5 — Export truncation messaging (D17-N5)

**Current:** CSV export capped at 10k rows silently.
**Required:**
- Truncation explicit: total matching rows (if available), exported count, applied filters, truncation note in the file/response.
- Consider narrower-filter guidance for large exports.
**Acceptance:** capped export shows truncation clearly.

## U-6 — Failed export UX (D18-N8)

**Current:** FAILED status surfacing unverified.
**Required:**
- Failed exports show clear FAILED status + UI message; internals logged server-side only; user can retry where appropriate; failed jobs leave no broken downloadable file.
**Acceptance:** failed export → clear message + retry; no broken download.

## U-7 — Notification preferences wiring (D19-N4)

**Current:** `notification_preferences` settings unconsumed.
**Required:**
- Preferences wired into supported non-critical categories (informational, digests, optional email if added) — never suppress mandatory security/workflow-critical/compliance notifications (assigned/available action, actionable SLA breach, permission/security changes, account events).
- If a preference can't be honored yet, hide/disable its UI (WP-11 principle: no placebo UI).
**Acceptance:** non-critical preference suppresses informational notifications; critical always delivered.

## U-8 — readAll semantics (D19-N7)

**Current:** `readAll` marks archived-unread rows read too.
**Required:**
- `readAll` affects only non-archived unread rows; archived-unread untouched unless explicitly requested.
**Acceptance:** archived-unread rows unchanged by readAll.

## U-9 — Runtime submit-transition read (D1-N1 runtime half)

**Current:** wizard submits via first graph edge (`edges.find`).
**Required:**
- Wizard/instance page reads the `is_default_submit` flag (WP-3 V-1) to pick the submit transition deterministically; falls back to sole-edge when exactly one exists.
**Acceptance:** submit transition is deterministic (flag-driven).

---

## Business rules (consolidated)

1. Lists/KPIs are server-driven and scope-aware (DataScope).
2. Destructive transitions require explicit confirmation; audit captures field-level diffs.
3. Required comments enforced in UX; exports signal truncation/failure clearly.
4. Notification preferences bounded — never suppress mandatory notifications.
5. Submit transitions are deterministic.

## Error cases

| Case | Response |
|------|----------|
| Required comment empty | inline 422 UX |
| Destructive transition not confirmed | no submit (dialog) |
| Export truncated | explicit truncation note |
| Failed export | clear failure + retry |

## Acceptance criteria

1. Lists paginate server-side; KPIs reflect full scope; filters server-honored.
2. Confirmation dialogs fire for destructive transitions; field-level audit diffs written.
3. Required-comment UX clear; no silent no-op.
4. Export truncation/failure surfaced; no broken downloads.
5. Non-critical preferences effective; critical notifications always delivered.
6. readAll scoped to non-archived; submit-transition deterministic.
7. All WP-0 suites green.

## Test cases

- **Feature:** server-side list pagination/filter/KPI; transition audit diff (with masking); export truncation/failure; readAll scoping; submit-transition flag read.
- **Frontend unit:** confirmation dialog gating; required-comment UX; DataTable server-state wiring; preference toggle effect; failed-export UI.
- **Regression:** existing list/transition/export flows unchanged for legitimate paths.

## Manual verification steps

1. List → paginate beyond page 1; supervisor KPIs match full scope.
2. Reject transition → confirmation dialog with message; approve → proceeds.
3. Transition with data patch → audit shows field diffs.
4. Required-comment transition, empty comment → inline error.
5. Export >10k → truncation note; failed export → message + retry.
6. Toggle informational preference → suppressed; security notification still arrives.
7. readAll → archived-unread untouched.

## Rollback considerations

U-1 (server-side lists) is the substantive change — revert restores client-side page-1 behavior. U-2..U-9 are additive UX/audit. R10 store rework rides U-1. All reverts safe.

## Open questions

1. **U-1 KPI endpoint:** dedicated `/v1/engine-requests/stats` (recommended) vs extending list meta? Recommend dedicated.
2. **U-3 sensitive-field masking:** which fields are sensitive (amount? merchant tax?) — needs a field-sensitivity designation (could ride WP-4 semantic tags). Confirm minimal set.
