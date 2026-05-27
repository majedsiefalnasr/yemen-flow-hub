# SWIFT_OFFICER UX/UI Implementation Plan

Source spec: `docs/user-view/swift-officer.md`

## Implementation Goal

Build a narrow bank-side upload workspace for post-executive-approval SWIFT and FX request documents. The role has one operational responsibility: provide a SWIFT reference, upload both PDFs, and submit the stage.

## Existing Touchpoints

- `frontend/app/components/dashboard/SwiftOfficerDashboard.vue`
- `frontend/app/pages/requests/[id]/swift.vue`
- `frontend/app/components/workflow/SwiftUploadForm.vue`
- `frontend/app/components/requests/DocumentChecklist.vue`
- `frontend/app/stores/requests.store.ts`
- `frontend/app/composables/useRequests.ts`
- `backend/app/Services/Documents/DocumentService.php`
- `backend/app/Http/Controllers/Api/DocumentController.php`
- `backend/tests/Feature/Workflow/SwiftUploadTest.php`

## Tasklist

### 1. Role Surface And Navigation

- [x] Verify sidebar renders dashboard, requests, notifications, and settings only.
- [x] Hide operations/admin/report/audit surfaces.
- [x] Ensure no non-SWIFT role can access the upload form route.
- [x] Keep SWIFT upload controls gated by both role and status.
- [x] Keep all data own-bank scoped.

### 2. Dashboard

- [x] Build focused upload queue, not analytics.
- [x] Header includes bank SWIFT officer subtitle and no primary page action.
- [x] Add pending SWIFT strip when `pending_swift_upload > 0`.
- [x] Add KPI cards: pending upload, uploaded, completed, executive rejected.
- [x] Add SWIFT queue table sorted oldest in `WAITING_FOR_SWIFT` first.
- [x] Include two-pill document progress: SWIFT and FX request.
- [x] Add inline template download link where supported.
- [x] Action is upload for `WAITING_FOR_SWIFT`, view otherwise.
- [x] Use healthy empty state, skeleton rows, and inline retry error.

### 3. Requests List

- [x] Implement tabs: pending_swift, swift_done, completed, rejected, all.
- [x] Keep pending_swift first.
- [x] Add search, column visibility, export, and refresh.
- [x] Do not render bulk upload.
- [x] Table shows documents two-pill indicator, age in stage, and upload/view action.
- [x] Use full canonical statuses.

### 4. Request Detail

- [x] Render PreApprovalLocked, SwiftReady, SwiftCompleted, and Locked banner states.
- [x] Do not render correction, claim, voting, or support controls.
- [x] Tabs: overview, documents, parties, activity log.
- [x] Overview is read-only; no editable fields.
- [x] Documents tab shows request docs plus SWIFT/FX request document rows and locks external FX confirmation.
- [x] Add upload shortcut only when status is `WAITING_FOR_SWIFT`.
- [x] Actions panel links to `/requests/{id}/swift` only for `WAITING_FOR_SWIFT`.
- [x] For `SWIFT_UPLOADED` or `FX_CONFIRMATION_PENDING`, render handoff text to Director.

### 5. SWIFT Upload Page

- [x] Gate form to `SWIFT_OFFICER` and status `WAITING_FOR_SWIFT`; wrong role/status renders access-denied state with specific reason.
- [x] Header includes reference, locked-data subtitle, breadcrumbs, and status badge.
- [x] Add locked-data summary panel with lock icons on all labels.
- [x] Add SWIFT reference input with required validation and soft format warning.
- [x] Add SWIFT PDF upload drop zone.
- [x] Add FX confirmation request PDF upload drop zone.
- [x] Add template download button for FX confirmation request template.
- [x] Enforce PDF-only and 10 MB client-side before upload.
- [x] Preserve selected files on network failure where browser security allows.
- [x] Submit button stays disabled until reference plus both PDFs are present.
- [x] Disabled tooltip identifies the missing requirement.
- [x] On success, show confirmation state and route back to queue.
- [x] On 403/409 state change, keep form intact and show reload banner.

### 6. Notifications, Settings, Profile

- [x] Notifications include newly waiting SWIFT request, upload confirmation, FX completion, and template version update.
- [x] Exclude voting, claim, audit, other-bank, and intake-stage notifications.
- [x] Settings default SWIFT-relevant notifications on.
- [x] Profile stats include uploads and average time-to-upload after executive approval.

### 7. Backend And Data Readiness

- [x] Confirm `DocumentService::uploadSwiftDocuments()` accepts SWIFT file, FX request file, and SWIFT reference in one transaction.
- [x] Confirm endpoint rejects single-file submit and non-PDF/oversized files.
- [x] Confirm transition advances to `SWIFT_UPLOADED` and downstream FX pending behavior matches docs.
- [x] Confirm `ImportRequestResource` exposes `has_swift_document`, `has_fx_request_document`, `swift_reference`, upload timestamps, and uploaded-by user.
- [x] Confirm template download endpoint exists or add it to document rules/template API before UI implementation.

## Tests List

### Frontend Unit And Component

- [ ] `role-surfaces.test.ts`: SWIFT role has only dashboard, requests, notifications, settings, and SWIFT upload action.
- [ ] `SwiftOfficerDashboard.test.ts`: action strip, KPIs, two-pill document state, queue sorting, empty/error states.
- [ ] `workflow-buckets.test.ts`: pending_swift and swift_done statuses.
- [ ] `SwiftUploadPage.test.ts`: wrong role/status locked state, reference validation, two-document gate, disabled reasons, success state.
- [ ] `SwiftUploadForm.test.ts` if separated: upload drop zones, PDF/size errors, retry state.
- [ ] `DocumentChecklist.test.ts`: external FX confirmation locked for SWIFT officer.
- [ ] `ActionsPanel.test.ts`: upload action only for `WAITING_FOR_SWIFT`.

### Frontend Store And Composable

- [ ] `requests.store.upload.test.ts`: multipart upload includes both files and swift reference.
- [ ] `useRequests.test.ts`: `swiftUpload` maps backend validation and 403/409 errors.
- [ ] `dashboard.store.test.ts`: swift queue stats normalize.

### Backend Feature

- [ ] `SwiftUploadTest.php`: both PDFs required, reference required, PDF-only, max size, own-bank role enforcement.
- [ ] `DocumentControllerTest.php`: multipart error payloads.
- [ ] `WorkflowControllerTest.php`: SWIFT transition and immutable states.
- [ ] `DocumentDownloadPermissionTest.php`: SWIFT officer denied external FX confirmation.
- [ ] `DashboardStatsTest.php`: pending/uploaded/completed/rejected counts.

### E2E, Visual, Accessibility

- [ ] Playwright SWIFT flow: pending queue to upload page, missing requirement tooltips, upload both PDFs, success state.
- [ ] Playwright forbidden role flow: non-SWIFT users cannot access `/requests/{id}/swift` form.
- [ ] Visual snapshots: dashboard, requests list, upload page empty, upload page completed, access-denied state.
- [ ] Accessibility: file inputs labelled, drop zones keyboard accessible, locked summary not mistaken as form inputs, no color-only document indicators.

