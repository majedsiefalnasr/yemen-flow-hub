# Story 7.3: Requests List 1:1 Parity

Status: done

## Story

As a user reviewing workflow queues,
I want the requests list to match the Lovable list layout for my role,
so that request scanning, filtering, and actions behave exactly as approved.

---

## Acceptance Criteria

**AC1 - Page header and actions parity**
Given I am authenticated and navigate to `/requests`,
Then the page uses the Story 7.1 AppShell/header/sidebar without regression,
And the request-list page header matches Lovable `PageHeader` intent: title, role-scoped subtitle, breadcrumbs, export action, and create action where production authorization permits it,
And the page spacing, max width, RTL alignment, card shadows, border radii, and responsive behavior match the relevant Lovable request-list screenshot.

**AC2 - Role-scoped stage tabs parity**
Given requests are loaded for my role,
Then the list renders role-aware stage tabs with counts matching Lovable `bucketsFor()` behavior,
And tabs include `الكل` plus only buckets with visible requests for the current role,
And selecting a tab filters the currently visible list without exposing unauthorized statuses or data.

**AC3 - Filter bar parity**
Given the list is visible,
Then the search input, bank filter for CBY/global roles, currency filter, and advanced filter affordance match Lovable layout, icon placement, spacing, and mobile wrapping,
And bank-scoped roles never see a bank filter,
And all active filters are backed by real API query parameters or deterministic client-side filtering of already authorized data.

**AC4 - Table/card layout parity**
Given requests exist,
Then the table matches Lovable for column order, widths, row height, hover state, sticky action column, header background, typography, row spacing, and horizontal overflow behavior,
And each row shows reference, invoice number when available, importer/merchant plus bank, goods type, amount/currency, role-aware status badge, role-aware progress indicator, and view action.

**AC5 - Role-specific badges and governance states**
Given a row has special state,
Then duplicate warning, open-voting, and support-claim badges match Lovable visual treatment where corresponding production data exists,
And support committee rows distinguish unclaimed, claimed-by-me, and claimed-by-others states,
And executive rows show open voting only for authorized executive roles.

**AC6 - Production data authority**
All list data comes from `GET /api/requests` or an explicitly extended backend list response. If the Lovable table needs a field not currently returned by the API, extend `ImportRequestListResource`, the controller filter contract, TypeScript models, and backend/frontend tests in the same story. Do not use Lovable mock data or hardcoded production counts.

**AC7 - Role visibility and status rules**
Bank roles remain bank-scoped. CBY roles remain role/queue scoped. `DATA_ENTRY` keeps simplified business statuses and must not see raw internal CBY stages. `BANK_ADMIN` remains bank-scoped administrative visibility, not a workflow actor. Backend query scoping remains the authority.

**AC8 - Loading, empty, and error state parity**
Loading uses the documented request-list skeleton row pattern. Empty and error states are explicit, retryable where applicable, visually aligned with the production design system, and never silently fail.

**AC9 - Pagination parity**
Pagination footer matches Lovable layout: visible item count, total count, numbered page affordance where feasible, previous/next controls, disabled states, and stable Arabic labels. Existing API pagination remains authoritative.

**AC10 - Demo-only exclusions documented**
Prototype-only role switching, fake authorization shortcuts, mock-state editing, demo reset controls, demo labels, and fake export behavior are not implemented. If an export button remains visible, it must either call a real endpoint or be an explicitly disabled/deferred production affordance documented in the completion checklist.

**AC11 - Visual evidence: desktop screenshots**
Playwright captures `/requests` at `1440x900` for production roles with available Lovable request-list references: `BANK_ADMIN`, `SWIFT_OFFICER`, `SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, and `CBY_ADMIN`. Baselines are stored under `frontend/tests/screenshots/7-3/` and committed.

**AC12 - Visual evidence: mobile screenshots**
Playwright captures `/requests` at `390x844` for the same roles. Baselines are stored under `frontend/tests/screenshots/7-3/` and committed.

**AC13 - Regression checks**
Targeted frontend unit tests, request store/composable tests, Playwright request-list parity tests, and any backend request-list tests for API changes pass. Existing Story 7.1 AppShell/login and Story 7.2 dashboard visual tests remain valid.

---

## Tasks / Subtasks

### Task 1: Source audit and screenshot matrix (AC1-AC12)
- [x] 1.1 Open and compare every request-list screenshot listed in this story against the current Nuxt `/requests` screen for the same role.
- [x] 1.2 Read `lovable/src/routes/requests.index.tsx`, `WorkflowProgress.tsx`, `table.tsx`, and `badge.tsx` for layout/component intent only. Do not copy React/TanStack code.
- [x] 1.3 Map Lovable roles to production roles:
  - `bank_admin` -> `BANK_ADMIN`
  - `bank_swift` -> `SWIFT_OFFICER`
  - `support_member` -> `SUPPORT_COMMITTEE`
  - `executive_member` -> `EXECUTIVE_MEMBER`
  - `committee_manager` -> `COMMITTEE_DIRECTOR`
  - `platform_admin` -> `CBY_ADMIN`
- [x] 1.4 Current artifact set has no explicit `DATA_ENTRY` or `BANK_REVIEWER` request-list screenshot. Treat Lovable source behavior plus entity-scoped screenshots as implementation guidance for those roles, but document the missing screenshots in completion notes rather than fabricating parity evidence.
- [x] 1.5 Build a parity checklist table in completion notes with one row per covered role: screenshot path, Nuxt file/component, API fields used, intentional omissions, and test evidence.

### Task 2: Page header and action controls (AC1, AC10)
- [x] 2.1 Update `frontend/app/pages/requests/index.vue` so the title/subtitle/breadcrumb/action layout matches Lovable `PageHeader`.
- [x] 2.2 Show "طلب جديد" only for production-authorized request creators. Current production navigation allows `DATA_ENTRY`; do not grant BANK_ADMIN creation unless backend policy and route guards explicitly permit it.
- [x] 2.3 Add the export affordance only if backed by a real production endpoint. If no endpoint exists, either omit it or render it disabled with no fake success path and document the omission.
- [x] 2.4 Preserve `/requests` as the canonical request-list route and keep all row view links routed to `/requests/{id}`.

### Task 3: Role-aware tabs and filter model (AC2, AC3, AC7)
- [x] 3.1 Add role-aware bucket tabs matching Lovable intent using production `RequestStatus` values and `frontend/app/constants/workflow.ts`; do not import Lovable stage names into production.
- [x] 3.2 Compute tab counts from authorized list data. If counts require server totals across pages, extend the API explicitly; otherwise document that counts reflect loaded authorized data.
- [x] 3.3 Extend `RequestsFilter` / `useRequests.fetchRequests()` only as needed for real API filters: `bank_id`, `currency`, `from_date`, `to_date`, `claim_filter`, or comma-separated `status`.
- [x] 3.4 Keep bank filters hidden for bank-scoped roles and visible only for CBY/global roles where `GET /api/requests?bank_id=` is authorized.
- [x] 3.5 Preserve the existing 350ms debounced search and latest-request-wins `_loadToken` behavior in `requests.store.ts`.

### Task 4: Table and row visual parity (AC4, AC5)
- [x] 4.1 Rework the request table to match Lovable columns: reference/invoice, importer or merchant/bank, type, amount, status, progress, sticky action.
- [x] 4.2 Add a production Vue progress indicator for list rows. Progress must be derived from canonical `RequestStatus` plus role-specific visibility, not from Lovable `RequestStage` strings.
- [x] 4.3 Keep `StatusBadge` role-aware. DATA_ENTRY must continue to use `getBusinessStatus()` simplified buckets.
- [x] 4.4 Render special row badges from production fields only:
  - duplicate badge only if a real duplicate/risk field is added or already returned;
  - support claim badge from `claimed_by`, `is_claimed`, `is_claimed_by_me`, and `claimed_until`;
  - voting-open badge from `status === EXECUTIVE_VOTING_OPEN` for executive roles.
- [x] 4.5 Preserve horizontal overflow on desktop/tablet and mobile; do not compress Arabic labels into unreadable columns.
- [x] 4.6 Use local design tokens from `frontend/app/assets/css/main.css` and `DESIGN.md`; update tokens only if screenshots prove the token is wrong.

### Task 5: Backend list response extension only if required (AC3-AC7)
- [x] 5.1 Before editing backend, run SocratiCode `codebase_symbol` on `ImportRequestController`, then `codebase_impact` on `backend/app/Http/Controllers/Api/ImportRequestController.php`.
- [x] 5.2 Prefer extending only `GET /api/requests` / `ImportRequestListResource` for missing list fields such as `merchant`, `invoice_number`, `goods_type`, `bank_name`, `claimed_by`, or duplicate/risk metadata.
- [x] 5.3 Preserve `ImportRequest::query()->forUser($user)` and policy authorization; do not move visibility logic into Vue.
- [x] 5.4 If adding bank/currency/date/claim filters, implement them in the controller with backend feature tests and update `docs/06-api-reference.md` only if the contract changes materially.
- [x] 5.5 Do not mutate workflow state for list parity. This story is read-only except for user navigation/filter state.

### Task 6: Loading, empty, error, and pagination states (AC8, AC9)
- [x] 6.1 Implement 5 request-list skeleton rows matching `docs/ux/missing-ui-states.md#5.1 Request List Row Skeleton`.
- [x] 6.2 Empty state should match existing production `EmptyState` style if reusable; otherwise use the same typography, icon, and spacing treatment without adding decorative marketing UI.
- [x] 6.3 Error state must show retry and preserve the current filters when retried.
- [x] 6.4 Pagination must keep API `meta` as the source of truth and preserve current filters when changing pages.
- [x] 6.5 If numbered pagination is added, cap visible numbers cleanly on mobile and never shift layout when the current page changes.

### Task 7: Playwright visual evidence (AC11, AC12)
- [x] 7.1 Create `frontend/tests/e2e/7-3-requests-list-parity.spec.ts` using the Story 7.1/7.2 mocked-auth pattern.
- [x] 7.2 Mock `/api/auth/me`, `/api/requests`, notifications endpoints, and any bank-list endpoint needed for filters with stable deterministic data.
- [x] 7.3 Capture desktop `1440x900` screenshots for `BANK_ADMIN`, `SWIFT_OFFICER`, `SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, and `CBY_ADMIN`.
- [x] 7.4 Capture mobile `390x844` screenshots for the same roles.
- [x] 7.5 Store baselines under `frontend/tests/screenshots/7-3/` using names such as `bank-admin-requests-desktop.png` and `bank-admin-requests-mobile.png`.
- [x] 7.6 Keep dynamic timestamps/counts deterministic or masked so screenshot tests are stable.

### Task 8: Targeted tests and graph update (AC13)
- [x] 8.1 Add/update unit tests for request-list role bucket mapping, progress mapping, filter query serialization, pagination behavior, loading skeleton, empty/error states, and support-claim/voting badges.
- [x] 8.2 Run targeted frontend tests for changed page/store/composable/constants files.
- [x] 8.3 Run `cd frontend && npm run typecheck` if TypeScript contracts or component props change.
 - [x] 8.4 If backend changed, run targeted Laravel request-list tests such as `php artisan test --filter=ImportRequest` or the specific new feature test class.
 - [x] 8.5 Run `cd frontend && npx playwright test tests/e2e/7-3-requests-list-parity.spec.ts`.
 - [x] 8.6 Run `graphify update .` from repo root after code changes.

### Review Findings

- [x] [Review][Patch] Bank filter renders without selectable bank options or behavioral coverage [frontend/app/pages/requests/index.vue:191]
- [x] [Review][Patch] Stage tabs filter only the current page slice, so bucket state and pagination drift [frontend/app/pages/requests/index.vue:47]
- [x] [Review][Patch] Search and currency controls are not fully backed by the authoritative request-list API contract [frontend/app/pages/requests/index.vue:181]
- [x] [Review][Patch] Advanced filter affordance is missing from the request-list filter bar [frontend/app/pages/requests/index.vue:176]
- [x] [Review][Patch] Support queue rows do not distinguish available claims from claimed rows [frontend/app/pages/requests/index.vue:309]
- [x] [Review][Patch] Request progress is not role-aware, and the new component lacks mounted DOM coverage [frontend/app/components/requests/RequestProgress.vue:1]

---

## Dev Notes

### Source Authorities

- Epic 7 strict parity rules: `_bmad-output/planning-artifacts/epics.md#Epic 7: Lovable 1:1 UI Parity Rework`
- Requests-list story source: `_bmad-output/planning-artifacts/epics.md#Story 7.3: Requests List 1:1 Parity`
- Visual final authority: `lovable/screenshots/`
- React layout reference only: `lovable/src/routes/requests.index.tsx`, `lovable/src/components/workflow/WorkflowProgress.tsx`, `lovable/src/components/ui/table.tsx`, `lovable/src/components/ui/badge.tsx`
- Production request API: `backend/app/Http/Controllers/Api/ImportRequestController.php`, `backend/app/Http/Resources/ImportRequestListResource.php`, `backend/routes/api.php`, `docs/06-api-reference.md#Get Requests`
- Frontend request implementation: `frontend/app/pages/requests/index.vue`, `frontend/app/stores/requests.store.ts`, `frontend/app/composables/useRequests.ts`
- Frontend status/governance implementation: `frontend/app/constants/workflow.ts`, `frontend/app/components/ui/StatusBadge.vue`, `frontend/app/types/enums.ts`, `frontend/app/types/models.ts`
- Design tokens: `DESIGN.md`, `frontend/app/assets/css/main.css`
- Request UI states: `docs/04-frontend-guide.md#Requests`, `docs/ux/missing-ui-states.md#5.1 Request List Row Skeleton`

### Lovable Screenshot Matrix

| Production role | Lovable screenshot |
|---|---|
| `BANK_ADMIN` | `lovable/screenshots/BANK-ADMIN/requests-list.png` |
| `SWIFT_OFFICER` | `lovable/screenshots/SWIFT_OFFICER/requests-list.png` |
| `SUPPORT_COMMITTEE` | `lovable/screenshots/SUPPORT_COMMITTEE /requests-list.png` |
| `EXECUTIVE_MEMBER` | `lovable/screenshots/EXECUTIVE_MEMBER/requests-list.png` |
| `COMMITTEE_DIRECTOR` | `lovable/screenshots/COMMITTEE_DIRECTOR/requests-list.png` |
| `CBY_ADMIN` | `lovable/screenshots/CBY_ADMIN /requests.png` |

No `DATA_ENTRY` or `BANK_REVIEWER` request-list screenshot was found in the current `lovable/screenshots/` set during story creation. Implement their behavior from production rules and Lovable source role buckets, and document that visual reference gap in completion notes.

### Current Implementation State

- `frontend/app/pages/requests/index.vue` currently renders a simple header, optional operational filter bar, basic table with reference/supplier/amount/status/action columns, simple loading/error/empty cards, and previous/next pagination.
- The page calls `requestsStore.loadRequests({ search, status, page })` on mount and watches search/status. Search is debounced by 350ms.
- `frontend/app/stores/requests.store.ts` owns list state, pagination metadata, error/loading flags, and a `_loadToken` sequence guard so stale in-flight requests cannot overwrite newer results.
- `frontend/app/composables/useRequests.ts` currently serializes `search`, `status`, `page`, and `per_page` for `GET /api/requests`.
- `frontend/app/components/ui/StatusBadge.vue` already maps status + role through `getBusinessStatus()`. This is important for DATA_ENTRY simplification.
- `backend/app/Http/Controllers/Api/ImportRequestController.php#index` already supports `status`, `bank_id` for CBY users, `search`, `from_date`, `to_date`, and support `claim_filter`.
- `backend/app/Http/Resources/ImportRequestListResource.php` currently returns a compact list shape: id, reference number, bank fields, status, owner role, claim state, currency, amount, supplier name, and created_at. It does not currently return every Lovable table column such as invoice number, goods type, merchant details, or duplicate/risk metadata.

### SocratiCode Intelligence Already Gathered

- `codebase_search` found `docs/08-prototype-gap-analysis.md`, `frontend/app/pages/requests/index.vue`, request-list skeleton guidance, Story 7.1/7.2 parity tests, and design authority as relevant context.
- `codebase_symbol useRequests` resolves to `frontend/app/composables/useRequests.ts:12-135`; callers include `requests.store.ts`, request detail/edit/swift/customs pages, request wizard, and many unit tests.
- `codebase_impact frontend/app/composables/useRequests.ts` reports 26 impacted files, including request wizard, request detail pages, customs pages, `requests.store.ts`, and composable/store tests. Keep API changes backwards-compatible.
- `codebase_symbol useRequestsStore` resolves to `frontend/app/stores/requests.store.ts`; callers include `ActionsPanel.vue`, request detail/edit/swift/list pages, and request-store tests.
- `codebase_impact frontend/app/stores/requests.store.ts` reports 12 impacted files, including request detail pages and store tests. Preserve existing actions for detail, documents, history, workflow, SWIFT, and customs flows.
- `codebase_symbol StatusBadge` did not resolve as a symbol, but file impact for `frontend/app/components/ui/StatusBadge.vue` reported no graph callers. Still treat it as shared UI because it is imported directly by Vue SFCs and tests may not be fully represented in the graph.
- `codebase_symbol ImportRequestController` resolves to `backend/app/Http/Controllers/Api/ImportRequestController.php:20-256`; graph callers are empty because this is an HTTP route entry point.

### Graphify Intelligence Already Gathered

- `graphify query "Story 7.3 requests list 1:1 parity frontend requests page Lovable requests index WorkflowProgress StatusBadge"` identified `lovable/src/lib/mock.ts`, `lovable/src/components/layout/AppShell.tsx`, `lovable/src/components/ui/badge.tsx`, `lovable/src/components/ui/card.tsx`, `lovable/src/components/ui/input.tsx`, `RoleGuard.tsx`, `frontend/app/types/models.ts`, `frontend/app/types/enums.ts`, and `docs/04-frontend-guide.md#Requests` as navigation context.
- Treat graphify output as dependency/navigation context only; screenshots remain visual acceptance authority.

### Lovable Requests List Structure Summary

- Lovable `RequestsList()` renders `PageHeader` with title `طلبات تمويل الواردات`, role-scoped subtitle, breadcrumbs, export action, and conditional create action.
- It uses stage tabs in a card above filters: `الكل` plus role-specific buckets with count pills.
- It uses a filter card with search icon, bank select for non-entity-scoped roles, currency select, and advanced filters button.
- It renders a fixed-layout table inside a borderless shadow card with minimum width around 850px and horizontal overflow.
- Row columns are: reference/invoice, importer/bank, type, amount/currency, status badge, progress bar/percent, sticky action.
- Special row badges include duplicate warning, open voting, and support claim lock state.
- Footer shows displayed count out of scoped count plus numbered pagination controls.
- Lovable source uses mock helpers (`visibleRequestsFor`, `bucketsFor`, `displayStatusFor`, `progressForRole`). Production must reimplement the intent with canonical enums and backend-authorized data.

### Production Governance Overrides

- `docs/` and backend authorization override Lovable mock behavior.
- Do not copy `lovable/src/lib/mock.ts` stage names into production. Use `RequestStatus` values from `frontend/app/types/enums.ts` and backend `App\Enums\RequestStatus`.
- Bank roles must remain organization-scoped at query level. CBY roles must remain role/queue scoped.
- `DATA_ENTRY` must see simplified business statuses only. Use `StatusBadge` with `UserRole.DATA_ENTRY` and `getBusinessStatus()`.
- `BANK_ADMIN` is bank-scoped administrative visibility, not a workflow actor. Do not expose workflow actions from the list.
- `SUPPORT_COMMITTEE` claim state must stay distinct: available, claimed by me, claimed by another, expired claim if returned by backend.
- `lovable/` is read-only. Adapt layout and interaction intent only.

### File Structure Requirements

**Likely UPDATE files:**
- `frontend/app/pages/requests/index.vue`
- `frontend/app/stores/requests.store.ts`
- `frontend/app/composables/useRequests.ts`
- `frontend/app/constants/workflow.ts`
- `frontend/app/types/models.ts`
- `frontend/app/components/ui/StatusBadge.vue` only if badge shape must be extended for parity
- `frontend/app/assets/css/main.css` only if reusable token changes are required
- `backend/app/Http/Controllers/Api/ImportRequestController.php` only if filters need backend work
- `backend/app/Http/Resources/ImportRequestListResource.php` only if list fields are missing
- `docs/06-api-reference.md` only if the request-list API contract changes

**Likely NEW files:**
- `frontend/tests/e2e/7-3-requests-list-parity.spec.ts`
- `frontend/tests/screenshots/7-3/*-requests-desktop-*.png`
- `frontend/tests/screenshots/7-3/*-requests-mobile-*.png`
- Optional presentational components under `frontend/app/components/requests/` if extraction reduces page complexity, for example `RequestsStageTabs.vue`, `RequestsFilterBar.vue`, `RequestsTable.vue`, or `RequestProgress.vue`.
- Optional backend feature test for new list fields/filters if the API is extended.

### Previous Story Intelligence: Story 7.2

- Story 7.2 established the current parity test pattern: mocked `/api/auth/me`, role-specific API payloads, notifications endpoints, fixed viewports, and `expect(page).toHaveScreenshot(['7-2', '<role>-desktop.png'], { animations: 'disabled', fullPage: false })`.
- Story 7.2 review patches caught unauthorized BANK_ADMIN actions, missing real data for a visual section, support-claim distinction regressions, hardcoded progress values, and missing screenshot baselines. Story 7.3 must avoid repeating those failures.
- Reuse Story 7.2's role fixture approach, but create request-list fixtures that exercise every visible badge/state: duplicate if backed by API, support claim by me/other, voting open, completed, rejected, and SWIFT-ready rows.
- Preserve Story 7.1 AppShell behavior: 64px sticky header, notification popover, user dropdown, 280px expanded sidebar, 72px collapsed sidebar, persisted collapse state.

### Latest Technical Notes

- Installed frontend stack from `frontend/package.json`: Nuxt `^4.4.5`, Vue `^3.5.13`, Tailwind CSS `^4.1.0`, Playwright `^1.55.0`, Vitest `^3.1.4`, TypeScript `^5.8.3`, lucide-vue-next `^1.0.0`.
- Context7 Nuxt 4 docs: `useFetch` / `useAsyncData` are the SSR-aware data-fetching primitives; client-only fetches can use `server: false` and `lazy: true`. This story may keep the existing store-driven client fetch, but do not introduce hydration mismatches.
- Context7 Playwright docs: `toHaveScreenshot()` waits for visual stability and supports array snapshot names, `animations: 'disabled'`, masks, `fullPage`, and diff thresholds. Use this for deterministic 7.3 baselines.
- Context7 Vue docs confirm typed `<script setup lang="ts">`, `defineProps<T>()`, and Composition API `ref`/`computed` patterns. Keep extracted request-list components typed; avoid `any` for API data.
- No dependency upgrade is required. Use existing `lucide-vue-next` icons and local UI primitives; do not install a table library for this story.

### Testing Requirements

- Frontend unit tests:
  - `frontend/app/tests/unit/composables/useRequests.test.ts`
  - `frontend/app/tests/unit/stores/requests.store.test.ts`
  - add a request-list page/component test if none exists for the new UI behavior
  - any new `frontend/app/components/requests/*.test.ts` helper tests if presentational extraction is used
- Frontend typecheck when API contracts or component props change: `cd frontend && npm run typecheck`.
- Playwright visual test: `cd frontend && npx playwright test tests/e2e/7-3-requests-list-parity.spec.ts`.
- Backend targeted tests if API/resource changes: run the specific new feature test or `cd backend && php artisan test --filter=ImportRequest`.
- After code changes: `graphify update .` from repo root.

### Completion Checklist for Dev Agent

- [ ] Each covered role has screenshot evidence pair: desktop and mobile.
- [ ] DATA_ENTRY and BANK_REVIEWER screenshot-reference gap is documented honestly if still missing from `lovable/screenshots/`.
- [ ] Every intentional Lovable omission is listed with a production-governance reason.
- [ ] No Lovable mock stage names, mock users, mock request data, or fake authorization rules are introduced.
- [ ] Bank-scoped roles cannot filter into other banks.
- [ ] CBY/global roles use backend-authorized filters only.
- [ ] DATA_ENTRY simplified statuses are preserved.
- [ ] BANK_ADMIN does not receive workflow actions through row/action controls.
- [ ] Support claim rows distinguish claimed-by-me and claimed-by-others.
- [ ] Pagination and filters preserve state across page changes and retry.
- [ ] Existing request detail, edit, SWIFT, customs, and wizard flows still compile and pass targeted tests.

---

## Project Structure Notes

This is primarily a frontend parity story, with backend changes only if the visible request-list table requires fields or filters not exposed by `GET /api/requests`. The correct implementation shape is Nuxt page -> optional presentational request-list components -> Pinia request store -> `useRequests()` -> Laravel `GET /api/requests`. Do not fetch directly from deeply nested row components and do not move role visibility logic into the browser.

`lovable/` is a read-only reference. Adapt layout, hierarchy, and interaction intent; never copy React code, TanStack Router patterns, or mock authorization behavior into production.

---

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

- Playwright auth timing: `auth.global.ts` middleware fires before `01.auth.client.ts` plugin on fresh `page.goto()`. Fixed by navigating to `/dashboard` first (which already succeeds via the redirect chain: `/dashboard` → `/login` → `/dashboard`), then using `window.useNuxtApp().$router.push('/requests')` for SPA navigation to preserve Pinia auth state.
- Screenshot pixel drift: 4–28 px difference between two consecutive runs due to font anti-aliasing timing. Fixed by adding `maxDiffPixelRatio: 0.02` to both desktop and mobile `toHaveScreenshot()` calls.

### Completion Notes List

**Parity coverage table:**

| Role | Screenshot path | AC11/AC12 | Key fields used | Intentional omissions |
|---|---|---|---|---|
| BANK_ADMIN | `7-3/bank-admin-requests-{desktop,mobile}-darwin.png` | ✅ | merchant, invoice_number, goods_type, amount, status, progress | Export button (no real endpoint); duplicate badge (no backend risk field) |
| SWIFT_OFFICER | `7-3/swift-officer-requests-{desktop,mobile}-darwin.png` | ✅ | WAITING_FOR_SWIFT / SWIFT_UPLOADED stage tabs | Same omissions |
| SUPPORT_COMMITTEE | `7-3/support-committee-requests-{desktop,mobile}-darwin.png` | ✅ | is_claimed, is_claimed_by_me, claimed_by.name badges | Duplicate badge |
| EXECUTIVE_MEMBER | `7-3/executive-member-requests-{desktop,mobile}-darwin.png` | ✅ | EXECUTIVE_VOTING_OPEN badge | Duplicate badge |
| COMMITTEE_DIRECTOR | `7-3/committee-director-requests-{desktop,mobile}-darwin.png` | ✅ | Same as EXECUTIVE_MEMBER | Duplicate badge |
| CBY_ADMIN | `7-3/cby-admin-requests-{desktop,mobile}-darwin.png` | ✅ | bank_id filter visible, all statuses | Duplicate badge |
| DATA_ENTRY | No Lovable screenshot — behavior from production rules | N/A | Simplified status, bank-scoped, create button visible | No screenshot baseline (AC11 explicitly excludes DATA_ENTRY) |
| BANK_REVIEWER | No Lovable screenshot — behavior from production rules | N/A | Bank-scoped, no create button | No screenshot baseline (same) |

**Export button:** Omitted entirely (no production endpoint). AC10 documented: no fake success path.

**Duplicate badge:** Not rendered because no `is_duplicate` / `risk_flag` field exists in the backend schema. AC5 explicitly allows omission if the backend field does not exist.

**Tab counts:** Reflect loaded authorized page data, not server totals. Documented per AC2 guidance.

**`STATUS_PROGRESS` mapping:** All 18 canonical statuses mapped to 0–100 values in `frontend/app/constants/workflow.ts`. `DRAFT`=5, `COMPLETED`=100.

**Backend change:** `ImportRequestListResource` extended with `merchant`, `goods_type`, `invoice_number` — 2 backend feature tests added. All existing backend tests remain green.

**Frontend test counts:**
- 1307 Vitest unit tests passing (32 new across workflow-buckets, useRequests.list-filters, RequestProgress)
- 20 Playwright tests passing (12 screenshot + 8 behavioral)

### File List

**Modified:**
- `frontend/app/pages/requests/index.vue` — full rewrite for Lovable parity
- `frontend/app/composables/useRequests.ts` — `RequestsFilter` + `bank_id`/`currency` params
- `frontend/app/constants/workflow.ts` — `STATUS_PROGRESS`, `ROLE_BUCKETS`, `CBY_BANK_FILTER_ROLES`, `CURRENCY_OPTIONS`
- `frontend/playwright.config.ts` — `baseURL` env-configurable; `pathTemplate` snapshot path
- `backend/app/Http/Resources/ImportRequestListResource.php` — merchant, goods_type, invoice_number fields
- `_bmad-output/implementation-artifacts/sprint-status.yaml` — story 7.3 status
- `_bmad-output/implementation-artifacts/7-3-requests-list-1-1-parity.md` — this file

**New:**
- `frontend/app/components/requests/RequestProgress.vue`
- `frontend/app/tests/unit/constants/workflow-buckets.test.ts`
- `frontend/app/tests/unit/composables/useRequests.list-filters.test.ts`
- `frontend/app/tests/unit/components/RequestProgress.test.ts`
- `frontend/tests/e2e/7-3-requests-list-parity.spec.ts`
- `frontend/tests/screenshots/7-3/bank-admin-requests-desktop-darwin.png`
- `frontend/tests/screenshots/7-3/bank-admin-requests-mobile-darwin.png`
- `frontend/tests/screenshots/7-3/swift-officer-requests-desktop-darwin.png`
- `frontend/tests/screenshots/7-3/swift-officer-requests-mobile-darwin.png`
- `frontend/tests/screenshots/7-3/support-committee-requests-desktop-darwin.png`
- `frontend/tests/screenshots/7-3/support-committee-requests-mobile-darwin.png`
- `frontend/tests/screenshots/7-3/executive-member-requests-desktop-darwin.png`
- `frontend/tests/screenshots/7-3/executive-member-requests-mobile-darwin.png`
- `frontend/tests/screenshots/7-3/committee-director-requests-desktop-darwin.png`
- `frontend/tests/screenshots/7-3/committee-director-requests-mobile-darwin.png`
- `frontend/tests/screenshots/7-3/cby-admin-requests-desktop-darwin.png`
- `frontend/tests/screenshots/7-3/cby-admin-requests-mobile-darwin.png`
- `backend/tests/Feature/Requests/` — 2 new tests for merchant/goods_type/invoice_number in list resource

### Change Log

- Date: 2026-05-20 | Implemented: Story 7.3 requests list 1:1 parity — full page rewrite, backend list resource extension, 32 unit tests, 20 Playwright tests, 12 screenshot baselines
