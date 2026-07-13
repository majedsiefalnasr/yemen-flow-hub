# Testing Guide

**Verified:** 2026-07-13, against `backend/app/`, `backend/tests/`,
`frontend/app/`, and the canonical architecture docs directly. This is
the live testing authority, replacing the archived
[`docs/archive/testing-manual/`](archive/testing-manual/README.md),
which predates the dynamic workflow engine and describes a retired
fixed status pipeline and Executive Voting flow. Reused from that
archive: the test-user-alias table, the evidence template, and the
entry/exit-criteria shape — not its workflow paths, status vocabulary,
or voting steps, all of which no longer exist.

For the concepts this guide tests, see:

- [`architecture/05-request-state-model.md`](architecture/05-request-state-model.md) — the four request-state fields
- [`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md) — Designer-defined stages and transitions
- [`architecture/03-permission-model.md`](architecture/03-permission-model.md) — the two authorization systems, claim ownership
- [`architecture/04-dashboard-architecture.md`](architecture/04-dashboard-architecture.md) — the actionable-work invariant

---

## What this guide covers

Automated tests are the primary regression evidence — this guide
describes what must be true and points at the automated coverage that
proves it, plus a manual-verification checklist for anything not yet
covered by a focused automated test. Runtime source, Designer
configuration, and the canonical architecture documents remain
authoritative; tests exist to catch regressions against that
authority, not to define it. This guide is not a fixed script of
statuses to walk through; the workflow itself is Designer-defined and
varies by published `WorkflowVersion`.

---

## 1. Request state assertions

Every test that inspects an `EngineRequest`'s state must assert against
the four independent fields — never reconstruct a combined status label:

| Field                         | What to assert                                                                                                                                                                                                |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `runtime_status`              | One of `ACTIVE`, `CLOSED`, `REJECTED`, `CANCELLED`, `ABANDONED`. Read the `runtime_status` key in API responses, not the legacy `status` alias.                                                               |
| `current_stage`               | The `WorkflowStage` the request actually occupies — `code`/`name` are Designer-defined per workflow version, not a fixed vocabulary to assert by literal string across versions.                              |
| `current_stage.semantic_role` | **Nullable.** One of the 8 `StageSemanticRole` cases, or `null` for a stage that predates the semantic-role rollout. A test must not assume this field is always present.                                     |
| `final_outcome`               | Present in the API response **only** when `current_stage.is_final` is `true` — absent (not `null`) on a non-terminal request. Assert key absence, not just a falsy value, when testing the non-terminal case. |

**Do not test for a `runtime_status: COMPLETED` value** — it does not
exist. Successful completion is `runtime_status: CLOSED` with
`final_outcome: COMPLETED`.

**Do not test for any retired status value** — `SUBMITTED`,
`BANK_APPROVED`, `EXECUTIVE_VOTING_OPEN`, `EXECUTIVE_VOTING_CLOSED`,
`AUTO_ABSTAIN_TIMEOUT`, `CUSTOMS_DECLARATION_ISSUED`, or any other value
from the retired 22-value enum. None have a current equivalent to map
to; asserting against them tests a vocabulary the system no longer
speaks.

**Focused commands:**

```bash
php artisan test tests/Feature/Engine/EngineRequestResourceStateContractTest.php
php artisan test tests/Feature/EngineRequest
```

---

## 2. Dynamic, Designer-defined workflow paths

The workflow graph — stages, transitions, which roles may execute which
transition — is defined per `WorkflowVersion` in the Designer, not
hardcoded. A test suite must not assume a single fixed path (e.g. "Data
Entry → Bank Review → Support → SWIFT → Executive → FX confirmation")
holds for every workflow version; it holds only for the specific
published version under test.

**What to verify:**

- A request only ever advances along a `WorkflowTransition` that exists
  on its current `WorkflowVersion`, from its actual `current_stage`.
- `EngineTransitionService::execute()` is the only path that moves a
  request between stages — no test should mutate
  `current_stage_id`/`status` directly on the model as a setup
  shortcut for a transition test; that bypasses the checks the test is
  meant to exercise.
- The enforcement order inside `execute()` (lock → active check →
  optimistic-version check → transition/from-stage match → stage
  permission → claim) is itself testable: a request that fails an
  earlier check must never reach a later one (e.g. a user without
  EXECUTE permission must get `StageExecutionForbidden`, not a claim
  error, even if they also don't hold the claim).
- Publish-time validation (`WorkflowPublishRulePack`) rejects
  unreachable stages and blocks publishing when the initial/any final
  stage is `INACTIVE`, or a transition references an inactive stage.

**Focused commands:**

```bash
php artisan test tests/Feature/Workflow
php artisan test --filter=EngineTransitionServiceTest
php artisan test tests/Feature/Designer
```

---

## 3. Permission, capability, and claim checks are distinct

Yemen Flow Hub runs **two independent authorization systems** plus a
separate claim gate — a test must not conflate them:

1. **Screen capabilities** (`PermissionService::userHasCapability()`) —
   role-based, gates admin-console/analytics screens
   (`system_dashboard`, `bank_analytics`, `merchants`, …). Test that a
   user without a capability cannot view the gated screen or its data,
   and that granting/revoking the capability via
   `PUT /api/v1/roles/{role}/screen-permissions` changes access
   immediately (cache is invalidated, not just written).
2. **Workflow stage permissions** (`StagePermissionResolver::userCanAccessStage()`) —
   identity-set based (`organization_id`/`team_id`/`role_id`/`user_id`,
   `null` = wildcard), gates VIEW/EXECUTE on a specific
   `WorkflowStage`. Test both directions: a granted identity set can
   access the stage; a non-matching identity set cannot, even if the
   user holds a role that has access on a _different_ stage or a
   different organization's identical stage code.
3. **`can_execute`** — the API-surfaced field a request carries per
   viewer, derived from stage permission **and** claim state together.
   Test that `can_execute` is `false` when stage permission is absent,
   and separately `false` when stage permission is present but
   `requires_claim` is true and the viewer doesn't hold the claim — two
   different reasons for the same boolean, both must be covered.
4. **Claim ownership** — checked only after stage permission passes
   (§4). A user without EXECUTE permission must never reach a claim
   error; assert the specific rejection reason (`StageExecutionForbidden`
   vs. `CLAIM_NOT_HELD`), not just "request rejected."

**Do not test `App\Enums\UserRole` as if it gates either authorization
system directly** — neither runtime resolver consults it; it is a
classification/serialization enum layered on top.

**Focused commands:**

```bash
php artisan test tests/Feature/Permission
php artisan test tests/Unit/Workflow/StagePermissionResolverTest.php
php artisan test tests/Feature/Workflow/StagePermissionTest.php
```

---

## 4. Organization and `DataScope` enforcement

`App\Services\Authorization\DataScope` is a plain static-method service
— **not** a global scope or automatic query filter. Every read surface
must call it explicitly (`DataScope::forUser()` +
`DataScope::applyTo()`); a test covering a new list/search/report
endpoint must confirm that surface actually invokes it, not assume
org-scoping is automatic because it is elsewhere in the codebase.

**Resolution to verify:**

- `NATIONAL_COMMITTEE` organization classification → system-wide, no
  filter.
- `BANKING_SECTOR` → own-bank only.
- Any other classification → deny-by-default (`whereRaw('1 = 0')`) —
  test that this actually returns zero rows, not an error or an
  unfiltered set.

**Direct cross-organization API denial — required test shape:**
authenticate as a user in Bank A, request a resource that belongs to
Bank B by ID (not by list-filtering) — a request record, a document, a
merchant, a report — and assert denial (403/404 per the endpoint's
existing convention, not a 200 with leaked data). List-endpoint
filtering tests are not a substitute for this: they prove Bank B's data
doesn't appear in Bank A's list, not that direct ID access to a Bank B
record is blocked when Bank A guesses or reuses an ID.

**Focused commands:**

```bash
php artisan test tests/Unit/Services/Authorization/DataScopeTest.php
php artisan test tests/Feature/Financing/FinancingDataScopeTest.php
php artisan test tests/Feature/Dashboard/DashboardDataScopeTest.php
php artisan test tests/Feature/Search/SearchDataScopeTest.php
php artisan test tests/Feature/Compliance/ComplianceDataScopeTest.php
```

---

## 5. Claim lifecycle: claim, heartbeat, release, expiration, claim-loss

**The live claim TTL is admin-configurable**, not a static config
value. `EngineClaimService` reads `SettingResolver::get('support_claim_ttl', 15)`
— an admin-editable setting (5–60 minutes), cached and backed by the
`SystemSetting` DB row. `config('workflow.support_claim_ttl_minutes')`
exists in `backend/config/workflow.php` but is **not** read by
`EngineClaimService` at runtime; it is read only by
`EngineRequestScenarioBuilder`, a database seeder, for synthetic test
scenarios. A test asserting claim TTL behavior must not assert against
the config key — assert against the admin setting (`SettingResolver`)
or, for the expiry sweep itself, against the persisted
`claim_expires_at` column directly.

**Endpoints:**

- Claim: `POST /api/v1/engine-requests/{id}/claim`
- Heartbeat: `POST /api/v1/engine-requests/{id}/claim/heartbeat` (frontend pings every 60 seconds; extends `claim_expires_at`)
- Release: `DELETE /api/v1/engine-requests/{id}/claim`

**What to verify:**

- Claiming a request that `requires_claim` sets `claimed_by`,
  `claimed_at`, `claim_expires_at` (now + resolved TTL), `claim_stage_id`.
- A second user cannot claim an already-claimed, unexpired request.
- Heartbeat from the claim holder extends `claim_expires_at`; heartbeat
  from a non-holder is rejected.
- Release clears the claim fields; force-release by a non-holder
  requires `RoleCodes::SYSTEM_ADMIN` and otherwise returns
  `CLAIM_NOT_HELD` (403) — the exact error code thrown by
  `EngineException::claimNotHeld()`.
- **Expiration**: `workflow:expire-engine-claims` releases rows where
  `claim_expires_at` is in the past — it reads only that persisted
  column, not the TTL setting itself (the TTL was already baked in at
  claim/heartbeat time). Test the sweep command directly against a
  request with an artificially past `claim_expires_at`, independent of
  waiting out a real TTL window.
- **Claim-loss**: a held claim that expires (via the sweep, or by
  elapsed time in an integration test) must make the request
  claimable again by another user, and must flip `can_execute` to
  `false` for the original holder if they no longer hold an unexpired
  claim on a `requires_claim` stage.

**Focused commands:**

```bash
php artisan test tests/Feature/Engine/EngineClaimServiceTest.php
php artisan test tests/Feature/Engine/EngineClaimEndpointTest.php
php artisan test tests/Feature/Engine/EngineClaimGuardTest.php
php artisan test tests/Feature/Engine/EngineSupportClaimTest.php
php artisan test tests/Feature/Engine/ExpireEngineClaimsCommandTest.php
php artisan test tests/Feature/Operations/ExpireEngineClaimsHeartbeatTest.php
php artisan test tests/Feature/Workflow/ClaimTtlSettingTest.php
php artisan test tests/Feature/Workflow/EngineClaimLifecycleTest.php

# Frontend: heartbeat composable
pnpm exec vitest run app/tests/unit/composables/useEngineClaim.test.ts
```

---

## 6. The shared actionable-work record-ID invariant

The dashboard `actionable` count, dashboard preview record IDs, the
`/workflows` nav badge, and `/my-queue` all resolve through **one**
contract — `App\Services\Workflow\UserActionableRequestQuery` — and
must stay equal **by record ID**, not merely by matching count. A test
that only compares counts across these four surfaces can pass while the
underlying record sets differ; assert the actual ID sets are equal
(or a strict subset relationship where the surface is intentionally
bounded, e.g. a preview `LIMIT`).

**Where this applies:**

- `GET /api/v1/engine-requests/my-queue` (`EngineRequestController::myQueue()`)
- `GET /api/dashboard/work`'s `actionable` section (`DashboardWorkController::work()`)
- The `/workflows` nav badge (frontend reads `dashboardWorkStore.work.actionable.count` — the same in-memory value as the dashboard KPI card, not a separate fetch)

Any new "how much work does this user have" surface must go through
`UserActionableRequestQuery`, not a bespoke query — a test adding
coverage for a new surface should assert it delegates to this service
rather than duplicating its filtering logic.

**Focused commands:**

```bash
php artisan test tests/Feature/Engine/UserActionableRequestQueryTest.php
php artisan test tests/Feature/Dashboard

# Frontend: dashboard store and nav badge composable
pnpm exec vitest run app/tests/unit/stores/dashboard.store.test.ts
pnpm exec vitest run app/tests/unit/components/MyWorkDashboard.test.ts
```

---

## 7. Manual verification checklist

For surfaces not yet covered by a focused automated test, use this
checklist during manual/exploratory verification. Reused in shape from
the archived manual, rewritten against current architecture:

- [ ] Each role sees only navigation, queues, request data, documents,
      and actions its screen capabilities and stage permissions
      actually grant — not a fixed per-role UI list.
- [ ] Request visibility is `DataScope`-scoped: bank roles see only
      their own bank; CBY roles per their organization classification.
- [ ] The request only advances through transitions that exist on its
      actual `WorkflowVersion` from its actual `current_stage` — verify
      against the live Designer configuration, not a fixed path
      assumption.
- [ ] Forbidden controls are not rendered — not merely disabled or
      rejected server-side after the fact.
- [ ] Backend rejection still blocks a forbidden action if a tester
      reaches an endpoint directly or hits stale UI state.
- [ ] Every workflow transition appears in `workflow_history` (stage
      log) and `audit_logs` (actor, `user_role` at time of action,
      `old_values`/`new_values`, shared `correlation_id`).
- [ ] PDF-only validation is enforced for every document upload
      surface.
- [ ] Terminal requests (`runtime_status` other than `ACTIVE`) cannot
      be edited, transitioned, or have documents replaced —
      `REQUEST_CLOSED` (403).
- [ ] A stale/expired claim on a `requires_claim` stage correctly
      releases and becomes claimable by another eligible user.
- [ ] Direct cross-organization ID access is denied (see §4) — not
      just filtered out of list views.

### Test users

Reused verbatim from the archived manual — the alias/role/organization
shape remains useful regardless of workflow version:

| Alias           | Role                 | Organization |
| --------------- | -------------------- | ------------ |
| `admin-cby`     | `CBY_ADMIN`          | CBY          |
| `director-cby`  | `COMMITTEE_DIRECTOR` | CBY          |
| `exec-a-cby`    | `EXECUTIVE_MEMBER`   | CBY          |
| `exec-b-cby`    | `EXECUTIVE_MEMBER`   | CBY          |
| `support-a-cby` | `SUPPORT_COMMITTEE`  | CBY          |
| `support-b-cby` | `SUPPORT_COMMITTEE`  | CBY          |
| `swift-bank-a`  | `SWIFT_OFFICER`      | Bank A       |
| `swift-bank-b`  | `SWIFT_OFFICER`      | Bank B       |
| `bank-admin-a`  | `BANK_ADMIN`         | Bank A       |
| `bank-admin-b`  | `BANK_ADMIN`         | Bank B       |
| `reviewer-a`    | `BANK_REVIEWER`      | Bank A       |
| `reviewer-a-2`  | `BANK_REVIEWER`      | Bank A       |
| `reviewer-b`    | `BANK_REVIEWER`      | Bank B       |
| `reviewer-b-2`  | `BANK_REVIEWER`      | Bank B       |
| `entry-a`       | `DATA_ENTRY`         | Bank A       |
| `entry-a-2`     | `DATA_ENTRY`         | Bank A       |
| `entry-b`       | `DATA_ENTRY`         | Bank B       |
| `entry-b-2`     | `DATA_ENTRY`         | Bank B       |

Use Bank B for negative organization-scope checks (§4). Bank B data
must never appear to Bank A users, and Bank A data must never appear to
Bank B users — including by direct ID access, not only by list
filtering.

### Evidence template

Reused verbatim from the archived manual:

```text
Run ID:
Date:
Environment:
Tester:
Browser:
Seed users:
Primary request number:
Alternate request numbers:

Step:
Actor:
State before (runtime_status / current_stage / semantic_role):
Action:
Expected state after:
Actual state after:
Screenshots:
Audit/history evidence:
Pass/Fail:
Notes:
```

Use `playwright-cli` for browser evidence (see `AGENTS.md`).

### Exit criteria

- Automated coverage exists for §1–6 above, or the gap is tracked as
  known debt.
- Manual checklist items are exercised at least once per released
  workflow version with materially different stage/permission
  configuration.
- Cross-bank visibility and direct cross-organization ID-access checks
  pass.
- Audit and `workflow_history` match the full transition chain for any
  manually exercised request.
- No role-inappropriate workflow control is visible during manual
  verification.

---

## Verification ladder for this repository

Default to the smallest relevant test — see `AGENTS.md`'s Quality
Gates for the full ladder. Full suites (`php artisan test`,
`pnpm test`) are for release checks, broad refactors, security-critical
changes, or explicit request — not the default for a single-behavior
change.
