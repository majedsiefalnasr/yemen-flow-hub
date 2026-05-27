# BANK_ADMIN UX/UI Implementation Plan

Source spec: `docs/user-view/bank-admin.md`

## Implementation Goal

Build a bank-scoped administration workspace for staff, merchants, reports, and read-only request portfolio oversight. This role must never become a hidden workflow approver.

## Existing Touchpoints

- `frontend/app/components/dashboard/BankAdminDashboard.vue`
- `frontend/app/pages/requests/index.vue`
- `frontend/app/pages/requests/[id]/index.vue`
- `frontend/app/pages/staff.vue`
- `frontend/app/components/staff/StaffModal.vue`
- `frontend/app/pages/merchants.vue`
- `frontend/app/components/merchants/*`
- `frontend/app/pages/reports.vue`
- `frontend/app/composables/useUsers.ts`
- `frontend/app/composables/useMerchants.ts`
- `frontend/app/composables/useReports.ts`
- `backend/app/Http/Controllers/Api/UserController.php`
- `backend/app/Http/Controllers/Api/MerchantController.php`
- `backend/app/Http/Controllers/Api/ReportController.php`

## Tasklist

### 1. Role Surface And Navigation

- [x] Verify sidebar renders dashboard, requests, notifications, merchants, staff, reports, and settings.
- [x] Keep New Request out of primary nav and dashboard primary actions.
- [x] If `/requests/new` remains reachable as fallback, make it low-prominence and guarded by clear requester identity.
- [x] Hide all approve, return, reject, claim, vote, SWIFT, and FX completion controls.
- [x] Keep all data own-bank scoped.

### 2. Dashboard

- [x] Build bank operational overview, not workflow inbox.
- [x] Header includes date-range filter, refresh, last-updated timestamp, and bank summary export.
- [x] Add read-only oversight chip.
- [x] Add four clickable KPI cards: total, in process, approved/completed, rejected.
- [x] Add conditional operational health strip for rejection spike, stalled CBY stages, missing reviewer/SWIFT coverage, repeated support returns, or suspended staff with active responsibilities.
- [x] Hide operational health strip when no risk exists.
- [x] Add quick actions: requests, merchants, staff, reports.
- [x] Add monthly trend chart using existing chart components or Unovis wrapper.
- [x] Add recent bank requests table as read-only, max 8 rows.
- [x] Use skeleton, date-filter empty state, and inline retry error.

### 3. Requests List And Detail

- [x] Implement tabs: pending, at_cby, swift_fx, completed, rejected, all.
- [x] Header includes read-only oversight chip and no New Request CTA.
- [x] Toolbar includes search, column visibility, export, saved views, and refresh.
- [x] Limit bulk toolbar to export/print/clear.
- [x] Table shows Created By, Current Owner, Age in Stage, and View-only action.
- [x] Request detail shares reviewer read-only layout but actions panel contains only current owner/stage text.
- [x] For drafts created by current admin fallback, render edit draft and delete draft only.
- [ ] Documents tab allows request docs, SWIFT, and FX request downloads but shows external FX confirmation as locked row.
- [x] Never render decision buttons or disabled workflow buttons.

### 4. Staff Management

- [x] Treat `/staff` as own-bank IAM, not HR.
- [x] Add access health summary cards: active staff, MFA enabled percent, inactive, critical role coverage, recent role changes, recent permission denials.
- [x] Add filters: search, role, status, MFA, last-login range.
- [x] Staff table columns: employee, role, status, MFA, last login, workload, created date, actions.
- [x] Restrict role select to `DATA_ENTRY` and `BANK_REVIEWER`.
- [x] Do not render CBY roles or `SWIFT_OFFICER` as assignable.
- [x] Use shadcn-vue Dialog for add/edit and AlertDialog for deactivation/reset/force logout.
- [ ] Add deactivation pre-check UI showing active drafts or active reviews.
- [x] Sensitive actions require confirmation and audit logging.

### 5. Merchant Registry

- [x] Build bank-scoped merchant registry focused on completeness and duplicate prevention.
- [x] Add quality summary: total, active, incomplete records, possible duplicates, inactive.
- [x] Add filters: search, status, completeness.
- [x] Table columns: name, registry number, tax ID, status, linked requests, last activity, completeness, actions.
- [x] Add/edit dialog validates required fields and uniqueness within bank.
- [ ] Duplicate warnings require explicit confirmation to proceed.
- [x] Empty state includes add merchant CTA.

### 6. Reports, Notifications, Settings, Profile

- [x] Reports remain own-bank only and lighter than CBY reports.
- [x] Add KPI strip, monthly trend, category donut, currency bar chart, submission heatmap, outcomes table, and scheduled reports if backend supports it.
- [x] Notifications are rollups only: staff lifecycle, merchant alerts, bank operational concerns, scheduled reports.
- [x] Exclude per-request workflow noise by default.
- [x] Profile stats highlight staff managed, merchants managed, and last admin action.

### 7. Backend And Data Readiness

- [x] Confirm `/api/dashboard/stats` supports bank admin extended metrics or add fields safely.
- [x] Confirm `UserController` role assignment validation blocks CBY roles and `SWIFT_OFFICER` for bank admins.
- [x] Confirm staff list filters own bank and excludes CBY users.
- [x] Confirm merchant CRUD is own-bank scoped and duplicate detection supports warning metadata.
- [x] Confirm reports are bank-scoped for bank admins.
- [ ] Confirm external FX confirmation download is denied to Bank Admin.

## Tests List

### Frontend Unit And Component

- [ ] `role-surfaces.test.ts`: Bank Admin allowed admin-lite bank surfaces only; forbidden workflow controls.
- [ ] `BankAdminDashboard.test.ts`: KPI links, operational health strip conditions, chart/table states.
- [ ] `bank-admin-requests.test.ts`: read-only request table, tabs, no decision controls.
- [ ] `RequestDetailPage.test.ts`: read-only actions panel and locked external FX row.
- [ ] `StaffPage.test.ts`: staff filters, role options restricted, workload column, deactivation warning.
- [ ] `StaffModal.test.ts`: Data Entry/Bank Reviewer only, validation and server errors.
- [ ] `merchants-page.test.ts` and merchant component tests: quality summary, duplicate warning, add/edit flows.
- [ ] `reports.test.ts`: own-bank report filters and export actions.

### Frontend Store And Composable

- [ ] `useUsers.test.ts`: bank-scoped user filters and role validation errors.
- [ ] `useMerchants.test.ts`: duplicate warning and CRUD refresh behavior.
- [ ] `useReports.test.ts`: bank report fetch and export path.
- [ ] `dashboard.store.test.ts`: bank admin extended stats normalize optional fields.

### Backend Feature

- [ ] `BankAdminStaffManagementTest.php`: role assignment restrictions, own-bank users, deactivation audit.
- [ ] `MerchantControllerTest.php`: bank scoping, duplicate fields, CRUD validation.
- [ ] `ReportControllerTest.php`: bank report scope and exports.
- [ ] `DocumentDownloadPermissionTest.php`: Bank Admin denied external FX confirmation.
- [ ] `ImportRequestControllerTest.php`: read-only portfolio scope.
- [ ] `BankAdminRbacTest.php`: no workflow decision authority.

### E2E, Visual, Accessibility

- [ ] Playwright Bank Admin navigation: dashboard, staff, merchants, reports visible; workflow controls absent.
- [ ] Playwright staff flow: add Data Entry, reject SWIFT/CBY role option, deactivate with impact warning.
- [ ] Playwright merchant flow: add/edit merchant and duplicate warning.
- [ ] Visual snapshots: dashboard, staff table, merchant table, reports page, read-only request detail.
- [ ] Accessibility: Dialog labels, AlertDialog focus trap, tables keyboard navigable, management actions have accessible names.

