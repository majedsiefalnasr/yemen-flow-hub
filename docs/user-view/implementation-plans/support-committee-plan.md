# SUPPORT_COMMITTEE UX/UI Implementation Plan

Source spec: `docs/user-view/support-committee.md`

## Implementation Goal

Build a CBY-global support review console centered on claim ownership. The user can decide only requests personally claimed by them, with heartbeat/TTL state visible and recoverable.

## Existing Touchpoints

- `frontend/app/components/dashboard/SupportCommitteeDashboard.vue`
- `frontend/app/composables/useClaimLifecycle.ts`
- `frontend/app/components/banners/ActiveReviewBanner.vue`
- `frontend/app/components/banners/ClaimedByOthersBanner.vue`
- `frontend/app/components/banners/UnclaimedBanner.vue`
- `frontend/app/components/requests/ActionsPanel.vue`
- `frontend/app/components/requests/DocumentChecklist.vue`
- `frontend/app/stores/requests.store.ts`
- `backend/app/Console/Commands/ExpireClaimsCommand.php`
- `backend/app/Services/Workflow/WorkflowService.php`
- `backend/routes/api.php`

## Tasklist

### 1. Role Surface And Navigation

- [x] Verify sidebar renders dashboard, requests, notifications, and settings only.
- [x] Keep this role CBY-global, not bank-scoped.
- [x] Hide all executive voting, SWIFT upload, FX completion, admin, staff, merchant, and report controls unless later policy explicitly grants read-only reports.
- [x] Ensure decision buttons are hidden when request is unclaimed or claimed by another member.

### 2. Dashboard

- [x] Build claim-aware queue console.
- [x] Header includes `نطاق عبر البنوك` chip.
- [x] Add active-claim strip as highest priority when current user has claims.
- [x] Add KPI cards: waiting for claim, active by me, claimed by others, recently approved.
- [x] Add quick actions: support queue and notifications.
- [x] Add support queue table with unclaimed, claimed by me, and claimed by others states.
- [x] Use row tinting plus labels/chips, not color alone.
- [x] Sort unclaimed/oldest work first and active own claims prominently.
- [x] Add claim-state action: claim, resume, or view.
- [x] Use skeleton, healthy empty state, and inline retry error.

### 3. Requests List

- [x] Implement tabs: waiting, my_claims, in_progress, approved, returned, rejected, all.
- [x] Keep my_claims and waiting operationally first.
- [x] Add search across reference, supplier, merchant, invoice, and bank.
- [x] Add bank filter, column visibility, export, refresh, and hide-claimed-by-others toggle.
- [x] No bulk decisions.
- [x] Table columns include bank, claim owner, age in stage, and claim-state action.
- [x] Persist hide-others and tab filters through query or user preferences.

### 4. Request Detail And Claim Lifecycle

- [x] Header includes status badge and claim state chip.
- [x] Banner priority: ActiveReview, ClaimedByOthers, Unclaimed, Correction, Locked.
- [x] ActiveReviewBanner shows acquired time, TTL countdown, heartbeat indicator, and release action.
- [x] Heartbeat posts every 60 seconds and resets 15-minute TTL.
- [x] Heartbeat failure changes indicator to amber and retries.
- [x] Repeated heartbeat failure converts banner to error state with refresh action.
- [x] ClaimedByOthersBanner hides all decision buttons and shows owner.
- [x] UnclaimedBanner offers atomic claim action and handles 409 conflict by refreshing claim state.
- [x] Activity log includes claim acquire, release, and auto-expire events.
- [x] Documents tab lists intake-stage documents only; do not list downstream SWIFT/FX docs.
- [x] Actions panel:
  - [x] Unclaimed: claim action only.
  - [x] Claimed by me: approve, return, reject, release.
  - [x] Claimed by others: informational note only.
  - [x] Terminal: empty panel with LockedBanner context.
- [x] Approval confirmation states that executive voting opens automatically downstream.
- [x] Return dialog requires reason and optional flagged fields/documents.
- [x] Reject dialog explains rejection goes back to Bank Reviewer and requires longer reason.
- [x] Claim expiry modal offers return to queue or re-claim.

### 5. Notifications, Settings, Profile

- [x] Notifications include auto claim release, decision confirmation, optional new queue item, optional aging claim reminder.
- [x] Default new request in queue notification off; claim release and decision confirmations on.
- [x] Profile stats include reviews, approvals, returns, rejections, and average claim duration.
- [x] Settings expose claim-related notification preferences.

### 6. Backend And Data Readiness

- [x] Confirm claim endpoints are used: `POST`, `DELETE`, and heartbeat under `/api/workflow/{id}/claim-support-review`.
- [x] Confirm Redis TTL is 15 minutes and frontend heartbeat interval is 60 seconds.
- [x] Confirm request resources expose `is_claimed_by_me`, `claimed_by`, `claim_acquired_at`, and TTL/expiry metadata if required for countdown.
- [x] Confirm claim 409 response codes are machine-readable.
- [x] Confirm support decision endpoints reject missing/expired claims.
- [x] Confirm claim acquire/release/expiry are written to stage history and audit logs.

## Tests List

### Frontend Unit And Component

- [x] `role-surfaces.test.ts`: support role has no SWIFT, voting, FX, admin, staff, merchant controls.
- [x] `SupportCommitteeDashboard.test.ts`: active claim strip, KPI links, row tinting, queue actions, empty/error states.
- [x] `workflow-buckets.test.ts`: waiting, my_claims, and in_progress matching functions.
- [x] `ClaimBanners.test.ts`: active, others, unclaimed, heartbeat states, release action.
- [x] `RequestDetailClaimLogic.test.ts`: decision buttons hidden unless claimed by me.
- [x] `ActionsPanel.test.ts`: approve/return/reject/release only for `SUPPORT_REVIEW_IN_PROGRESS` claimed by me.
- [x] `DocumentChecklist.test.ts`: intake docs only for support.

### Frontend Store And Composable

- [x] `useClaimLifecycle.test.ts`: starts heartbeat, stops on unmount, handles failures, release, expiry, and 409.
- [x] `requests.store.workflow.test.ts`: support claim, release, approve, return, reject calls.
- [x] `dashboard.store.test.ts`: support queue arrays normalize.
- [x] `useNotifications.test.ts`: claim release notification priority.

### Backend Feature

- [x] `ClaimLifecycleTest.php`: atomic claim, 409 conflict, heartbeat, release, expiry command.
- [x] `WorkflowControllerTest.php`: support approve/return/reject requires valid claim.
- [x] `SupportReturnTest.php`: support return reason and notification.
- [x] `DashboardStatsTest.php`: waiting, active by me, claimed by others counts.
- [x] `DocumentDownloadPermissionTest.php`: support can access intake docs only.

### E2E, Visual, Accessibility

- [x] Playwright support flow: claim unclaimed request, heartbeat indicator visible, approve.
- [x] Playwright conflict flow: request claimed by another member renders read-only detail and no decision buttons.
- [x] Playwright expiry flow with mocked 409 `CLAIM_EXPIRED`: modal offers queue and re-claim.
- [x] Visual snapshots: dashboard, requests list claim states, active review detail, claimed-by-others detail.
- [x] Accessibility: countdown announced without noisy live region spam, buttons labelled, claim state not color-only.

