# Dashboard Architecture Decision Report

Evidence date: 2026-07-11. Produced before Phase D, at the user's request. **No
code changed.** Answers the core question: should the permanent architecture be
**one dedicated platform-administration dashboard + one permission/workflow-driven
dynamic dashboard for all workflow users?**

**Recommendation: YES — approve the two-dashboard model,** with Level 1 (fixed
layout, dynamic data) now and Level 2 (metadata widget catalog) classified as a
future enhancement. Evidence and full contracts below.

---

## Part 1 — Current dashboard architecture (as built)

### 1.1 Frontend map

| Layer | File | Role coupling |
| ----- | ---- | ------------- |
| Page (selector) | `pages/dashboard.vue` | **8 hard-coded `role === UserRole.X` branches** (`:63-70`) choose the component; unknown/dynamic role → generic placeholder |
| Role dashboards | `components/dashboard/{DataEntry,BankReviewer,BankAdmin,SupportCommittee,SwiftOfficer,Executive,CommitteeDirector,CbyAdmin}Dashboard.vue` | one bespoke Vue component per role (8) |
| Shared cards | `DashboardKpiCard.vue`, `DashboardSection.vue`, `shared/dashboard/MetricCard.vue`, `MetricGrid.vue` | role-neutral (reusable) |
| Store | `stores/dashboard.store.ts` | role-neutral: `loadStats()` → one `/api/dashboard/stats` call, `stats/loading/error` |
| Stats typing | `composables/useDashboard.ts` | 8 role-specific `*DashboardStats` interfaces + union |
| Nav badges | `composables/useNavBadges.ts` | **3rd role-hardcoded surface**: `switch(role)` mapping each role to a different stats key (`:23-64`) |
| Customs page | `pages/customs/index.vue` | role-guarded (`ROUTE_ROLE_MAP['/customs']`); "ready" list already uses `fetchQueue()` = my-queue |

### 1.2 Backend map

| Layer | File | Role coupling |
| ----- | ---- | ------------- |
| Dashboard entry | `DashboardStatsService::stats()` | **8 `hasRoleCode()` branches** (`:24-31`) → 8 bespoke `*Stats()` methods |
| Per-role stats | `dataEntryStats … cbyadminStats` | each hand-rolls its own read-model bucket queries |
| Read model | `EngineRequestReadModel` | stage buckets resolve `semantic_role IN (...) OR code IN (...)` (`bucket()`); stage codes hard-coded as a **fallback** behind the semantic role |
| Actionable-work truth | `EngineRequestController::myQueue()` (`:283-313`) | **fully dynamic, zero role/stage codes**: `accessibleStageIds($user, EXECUTE)` + `active()` + `forUser()` (DataScope) + per-stage union + SLA priority |
| Nav-badge dashboard read | same `/api/dashboard/stats` | reused by `AppSidebar` → `useNavBadges` |

### 1.3 The structural defect

**The actionable-work query already exists and is dynamic** (`myQueue`), but the
dashboard stats methods and the frontend (component selection + nav badges) are a
**parallel, role-coupled layer that does not reuse it.** Three surfaces
independently decide "what is this user's pending work," keyed on role:

1. `dashboard.vue` picks the component by role.
2. `DashboardStatsService::stats()` picks the stats method by role.
3. `useNavBadges` picks the badge stats-key by role.

Any of the three can disagree with `myQueue` and with each other. **UI-FX-001 was
exactly this:** the Director stats method counted the `fx_confirmation_pending`
bucket (FX_CONFIRM, another team's stage) while `myQueue` counted FINAL — a
disjoint record set. C3 patched the Director method + component but **left the
role-switch architecture intact**, and left a live remnant (below).

---

## Part 2 — Current role/stage hard-coding inventory

| # | Location | Kind | Classification |
| - | -------- | ---- | -------------- |
| 1 | `dashboard.vue:63-70` | 8× `role === UserRole.X` → component | **Replaceable by capability** (admin vs work split) |
| 2 | `DashboardStatsService::stats():24-31` | 8× `hasRoleCode()` → stats method | **Replaceable by stage-permission/shared query** |
| 3 | `useNavBadges.ts:23-64` | `switch(role)` → stats key | **Replaceable by shared actionable count** |
| 4 | `useNavBadges.ts:47-57` | Director badge sums `sessions_ready_to_close + sessions_with_tie + fx_confirmation_pending` | **Legacy / voting remnant — live inconsistency** (see below) |
| 5 | `EngineRequestReadModel:23-43` | stage `codes` in buckets | **Temporary compatibility** (semantic-role fallback; retired by B4) |
| 6 | `RoleCodes::COMMITTEE_DIRECTOR/SUPPORT/...` in stats branches | role codes | **Replaceable by stage permission**, except SYSTEM_ADMIN |
| 7 | `RoleCodes::SYSTEM_ADMIN` branch | role code | **Required platform invariant** (but should be expressed as a capability at the dashboard boundary) |
| 8 | `frontend .../ExecutiveDashboard.vue` voting UI | voting remnant | **Legacy** (Executive voting out of V1) |

### Live inconsistency still present after C3

`useNavBadges` was **not** updated in C3. The Director nav badge still sums the
zeroed voting counters plus the backward-compat `fx_confirmation_pending` alias
(which C3 repointed to the FINAL count). It currently equals 6 only by
coincidence of the alias, through dead voting keys — the badge is **not** reading
the clean `final_pending`. This is precisely the "count = badge = queue must be
one query" principle being violated structurally, and is direct evidence for the
shared-query recommendation.

---

## Part 3 — Recommended permanent model

**Two dashboards:**

1. **`SystemAdminDashboard`** — platform governance (users, roles, orgs, teams,
   workflow definitions/versions, designer validation failures, queue/worker/Redis
   health, failed jobs, audit activity, security events, settings, app health,
   global metrics). **Dedicated is correct** — it represents configuration and
   monitoring of the platform, not workflow work, and its data sources
   (governance tables, health probes) are unrelated to the engine-request queue.

2. **`MyWorkDashboard`** — one shared dashboard for **every** workflow user,
   derived from the current user's authorization + workflow metadata, never from
   a hard-coded role name.

This is approved because the enabling primitives **already exist**:

- The dynamic actionable-work query is `myQueue` (`accessibleStageIds(EXECUTE)` +
  DataScope). No role codes.
- DataScope (`forUser`), `StagePermissionResolver`, active-role authorization, and
  claim rules are all role-agnostic services already in production.
- Admin surfaces are already capability-gated (`workflow_designer`,
  `screen_permissions` screen caps), so the admin/work boundary can be a
  capability, not a role code.
- The `is_system` protection guarantee is enforced (Part 9), so a
  capability-owning core admin cannot be deleted or stripped.

---

## Part 4 — System-Admin dashboard contract

**Access:** protect by a **stable platform capability**, not `role === system_admin`.

- Introduce/read a `system_dashboard.view` (and `workflow_governance.view` /
  `system_administration.manage` for sub-sections) capability, resolved from
  screen/role grants server-side.
- The frontend selects `SystemAdminDashboard` when the user holds
  `system_dashboard.view`; otherwise `MyWorkDashboard`. **No role-code branch.**
- Backend endpoints for admin data must independently authorize on the same
  capability (frontend visibility never grants access — principle 7).

**Non-removable core admin — GUARANTEED (verified):** `Role` uses
`ProtectsSystemRecords`. For `is_system = true` rows the model boot hooks throw on
**delete**, on **deactivation** (`is_active → false`), and on mutating **`code`**
or **`is_system`**. The seeded `system_admin` role and `system_administration`
org are `is_system = true`. So the core administrator role cannot be deleted,
disabled, or have its identity/recovery capabilities stripped. (Follow-up: ensure
the chosen `system_dashboard.*` capability is likewise pinned to the protected
admin role so it cannot be revoked into a lockout.)

---

## Part 5 — Dynamic Work dashboard contract

`MyWorkDashboard` answers **"what can this user view or act on now under the
published workflow + current authorization?"** — driven by stage permissions
(VIEW/EXECUTE), org/bank scope, team, active role, user grants, claim ownership,
assignment, workflow version, current stage, runtime status, final outcome, SLA
metadata, semantic role, history, available actions. **No `if (role === …)`.**

Sections (Level 1 fixed layout, backend fills/omits each):

1. **Actionable work** (primary) — same query contract as `myQueue`.
2. **Claimed / assignable** — claimed-by-me, available-to-claim, near-SLA, overdue
   (only where stages require claims; never others' claims without a monitoring
   capability).
3. **Tracking / view-only** — VIEW-but-not-EXECUTE records (created-by-me,
   my-bank, previously-handled, waiting-on-another-team). **Never counted as
   actionable.**
4. **Recent activity** — authorized-scope history only.
5. **SLA & alerts** — near-due, overdue, unclaimed, blocked, validation/document
   issues.
6. **Optional metrics** — capability-gated; clearly separated from actionable.

---

## Part 6 — API contract proposal

New endpoint, consistent with existing `/api/dashboard/stats` and
`/api/v1/engine-requests/my-queue`:

```http
GET /api/v1/dashboard/work
```

```jsonc
{
  "actionable": {
    "count": 6,
    "items": [ /* bounded preview, read-model resource shape */ ],
    "queue_url": "/workflows?queue=mine"
  },
  "claimed":  { "count": 2, "items": [] },
  "tracking": { "count": 14, "items": [], "queue_url": "/workflows?scope=all" },
  "sla":      { "near_due": 3, "overdue": 1 },
  "recent_activity": [],
  "metrics":  []      // capability-gated, may be empty
}
```

Rules: apply backend authorization + DataScope; **bounded** preview lists (reuse
`limit`, don't load full queues); avoid N+1 (reuse `myQueue`'s eager loads);
`count` and `items` derive from the **same** scoped query; stable
`queue_url`/query identifiers; multi-workflow / multi-version safe; only
authorized fields; distinguish actionable vs trackable vs analytical.

`SystemAdminDashboard` keeps its own endpoint(s) (governance/health), separate
from `/dashboard/work`.

---

## Part 7 — Shared actionable-query design

Extract the `myQueue` core into one service, e.g.
`App\Services\Workflow\UserActionableRequestQuery`:

```text
actionableStageIds(User): array          // = accessibleStageIds(user, EXECUTE)
actionableQuery(User, Request): Builder   // active() + forUser() + stage IN ids + filters
actionableCount(User, Request): int
actionablePreview(User, Request, limit): Collection
```

Single source for: **dashboard count, dashboard preview, `my-queue`, nav badge,
and any current-work notification count** (principle: all five equal by
construction). `myQueue`'s `UnionStagePaginator` path becomes a caller;
`committeeDirectorStats` (and every other role's actionable count) becomes a
caller; `useNavBadges` reads `actionable.count`. This deletes findings #2, #3, #4
of the inventory and structurally prevents another UI-FX-001.

Applies: DataScope, EXECUTE stage resolution, active workflow version (via
`current_stage_id` pinning), claim rules, org/team/role/user grants, runtime
status, eligibility.

---

## Part 8 — Workflow Designer integration

The dashboard derives behavior from designer **metadata**, never a UI builder:

- New role given EXECUTE on a stage → appears in that user's actionable work
  automatically (query is permission-driven).
- Moving EXECUTE between roles in a new version → responsible users' queues shift
  automatically (version pinning + stage permissions).
- New stage → appears with no frontend enum change; **labels from
  `workflow_stages.name`**, progress from the pinned graph + history, SLA widgets
  from stage metadata, results from `final_outcome`.
- **Do not hard-code the nine stages as a permanent frontend model.** (The
  `frontend` still has stage-code assumptions in status/label maps — Phase D / B4
  target.)

Designer influence stays **metadata only**: stages, permissions, names, semantic
roles, claim/SLA, transitions, field rules, final stages. No raw SQL / URLs /
component names / code (Level 2 guardrail).

---

## Part 9 — Security model (must hold)

Preserve: backend authorization as truth; bank/org scoping; stage-permission
resolution; **active-role-only** authorization (RBAC-001, already fixed);
hidden-field filtering; document visibility; claim rules; **workflow version
pinning**; auditability; bounded queries; no direct-ID bypass; no frontend-only
permission assumptions. **Count and preview use the same scope** — the dashboard
must never show a count that includes records the user cannot open. Test positive
and negative. The shared query (Part 7) is what makes count == preview == queue
provable rather than asserted.

---

## Part 10 — Test matrix

**Backend:** actionable count == `my-queue` count; actionable IDs ⊆ same queue;
Director sees FINAL (V2); Support sees SUPPORT on EXECUTE grant; SWIFT sees FX;
Bank Reviewer sees INTERNAL for the correct bank only; **new dynamic role gets
work after a stage-permission grant (no frontend change)**; removing EXECUTE
removes dashboard work immediately; VIEW-only → tracking not actionable;
cross-bank/org excluded; claimed follows ownership; no-active-role → no
role-derived work; system-admin dashboard capability-protected; unauthorized
users cannot read system metrics.

**Frontend:** dynamic sections render from API; empty/loading/error/403/404/429/
retry; nav badge == dashboard actionable count; actionable links open the matching
queue; **no role-specific condition needed for a new role**; RTL/a11y/keyboard/
responsive; legacy-dashboard compatibility during migration.

**E2E (per role + one new dynamic test role):** system admin, data entry, bank
reviewer, support, committee manager, SWIFT, FX confirmation, committee director,
**newly created dynamic role**.

The existing C3 tests (Director FINAL queue, dashboard==customs parity, RBAC-005
`/auth/me`) are reused as the parity oracle for the migration and **kept until
equivalent dynamic tests exist**.

---

## Part 11 — Migration plan (phased, if approved)

1. Inventory dashboard dependencies (this report).
2. Extract `UserActionableRequestQuery` from `myQueue`; point `myQueue` at it (no
   behavior change; parity test).
3. Add `GET /api/v1/dashboard/work` (actionable/claimed/tracking/sla/recent/metrics),
   backed by the shared query + capability-gated metrics.
4. Build `MyWorkDashboard.vue` (Level 1 fixed layout).
5. **Pilot one non-admin role** (recommend SWIFT or Support — single EXECUTE
   stage, simplest) through `MyWorkDashboard`.
6. Assert count == IDs == `my-queue` == nav badge parity for the pilot.
7. Migrate the remaining workflow roles.
8. Move Committee Director off the dedicated component **after** parity tests pass.
9. Remove role-based dashboard selection (`dashboard.vue` switch → capability:
   admin vs work).
10. Remove voting dashboard remnants (`useNavBadges` Director voting sum;
    `ExecutiveDashboard` voting UI).
11. Keep only `SystemAdminDashboard` + `MyWorkDashboard`.
12. Update `AGENTS.md` + architecture docs.

Each step ships with: dependencies, tests, rollback, acceptance criteria,
affected roles, API changes, frontend changes, compatibility impact.

---

## Part 12 — Treatment of existing dashboards

| Component | Disposition |
| --------- | ----------- |
| `CommitteeDirectorDashboard.vue` | **Transitional.** Valid evidence of the correct FINAL queue contract. Absorb into `MyWorkDashboard` after parity. **Do not remove until equivalent dynamic tests exist**; its FINAL-queue tests stay useful. |
| `ExecutiveDashboard.vue` | Absorb into `MyWorkDashboard`; **delete voting UI** (out of V1). |
| `DataEntry/BankReviewer/BankAdmin/Support/Swift` dashboards | Convert to thin cases of `MyWorkDashboard` (actionable + tracking + role-appropriate optional metrics), then delete the bespoke components. BankAdmin's charts become capability-gated optional metrics. |
| `CbyAdminDashboard.vue` | Becomes the basis of the dedicated `SystemAdminDashboard` (capability-gated), retained as true platform specialization. |
| Shared cards (`MetricCard`, `MetricGrid`, `DashboardSection`) | Keep — reused by `MyWorkDashboard`. |

---

## Part 13 — Voting & legacy code

- `useNavBadges` Director branch: drop `sessions_ready_to_close` /
  `sessions_with_tie` / `fx_confirmation_pending` sum → read `actionable.count`.
- `ExecutiveDashboard` voting UI + `VotingQueueItem` dashboard usage: remove.
- Backward-compat Director keys (`fx_confirmation_pending`,
  `customs_declaration_pending`) added in C3: remove once `MyWorkDashboard` +
  nav badge both read `actionable`.
- `EngineRequestReadModel` stage-code fallbacks: retire as B4 (semantic-role
  rename, deferred to Phase D) lands.

---

## Part 14 — Risks & trade-offs

- **Scope:** this is a larger refactor than a Phase D UX pass. Mitigate with the
  pilot-one-role migration + parity oracle; it can run incrementally behind the
  existing dashboards.
- **`bank_admin` analytics** (charts, monthly, financed totals) don't fit the
  actionable/tracking/SLA sections cleanly → they belong in **capability-gated
  optional metrics**, which must be designed so a bank admin's *analytics* is
  never presented as *pending work* (the general form of the UI-FX-001 mistake).
- **Level 2 widget catalog** is tempting but unnecessary now → classify as future;
  Level 1 (fixed layout, dynamic data) ships the value with far less risk and no
  designer-as-code-builder surface.
- **Semantic-role dependency:** the cleanest metadata-driven labels/progress want
  B4 done; the shared **actionable query does not** (it's stage-permission based),
  so Part 7 can proceed before B4.
- **Capability introduction:** `system_dashboard.view` is new; must be seeded,
  pinned to the protected admin role, and covered by negative tests to avoid a
  lockout or an unauthorized-admin path.

---

## Part 15 — Items requiring business approval

1. **Approve the two-dashboard model** (one admin + one dynamic work dashboard).
2. Approve introducing platform capabilities (`system_dashboard.view`, etc.) as
   the admin/work boundary instead of `role === system_admin`.
3. Confirm `bank_admin` (and any) analytics are **optional monitoring metrics**,
   never the actionable count.
4. Confirm the migration runs **as its own phase** (recommend before or folded
   into Phase D UX), given it is broader than a styling pass.
5. Confirm removal of Executive-voting dashboard remnants is in scope now.

---

## Recommendation

**Approve: one dedicated platform-administration dashboard + one
permission-and-workflow-driven dynamic dashboard for all workflow users.** The
codebase already contains the dynamic actionable-work query (`myQueue`), the
role-agnostic authorization services (DataScope, StagePermissionResolver,
active-role model), capability-gated admin surfaces, and an enforced non-removable
system-admin guarantee (`ProtectsSystemRecords`). The current per-role dashboard
components, per-role stats methods, and per-role nav badges are a redundant
role-coupled layer beside that query — the direct cause of UI-FX-001 and of the
still-live nav-badge inconsistency. Consolidating onto a shared
`UserActionableRequestQuery` and a single `MyWorkDashboard` (Level 1) makes the
dashboard scale with dynamic roles/stages/workflows by configuration, not by new
Vue components, while keeping backend authorization the source of truth.

`CommitteeDirectorDashboard.vue` remains valid transitional evidence of the
correct Director contract and its tests are retained until dynamic-dashboard tests
replace them.
