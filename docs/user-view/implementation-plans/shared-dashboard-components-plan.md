# Shared Dashboard Components Implementation Plan

Source reference: `shadcn-admin/app/(dashboard)/dashboard2` and `shadcn-admin/features/dashboard2/components/*`

Browser evidence: `output/playwright/shadcn-admin-dashboard2.png`

## Implementation Goal

Convert the useful `/dashboard2` dashboard patterns into Yemen Flow Hub shared Vue/shadcn-vue components, then replace duplicated KPI, chart, list, and insight card markup across dashboards and reporting pages without weakening role-scoped UX rules.

The target is not a direct business-dashboard clone. The visual pattern is reusable, but all labels, data, actions, and visibility must stay aligned to Yemen Flow Hub role specifications and workflow rules.

## Template Patterns To Inherit

- `MetricsOverview`: four prominent metric cards with icon, current value, trend badge, and previous/baseline value.
- `SalesChart`: section card shell with title, description, range selector, export action, and a large time-series chart.
- `RevenueBreakdown`: chart card with category selector, donut/pie breakdown, and clickable legend rows.
- `RecentTransactions`: compact recent activity list with avatar/status/action affordances.
- `TopProducts`: ranked list card with progress indicators and secondary metadata.
- `CustomerInsights`: wide insight card with tabs, chart area, and side key-metric cards.
- `QuickActions`: compact action group, but Yemen Flow Hub should keep existing role-forbidden action rules.

## Shared Component Set

Create these components under `frontend/app/components/shared/dashboard/`:

- [x] `MetricCard.vue`: one reusable top-card component for KPI strips. Supports `label`, `value`, `icon`, `tone`, `trend`, `previousLabel`, `href/onClick`, `highlighted`, and `data-testid`.
- [x] `MetricGrid.vue`: responsive wrapper for 3, 4, 5, or 6 metric cards using the `/dashboard2` visual rhythm and project RTL breakpoints.
- [x] `AnalyticsCard.vue`: common section-card shell with `title`, `description`, optional actions slot, loading/error/empty slots, and consistent gradient/border/shadow treatment.
- [x] `TimeSeriesChartCard.vue`: `AnalyticsCard` wrapper for Unovis line/area charts, replacing ad hoc line-chart cards.
- [x] `BreakdownChartCard.vue`: `AnalyticsCard` wrapper for donut/pie/bar breakdowns with legend rows and optional category selector.
- [x] `RecentActivityCard.vue`: compact activity/request list shell, adapted from `RecentTransactions`, using Yemen Flow Hub request/user/audit row props.
- [x] `RankedListCard.vue`: ranked entity/risk/product-style list shell with `Progress`, `Badge`, and drilldown actions.
- [x] `InsightsTabsCard.vue`: wide tabbed analytics card for compliance, customer/entity insight, audit anomalies, or workflow-pressure summaries.
- [x] `DashboardToolbar.vue`: optional page-level toolbar for refresh, last-updated, date range, bank/entity filters, and export actions.

## Component Rules

- Use shadcn-vue primitives only: `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `Button`, `Badge`, `Select`, `Tabs`, `Progress`, `Skeleton`, `Alert`, `Table`.
- Use `@unovis/vue` through existing chart primitives/patterns, not Recharts from the React template.
- Use project semantic tokens: `--severity-green`, `--severity-amber`, `--severity-red`, `--voting`, `--swift`, `--info`, `text-primary`, `text-muted-foreground`, `border-border`.
- Preserve RTL layout and Arabic copy. The inherited layout rhythm is useful; English labels from the template are not.
- Keep role-forbidden actions unmounted. Shared action slots must not make forbidden workflow actions easier to leak.
- Keep existing data sources (`useDashboardStore`, `useReports`, `useAudit`, `useMerchants`, `useUsers`, etc.) and normalize data in page-level computed values first.

## Replacement Analysis

### Priority 1: KPI Cards

Replace duplicated top KPI card markup first. This gives the highest reuse with the lowest behavior risk.

| Surface | Current Pattern | Replacement |
| --- | --- | --- |
| `frontend/app/components/dashboard/DataEntryDashboard.vue` | Manual four-card grid | `MetricGrid` + `MetricCard` |
| `frontend/app/components/dashboard/BankReviewerDashboard.vue` | Manual four-card grid | `MetricGrid` + `MetricCard` |
| `frontend/app/components/dashboard/SupportCommitteeDashboard.vue` | Manual four-card grid | `MetricGrid` + `MetricCard` |
| `frontend/app/components/dashboard/SwiftOfficerDashboard.vue` | Manual four-card grid | `MetricGrid` + `MetricCard` |
| `frontend/app/components/dashboard/BankAdminDashboard.vue` | Manual KPI grid | `MetricGrid` + `MetricCard` |
| `frontend/app/components/dashboard/CbyAdminDashboard.vue` | Manual six-card strategic strip with sparkline | `MetricGrid` + `MetricCard`; keep sparkline as optional slot |
| `frontend/app/components/dashboard/ExecutiveDashboard.vue` | Raw `<button>` KPI cards | Convert to `MetricGrid` + `MetricCard` and remove raw button cards |
| `frontend/app/pages/audit.vue` | Local `kpis` cards | `MetricGrid` + `MetricCard` |
| `frontend/app/pages/reports.vue` | Local KPI cards | `MetricGrid` + `MetricCard` |
| `frontend/app/pages/reports/index.vue` | Raw `.kpi-card` CSS and skeleton divs | `MetricGrid` + `MetricCard` + `Skeleton` |
| `frontend/app/pages/admin/banks.vue` | Admin KPI cards | `MetricGrid` + `MetricCard` |
| `frontend/app/pages/admin/cby-staff.vue` | Admin KPI cards | `MetricGrid` + `MetricCard` |
| `frontend/app/pages/merchants.vue` | Merchant KPI cards | `MetricGrid` + `MetricCard` |
| `frontend/app/pages/profile.vue` | Profile stats strip | Use `MetricGrid` only if the visual hierarchy should match operational stats; otherwise leave lower-priority |

### Priority 2: Analytics And Chart Cards

Replace page-local chart shells only where charts support operational decisions.

| Surface | Current Pattern | Replacement |
| --- | --- | --- |
| `frontend/app/components/dashboard/BankAdminDashboard.vue` | Custom SVG monthly trend and operational widgets | `TimeSeriesChartCard` for monthly volume; `BreakdownChartCard` for category/health breakdowns where data exists |
| `frontend/app/components/dashboard/CbyAdminDashboard.vue` | Workflow pressure, risk intelligence, compliance signals | `AnalyticsCard`, `RankedListCard`, and `InsightsTabsCard` where the card shell is repeated; keep governance-specific tables intact |
| `frontend/app/pages/reports/index.vue` | Local `.section-card`, `.charts-row`, custom skeleton CSS | `AnalyticsCard`, `TimeSeriesChartCard`, `BreakdownChartCard` |
| `frontend/app/pages/reports.vue` | Existing report KPI and summary cards | Adopt `AnalyticsCard` for report sections after KPI replacement |
| `frontend/app/pages/audit.vue` | Duplicate/risk/audit summary cards | `AnalyticsCard`, `RecentActivityCard`, `RankedListCard` |

### Priority 3: Lists And Insight Cards

Use these after KPI and chart shells are stable.

| Surface | Current Pattern | Replacement |
| --- | --- | --- |
| Dashboard recent queues (`DataEntry`, `BankReviewer`, `Support`, `Swift`) | Small queue tables | Keep shadcn `Table` for operational queues; only wrap with `AnalyticsCard` if the section shell is duplicated |
| `CbyAdminDashboard.vue` critical events | Feed-style governance events | `RecentActivityCard` |
| `CbyAdminDashboard.vue` bank risk intelligence | Ranked risk rows | `RankedListCard` |
| `audit.vue` risk indicators and duplicate invoice findings | Repeated risk card/list shells | `RankedListCard` or `InsightsTabsCard` |
| `reports/index.vue` status and bank breakdown tables | Report tables | `AnalyticsCard`; do not replace tables with decorative lists |

## Implementation Phases

### Phase 0: Evidence And Baseline

- [x] Capture `/dashboard2` screenshot with `playwright-cli`: `output/playwright/shadcn-admin-dashboard2.png`.
- [x] Inspect template component source under `shadcn-admin/features/dashboard2/components/`.
- [x] Run SocratiCode and graphify discovery for existing dashboard/card surfaces.
- [x] Capture before screenshots for current Yemen Flow Hub dashboards/pages targeted in Phase 2 and Phase 3.

### Phase 1: Build Shared Foundations

- [x] Create `frontend/app/components/shared/dashboard/MetricCard.vue`.
- [x] Create `frontend/app/components/shared/dashboard/MetricGrid.vue`.
- [x] Create `frontend/app/components/shared/dashboard/AnalyticsCard.vue`.
- [x] Create story/test fixtures with Arabic labels, RTL layout, positive/negative/neutral trend badges, loading, empty, and error states.
- [x] Add focused Vitest tests for click/keyboard behavior, semantic colors, and slot rendering.

### Phase 2: Replace KPI Strips

- [x] Replace KPI cards in `DataEntryDashboard.vue`.
- [x] Replace KPI cards in `BankReviewerDashboard.vue`.
- [x] Replace KPI cards in `SupportCommitteeDashboard.vue`.
- [x] Replace KPI cards in `SwiftOfficerDashboard.vue`.
- [x] Replace KPI cards in `BankAdminDashboard.vue`.
- [x] Replace KPI cards in `CbyAdminDashboard.vue` without losing sparkline/severity behavior.
- [x] Replace raw KPI button cards in `ExecutiveDashboard.vue`.
- [x] Replace page-level KPI strips in `audit.vue`, `reports.vue`, `admin/banks.vue`, `admin/cby-staff.vue`, and `merchants.vue`.

### Phase 3: Replace Chart And Insight Shells

- [x] Create `TimeSeriesChartCard.vue` using existing Unovis chart components.
- [x] Create `BreakdownChartCard.vue` using existing pie/bar chart components.
- [x] Create `RecentActivityCard.vue`, `RankedListCard.vue`, and `InsightsTabsCard.vue`.
- [x] Replace report chart shells in `reports/index.vue`.
- [x] Replace duplicated dashboard analytics shells in `BankAdminDashboard.vue` and `CbyAdminDashboard.vue`.
- [x] Replace audit insight/risk shells in `audit.vue`.

### Phase 4: Visual And Behavior Verification

- [x] Run focused Vitest suites for every touched dashboard/page.
- [x] Run Playwright visual dashboard screenshots for all roles: `frontend/tests/visual/dashboards.spec.ts`.
- [x] Use `playwright-cli` to manually inspect `/dashboard`, `/reports`, `/audit`, `/admin/banks`, `/admin/cby-staff`, and `/merchants` at desktop and mobile breakpoints.
- [x] Run `graphify update .` after code changes and keep `graphify-out/` local only.

## Acceptance Criteria

- All role dashboards use the same shared top-card component unless a role spec explicitly requires a different control.
- KPI cards remain keyboard-accessible when clickable and include correct `aria-label`.
- No role-forbidden action is introduced through shared slots.
- No raw `<button>` KPI cards, raw chart skeleton divs, or raw section-card wrappers remain in targeted surfaces unless documented as deferred.
- Reports/audit analytics cards use shadcn-vue shells and `Skeleton`/`Alert`/`Empty` states instead of custom loading/error markup.
- Existing dashboard store/composable contracts remain unchanged unless a page already needs missing backend fields.
- Visual rhythm matches the `/dashboard2` evidence: spacious metric cards, subtle gradient analytics cards, consistent actions in card headers, and responsive 1/2/4 column behavior.

## Verification Commands

Use focused verification first:

```bash
cd frontend
bunx vitest run app/tests/unit/components app/tests/unit/pages app/tests/unit/stores/dashboard.store.test.ts
npx playwright test --project=visual tests/visual/dashboards.spec.ts
```

Use browser evidence for UI-facing replacements:

```bash
playwright-cli open http://localhost:3000/dashboard
playwright-cli screenshot --filename=output/playwright/dashboard-shared-cards.png
playwright-cli open http://localhost:3000/reports
playwright-cli screenshot --filename=output/playwright/reports-shared-cards.png
```
