@../AGENTS.md
@PRODUCT.md
@DESIGN.md
@SHADCN.md

# Claude Code — Frontend

Yemen Flow Hub Nuxt 4 frontend application.

## Git Scope

Frontend code lives in **two repos simultaneously**:
- **Frontend team repo** (`git@github.com:ultimate-eg/yemen-flow-hub-frontend.git`) — frontend team's standalone repo
- **Root monorepo** (`git@github.com:majedsiefalnasr/yemen-flow-hub.git`) — tracked under `frontend/`

Every frontend change must be committed to **both**:

```bash
# 1. From inside frontend/ — commit to frontend team repo
git add <files>
git commit -m "feat(voting): add session open/close director controls"

# 2. From root — commit same change to root monorepo
cd ..
git add frontend/<files>
git commit -m "feat(voting): add session open/close director controls"
```

Conventional commit format: `type(scope): description`
All commits must stay signed. Never use `--no-gpg-sign`, `--no-sign`, or `-c commit.gpgsign=false`; if signing fails, fix signing first.
Never add or commit generated artifacts from `graphify-out/`, `_bmad-output/implementation-artifacts/`, or `_bmad-output/test-artifacts/`. Keep them local only.

Examples:
- `feat(voting): add session open/close director controls`
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
frontend/
├── components/
│   ├── ui/          ← shadcn-vue primitives only
│   ├── forms/       ← Form components
│   ├── workflow/    ← Workflow-specific components
│   ├── voting/      ← Voting interface components
│   ├── dashboard/   ← Queue/dashboard widgets
│   ├── audit/       ← Audit trail components
│   ├── tables/      ← Data tables
│   └── layout/      ← Layout components
├── composables/
│   ├── useAuth.ts
│   ├── usePermissions.ts
│   ├── useWorkflow.ts
│   ├── useVoting.ts
│   └── useApi.ts
├── stores/
│   ├── auth.store.ts
│   ├── requests.store.ts
│   ├── workflow.store.ts
│   ├── voting.store.ts
│   └── notifications.store.ts
├── services/
│   ├── api/
│   ├── auth/
│   ├── requests/
│   └── voting/
├── middleware/
│   ├── auth.ts
│   ├── guest.ts
│   └── role.ts
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

### Role-aware UI
Frontend permissions are for UX only (hiding actions). Backend is the source of truth. Never trust frontend permission checks for security.

### Status handling
- Internal statuses: use the canonical enum from `AGENTS.md` exactly
- Data Entry users receive **simplified statuses** only (see mapping in `../docs/01-workflow-and-business-rules.md`)
- Never show CBY internal workflow stages to Data Entry users
- Status → simplified label mapping must be centralized in a single composable/constant

### Support claim heartbeat
When a Support Committee user is on the active review page:
- Send `POST /api/workflow/{id}/claim-support-review/heartbeat` every **60 seconds**
- Stop on page leave / component unmount
- On claim loss (API 409), redirect user back to queue with notification

### Read-only states
Locked workflow states must visually communicate lock:
- Disabled action buttons
- Lock icon + "Locked" badge
- `#f5f5f7` field backgrounds with `#8e8e93` text
- Read-only banner at top of request

### Voting concurrency UI
After submitting a vote, optimistically update the UI. If server returns `VOTING_SESSION_CLOSED`, revert and show notification.

## Design Tokens (from DESIGN.md)

```ts
// Use these exact values — no approximations
const colors = {
  background: '#ffffff',
  surface: '#ffffff',
  primaryText: '#1c222b',       // on-surface
  secondaryText: '#6c757d',     // on-surface-variant
  border: '#cccccc',            // outline-variant
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
/requests
/requests/new
/requests/[id]
/voting
/voting/[id]
/customs
/customs/[id]
/users          ← CBY Admin only
/banks          ← CBY Admin only
```

## Anti-patterns (never generate)

- Shared analytics dashboards visible to all roles equally
- Charts, KPIs, or vanity metrics on operational dashboards
- `INTERNAL_REJECTED` or `WAITING_SWIFT` status values (wrong enum names)
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

## Docs Reference

Full rules in `../docs/` and `../AGENTS.md`. Key files:
- `../docs/01-workflow-and-business-rules.md` — workflow stages and simplified status mapping
- `../docs/04-frontend-guide.md` — frontend architecture
- `../docs/06-api-reference.md` — API contracts (endpoints, response shapes)
- `../DESIGN.md` — visual design system
