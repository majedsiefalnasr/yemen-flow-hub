# G11-P1: Engine Nav Wiring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the dynamic engine `/workflows` pages reachable and role-gated in the frontend sidebar, command palette, and search, and repoint all "create new request" CTAs to `/workflows/new` so new requests flow into the engine (legacy `/requests` becomes a read-only view of old data until G11-P6 removes it).

**Architecture:** The frontend derives nav from a single role-surface contract. Add a `nav.workflows` surface (route `/workflows`, all 8 roles), a `nav.workflows_new` surface (route `/workflows/new`, the create-capability roles), register them in the surface matrix / route map / NAV_ITEMS / sidebar groups / palette / search. Then repoint every `/requests/new` CTA (dashboards, pages, command palette quick-action, request list "new" button) to `/workflows/new`. The legacy `/requests` nav stays — both coexist until P6.

**Tech Stack:** Nuxt 4, Vue 4, TypeScript, shadcn-vue, Vitest.

## Global Constraints

- Single git repo now (no submodules). Commit from root: `git add frontend/<files> && git commit`. Conventional commits: `type(scope): description`; **scope must be `workflow`**. End every message with a blank line then `Co-Authored-By: Claude <noreply@anthropic.com>`. Signed — never `--no-gpg-sign`. Husky commit-msg + pre-push hooks active on the root repo — do NOT `--no-verify`.
- Frontend at `frontend/app/`. Run `pnpm` with `frontend/` as working dir. Use `git -C <abs>` or run with the right working dir; never `cd` inside compound `&&`.
- shadcn-vue components only (no raw `<button>`/`<table>`). RTL-first, Arabic-first. Semantic color tokens, not raw Tailwind color classes.
- Role-surface contract is the single source of nav truth — every nav change goes through `nav.<surface>` in `app/constants/role-surfaces.ts`. Do NOT hard-code role arrays for nav.
- `nav.workflows` = ALL 8 roles see it (coexist decision). `nav.workflows_new` (create) = the same roles currently allowed `nav.new_request` (so existing create-capability roles map across).
- TDD where behavior is testable (composables/constants logic). For pure nav-link/CTA wiring (template edits), the test is: typecheck clean + a Vitest asserting the route surfaces resolves to the expected roles + manual smoke via `playwright-cli` at the end.
- Verification: focused. Run touched Vitest files + `pnpm typecheck` (this changes constants/types/composables — typecheck required) + `pnpm exec eslint <files>` (zero warnings). Do NOT run full `pnpm test` by default.

---

### Task 1: Add `nav.workflows` + `nav.workflows_new` surfaces and wire nav

**Files:**
- Modify: `frontend/app/constants/role-surfaces.ts` (union type line 3-29, `NAV_SURFACE_ROUTES` lines 295-336)
- Modify: `frontend/app/constants/role-surfaces.ts` `ROLE_SURFACE_MATRIX` (add `nav.workflows` to every role's `allowed[]`; add `nav.workflows_new` to every role that currently has `nav.new_request`)
- Modify: `frontend/app/constants/workflow.ts` (`NAV_ITEMS` ~line 274, `ROUTE_ROLE_MAP` ~line 1001, `PROTECTED_ROUTES` ~line 983)
- Modify: `frontend/app/components/AppSidebar.vue` (`NAV_GROUP_DEFS` line 135)
- Modify: `frontend/app/components/CommandPalette.vue` (`shortcutByRoute` line 69, `aliasesByRoute` line 91, the routes heading line 216)
- Modify: `frontend/app/components/SearchForm.vue` (`shortcutByRoute` line 33, `commandGroupByRoute` line 51)
- Modify: `frontend/app/composables/useNavBadges.ts` (add `/workflows` badge wiring if it mirrors `/requests`)
- Test: `frontend/app/tests/unit/constants/role-surfaces.test.ts` (create if absent) — assert `rolesForSurface('nav.workflows')` returns all 8 roles; `NAV_SURFACE_ROUTES['nav.workflows'] === '/workflows'`.

**Interfaces:**
- Consumes: existing `rolesForSurface`, `NAV_SURFACE_ROUTES`, `ROUTE_ROLE_MAP` machinery.
- Produces: `nav.workflows` surface → route `/workflows`, all 8 roles; `nav.workflows_new` surface → route `/workflows/new`, create-capability roles. Both resolvable by `rolesForSurface` and `resolveRouteRoles`.

- [ ] **Step 1: Write the failing test**

Create/extend `frontend/app/tests/unit/constants/role-surfaces.test.ts`:

```ts
import { describe, it, expect } from 'vitest'
import {
  ROLE_SURFACE_MATRIX,
  NAV_SURFACE_ROUTES,
  rolesForSurface,
} from '@/constants/role-surfaces'
import { UserRole } from '@/types/enums'

describe('engine workflow nav surfaces', () => {
  it('nav.workflows route is /workflows', () => {
    expect(NAV_SURFACE_ROUTES['nav.workflows']).toBe('/workflows')
  })

  it('nav.workflows_new route is /workflows/new', () => {
    expect(NAV_SURFACE_ROUTES['nav.workflows_new']).toBe('/workflows/new')
  })

  it('nav.workflows is visible to all 8 roles', () => {
    const roles = rolesForSurface('nav.workflows')
    expect(new Set(roles)).toEqual(new Set(Object.values(UserRole)))
  })

  it('nav.workflows_new roles match nav.new_request roles', () => {
    expect(rolesForSurface('nav.workflows_new').sort()).toEqual(
      rolesForSurface('nav.new_request').sort(),
    )
  })

  it('every role has nav.workflows in its allowed list', () => {
    for (const role of Object.values(UserRole)) {
      expect(ROLE_SURFACE_MATRIX[role].allowed).toContain('nav.workflows')
    }
  })
})
```

If `role-surfaces.test.ts` already exists, APPEND this `describe` block rather than overwriting.

- [ ] **Step 2: Run test to verify it fails**

Run (from `frontend/`): `pnpm exec vitest run app/tests/unit/constants/role-surfaces.test.ts`
Expected: FAIL — `nav.workflows` is not a key yet (TS error on the surface union, or `undefined` route).

- [ ] **Step 3: Add the two surfaces to the union type**

In `frontend/app/constants/role-surfaces.ts`, in the `RoleSurfaceKey` union (lines 3-29), add after `'nav.new_request',` (line 6):

```ts
  | 'nav.workflows'
  | 'nav.workflows_new'
```

- [ ] **Step 4: Add routes to NAV_SURFACE_ROUTES**

In the same file, in `NAV_SURFACE_ROUTES` (lines 316-336): add `nav.workflows` and `nav.workflows_new` to BOTH the key union (lines 296-314) and the object (after `'nav.new_request': '/requests/new',` line 319):

```ts
  'nav.workflows': '/workflows',
  'nav.workflows_new': '/workflows/new',
```

Add `'nav.workflows'` and `'nav.workflows_new'` to the `Record<... , string>` key union above the `= {` so the type accepts them.

- [ ] **Step 5: Add surfaces to ROLE_SURFACE_MATRIX**

For EVERY role in `ROLE_SURFACE_MATRIX` (DATA_ENTRY, BANK_REVIEWER, BANK_ADMIN, SWIFT_OFFICER, SUPPORT_COMMITTEE, EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR, CBY_ADMIN — 8 entries), add `'nav.workflows',` to that role's `allowed[]` array (next to `'nav.requests',` is a natural spot).

For each role whose `allowed[]` currently contains `'nav.new_request',`, ALSO add `'nav.workflows_new',` immediately after it.

Do NOT add `nav.workflows_new` to a role that lacks `nav.new_request` — the test asserts the two role-sets are equal.

- [ ] **Step 6: Add NAV_ITEMS entries**

In `frontend/app/constants/workflow.ts`, in `NAV_ITEMS` (starts ~line 274), add two entries after the `nav.new_request` item (after line ~291). Pick an existing icon name from the `IconName` set used by siblings (e.g. `'git-branch'` or `'workflow'` if present — verify against `ICONS`/`IconName` in this file; if neither exists use an existing name like `'layers'`):

```ts
  {
    label: 'سير العمل',
    route: NAV_SURFACE_ROUTES['nav.workflows'],
    icon: '<chosen-existing-icon-name>',
    roles: rolesForSurface('nav.workflows'),
  },
```

(Only ONE NAV_ITEMS entry is needed for `/workflows` — the queue page. Do NOT add `/workflows/new` as a sidebar item; it is reached via the create CTA, like `/requests/new` is reached via CTA. Confirm `/requests/new` is NOT a standalone NAV_ITEMS entry today — if it is, mirror it; if not, do not add one.)

- [ ] **Step 7: Add routes to ROUTE_ROLE_MAP + PROTECTED_ROUTES**

In `frontend/app/constants/workflow.ts`:

In `PROTECTED_ROUTES` (~line 983), add:
```ts
  '/workflows',
  '/workflows/new',
```

In `ROUTE_ROLE_MAP` (~line 1001), add (next to the `/requests` entries):
```ts
  '/workflows': rolesForSurface('nav.workflows'),
  '/workflows/new': rolesForSurface('nav.workflows_new'),
  '/workflows/:id': rolesForSurface('nav.workflows'),
```

- [ ] **Step 8: Add /workflows to a sidebar group**

In `frontend/app/components/AppSidebar.vue`, `NAV_GROUP_DEFS` line 135:
```ts
    routes: ['/requests', '/requests/new', '/workflows', '/customs', '/staff'],
```

- [ ] **Step 9: Add /workflows to CommandPalette**

In `frontend/app/components/CommandPalette.vue`:
- `shortcutByRoute` (line 69): add `'/workflows': 'W',`
- `aliasesByRoute` (line 91): add `'/workflows': 'engine workflow dynamic requests دوري',`
- the headings list (line 216): change to
```ts
  { heading: 'الطلبات', routes: ['/requests', '/requests/new', '/workflows', '/workflows/new'] },
```

- [ ] **Step 10: Add /workflows to SearchForm**

In `frontend/app/components/SearchForm.vue`:
- `shortcutByRoute` (line 33): add `'/workflows': 'W',`
- `commandGroupByRoute` (line 54): change to
```ts
    routes: ['/dashboard', '/requests', '/requests/new', '/workflows', '/workflows/new', '/customs'],
```

- [ ] **Step 11: Add /workflows badge (if mirrored)**

Read `frontend/app/composables/useNavBadges.ts`. If it has an entry/branch for `/requests`, add a parallel one for `/workflows` so the engine queue can later show an unread/action badge. If the composable uses a data-driven map (not a hard-coded `/requests` branch), no change is needed — note that in the report. Do not invent badge data; wiring only.

- [ ] **Step 12: Run the test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/constants/role-surfaces.test.ts`
Expected: PASS (all assertions).

- [ ] **Step 13: typecheck + lint touched files**

Run:
```bash
pnpm typecheck
pnpm exec eslint app/constants/role-surfaces.ts app/constants/workflow.ts app/components/AppSidebar.vue app/components/CommandPalette.vue app/components/SearchForm.vue app/composables/useNavBadges.ts app/tests/unit/constants/role-surfaces.test.ts
```
Expected: typecheck 0 errors; eslint clean (0 warnings). If typecheck flags the `NAV_ITEMS` icon name (must be a valid `IconName`), change the icon to one that exists in the `IconName`/`ICONS` set in `workflow.ts`.

- [ ] **Step 14: Commit**

```bash
git -C <root> add frontend/app/constants/role-surfaces.ts frontend/app/constants/workflow.ts frontend/app/components/AppSidebar.vue frontend/app/components/CommandPalette.vue frontend/app/components/SearchForm.vue frontend/app/composables/useNavBadges.ts frontend/app/tests/unit/constants/role-surfaces.test.ts
git -C <root> commit -m "feat(workflow): wire engine /workflows into nav and role surfaces

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 2: Repoint create-request CTAs to the engine

**Files:**
- Modify: `frontend/app/components/CommandPalette.vue` (QUICK_ACTIONS `qa-new-request` URL line ~119)
- Modify: `frontend/app/components/dashboard/DataEntryDashboard.vue` (lines ~270, ~320)
- Modify: `frontend/app/pages/index.vue` (lines ~44, ~60)
- Modify: `frontend/app/pages/dashboard.vue` (lines ~45, ~54)
- Modify: `frontend/app/pages/merchants.vue` (lines ~546, ~1356)
- Modify: `frontend/app/pages/requests/index.vue` (line ~825) — the legacy list "new request" button
- Modify: `frontend/app/pages/requests/new.vue` — redirect guard, not a CTA (see below)
- Test: extend `frontend/app/tests/unit/constants/role-surfaces.test.ts` OR a CTA-target test asserting the engine-new surface resolves; plus rely on typecheck.

**Interfaces:**
- Consumes: `nav.workflows_new` surface + `/workflows/new` route from Task 1.
- Produces: every "create request" affordance routes the user to `/workflows/new`. `ROUTE_ROLE_MAP['/workflows/new']` gates those CTAs (replaces `ROUTE_ROLE_MAP['/requests/new']` reads).

- [ ] **Step 1: Write the failing test**

Append to `frontend/app/tests/unit/constants/role-surfaces.test.ts`:

```ts
import { ROUTE_ROLE_MAP } from '@/constants/workflow'

describe('engine create CTA route guard', () => {
  it('/workflows/new route guard exists and matches nav.workflows_new', () => {
    expect(ROUTE_ROLE_MAP['/workflows/new']).toEqual(rolesForSurface('nav.workflows_new'))
  })
})
```

- [ ] **Step 2: Run test to verify it passes (Task 1 already added the route)**

Run: `pnpm exec vitest run app/tests/unit/constants/role-surfaces.test.ts`
Expected: PASS (this is a guard-confirmation test; if it fails, Task 1's `/workflows/new` ROUTE_ROLE_MAP entry is missing — add it).

- [ ] **Step 3: Repoint CommandPalette quick-action**

In `frontend/app/components/CommandPalette.vue`, the `qa-new-request` entry (~line 119): change `url: '/requests/new'` → `url: '/workflows/new'`.

- [ ] **Step 4: Repoint DataEntryDashboard CTAs**

In `frontend/app/components/dashboard/DataEntryDashboard.vue`:
- line ~270: `router.push('/requests/new')` → `router.push('/workflows/new')`
- line ~320: `to="/requests/new"` → `to="/workflows/new"`

Read the surrounding template for each — there may be a comment or a second CTA; repoint all `/requests/new` occurrences in this file to `/workflows/new`.

- [ ] **Step 5: Repoint pages/index.vue + pages/dashboard.vue**

In `frontend/app/pages/index.vue` and `frontend/app/pages/dashboard.vue`:
- Each reads `ROUTE_ROLE_MAP['/requests/new']` to decide CTA visibility (~line 44 / ~45) and pushes `/requests/new` (~line 60 / ~54). Change BOTH the guard read and the push target to `/workflows/new` (guard: `ROUTE_ROLE_MAP['/workflows/new']`).

- [ ] **Step 6: Repoint pages/merchants.vue**

In `frontend/app/pages/merchants.vue`, two occurrences (~546, ~1356) push `/requests/new` — change both to `/workflows/new`. Read context to confirm both are "start a request for this merchant" CTAs (they are).

- [ ] **Step 7: Legacy /requests list "new" button + legacy /requests/new page**

- `frontend/app/pages/requests/index.vue` line ~825: the legacy list's "new request" `<Button href="/requests/new">`. Repoint to `/workflows/new` so even the legacy list starts new requests in the engine.
- `frontend/app/pages/requests/new.vue` (the legacy create page): since CTAs no longer point here, add a `definePageMeta({ middleware })` redirect OR keep it reachable for rollback. **Decision: leave it reachable** (rollback safety per G11 cutover). Do NOT delete. Add a one-line code comment at top: `// Legacy create page — kept for G11 rollback; CTAs now route to /workflows/new`. (Edit only — no behavioral redirect yet.)

- [ ] **Step 8: typecheck + lint touched files**

Run:
```bash
pnpm typecheck
pnpm exec eslint app/components/CommandPalette.vue app/components/dashboard/DataEntryDashboard.vue app/pages/index.vue app/pages/dashboard.vue app/pages/merchants.vue app/pages/requests/index.vue app/pages/requests/new.vue
```
Expected: typecheck 0 errors; eslint clean.

- [ ] **Step 9: Commit**

```bash
git -C <root> add frontend/app/components/CommandPalette.vue frontend/app/components/dashboard/DataEntryDashboard.vue frontend/app/pages/index.vue frontend/app/pages/dashboard.vue frontend/app/pages/merchants.vue frontend/app/pages/requests/index.vue frontend/app/pages/requests/new.vue frontend/app/tests/unit/constants/role-surfaces.test.ts
git -C <root> commit -m "feat(workflow): repoint create-request CTAs to engine /workflows/new

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Final verification

- [ ] **Full focused test run**

Run (from `frontend/`):
```bash
pnpm exec vitest run app/tests/unit/constants/role-surfaces.test.ts
pnpm exec vitest run app/tests/unit/composables/useNavBadges.test.ts
pnpm typecheck
```
Expected: tests green, typecheck 0 errors.

- [ ] **Manual smoke (playwright-cli)**

Log in as DATA_ENTRY → sidebar shows "سير العمل" + "طلبات التمويل"; the dashboard "طلب جديد" CTA navigates to `/workflows/new`; `/workflows` loads the engine queue; command palette (Cmd/Ctrl-K) lists `/workflows`. Log in as CBY_ADMIN → both nav items visible. Confirm legacy `/requests` still reachable.

---

## Self-Review

- **Spec coverage:** nav surface (T1 surfaces+matrix+items+group+palette+search+badges+routes), CTA repoint (T2 palette+dashboards+pages+merchants+legacy list). Create-CTA repoint + nav-link both covered.
- **Placeholder scan:** one verify-before-use item — the `NAV_ITEMS` icon name (Task 1 Step 6) must be an existing `IconName`; flagged in-step, not a placeholder. Legacy `/requests/new` handling decided (keep for rollback).
- **Type consistency:** `nav.workflows` / `nav.workflows_new` used consistently T1↔T2; `ROUTE_ROLE_MAP['/workflows/new']` read in T2 matches the entry added in T1 Step 7.
