# Functional / RBAC / Workflow Audit - Security and Runtime Checkpoint

**Status:** Audit only. No application fix has been implemented.

**Evidence date:** 2026-07-11

## Scope and approved V1 decisions

This checkpoint covers direct API authorization, data isolation, stage
permissions, workflow transitions, dynamic field enforcement, documents,
claims, notifications, audit history, and sampled frontend/backend permission
parity.

The following are accepted V1 behavior and are not defects:

- Stage EXECUTE grants every valid outgoing transition from that stage, subject
  to runtime conditions, claims, comments, field validation, status, and
  optimistic locking.
- Field visibility is stage-scoped. Every authorized viewer of the same stage
  receives the same visible field set.
- Workflows that need different executor groups or field audiences must model
  those responsibilities as separate stages.

## Executive security checkpoint

The focused controls are generally strong once a request is inside a valid
organization scope: bank isolation, hidden fields, documents, claims, stale
versions, audit history, notifications, and transition validation have direct
automated evidence.

The request policy has one critical boundary failure: a user whose `bank_id` is
NULL is treated as in scope for every request, even when `DataScope` explicitly
denies that user's organization classification. With matching stage metadata,
this permits cross-organization reading and transition execution.

The screen-permission write API also treats UI-hidden admin-only screens as
delegable. This can be chained into role self-escalation.

## Confirmed findings

### RBAC-004 - Cross-organization request read and execution bypass

**Severity:** Critical

**Category:** Authorization / IDOR / Workflow integrity / Data isolation

**Affected surface:** `/api/v1/engine-requests/{id}` and request subresources.

**Role and organization:** role-free user in an `OTHER`-classification
organization with stage VIEW or EXECUTE metadata and `bank_id = NULL`.

**Current behavior:**

- `DataScope::forUser()` resolves `OTHER` to neither system-wide nor own-bank
  access, so list queries return no records.
- `EngineRequestPolicy::inScope()` returns true for every request whenever
  `user.bank_id === null`.
- Stage permissions then independently authorize VIEW or EXECUTE.

**Dynamic evidence:**

| Operation                         | Expected | Actual |
| --------------------------------- | -------- | ------ |
| Request list                      | Empty    | Empty  |
| Request detail by ID              | 403/404  | 200    |
| Form schema                       | 403/404  | 200    |
| History                           | 403/404  | 200    |
| Workflow graph                    | 403/404  | 200    |
| Document list                     | 403/404  | 200    |
| Execute valid outgoing transition | 403/404  | 200    |

The transition probe used an isolated published workflow, a bank-owned request,
an `OTHER` organization executor, a valid outgoing transition, and stage
EXECUTE metadata. The transition endpoint returned HTTP 200.

**Security impact:** unauthorized cross-organization disclosure and mutation of
bank workflow requests. History and document metadata are exposed, and request
state can be advanced by an organization whose data scope is deny-all.

**Root cause:** list paths use `DataScope`; detail and mutation policies use a
different null-bank heuristic. Stage permission is being treated as sufficient
without first enforcing the same organization data scope.

**Reproduction:**

- `test_cf3_other_classification_user_cannot_view_request_by_id`
- `test_cf3_other_classification_user_cannot_access_request_subresources`
- `test_cf3_other_classification_executor_cannot_transition_bank_request`

**Regression recommendation:** add a policy-level organization classification
matrix for VIEW and EXECUTE, then repeat it for show, form-schema, history,
graph, documents, draft, claim, and actions. Test NATIONAL_COMMITTEE,
BANKING_SECTOR own bank, BANKING_SECTOR other bank, OTHER, null organization,
and system administrator separately.

### RBAC-002 - Admin-only screen delegation enables role self-escalation

**Severity:** High

**Category:** Privilege escalation / Administrative authorization

**Current behavior:** all eight `ADMIN_ONLY_SCREENS` keys were accepted by
`PUT /api/v1/roles/{role}/screen-permissions`:

```text
workflow_designer, users, teams, roles, screen_permissions,
reference_data, organizations, banks
```

The matrix hides these screens, but the write API validates against every
screen except `requests`.

**Escalation chain:**

1. Administrator grants Support `screen_permissions:MANAGE` through the API.
2. Support calls the same endpoint for its own role.
3. Support grants itself `workflow_designer:MANAGE`.
4. The self-grant returns HTTP 200 and designer policies honor it.

**Security impact:** a role delegated one supposedly admin-only capability can
expand its own administrative authority. The UI does not expose the operation,
but direct API calls do.

**Regression recommendation:** reject universal and admin-only keys in the
write request, enforce the target-role and caller contract server-side, and add
one negative feature case per protected key plus an explicit self-escalation
chain test.

### RBAC-001 - Historical inactive administrator access reaches live APIs

**Severity:** High

**Expanded evidence:**

- Inactive-only historical admin reached `/api/admin/settings` with HTTP 200.
- Inactive-only historical admin opened a request by ID with HTTP 200.
- A user reassigned from admin to Support in an `OTHER` organization listed two
  bank requests although `DataScope` should return zero.
- `isSystemAdmin()` remained true after reassignment.

The system's passing role-model tests confirm one active role is the intended
contract and that prior pivots are deliberately deactivated, not retained as
authorization inputs.

**Regression recommendation:** test every privileged identity helper against
active pivot, inactive pivot, inactive role record, no active role, reassigned
role, loaded relationship, and unloaded relationship states. Add direct API
coverage for settings, audit, request list/detail, search, users, banks,
operations dashboards, claim override, and FX operations.

### RBAC-003 - Derived frontend capabilities include inactive identities

**Severity:** Medium

The previously confirmed `/auth/me` mismatch remains valid. Runtime stage
resolution excludes inactive roles and teams, while capability derivation does
not. This is a frontend visibility mismatch; the runtime resolver still denies
the inactive identity.

### UI-RBAC-001 - Workflow designer direct URL has a blank denial state

**Severity:** Medium

`/admin/workflows` is the only page found that uses `ScreenGuard` without
`definePageMeta`. Support direct navigation mounts the page, issues a forbidden
designer request, and displays an empty shell. Comparable admin routes redirect
to `/forbidden` before their protected API request.

### WF-001 - Canonical published workflow fails its own validation contract

**Severity:** High

**Category:** Workflow configuration / Audit integrity / Seeder bypass

**Current behavior:** `ImportFinancingWorkflowSeeder` inserts the canonical
version directly with state `PUBLISHED`; it does not pass the version through
the publish validation service. Running `WorkflowVersionValidator` against a
freshly seeded canonical version returns five errors:

- Four rejection transitions require confirmation messages but have none:
  `INTERNAL -> CREATE`, `EXEC -> CLOSED_REJECTED`, `FX_CONFIRM -> FX`, and
  `FINAL -> FX_CONFIRM`.
- The `SUPPORT:ADD_NOTES` self-loop is not marked `is_self_loop`.

All four rejection transitions also set `requires_comment = false`. This
conflicts with the authoritative role specifications, which require explicit,
audit-preserved reasons for consequential reject and return decisions.

**Dynamic evidence:** isolated `DatabaseSeeder` execution followed by the same
validator used by the designer reproduced all five validation errors. Separate
secure-expectation probes identified the four comment-free rejection paths and
the unmarked Support self-loop.

**Impact:** the shipped canonical configuration does not satisfy the rules the
designer applies to user-published versions. Reject actions can execute without
a reason, weakening operator guidance and the business-readable audit trail.

**Root cause:** direct published-state seeding bypasses the publish gate, and
the parity manifest asserts structural equality without asserting that the
canonical result validates.

**Regression recommendation:** add a canonical-seed test requiring an empty
validator result, require comments and confirmation copy on documented
consequential transitions, and explicitly mark intentional self-loops.

**Reproduction:** `Phase3WorkflowConfigurationProbeTest`.

## Secure controls verified

### Stage permissions and fields

Focused stage and field suites passed 46 tests with 105 assertions:

- Organization-only, organization/team/role, user, and wildcard row matching.
- AND within a permission row and OR across rows.
- EXECUTE implies VIEW; VIEW does not imply EXECUTE.
- Inactive teams and roles excluded by runtime resolution.
- Published workflow permission and field-rule immutability.
- Hidden-field writes rejected.
- Read-only field changes rejected.
- Required fields enforced on transition, not ordinary draft save.
- FILE values require server-side document evidence.
- Static/dynamic option, date, numeric, regex, length, and checkbox validation.

The designer API requires an organization on new permission rows, while the
runtime resolver still supports all-NULL wildcard rows already present in the
database. Wildcard access therefore remains a migration/legacy configuration
risk to inventory in the published workflow, not a failing matching rule.

The inspected local canonical version has no all-NULL wildcard rows. Its only
mixed-audience stage is `FX_CONFIRM`: commercial banks receive VIEW and the
national FX-confirmation team receives EXECUTE. All 38 fields are visible and
read-only there, so no conflicting field audience was found under the accepted
V1 stage-scoped model.

### Workflow runtime

Focused runtime suites verified:

- Request creation permission and bank/merchant scope.
- Draft save and stale-version rejection.
- Valid transition, wrong-stage transition, unavailable transition,
  non-executor denial, required comment, and terminal-state rejection.
- Final outcomes: CLOSED, REJECTED, CANCELLED, and ABANDONED.
- Claim, competing claimant, heartbeat, non-holder denial, release, and TTL.
- Transaction rollback when a stage hook fails.
- History and audit-log creation.
- Notification inbox ownership and audience scoping.
- Voting executor restrictions and stale-version rejection.
- SWIFT and signed-FX PDF-only upload restrictions.

Optimistic stale-version tests provide duplicate-submit regression evidence.
A true parallel MySQL transition race remains a load/integration test gap; the
existing audit load plan specifies 20-50 concurrent callers with exactly one
success and all others stale.

### Hidden data and documents

Focused visibility suites passed:

- Hidden fields absent from detail, list, and form schema.
- Hidden field-linked documents absent from lists and not downloadable.
- Unlinked visible documents remain accessible to an authorized viewer.
- Cross-bank/cross-organization document access denied for normally scoped
  users.
- Pending malware scan and checksum mismatch block download.
- Superseded document replacement and history behavior are enforced.

No hidden-field or hidden-document exposure was confirmed in this slice.

### Other scoped resources

Focused suites passed for:

- Bank-admin user listing, detail, and deactivation within own bank.
- Audit-log own-bank scope and deny-without-bank behavior.
- Report own-bank aggregates, system-wide NC aggregates, export capability,
  job-time requester scope, and export auditing.
- Notification recipient ownership and bank/NC audience scope.
- Search bank scope and no-organization deny behavior.
- Financing merchant probes across banks.
- Governance bank, organization, team, and role consistency, lifecycle guards,
  protected records, optimistic versions, and audit logging.

## Frontend/backend parity sample

| Identity   | Backend `/auth/me`                                                                            | Visible navigation                                           | Direct protected route                                           |
| ---------- | --------------------------------------------------------------------------------------------- | ------------------------------------------------------------ | ---------------------------------------------------------------- |
| CBY Admin  | Full administrative capabilities                                                              | Full admin navigation                                        | `/admin/workflows` loads with heading and no console error       |
| Bank Admin | Reports VIEW, requests VIEW, users MANAGE/VIEW, merchants MANAGE/VIEW, settings/notifications | Reports, requests, staff, merchants, settings, notifications | `/admin/roles` redirects to explicit 403                         |
| Support    | Requests and notifications only in sampled seed                                               | Requests, dashboard, notifications, settings                 | `/admin/roles` redirects; `/admin/workflows` blank-denial defect |

Frontend role-surface tests passed: 178 tests across the route/navigation
catalog. These tests do not cover the missing page metadata on
`/admin/workflows`, which requires a dedicated regression test.

## Remaining audit work before implementation planning

1. Inventory the actual published workflow for wildcard permissions and stages
   that combine audiences with conflicting action or visibility needs.
2. Run a real MySQL parallel transition race or explicitly defer it to the load
   test phase with acceptance criteria.
3. Complete page-by-page functional and UX checks for loading, empty, error,
   denial, stale data, RTL, keyboard, focus, and responsive behavior.
4. Expand role/browser sampling to Data Entry, Bank Reviewer, SWIFT, Executive,
   and Director primary workflows.
5. Produce the final consolidated role matrix, workflow coverage matrix,
   findings table, test plan, and implementation roadmap for approval.

## Local data note

The local database also contains a non-source workflow `DBGWF`, inserted as
published on 2026-07-09. It has no final stage, outgoing transition, reachable
final, or executor and fails the current validator with four errors. Current
publish API tests correctly reject equivalent invalid drafts, and no source or
seeder creates `DBGWF`; it is therefore classified as stale/manual local data,
not a confirmed current publishing defect. It was not deleted during the
audit.
