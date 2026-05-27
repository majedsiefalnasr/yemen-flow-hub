# BANK_REVIEWER UX/UI Implementation Plan

Source spec: `docs/user-view/bank-reviewer.md`

## Implementation Goal

Build a bank-side decisioning workspace for internal review, support-rejection follow-up, and downstream tracking. The UI must enforce segregation of duties by hiding decision controls for requests created by the current user.

## Existing Touchpoints

- `frontend/app/components/dashboard/BankReviewerDashboard.vue`
- `frontend/app/pages/requests/index.vue`
- `frontend/app/pages/requests/[id]/index.vue`
- `frontend/app/components/requests/ActionsPanel.vue`
- `frontend/app/components/requests/DocumentChecklist.vue`
- `frontend/app/components/banners/SegregationBlockedBanner.vue`
- `frontend/app/components/banners/CorrectionBanner.vue`
- `frontend/app/components/banners/LockedBanner.vue`
- `frontend/app/stores/requests.store.ts`
- `frontend/app/composables/useRequests.ts`
- `backend/app/Http/Controllers/Api/WorkflowController.php`
- `backend/app/Services/Workflow/WorkflowService.php`

## Tasklist

### 1. Role Surface And Navigation

- [ ] Verify sidebar renders dashboard, requests, notifications, and settings only.
- [ ] Remove primary new-request affordance from dashboard and nav.
- [ ] Do not render staff, merchants, reports, audit, support claim, voting finalization, SWIFT upload, or FX completion controls.
- [ ] Preserve own-bank scope across search, request list, detail, and documents.

### 2. Dashboard

- [ ] Build decisioning launcher with greeting and bank reviewer subtitle.
- [x] Add highest-priority support-rejected strip for `SUPPORT_REJECTED` follow-up decisions.
- [x] Add four clickable KPI cards: pending review, rejected by support, at CBY, approved/completed.
- [x] Add quick actions: review queue and all bank requests.
- [ ] Implement review queue table for `SUBMITTED` and `BANK_REVIEW`, sorted oldest first.
- [x] Show submitter name and role chip in queue rows to make segregation visible.
- [x] If current user created the request, hide decision navigation and show explanatory tooltip/note.
- [x] Add downstream tracking table for recently approved requests in CBY stages.
- [x] Use skeleton, reassuring empty review queue, hidden empty downstream section, and inline retry error.

### 3. Requests List

- [ ] Implement tabs: pending, support_rejected, bank_returned, support_returned, at_cby, completed, rejected, all.
- [ ] Keep pending and support_rejected first.
- [ ] Use full canonical status labels, not simplified Data Entry labels.
- [ ] Add toolbar search, column visibility, filter-scoped export, and created-by-me toggle.
- [ ] Add selection toolbar for export/print/clear only.
- [ ] Do not render bulk approve, reject, return, or review actions.
- [ ] Show Created By, Age in Stage, Last Activity, and contextual row action.
- [ ] Persist tab and created-by-me filters in URL query where practical.

### 4. Request Detail And Decisions

- [ ] Header includes reference, canonical status, print, and audit snapshot link to activity tab.
- [ ] Banner priority: SegregationBlocked, SupportRejected, Correction, Locked.
- [ ] Use full lifecycle progress with return loops.
- [ ] Tabs: overview, documents, parties, activity log.
- [ ] Documents tab allows own-bank request docs, SWIFT, FX request, and external FX confirmation downloads when available.
- [ ] Actions panel hides all decision buttons when current user created the request.
- [ ] `SUBMITTED`: render start review primary action.
- [ ] `BANK_REVIEW`: render approve, return, and terminal reject actions.
- [ ] Approve dialog includes summary and optional note.
- [ ] Return dialog requires reason with minimum length and optional flagged fields/documents.
- [ ] Terminal reject dialog uses destructive treatment, explicit irreversible warning, and longer required reason.
- [ ] `SUPPORT_REJECTED`: render keep rejected and return to Data Entry actions.
- [ ] Other statuses render read-only explanation only.
- [ ] Store reason text on workflow transition and ensure audit log receives actor role.

### 5. Notifications, Settings, Profile

- [ ] Notifications include new bank-review submissions, corrected resubmissions, support outcomes, executive outcomes, SWIFT upload, FX completion, and forbidden-action audit alerts.
- [ ] Exclude voting tally details, claim ownership transfers, and other-bank events.
- [ ] Profile stats include reviews performed, approvals, returns, and terminal rejections.
- [ ] Settings notification defaults are enabled for reviewer-relevant events.

### 6. Backend And Data Readiness

- [ ] Confirm dashboard stats expose `pending_review`, `rejected_by_support` or compatible field, `at_cby`, `approved_completed`, `review_queue`, and `downstream_queue`.
- [ ] Confirm request resources include `created_by_user`, stage age, last actor, support rejection reason, return reason, and document metadata.
- [ ] Confirm bank reviewer workflow endpoints enforce own-bank scope and segregation.
- [ ] Confirm terminal `BANK_REJECTED` cannot be reversed.
- [ ] Confirm support-rejected keep/acknowledge behavior is represented if required by UI state.

## Tests List

### Frontend Unit And Component

- [ ] `role-surfaces.test.ts`: reviewer has no new request, admin, support, SWIFT, voting, or FX completion surfaces.
- [ ] `BankReviewerDashboard.test.ts`: support-rejected strip, KPI links, review queue sort, downstream table visibility, empty/error states.
- [ ] `workflow-buckets.test.ts`: reviewer tabs and statuses match the plan.
- [ ] `RequestsListAdvancedFilters.test.ts`: created-by-me filter hides current user's submitted requests.
- [ ] `RequestDetailPage.test.ts`: banner priority and canonical labels.
- [ ] `ActionsPanel.test.ts`: start review, approve, return, terminal reject, support-rejected follow-up, and hidden actions under segregation.
- [ ] `DocumentChecklist.test.ts`: reviewer document download authority.
- [ ] `LockedBanner.test.ts`: terminal bank rejection uses irreversible language.

### Frontend Store And Composable

- [ ] `requests.store.workflow.test.ts`: bank review, approve, return, terminal reject, support-rejected return calls.
- [ ] `useRequests.workflow.test.ts`: API paths and error mapping for reviewer actions.
- [ ] `dashboard.store.test.ts`: reviewer dashboard stats normalize optional arrays.

### Backend Feature

- [ ] `DashboardStatsTest.php`: reviewer queue, support-rejected count, downstream data.
- [ ] `WorkflowControllerTest.php`: start review and approve transitions.
- [ ] `BankReturnTest.php`: return reason and actor role.
- [ ] `SupportReturnTest.php`: support rejected to bank reviewer follow-up path if applicable.
- [ ] `DocumentDownloadPermissionTest.php`: reviewer own-bank document access.
- [ ] `BankAdminRbacTest.php` or reviewer-specific RBAC test: reviewer cannot decide own created request.

### E2E, Visual, Accessibility

- [ ] Playwright reviewer flow: pending queue, start review, return with reason, approve with confirmation.
- [ ] Playwright segregation flow: request created by current user renders SegregationBlockedBanner and no decision buttons.
- [ ] Playwright support-rejected follow-up: keep/return options and reason handling.
- [ ] Visual snapshots: dashboard, requests list pending tab, request detail bank review, terminal reject dialog.
- [ ] Accessibility: AlertDialog title/description, reason textarea labels, focus trap, keyboard cancel/confirm, no color-only destructive state.

