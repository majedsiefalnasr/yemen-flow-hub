# Functional / RBAC / Workflow Audit - Role and UI Checkpoint

**Status:** Audit only. No application behavior has been changed.

**Evidence date:** 2026-07-11

## Scope

This checkpoint compares the seeded canonical workflow, authoritative
`docs/user-view` role contracts, `/auth/me`, navigation, primary dashboards,
request pages, and live MySQL API behavior.

Sampled identities:

- Data Entry: `entry@ybrd.com.ye`
- Bank Reviewer: `reviewer@ybrd.com.ye`
- Support Committee: `support1@cby.gov.ye`
- SWIFT Officer: `swift@ybrd.com.ye`
- Executive Member: `exec1@cby.gov.ye`
- Committee Director: `director@cby.gov.ye`
- Bank Admin and CBY Admin evidence is recorded in the prior checkpoint.

## Confirmed findings

### WF-002 - Canonical workflow does not implement authoritative role decisions

**Severity:** Critical

**Category:** Business workflow integrity / RBAC / Audit semantics

The seeded `IMPORT_FINANCING` workflow is structurally much simpler than the
authoritative role contracts and assigns consequential decisions to the wrong
roles or omits them entirely.

#### Bank Reviewer

**Expected:** Approve, Return for correction, and terminal Reject are three
distinct decisions. Return and reject require explicit reasons, and terminal
rejection is irreversible.

**Actual:** `INTERNAL` has only:

- `APPROVE -> SUPPORT`
- `REJECT -> CREATE`

The request UI showed only `اعتماد` and `رفض`. The action labeled Reject moves
the request back to CREATE, so it behaves as a return while being recorded and
presented as rejection. No terminal bank-rejection path exists in the canonical
graph.

#### Support Committee

**Expected:** after claiming, Support can Approve, Return to Data Entry, or
Reject to Bank Reviewer, with explicit reasons.

**Actual:** `SUPPORT` has only:

- `APPROVE -> EXEC`
- `ADD_NOTES -> SUPPORT`

The live request UI showed `اعتماد` and `إضافة ملاحظات`. There is no Return or
Reject transition. The self-loop is also not marked intentional and causes the
published canonical version to fail current validation.

#### Executive Member and Committee Director

**Expected:** each Executive Member casts one vote. Members cannot directly
advance or reject the request. The Committee Director closes/finalizes the
session, resolves ties, and is distinct from an Executive Member.

**Actual canonical metadata:** `EXEC` and `FINAL` both grant EXECUTE to
`committee_manager`, which maps to `EXECUTIVE_MEMBER`. `EXEC` directly exposes
APPROVE and REJECT transitions; `FINAL` also exposes APPROVE and REJECT. The
`committee_director` role is assigned neither stage.

Existing runtime tests explicitly prove an Executive Member can execute the
approve/reject workflow transition. This is not merely a missing frontend
control: the backend stage permission and transition model currently authorizes
the member as the stage decision-maker. The live Executive queue additionally
returned six `EXEC` records and six `FINAL` records with `can_execute: true` for
the same member. After the limiter reset, direct request pages confirmed active
`Approve` and `Final Reject` controls at `EXEC`, followed by active `Final
Approval` and `Reject` controls at `FINAL` for that same Executive Member.

**Impact:** workflow outcomes, return/rejection meaning, segregation of duties,
voting governance, and audit history do not match the product's authoritative
business process.

**Regression recommendation:** define an approved V1 workflow coverage manifest
from the role specifications, including actor, stage, action meaning, target,
reason requirement, terminality, and claim/vote rules. Validate the seeded
workflow against that manifest, not only against its current self-referential
fixture.

### RBAC-005 - Director navigation exposes a route its capabilities deny

**Severity:** High

**Category:** Frontend/backend permission parity / Workflow assignment

**Current behavior:** the Director's `/auth/me` response contains audit,
reports, notifications, and settings but no `requests` capability. The sidebar
nevertheless shows `طلبات التمويل` with a count of 6. Following the visible link
redirects to `/forbidden?path=/workflows`.

**Root cause:** role-surface navigation assumes the Director can use the request
page, while workflow-derived screen capability has no Director stage assignment.

**Impact:** a primary role workflow is visibly offered and then denied. The
Director cannot inspect the same request surface needed for finalization and
governance duties.

**Regression recommendation:** add route/navigation tests that combine the
actual seeded `/auth/me` capability map with rendered sidebar items for every
role. Static role-map tests alone are insufficient.

### UI-FX-001 - Director dashboard and FX-confirmation queue disagree

**Severity:** High

**Category:** Functional consistency / Queue accuracy

**Current behavior:** the Director dashboard reports six requests ready for
external FX confirmation and renders six rows. `/customs` reports zero ready
requests and zero issued confirmations in the same authenticated session.

**Impact:** the Director is told work is pending but the dedicated operational
queue provides no actionable records.

**Root cause:** the two screens use incompatible queue contracts. The dashboard
uses `DashboardStatsService::committeeDirectorStats()` and queries the
`fx_confirmation_pending` read-model bucket. The dedicated page calls
`useEngineRequests().fetchQueue()`, which requests
`/api/v1/engine-requests/my-queue`. That endpoint is an executable-stage work
queue, but the seeded Director has no executable stage assignment. The same six
records are therefore counted by the dashboard and excluded from `/customs`.

**Evidence:** `frontend/app/pages/customs/index.vue:37-42`,
`frontend/app/composables/useEngineRequests.ts:58-69`, and
`backend/app/Services/Dashboard/DashboardStatsService.php:378-404`.

**Regression recommendation:** for the same seeded user, assert dashboard ready
count equals the dedicated queue total and every dashboard row links to an
actionable request.

### WF-003 - SWIFT stage can advance without its mandatory document package

**Severity:** Critical

**Category:** Workflow integrity / Required evidence / Frontend-backend parity

**Affected role and stage:** SWIFT Officer at canonical stage `FX` (the SWIFT
semantic stage).

**Current behavior:** the SWIFT dashboard correctly identifies pending document
work, but both “Upload SWIFT documents” and “View request” navigate to the same
generic request detail. That detail exposes a generic `Approve` transition and
does not render the SWIFT reference or either required PDF control. The backend
permits the stage transition with an empty `data` payload and without documents.

**Expected behavior:** the officer must provide a SWIFT reference, a SWIFT PDF,
and an FX-confirmation-request PDF before the request can leave the SWIFT stage.
The role must not receive an approval action.

**Reproduction:**

1. Sign in locally as `swift@ybrd.com.ye`.
2. Open the SWIFT dashboard; observe three pending uploads.
3. Select `رفع وثائق السويفت` for `ENG-2026-YBRD-A007`.
4. Observe navigation to `/workflows/instances/7` and the enabled generic
   `اعتماد` action, with no SWIFT package form.
5. Run `php artisan route:list --path=swift`; no dedicated route exists.
6. Run `EngineSwiftUploadTest`; its transition regression posts `data: []`
   without uploading documents and expects HTTP 200.

**Root cause:** the canonical seeder defines `FX -> FX_CONFIRM` as a plain
`APPROVE` transition and makes every downstream field read-only and optional.
`SwiftUploadForm.vue` and `UploadSwiftRequest.php` contain much of the intended
package validation but have no caller or route. Dashboard action wiring sends
both commands to the generic detail page.

**Evidence:**
`backend/database/seeders/ImportFinancingWorkflowSeeder.php:267-289,324-337`,
`backend/tests/Feature/Engine/EngineSwiftUploadTest.php:191-207`,
`frontend/app/components/dashboard/SwiftOfficerDashboard.vue:125-153`, and the
absence of references to `SwiftUploadForm` outside its own file.

**Impact:** a request can progress toward external FX confirmation without the
bank evidence mandated by the business workflow. History can therefore record a
successful SWIFT-stage completion for a package that never existed.

**Regression recommendation:** add an integration test against the canonical
published workflow proving the transition is rejected until all three package
elements exist, plus API tests for missing/invalid PDFs, wrong bank, wrong role,
duplicate submission, and stale request version. Add a browser test that reaches
the dedicated upload surface and proves the transition control remains disabled
with a specific reason until the complete package is present.

### API-UI-001 - Request stats fail on MySQL and trigger a frontend request storm

**Severity:** High

**Category:** Backend query correctness / Error handling / Rate limiting

**Reproduction:** Executive Member opened `/workflows` against the local MySQL
stack.

**Observed behavior:**

- `GET /api/v1/engine-requests/my-queue` returned 200.
- Both `stats?scope=queue` and `stats?scope=all` returned 500 repeatedly.
- The browser logged 122 errors.
- Repeated reloads exhausted `api-default`; list and stats calls then returned 429.
- The page displayed `Too many requests` rather than the original failure.

**Backend root cause:** `EngineRequestStatsService` starts from
`EngineRequest::withStageEntry()`, which selects `engine_requests.*`, then adds
status aggregates and groups only by `engine_requests.status`. MySQL with
`ONLY_FULL_GROUP_BY` rejects the non-grouped selected columns. SQLite tests pass
and therefore miss the production-like failure.

**Frontend amplification:** the page calls queue plus two stats requests from a
deep watcher over filter/pagination state. Failure/render-driven table state
changes repeatedly trigger `load()` without single-flight, cancellation, or a
stable failure boundary.

**Impact:** the request page becomes unusable, masks the original error, floods
the backend, and consumes the caller's rate-limit budget.

**Regression recommendation:**

- Add a MySQL integration test for stats aggregation under
  `ONLY_FULL_GROUP_BY` for every role/data-scope branch.
- Add a frontend test proving one failed load causes one request batch and a
  stable retry action, with no automatic retry loop.
- Verify 500 remains the surfaced root error and does not degrade into 429 due
  to client behavior.

### UI-RBAC-002 - Request-detail denial renders a blank page

**Severity:** Medium

**Category:** Permission-denial UX / Error handling

**Current behavior:** while signed in as YBRD SWIFT Officer, direct navigation
to TIIB request `/workflows/instances/35` received the correct backend 403 but
rendered only the application shell and an empty main area. Two raw fetch errors
were written to the console; no forbidden message, safe redirect, or recovery
action appeared.

**Root cause:** `load()` awaits `store.loadInstance()` without catching the
error. `loadInstance()` assigns `current` only after `show()` succeeds, and the
page template renders only loading or `store.current`; it has no error/denial
branch.

**Impact:** secure denial looks like a broken or indefinitely incomplete page,
which encourages repeated requests and support escalation.

**Regression recommendation:** browser-test direct request URLs for 403, 404,
500, and offline failures. Assert a role-appropriate denial/error state, no
request data, one request attempt, and a safe navigation action.

## Secure and functional controls observed

### Data Entry

- `/auth/me`: requests VIEW/CREATE/UPDATE; merchants VIEW; settings and
  notifications VIEW.
- Navigation matched those capabilities and included New Request.
- `/workflows/new` loaded the published workflow chooser and rendered the
  expected `طلب تمويل جديد` heading without an alert.

### Bank Reviewer

- `/auth/me`: requests VIEW/UPDATE plus settings/notifications.
- Navigation matched the capability set.
- A request at INTERNAL loaded successfully and exposed only transitions from
  that stage. The transition set itself is defective as described in WF-002.

### Support Committee

- `/auth/me`: requests VIEW/UPDATE with audit/report VIEW in the seeded catalog.
- A Support request loaded and correctly required claim continuation before
  decisioning.
- The available transition set is incomplete as described in WF-002.

### SWIFT Officer

- `/auth/me` limited navigation to requests, notifications, and settings; the
  queue and dashboard were bank-scoped and showed three YBRD pending records.
- Business fields on the sampled request were read-only.
- Direct access to TIIB request 35 returned backend 403; the denial UI defect is
  described in UI-RBAC-002.
- The document-package completion control is bypassed as described in WF-003.

### Committee Director

- Reports and audit navigation matched `/auth/me`.
- `/customs` was accessible only to the Director role and used the updated
  external FX confirmation terminology.
- Request navigation and queue counts were inconsistent as described above.

## UX observations

- The active global quick-user-switch control exposed the accessible name
  `تبديل المستخدم السريع`; nameless copies in modal snapshots belonged to the
  inert background tree and are not classified as a defect.
- Error state on `/workflows` includes a retry action, but the retry storm means
  the state is not operationally safe under persistent backend failure.
- Rejection labels are not merely wording issues: they currently describe the
  wrong workflow outcome and would produce misleading history/audit narratives.

## Remaining role slice

1. Focused accessibility checks for keyboard, focus, and dialogs across the
   remaining role pages.

## Verification evidence

- Dedicated Phase 2 and Phase 3 audit probes: 16 failed secure expectations and
  3 baseline PDO deprecation notices. The failures reproduce RBAC-001 through
  RBAC-004 and WF-001; they are intentional audit evidence, not a green suite.
- Audit PHP files pass focused Pint formatting.
- All four audit Markdown files pass Prettier formatting.
