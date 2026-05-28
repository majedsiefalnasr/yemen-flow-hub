# DATA_ENTRY UX/UI Implementation Plan

Source spec: `docs/user-view/data-entry.md`

## Implementation Goal

Build a focused bank intake workspace for request drafting, correction, submission, and lifecycle tracking. This role must never see CBY operational controls, support claims, executive voting controls, SWIFT upload controls, or FX confirmation controls.

## Existing Touchpoints

- `frontend/app/pages/dashboard.vue`
- `frontend/app/components/dashboard/DataEntryDashboard.vue`
- `frontend/app/pages/requests/index.vue`
- `frontend/app/pages/requests/new.vue`
- `frontend/app/pages/requests/[id]/edit.vue`
- `frontend/app/pages/requests/[id]/index.vue`
- `frontend/app/components/requests/RequestsDataTable.vue`
- `frontend/app/components/requests/ActionsPanel.vue`
- `frontend/app/components/requests/DocumentChecklist.vue`
- `frontend/app/components/banners/CorrectionBanner.vue`
- `frontend/app/components/banners/LockedBanner.vue`
- `frontend/app/components/workflow/WorkflowProgress.vue`
- `frontend/app/stores/requests.store.ts`
- `frontend/app/composables/useRequestWizard.ts`
- `frontend/app/constants/workflow.ts`
- `frontend/app/constants/role-surfaces.ts`

## Tasklist

### 1. Role Surface And Navigation

- [x] Verify `ROLE_SURFACE_MATRIX.DATA_ENTRY` allows only dashboard, requests, new request, notifications, and settings.
- [x] Verify sidebar does not render admin, reports, merchants, staff, audit, SWIFT, voting, support-claim, or FX confirmation surfaces.
- [x] Keep `/requests/new` visible as the primary action only for Data Entry.
- [x] Ensure page route meta and `ROUTE_ROLE_MAP` reject direct access to role-forbidden pages.
- [x] Confirm profile is only available through the sidebar footer dropdown, not main navigation.

### 2. Dashboard

- [x] Implement the dashboard as a task launcher, not an analytics page.
- [x] Show greeting, bank-scoped subtitle, and primary `+ طلب جديد` action.
- [x] Add a conditional amber correction strip above KPIs for `BANK_RETURNED`, `SUPPORT_RETURNED`, and `DRAFT_REJECTED_INTERNAL`.
- [x] Make the strip link to `/requests?tab=returned` and include first returned reference plus truncated reason.
- [x] Build four clickable KPI cards: completed, under CBY processing, needs correction, drafts.
- [x] Make every KPI card route to its matching requests tab and expose `cursor-pointer`, focus state, and hover state.
- [x] Add quick actions: create request, follow requests, notifications with unread badge.
- [x] Render drafts table only when drafts exist; skip empty placeholder.
- [x] Render recent requests table with simplified business labels only.
- [x] Use skeleton KPI/table states and inline retry error card.
- [x] For no requests at all, hide KPI cards and render the single new-request empty state.

### 3. Requests List

- [x] Keep the list bank-scoped and remove bank filter for this role.
- [x] Implement ordered tabs: returned, draft, submitted, processing, completed, rejected, all.
- [x] Keep `returned` first and sync tab with query param.
- [x] Use simplified business status labels from `DATA_ENTRY_STATUS_LABELS`.
- [x] Add toolbar search, column visibility, filter-scoped export, and `+ طلب جديد`.
- [x] Add selection toolbar with export selected, print selected, and clear selection only.
- [x] Do not render bulk submit, delete, edit, review, claim, vote, SWIFT, or FX actions.
- [x] Render contextual row action as edit only for returned/editable statuses.
- [x] Implement filtered-empty, no-data-empty, loading, and inline error states.

### 4. New And Edit Request Wizard

- [x] Keep the four-step wizard: request data, supplier/shipment, required documents, review/submit.
- [x] Use VeeValidate/Zod schema ownership in existing wizard schema files.
- [x] Use shadcn-vue form composition and field-level errors close to controls.
- [x] Add merchant searchable select scoped to the user's bank.
- [x] Add duplicate invoice soft warning before submit while keeping backend authority.
- [x] Add document completion indicator and per-document upload cards.
- [x] Enforce PDF-only and 10 MB limit in UI before upload and preserve backend validation.
- [x] Implement inline upload errors per file slot and preserve successfully uploaded sibling files.
- [x] Add review step declaration checkbox that gates submit.
- [x] Add disabled-submit explanations for missing documents or declaration.
- [x] Persist draft safely at any step and never lose form input on network failure.
- [x] On returned edits, pin `CorrectionBanner` across all wizard steps.
- [x] On 403/409 while editing, freeze the form and show reload action.

### 5. Request Detail

- [x] Header includes reference, simplified status badge, print, and conditional edit button.
- [x] Render `CorrectionBanner` for returned/editable correction states.
- [x] Render `LockedBanner` for terminal or non-editable states.
- [x] Do not render active review, claim, voting, SWIFT, or FX controls.
- [x] Use business-language workflow progress and show return loops.
- [x] Provide tabs: overview, documents, parties, activity log.
- [x] Documents tab shows intake docs as downloadable and downstream documents as locked rows with explanatory tooltip.
- [x] Keep downstream documents visible as locked rows, not hidden, because this role tracks lifecycle but cannot download.
- [x] Actions panel shows edit/resubmit/submit only for editable states, read-only text for in-flight states, and empty panel for terminal states.
- [x] Activity log uses business phrases, not raw audit dump or raw enum names.

### 6. Notifications, Settings, Profile

- [x] Notifications include returns, bank/support outcomes, final decision, completion, and inactivity warning.
- [x] Notifications exclude claim transfers, voting tallies, audit alerts, and SLA escalations.
- [x] Implement tabbed notification table with unread styling, bulk read/export/print, and linked request navigation.
- [x] Settings use existing six-tab structure and save/discard unsaved bar where practical.
- [x] Profile uses the three-column identity/security/stats layout with role and bank affiliation read-only.

### 7. Backend And Data Readiness

- [x] Confirm `/api/dashboard/stats` includes `draft`, `returned`, `under_cby_processing`, `completed`, `draft_requests`, `returned_requests`, and `recent_requests`.
- [x] Confirm `/api/requests` supports role bucket tabs through status filters or frontend filtering with bank-scoped data only.
- [x] Confirm `ImportRequestResource` exposes return reason, returning actor, timestamps, and flagged fields/documents if available.
- [x] Confirm document download policy allows intake documents only and denies SWIFT/FX/external FX for this role.
- [x] Confirm terminal mutation attempts return `WORKFLOW_IMMUTABLE_STATE` with HTTP 403.

## Tests List

### Frontend Unit And Component

- [x] `role-surfaces.test.ts`: Data Entry allowed and forbidden surfaces exactly match the plan.
- [x] `nav-items.test.ts`: sidebar renders only Data Entry nav items.
- [x] `DataEntryDashboard.test.ts`: correction strip priority, KPI links, draft table conditional rendering, no-request empty state, skeleton and error state.
- [x] `workflow-status.test.ts`: raw CBY status labels are not primary labels for Data Entry.
- [x] `workflow-buckets.test.ts`: returned tab includes bank/support/internal returns and sits first.
- [x] `RequestsListAdvancedFilters.test.ts`: query tab sync and clear-filter behavior.
- [x] `RequestWizard.test.ts` and wizard step tests: step validation, draft save, declaration gate, duplicate warning, disabled-submit messages.
- [x] `DocumentChecklist.test.ts`: Data Entry can download intake docs and sees locked rows for downstream docs.
- [x] `ActionsPanel.test.ts`: no review, claim, vote, SWIFT, or FX actions for any status.
- [x] `CorrectionBanner.test.ts` and `LockedBanner.test.ts`: returned and terminal states render correct CTAs or no CTAs.

### Frontend Store And Composable

- [x] `dashboard.store.test.ts`: Data Entry stats normalize missing arrays.
- [x] `requests.store.create-update.test.ts`: save draft, update draft, submit paths.
- [x] `requests.store.upload.test.ts`: PDF upload error and success handling.
- [x] `useRequestWizard.test.ts`: step persistence and server conflict handling.
- [x] `useDocumentPermissions.test.ts`: downstream downloads denied for Data Entry.
- [x] `useNotifications.test.ts`: Data Entry notification filters and unread count behavior.

### Backend Feature

- [x] `DashboardStatsTest.php`: Data Entry dashboard counts by simplified buckets and own-bank scope.
- [x] `ImportRequestControllerTest.php`: own-bank request index/show only.
- [x] `WizardFieldsTest.php`: draft, edit, submit validation.
- [x] `DuplicateInvoiceTest.php`: duplicate warning/validation data.
- [x] `DocumentControllerTest.php`: PDF-only and 10 MB validation.
- [x] `DocumentDownloadPermissionTest.php`: intake allowed and SWIFT/FX/external FX denied.
- [x] `WorkflowControllerTest.php`: submit and terminal immutable behavior.

### E2E, Visual, Accessibility

- [x] Playwright Data Entry flow: login, empty dashboard, create draft, upload docs, submit, verify request becomes read-only.
- [x] Playwright returned correction flow: returned strip, edit wizard pinned correction banner, resubmit.
- [x] Playwright forbidden-surface sweep: direct URLs for audit/reports/staff/merchants/SWIFT/voting/FX are blocked and controls are not rendered.
- [x] Visual snapshots: dashboard, requests list, wizard step 3, request detail returned state, request detail terminal state.
- [x] Accessibility: keyboard wizard navigation, labelled inputs, visible focus, Arabic text fit at 375/768/1440 px, no color-only status indicators.

