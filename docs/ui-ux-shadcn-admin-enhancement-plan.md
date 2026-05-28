# UI/UX Enhancement Plan From shadcn-admin Deep Dive

## Purpose

Use `shadcn-admin/` as an interaction and organization reference for improving Yemen Flow Hub's Nuxt 4 + shadcn-vue frontend. The goal is not to clone the template. The goal is to extract the admin-product craft that fits Yemen Flow Hub: app shell structure, topbar behavior, role-safe navigation, table ergonomics, dashboard composition, settings/preferences patterns, and consistent feedback states.

Yemen Flow Hub remains RTL-first, Arabic-first, government banking focused, role-scoped, queue-first, and audit-sensitive. The template is LTR, React/Next, SaaS-flavored, and visually more decorative than this product. Every adopted pattern must be translated through `docs/user-view/*.md`, root `DESIGN.md`, `frontend/DESIGN.md`, and `frontend/SHADCN.md`.

## Evidence Captured

The template was run locally on `http://localhost:3031` and inspected with `playwright-cli`. Protected pages required a temporary visual-only auth bypass because the template expects Better Auth and a real database. That bypass is not product behavior and must never be copied.

Initial screenshot set:

- `/private/tmp/yfh-shadcn-admin-shots/01-dashboard.png`
- `/private/tmp/yfh-shadcn-admin-shots/02-dashboard2.png`
- `/private/tmp/yfh-shadcn-admin-shots/03-tasks.png`
- `/private/tmp/yfh-shadcn-admin-shots/04-users.png`
- `/private/tmp/yfh-shadcn-admin-shots/05-payment-transactions.png`
- `/private/tmp/yfh-shadcn-admin-shots/06-sign-in.png`
- `/private/tmp/yfh-shadcn-admin-shots/07-forbidden.png`

Deep-dive screenshot set:

- `/private/tmp/yfh-shadcn-admin-deep-shots/08-payment-dashboard.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/09-mail.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/10-chats.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/11-calendar.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/12-kanban.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/13-ai-chat.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/14-help-center.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/15-settings-profile.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/16-sign-in-1.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/17-sign-in-2.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/18-sign-up-2.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/19-unauthorized.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/20-internal-server-error.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/21-theme-customizer-theme-tab.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/22-theme-customizer-layout-tab.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/23-command-search.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/24-users-role-filter-open.png`
- `/private/tmp/yfh-shadcn-admin-deep-shots/26-tasks-status-filter-open.png`

Primary template source files inspected:

- `shadcn-admin/config/sidebar.ts`
- `shadcn-admin/components/layout/app-sidebar.tsx`
- `shadcn-admin/components/layout/dashboard-header.tsx`
- `shadcn-admin/components/layout/nav-group.tsx`
- `shadcn-admin/components/layout/command-search.tsx`
- `shadcn-admin/components/ui/sidebar.tsx`
- `shadcn-admin/contexts/sidebar-context.tsx`
- `shadcn-admin/components/shared/theme-toggle.tsx`
- `shadcn-admin/components/shared/theme-customizer/index.tsx`
- `shadcn-admin/components/shared/theme-customizer/theme-tab.tsx`
- `shadcn-admin/components/shared/theme-customizer/layout-tab.tsx`
- `shadcn-admin/hooks/use-theme-manager.ts`
- `shadcn-admin/hooks/search-params.ts`
- `shadcn-admin/features/tasks/components/data-table.tsx`
- `shadcn-admin/features/tasks/components/data-table-toolbar.tsx`
- `shadcn-admin/features/tasks/components/data-table-filtered.tsx`
- `shadcn-admin/features/tasks/components/data-table-view-options.tsx`
- `shadcn-admin/features/users/components/user-data-table.tsx`
- `shadcn-admin/features/payment-transactions/components/transactions-table.tsx`
- `shadcn-admin/features/dashboard/components/selection-cards.tsx`
- `shadcn-admin/features/dashboard/components/chart-area-interactive.tsx`
- `shadcn-admin/components/ui/chart.tsx`

Current Yemen Flow Hub frontend files to target first:

- `frontend/app/components/layout/AppShell.vue`
- `frontend/app/components/layout/PageHeader.vue`
- `frontend/app/components/AppSidebar.vue`
- `frontend/app/components/NavUser.vue`
- `frontend/app/components/requests/RequestsDataTable.vue`
- `frontend/app/components/dashboard/DashboardKpiCard.vue`
- `frontend/app/components/dashboard/*Dashboard.vue`
- `frontend/app/pages/dashboard.vue`
- `frontend/app/pages/login.vue`
- `frontend/app/pages/requests/index.vue`
- `frontend/app/pages/settings.vue`

## Template Architecture Findings

### Route Inventory

The template is organized by feature routes rather than one large admin page. Important route groups:

- Auth: `sign-in`, `sign-in-1`, `sign-in-2`, `sign-up-1`, `sign-up-2`, `reset-password-1`, `reset-password-2`.
- Dashboards: `dashboard`, `dashboard2`, `payment-dashboard`.
- Work surfaces: `tasks`, `users`, `payment-transactions`, `mail`, `chats`, `calendar`, `kanban`, `ai-chat`, `help-center`, `settings`.
- Error states: `unauthorized`, `forbidden`, `not-found`, `internal-server-error`, `maintenance-error`.

Yemen Flow Hub should keep this feature-folder mindset. It should not add literal mail/chat/calendar/AI features unless a business requirement exists. Those pages are useful as interaction references for notifications, support review claims, voting sessions, activity feeds, help content, and settings IA.

### Sidebar

Template pattern:

- Navigation data lives in `config/sidebar.ts` as grouped route metadata.
- `AppSidebar` consumes the data and renders teams, nav groups, nested entries, active states, and footer user controls.
- `SidebarProvider` stores collapsed state in a cookie.
- `components/ui/sidebar.tsx` supports `side="left" | "right"`, variants `sidebar | floating | inset`, and collapse modes `offcanvas | icon | none`.
- Mobile uses a sheet-style sidebar.
- Keyboard shortcut toggles the sidebar.

Yemen Flow translation:

- Keep `NAV_ITEMS` role-scoped in Vue and drive rendering from role permissions.
- Move sidebar to the right by default and use logical CSS: `ms`, `me`, `ps`, `pe`, `border-s`, `border-e`.
- Adopt the template's grouped and nested IA only where it reduces operational scanning cost.
- Add count badges only from real work queues: pending review, active support claims, voting sessions, unread notifications.
- Do not render forbidden routes. Filtering must happen before component render, not with CSS hiding.

### Topbar And Command Search

Template pattern:

- `dashboard-header.tsx` is sticky and includes sidebar trigger, command search, theme controls, theme customizer, and profile menu.
- `command-search.tsx` opens with a compact search affordance and keyboard shortcut.
- Command entries are grouped by app sections and navigate directly to routes.

Yemen Flow translation:

- Create a true global topbar in `AppShell.vue`, or split `PageHeader.vue` into global shell controls plus page-level controls.
- Command palette should be dynamic and role-scoped, built from allowed routes plus operational quick actions.
- Search groups should be Yemen Flow concepts, not template app labels: requests, workflow queues, entities, staff, audit, reports, settings, help.
- Shortcuts must not expose forbidden routes through search results.
- Arabic labels are primary; English aliases can support search if required.

### Theme Toggle And Settings

Template pattern:

- `ThemeToggle` offers light, dark, system.
- `ThemeCustomizer` opens in a right-side sheet and allows preset changes, radius changes, theme import, brand colors, advanced customization, sidebar variant, collapse mode, and sidebar side.
- Layout controls show that sidebar position and collapse behavior are runtime-configurable.

Yemen Flow translation:

- Do not port arbitrary theme customization to production users. This product has institutional branding and regulated UX consistency.
- Keep safe preferences only: light/dark/system if already supported, density, high contrast, reduced motion, sidebar collapsed state.
- Sidebar side should not be user-configurable in production. RTL default is right.
- Theme import, custom colors, random palettes, and template presets should be rejected.
- The layout tab is useful as a design QA model: verify right sidebar, inset shell, icon collapse, mobile sheet.

### Dashboard Cards And Charts

Template pattern:

- Dashboards combine KPI cards, selection cards, date-range controls, chart sections, and detailed tables.
- `dashboard2` and `payment-dashboard` show multiple card rhythms and chart/table combinations.
- Recharts is wrapped by `components/ui/chart.tsx`.

Yemen Flow translation:

- Adopt the rhythm, not the metrics. Do not import revenue, sales, payment gateway, customer, subscription, or conversion language.
- Keep queue-first dashboards for operational roles.
- Use chart-heavy views only where role docs support oversight: `BANK_ADMIN` and `CBY_ADMIN` first.
- Use existing Vue chart stack and current components, not React/Recharts.
- KPI cards should share a single contract: label, value, icon, semantic state, optional trend, optional drilldown, optional SLA hint.

### Data Tables And Filters

Template pattern:

- Tables use a predictable system: search, faceted filters, selected count, reset filters, view options, pagination, row actions.
- `data-table-filtered.tsx` uses a popover with command search, checkbox options, facet counts, selected badges, and clear action.
- `search-params.ts` centralizes URL query-state parsing and defaulting.
- User and transaction tables use dropdown filters and column controls for dense operational browsing.

Yemen Flow translation:

- Strengthen `RequestsDataTable.vue` first, then reuse the table shell for staff, entities, merchants, audit, reports, workflow docs.
- Keep filters URL-synced for shareable operational views.
- Translate faceted filters to shadcn-vue popovers and commands.
- Columns must be role-aware at data-model level. Forbidden columns must not be rendered or exported.
- Use project semantic tokens for status badges and severity filters.

### Work Surfaces

Template pattern:

- `mail`, `chats`, `calendar`, `kanban`, `help-center`, and `settings` are well-composed task surfaces with side panels, lists, detail panes, and empty states.

Yemen Flow translation:

- Mail pattern maps to notifications and audit/event inboxes.
- Chat pattern maps to internal request notes only if business rules allow it. Do not introduce chat as a feature by visual imitation.
- Calendar pattern maps to voting session windows, SLA due dates, and scheduled reports only if needed.
- Kanban pattern maps to workflow stage overview, but not as the primary request queue. Status workflow is regulated and must stay canonical.
- Help center pattern maps to role-specific help and SOP guidance.
- Settings pattern maps to profile, preferences, security, and admin-controlled platform settings.

## Adaptation Matrix

| Template pattern | Template source | Keep | Adapt or reject | Yemen Flow target |
| --- | --- | --- | --- | --- |
| Right/left capable sidebar | `components/ui/sidebar.tsx`, `contexts/sidebar-context.tsx` | Collapse modes, mobile sheet, keyboard toggle | Default to right side, remove user side switch in production | `AppShell.vue`, `AppSidebar.vue` |
| Grouped sidebar config | `config/sidebar.ts` | Data-driven groups and nested items | Replace LTR icons/copy and role-agnostic routes | Role-filtered `NAV_ITEMS` |
| Sticky dashboard header | `dashboard-header.tsx` | Global utility row, compact controls | Split global shell controls from page actions | `AppShell.vue`, `PageHeader.vue` |
| Command palette | `command-search.tsx` | Shortcut, grouped command results, fast navigation | Build from permissions and allowed routes only | New `CommandPalette.vue` |
| Theme toggle | `theme-toggle.tsx` | Light/dark/system affordance if supported | Ensure token-safe styling | Settings/topbar preference control |
| Theme customizer | `theme-customizer/*` | Layout QA ideas and preference sheet structure | Reject arbitrary colors, imports, preset mutation | Safe Preferences panel only |
| Faceted table filters | `tasks/data-table-filtered.tsx` | Popover search, checkbox facets, counts, active chips | Use Arabic labels, canonical enums, URL query state | `RequestsDataTable.vue`, reusable table toolbar |
| Column visibility | `data-table-view-options.tsx` | User-controlled density for allowed columns | Forbidden columns cannot be toggled into view | Reusable column menu |
| URL query params | `hooks/search-params.ts` | Central filter schema and defaults | Implement with Nuxt route/query composable | `useTableQueryState` composable |
| KPI/card rhythm | dashboard feature components | Consistent hierarchy and spacing | Replace metrics and colors with workflow semantics | Dashboard components |
| Chart wrappers | `components/ui/chart.tsx` | Legend, tooltip, color mapping concepts | Do not port Recharts | Existing Vue chart components |
| Auth layouts | auth routes | Clear two-column form variants | Remove social proof and consumer SaaS copy | `login.vue`, future reset/MFA pages |
| Error pages | error routes | Focused error layout and recovery actions | Arabic institutional copy and role-safe navigation | Error pages/middleware handling |
| Help center | `help-center` | Searchable SOP/help IA | Role-specific content, no generic FAQ filler | Help/SOP pages |

## Implementation Checklist

### Phase 0: Baseline, Governance, And Evidence

- [ ] Capture current Yemen Flow Hub screenshots for `/login`, `/dashboard`, `/requests`, `/requests/new`, `/settings`, error states, and all role dashboard variants.
- [ ] Create a route inventory for the current frontend: shell, sidebar, page headers, dashboards, tables, forms, dialogs, errors, settings.
- [ ] Map every proposed UI change to one source of truth: `docs/user-view/*.md`, root `DESIGN.md`, `frontend/DESIGN.md`, or `frontend/SHADCN.md`.
- [ ] Confirm all changes use shadcn-vue components where applicable.
- [ ] Confirm no work touches `lovable/`.
- [ ] Confirm generated artifacts remain local-only: `graphify-out/`, `_bmad-output/implementation-artifacts/`, `_bmad-output/test-artifacts/`.

### Phase 1: Shell, Sidebar, And Topbar

- [ ] Refactor `AppShell.vue` to own global shell controls instead of scattering them across pages.
- [ ] Add or formalize a `GlobalTopbar.vue` with sidebar trigger, command palette trigger, notification trigger, preferences/theme trigger, and user menu.
- [ ] Keep page-specific title, breadcrumbs, and primary actions in `PageHeader.vue`.
- [ ] Keep sidebar right-aligned by default and verify expanded, collapsed, mobile sheet, and keyboard toggle states.
- [ ] Improve active nav styling with project blue and full pill or contained active state from `DESIGN.md`, not template purple.
- [ ] Add nested sidebar groups for admin-only areas only if role docs justify them.
- [ ] Add operational nav badges from real API counts only.
- [ ] Add tests that forbidden nav entries are not mounted for each role.

### Phase 2: Role-Scoped Command Palette

- [ ] Build `CommandPalette.vue` with shadcn-vue `Command` and `Dialog` patterns.
- [ ] Source command entries from the same role-filtered nav and permission rules used by the sidebar.
- [ ] Add groups: الطلبات, الطوابير, الجهات, الموظفون, التدقيق, التقارير, الإعدادات, المساعدة.
- [ ] Add quick actions only when the active role and status allow them, for example new request for `DATA_ENTRY`.
- [ ] Support keyboard shortcut without conflicting with browser or Arabic input behavior.
- [ ] Add tests proving forbidden routes/actions do not appear in command search.

### Phase 3: Safe Preferences, Not Theme Mutation

- [ ] Keep or add light/dark/system only if current token implementation supports it cleanly.
- [ ] Add density preference: comfortable and compact.
- [ ] Add high-contrast preference if it can be done through existing semantic tokens.
- [ ] Add reduced-motion preference and respect OS setting.
- [ ] Persist sidebar collapsed state and density per user if backend support exists, otherwise use local storage as temporary frontend state.
- [ ] Do not expose theme imports, custom brand colors, radius sliders, random palette presets, or sidebar-side switching in production.
- [ ] Consider a dev-only design QA route for inspecting theme/layout states if the team needs it.

### Phase 4: Page Header System

- [ ] Standardize `PageHeader.vue` props/slots: title, subtitle, breadcrumbs, primary action, secondary actions, toolbar, status summary, last updated.
- [ ] Remove duplicate greeting/header blocks inside role dashboard subcomponents.
- [ ] Add consistent refresh, export, date range, and bank/entity filters where role docs allow them.
- [ ] Verify RTL alignment: primary actions should sit where Arabic scanning expects, and icon order must mirror.
- [ ] Add compact behavior for `600px` width without hiding important workflow actions.

### Phase 5: Dashboard Composition

- [ ] Define a shared `DashboardKpiCard` contract: icon, label, value, semantic state, trend, SLA hint, drilldown route, loading state.
- [ ] Define an `ActionRequiredStrip` component for urgent role work.
- [ ] Define a `DashboardSection` component for consistent headings, helper text, and actions.
- [ ] Keep operational roles queue-first: `DATA_ENTRY`, `BANK_REVIEWER`, `SUPPORT_COMMITTEE`, `SWIFT_OFFICER`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`.
- [ ] Add chart-backed sections first to `BANK_ADMIN` and `CBY_ADMIN`, where oversight and analytics are role-appropriate.
- [ ] Use existing Vue chart components and project tokens.
- [ ] Avoid generic SaaS cards, vanity metrics, gradients, and revenue language.

Role dashboard checklist:

- [ ] `DATA_ENTRY`: drafts, returned corrections, submitted requests, new request CTA.
- [ ] `BANK_REVIEWER`: review queue, segregation-of-duties blocked state, returned/rejected follow-up.
- [ ] `BANK_ADMIN`: bank portfolio health, staff/merchant admin tasks, bank-scoped oversight.
- [ ] `SUPPORT_COMMITTEE`: unclaimed queue, claimed by me, claimed by others, heartbeat and TTL visibility.
- [ ] `SWIFT_OFFICER`: pending SWIFT upload queue, two-document completion gate, upload errors.
- [ ] `EXECUTIVE_MEMBER`: assigned sessions, voted sessions, pending vote age.
- [ ] `COMMITTEE_DIRECTOR`: open/close voting, tie resolution, final decision, FX confirmation pending.
- [ ] `CBY_ADMIN`: governance, SLA, bank risk, audit anomalies, platform health.

### Phase 6: Table System And Query-State Filters

- [ ] Create a reusable table toolbar inspired by the template: search, faceted filters, active filter chips, reset, column visibility, export, selected count.
- [ ] Create a Nuxt query-state composable for table filters and pagination.
- [ ] Start with `RequestsDataTable.vue` and `/requests` because it is the core operational queue.
- [ ] Translate template faceted filters into shadcn-vue `Popover`, `Command`, `Checkbox`, `Badge`, and `Button` patterns.
- [ ] Add canonical enum filters: role, status, organization, category, date range, claim state, voting state, document state as applicable.
- [ ] Add column visibility for allowed columns only.
- [ ] Ensure exports respect the same role-visible column model.
- [ ] Add keyboard focus checks for search, filters, row actions, pagination, and column menu.

Priority table surfaces:

- [ ] `RequestsDataTable.vue`
- [ ] `/requests`
- [ ] `/merchants`
- [ ] `/staff`
- [ ] `/admin/cby-staff`
- [ ] `/admin/entities`
- [ ] `/admin/workflow-docs`
- [ ] `/audit`
- [ ] `/reports`

### Phase 7: Workflow Forms, Dialogs, And Action Surfaces

- [ ] Refine `RequestWizard` with stronger step hierarchy, completed/active/locked/error states, and document requirement clarity.
- [ ] Use `AlertDialog` for irreversible workflow actions: bank rejection, support rejection, vote closure, final decision, external FX completion.
- [ ] Prefer inline panels for correction reasons, review notes, and document guidance where interruption is not required.
- [ ] Show disabled action reasons only when the action is role-appropriate but temporarily unavailable.
- [ ] Do not render role-forbidden actions.
- [ ] Validate all workflow action surfaces against canonical role and status enums.

### Phase 8: Auth, Error, Empty, Loading, And Help

- [ ] Redesign `/login` using template layout clarity, but institutionalize it: CBY identity, formal Arabic copy, security posture, rate-limit/lockout help.
- [ ] Remove social-login, social-proof, marketing claims, gradients, and playful illustrations.
- [ ] Add or refine reset password and future MFA screens with the same form rhythm.
- [ ] Standardize `unauthorized`, `forbidden`, `not found`, `server error`, and `maintenance` pages with Arabic copy and clear next actions.
- [ ] Use shadcn-vue `Skeleton` for loading states.
- [ ] Use shadcn-vue `Alert` for inline errors.
- [ ] Make empty states operational: queue clear, no matching filters, no assigned sessions, no active claims.
- [ ] Build help/SOP pages from real role guidance, not generic FAQ text.

### Phase 9: RTL, Responsive, Accessibility, And Visual QA

- [ ] Audit changed files for physical directions: `ml`, `mr`, `pl`, `pr`, `left`, `right`, `border-l`, `border-r`.
- [ ] Replace with logical spacing and border utilities where possible.
- [ ] Verify dropdown, popover, dialog, tooltip, table overflow, and mobile sheet alignment in RTL.
- [ ] Test desktop `1440x1000`, desktop `1280x900`, tablet-width, and compact `600px` states.
- [ ] Confirm mobile menus do not expose forbidden controls.
- [ ] Validate color contrast for status badges, active nav, focus rings, and action strips.
- [ ] Add Playwright visual checks for shell, sidebar, topbar, command palette, requests table, login, error pages, and one dashboard per role.
- [ ] Add keyboard checks for command palette, sidebar toggle, filters, row action menus, dialogs, and pagination.
- [ ] Run `npm run typecheck` and targeted Vitest/Playwright suites after each implementation phase.

## Do Not Port

- [ ] Do not port React, Next, Better Auth, Recharts, or template-specific providers.
- [ ] Do not port LTR layout assumptions.
- [ ] Do not port purple gradients, glass effects, decorative chrome, or SaaS hero metrics.
- [ ] Do not port payment/revenue/customer/subscription language.
- [ ] Do not port theme import, random theme presets, custom brand color editing, or radius sliders as user-facing features.
- [ ] Do not port pricing, AI chat, mail, calendar, or kanban as literal product features without a Yemen Flow requirement.
- [ ] Do not replace shadcn-vue components with raw HTML to make tests pass.
- [ ] Do not add role-inappropriate controls and rely on backend rejection later.

## Suggested Delivery Order

1. Shell and sidebar foundation: right-side sidebar, global topbar, profile/utilities, role-safe nav.
2. Command palette: role-scoped navigation and safe quick actions.
3. Requests table toolbar: URL filters, facets, active chips, column visibility, selected count.
4. Dashboard component system: KPI cards, action strips, section wrappers, role-specific refinements.
5. Workflow forms and dialogs: request wizard, action confirmations, review notes, upload surfaces.
6. Auth/error/empty/loading/help polish.
7. Safe preferences: density, contrast, reduced motion, sidebar state.
8. RTL/responsive/accessibility hardening and Playwright visual baselines.

## Acceptance Checklist

- [ ] All adapted patterns are RTL-native and Arabic-first.
- [ ] Sidebar is right-aligned, collapsible, role-scoped, and free of forbidden nav entries.
- [ ] Global topbar and page header have clear separate responsibilities.
- [ ] Command palette never reveals forbidden routes or actions.
- [ ] Theme controls preserve Yemen Flow branding and tokens.
- [ ] Every dashboard starts from the role's operational work, not generic analytics.
- [ ] Charts appear only where role docs support analytics or oversight.
- [ ] Tables support search, URL-synced filters, faceted filters, pagination, allowed column visibility, selected count, and keyboard navigation.
- [ ] Forbidden columns and actions are absent from render and export models.
- [ ] Statuses and badges use canonical enums and semantic tokens.
- [ ] Loading, empty, error, forbidden, locked, and maintenance states are consistent.
- [ ] No template purple gradients, social proof, SaaS metrics, or arbitrary theme mutation remain.
- [ ] Playwright before/after screenshots exist for every implemented phase.
- [ ] Typecheck and targeted tests pass for changed areas.
