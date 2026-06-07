---
type: sprint-change-proposal
date: 2026-06-07
author: MAJED (with Dev agent)
trigger: deferred-work.md 2026-06-07 reconciliation — verified-live pre-production debt
scope_classification: Moderate (new epic; backlog addition, no rollback, no MVP change)
change_scope: Add Epic 16 — Pre-Production Security & Reliability Hardening (5 stories)
status: approved
---

# Sprint Change Proposal — Epic 16: Pre-Production Security & Reliability Hardening

## Section 1 — Issue Summary

**Problem statement.** The `2026-06-07` reconciliation of
`_bmad-output/implementation-artifacts/deferred-work.md` audited every open deferral
against current code. A subset of those deferrals are **security- and reliability-class
defects** that were knowingly deferred at review time ("revisit before production") and are
**still open** in the shipped backend. This platform is an audit-sensitive Central Bank of
Yemen (CBY) banking workflow system; these items must clear before a production deployment.

**Discovery context.** This is not a new requirement or a failed approach. Each item was
documented at its original story's code review and parked under an explicit
"before production" / "security-hardening story" deferral. The reconciliation simply
collected them and confirmed they remain unaddressed.

**Evidence (all confirmed STILL OPEN against current code on 2026-06-07):**

| # | Defect | Location (verified) |
|---|--------|---------------------|
| 1 | Per-email lockout key has no IP component — attacker locks any user with 10 bad-password requests from rotating IPs | `backend/app/Http/Controllers/Api/AuthController.php:62,64` (`$failKey = 'login_fail:'.$email`) |
| 2 | `lockedOut()` returns 403 + `ACCOUNT_LOCKED`, no `Retry-After` (RFC 6585 → 429/423 + `Retry-After`) | `backend/app/Support/ApiResponse.php:53-60` |
| 3 | `last_login_at` written via `forceFill()->save()` — concurrent multi-device login lost update | `backend/app/Http/Controllers/Api/AuthController.php` (`issueSession`) |
| 4 | `MfaService::sendOtpEmail(User\|string)` early-returns on unknown email — account-existence oracle | `backend/app/Services/Auth/MfaService.php:56-68` |
| 5 | No rate limit on document upload routes (login/otp are throttled; uploads are not) | `backend/routes/api.php:86,88,112` |
| 6 | `original_filename` stored verbatim — `../` / special-char injection in storage + `Content-Disposition` | `backend/app/Services/Documents/DocumentService.php:199,258` |
| 7 | `download_url` in `DocumentResource` not signed/time-limited — unauthenticated download if Sanctum not enforced on the route | `backend/app/Http/Resources/DocumentResource.php` |
| 8 | `ImportRequest` reference_number race — `creating()` reads `latest('id')->value()` with no lock; two concurrent creates in the same year collide on sequence | `backend/app/Models/ImportRequest.php:246-267` |
| 9 | CBY recipient roles not org-scoped in resolver (`orWhereIn('role', $cbyRoles)`) — latent if an org-scoped CBY role is added | `backend/app/Services/Notifications/SendEmailNotification.php:201-203` |
| 10 | `partitionRecipientRoles()` silently drops non-bank/non-CBY roles — a mandatory recipient can be omitted with no signal | `backend/app/Services/Notifications/SendEmailNotification.php:213-227` |
| 11 | Orphaned dead mailables retained (re-wire hazard) | `backend/app/Mail/` — `RequestApprovedMail`, `RequestRejectedMail`, `RequestReturnedMail`, `VotingOpenedMail`, `MfaOtpMail`, `PasswordRecoveryOtpMail` |
| 12 | `EmailDelivery` fully mass-assignable while service uses `forceFill`; `EmailDeliveryStatus::BOUNCED` unreachable + `markFailed` sets no terminal timestamp | `backend/app/Models/EmailDelivery.php`, `backend/app/Enums/EmailDeliveryStatus.php` |
| 13 | `AUTO_ABSTAIN_TIMEOUT` inserts in `closeSession()` / `overrideAndFinalize()` have no `auditService->log()` call (manual votes are logged) | `backend/app/Services/Voting/VotingService.php:66,98,186,239` |
| 14 | Blanket `AccessDeniedHttpException\|AuthorizationException` catch logs **every** framework `abort(403)`/signed-URL denial as `AUTHORIZATION_FAILURE` — audit-log flood risk | `backend/bootstrap/app.php:85-100` |
| 15 | Audit log fires **before** stream delivery in customs/document download — completed-download audit recorded for bytes never delivered | `backend/app/Services/Customs/CustomsService.php:115`, `backend/app/Services/Documents/DocumentService.php` |

---

## Section 2 — Impact Analysis

### Epic Impact

- **No existing epic is invalidated.** Epics 1–15 remain `done`/correct. The defects are
  residual debt from those epics, not regressions.
- **A new epic is the correct container.** These are cross-cutting hardening items spanning
  auth, uploads, model layer, notifications, voting, and the global exception handler.
  Re-opening five completed epics to absorb one item each would corrupt their `done` state
  and scatter the work. A dedicated Epic 16 keeps the pre-production hardening pass
  cohesive, traceable, and independently shippable.
- **No epic resequencing.** Epic 16 is additive and depends only on already-shipped code.
- **Sibling cleanup observed:** `epic-15` is still marked `in-progress` in
  `sprint-status.yaml` although all of `15-1..15-6` are `done` and the file's own
  `last_updated` note says they were "patched and set done". This proposal corrects
  `epic-15 → done` alongside adding Epic 16 (trivial, accuracy-only).

### Artifact Conflict Analysis

| Artifact | Impact |
|----------|--------|
| **PRD / `project-context.md`** | No conflict. Reinforces existing §12 Security Requirements (login rate limit, account lockout, PDF-only private storage, audit completeness). No requirement is changed, only enforced. |
| **Architecture (`architecture.md`)** | No structural change. All fixes stay inside existing service/model/handler boundaries (WorkflowService untouched; AuditService remains the audit authority; email outbox/registry contracts unchanged). |
| **Database / schema** | Minimal: Story 16-2 may add a small migration if filename sanitization stores a normalized name; Story 16-3 may add a dedicated sequence/counter table **or** use a pessimistic lock with no schema change (implementer's choice, see story). No canonical enum changes. `AUTO_ABSTAIN_TIMEOUT` audit (16-5) writes to existing `audit_logs`. |
| **API contracts (`06-api-reference.md`)** | Story 16-1 changes the lockout response to 429/423 + `Retry-After` (documented response-shape change, backward-tolerant for clients that only branch on success). Story 16-2 adds throttle headers on upload routes. Both are documentation updates, not contract breaks. |
| **UI/UX (`docs/user-view/*`)** | None. No new screens or role surfaces. Frontend may later consume `Retry-After`, but that is out of scope here. |
| **Testing** | Each story adds backend feature/unit coverage (lockout-by-IP, oracle-equivalence timing, concurrent-create reference uniqueness, recipient-completeness signal, auto-abstain audit row, scoped-403 audit). Mirrors existing `backend/tests/Feature/**` patterns. |
| **CI / deployment** | None. No new infra, no new env vars beyond optional throttle tuning. |

### Technical / Backend Constraints (AGENTS.md + backend/CLAUDE.md)

- All workflow state changes stay in `WorkflowService::transition()` — **none** of these
  fixes mutate `current_status` directly.
- Org-scoped visibility stays enforced at the Eloquent query level (16-4 strengthens this
  for CBY recipient resolution).
- Every relevant action continues to log to `audit_logs` (16-5 closes the auto-abstain gap;
  16-5 also *narrows* over-logging without dropping any domain-authorization event).
- Commits go to both backend team repo and root monorepo, signed, conventional-commit format.

---

## Section 3 — Recommended Path Forward

**Selected approach: Option 1 — Direct Adjustment (add a new epic + stories within the
existing plan).** Hybrid elements: none required.

| Option | Verdict | Rationale |
|--------|---------|-----------|
| 1 — Direct adjustment (new Epic 16, 5 stories) | **Selected** | Cohesive pre-production hardening pass; no disruption to `done` epics; each story is independently testable and shippable. Effort: Medium. Risk: Low. |
| 2 — Rollback | Rejected | Nothing to roll back; the underlying features are correct and live. Rollback would destroy working functionality for zero gain. |
| 3 — MVP review / scope reduction | Rejected | MVP is unaffected; these are hardening items layered on a complete MVP, not scope the MVP can shed. |

**Why grouped into 5 stories** (by subsystem + reviewer cohesion, so each can be reviewed by
a fresh context against one bounded area):

- **16-1 Auth hardening** — items 1–4 (login lockout, lockout response, last-login write, MFA oracle)
- **16-2 Upload hardening** — items 5–7 (upload rate limit, filename sanitization, signed/time-limited download URL)
- **16-3 Workflow integrity** — item 8 (reference_number race)
- **16-4 Email recipient correctness + dead-code cleanup** — items 9–12 (CBY org-scope, drop-signal, dead mailables, EmailDelivery hardening)
- **16-5 Audit completeness** — items 13–15 (auto-abstain audit, scoped authz logging, stream-ordering audit)

**Effort:** Medium overall (≈5 focused backend stories).
**Risk:** Low — additive hardening on stable code; main risk is over-broad refactors, mitigated by per-story scope fences below.
**Timeline impact:** A single pre-production hardening mini-sprint; no downstream epic is blocked.

---

## Section 4 — Detailed Change Proposals (Story Specs)

> These are epic-level story definitions for backlog entry. Full per-story spec files are
> authored later via `create-story`. Each story MUST follow AGENTS.md + backend/CLAUDE.md
> (WorkflowService for transitions, org-scoped queries, AuditService logging, signed commits
> to both repos) and use SocratiCode (`codebase_symbol` → `codebase_impact`) before editing.

### Story 16-1 — Auth hardening

**Goal:** Remove the auth-layer DoS and information-disclosure vectors deferred from Story 1-2 / Epic 15.

**Acceptance criteria:**
1. Account-lockout keying includes an IP component **or** introduces a progressive
   delay / CAPTCHA step before hard lockout, so an attacker rotating IPs cannot lock an
   arbitrary user with 10 bad-password requests. Legitimate per-email lockout behavior
   (10 consecutive real failures → 15-min lock) is preserved.
   _[`AuthController.php:62-89`]_
2. The lockout response uses HTTP **429** (or **423**) with a `Retry-After` header per
   RFC 6585. `ApiResponse::lockedOut()` is updated; `06-api-reference.md` reflects the new
   shape. Existing `error_code` semantics remain machine-readable.
   _[`ApiResponse.php:53-60`]_
3. `last_login_at` is written without a lost-update race — via `updateQuietly()` (or a
   scoped, locked update) instead of `forceFill()->save()`. No model events that would
   re-trigger workflow side effects are fired.
   _[`AuthController.php` `issueSession`]_
4. `MfaService::sendOtpEmail()` no longer exposes an observable account-existence oracle:
   unknown vs. known email produce indistinguishable observable behavior (timing + response).
   The `User`-typed path (AuthController already passes a `User`) remains the primary call;
   the string path is made safe or removed.
   _[`MfaService.php:56-68`]_

**Scope fence:** auth/login + MFA send path only. Do not touch TOTP verification logic,
PIN login, or password-recovery flows beyond the oracle fix.

### Story 16-2 — Upload hardening

**Goal:** Close the document-upload abuse and path/identity-disclosure gaps deferred from Story 2-2 / 4-3.

**Acceptance criteria:**
1. Throttle middleware is applied to all document upload routes — `POST /api/documents/upload`,
   the deprecated `POST /api/requests/{importRequest}/documents`, and
   `POST /api/workflow/{importRequest}/swift-upload` — consistent with the existing
   `throttle:*` pattern on auth routes. PDF-only + private-storage rules are unchanged.
   _[`routes/api.php:86,88,112`]_
2. `original_filename` is sanitized on **store** and on **download** (`Content-Disposition`):
   `../`, path separators, and control/special characters are neutralized; a safe display
   name is persisted/returned. Stored checksum/behavior for current `local` disk is preserved.
   _[`DocumentService.php:199,258`]_
3. `download_url` exposed by `DocumentResource` is either a Laravel **signed, time-limited
   URL** or is removed in favor of the Sanctum-protected `GET /api/documents/{id}/download`
   route, so a leaked URL cannot yield an unauthenticated download. Download authorization
   (permission matrix) is unchanged.
   _[`DocumentResource.php`]_

**Scope fence:** upload/download surface only. Do not redesign storage drivers, dedup, or
introduce a max-file-size policy (separate deferred item, out of scope).

### Story 16-3 — Workflow integrity: reference_number race

**Goal:** Make `import_requests.reference_number` generation safe under concurrent creates (deferred from Story 1-1).

**Acceptance criteria:**
1. Two concurrent `ImportRequest::create()` calls in the same year can never receive the
   same `YFH-{YEAR}-{NNNNNN}` sequence. Implemented via a DB-level sequence/counter table
   **or** a pessimistic lock around the read-increment, inside a transaction.
   _[`ImportRequest.php:246-267`]_
2. Generated format is unchanged (`YFH-{YEAR}-{6-digit}`); per-year sequence resets as today;
   `withTrashed()` continuity is preserved.
3. A concurrency test demonstrates uniqueness under parallel creation; the `unique`
   constraint on `reference_number` is never the thing that catches the collision.

**Scope fence:** reference_number generation only. Do not refactor the broader model
`booted()` hooks or other generators (e.g. customs declaration_number) in this story.

### Story 16-4 — Email recipient correctness + dead-code cleanup

**Goal:** Guarantee no mandatory email recipient is silently dropped, and remove re-wire
hazards left after Epic 14→15 supersession.

**Acceptance criteria:**
1. CBY recipient resolution is correct if/when an org-scoped CBY role is introduced — the
   resolver no longer assumes all CBY roles are global. Current behavior (regulator roles
   see all) is preserved for existing roles.
   _[`SendEmailNotification.php:201-203`]_
2. `partitionRecipientRoles()` no longer silently drops a role that is neither bank nor CBY —
   such a case is surfaced (logged/asserted) so a mandatory recipient cannot be omitted
   without a signal.
   _[`SendEmailNotification.php:213-227`]_
3. Orphaned, no-longer-dispatched mailables are deleted: `RequestApprovedMail`,
   `RequestRejectedMail`, `RequestReturnedMail`, `VotingOpenedMail`, `MfaOtpMail`,
   `PasswordRecoveryOtpMail`. **`TestEmailMail` is retained** (still dispatched by the
   admin test-email action, Story 14-2). A grep confirms zero remaining dispatch references
   before deletion.
   _[`backend/app/Mail/`]_
4. `EmailDelivery` model no longer fully mass-assignable for service-owned columns
   (`status` / `provider_message_id` / `template_version_id`) given the service writes via
   `forceFill`; `EmailDeliveryStatus::BOUNCED` either gains a writer or is removed as dead;
   `markFailed` sets a terminal timestamp consistent with the other terminal states.
   _[`EmailDelivery.php`, `EmailDeliveryStatus.php`]_

**Scope fence:** notification recipient resolution + email outbox model + Mail cleanup only.
Do not change template registry/resolver/renderer contracts or queue dispatch semantics.

### Story 16-5 — Audit completeness

**Goal:** Close the three audit-fidelity gaps (auto-abstain not logged, over-logging of
framework 403s, download-audited-before-delivery).

**Acceptance criteria:**
1. System-generated `AUTO_ABSTAIN_TIMEOUT` votes inserted in `closeSession()` and
   `overrideAndFinalize()` produce `audit_logs` entries (parity with manual-vote logging),
   including actor/role context per AGENTS.md.
   _[`VotingService.php:66,98,186,239`]_
2. The global `AccessDeniedHttpException|AuthorizationException` audit catch is scoped to
   **domain authorization events** only; routine framework `abort(403)` and signed-URL
   denials no longer flood `audit_logs` with `AUTHORIZATION_FAILURE`. **No domain-level
   authorization denial is dropped** (the `UnauthorizedTransitionException|SelfReviewException`
   handler at `:114-129` remains the authoritative workflow-authz audit path).
   _[`bootstrap/app.php:85-100`]_
3. Download audit for customs/document streams reflects delivery intent correctly: either
   the audit is event-based (recorded on successful stream start/completion) **or** the
   ordering trade-off is explicitly documented and accepted, so a dropped connection does
   not silently record a "completed download" for undelivered bytes. Applied consistently
   across `CustomsService::download` and `DocumentService::download`.
   _[`CustomsService.php:115`, `DocumentService.php`]_

**Scope fence:** audit logging fidelity only. Do not change the AuditService signature,
add new `audit_logs` columns (separate deferred F12 item), or alter workflow transitions.

---

## Out of Scope (explicitly excluded per directive)

Cosmetic / test-only / by-design deferrals remain in `deferred-work.md` and are **not** part
of Epic 16:

- `formatDate` duplication (F6), in-tab retry affordances (F10/F13), test-mirrors-logic (F11)
- `workflow.transition.active` IoC test bypass and test-fixture role hardcoding
- Generic 403 copy ("Forbidden action") and `AuditController` action-enum filter validation (F13)
- SWIFT/support queue sort-key fragility, dashboard caching, `(clone $base)` ceremony
- `TemplateResolver` blade fallback, `substitute()` non-scalar coercion, frontend enum drift/middleware race
- The dropped-as-obsolete **BankAdmin M5/C9 dashboard fields** (decision: not built — YAGNI/no vanity metrics)

---

## Section 5 — Implementation Handoff

**Change scope classification: Moderate** (new epic + backlog reorganization; no rollback,
no MVP redefinition).

| Role | Responsibility |
|------|----------------|
| **Product Owner / MAJED** | Approve this proposal; confirm Epic 16 priority within the pre-production sequence. |
| **Scrum Master / `create-story`** | Author per-story spec files `16-1 … 16-5` from the Section 4 definitions, in story-creation order; flip each row `backlog → ready-for-dev`. |
| **Developer agent (`dev-story`)** | Implement each story against `backend/`, following AGENTS.md + backend/CLAUDE.md; SocratiCode pre-checks; tests; signed commits to backend team repo **and** root monorepo. |
| **Reviewer (`code-review`, fresh context)** | Per-story review with security lens (oracle equivalence, IP-keying, concurrency proof, recipient completeness, audit-row presence/scoping). |

**Success criteria:** all 15 evidence items closed with tests; `deferred-work.md` updated to
move each closed item into its "Resolved (reconciled out)" section with the fixing story as
evidence; `sprint-status.yaml` reflects Epic 16 progress.

**Suggested story order:** 16-3 (smallest, isolated) → 16-1 → 16-2 → 16-5 → 16-4
(largest cleanup last). Order is advisory; stories are independent.

---

## Approval

Scope addition was explicitly directed by MAJED on 2026-06-07 with the deliverables
"produce a sprint-change-proposal and add epic-16 + 16-1..16-5 rows to sprint-status.yaml".
Proposal recorded as **approved**; `sprint-status.yaml` updated accordingly (Epic 16 added as
`backlog`; `epic-15` corrected to `done`).
