# Frontend Guide

**Verified:** 2026-07-13, against `frontend/app/pages/`,
`frontend/app/components/dashboard/`, `frontend/app/middleware/`,
`frontend/app/constants/workflow.ts`, and `frontend/CLAUDE.md` /
`frontend/PRODUCT.md` / `frontend/DESIGN.md` / `frontend/SHADCN.md`
directly — not carried over from the legacy `docs/04-frontend-guide.md`,
which predates the dynamic workflow engine and describes a fixed
per-role static-status UX model (18-value status vocabulary, dedicated
`/voting` routes, per-role Pinia stores) that no longer matches the
shipped application.

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

Nuxt 4, Vue 4, TypeScript, Tailwind CSS v4, shadcn-vue, Pinia, VueUse,
VeeValidate, Zod. RTL-first, Arabic-first (IBM Plex Sans Arabic + Inter).
Desktop-first with responsive degradation at ≤ 600px.

---

## Actual routes (verified against `frontend/app/pages/`)

The shipped route tree is substantially larger than a short "main
pages" list can convey. As of this verification, `frontend/app/pages/`
contains:

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
/settings, /settings/user, /settings/bank, /settings/system
/staff, /bank/users
/admin/staff, /admin/banks, /admin/orgs, /admin/settings, /admin/health,
/admin/roles, /admin/workflows, /admin/teams, /admin/reference-data,
/admin/screen-permissions
/mfa-setup, /reset-password, /change-temporary-password
/forbidden, /unauthorized
```

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

## Route admission: capability-derived, with a small number of hardcoded exceptions

Route access is gated by the `role` middleware
(`frontend/app/middleware/role.ts`), which reads
`ROUTE_ROLE_MAP[path]` (`frontend/app/constants/workflow.ts`) and
redirects to `/forbidden` if the current user's role isn't in the list.
**Most entries in `ROUTE_ROLE_MAP` are derived from a screen/capability
surface** via `rolesForSurface('nav.xxx')` — e.g. `/workflows`,
`/merchants`, `/customs`, `/reports`, `/audit`, `/notifications`, and
every `/admin/*` route except two. **A small number of routes are
hardcoded to a fixed role array instead:** `/admin` and `/admin/health`
(`[UserRole.CBY_ADMIN]`), `/settings/system` (`[UserRole.CBY_ADMIN]`),
and `/settings/bank` (`[UserRole.BANK_ADMIN]`). Do not describe route
admission as "capability-only end to end" — it is capability-derived for
most routes, with these specific fixed-role exceptions, matching the
pattern [`AGENTS.md`](../AGENTS.md) documents for the dashboard route
itself (see below).

---

## Dashboard selection: capability-led component choice, on top of a fixed-role route gate

`frontend/app/pages/dashboard.vue` and `frontend/app/pages/index.vue`
both implement the same **Phase D0 capability-family routing**, verified
directly:

```ts
const dashboardFamily = computed<"system" | "bank" | "work">(() => {
  if (can("system_dashboard", "VIEW")) return "system";
  if (can("bank_analytics", "VIEW")) return "bank";
  return "work";
});
```

`SystemAdminDashboard` (`CbyAdminDashboard.vue`) →
`BankAdminDashboard.vue` → `MyWorkDashboard.vue` (fallthrough) — three
components selected purely by the `can()` capability check, never by
`role === X`. Any future dynamic executor role that holds neither
analytics capability automatically falls through to `MyWorkDashboard`
with no frontend code change.

This capability-led **component selection** sits on top of a still
fixed-role **route admission** gate: both `dashboard.vue` and
`index.vue` carry `definePageMeta({ middleware: ['auth', 'role'],
requiredRoles: ROUTE_ROLE_MAP['/dashboard'] })`, and `/dashboard`
resolves through the same `rolesForSurface('nav.dashboard')` capability
mapping described above — so reaching the dashboard page at all is
still capability-derived, but through the route layer, not the
component layer. Do not describe dashboard selection as "capability
end-to-end and nothing else" — it is two capability-gated layers
(route admission, then component choice within the page), not a single
unguarded capability check with no route-level gate at all.

For the full two-family model (why `BankAdminDashboard` has no
actionable queue, the shared actionable-work invariant across
`/my-queue`/nav badge/dashboard preview), see
[`architecture/04-dashboard-architecture.md`](architecture/04-dashboard-architecture.md)
— not duplicated here.

---

## Request state: read the four fields, never a static enum

There is no frontend `RequestStatus` enum in the current codebase
(confirmed: `frontend/app/types/enums.ts` has no such type). Status
badges, timelines, filters, and dashboards must read `runtime_status`,
`current_stage` (including `current_stage.semantic_role`), and
`final_outcome` from the API and build presentation inline —
`BankAdminDashboard.vue`'s `RUNTIME_STATUS_BADGE` map (verified
directly) is the reference pattern cited by
[`frontend/DESIGN.md`](../frontend/DESIGN.md) §7. There is no shared
`StatusBadge` component; every consumer builds its own badge from the
same three fields and the semantic token map.

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

---

## Support claim heartbeat

When a Support Committee user is on the active review page: send
`POST /api/v1/engine-requests/{id}/claim/heartbeat` every 60 seconds;
stop on page leave / component unmount; on claim loss (`403
CLAIM_NOT_HELD`), redirect back to queue with a notification. See
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
  route admission today runs through the single `role` middleware
  reading `ROUTE_ROLE_MAP`, itself mostly capability-derived (see
  above).
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
