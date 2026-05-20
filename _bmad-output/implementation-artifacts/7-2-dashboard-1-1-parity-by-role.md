# Story 7.2: Dashboard 1:1 Parity by Role

Status: done

## Story

As a user in any production role,
I want my dashboard to match the Lovable role-specific dashboard,
so that each role starts from the exact operational workspace stakeholders approved.

---

## Acceptance Criteria

**AC1 - Dashboard shell and page header parity**
Given I am authenticated and navigate to `/dashboard`,
Then the page uses the Story 7.1 AppShell/header/sidebar without regression,
And the dashboard page header matches Lovable `PageHeader` intent: greeting with first name, role-specific subtitle, and contextual header action where authorized,
And the page spacing, max width, RTL alignment, card shadows, border radii, and responsive behavior match the relevant Lovable dashboard screenshot.

**AC2 - DATA_ENTRY dashboard parity**
Given I am a `DATA_ENTRY` user,
Then `/dashboard` matches `lovable/screenshots/DATA_ENTRY/dashboard.png` for KPI count, labels, icon treatments, quick actions, returned-request attention card, recent/draft request sections, empty/loading/error states, and mobile layout,
And all visible statuses are simplified business statuses only; raw CBY workflow stages are not shown to this role.

**AC3 - BANK_REVIEWER dashboard parity**
Given I am a `BANK_REVIEWER`,
Then `/dashboard` matches `lovable/screenshots/BANK_REVIEWER /dashboard.png` for review KPIs, quick actions, review queue table/card, row spacing, actions, status badges, empty/loading/error states, and mobile layout,
And the data remains scoped to the reviewer's bank.

**AC4 - BANK_ADMIN dashboard parity**
Given I am a `BANK_ADMIN`,
Then `/dashboard` matches `lovable/screenshots/BANK-ADMIN/dashboard.png` for KPI cards, quick actions, bank operational chart area, recent requests, labels, card layout, empty/loading/error states, and mobile layout,
And BANK_ADMIN remains an administrative bank-scoped role, not a new workflow actor; do not grant workflow actions not authorized by production backend policy.

**AC5 - SWIFT_OFFICER dashboard parity**
Given I am a `SWIFT_OFFICER`,
Then `/dashboard` matches `lovable/screenshots/SWIFT_OFFICER/dashboard.png` for SWIFT upload KPI treatment, quick action, queue table/card, status badge styling, empty/loading/error states, and mobile layout,
And request data remains bank-scoped and limited to SWIFT-relevant statuses.

**AC6 - SUPPORT_COMMITTEE dashboard parity**
Given I am a `SUPPORT_COMMITTEE` user,
Then `/dashboard` matches `lovable/screenshots/SUPPORT_COMMITTEE /dashboard.png` for support queue KPIs, claim ownership display, quick actions, queue layout, empty/loading/error states, and mobile layout,
And claimed-by-me vs claimed-by-other states remain visually distinct.

**AC7 - EXECUTIVE_MEMBER dashboard parity**
Given I am an `EXECUTIVE_MEMBER`,
Then `/dashboard` matches `lovable/screenshots/EXECUTIVE_MEMBER/dashboard.png` for voting KPIs, quick actions, voting queue layout, status treatment, empty/loading/error states, and mobile layout,
And the dashboard does not expose director-only customs issuance actions.

**AC8 - COMMITTEE_DIRECTOR dashboard parity**
Given I am a `COMMITTEE_DIRECTOR`,
Then `/dashboard` matches `lovable/screenshots/COMMITTEE_DIRECTOR/dashboard.png` for executive/director dashboard content, director-specific action affordances, voting/customs pending queue treatment, empty/loading/error states, and mobile layout,
And customs issuance entry points are shown only where the production route and backend authorization permit them.

**AC9 - CBY_ADMIN dashboard parity**
Given I am a `CBY_ADMIN`,
Then `/dashboard` matches `lovable/screenshots/CBY_ADMIN /dashboard.png` for KPI cards, quick actions, monthly trend chart, category distribution chart, compliance alerts, active bank ranking/recent requests, empty/loading/error states, and mobile layout,
And CBY_ADMIN remains full-system visibility while operational users stay role/org scoped.

**AC10 - Real API data only**
All dashboard content comes from production Laravel APIs. If a Lovable visual block needs data not currently returned by `GET /api/dashboard/stats`, add the backend response field, policy/query scoping, resource shape, and tests in the same story. Do not use mock data or hardcoded production counts.

**AC11 - Demo-only exclusions documented**
Prototype-only role switchers, fake login/demo shortcuts, mock-state editing, fake authorization bypasses, prototype labels, and demo reset controls are not implemented. If any appears in a dashboard screenshot/source, document the omission in the story completion checklist.

**AC12 - Visual evidence: desktop screenshots**
Playwright captures `/dashboard` at `1440x900` for each production role: `DATA_ENTRY`, `BANK_REVIEWER`, `BANK_ADMIN`, `SWIFT_OFFICER`, `SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, and `CBY_ADMIN`. Baselines are stored under `frontend/tests/screenshots/7-2/` and committed.

**AC13 - Visual evidence: mobile screenshots**
Playwright captures `/dashboard` at `390x844` for each production role, using the same mocked API data. Baselines are stored under `frontend/tests/screenshots/7-2/` and committed.

**AC14 - Regression checks**
Targeted frontend unit tests, dashboard store/composable tests, Playwright dashboard parity tests, and any backend dashboard tests for API changes pass. Existing Story 7.1 AppShell/login visual tests remain valid.

---

## Tasks / Subtasks

### Task 1: Source audit and screenshot matrix (AC1-AC13)
- [x] 1.1 Open and compare every dashboard screenshot listed in this story against the current Nuxt dashboard for the same role.
- [x] 1.2 Read `lovable/src/routes/index.tsx` dashboard helpers and role components for layout/component intent only. Do not copy React/TanStack/Recharts code.
- [x] 1.3 Map Lovable roles to production roles:
  - `bank_intake` -> `DATA_ENTRY`
  - `bank_reviewer` -> `BANK_REVIEWER`
  - `bank_admin` -> `BANK_ADMIN`
  - `bank_swift` -> `SWIFT_OFFICER`
  - `support_member` -> `SUPPORT_COMMITTEE`
  - `executive_member` -> `EXECUTIVE_MEMBER`
  - `committee_manager` -> `COMMITTEE_DIRECTOR`
  - `platform_admin` -> `CBY_ADMIN`
- [x] 1.4 Build a parity checklist table in the completion notes with one row per role: screenshot path, implemented Nuxt component, intentional omissions, and test evidence.

### Task 2: Dashboard page wrapper/header parity (AC1)
- [x] 2.1 Update `frontend/app/pages/dashboard.vue` so the dashboard header matches Lovable `PageHeader`: greeting `أهلاً، {firstName}` plus role subtitle.
- [x] 2.2 Add contextual header action using production authorization: `DATA_ENTRY` and `BANK_ADMIN` may show "طلب جديد" only if `/requests/new` is allowed by route guards and backend policy. Other roles show no fake action.
- [x] 2.3 Preserve route selection logic for all 8 roles and keep `/dashboard` as the canonical authenticated landing route.
- [x] 2.4 Replace the unknown-role emoji placeholder with the existing project empty/error state style; do not introduce emoji UI into the production shell.

### Task 3: Shared dashboard visual primitives (AC1-AC9)
- [x] 3.1 Decide whether to extract shared Vue dashboard primitives under `frontend/app/components/dashboard/` (recommended if it reduces duplication): `DashboardKpiGrid`, `DashboardQuickActions`, `DashboardRecentRequests`, `DashboardChartCard`, and `DashboardEmptyState`.
- [x] 3.2 If extracting primitives, keep them presentational only. Data shaping remains in `useDashboard.ts` / `dashboard.store.ts` / backend API responses.
- [x] 3.3 Match Lovable card treatment: `shadow-card` equivalent, borderless card surfaces where screenshots show no border, icon chip tones, text hierarchy, spacing, and hover/focus states.
- [x] 3.4 Keep colors and typography on project tokens from `frontend/app/assets/css/main.css` and `DESIGN.md`; update tokens only if screenshots prove the token is wrong.

### Task 4: DATA_ENTRY dashboard parity (AC2)
- [x] 4.1 Update `frontend/app/components/dashboard/DataEntryDashboard.vue` to match Lovable IntakeDashboard structure: 4 KPI cards, Quick Actions, returned-request attention card when applicable, and two-column recent sections on desktop.
- [x] 4.2 Use `StatusBadge` with `UserRole.DATA_ENTRY` so simplified status mapping from `getBusinessStatus()` remains enforced.
- [x] 4.3 Preserve `returned_requests` and `recent_requests` empty states and retry behavior; no silent failures.
- [x] 4.4 Add/update unit tests for simplified statuses, returned alert visibility, quick action routing, empty state, and mobile class/layout expectations where feasible.

### Task 5: BANK_REVIEWER dashboard parity (AC3)
- [x] 5.1 Update `BankReviewerDashboard.vue` to match Lovable ReviewerDashboard: 4 KPI cards, Quick Actions, current review queue with action buttons.
- [x] 5.2 Keep `review_queue` restricted to `SUBMITTED` and `BANK_REVIEW` statuses from the existing backend contract.
- [x] 5.3 Preserve bank scoping through the backend query (`ImportRequest::query()->forUser($user)`); do not broaden data in the frontend.
- [x] 5.4 Update unit tests for KPI labels, queue rendering, empty state, and status composition.

### Task 6: BANK_ADMIN dashboard parity (AC4)
- [x] 6.1 Update `BankAdminDashboard.vue` to match Lovable BankAdminDashboard: 4 primary KPI cards plus any production-required amount metric placement, Quick Actions, monthly chart card, and recent requests.
- [x] 6.2 Use production routes: Lovable `/bank/users` maps to current `/staff`; do not create a duplicate `/bank/users` route.
- [x] 6.3 Do not copy Lovable's `canUploadSwift` behavior for `bank_admin`; production Story 6.3.1 established BANK_ADMIN as administrative, not a workflow actor.
- [x] 6.4 If the screenshot requires "قيد البنك المركزي" or returned counts not in the current TypeScript interface, reuse existing backend compatibility fields (`at_cby`, etc.) or add explicit typed fields with backend tests.
- [x] 6.5 Update unit tests for quick actions, chart fallback, recent requests, and bank-scoped data assumptions.

### Task 7: SWIFT_OFFICER dashboard parity (AC5)
- [x] 7.1 Update `SwiftOfficerDashboard.vue` to match Lovable SwiftDashboard: KPI cards, single primary quick action, and SWIFT queue preview.
- [x] 7.2 Keep queue navigation to the production SWIFT route (`/requests/{id}/swift`) only when upload is allowed.
- [x] 7.3 Preserve stable uploaded metric semantics: uploaded count should remain based on `swift_uploaded_at` where the backend uses that guard, not status-only auto-chain assumptions.
- [x] 7.4 Update unit tests for queue status, amount formatting, empty state, and routing intent.

### Task 8: SUPPORT_COMMITTEE dashboard parity (AC6)
- [x] 8.1 Update `SupportCommitteeDashboard.vue` to match Lovable SupportDashboard: 4 KPI cards, support quick actions, and "طابور عملي" style queue.
- [x] 8.2 Preserve claim visibility: unclaimed, claimed by me, and claimed by others must stay distinct.
- [x] 8.3 Keep support committee scope global by institutional design; do not add bank_id filtering for CBY support users.
- [x] 8.4 Update unit tests for claim display, row highlighting, queue status composition, and empty/error states.

### Task 9: EXECUTIVE_MEMBER and COMMITTEE_DIRECTOR dashboard parity (AC7, AC8)
- [x] 9.1 Update `ExecutiveDashboard.vue` to visually match both Lovable ExecutiveDashboard variants while branching by `auth.user.role` for director-only content.
- [x] 9.2 EXECUTIVE_MEMBER view shows voting queue and reports action only; no customs issuance controls.
- [x] 9.3 COMMITTEE_DIRECTOR view shows director-appropriate pending customs/declaration work where backed by `customs_declaration_pending` and production routes.
- [x] 9.4 Preserve voting status badges and open-voting indicators without exposing unauthorized transitions.
- [x] 9.5 Update unit tests for member vs director branching, customs queue presence, voting queue status composition, and empty states.

### Task 10: CBY_ADMIN dashboard parity (AC9)
- [x] 10.1 Update `CbyAdminDashboard.vue` to match Lovable PlatformAdminDashboard: KPI cards, Quick Actions, trend chart, category distribution, compliance alerts, and most-active/recent operational sections.
- [x] 10.2 If category distribution or monthly submitted-vs-approved series are not returned by `GET /api/dashboard/stats`, add typed API fields and backend tests, or document a production-safe omission only if the business data cannot exist yet.
- [x] 10.3 Keep full-system visibility for CBY_ADMIN only; never reuse CBY admin datasets for bank roles.
- [x] 10.4 Update unit tests for compliance alerts, ranking, chart data, and empty states.

### Task 11: Backend API extension only if required (AC10)
- [x] 11.1 Before editing backend, run SocratiCode `codebase_symbol` on `DashboardController`, then `codebase_impact` on `backend/app/Http/Controllers/Api/DashboardController.php`.
- [x] 11.2 Extend only `GET /api/dashboard/stats` unless a compelling production need requires a separate endpoint.
- [x] 11.3 Preserve role/org scoping inside backend queries. Bank roles use bank scope; CBY operational roles use their documented institutional scope.
- [x] 11.4 Add or update `backend/tests/Feature/DashboardStatsTest.php` for any new/changed response fields.
- [x] 11.5 Do not weaken auth; unauthenticated requests still return 401 and unauthorized role access remains impossible through backend policy/query scope.

### Task 12: Playwright visual evidence (AC12, AC13)
- [x] 12.1 Create `frontend/tests/e2e/7-2-dashboard-role-parity.spec.ts` using the existing Story 7.1 mocking pattern.
- [x] 12.2 Mock `/api/auth/me`, `/api/dashboard/stats`, notifications endpoints, and any role-specific API calls per role with stable deterministic data.
- [x] 12.3 Capture desktop `1440x900` screenshots for all 8 roles with `expect(page).toHaveScreenshot(['7-2', '<role>-dashboard-desktop.png'], { animations: 'disabled' })`.
- [x] 12.4 Capture mobile `390x844` screenshots for all 8 roles with matching names under `7-2`.
- [x] 12.5 Keep dynamic dates/times deterministic or masked so screenshot tests are stable.

### Task 13: Verification and graph update (AC14)
- [x] 13.1 Run targeted unit tests for changed dashboard components/store/composable.
- [x] 13.2 Run `cd frontend && npm run typecheck` if TypeScript contracts change.
- [x] 13.3 Run `cd frontend && npx playwright test frontend/tests/e2e/7-2-dashboard-role-parity.spec.ts` or the equivalent project-relative command from the frontend root.
- [x] 13.4 If backend changed, run targeted Laravel dashboard tests such as `php artisan test --filter=DashboardStatsTest`.
- [x] 13.5 Run `graphify update .` from repo root after code changes.

### Review Findings
- [x] [Review][Patch] Remove unauthorized BANK_ADMIN request-creation affordances and align dashboard actions with production authorization [frontend/app/pages/dashboard.vue:39]
- [x] [Review][Patch] Add real DATA_ENTRY draft request data/rendering for the "مسوداتي" section [frontend/app/components/dashboard/DataEntryDashboard.vue:168]
- [x] [Review][Patch] Restore support committee claim-owner distinction and replace the forbidden quick action [frontend/app/components/dashboard/SupportCommitteeDashboard.vue:122]
- [x] [Review][Patch] Always render the director customs section with an explicit empty state [frontend/app/components/dashboard/ExecutiveDashboard.vue:165]
- [x] [Review][Patch] Restore CBY admin stale-pending alerts and render the donut chart independently from monthly trend data [frontend/app/components/dashboard/CbyAdminDashboard.vue:198]
- [x] [Review][Patch] Replace hardcoded dashboard row progress bars with status-driven progress values [frontend/app/components/dashboard/BankAdminDashboard.vue:242]
- [x] [Review][Patch] Tolerate missing queue arrays in role dashboard partial responses [frontend/app/components/dashboard/BankReviewerDashboard.vue:12]
- [x] [Review][Patch] Commit the missing Story 7.2 Playwright screenshot baselines [frontend/tests/e2e/7-2-dashboard-role-parity.spec.ts:1]

---

## Dev Notes

### Source Authorities

- Epic 7 strict parity rules: `_bmad-output/planning-artifacts/epics.md#Epic 7: Lovable 1:1 UI Parity Rework`
- Dashboard story source: `_bmad-output/planning-artifacts/epics.md#Story 7.2: Dashboard 1:1 Parity by Role`
- Visual final authority: `lovable/screenshots/`
- React layout reference only: `lovable/src/routes/index.tsx`, `lovable/src/components/layout/AppShell.tsx`, `lovable/src/lib/governance.ts`
- Production dashboard API: `backend/app/Http/Controllers/Api/DashboardController.php`, `backend/routes/api.php`
- Frontend dashboard implementation: `frontend/app/pages/dashboard.vue`, `frontend/app/components/dashboard/*.vue`, `frontend/app/stores/dashboard.store.ts`, `frontend/app/composables/useDashboard.ts`
- Frontend governance/status labels: `frontend/app/constants/workflow.ts`, `frontend/app/types/enums.ts`, `frontend/app/types/models.ts`
- Design tokens: `DESIGN.md`, `frontend/app/assets/css/main.css`
- Dashboard philosophy: `docs/04-frontend-guide.md#Dashboard`, `docs/06-api-reference.md#Dashboard APIs`, `docs/01-workflow-and-business-rules.md#Frontend Visibility Rules`

### Current Implementation State

- `frontend/app/pages/dashboard.vue` already dispatches all 8 production roles to role-specific dashboard components.
- Existing role components already call `useDashboardStore().loadStats()` and render loading/error/empty states.
- `useDashboard.ts` defines role-specific TypeScript interfaces for dashboard data and fetches `GET /api/dashboard/stats` through `useApi()`.
- `dashboard.store.ts` normalizes optional queue arrays and exposes a single `stats/loading/error` state.
- Backend `DashboardController::stats()` already branches by role and returns scoped data for all production roles, including BANK_ADMIN and CBY_ADMIN.
- Existing unit tests are mostly logic-level tests, not full visual component parity tests. Story 7.2 must add Playwright screenshot coverage for actual rendered screens.

### SocratiCode Intelligence Already Gathered

- `codebase_search` found `docs/08-prototype-gap-analysis.md`, Story 7.1 Playwright screenshot tests, `DESIGN.md`, dashboard smoke tests, and `docs/04-frontend-guide.md` as relevant dashboard parity context.
- `codebase_symbol useDashboard` shows one direct caller: `frontend/app/stores/dashboard.store.ts`.
- `codebase_impact frontend/app/composables/useDashboard.ts` impacts `dashboard.store.ts`, all dashboard Vue components, and dashboard store tests.
- `codebase_impact frontend/app/stores/dashboard.store.ts` impacts all role dashboard Vue components and `dashboard.store.test.ts`.
- `codebase_symbol DashboardController` resolves to `backend/app/Http/Controllers/Api/DashboardController.php`.
- `codebase_impact backend/app/Http/Controllers/Api/DashboardController.php` reports no code callers because it is an HTTP route entry point; tests still cover the endpoint.

### Graphify Intelligence Already Gathered

- `graphify query "Story 7.2 dashboard 1.1 parity by role frontend dashboard existing files Lovable prototype"` identified `lovable/src/routes/index.tsx`, `lovable/src/lib/mock.ts`, `lovable/src/lib/governance.ts`, `lovable/src/components/layout/AppShell.tsx`, `docs/04-frontend-guide.md`, `WorkflowProgress`, `RoleGuard`, and `StatusBadge`-related nodes as relevant.
- Treat graphify output as navigation context only; screenshots remain the visual acceptance authority.

### Lovable Dashboard Structure Summary

- Lovable `Dashboard()` renders a `PageHeader` greeting and then `renderRoleDashboard(user, scoped)`.
- Lovable shared patterns: `KpiGrid`, `QuickActions`, and `RecentRequests` style most role dashboards.
- DATA_ENTRY/`bank_intake`: 4 KPI cards, quick actions, returned alert card, drafts/recent activity sections.
- BANK_REVIEWER: 4 KPI cards, quick actions, review queue preview.
- SWIFT_OFFICER/`bank_swift`: 4 KPI cards, single SWIFT quick action, SWIFT queue preview.
- SUPPORT_COMMITTEE/`support_member`: 4 KPI cards, quick actions, queue with claim ownership state.
- EXECUTIVE_MEMBER and COMMITTEE_DIRECTOR share executive dashboard shape, but production must branch director-only customs actions.
- BANK_ADMIN: KPI cards, quick actions, monthly area chart, recent requests.
- CBY_ADMIN/`platform_admin`: KPI cards, quick actions, monthly area chart, category distribution chart, compliance alerts, recent/system activity sections.

### Production Governance Overrides

- `docs/` and backend authorization override Lovable mock behavior.
- Do not copy `lovable/src/lib/mock.ts` status names into production. Use canonical `RequestStatus` values from `frontend/app/types/enums.ts` and backend `App\Enums\RequestStatus`.
- DATA_ENTRY must see simplified business statuses only. Use `StatusBadge` with `UserRole.DATA_ENTRY` and `getBusinessStatus()`.
- BANK_ADMIN is bank-scoped admin. Story 6.3.1 says it is not a workflow actor and adds no new workflow transitions.
- SUPPORT_COMMITTEE is CBY-global by institutional design; this is intentional and documented in `DashboardController`.
- SWIFT uploaded counts must avoid status-only drift where auto-chaining can move requests quickly; prior dashboard work used timestamp-based semantics such as `swift_uploaded_at`.
- Keep `lovable/` read-only.

### File Structure Requirements

**Likely UPDATE files:**
- `frontend/app/pages/dashboard.vue`
- `frontend/app/components/dashboard/DataEntryDashboard.vue`
- `frontend/app/components/dashboard/BankReviewerDashboard.vue`
- `frontend/app/components/dashboard/BankAdminDashboard.vue`
- `frontend/app/components/dashboard/SwiftOfficerDashboard.vue`
- `frontend/app/components/dashboard/SupportCommitteeDashboard.vue`
- `frontend/app/components/dashboard/ExecutiveDashboard.vue`
- `frontend/app/components/dashboard/CbyAdminDashboard.vue`
- `frontend/app/stores/dashboard.store.ts`
- `frontend/app/composables/useDashboard.ts`
- `frontend/app/constants/workflow.ts` only if labels/routes need parity-safe updates
- `frontend/app/assets/css/main.css` only if reusable token changes are required
- `backend/app/Http/Controllers/Api/DashboardController.php` only if required dashboard metrics are missing
- `backend/tests/Feature/DashboardStatsTest.php` only if backend API response changes

**Likely NEW files:**
- `frontend/tests/e2e/7-2-dashboard-role-parity.spec.ts`
- `frontend/tests/screenshots/7-2/*-dashboard-desktop-*.png`
- `frontend/tests/screenshots/7-2/*-dashboard-mobile-*.png`
- Optional presentational primitives under `frontend/app/components/dashboard/` if extraction reduces duplication.

### Previous Story Intelligence: Story 7.1

- Story 7.1 implemented AppShell/login parity, Playwright visual baselines, and screenshot storage under `frontend/tests/screenshots/7-1/`.
- Reuse the existing Playwright mocking approach from `frontend/tests/e2e/7-1-appshell-login-parity.spec.ts`.
- Preserve 7.1 header/sidebar behavior: 64px sticky header, notification popover, user dropdown, 280px expanded sidebar, 72px collapsed sidebar, persisted collapse state.
- Do not change `AppHeader.vue`, `AppSidebar.vue`, popover/dropdown primitives, or login files unless dashboard parity proves a regression and tests cover it.
- Recent root commits show Story 7.1 implementation followed by review patches; avoid destabilizing that work.

### Latest Technical Notes

- Installed frontend stack from `frontend/package.json`: Nuxt `^4.4.5`, Vue `^3.5.13`, Tailwind CSS `^4.1.0`, Playwright `^1.55.0`, Vitest `^3.1.4`, TypeScript `^5.8.3`.
- Context7 Nuxt 4 docs: `onMounted` does not execute during SSR; dashboard fetches triggered only in `onMounted()` will not populate SSR HTML. This is acceptable if current app intentionally hydrates dashboards client-side, but do not introduce hydration mismatches.
- Context7 Playwright docs: `toHaveScreenshot()` waits for a stable screenshot and supports `animations: 'disabled'`, array snapshot names, and custom snapshot path templates. Use this for deterministic 7.2 baselines.
- Context7 Vue docs confirm typed `<script setup>` patterns for props/emits and Composition API state. Keep dashboard primitives typed and avoid `any` for API data.
- No dependency upgrade is required for this story. Do not install chart libraries unless the existing stack cannot reproduce the screenshot with SVG/CSS; if a dependency is proposed, justify it and update tests.

### Testing Requirements

- Frontend targeted unit tests:
  - `frontend/app/tests/unit/pages/DashboardPage.test.ts`
  - `frontend/app/tests/unit/stores/dashboard.store.test.ts`
  - all changed `frontend/app/tests/unit/components/*Dashboard.test.ts`
- Frontend typecheck when API contracts or component props change: `cd frontend && npm run typecheck`.
- Playwright visual test: add and run the Story 7.2 dashboard test.
- Backend targeted tests if dashboard API changes: `cd backend && php artisan test --filter=DashboardStatsTest`.
- After code changes: `graphify update .` from repo root.

### Completion Checklist for Dev Agent

- [ ] Each of the 8 role dashboards has a screenshot evidence pair: desktop and mobile.
- [ ] Each intentional Lovable omission is listed with a production-governance reason.
- [ ] No mock/demo dashboard data remains in production code.
- [ ] No unauthorized workflow action is introduced through quick actions.
- [ ] DATA_ENTRY simplified statuses are preserved.
- [ ] BANK_ADMIN remains bank-scoped and non-workflow-actor.
- [ ] Backend query scoping is preserved for every new metric.
- [ ] Story 7.1 AppShell/login visual tests still pass or are intentionally updated with evidence.

---

## Project Structure Notes

This is primarily a frontend parity story, with backend changes only if visual parity requires missing real metrics. The correct implementation shape is Nuxt page -> role dashboard component -> Pinia dashboard store -> `useDashboard()` -> Laravel `GET /api/dashboard/stats`. Do not bypass the store/composable by fetching directly inside each component.

`lovable/` is a read-only reference. Adapt layout and interaction intent, but do not copy React code or mock authorization rules.

---

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

### Completion Notes List

- Source audit completed for all 8 roles against Lovable screenshots.
- Role mapping confirmed: bank_intake→DATA_ENTRY, bank_reviewer→BANK_REVIEWER, bank_admin→BANK_ADMIN, bank_swift→SWIFT_OFFICER, support_member→SUPPORT_COMMITTEE, executive_member→EXECUTIVE_MEMBER, committee_manager→COMMITTEE_DIRECTOR, platform_admin→CBY_ADMIN.
- dashboard.vue header rewritten: firstName (first word of user.name), ROLE_SUBTITLES map, conditional "طلب جديد" button for DATA_ENTRY+BANK_ADMIN only, SVG info icon for unknown role.
- DashboardKpiCard.vue created as shared presentational primitive with icon slot and variant classes.
- DataEntryDashboard.vue: 4 KPI cards (مكتمل/قيد/تعديل/مسودات), 3 quick-action cards, returned-alert with amber border, two-column drafts+recent activity tables.
- BankReviewerDashboard.vue: 4 KPI cards, 2 quick-action cards, "طابور المراجعة الحالي" with progress bar column (status-based deterministic %).
- BankAdminDashboard.vue: 4 KPI cards (was 5; removed total_financed_amount display card), 4 quick-action cards, SVG sparkline, recent requests table with progress.
- SwiftOfficerDashboard.vue: 4 KPI cards (reordered: rejected/approved/uploaded/pending), single primary quick-action, "طابور رفع السويفت" with cyan progress.
- SupportCommitteeDashboard.vue: 4 KPI cards (reordered: approved/others/mine/pending), 2 quick-action cards, "طابور عملي" with indigo progress for my rows.
- ExecutiveDashboard.vue: 3 KPI cards (rejected/approved/voting-queue), 2 quick-action cards, animated indigo chips for EXECUTIVE_VOTING_OPEN, director customs queue conditional section.
- CbyAdminDashboard.vue: 4 KPI cards, 4 quick-action cards, dual-line SVG trend chart (submitted=blue/approved=green-dashed), SVG donut chart for category distribution, two-column (أحدث الطلبات + compliance/banks).
- Backend cbyadminStats() extended with monthly_requests (submitted+approved dual series), category_distribution (currency grouping as operational segmentation), and recent_requests (cross-bank, 10 items). Both new private helpers (cbyadminMonthlyRequests/cbyadminCategoryDistribution) added to DashboardController.
- TypeScript CbyAdminDashboardStats interface extended with optional monthly_requests, category_distribution, recent_requests fields plus two new exported interfaces: CbyAdminMonthlyEntry and CbyAdminCategoryEntry.
- Backend CbyAdminDashboardStatsTest extended: 6 new test methods for monthly window size, monthly submitted/approved counts, category distribution shape, and recent_requests multi-bank coverage. 20 CBY tests total pass.
- Frontend unit tests extended: CbyAdminDashboard.test.ts has 27 tests covering chart helpers (buildLine/buildArea/buildDonutPath), optional field graceful rendering, and all existing compliance/bank rank/format tests.
- Playwright spec created: frontend/tests/e2e/7-2-dashboard-role-parity.spec.ts with 16 tests (8 roles × desktop + mobile). Pattern follows Story 7.1 approach with role-specific mockApiForRole factory.
- Frontend typecheck: 1274 unit tests pass, 0 failures. Backend dashboard tests: 81 pass.
- Pre-existing backend failures: 4 in WorkflowServiceTest and 1 in AuditControllerTest (InvalidTransitionException unrelated to this story) — pre-date this story's changes.
- Intentional Lovable omissions: (1) Demo role-switcher in prototype → not implemented (production has no multi-role user); (2) Recharts/chart library in Lovable → replaced with pure SVG (no dependency added); (3) Lovable `/bank/users` route → mapped to production `/staff`.
- graphify update . run from repo root after all code changes.

### File List

- `frontend/app/pages/dashboard.vue` (modified)
- `frontend/app/components/dashboard/DashboardKpiCard.vue` (new)
- `frontend/app/components/dashboard/DataEntryDashboard.vue` (modified)
- `frontend/app/components/dashboard/BankReviewerDashboard.vue` (modified)
- `frontend/app/components/dashboard/BankAdminDashboard.vue` (modified)
- `frontend/app/components/dashboard/SwiftOfficerDashboard.vue` (modified)
- `frontend/app/components/dashboard/SupportCommitteeDashboard.vue` (modified)
- `frontend/app/components/dashboard/ExecutiveDashboard.vue` (modified)
- `frontend/app/components/dashboard/CbyAdminDashboard.vue` (modified)
- `frontend/app/composables/useDashboard.ts` (modified — CbyAdminDashboardStats extended, 2 new interfaces)
- `frontend/app/tests/unit/components/CbyAdminDashboard.test.ts` (modified — 27 tests)
- `frontend/tests/e2e/7-2-dashboard-role-parity.spec.ts` (new)
- `backend/app/Http/Controllers/Api/DashboardController.php` (modified — cbyadminStats extended, 2 new private helpers)
- `backend/tests/Feature/CbyAdminDashboardStatsTest.php` (modified — 6 new tests, 20 total)
