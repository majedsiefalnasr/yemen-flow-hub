# Yemen Flow Hub — GitHub Copilot Instructions (Frontend)

Nuxt 4 frontend for Yemen Flow Hub — an internal government banking regulatory workflow platform for the Central Bank of Yemen.

## Source of Truth

Read `AGENTS.md` (one level up at `../AGENTS.md`) and `CLAUDE.md` in this directory. All workflow rules, status enums, and design tokens are defined there.

## Git

Frontend code is tracked under `frontend/` in the root monorepo. Commit every frontend change from the root repository:

```bash
git add frontend/<files>
git commit -m "type(scope): description"
```

Commit format: `type(scope): description`

## Critical Rules

### RTL is the default direction

```html
<html dir="rtl" lang="ar"></html>
```

All layouts are right-to-left. Never mirror LTR.

### No business logic in components

Logic belongs in composables, stores, and services only.

### Canonical status values (use exactly these)

DRAFT, DRAFT_REJECTED_INTERNAL, SUBMITTED, BANK_REVIEW, BANK_APPROVED,
SUPPORT_REVIEW_PENDING, SUPPORT_REVIEW_IN_PROGRESS, SUPPORT_APPROVED,
SUPPORT_REJECTED, WAITING_FOR_SWIFT, SWIFT_UPLOADED, WAITING_FOR_VOTING_OPEN,
EXECUTIVE_VOTING_OPEN, EXECUTIVE_VOTING_CLOSED, EXECUTIVE_APPROVED,
EXECUTIVE_REJECTED, CUSTOMS_DECLARATION_ISSUED, COMPLETED

Never use `INTERNAL_REJECTED`, `WAITING_SWIFT`, or any unlisted value.

### Data Entry simplified status mapping

Centralize in `constants/statusMap.ts`. Do NOT show CBY internal stages to DATA_ENTRY role.

### Design tokens

```ts
background: '#f5f5f7', surface: '#ffffff', primaryText: '#1d1d1f',
border: '#d2d2d7', primaryBlue: '#0071e3', approvedGreen: '#34c759',
rejectedRed: '#ff3b30', pendingAmber: '#ff9f0a', votingIndigo: '#5856d6',
swiftCyan: '#32ade6', lockedGray: '#8e8e93'
```

No gradients. No glassmorphism. Card radius: 12px.

### Support claim heartbeat

Ping `POST /api/workflow/{id}/claim-support-review/heartbeat` every 60 seconds while reviewer is on the page.

### Frontend permissions are UX only

Backend is the source of truth for authorization. Never skip an API call because frontend permission appears to allow it.

## Context7

```bash
npx ctx7@latest library "Nuxt" "<question>"
npx ctx7@latest docs <id> "<question>"
```

## SocratiCode

Use semantic codebase search before modifying composables, stores, or services.

## Verification Ladder

Before editing, check `git -c core.fsmonitor=false status --short` from the repository root and report existing dirty files. Do not modify dirty files unless directly in scope.

Keep `pnpm`; do not migrate to Bun. Default verification is focused:

- Run the smallest relevant Vitest file or name filter for the touched behavior.
- Run ESLint/Prettier only for touched files where possible.
- Run `pnpm typecheck` only for type, composable, store, API contract, shared interface, or cross-module changes.
- Do not run full `pnpm test` by default.
- Full suites are required only for release checks, broad refactors, security-critical changes, or explicit user requests.
- If the full suite is known red, report the known baseline and do not treat unrelated failures as task failures.

Focused commands:

```bash
pnpm exec vitest run app/tests/unit/components/FxConfirmationCard.test.ts
pnpm exec vitest run -t "rejects non-PDF uploads"
pnpm exec eslint app/components/Example.vue app/composables/useExample.ts
pnpm exec prettier app/components/Example.vue --check
```
