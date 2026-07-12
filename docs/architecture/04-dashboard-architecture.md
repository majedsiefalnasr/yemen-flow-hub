# Dashboard Architecture

**Verified:** 2026-07-12, against `frontend/app/` and `backend/app/`
directly. Promotes `docs/audit-functional/14-dashboard-architecture-decision.md`
(a dated, past-tense decision record proposing this model) into a
present-tense canonical reference. **The two-family component
structure is shipped** — `MyWorkDashboard.vue` plus the two analytics
dashboards, selected by a capability check rather than a per-role
component branch. **The proposed capability-only end-to-end selection
model is not fully shipped** — fixed-role constraints remain in route
admission (`ROUTE_ROLE_MAP['/dashboard']`) and backend analytics
dispatch (`hasRoleCode()` checks in `DashboardStatsService::stats()`),
documented in full below.

For the permission mechanics dashboards route on, see
[`03-permission-model.md`](03-permission-model.md). For the request-state
fields dashboards render, see
[`05-request-state-model.md`](05-request-state-model.md).

---

## Two dashboard families, selected by capability — with remaining fixed-role constraints

Dashboards are **two families**, and _which component renders_ inside
the dashboard page is chosen by capability, not a `role === X` branch.
**This is not capability-only end to end** — route admission to the
dashboard page itself, and the backend's analytics data dispatch, both
still gate on a fixed role code alongside the capability. Treat this as
capability-led frontend family selection with remaining fixed-role
constraints in route admission and analytics backend dispatch — not as
fully capability-only behavior. The gap between the two is architecture
drift from the design this document's source decision record proposed,
not an intentional hybrid design.

1. **Operational family — `MyWorkDashboard.vue`.** The dashboard for
   every workflow-executor user whose role is listed in
   `ROUTE_ROLE_MAP['/dashboard']` (see below) and who holds neither
   analytics capability.
2. **Analytics/governance family** — `CbyAdminDashboard.vue` (imported
   and aliased `SystemAdminDashboard` — there is no separately-named
   `SystemAdminDashboard.vue` file) and `BankAdminDashboard.vue`.

### Frontend component-selection order (capability-based)

Both `frontend/app/pages/dashboard.vue` and `frontend/app/pages/index.vue`
implement the **same routing logic independently** (not via a shared
composable — a real duplication, not a documentation gap):

```ts
const dashboardFamily = computed<"system" | "bank" | "work">(() => {
  if (can("system_dashboard", "VIEW")) return "system";
  if (can("bank_analytics", "VIEW")) return "bank";
  return "work";
});
```

```vue
<SystemAdminDashboard v-if="dashboardFamily === 'system'" />
<BankAdminDashboard v-else-if="dashboardFamily === 'bank'" />
<MyWorkDashboard v-else />
```

Order confirmed: `system_dashboard` VIEW capability checked first, then
`bank_analytics` VIEW, then fallthrough to `MyWorkDashboard`. `can()`
comes from `useScreenPermissions()`, reading a client-side capability map
(`auth.screenPermissions`) populated at login — not a live per-check API
call. **This is the only part of dashboard selection that is genuinely
capability-only** — which of the 3 components renders, once the user is
already admitted to the `/dashboard` route.

### Route admission is a fixed-role gate, not capability-based

Before component selection ever runs, both pages declare:

```ts
definePageMeta({
  middleware: ["auth", "role"],
  requiredRoles: ROUTE_ROLE_MAP["/dashboard"],
});
```

`frontend/app/middleware/role.ts` checks
`requiredRoles.includes(auth.user.role)` and denies the route entirely if
the user's `UserRole` is not in that list.
`ROUTE_ROLE_MAP['/dashboard']` currently lists all 8 canonical roles
(confirmed via `frontend/app/constants/role-surfaces.ts`'s `nav.dashboard`
surface entry, present in every role's block), so in practice no
currently-seeded role is denied today — but this is a **fixed role-code
list**, not a capability check. A future dynamic role that isn't added to
this list would be denied the `/dashboard` route entirely, before
component selection could ever route it to `MyWorkDashboard`. **Do not
claim a future dynamic executor role reaches `MyWorkDashboard`
automatically with no code change** — `ROUTE_ROLE_MAP['/dashboard']` is
that code change, and it is not derived from capabilities.

### Backend analytics gate requires both a fixed role code and a capability

`DashboardStatsService::stats()` gates both analytics branches on **role
code AND capability**, not capability alone:

```php
$analyticsGate = fn (string $roleCode, string $screen): bool => $user->hasRoleCode($roleCode)
    && $this->permissions->userHasCapability($user, $screen, 'VIEW');

return match (true) {
    $analyticsGate(RoleCodes::SYSTEM_ADMIN, 'system_dashboard') => $this->cbyadminStats($user, $scope),
    $analyticsGate(RoleCodes::BANK_ADMIN, 'bank_analytics') => $this->bankAdminStats($user, $scope),
    // ...legacy workflow-role branches, see the "Legacy DashboardStatsService branches" note below
};
```

`hasRoleCode(RoleCodes::SYSTEM_ADMIN)` and `hasRoleCode(RoleCodes::BANK_ADMIN)`
are **fixed role-code checks**, hardcoded in this `match`, not resolved
from the capability system. A user who holds the `system_dashboard`
capability but not the `SYSTEM_ADMIN` role code does not reach
`cbyadminStats()` — they fall through to whichever legacy branch their
actual role matches (see "Legacy `DashboardStatsService` branches" below
for what that means in practice). This is the precise mismatch the
frontend/backend split creates: the frontend's `can()` check has no
role-code condition, so it can route a user to an analytics _component_
based on capability alone, while the backend's `match` can simultaneously
route that same user's _data_ to a legacy branch based on role code
alone, if the two ever diverge (see the reassignment scenario below).

### Why component selection is capability-based, with the caveats above

A per-role dashboard _component_ requires a code change every time a new
role needs a different UI. Component selection avoids that: any user
whose capability check passes reaches the matching component without a
frontend code change. This does not extend to route admission (fixed
role list, above) or backend analytics dispatch (fixed role code +
capability, above) — both of those still require a code change to
onboard a genuinely new dynamic role. Confirmed via source: zero
`role === 'X'` branches exist in either routing page's **component
selection** logic specifically (`grep -rn "role ===" frontend/app/pages`
matching `dashboard` returns nothing in that block), and exactly 3
mountable dashboard components exist in
`frontend/app/components/dashboard/` — no 4th, 5th, 6th, 7th, or 8th
per-role file. The fixed-role constraints documented above sit alongside
this, not instead of it.

---

## The shared actionable-work invariant

The dashboard `actionable` count, dashboard preview record IDs, the
`/workflows` nav badge, and `/my-queue` all derive from **one contract** —
`App\Services\Workflow\UserActionableRequestQuery` — and must stay equal
**by record ID**, not merely by count. Confirmed via three independent
call sites, all resolving to the same service:

- **`/my-queue`** (`GET /api/v1/engine-requests/my-queue`) —
  `EngineRequestController::myQueue()` calls
  `$this->actionableQuery->paginate($request->user(), $request)`.
- **Dashboard `actionable` section** — `DashboardWorkController::work()`
  calls the same constructor-injected `UserActionableRequestQuery`
  instance's `actionableCount()`/`actionablePreview()`.
- **`/workflows` nav badge** — `frontend/app/composables/useNavBadges.ts`'s
  `buildOperationalNavBadges()` does not call the backend itself; it
  reads `dashboardWorkStore.work.actionable.count` — the exact same
  in-memory field the dashboard's own KPI card renders, both populated
  by one `GET /api/dashboard/work` fetch. `AppSidebar.vue` passes this
  value in directly:

  ```ts
  const navBadgesByRoute = computed(() =>
    buildOperationalNavBadges({
      actionableCount: dashboardWorkStore.work?.actionable.count ?? null,
      unreadCount: notificationsStore.unreadCount,
    }),
  );
  ```

All three resolve through `UserActionableRequestQuery` by direct code
reference — this is provable from source, not asserted.

---

## The `GET /api/dashboard/work` contract

Route: `GET /api/dashboard/work` (unprefixed — no `/v1`, despite the
controller class living in the `Api\V1` namespace; see
[`api-reference.md`](../api-reference.md) for the general route-naming
note). Controller: `App\Http\Controllers\Api\V1\DashboardWorkController::work()`.

Exact, exhaustive response shape (confirmed no other top-level keys
exist):

```php
[
    'actionable' => [
        'count' => /* UserActionableRequestQuery::actionableCount() */,
        'items' => /* bounded preview, PREVIEW_LIMIT = 10 */,
        'queue_url' => '/workflows?queue=mine',
    ],
    'claimed' => [
        'count' => /* ...claimedCount() */,
        'items' => /* bounded preview */,
        // no queue_url — only actionable and tracking carry one
    ],
    'tracking' => [
        'count' => /* ...trackingCount() */,
        'items' => /* bounded preview */,
        'queue_url' => '/workflows?scope=all',
    ],
    'sla' => /* ...slaCounts() — near_due, overdue */,
    'recent_activity' => [],
    'metrics' => [],
]
```

`recent_activity` and `metrics` are hardcoded empty arrays today, with an
in-source comment confirming this is intentional: "Level-1 sections the
backend fills as data/capabilities become available; empty is a valid
'nothing to show' state, not an error." `MyWorkDashboard.vue` fetches
both keys but renders neither — no template markup references
`work.recent_activity` or `work.metrics`. Treat both as reserved,
currently-inert keys, not as evidence of a bug.

### Sections `MyWorkDashboard.vue` actually renders

1. Action banner (amber, `role="alert"`) — shown only when
   `actionable.count > 0`.
2. A 4-card metric grid: actionable count, claimed count, SLA near-due,
   SLA overdue.
3. Actionable work queue — a table of `actionable.items`, with an empty
   state when zero.
4. Tracking queue — a table, rendered only `v-if="tracking.count > 0"`.

No `recent_activity` or `metrics` section is rendered (see above).

---

## Legacy `DashboardStatsService` branches (analytics-only endpoint, mixed dispatch)

`GET /api/dashboard/stats` (`DashboardStatsService::stats()`) is the
analytics-family data source — both `CbyAdminDashboard.vue` and
`BankAdminDashboard.vue` call it via the same `useDashboardStore()`
Pinia store, differentiated only by which fields the backend populates
for that user's `match(true)` branch. This service's dispatch is **not**
analytics-only: alongside the two capability-gated analytics branches
above, it still contains 6 legacy workflow-role branches
(`dataEntryStats`, `bankReviewerStats`, `supportCommitteeStats`,
`swiftOfficerStats`, `executiveMemberStats`, `committeeDirectorStats`).

**These legacy branches are not structurally unreachable — they are
unreached under default seeded capability assignments, which is a
weaker guarantee.** Under the default seeding, `EXECUTIVE_MEMBER`/
`COMMITTEE_DIRECTOR` and the other workflow-executor roles hold neither
`system_dashboard` nor `bank_analytics` capability, so
`dashboard.vue`/`index.vue` route them to `MyWorkDashboard`, which calls
only `GET /api/dashboard/work` — nothing in `frontend/app` calls
`GET /api/dashboard/stats` for these roles under that default
configuration.

**But screen capabilities are administratively reassignable**
(`PUT /api/v1/roles/{role}/screen-permissions`), while the backend's
analytics gate is fixed to specific role codes (`SYSTEM_ADMIN`,
`BANK_ADMIN` — see above), not derived from the capability grant. This
creates a real possible mismatch: if an administrator grants the
`system_dashboard` or `bank_analytics` capability to a role other than
`SYSTEM_ADMIN`/`BANK_ADMIN` (e.g. to `COMMITTEE_DIRECTOR`), the
**frontend** would route that user's component selection to an analytics
dashboard (`can()` only checks the capability), while the **backend**
would still fall through `DashboardStatsService::stats()`'s `match` to
that role's legacy branch (`committeeDirectorStats()`, since
`hasRoleCode(SYSTEM_ADMIN)`/`hasRoleCode(BANK_ADMIN)` would both be
false) — an analytics component rendering a legacy-branch payload it
wasn't designed for.

**Do not call the legacy payload universally dead.** Whether this
mismatch is currently exploitable depends on the live database's
`screen_permissions` grants, which this document cannot verify by static
analysis — settling it requires a live database query, runtime
configuration inspection, or a targeted regression test that attempts
the reassignment and checks the resulting dashboard payload. Under the
default seeded grants, the legacy branches' zeroed voting fields
(`waiting_for_voting_open`, `active_voting_sessions`, `voting_queue`,
etc. — see below) are unreached payload — but "unreached under defaults"
and "structurally unreachable" are different claims, and this document
only supports the former. See
[`engine/extension-guide.md`](../engine/extension-guide.md) for the rule
this implies regardless: new operational metrics must use
`DashboardWorkController` exclusively; new analytics metrics may extend
only the two capability-gated branches, never the 6 legacy ones.

---

## Executive Voting dashboards are out of V1

No voting-session dashboard UI is mounted anywhere. Confirmed:

- `DashboardStatsService`'s legacy `executiveVotingStats()` still returns
  zeroed voting keys (`waiting_for_voting_open`, `active_voting_sessions`,
  `decisions_approved`, `decisions_rejected`, `finalized_decisions`,
  `pending_my_vote`, `voting_queue: []`), consumed only by the legacy
  `executiveMemberStats()`/`committeeDirectorStats()` branches above —
  **unreached under default seeded capability assignments**, and
  **potentially reachable after an administrative capability
  reassignment** (see "Legacy `DashboardStatsService` branches" above:
  frontend capability-based component selection and the backend's fixed
  role-code dispatch can diverge).
- No live dashboard component renders these fields. The one voting-token
  reference found in the dashboard component tree —
  `DashboardKpiCard.vue`'s deprecated `variant="indigo"` → `var(--voting)`
  color mapping — is a **generic design-token fallback for a deprecated
  prop**, not voting functionality, and `DashboardKpiCard.vue` has **zero
  callers anywhere in `frontend/app`** (confirmed via
  `grep -rn "DashboardKpiCard" frontend/app`) — it is not imported by
  `MyWorkDashboard.vue`, `CbyAdminDashboard.vue`, or
  `BankAdminDashboard.vue`, all three of which use `MetricCard.vue`
  instead.
- The similar-looking `tone="voting"` usage on the Reports page
  (`frontend/app/pages/reports/index.vue`) is the same design-token reuse
  — it colors an "average processing time" KPI card, unrelated to voting,
  and Reports is not a dashboard.

Do not reintroduce a voting-queue widget, voting KPI card, or
voting-session section to any dashboard component. See
[`api-reference.md`](../api-reference.md)'s "Executive Voting (out of V1
— no live routes)" section for the full backend/frontend cleanup-debt
inventory.

---

## Prohibited patterns

Per [`development-guide.md`](../development-guide.md)'s "Never" list and
verified against source:

- **No new per-role dashboard component.** Exactly 3 mountable dashboard
  components exist (`MyWorkDashboard`, `CbyAdminDashboard`/aliased
  `SystemAdminDashboard`, `BankAdminDashboard`); adding a 4th
  role-specific one reintroduces the exact structural defect this
  document's source decision record identified (three surfaces
  independently deciding "what is this user's pending work," able to
  disagree with each other and with `/my-queue`).
- **No new per-role dashboard component-selection branch.** The
  _component-selection_ block (`dashboardFamily` computed in both routing
  pages) is capability-based — `can('system_dashboard'|'bank_analytics', 'VIEW')`
  exclusively, no `role === 'X'` conditional. **This does not mean the
  entire routing path is role-free.** Fixed-role route _admission_
  already exists (`requiredRoles: ROUTE_ROLE_MAP['/dashboard']`, checked
  by `middleware/role.ts`) and is documented above as architecture drift
  from the source decision record's capability-only proposal, not as an
  additional pattern to avoid introducing — it already exists. Do not
  add a _new_ role-name branch to component selection; do not describe
  the existing route-admission gate as if it weren't there.
- **No bespoke pending-work query.** Any new "how much work does this
  user have" count must go through `UserActionableRequestQuery`, not a
  new hand-rolled query — this is what keeps dashboard count, dashboard
  preview, nav badge, and `/my-queue` equal by record ID rather than
  merely by coincidence of count.

---

## Level 1 today, Level 2 is a future enhancement — not shipped

Current dashboards are **Level 1**: fixed Vue template, dynamic data.
Confirmed no metadata-driven widget catalog exists — `grep -rniE
"widget|MetricCatalog" backend/app --include="*.php"` returns exactly one
hit, and it is a comment, not code (`ReportController.php:390`). No
`Widget` model, no `MetricCatalog` service or table. `MyWorkDashboard.vue`'s
template is a fixed sequence of hardcoded `Card`/`MetricGrid`/`Table`
blocks — no `v-for` over a widget-definitions array, no dynamic
`<component :is="widget.type">` render loop.

A **Level 2** metadata-driven widget catalog — where a designer could
configure dashboard widgets as data rather than the current fixed
sections — is a documented future enhancement, not present in code today.
Do not build against a Level 2 API that doesn't exist; if a story
requires configurable widgets, that is new scope requiring its own
design, not an extension of the current Level 1 dashboards.
