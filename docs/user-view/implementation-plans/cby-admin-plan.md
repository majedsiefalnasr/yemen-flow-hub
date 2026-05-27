# CBY_ADMIN UX/UI Implementation Plan

Source spec: `docs/user-view/cby-admin.md`

## Implementation Goal

Build a national platform oversight and administration workspace. CBY Admin has broad visibility and admin powers, but no workflow actor powers: no voting, claim, SWIFT upload, FX completion, or request decisioning.

## Existing Touchpoints

- `frontend/app/components/dashboard/CbyAdminDashboard.vue`
- `frontend/app/pages/requests/index.vue`
- `frontend/app/pages/requests/[id]/index.vue`
- `frontend/app/pages/merchants.vue`
- `frontend/app/pages/reports.vue`
- `frontend/app/pages/audit.vue`
- `frontend/app/pages/admin/cby-staff.vue`
- `frontend/app/pages/admin/entities.vue`
- `frontend/app/pages/admin/workflow-docs.vue`
- `frontend/app/pages/admin/roles.vue`
- `frontend/app/pages/admin/settings.vue`
- `frontend/app/composables/useAudit.ts`
- `frontend/app/composables/useUsers.ts`
- `frontend/app/composables/useBanks.ts`
- `frontend/app/composables/useDocumentTypes.ts`
- `frontend/app/composables/useAdminSettings.ts`
- `backend/app/Http/Controllers/Api/*`

## Tasklist

### 1. Role Surface And Navigation

- [x] Verify sidebar renders dashboard, requests, notifications, merchants, reports, audit, CBY staff, entities, workflow docs, roles, and settings.
- [x] Do not render staff bank page, new request, external FX confirmation queue as Director operation, support claim, SWIFT upload, vote, close/finalize, or FX completion controls.
- [x] Add read-only oversight badges on workflow pages.
- [x] Keep platform admin mutation actions visually separate from workflow actor actions.

### 2. Dashboard

- [x] Build strategic oversight dashboard, not task inbox.
- [x] Header includes date range, bank filter, refresh, last-updated, and executive summary export.
- [x] Add six KPI cards: active workflow requests, SLA violations, open executive voting, FX pending, bank risk alerts, system availability.
- [x] Add workflow pressure map table/heatmap with active count, average age, SLA risk, and trend.
- [x] Add executive voting oversight with waiting member names.
- [x] Add bank risk intelligence sortable table.
- [x] Add compliance and audit signals cards.
- [x] Add critical events feed excluding low-value operational noise.
- [x] All widgets respond to global filters and click through to filtered requests/audit.
- [x] Use skeletons, inline error, and no decorative charts.

### 3. Requests Registry

- [x] Build national workflow registry and investigation surface.
- [x] Add smart summary bar for SLA breaches, delayed voting, FX delays, and bank risk anomalies.
- [x] Implement primary tabs: active, needs attention, executive voting, FX pending, rejected, completed, all requests.
- [x] Add toolbar: search, export, refresh, saved views, column visibility.
- [x] Add advanced filters drawer with bank, workflow stage, exact status, date, amount, SLA, voting, pending member, FX, repeated support returns, high value.
- [x] Keep filter state URL-shareable.
- [x] Table columns: reference, bank, merchant, amount, current stage, age, SLA state, voting state, FX state, last activity, risk flags, view-only actions.
- [x] Show business-friendly status badge plus muted internal enum metadata.
- [x] Add quick preview drawer with workflow timeline, blocker, latest docs, voting, FX progress, audit summary, risk flags.
- [x] Do not render workflow action controls.

### 4. Request Detail

- [x] Build read-only investigation detail.
- [x] Header shows request summary, bank, merchant, amount, status, exact enum metadata, age, SLA, risk flags, oversight badge.
- [x] Primary actions: export case file, open audit view, copy link.
- [x] Add Current Blocker panel as highest-priority detail component.
- [x] Add updated workflow progress with returned loops and rejected branches.
- [x] Tabs: overview, workflow timeline, documents, executive voting, FX confirmation, parties, audit trail.
- [x] Documents tab shows document provenance and download-only actions where permitted.
- [x] Executive Voting tab shows session state, pending members, votes per visibility policy, final outcome.
- [x] FX tab tracks waiting for SWIFT, SWIFT uploaded, FX request uploaded, FX pending, completed.
- [x] Add right-side intelligence panel with owner role, blocker, SLA, age, pending actors, latest activity, risk flags, linked audit.
- [x] No workflow controls anywhere.

### 5. Merchants

- [x] Build cross-bank merchant risk registry, not simple CRUD.
- [x] Add smart summary bar for duplicates, cross-bank merchants, repeated rejections, missing data, high-value merchants.
- [x] Add tabs: all, duplicates, high risk, missing data, high activity, inactive.
- [x] Add search and filters by merchant, registry, tax ID, bank, status, risk.
- [x] Table prioritizes banks, active/total requests, amount, rejection/return rate, duplicate risk, last activity, status.
- [x] Add merchant profile drawer with associated banks, request history, duplicate candidates, completeness, risk signals, audit.
- [x] Keep mutation actions secondary and audit-heavy.

### 6. Reports

- [x] Build historical analysis page separate from live dashboard.
- [x] Add global filters: date, bank, stage, goods category, currency, amount, risk, outcome.
- [x] Add tabs: executive summary, bank performance, workflow SLA, decisions/outcomes, executive voting, SWIFT and FX, compliance/risk.
- [x] Support PDF, Excel/CSV, and scheduled report actions.
- [x] Exports must respect active filters and include multiple sheets where appropriate.
- [x] Use trend, ranking, bar, heatmap, and funnel charts only where they answer a management question.

### 7. Audit

- [x] Build security/compliance investigation center, not raw log dump.
- [x] Add event categories: workflow, voting, documents, access/security, permissions, admin changes, system.
- [x] Add smart summary bar for permission denials, suspicious logins, sensitive role changes, high-risk document downloads, failed workflow attempts.
- [x] Add tabs: all, security, workflow, documents, permissions, admin changes, anomalies.
- [x] Add filters: time, actor, role, bank, request, merchant, category, event type, severity, IP, device, outcome.
- [x] Add compact events table and read-only event detail drawer.
- [x] Add anomaly grouping for repeated denials, failed logins, unusual downloads, role change followed by sensitive action.
- [x] Audit exports include filters, event IDs, generated-by, and timestamp.

### 8. CBY Staff Management

- [x] Build CBY-side IAM page.
- [x] Add access health summary: active users, MFA %, inactive, critical roles, recent role changes, active sessions, permission-denial alerts.
- [x] Add tabs: all CBY users, support committee, executive committee, administration, suspended/inactive, security review.
- [x] Role select includes only `SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, and `CBY_ADMIN`.
- [x] Enforce Director and Executive Member exclusivity with clear inline explanation.
- [x] Block or strongly prevent deactivating last active Director or disabling all executive voters.
- [x] Add workload context per role before deactivation.
- [x] Sensitive actions require confirmation, reason where appropriate, and audit logging.
- [x] Bulk actions remain conservative: export, require MFA, force logout if supported.

### 9. Entities, Document Rules, Permissions Reference, Settings, Profile

- [x] Entities page prioritizes operational health, missing roles, SLA risk, SWIFT/FX delays, and governance actions over CRUD.
- [x] Entity profile shows workflow health, role coverage, risk/compliance, recent activity, linked requests.
- [x] Document Rules page distinguishes uploaded, generated, generated plus re-uploaded, and template-based documents.
- [x] Document Rules must represent SWIFT PDF, FX request PDF, FX request template, and external FX confirmation lifecycle.
- [ ] Add impact preview before saving high-risk document-rule changes.
- [x] Permissions Reference is read-only governance intelligence with role cards, ownership map, document authority matrix, critical rules, and surface access matrix.
- [x] Settings are governance-sensitive with General, Security, Notifications, Workflow/SLA, Integrations, Audit/Compliance tabs.
- [x] High-risk settings require confirmation, reason, re-auth if needed, and audit log.
- [x] Profile emphasizes MFA, active sessions, governance activity, recent exports, and permission denials.

### 10. Backend And Data Readiness

- [x] Confirm dashboard stats expose all governance KPIs, workflow pressure rows, voting sessions, bank risk rows, compliance signals, and critical events.
- [x] Confirm request index can support CBY advanced filters server-side for scale.
- [x] Confirm audit API supports filters, stats, duplicates, risk indicators, and event detail metadata.
- [x] Confirm user controller enforces CBY-only role assignment and critical-role protections.
- [x] Confirm bank/entity controller exposes risk, missing roles, workflow health, and suspension impact.
- [ ] Confirm document rules/template API supports lifecycle and impact preview needs or add backend stories.
- [x] Confirm reports endpoints support cross-bank filters, export formats, and scheduled report metadata if planned.

## Tests List

### Frontend Unit And Component

- [ ] `role-surfaces.test.ts`: CBY Admin has admin/oversight surfaces only and no workflow actor actions.
- [ ] `CbyAdminDashboard.test.ts`: KPI severity, filter propagation, workflow pressure map, voting waiting names, risk signals.
- [ ] `cby-admin-requests.test.ts`: smart summary, tabs, advanced drawer, status dual presentation, preview drawer, view-only actions.
- [ ] `RequestDetailPage.test.ts`: blocker panel, investigation tabs, right intelligence panel, no action controls.
- [ ] `merchants-page.test.ts`: cross-bank risk tabs, duplicate indicators, profile drawer.
- [ ] `reports.test.ts`: report tabs, filters, export actions.
- [ ] `audit.test.ts`: event categories, filters, details drawer, anomaly groups.
- [ ] `CbyAdminPages.test.ts`: cby staff, entities, workflow docs, roles, settings route rendering.
- [ ] `StaffModal12_2.test.ts`: CBY role constraints and Director/Executive exclusivity.
- [ ] `settings.test.ts`: sensitive setting warning and unsaved changes bar.

### Frontend Store And Composable

- [ ] `useAudit.test.ts`: filters, stats, risk indicators, pagination.
- [ ] `useUsers.test.ts`: CBY role assignment errors and user filters.
- [ ] `useBanks.test.ts`: entity filters and risk data.
- [ ] `useDocumentTypes.test.ts`: document rules lifecycle fields and template data.
- [ ] `useAdminSettings.test.ts`: high-risk setting update, reset, SMTP update.
- [ ] `useReports.test.ts`: cross-bank filters and export paths.
- [ ] `dashboard.store.test.ts`: CBY admin optional governance arrays normalize.

### Backend Feature

- [ ] `CbyAdminDashboardStatsTest.php`: governance KPIs, workflow pressure, voting oversight, bank risk.
- [ ] `AuditControllerTest.php`: filters, stats, duplicates, risk indicators.
- [ ] `UserControllerTest.php`: CBY-only role assignment, Director/Executive exclusivity, critical-role deactivation blocks.
- [ ] `BankControllerTest.php`: entity risk fields, missing role filters, suspension impact.
- [ ] `DocumentTypeController` tests if added: lifecycle rules, templates, impact preview, audit logging.
- [ ] `ReportControllerTest.php`: cross-bank report filters and exports.
- [ ] `AdminSettingsControllerTest.php`: sensitive setting audit and validation.
- [ ] `DocumentDownloadPermissionTest.php`: CBY Admin download-only authority, no upload/finalize.

### E2E, Visual, Accessibility

- [ ] Playwright CBY Admin oversight flow: dashboard filter to requests, preview drawer, request detail, audit link.
- [ ] Playwright CBY staff flow: add CBY user, reject bank role, reject Director/Executive conflict, deactivation guard.
- [ ] Playwright document rules flow: open rule, view impact preview, cancel dangerous change.
- [ ] Playwright audit investigation flow: filter denied events, open detail drawer, export evidence.
- [ ] Visual snapshots: dashboard, requests registry, request detail, audit, CBY staff, entities, document rules, roles reference, settings.
- [ ] Accessibility: high-density tables keyboard navigable, filter drawers labelled, event drawers have titles, severity not color-only, no horizontal scroll at mobile fallback.

