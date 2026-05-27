# COMMITTEE_DIRECTOR UX/UI Implementation Plan

Source spec: `docs/user-view/committee-director.md`

## Implementation Goal

Build a CBY-global governance and completion console for voting closure/finalization and external FX confirmation completion. The Director is a workflow authority, not a generic admin.

## Existing Touchpoints

- `frontend/app/components/dashboard/ExecutiveDashboard.vue`
- `frontend/app/pages/customs/index.vue`
- `frontend/app/pages/customs/[id]/print.vue`
- `frontend/app/pages/requests/[id]/customs-preview.vue`
- `frontend/app/components/requests/ActionsPanel.vue`
- `frontend/app/stores/voting.store.ts`
- `frontend/app/composables/useVoting.ts`
- `frontend/app/composables/useRequests.ts`
- `backend/app/Services/Voting/VotingService.php`
- `backend/app/Services/Customs/CustomsService.php`
- `backend/app/Http/Controllers/Api/CustomsController.php`

## Tasklist

### 1. Role Surface And Navigation

- [x] Verify sidebar renders dashboard, requests, external FX confirmation (`/customs`), reports, audit, notifications, and settings.
- [x] Use "تأكيد المصارفة الخارجية" labels everywhere while retaining `/customs` legacy URL.
- [x] Do not render staff, merchants, entities, document rules, or settings-admin surfaces.
- [x] Do not render SWIFT upload, support claim, or generic CBY admin actions.
- [x] Ensure Director and Executive Member role exclusivity is reflected in admin/IAM plan.

### 2. Dashboard

- [x] Build governance and completion console with cross-bank chip.
- [x] Add composite action-required strip for ready-to-close, ready-to-finalize, FX pending, and tied sessions.
- [x] Hide strip entirely when no Director-exclusive action is pending.
- [x] Add KPI cards: active voting sessions, FX confirmation pending, finalized decisions, rejection decisions.
- [x] Add voting lifecycle table sorted ready-to-close first, then own pending vote, waiting members, finalize-pending.
- [x] Add columns: my vote, progress, ready-to-close chip, tied indicator, age, action.
- [x] Add FX confirmation queue table for `FX_CONFIRMATION_PENDING` and legacy incomplete states.
- [x] Use skeleton, healthy empty state, and inline retry error.

### 3. Requests List

- [x] Implement tabs: ready_to_close, ready_to_finalize, pending_my_vote, voting_open, fx_pending, swift_in_progress, approved, completed, rejected, all.
- [x] Keep Director-actionable tabs first.
- [x] Add search, bank filter, column visibility, export, saved views, and refresh.
- [x] Add Ready to Close and FX Document State columns.
- [x] Row actions route to vote, close, finalize, tie-break, or FX detail based on state.
- [x] Keep filter state URL-shareable.

### 4. Request Detail, Voting, And Finalization

- [x] Header includes reference, canonical status, print, and case-file export.
- [x] Banner priority: ReadyToClose, TieBreak, ReadyToFinalize, FXReady, VotingPending, Locked.
- [x] Inline VotingPanel includes Executive Member behavior plus Director additions.
- [x] Render own vote buttons when voting open and Director has not voted.
- [x] Render Close Session only when all active executive members have voted.
- [x] When close is blocked, disabled tooltip lists pending member names.
- [x] Close confirmation summarizes tally and transition to `EXECUTIVE_VOTING_CLOSED`.
- [x] Render Finalize Decision when closed and non-tied; modal states resulting status and downstream effect.
- [x] Render Tie-break Resolution when closed and tied; require selected outcome and reason.
- [x] Render override action only if backend/platform policy enables it; require reason and audit distinction.
- [x] Preserve original tally plus Director tie-break/override record after finalization.
- [x] Do not render voting controls after finalization.

### 5. FX Confirmation Completion

- [x] Actions panel handles `FX_CONFIRMATION_PENDING` sequence: download generated PDF, upload signed/stamped PDF, complete.
- [x] Enforce sequential order in UI (three-step download→upload→complete; pending backend signed-upload endpoint).
- [x] Completion button disabled until signed PDF uploaded (pending backend signed-upload endpoint).
- [x] Confirmation modal clearly states request becomes completed and action is irreversible.
- [x] FX Confirmation tab shows mini lifecycle: generated, downloaded, signed, uploaded, completed.
- [x] Documents tab includes every document including generated and signed external FX confirmation.
- [x] `/customs` page shows Ready for Issuance and Completed columns.
- [x] `/customs/[id]/print` and `/requests/{id}/customs-preview` use external FX terminology.
- [x] Preserve legacy `CUSTOMS_DECLARATION_ISSUED` handling as migration compatibility, not new copy.

### 6. Reports, Audit, Notifications, Settings, Profile

- [x] Reports extend Executive Member reports with Director-specific voting lifecycle and FX completion analytics.
- [x] Audit access is read-only and governance-focused.
- [x] Notifications include all-members-voted, finalize-pending, tied tally, SWIFT uploaded/FX ready, FX completed, high-value, Director SLA, and forbidden Director endpoint attempts.
- [x] MFA required in settings/profile.
- [x] Profile stats include sessions closed, decisions finalized, FX confirmations completed, average time-to-close, and average time-to-FX-completion.

### 7. Backend And Data Readiness

- [x] Confirm voting detail exposes all active members, pending names, tally, tie state, Director vote state, ready-to-close, and finalization state.
- [x] Confirm `VotingService::closeSession()` blocks until all active members have voted.
- [x] Confirm `VotingService::finalizeDecision()` and override/tie-break paths are audited with Director role.
- [x] Confirm approval finalization auto-chains to the SWIFT stage used by current workflow docs.
- [x] Identify gap: current customs endpoints generate/download generated PDF; signed re-upload and explicit three-step completion may need backend work if not already present.
- [x] Confirm FX completion is wrapped in one transaction with row locking.
- [x] Confirm external FX document download policy allows Director.

## Tests List

### Frontend Unit And Component

- [ ] `role-surfaces.test.ts`: Director has close/finalize/external FX actions but no admin/IAM/SWIFT/support actions.
- [ ] `ExecutiveDashboard.test.ts`: Director composite strip, voting lifecycle queue, FX queue, KPI links.
- [ ] `workflow-buckets.test.ts`: Director buckets and ready-to-close matching.
- [ ] `VotingPanel.test.ts`: close disabled with pending names, close enabled when all voted, finalize, tie-break, override policy.
- [ ] `VotingRequestDetailPage.test.ts`: banner priority and Director controls.
- [ ] `ActionsPanel.voting.test.ts`: Director-only controls absent for Executive Member.
- [ ] `customs.queue.test.ts`: external FX queue labels, ready/completed columns, empty states.
- [ ] `CustomsPreviewPage.test.ts` and `CustomsPrintPage.test.ts`: terminology and document state.

### Frontend Store And Composable

- [ ] `voting.store.test.ts`: close, finalize, director override, error mapping.
- [ ] `useVoting.test.ts`: pending member names and tie state parsing.
- [ ] `useRequests.customs-preview.test.ts`: preview and download handling.
- [ ] Add FX signed upload composable/store tests when backend endpoint exists.

### Backend Feature

- [ ] `VotingEngineTest.php`: cannot close before all votes; close after all; finalize non-tied; tie-break reason required; override audited if enabled.
- [ ] `WorkflowVotingActorTest.php`: Director actor role recorded.
- [ ] `CustomsDeclarationTest.php`: generated PDF, download audit, completion transaction.
- [ ] Add signed external FX upload tests if endpoint is introduced.
- [ ] `CustomsDownloadPermissionTest.php`: Director access allowed.
- [ ] `DashboardStatsTest.php`: Director queues and FX pending counts.

### E2E, Visual, Accessibility

- [ ] Playwright Director voting flow: own vote, close when all voted, finalize approved, verify SWIFT handoff.
- [ ] Playwright blocked close flow: pending names visible in tooltip and no API call.
- [ ] Playwright tie-break flow: closed tied session requires reason and finalizes.
- [ ] Playwright FX flow: download generated PDF, upload signed PDF, complete request, completed state.
- [ ] Visual snapshots: dashboard composite strip, voting detail ready-to-close, tie-break modal, FX pending detail, `/customs` queue.
- [ ] Accessibility: complex action panel keyboard order, tooltips accessible, modal focus trap, upload controls labelled, no color-only tie/ready states.

