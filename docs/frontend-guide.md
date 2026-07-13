# Frontend Guide

**Verified:** 2026-07-13, against `frontend/package.json`,
`frontend/app/pages/`, `frontend/app/components/dashboard/`,
`frontend/app/middleware/{role,screen}.ts`,
`frontend/app/constants/{workflow,role-surfaces}.ts`,
`frontend/app/composables/{useScreenPermissions,useEngineClaim}.ts`,
`frontend/app/stores/auth.store.ts`, `frontend/app/types/models.ts`,
`frontend/app/pages/workflows/instances/[id].vue`, and
`frontend/CLAUDE.md` / `frontend/PRODUCT.md` / `frontend/DESIGN.md` /
`frontend/SHADCN.md` directly — not carried over from the legacy
`docs/04-frontend-guide.md`, which predates the dynamic workflow engine
and describes a fixed per-role static-status UX model (18-value status
vocabulary, dedicated `/voting` routes, per-role Pinia stores) that no
longer matches the shipped application. Re-checked and corrected
2026-07-13 after an independent review found 6 issue groups (stack
version, route inventory completeness, the route-admission mechanism,
request-state type drift, claim-heartbeat behavior, and the plan
record) — see the Step 4B accuracy-correction record in
[`archive/audit-functional/22-documentation-consolidation-plan.md`](archive/audit-functional/22-documentation-consolidation-plan.md).
Extended 2026-07-13 (Step 5) with three generic authoring templates
(per-surface operational posture, forbidden-actions, cross-role
handoff) extracted from `docs/archive/user-view/*.md` and genericized
against the current architecture — that source material now lives at
`docs/archive/user-view/` (Step 10), preserved verbatim and still not
a live source. Corrected
2026-07-13 after a further review of the Step 5 additions (source
enum count, the four-concept model listing, and `semantic_role`
nullability) — see the Step 5 accuracy-correction record in
[`archive/audit-functional/22-documentation-consolidation-plan.md`](archive/audit-functional/22-documentation-consolidation-plan.md).

This document covers frontend-specific conventions that sit above the
four mandatory context files, which remain the primary authority for
their own domains and are not duplicated here:

- [`../frontend/PRODUCT.md`](../frontend/PRODUCT.md) — product identity,
  the 8 roles and their daily tasks, operational posture, brand tone.
- [`../frontend/DESIGN.md`](../frontend/DESIGN.md) — color token rules,
  RTL border rule, skeleton/error/banner patterns.
- [`../frontend/SHADCN.md`](../frontend/SHADCN.md) — the full shadcn-vue
  component reference: decision table, recipes, import paths, 10
  absolute rules.
- [`../frontend/CLAUDE.md`](../frontend/CLAUDE.md) — the frontend AI
  instruction file (loads the three above).

For the four-concept request-state model
(`runtime_status`/`current_stage`/`semantic_role`/`final_outcome`), see
[`architecture/05-request-state-model.md`](architecture/05-request-state-model.md)
— not duplicated here. For the workflow engine itself, see
[`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md).
For the dashboard-family model in full, see
[`architecture/04-dashboard-architecture.md`](architecture/04-dashboard-architecture.md).
For API contracts, see [`api-reference.md`](api-reference.md), including
its Executive Voting section for the verified backend/frontend
cleanup-debt inventory.

---

## Stack

Nuxt 4, Vue 3.5 (`frontend/package.json` pins `"vue": "^3.5.13"`),
TypeScript, Tailwind CSS v4, shadcn-vue, Pinia, VueUse, VeeValidate, Zod.
RTL-first, Arabic-first (IBM Plex Sans Arabic + Inter). Desktop-first
with responsive degradation at ≤ 600px.

---

## Actual routes (verified against `frontend/app/pages/`)

`frontend/app/pages/` contains **35 `.vue` source files**, resolving to
**34 distinct URL paths** — one path, `/settings`, is served by two
files: `settings.vue` (the parent route, a layout/tab shell) and
`settings/index.vue` (its default child). This is a normal Nuxt
nested-route pattern, not a duplicate page; `settings/bank.vue`,
`settings/system.vue`, and `settings/user.vue` are the other tab
children under the same `settings.vue` parent.

```
/login
/dashboard, /                      (same dashboard-family component tree)
/workflows
/workflows/new
/workflows/instances/[id]
/customs                            (legacy URL alias — content is FX confirmation, see below)
/merchants
/audit
/notifications
/reports
/settings (settings.vue + settings/index.vue), /settings/user, /settings/bank, /settings/system
/staff, /bank/users
/admin/staff, /admin/banks, /admin/orgs, /admin/settings, /admin/health,
/admin/roles, /admin/workflows, /admin/teams, /admin/reference-data,
/admin/screen-permissions, /admin/email-templates, /admin/email-templates/[type]
/mfa-setup, /reset-password, /change-temporary-password
/forbidden, /unauthorized
```

This list is the full route inventory as verified against
`frontend/app/pages/` at the date above — re-run `find frontend/app/pages
-name "*.vue"` before trusting it as current if this document is read
much later, since new pages are added without a corresponding doc
update by default.

The legacy `/requests`, `/requests/new`, `/requests/[id]`, `/voting`,
`/voting/[id]`, top-level `/users`, and top-level `/banks` routes do not
exist — do not reintroduce them or reference them as current.

### `/customs` is a legacy URL alias, not customs-facing content

`/customs` (`frontend/app/pages/customs/index.vue`) keeps its legacy URL
path but its content is entirely external FX confirmation
(`تأكيد المصارفة الخارجية`) — the page's own banner states "this page
only keeps the old URL; every action here concerns external FX
confirmation." Do not add customs-declaration terminology anywhere in
this flow; align new copy to "FX confirmation," matching
[`AGENTS.md`](../AGENTS.md)'s canonical terminology rule.

---

## Route admission: a mixed model — live capability checks on some routes, static role arrays on most

Route admission is **not** one mechanism. Two separate middlewares
guard different routes, and neither one is a live-capability check
everywhere:

- **`screen` middleware** (`frontend/app/middleware/screen.ts`) reads
  `to.meta.requiredScreen`, skips enforcement entirely during server
  execution (`if (import.meta.server) return`), and on the client calls
  `useScreenPermissions().can(screen, capability)`, which reads
  `auth.screenPermissions` on the Pinia auth store. That field is
  populated by `auth.store.ts`'s `fetchUser()`/`extendSession()`
  actions, which call `$fetch('/api/auth/me', ...)` — confirmed against
  `backend/routes/api.php`'s `Route::get('me', [AuthController::class,
'me'])` under the `api/auth/` prefix group — and assign
  `store.screenPermissions = data.screen_permissions ?? {}` from that
  response. So the data is **backend-provided, client-hydrated**: the
  backend's `/api/auth/me` payload is the source, but the store
  populates client-side, not during SSR — which is exactly why the
  middleware defers its own check to the client. This **is** a live
  capability check (reads current session data on every navigation),
  just not a server-rendered one. Verified in use on `/workflows`,
  `/workflows/new`, `/workflows/instances/[id]`, and several governance
  pages: `/admin/orgs`, `/admin/reference-data`, `/admin/roles`,
  `/admin/screen-permissions`, `/admin/staff`, `/admin/teams`,
  `/admin/workflows`, `/bank/users`.
- **`role` middleware** (`frontend/app/middleware/role.ts`) reads
  `to.meta.requiredRoles` (falling back to
  `resolveRouteRoles(path)` → `ROUTE_ROLE_MAP[path]` from
  `frontend/app/constants/workflow.ts`) and redirects to `/forbidden`
  if the signed-in user's role isn't in that array. **This is a static
  role check, not a live capability check**, regardless of how the
  array was produced.

Within `ROUTE_ROLE_MAP`, the arrays come from two different sources,
and **neither is a live capability read**:

- Most entries call `rolesForSurface('nav.xxx')`
  (`frontend/app/constants/role-surfaces.ts`), which filters the
  hardcoded `ROLE_SURFACE_MATRIX` — a static, compile-time
  `Record<UserRole, …>` — by which roles list that surface in their
  `allowed` array. **This does not read `screen_permissions` and does
  not call `can()`.** It is a static role-to-surface lookup table, not
  live capability data — do not call it "capability-derived."
- Exactly **4 entries are direct literal role arrays**, with no surface
  indirection at all: `/admin` and `/admin/health`
  (`[UserRole.CBY_ADMIN]`), `/settings/system`
  (`[UserRole.CBY_ADMIN]`), `/settings/bank` (`[UserRole.BANK_ADMIN]`).

Some pages skip both `screen` and `role` and use only `auth` (or
`guest` for unauthenticated pages). And several pages hardcode a role
array directly in `definePageMeta` rather than going through
`ROUTE_ROLE_MAP` at all — verified: `/admin/email-templates` and
`/admin/email-templates/[type]` (`[UserRole.CBY_ADMIN]`), `/staff`
(`[UserRole.BANK_ADMIN]`), and `/reports` (a local `REPORTING_ROLES`
array literal defined in the page itself).

In short: a handful of routes (mostly workflow and governance pages) are
gated by a live capability check; most other routes are gated by a
static role whitelist, whether that whitelist comes from
`ROUTE_ROLE_MAP`'s `rolesForSurface()` calls, `ROUTE_ROLE_MAP`'s literal
arrays, or a page-local literal array outside `ROUTE_ROLE_MAP`
entirely. Do not describe route admission as "capability-derived" as a
general rule — that only holds for the routes on `screen` middleware.

---

## Dashboard: a static role gate at the route, then capability-led component choice

The shipped dashboard is **not** capability-gated end to end. It is a
static role whitelist at route admission, followed by a live capability
check that only chooses which component renders once the route has
already been granted:

1. **Route admission — static role whitelist.** Both `dashboard.vue`
   and `index.vue` carry `definePageMeta({ middleware: ['auth', 'role'],
requiredRoles: ROUTE_ROLE_MAP['/dashboard'] })`.
   `ROUTE_ROLE_MAP['/dashboard']` is `rolesForSurface('nav.dashboard')`
   — per the section above, this filters the hardcoded
   `ROLE_SURFACE_MATRIX`, not live `screen_permissions`. A user whose
   role isn't listed in the matrix for `nav.dashboard` never reaches the
   page at all, regardless of what capabilities they hold.
2. **Component selection — live capability check**, verified directly,
   only runs for users who already passed step 1:

   ```ts
   const dashboardFamily = computed<"system" | "bank" | "work">(() => {
     if (can("system_dashboard", "VIEW")) return "system";
     if (can("org_analytics", "VIEW")) return "bank";
     return "work";
   });
   ```

   `SystemAdminDashboard` (`CbyAdminDashboard.vue`) →
   `BankAdminDashboard.vue` → `MyWorkDashboard.vue` (fallthrough) —
   these three components are selected purely by the `can()` capability
   check, never by `role === X`. Any future dynamic executor role that
   already passes the route's static role whitelist and holds neither
   analytics capability falls through to `MyWorkDashboard` with no
   frontend code change to the component-selection logic itself — but a
   wholly new role still needs a `ROLE_SURFACE_MATRIX` entry for
   `nav.dashboard` before it can reach the route in the first place.

Do not describe this as "two capability-gated layers" or
"capability end-to-end" — it is a static role gate at the route,
then a capability-led choice inside it.

For the full two-family model (why `BankAdminDashboard` has no
actionable queue, the shared actionable-work invariant across
`/my-queue`/nav badge/dashboard preview), see
[`architecture/04-dashboard-architecture.md`](architecture/04-dashboard-architecture.md)
— not duplicated here.

---

## Request state: the canonical rule is the required direction — the shipped frontend type has not fully caught up

There is no frontend `RequestStatus` enum in the current codebase
(confirmed: `frontend/app/types/enums.ts` has no such type) — the
18-value static enum is genuinely gone. But do not read that as "every
consumer already reads the four canonical fields." The frontend
`EngineRequest` type (`frontend/app/types/models.ts`) has real drift
against the canonical model documented in
[`architecture/05-request-state-model.md`](architecture/05-request-state-model.md),
verified directly:

- `export type EngineRequestStatus = 'ACTIVE' | 'CLOSED' | 'REJECTED'`
  — 3 cases, not the backend's 5 (`ACTIVE`/`CLOSED`/`REJECTED`/
  `CANCELLED`/`ABANDONED`).
- `EngineRequest.status: EngineRequestStatus` — the field is still
  named `status`, not `runtime_status`.
- `EngineRequest` has **no `final_outcome` field at all.**
- `EngineRequest.current_stage` has no `semantic_role` field — its type
  only carries `id`, `code`, `name`, `is_initial`, `is_final`,
  `sla_duration_minutes`, `requires_claim`.

Existing consumers read `.status` directly, matching the type as it
stands today — e.g. `BankAdminDashboard.vue`:
`RUNTIME_STATUS_BADGE[row.original.status]`. **`BankAdminDashboard.vue`
is a reference only for how to map a runtime-status value to a
severity color token** (the `RUNTIME_STATUS_BADGE` map pattern cited by
[`frontend/DESIGN.md`](../frontend/DESIGN.md) §7); it is not evidence
that the frontend already consumes the complete four-field model — it
consumes the one field (`status`) the current type exposes.

**The canonical rule remains the required direction for new and
changed code:** do not reintroduce a static frontend status enum, and
prefer reading `runtime_status`/`current_stage.semantic_role`/
`final_outcome` once the type/API surface supports it. But state this
as a direction, not as already-true behavior — the type gap above is
real, current, unaddressed drift, not a hypothetical risk.

---

## Executive Voting: out of V1, with verified cleanup-debt residue — not active UI

There is no `/voting` or `/voting/[id]` route, no voting composable, and
no voting store. A repo-wide search for active voting UI found only
inert residue, verified individually, none of it rendered voting
functionality:

- `--voting` (a CSS custom property / design token) is reused generically
  as an "indigo" tone for several unrelated surfaces —
  `ActionRequiredStrip.vue`, `ActiveReviewBanner.vue`,
  `DashboardKpiCard.vue`, `MetricCard.vue`'s `voting` tone option. This
  is a token name, not voting feature UI; do not treat its presence as
  evidence of live voting functionality.
- `audit.vue`'s `ACTION_LABELS` map still contains
  `VOTE_SUBMITTED`/`VOTING_SESSION_OPENED`/`VOTING_SESSION_CLOSED` —
  dead label strings for historical audit-log entries, not a live
  voting feature.
- `useReports.ts` declares an optional `voting_analytics?` field on its
  report-response type; nothing renders it (verified: no
  `voting_analytics` reference exists anywhere in `app/pages` or
  `app/components`).
- `useAdminSettings.ts` declares `voting_session_timeout` and
  `secret_voting` setting fields.

None of this is active Executive Voting UI. For the complete, previously
verified backend/frontend cleanup-debt inventory (dead enum cases, dead
frontend types, unreachable notification templates), see
[`api-reference.md`](api-reference.md)'s "Executive Voting (out of V1 —
no live routes)" section — not re-inventoried here.

---

## Design consistency (from `frontend/CLAUDE.md`, restated for emphasis)

The UI prototype phase is complete. The shipped `frontend/` code is the
visual source of truth — there is no separate prototype to clone from.
Every new or changed surface must cite the existing shipped pattern it
follows, use only tokens from `DESIGN.md` / `frontend/DESIGN.md` (no raw
Tailwind color scales, no hardcoded hex), and compose existing
`frontend/SHADCN.md` components rather than raw HTML or bespoke
primitives. See `frontend/SHADCN.md`'s "Absolute Rules for AI" for the
non-negotiable list (no raw `<button>`/`<table>`/`<select>`, no
`animate-pulse` divs, `AlertDialog` for destructive confirmations, etc.).

### Operational density composition

All roles share the same component library; role surfaces compose
differently by operational posture, not by forking components:

| Posture                        | Roles                                                          | Density |
| ------------------------------ | -------------------------------------------------------------- | ------- |
| Distraction-free / low-density | SWIFT_OFFICER                                                  | Low     |
| Operational queue density      | DATA_ENTRY, BANK_REVIEWER, SUPPORT_COMMITTEE, EXECUTIVE_MEMBER | Medium  |
| Governance / lifecycle density | COMMITTEE_DIRECTOR, CBY_ADMIN, BANK_ADMIN                      | High    |

Density is a property of the role's spec, not the component library —
the same `KpiCard`/`ActionRequiredStrip`/workflow-progress components
serve all three postures by composition.

### Per-surface operational-posture template

Beyond the density-tier table above, use this template when writing or
reviewing the UX spec for a new role, screen, or capability-gated
surface — it is extracted and generalized from the density/posture
tables that recur across `docs/archive/user-view/*.md` (deprecated
historical material; the shape below is a reusable authoring pattern,
not preserved role-specific content):

| Aspect                     | What to fill in                                                                                                                                                                                                                         |
| -------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Work mode                  | Operational / claim-based / administrative / governance — pick the closest fit, don't invent a new category per role                                                                                                                    |
| Primary surface            | The one screen this role/capability opens most often                                                                                                                                                                                    |
| Secondary/tertiary surface | Any other screens the role touches regularly, in priority order                                                                                                                                                                         |
| State language             | Which of the four canonical concepts (`runtime_status`, `current_stage`, `current_stage.semantic_role`, `final_outcome`) the surface exposes, and at what level of detail — never a business-status label list built from a static enum |
| Visual density             | Which tier from the table above (Low/Medium/High), and why                                                                                                                                                                              |
| Decision/feedback tone     | What kind of action this surface drives — informational tracking, claim-gated decision, destructive confirmation, administrative CRUD                                                                                                   |

Fill in "State language" by reading the four canonical concepts —
`runtime_status`, `current_stage`, `current_stage.semantic_role`, and
`final_outcome` — for the surface in question. A surface may display
only the subset relevant to its audience (e.g. a simplified label for
an intake user), but any **state presentation or classification
decision** (which tab a request lands in, whether a request is
terminal) must be driven by these four concepts, never a static enum.
**Action availability is a separate concern**, driven by
server-derived `can_execute`, the Designer-defined outgoing
transitions from the current stage, claim state (`requires_claim`/
`claimRequiredButNotHeld`, see "Claim heartbeat," below), and backend
validation on the actual mutation call — not by the four state
concepts themselves. Do not hardcode a per-role simplified-status
mapping table sourced from the retired 22-value `docs/archive/user-view/`
vocabulary (the DATA_ENTRY mapping in `data-entry.md` alone lists 22
unique legacy enum values) — see "Request state," above.

---

## Forbidden-actions template

Every role-scoped or capability-gated surface should be reviewable
against an explicit forbidden-actions list, not just a positive list of
what it can do. This template generalizes the "Forbidden Actions
Reference" pattern found across `docs/archive/user-view/*.md` into an
architecture-current form:

1. **Enumerate the surface's real authority boundary** — not "what this
   role can't do in general," but specifically what the current
   surface must never render or allow, derived from the actual
   `stage_permissions`/screen-capability grants for that
   role/capability (see
   [`architecture/03-permission-model.md`](architecture/03-permission-model.md)),
   not from a fixed per-role table hardcoded in the frontend.
2. **Distinguish "no permission" from "permission held, action
   temporarily unavailable."** These are different backend states with
   different UI treatments, not one blanket "hide everything" rule:
   - **No permission at all** (no matching `stage_permissions` grant —
     the backend's `STAGE_EXECUTION_FORBIDDEN`) is genuinely forbidden:
     the control must not be rendered.
   - **Permission held, but the stage requires an unheld claim** (the
     backend's `CLAIM_NOT_HELD`) is not the same as forbidden — the
     shipped request-detail page (`canExecute` true,
     `claimRequiredButNotHeld` true) renders a visible prompt (a card
     with a "claim to continue" action), not a hidden control. Model
     this as its own state, not as either "always shown" or "always
     hidden."
3. **Cross-reference the backend enforcement**, don't just assert the
   UI hides it — frontend hiding is UX only; the backend is the
   authorization source of truth (see "Frontend permissions are UX
   only" under Architecture rules, below). If a backend response
   ever offers a forbidden action anyway, the frontend must drop it
   rather than render it.
4. **Do not enumerate voting actions as a forbidden category tied to a
   specific stage-based role split.** Executive Voting is out of V1 —
   there is no vote-casting, voting-session, or voting-queue action to
   list as forbidden-per-role in the first place (see "Executive
   Voting," above). A forbidden-actions list for a current role must
   not imply a voting feature exists to be forbidden from.

---

## Cross-role handoff template

The workflow engine's transitions are Designer-defined (see
[`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md))
— there is no fixed set of "the 3 handoffs a role participates in" the
way `docs/archive/user-view/*.md` enumerated for a fixed status
pipeline. Use this template to document a handoff for the workflow
version actually in use, rather than inheriting a fixed list:

1. **Identify the handoff by stage transition, not by status name.**
   State it as "stage A → stage B via transition T," keyed on the
   stages' code/name and the transition's code — not as a jump between
   two members of a static status enum. Stage codes, transition codes,
   and transition availability are Designer-defined and can differ
   between workflow versions. Where a stage has a `semantic_role` set
   (or one resolvable through the `SemanticRegistry` code-alias
   fallback — see
   [`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)),
   include it as supporting context ("stage A, semantic role
   `SUPPORT_REVIEW`"). `workflow_stages.semantic_role` is **nullable**
   and the compatibility fallback is still active, so treat semantic
   role as optional metadata: if it is present or resolvable, cite it;
   if it is absent, say so or document the fallback in use — **never
   invent a semantic role from a stage's display label.**
2. **State what UI surface makes the handoff visible to the receiving
   party** — a dashboard action-required strip, a claim-queue entry, a
   notification, a banner on the request-detail page. Every handoff
   this template documents should point at a concrete rendered surface,
   not just describe the backend transition.
3. **State what UI surface makes the handoff visible to the sending
   party** — e.g. a locked/read-only banner confirming the request left
   their editable set, or a "returned for correction" banner if the
   handoff is a return rather than a forward move.
4. **Note claim/permission implications**, if the destination stage has
   `requires_claim: true` — the receiving party may need to claim the
   request before decision controls appear (see "Claim heartbeat,"
   below).
5. **Do not hardcode a specific number of handoffs per role** ("this
   role participates in exactly N handoffs") — the number and shape of
   handoffs for a given role are a function of the published workflow
   version's stage graph, not a fixed property of the role itself.

---

## Claim heartbeat: generic to any claim-required stage, no automatic redirect on loss

`useEngineClaim()` (`frontend/app/composables/useEngineClaim.ts`) is
**generic to any claim-required stage** — it takes only `requestId` and
`currentUserId`, with no role check anywhere in the composable. It is
not restricted to Support Committee users; any executor on a stage with
`requires_claim: true` uses the same composable. Verified behavior,
directly from source:

- While the current user holds the claim (`isHeldByMe`), a `watch()`
  starts a 60-second `setInterval` that calls
  `POST /api/v1/engine-requests/{id}/claim/heartbeat`.
- The heartbeat stops automatically on release, component unmount
  (`onUnmounted`), or claim loss.
- **On `CLAIM_NOT_HELD`,** `handleClaimError()` calls `markClaimLost()`,
  which sets `claimLost.value = true`, clears `claimedBy`, and stops the
  heartbeat. **It does not call `navigateTo()` or trigger any
  notification** — there is no automatic redirect anywhere in
  `useEngineClaim.ts`.
- The request-detail page
  (`frontend/app/pages/workflows/instances/[id].vue`) reacts to
  `claimLost` by rendering an inline `Alert variant="destructive"`
  ("فُقدت مطالبة الطلب") with two manual buttons: "العودة إلى الطابور"
  (return to queue, calls `returnToQueue()`) and, if the stage still
  requires a claim and no one else holds it, "محاولة المتابعة مجدداً"
  (retry). The user must click to leave — nothing navigates them away
  automatically.

Do not claim an automatic redirect or a push notification on claim loss
unless a future change to `useEngineClaim.ts` or the request-detail page
actually adds one — the current behavior is inline, manual recovery.
See
[`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)'s
Claim lifecycle section for the backend TTL source.

---

## Architecture rules

- **No business logic in components.** Business logic lives in
  composables, stores, and services; components stay presentation-only.
- **Frontend permissions are UX only.** Hiding actions improves UX, but
  backend authorization is the security authority — never trust a
  frontend permission check for anything security-relevant.
- **No raw HTML where shadcn-vue provides a component.** See
  `frontend/SHADCN.md` for the full decision table.
- **Test compatibility does not override component integrity.** If a
  Vitest test cannot introspect a shadcn-vue component (teleported
  `Dialog` content, `Select` options not raw `<option>` tags, `Table`
  rows using `FlexRender`), skip or ignore that test rather than
  downgrading the component to raw HTML.

---

## Project structure

```
frontend/app/
├── components/
│   ├── ui/          ← shadcn-vue primitives only
│   ├── workflow/    ← Engine-request-specific components (forms, FX confirmation, SWIFT)
│   ├── dashboard/   ← MyWorkDashboard + the two analytics dashboards (Bank/System Admin)
│   ├── banners/     ← Claim/lock/correction state banners
│   ├── shared/      ← Cross-cutting presentation widgets
│   └── admin/       ← Governance/admin screens (roles, orgs, teams, screen permissions)
├── composables/     ← useEngine*, useDashboard, useDashboardWork, useAuth, etc.
├── stores/          ← auth, dashboard, dashboardWork, engineRequests, notifications, org, settings
├── middleware/
│   ├── auth.ts / auth.global.ts / guest.ts
│   ├── role.ts
│   └── screen.ts
├── pages/
├── layouts/
├── types/
├── utils/
└── constants/
```

---

## What this document removes from the legacy source

The following legacy content is **not** carried forward, and must not be
reintroduced:

- The fixed 18-value status vocabulary and the "Internal → Simplified
  Status Mapping" concept — replaced entirely by the four-field model in
  [`architecture/05-request-state-model.md`](architecture/05-request-state-model.md).
- Executive Voting as active UI functionality: dedicated `/voting`
  routes, a `voting.store.ts`, a `useVoting.ts` composable, "Voting
  Session UX" / "Vote Types" / "Voting Interface Requirements" sections
  describing a live feature. Executive Voting is out of V1; see the
  section above for what residue actually still exists.
- Fixed per-role navigation lists and per-role middleware files
  (`bank-reviewer.ts`, `executive.ts`) as the access-control model —
  route admission today runs through the shared `role`/`screen`
  middleware pair described above, not a middleware file per role.
- Customs-declaration terminology for the Director/FX-confirmation
  workflow — `/customs` is a legacy URL alias only; its content is
  external FX confirmation.
- The claim that `docs/03-database-and-models.md` is the source of truth
  for a status enum — that document no longer exists at that path; see
  [`architecture/06-database-and-models.md`](architecture/06-database-and-models.md).
- Suggested-but-never-built structural scaffolding presented as if
  shipped (`/services/api/`, `/services/voting/`, `middleware/admin.ts`)
  — the actual shipped structure is documented above, verified against
  `frontend/app/`.
