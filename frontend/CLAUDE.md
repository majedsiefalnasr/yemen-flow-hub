@../AGENTS.md
@PRODUCT.md
@DESIGN.md
@SHADCN.md

# Claude Code — Frontend

Yemen Flow Hub Nuxt 4 frontend application.

## Git Scope

Frontend code lives under `frontend/` in the root monorepo (`git@github.com:majedsiefalnasr/yemen-flow-hub.git`). It is tracked as normal root files, not as a submodule or nested Git repository.

Commit frontend changes from the root repository:

```bash
git add frontend/<files>
git commit -m "feat(workflow): add FX confirmation signed-document upload"
```

Conventional commit format: `type(scope): description`
All commits must stay signed. Never use `--no-gpg-sign`, `--no-sign`, or `-c commit.gpgsign=false`; if signing fails, fix signing first.
Never add or commit generated artifacts from `graphify-out/`, `_bmad-output/implementation-artifacts/`, or `_bmad-output/test-artifacts/`. Keep them local only.

Examples:

- `feat(workflow): add FX confirmation signed-document upload`
- `fix(support-queue): show heartbeat claim indicator`
- `style(rtl): fix table alignment in request details`

## Stack

- Nuxt 4, Vue 4, TypeScript
- Tailwind CSS v4
- shadcn-vue components
- Pinia (state management)
- VueUse, VeeValidate, Zod
- IBM Plex Sans Arabic + Inter fonts

## Architecture Rules

### Never in components

Business logic stays in composables, stores, and services. Components are presentation-only.

### Project structure

```
frontend/app/
├── components/
│   ├── ui/          ← shadcn-vue primitives only
│   ├── workflow/    ← Engine-request-specific components (forms, FX confirmation, SWIFT)
│   ├── dashboard/   ← MyWorkDashboard + the two analytics dashboards (Bank/System Admin)
│   ├── banners/     ← Claim/lock/correction state banners
│   ├── shared/       ← Cross-cutting presentation widgets
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

### RTL-first

- `dir="rtl"` on `<html>`
- All layouts default right-to-left
- Sidebar on the right side (280px expanded / 72px collapsed)
- Action columns rightmost in all tables
- No LTR layouts mirrored to RTL

### shadcn-vue — MANDATORY component usage

**All rules, recipes, and import paths are in `SHADCN.md`** (auto-loaded above via `@SHADCN.md`). Read it before writing any template code.

Key rules in brief:

- **No raw `<button>`** → `<Button>`
- **No raw `<table>/<tr>/<td>`** → `<Table>/<TableRow>/<TableCell>` etc.
- **No `animate-pulse` divs** → `<Skeleton>`
- **No custom error divs** → `<Alert variant="destructive">`
- **Quick-action tiles** (icon + title + desc stacked) → `<Card role="button" tabindex="0">` NOT `<Button>`
- **Destructive confirmations** → `<AlertDialog>` NOT `<Dialog>`
- **Import path**: always `from '@/components/ui/<name>'`

**Test compatibility rule:** Do NOT replace shadcn-vue components with raw HTML to make Vitest tests pass. shadcn-vue components are mandatory. If a Vitest test fails because it cannot introspect a shadcn-vue component (e.g. `<Dialog>` content is teleported, `<Select>` options are not raw `<option>` tags, `<Table>` rows use `FlexRender`), **skip or ignore that test** rather than downgrading the component to raw HTML. Component integrity takes precedence over test greenness.

### Role-aware UI

Frontend permissions are for UX only (hiding actions). Backend is the source of truth. Never trust frontend permission checks for security.

### Status handling

- Use the canonical request state model from `AGENTS.md`: `runtime_status`, `current_stage` (designer-defined stage, incl. `semantic_role`), and `final_outcome` — never a static frontend status enum
- Never show CBY internal workflow stages to Data Entry users; the current stage's designer label and the runtime status drive what a role sees, not a hardcoded per-role status map

### Support claim heartbeat

When a Support Committee user is on the active review page:

- Send `POST /api/v1/engine-requests/{id}/claim/heartbeat` every **60 seconds**
- Stop on page leave / component unmount
- On claim loss (API 403 `CLAIM_NOT_HELD`), redirect user back to queue with notification

### Read-only states

Locked workflow states must visually communicate lock:

- Disabled action buttons
- Lock icon + "Locked" badge
- `#f5f5f7` field backgrounds with `#8e8e93` text
- Read-only banner at top of request

## Design Tokens (from DESIGN.md)

```ts
// Use these exact values — no approximations
const colors = {
  background: '#ffffff',
  surface: '#ffffff',
  primaryText: '#1c222b', // on-surface
  secondaryText: '#6c757d', // on-surface-variant
  border: '#cccccc', // outline-variant
  primaryBlue: '#0066cc',
  successText: '#1b5e20',
  errorText: '#c62828',
  warningText: '#f57f17',
  votingIndigo: '#5856d6',
  swiftCyan: '#32ade6',
  lockedGray: '#8e8e93',
}
```

Button radius: `16px` (lg). Input radius: `12px` (md). Modal radius: `24px` (xl).
Container max: `1600px`. Sidebar: `280px` expanded / `72px` collapsed. Grid: `8px` base, `24px` gutters. No gradients. No glassmorphism.

## Pages

```
/login
/dashboard
/workflows
/workflows/new
/workflows/instances/[id]
/customs          ← legacy alias, still present
/admin/banks      ← CBY Admin only
/bank/users       ← Bank Admin only
/staff            ← CBY Admin only
```

The legacy `/requests`, `/requests/new`, `/requests/[id]`, `/voting`, `/voting/[id]`, top-level `/users`, and top-level `/banks` routes no longer exist. Executive Voting is not part of V1 — there is no voting UI anywhere, including within `/workflows/instances/[id]`.

## Anti-patterns (never generate)

- Shared analytics dashboards visible to all roles equally
- Charts, KPIs, or vanity metrics on operational dashboards
- A static frontend status enum (`RequestStatus`-style) reconstructing state from labels instead of reading `runtime_status`/`current_stage`/`final_outcome`
- LTR layouts adapted to RTL by just flipping direction
- Business logic inside Vue `<script setup>` directly
- Frontend-only visibility filtering without backend enforcement

## Context7 Usage

Before writing Nuxt/Vue/Tailwind implementation:

```bash
npx ctx7@latest library "Nuxt" "<your question>"
npx ctx7@latest docs <id> "<your question>"
```

Use for: Nuxt 4, Vue 4, Tailwind v4, shadcn-vue, Pinia, VeeValidate, Zod.

## SocratiCode Usage

Before modifying any composable, store, or service:

1. `codebase_search` — find related code semantically
2. `codebase_flow` — trace how data flows
3. `codebase_impact` — check what would break

## Verification Ladder

Before editing, run `git -c core.fsmonitor=false status --short` from `frontend/` and report existing dirty files. Do not modify dirty files unless directly in scope.

Keep `pnpm`; do not migrate to Bun. Default verification is focused:

1. Run the smallest relevant Vitest file or name filter for the touched behavior.
2. Run ESLint/Prettier only for touched files where possible.
3. Run `pnpm typecheck` only when changing types, composables, stores, API contracts, shared interfaces, or cross-module contracts.
4. Do not run full `pnpm test` by default.
5. Full frontend suites are required only for release checks, broad refactors, security-critical changes, or explicit user requests.
6. If the full suite is known red, report the known baseline and do not treat unrelated failures as task failures.

Focused commands:

```bash
pnpm exec vitest run app/tests/unit/components/FxConfirmationCard.test.ts
pnpm exec vitest run -t "rejects non-PDF uploads"
pnpm exec eslint app/components/Example.vue app/composables/useExample.ts
pnpm exec prettier app/components/Example.vue --check
```

## Browser Automation

For UI validation and browser interactions, use `playwright-cli`. Keep the `playwright-cli` command prefix permanently allowlisted in local tool permissions to avoid repeated approval prompts during frontend verification.

## Docs Reference

Full rules in `../docs/` and `../AGENTS.md`. Key files:

- `../docs/01-workflow-and-business-rules.md` — workflow stages and simplified status mapping
- `../docs/04-frontend-guide.md` — frontend architecture
- `../docs/06-api-reference.md` — API contracts (endpoints, response shapes)
- `../DESIGN.md` — visual design system
