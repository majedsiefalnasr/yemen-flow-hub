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
| Settings sub-nav (responsive) | `app/(dashboard)/settings/layout.tsx` | 3-mode nav: aside list (lg), tabs (md), select (sm) | Mirror to RTL, use canonical settings tabs | `settings.vue` layout |
| ContentSection wrapper | `components/shared/content-section.tsx` | Title + desc + separator + scroll body shell | Arabic copy, `max-w` for form readability | Settings section wrapper |
| Faded-bottom scroll | `content-section.tsx` (`faded-bottom`) | Gradient fade hint on long scroll areas | Keep token-safe (no decorative color) | Scrollable panels |
| Export CSV/JSON | `lib/export-data.ts` | Column-projected client export with quoting | Restrict to role-visible columns, Arabic headers, BOM for Excel Arabic | `useTableExport` composable |
| Sortable column header | `tasks/data-table-column-header.tsx` | Asc/Desc/Hide dropdown on header | Mirror icons; flip `-ml-3` to `-me-3` | Reusable `DataTableColumnHeader.vue` |
| Row action menu | `tasks/data-table-row-actions.tsx` | Dropdown with sub-menu, shortcuts, destructive | Role-gate each item; destructive via AlertDialog | `DataTableRowActions.vue` |
| Selected-count footer | `tasks/data-table-pagination.tsx` | "N of M row(s) selected" + page size + pager | Arabic numerals/copy; mirror pager chevrons | Pagination component |
| Logout confirm dialog | `nav-user.tsx` (`useLogout`) | AlertDialog gate before sign-out | Arabic copy; keep audit/session semantics | `NavUser.vue` |
| Password requirements | `components/shared/password-requirements.tsx` | Live check/X list for password rules | Match backend rule set; Arabic labels | Reset/MFA password fields |
| Coming soon placeholder | `components/shared/coming-soon.tsx` | Centered card for unbuilt routes | Strip gradient; use for not-yet-built admin pages | `ComingSoon.vue` (dev only) |
| `/` focus-search shortcut | `hooks/use-keyboard-shortcuts.ts` | `/` focuses search, `Esc` clears+blur | Guard against Arabic IME; skip when typing | `useTableKeyboard` composable |
| Debounced search | `hooks/use-debounced-callback.ts` | 300ms debounce synced to external value | Use VueUse `useDebounceFn` + `refDebounced` | Table search inputs |

## Deep Dive Findings

This section records concrete, file-level patterns extracted from a full read of the template source. Each finding includes the exact contract worth translating and a Vue/Nuxt note. Code is illustrative of the source only — do not port it verbatim.

### 1. App Shell Composition (definitive)

`app/(dashboard)/layout.tsx` is the authoritative shell. The nesting order is load-bearing:

```
SidebarConfigProvider          ← layout config (variant/collapsible/side)
  SidebarProvider              ← open/collapsed state + cookie + Cmd/Ctrl+B + mobile
    AppSidebar                 ← rail nav (sibling, not child, of inset)
    SidebarInset               ← the main column
      DashboardHeader          ← sticky topbar (wrapped in <Suspense> for search params)
      <div p-4 pt-0>{children} ← page content
```

Key insight: `AppSidebar` and `SidebarInset` are **siblings** inside `SidebarProvider`, not parent/child. `SidebarInset` is the content shell that gets the rounded/inset treatment when `variant="inset"`. The header lives *inside* `SidebarInset`, so it scrolls within the inset frame, not above the whole viewport.

`AppSidebar` itself (`components/layout/app-sidebar.tsx`) is thin: it reads `useAuth()` for the user, `useSidebarConfig()` for variant/collapsible/side, and renders three regions — `SidebarHeader` (TeamSwitcher), `SidebarContent` (mapped `NavGroup`s), `SidebarFooter` (NavUser) — plus a `SidebarRail` (the thin drag-to-resize/click-to-toggle strip).

Two distinct state systems exist and must not be conflated:
- `SidebarConfigProvider` (`contexts/sidebar-context.tsx`): static layout config — `variant`, `collapsible`, `side`. Defaults to `{ variant: "inset", collapsible: "icon", side: "left" }`. Plain `useState`, no persistence.
- `SidebarProvider` (in `components/ui/sidebar.tsx`): runtime open/closed + mobile sheet state, persisted to a cookie, toggled by Cmd/Ctrl+B.

**Vue note:** YFH already shipped GlobalTopbar + RTL inset sidebar (commit b76b9aa4). Confirm the sibling relationship holds in `AppShell.vue` (sidebar and inset content are siblings under a provider), that the topbar is inside the inset column, and that layout-config (side=right fixed) is separated from runtime open-state (persisted). Do not merge them into one store.

### 2. Navigation Data Model (`config/sidebar.ts` + `lib/types.ts`)

The nav contract is a precise discriminated union:

```ts
BaseNavItem  = { title; badge?; badgeColor?: "violet" | "green"; icon? }
NavLink      = BaseNavItem & { url; items?: never }          // leaf
NavCollapsible = BaseNavItem & { items: {title;url;icon?}[]; url?: never }  // group
NavGroup     = { title; items: NavItem[] }                   // labeled section
SidebarData  = { user?; teams: Team[]; navGroups: NavGroup[] }
```

`NavGroup` (`components/layout/nav-group.tsx`) branches per item:
- No `items` → `SidebarMenuLink` (leaf).
- Has `items` AND sidebar collapsed → `SidebarMenuCollapsedDropdown` (flyout dropdown, `side="right"`).
- Has `items` AND expanded → `SidebarMenuCollapsible` (inline `Collapsible`, auto-`defaultOpen` if a child is active).

Active detection — `checkIsActive(href, item, mainNav)` — matches on: exact url, url ignoring query string, any child url match, OR (for top-level) first path segment match. This last rule keeps a parent highlighted on detail/sub routes.

`NavBadge` supports only two semantic colors (`violet` default, `green`). Badges render `ml-auto` (must become `ms-auto` in RTL). The active-leaf indicator is an absolutely-positioned `left-0 ... rounded-r-full` bar with a violet→fuchsia gradient.

**Revised stance on the gradient active bar:** The violet→fuchsia gradient is appropriate for a general SaaS template but not for YFH's primary operational nav. For the main sidebar nav items (requests, dashboard, audit, reports, queue surfaces) use a solid `--primary` (`#0066cc`) bar — authority and clarity over decoration. However, the gradient approach is **appropriate and useful** for analytics/statistics sections: `BANK_ADMIN` portfolio overview, `CBY_ADMIN` governance dashboard, and any route group under a "تحليلات" (Analytics) or "الإحصائيات" (Statistics) label in the sidebar. Implement this as a `navGroupStyle?: 'default' | 'analytics'` field on `NavGroup` — `analytics` groups render the gradient active bar and a slightly more decorative badge; `default` groups use solid `--primary`. This gives the product visual hierarchy between operational queues and analytical oversight surfaces without applying decoration uniformly.

**Vue note:** Adopt the union shape verbatim (`NavLink | NavCollapsible` + `NavGroup`), but add a `roles?: Role[]` (or `can?: () => boolean`) field on `BaseNavItem`. Filter `navGroups` → `items` → sub-`items` by role *before* render so forbidden entries never mount. Replace `badgeColor` enum with YFH semantic states (e.g. `severity-red`, `warning`, `info`). Keep the collapsed-flyout-dropdown behavior — it is good for icon-rail mode — but mirror `side="right"` to `side="left"` (logical "start") in RTL.

### 3. Command Palette (`command-search.tsx`)

- Trigger: `SearchTrigger` is a fake input-styled button (`md:w-36 lg:w-56`) with a `⌘K` kbd hint, lives in the topbar.
- Open state + Cmd/Ctrl+K toggle live in `DashboardHeader`, not in the palette itself (palette is controlled via `open`/`onOpenChange`).
- Data is a **flat static array** of `{ title, url, group, icon }`, grouped at render via `reduce` into `Record<group, items[]>`, then rendered as `CommandGroup`s.
- No fuzzy library — relies on `cmdk`'s built-in substring filter inside `CommandInput`/`CommandList`.
- Each item is a `CommandItem asChild` wrapping a `<Link>` that closes the dialog on click.

Critical gap to fix in translation: the palette source array is **completely separate** from `config/sidebar.ts`. They are hand-maintained twice and can drift. It also exposes every route regardless of permission.

**Vue note:** YFH shipped a grouped command palette (commit b76b9aa4). Enforce a single source: derive palette items from the same role-filtered nav config used by the sidebar (plus a small set of role-gated quick actions). Never maintain two arrays. Keep `cmdk`-style substring filter (reka-ui `Command`); add Arabic-primary labels with optional English alias text in a hidden keyword field for search. Verify Cmd/Ctrl+K does not collide with Arabic-keyboard input.

### 4. Data Table System (the most reusable asset)

There are **two distinct table implementations** in the template, and they reveal the right vs. wrong factoring:

**(A) Composed/clean — tasks & transactions** (`features/tasks/components/data-table.tsx`, `features/payment-transactions/components/transactions-table.tsx`). This is the model to follow:
- Generic `DataTable<TData, TValue>` shell using TanStack Table with the full row-model stack: `getCoreRowModel`, `getFilteredRowModel`, `getPaginationRowModel`, `getSortedRowModel`, `getFacetedRowModel`, `getFacetedUniqueValues`.
- Columns passed in as a prop (`columns.tsx` / `transaction-columns.tsx`) — fully decoupled from the shell.
- Three internal `useState`s: `rowSelection`, `columnVisibility`, `sorting`. Filters + pagination are **derived from URL** (not local state) and synced back.
- The URL-sync pattern is the key reusable logic:
  - `pagination = useMemo(() => ({ pageIndex: search.page - 1, pageSize: search.perPage }))`
  - `columnFilters = useMemo()` builds a `ColumnFiltersState[]` from URL params.
  - `onColumnFiltersChange` / `onPaginationChange` callbacks reverse-map TanStack updaters back into URL params, **nulling defaults** (`page === 1 → null`, `perPage === DEFAULT → null`) to keep URLs clean.
- Sub-components: `DataTableToolbar`, `DataTablePagination`, `DataTableViewOptions`, `DataTableColumnHeader`, `DataTableRowActions`, `DataTableFacetedFilter`.
- Transactions table sets initial hidden columns via `columnVisibility` default `{ fee: false, country: false }` — a clean "show advanced columns on demand" pattern.

**(B) Monolithic/anti-pattern — users** (`features/users/components/user-data-table.tsx`). 700 lines: columns, filters (4-up `Select` grid), pagination, export, and color helpers all inline. Uses single-value `Select` filters with an `exactFilter` instead of faceted multi-select. **Do not follow this structure** — it is the cautionary example. Its only useful bits are the inline `<Select>` filter-bar layout (a `grid sm:grid-cols-4`) and the export dropdown.

**Faceted filter** (`data-table-filtered.tsx` / inline `FacetedFilter` in transactions toolbar) — the centerpiece:
- `Popover` + `Command` (search) + `Checkbox` rows.
- `column.getFacetedUniqueValues()` provides per-option counts shown right-aligned in monospace.
- Trigger button: dashed-border `outline`/`sm`, `PlusCircle` icon, title. When `selectedValues.size > 0`: a `Separator` then either up-to-2 option badges or "{n} selected" badge.
- Multi-select via a `Set`; `setFilterValue(arr.length ? arr : undefined)`.
- A "Clear filters" `CommandItem` appears (after `CommandSeparator`) only when something is selected.

**Column header** (`data-table-column-header.tsx`): if `!getCanSort()`, plain text; else a ghost button showing the sort arrow (`ArrowUp`/`ArrowDown`/`ChevronsUpDown`) opening a dropdown with Asc / Desc / Hide.

**Pagination** (`data-table-pagination.tsx`): left = selected-count, right = "Rows per page" `Select` (10/20/30/40/50) + "Page X of Y" + four pager buttons (first/prev/next/last with `ChevronsLeft` etc.). All chevrons are physical-direction icons that must mirror in RTL.

**View options** (`data-table-view-options.tsx`): dropdown of checkbox items for every column where `accessorFn !== undefined && getCanHide()`. Transactions variant adds a `columnLabels` map for human-readable names (tasks variant shows raw `column.id`) — the labeled version is the one to adopt.

**Vue note:** Build one generic `<DataTable>` (reka-ui / TanStack Vue Table or a hand-rolled equivalent) + the six sub-components, columns as a prop. Port the URL-sync derive/reverse-map pattern into a `useTableQueryState` composable backed by Nuxt `useRoute`/`router.replace` (replace history for search, push for page). Keep the faceted-filter `Popover+Command+Checkbox+counts` recipe — it is RTL-friendly with logical utilities. Refuse the monolithic users-table structure. Initial-hidden-columns and labeled view-options are both worth keeping.

### 5. Faceted Filter Counts Source

Counts come from TanStack's `getFacetedUniqueValues()` — i.e. they are computed from the **currently loaded client dataset**, not the server. For YFH's server-paginated request queues this is misleading (counts would reflect only the current page). Translation must either (a) compute facets server-side and pass option counts as data, or (b) only show counts when the full filtered set is client-side. Flag this explicitly so the team does not ship per-page counts that look like totals.

### 6. KPI / Metric Card Pattern (repeated 5x, identical)

`SectionCards`, `MetricsOverview`, `PaymentMetrics`, `UserStateCards`, `TransactionsStats` are the **same component copied five times** with different data arrays. The shared contract:

```
{ title, current/value, previous?/description?, growth/change (number), icon }
```

Layout: `Card > CardContent.space-y-4`; top row = icon (`size-6 text-muted-foreground`) on one side + a trend `Badge` (green if `growth>=0` else red, with `TrendingUp`/`TrendingDown`) on the other; bottom = label (muted sm) + value (`text-2xl font-bold`) + sub-line ("from {previous}" + `ArrowUpRight`, or a `description`).

This is strong evidence for YFH's single `DashboardKpiCard` contract. Note: the template uses `me-1`/`@5xl:` (logical + container queries) in the newer cards and `mr-1`/`lg:` in older ones — inconsistency to avoid; standardize on logical + container-query in Vue.

**Vue note:** One `DashboardKpiCard.vue` with props `{ label, value, icon, state?: 'success'|'warning'|'danger'|'info'|'neutral', trend?: { direction, value }, subLabel?, href? }`. Map `state`/`trend` to YFH semantic tokens, not raw green/red. Per saved feedback, render via shadcn-vue `Card` with `border-0 p-4 shadow-card` rather than a bespoke card. Replace "from {previous}/+%" vanity-metric framing with operational deltas (e.g. "منذ أمس", queue aging) only where role docs justify it.

### 7. Forms & Dialogs — two patterns

- **Controlled-state + manual Zod** (`tasks/add-task-modal.tsx`): local `useState` form object, `schema.parse()` in submit, manual `errors` map from `ZodError.issues`. Self-contained `Dialog` with its own trigger button. Lower fidelity.
- **react-hook-form + zodResolver** (`users/user-form-modal.tsx`): `useForm({ resolver: zodResolver(schema) })` with `Form`/`FormField`/`FormControl`/`FormItem`/`FormMessage` primitives, two-column `grid grid-cols-2 gap-4` for paired Selects, `DialogFooter` submit. This is the better pattern.

Neither uses a Sheet/Drawer for create/edit — both are centered `Dialog`s (the plan's earlier "drawer" assumption was wrong; there is a `tasks-mutate-drawer` referenced in the brief but the shipped create flow is a Dialog). Confirmation/destructive flows use `AlertDialog` (see NavUser logout, and the suggested pattern for row delete).

**Vue note:** YFH already uses VeeValidate + Zod + shadcn-vue `Form` primitives (RequestForm). Standardize all create/edit modals on that stack (the users-modal pattern), `Dialog` for create/edit, `AlertDialog` for irreversible workflow actions. Two-column paired-Select grid is a clean RTL-safe layout. Keep `PasswordRequirements`-style live validation for reset/MFA.

### 8. Settings IA — responsive tri-modal nav (`settings/layout.tsx`)

A genuinely reusable pattern. Same `sidebarNavItems` array ({title, href, icon}) rendered three ways by breakpoint:
- `lg`: vertical `aside w-52` of ghost `buttonVariants` links (active = `bg-muted`).
- `md` (below lg): `Tabs` (`SidebarNavTabs`) driving `router.push`.
- `<md`: a `Select` (`SidebarNavMobile`) driving `router.push`.

Shell: a fixed header block (title "Settings" + description + `Separator`) above a `flex min-h-0 flex-1` body; content column scrolls independently (`overflow-y-auto`). `ContentSection` (title + desc + separator + `faded-bottom` scroll body, `lg:max-w-xl` for form readability) wraps each tab's content.

Template tabs: Profile / Account / Appearance / Notifications / Display.

**Vue note:** Adopt the tri-modal responsive settings nav, mirrored RTL (aside on the right). YFH already has a 6-tab settings layout (Story 6.5); reconcile to: Profile, Security (incl. MFA), Notifications, Appearance/Display (safe prefs only), and admin-controlled platform settings where role permits. Use a `ContentSection.vue` wrapper for consistent tab headings + scroll body. Keep the `faded-bottom` scroll affordance only if implemented with token-safe gradient.

### 9. Auth Layout (`sign-in-2` + `sign-in-2/page.tsx`)

Two-column: left = brand mark (gradient square "SA" + name) atop a centered `max-w-md` form; right = full-bleed background image with a dark gradient overlay and a testimonial blockquote. The form has social buttons (Google/GitHub SVGs), an "or continue with" `Separator`, email + password (with `Eye`/`EyeOff` toggle and `onBeforeInput` char-blocking), a gradient submit button, and a sign-up link.

Everything decorative here is on the **Do Not Port** list: social login, testimonial/social proof, gradient submit, placeholder marketing image.

**Vue note:** Keep only the structural two-column + centered-form skeleton. Replace right panel with institutional CBY identity (no testimonial), drop social auth entirely, use solid `--primary` button. YFH already shipped a two-column login with OTP/MFA (Story 6.4) — this confirms the structure; ensure brand panel is on the logical-start side in RTL and copy is formal Arabic with rate-limit/lockout guidance.

### 10. Error Pages (`forbidden`/`maintenance`/etc.)

Uniform skeleton: `h-svh` centered flex, big muted lucide icon (`size-24`), giant code (`text-[7rem] font-bold`), short bold title, two-line muted description, and an action row — typically `Go Back` (router.back) + a primary recovery (`Back to Home` / `Refresh`). 403 explicitly says "Contact your administrator."

**Vue note:** One `ErrorState.vue` (props: `code`, `icon`, `title`, `description`, `actions[]`) for 401/403/404/500/503. Arabic institutional copy; recovery actions must be role-safe (e.g. "العودة إلى لوحة التحكم" routes to the role's own dashboard, never a forbidden home). Wire to Nuxt `error.vue` / middleware. 403 keeps the "contact administrator" guidance.

### 11. Theme Manager & Customizer (adopt selectively, with clear boundaries)

`use-theme-manager.ts` is a CSS-variable mutation engine: it enumerates ~50 CSS vars and sets/clears them on `document.documentElement` to apply presets, radius, dark/light mode, and individual brand colors. The `ThemeCustomizer` (right `Sheet`) exposes two tabs: `ThemeTab` (mode + radius + presets + brand colors) and `LayoutTab` (sidebar variant + collapsible mode + sidebar side).

**Revised stance:** YFH already has a sophisticated `useThemingStore` (Pinia) that applies dark mode, font, brand color, high contrast, and layout — all persisted to localStorage and applied via CSS var mutation on `document.documentElement`. The template's approach is the same architecture, just in React. The question is therefore not "adopt or reject the pattern" (we already use it) but "which specific settings to expose to users."

**What to adopt from the template into the Appearance settings page:**

| Template control | YFH target | Guidance |
| --- | --- | --- |
| Light / Dark / System toggle (`ThemeTab` mode section) | `useThemingStore.setMode()` — already exists | Already shipped (Story 6.7); wire the same 2-button + system option UI |
| Radius selector — 5 swatches (None / SM / MD / LG / XL) | `applyRadius()` → `--radius` CSS var | Add to Appearance tab; 5 swatches with visual corner previews; persist to store |
| Sidebar Variant — 3 visual swatches (sidebar / floating / inset) | `useSidebarConfig` equivalent in theming store | Adopt the `LayoutTab` swatch UI exactly; persist user choice; apply to `AppShell.vue` `variant` prop |
| Sidebar Collapsible Mode — 3 visual swatches (offcanvas / icon / none) | same config store | Adopt; auto-collapse if switching to icon while expanded |
| Sidebar Side — left / right swatches | **RTL-fixed: always right** | Do NOT expose this to users. YFH is RTL-first; sidebar position is a product constraint, not a preference. Keep the swatch as internal dev-QA only. |

**What to reject from ThemeTab:**
- Random theme presets (Shadcn/Tweakcn color-scheme pickers) — institutional branding is `#0066cc`, not user-selectable
- Import-from-JSON theme upload — no arbitrary CSS var injection by users
- Brand Colors accordion (per-var color pickers) — CBY brand is fixed; only `CBY_ADMIN` role could get a controlled brand color field if a business requirement exists
- `snow-effect.tsx` — seasonal decoration, reject

**The `LayoutTab` visual swatch pattern** (small div-based mini-previews of sidebar appearance) is the most transferable asset. Translate the swatch divs to Vue with logical CSS (`border-s` instead of `border-l`, swapped sidebar position for RTL previews). These swatches make the setting immediately understandable without words.

**Vue note:** Extend `useThemingStore` with `sidebarVariant`, `sidebarCollapsible`, and `radius` fields (add to `persistToCache`/`loadFromCache`). Wire variant/collapsible into `AppShell.vue` props reactively. Build an `AppearanceSettings.vue` tab with the mode toggle, radius swatches, sidebar variant swatches, and collapsible mode swatches — all in Arabic. The circular-transition CSS for dark mode toggle is a nice touch; adopt it if it respects `prefers-reduced-motion`.

### 12. Shared Components Inventory

- `content-section.tsx` — settings/section wrapper (see #8). **Adopt.**
- `password-requirements.tsx` — live rule checklist (Check/X, green/red). **Adopt** for reset/MFA, with backend-matching rules + Arabic.
- `coming-soon.tsx` — centered placeholder card (gradient — strip it). **Adopt** as a neutral dev placeholder for unbuilt admin routes.
- `animated-list.tsx` — `motion/react` staggered spring-in list (`scale`+`opacity` enter/exit, spring physics, auto-advancing index, `AnimatePresence`). **Adopt for notifications and activity feeds.** The pattern is: items appear one by one on load with spring animation, newest at top. In Vue, use `@vueuse/motion` or `@formkit/auto-animate`. Must be gated by `prefers-reduced-motion` — if the user has reduced motion enabled, items appear instantly with no animation. Limit use to: notification list on the `/notifications` page, activity feed on dashboards (CBY_ADMIN audit anomaly stream, SUPPORT_COMMITTEE claim queue arrival). Do not use for tables, forms, or any workflow-critical surface.
- `theme-toggle.tsx` — safe Light/Dark/System control. **Adopt.**
- `circular-transition.css` — the dark-mode toggle uses a radial clip-path animation originating from the click point. **Adopt** with `@media (prefers-reduced-motion: reduce)` fallback to instant switch.
- `color-picker.tsx`, `import-modal.tsx` — arbitrary branding mutation tools. **Reject** for production users (CBY_ADMIN brand color field is the one controlled exception if a business requirement exists).

### 13. Utility Hooks

- `use-debounced-callback.ts` — 300ms debounce that re-syncs when `externalValue` changes; used by every table search input. **Vue:** VueUse `refDebounced` + `watch`, or `useDebounceFn`.
- `use-keyboard-shortcuts.ts` — global `/` to focus search (skips when already in input/textarea/contenteditable) and `Esc` to clear filters + blur. **Vue:** VueUse `useEventListener`/`onKeyStroke`; preserve the input-focus guard so Arabic typing of "/" is unaffected; pair with the palette's Cmd/Ctrl+K.
- `search-params.ts` — nine `nuqs` `useQueryStates` definitions, each declaring the param schema + defaults + `history` mode (`replace` for search/filter-only, `push` for paginated tables) + `shallow: true`. This is the canonical "filter schema per surface" model. **Vue:** a `useTableQueryState(schema)` composable wrapping Nuxt route query with typed parsers (string / array-of-string via comma split / integer) and per-key history mode.

### 14. Export Utility (`lib/export-data.ts`)

`exportToCSV`/`exportToJSON` take rows + filename + optional `ExportColumn<T>[]` ({key,label}) projection, build content client-side, and trigger a Blob download. CSV quotes every field and escapes `"`→`""`. Both bail on empty data. Tables call these from an Export dropdown passing an explicit column list (so export shape is controlled, not raw row dump).

**Vue note:** Wrap as `useTableExport`. Two YFH-specific hardening requirements: (1) the column projection must be the **role-visible** column set — forbidden columns cannot leak into export; (2) prepend a UTF-8 BOM (`﻿`) to CSV so Excel renders Arabic correctly. Consider routing audit-sensitive exports through the backend (which already logs export events per Story 5.6) rather than client-side Blob, to preserve the audit trail.

## Component Translation Guide

Concrete mapping from template asset → Yemen Flow Hub Vue target. "Status" reflects whether YFH already has the piece (per memory/commits) or it is new work.

| Template asset | YFH Vue target | Status | Implementation guidance |
| --- | --- | --- | --- |
| `app/(dashboard)/layout.tsx` shell nesting | `app/components/layout/AppShell.vue` | Exists (b76b9aa4) | Verify sidebar/inset are siblings under provider; topbar inside inset column; layout-config store separate from open-state store. |
| `app-sidebar.tsx` | `app/components/AppSidebar.vue` | Exists | Header=brand, content=role-filtered groups, footer=NavUser, rail toggle. |
| `config/sidebar.ts` + `lib/types.ts` union | `NAV_ITEMS` config + nav types | Exists | Add `roles?`/`can?` per item; filter before render; replace badge colors with semantic states. |
| `nav-group.tsx` (link / collapsible / collapsed-dropdown) | `NavGroup.vue` | Exists/refine | Keep 3-branch render; mirror chevron + active bar to right edge; `ms-auto` badges. Solid `--primary` active bar for operational groups; gradient bar allowed for analytics/statistics groups via `navGroupStyle?: 'analytics'` field. |
| `nav-user.tsx` + `useLogout` AlertDialog | `NavUser.vue` | Exists | Footer dropdown + logout confirm AlertDialog; drop "Upgrade to Pro"/"Billing"; Arabic items; keep session/audit semantics. |
| `team-switcher.tsx` | (org/bank context header) | Adapt/optional | Most YFH roles are single-org; show fixed org identity, not a switcher. CBY_ADMIN may get a read-only scope indicator. No gradient logo. |
| `command-search.tsx` | `CommandPalette.vue` | Exists (b76b9aa4) | Derive items from role-filtered nav (single source); Arabic-primary + English keyword alias; Cmd/Ctrl+K; groups = الطلبات/الطوابير/الجهات/الموظفون/التدقيق/التقارير/الإعدادات/المساعدة. |
| `dashboard-header.tsx` | `GlobalTopbar.vue` | Exists (b76b9aa4) | Sidebar trigger + search trigger + (notifications) + theme + user. Replace theme-customizer gear with safe preferences. `ms-auto` for the utilities cluster. |
| tasks `data-table.tsx` shell | `DataTable.vue` (generic) | New (refactor) | Columns-as-prop; full TanStack/reka row models; rowSelection/visibility/sorting local; filters+pagination from URL. |
| `data-table-toolbar.tsx` | `DataTableToolbar.vue` | New | Search (debounced, `/` focus) + faceted filters + Reset (when filtered) + view options + primary action slot. |
| `data-table-filtered.tsx` faceted filter | `DataTableFacetedFilter.vue` | New | Popover+Command+Checkbox+counts; dashed trigger; selected badges/“n selected”; clear item. Counts: server-provided for paginated queues (see Finding #5). |
| `data-table-view-options.tsx` (labeled variant) | `DataTableViewOptions.vue` | New | Checkbox per hideable column using a `columnLabels` (Arabic) map; role-visible columns only. |
| `data-table-column-header.tsx` | `DataTableColumnHeader.vue` | New | Sort arrow + Asc/Desc/Hide dropdown; mirror `-ml-3`→`-me-3`. |
| `data-table-pagination.tsx` | `DataTablePagination.vue` | New | Selected-count + rows-per-page Select + page indicator + 4 pager buttons (mirrored chevrons). |
| `data-table-row-actions.tsx` | `DataTableRowActions.vue` | New | Dropdown; role-gate each action; destructive items open AlertDialog; never render forbidden actions. |
| `transaction-columns.tsx` status/method config maps | column `statusConfig`/badge maps | New pattern | Drive status badge styling from a config map keyed by canonical status enum → YFH semantic token; icon + label per status. |
| KPI cards (×5) | `DashboardKpiCard.vue` | Exists (refine) | Single contract {label,value,icon,state,trend,subLabel,href}; semantic tokens; `Card border-0 p-4 shadow-card`. |
| `selection-cards.tsx` rhythm | `DashboardSection.vue` + KPI grid | New | `grid sm:grid-cols-2 lg:grid-cols-4` rhythm; section heading + helper + actions wrapper. |
| `users/user-form-modal.tsx` (RHF+zod) | create/edit `Dialog` forms | Pattern exists | VeeValidate+Zod+`Form` primitives; two-column paired-Select grid; `Dialog` (not Sheet) for create/edit. |
| `add-task-modal.tsx` | (reject pattern) | — | Do not copy manual-Zod controlled-state form; use the RHF/VeeValidate pattern instead. |
| `settings/layout.tsx` tri-modal nav | `settings.vue` layout | Exists (6.5) | aside(lg)/tabs(md)/select(sm), mirrored RTL; reconcile tab set; `ContentSection.vue` per tab. |
| `content-section.tsx` | `ContentSection.vue` | New | title+desc+separator+scroll body, `lg:max-w-xl`; token-safe faded-bottom. |
| `password-requirements.tsx` | `PasswordRequirements.vue` | New | Live Check/X list; rules match backend; Arabic labels; used in reset/MFA. |
| error components | `ErrorState.vue` + `error.vue` | New/refine | Single props-driven layout for 401/403/404/500/503; role-safe recovery actions; Arabic. |
| `coming-soon.tsx` | `ComingSoon.vue` | New (dev) | Neutral placeholder (no gradient) for unbuilt admin routes. |
| `theme-toggle.tsx` | preference control | Exists (6.7) | Light/Dark/System with active check; `html.dark`. Adopt circular-transition CSS with `prefers-reduced-motion` fallback. |
| `theme-customizer/layout-tab.tsx` (variant + collapsible swatches) | `AppearanceSettings.vue` — Layout section | New (user feature) | Expose sidebar variant (sidebar/floating/inset) and collapsible mode (offcanvas/icon/none) as user preferences with visual swatches. Persist via `useThemingStore`. Sidebar side NOT user-configurable (RTL fixed = right). |
| `theme-customizer/theme-tab.tsx` (radius + mode only) | `AppearanceSettings.vue` — Theme section | Partial adopt | Mode toggle + radius swatches (5 options) are user preferences. Reject color presets, brand color pickers, and import-from-JSON. |
| `animated-list.tsx` (spring enter/exit list) | `AnimatedList.vue` | New (notifications/activity) | Adopt for `/notifications` page and activity feeds on BANK_ADMIN/CBY_ADMIN dashboards. Use `@vueuse/motion` or `@formkit/auto-animate`. Gate with `prefers-reduced-motion`; never on tables or workflow surfaces. |
| `lib/export-data.ts` | `useTableExport` composable | New | Role-visible column projection; UTF-8 BOM for Arabic CSV; prefer backend export for audited surfaces. |
| `use-debounced-callback.ts` | VueUse `refDebounced`/`useDebounceFn` | Replace | 300ms table search debounce. |
| `use-keyboard-shortcuts.ts` | `useTableKeyboard` (VueUse `onKeyStroke`) | New | `/` focus search (guard inputs/IME), `Esc` clear+blur. |
| `search-params.ts` (nuqs) | `useTableQueryState` (Nuxt route query) | New | Typed parsers (string/array/int), per-key history mode (replace for search, push for page), default-nulling. |
| `animated-list.tsx` | (defer) | Reject for now | Only for future activity feed; must respect reduced-motion. |

## Implementation Checklist

### Phase 0: Baseline, Governance, And Evidence

- [ ] Capture current Yemen Flow Hub screenshots for `/login`, `/dashboard`, `/requests`, `/requests/new`, `/settings`, error states, and all role dashboard variants.
- [x] Create a route inventory for the current frontend: shell, sidebar, page headers, dashboards, tables, forms, dialogs, errors, settings.
- [x] Map every proposed UI change to one source of truth: `docs/user-view/*.md`, root `DESIGN.md`, `frontend/DESIGN.md`, or `frontend/SHADCN.md`.
- [x] Confirm all changes use shadcn-vue components where applicable.
- [x] Confirm no work touches `lovable/`.
- [x] Confirm generated artifacts remain local-only: `graphify-out/`, `_bmad-output/implementation-artifacts/`, `_bmad-output/test-artifacts/`.

### Phase 1: Shell, Sidebar, And Topbar

- [x] Refactor `AppShell.vue` to own global shell controls instead of scattering them across pages.
- [x] Add or formalize a `GlobalTopbar.vue` with sidebar trigger, command palette trigger, notification trigger, preferences/theme trigger, and user menu.
- [x] Keep page-specific title, breadcrumbs, and primary actions in `PageHeader.vue`.
- [x] Keep sidebar right-aligned by default and verify expanded, collapsed, mobile sheet, and keyboard toggle states.
- [x] Improve active nav styling with project blue and full pill or contained active state from `DESIGN.md`, not template purple.
- [x] Add nested sidebar groups for admin-only areas only if role docs justify them.
- [ ] Add operational nav badges from real API counts only.
- [ ] Add tests that forbidden nav entries are not mounted for each role.
- [x] Confirm shell nesting: sidebar and inset content are siblings under one provider; topbar lives inside the inset column (not above the whole viewport).
- [x] Keep layout-config (fixed `side=right`, variant, collapsible) separate from runtime open/collapsed state; do not merge into one store.
- [x] Adopt the `NavLink | NavCollapsible` + `NavGroup` discriminated-union nav model and add a `roles?`/`can?` field on each item.
- [x] Keep the 3-branch `NavGroup` render (leaf / inline-collapsible / collapsed-flyout-dropdown); mirror flyout side and chevron to logical-start in RTL.
- [x] Replace the violet→fuchsia gradient active-bar with a solid `--primary` (#0066cc) indicator for operational nav groups (requests, queues, audit, workflow); allow gradient bar for analytics/statistics nav groups (BANK_ADMIN oversight, CBY_ADMIN governance) via a `navGroupStyle?: 'analytics'` field on `NavGroup`. Mirror the indicator bar to the right edge in RTL (`absolute right-0`, `rounded-l-full`). Replace `badgeColor` enum with YFH semantic states.
- [x] Replace TeamSwitcher with a fixed org/bank identity header (no switcher for single-org roles; read-only scope indicator for CBY_ADMIN).
- [x] Keep the NavUser logout AlertDialog confirm; drop "Upgrade to Pro"/"Billing"; preserve session/audit semantics.

### Phase 2: Role-Scoped Command Palette

- [ ] Build `CommandPalette.vue` with shadcn-vue `Command` and `Dialog` patterns.
- [ ] Source command entries from the same role-filtered nav and permission rules used by the sidebar.
- [ ] Add groups: الطلبات, الطوابير, الجهات, الموظفون, التدقيق, التقارير, الإعدادات, المساعدة.
- [ ] Add quick actions only when the active role and status allow them, for example new request for `DATA_ENTRY`.
- [ ] Support keyboard shortcut without conflicting with browser or Arabic input behavior.
- [ ] Add tests proving forbidden routes/actions do not appear in command search.
- [ ] Enforce a single source of truth: derive palette items from the same role-filtered nav config as the sidebar; never maintain a second hand-edited array (the template's key defect).
- [ ] Keep cmdk/reka substring filter; add Arabic-primary labels with optional English alias keywords for search matching.
- [ ] Keep the `SearchTrigger` input-styled button with a `⌘K`/`Ctrl K` hint in the topbar (mirror kbd to logical-end in RTL).

### Phase 3: Appearance Settings — Layout, Radius, Mode, And Animation

- [ ] **Mode toggle:** Light / Dark / System — already in `useThemingStore.setMode()`; wire the 2-button + system option UI in Appearance tab. Adopt `circular-transition.css` radial clip-path animation with `prefers-reduced-motion` fallback.
- [x] **Radius selector:** Add `radius` field to `useThemingStore`; apply via `document.documentElement.style.setProperty('--radius', value)`. Build 5 visual swatches (None / SM / MD / LG / XL) showing corner-radius preview. Persist to localStorage cache.
- [x] **Sidebar variant:** Add `sidebarVariant: 'sidebar' | 'floating' | 'inset'` to `useThemingStore`. Build 3 visual mini-swatches (translate `LayoutTab` div-based previews to Vue, using logical CSS `border-s`/`border-e` for RTL-correct swatch rendering). Wire reactively into `AppShell.vue` `variant` prop.
- [x] **Sidebar collapsible mode:** Add `sidebarCollapsible: 'offcanvas' | 'icon' | 'none'` to `useThemingStore`. Build 3 visual swatches. Auto-collapse sidebar if switching to `icon` while currently expanded. Wire into `AppShell.vue` `collapsible` prop.
- [ ] **Sidebar side:** Do NOT expose to users. Always `right` (RTL constraint). No swatch, no toggle in production.
- [ ] **High contrast:** Already in `useThemingStore.setHighContrast()` — confirm the `high-contrast` CSS class has meaningful token overrides.
- [x] **Reduced motion:** Respect `prefers-reduced-motion` OS setting. Add `reducedMotion: 'system' | 'always'` preference to store. Gate all animations (animated list, circular transition, sidebar slide) behind this check.
- [x] **Density preference:** Add `density: 'comfortable' | 'compact'` to store; apply as `data-density` attribute on `<html>`; define compact overrides for table row height, card padding, and sidebar item height in CSS.
- [x] **Animated list:** Build `AnimatedList.vue` using `@vueuse/motion` spring physics (scale + opacity enter/exit, `AnimatePresence` equivalent via `<TransitionGroup>`). Use on `/notifications` page and BANK_ADMIN/CBY_ADMIN activity feeds. Never on tables or workflow-critical surfaces. Disable when reduced motion is active.
- [ ] **Reject:** Random Shadcn/Tweakcn color-scheme presets, import-from-JSON theme upload, brand color pickers, `snow-effect`. CBY brand is `#0066cc` — not user-selectable (CBY_ADMIN brand color field is the one exception if a future business requirement is documented).
- [x] Persist all new preferences (`sidebarVariant`, `sidebarCollapsible`, `radius`, `density`, `reducedMotion`) in the existing `persistToCache`/`loadFromCache` cycle.

### Phase 4: Page Header System

- [x] Standardize `PageHeader.vue` props/slots: title, subtitle, breadcrumbs, primary action, secondary actions, toolbar, status summary, last updated.
- [ ] Remove duplicate greeting/header blocks inside role dashboard subcomponents.
- [ ] Add consistent refresh, export, date range, and bank/entity filters where role docs allow them.
- [ ] Verify RTL alignment: primary actions should sit where Arabic scanning expects, and icon order must mirror.
- [ ] Add compact behavior for `600px` width without hiding important workflow actions.

### Phase 5: Dashboard Composition

- [x] Define a shared `DashboardKpiCard` contract: icon, label, value, semantic state, trend, SLA hint, drilldown route, loading state.
- [ ] Define an `ActionRequiredStrip` component for urgent role work.
- [x] Define a `DashboardSection` component for consistent headings, helper text, and actions.
- [ ] Keep operational roles queue-first: `DATA_ENTRY`, `BANK_REVIEWER`, `SUPPORT_COMMITTEE`, `SWIFT_OFFICER`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`.
- [ ] Add chart-backed sections first to `BANK_ADMIN` and `CBY_ADMIN`, where oversight and analytics are role-appropriate.
- [ ] Use existing Vue chart components and project tokens.
- [ ] Avoid generic SaaS cards, vanity metrics, gradients, and revenue language.
- [x] Consolidate the five duplicated metric-card components into the single `DashboardKpiCard.vue` contract (the template copies the same card 5x).
- [ ] Drive status badges from a config map keyed by canonical status enum → semantic token + icon + label (port the `transaction-columns.tsx` `statusConfig` pattern, with YFH enums).
- [x] Add a `DashboardSection.vue` wrapper for consistent section heading + helper text + actions, and reuse the `grid sm:grid-cols-2 lg:grid-cols-4` KPI rhythm.
- [ ] Standardize on logical spacing utilities and container queries (`me-1`, `@container`) across cards; remove the template's `mr-1`/`lg:` inconsistency.

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

- [x] Create a reusable table toolbar inspired by the template: search, faceted filters, active filter chips, reset, column visibility, export, selected count.
- [x] Create a Nuxt query-state composable for table filters and pagination.
- [ ] Start with `RequestsDataTable.vue` and `/requests` because it is the core operational queue.
- [x] Translate template faceted filters into shadcn-vue `Popover`, `Command`, `Checkbox`, `Badge`, and `Button` patterns.
- [ ] Add canonical enum filters: role, status, organization, category, date range, claim state, voting state, document state as applicable.
- [x] Add column visibility for allowed columns only.
- [ ] Ensure exports respect the same role-visible column model.
- [ ] Add keyboard focus checks for search, filters, row actions, pagination, and column menu.
- [x] Build one generic `DataTable.vue` with columns-as-prop and the full row-model stack; refuse the monolithic users-table structure (700-line inline anti-pattern).
- [x] Extract the URL-sync derive/reverse-map logic into a `useTableQueryState` composable (typed parsers, per-key history mode, default-nulling of page/perPage).
- [x] Build the six sub-components: `DataTableToolbar`, `DataTableFacetedFilter`, `DataTableViewOptions`, `DataTableColumnHeader`, `DataTablePagination`, `DataTableRowActions`.
- [ ] Use a labeled (Arabic) `columnLabels` map in view-options, not raw `column.id`.
- [ ] Decide faceted-filter count source: for server-paginated queues, provide facet counts from the backend; do NOT show TanStack client-side per-page counts as if they were totals.
- [x] Add a debounced search (`refDebounced`/`useDebounceFn`, 300ms) and `/` focus + `Esc` clear shortcuts with input/IME guards.
- [x] Build a `useTableExport` composable: role-visible column projection + UTF-8 BOM for Arabic CSV; route audited exports through the backend where one exists.
- [ ] Support initial-hidden "advanced" columns (e.g. fee/country pattern) shown on demand via view-options.
- [x] Row-action menu must role-gate every item and route destructive actions through `AlertDialog`; never render forbidden actions.

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
- [x] Build one props-driven `ErrorState.vue` (code, icon, title, description, actions) for 401/403/404/500/503; wire to Nuxt `error.vue`/middleware.
- [x] Error recovery actions must be role-safe: "back to dashboard" routes to the role's own dashboard, never a forbidden home; 403 keeps "contact administrator" guidance.
- [ ] Standardize create/edit on the VeeValidate+Zod+`Form`-primitives pattern in a centered `Dialog` (not a Sheet); use the two-column paired-Select grid layout.
- [x] Add a `PasswordRequirements.vue` live checklist (Check/X, backend-matching rules, Arabic) for reset/MFA password fields.
- [x] Add a neutral `ComingSoon.vue` (no gradient) for unbuilt admin routes during rollout.
- [x] Add a `ContentSection.vue` wrapper (title + desc + separator + scroll body, `lg:max-w-xl`) for settings tabs.
- [ ] Adopt the tri-modal responsive settings nav (aside list on lg, tabs on md, select on sm), mirrored RTL.

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
- [ ] Do not port random Shadcn/Tweakcn color-scheme presets, import-from-JSON theme upload, or per-variable brand color pickers as user-facing features (radius, mode, sidebar variant/collapsible are fine).
- [ ] Do not port pricing, AI chat, mail, calendar, or kanban as literal product features without a Yemen Flow requirement.
- [ ] Do not replace shadcn-vue components with raw HTML to make tests pass.
- [ ] Do not add role-inappropriate controls and rely on backend rejection later.
- [ ] Do not make sidebar side (left/right) user-configurable — YFH is RTL-first, sidebar is always right.
- [ ] Do not apply gradient active bars to operational nav groups (requests, queues, audit) — only to explicitly analytics-scoped nav groups.

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
