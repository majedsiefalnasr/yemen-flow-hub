# EXECUTIVE_MEMBER UX/UI Implementation Plan

Source spec: `docs/user-view/executive-member.md`

## Implementation Goal

Build a CBY-global voting workspace where each executive member can see active sessions, cast exactly one vote, and track governance outcomes without receiving Director-exclusive finalization controls.

## Existing Touchpoints

- `frontend/app/components/dashboard/ExecutiveDashboard.vue`
- `frontend/app/components/requests/ActionsPanel.vue`
- `frontend/app/components/workflow` and voting components
- `frontend/app/stores/voting.store.ts`
- `frontend/app/composables/useVoting.ts`
- `backend/app/Services/Voting/VotingService.php`
- `backend/app/Http/Controllers/Api/VotingController.php`
- `backend/tests/Feature/Voting/VotingEngineTest.php`

## Tasklist

### 1. Role Surface And Navigation

- [x] Verify sidebar renders dashboard, requests, reports, notifications, and settings.
- [x] Do not render support claims, SWIFT upload, FX completion, audit, staff, merchants, admin, close-session, finalize, tie-break, or override controls.
- [x] Keep this role CBY-global.
- [ ] Strongly surface MFA enrollment prompt in profile/settings if missing.

### 2. Dashboard

- [x] Build voting workload console.
- [x] Header includes cross-bank chip.
- [x] Add action-required strip for `EXECUTIVE_VOTING_OPEN` sessions where current user has not voted.
- [x] Add three KPI cards: pending my vote, approval decisions, rejection decisions.
- [x] Add voting queue table sorted by my pending vote first.
- [x] Columns: reference, bank, supplier, amount, status, my vote, voting progress, age, action.
- [x] Emphasize rows where current user has not voted.
- [x] Use skeleton, healthy empty state, and inline retry error.

### 3. Requests List

- [x] Implement tabs: pending_my_vote, voted_by_me, pending_open, voting_open, voting_closed, approved, rejected, post_approval, all.
- [x] Keep pending_my_vote first.
- [x] Add search, bank filter, column visibility, export, and refresh.
- [x] No bulk vote action.
- [x] Table includes my vote, voting progress with pending count, age in stage, and vote/view action.
- [x] Keep tab state URL-shareable.

### 4. Request Detail And Voting Panel

- [x] Header includes reference, canonical status, and print.
- [x] Context banners: VotingPending, VotedConfirmation, Locked.
- [x] Do not render correction or claim banners.
- [x] Mount inline VotingPanel above tabs for executive-stage requests.
- [x] VotingPanel includes session status, opened timestamp, tally pills, member list, pending count, and vote buttons if allowed.
- [x] During open session, mask specific vote choices by default while still showing whether members have voted.
- [x] Render approve and reject buttons only when status is `EXECUTIVE_VOTING_OPEN` and current user has not voted.
- [x] Replace vote buttons with confirmation chip after successful vote.
- [x] Show tie-break placeholder only as Director handoff information.
- [x] Tabs: overview, documents, parties, voting, activity log.
- [x] Documents tab allows request docs, SWIFT, and FX request downloads; external FX confirmation row is locked.
- [x] Actions panel only anchors to VotingPanel or shows read-only state; do not duplicate vote buttons in right rail.
- [x] On `VOTING_SESSION_CLOSED` 409, roll back optimistic update and switch to read-only state.

### 5. Reports, Notifications, Settings, Profile

- [x] Reports are cross-bank, read-only governance analysis.
- [x] Add KPI strip: total requests, financing value, executive approval rate, this member participation rate, average voting duration.
- [x] Add charts: monthly trend, category distribution, amount by currency, voting participation heatmap.
- [x] Add voting analytics table for this member.
- [x] Notifications include new voting session, delayed vote reminder, session closed, final decision, high-value alert.
- [x] Exclude claim, SWIFT, FX completion, and bank-side operational noise.
- [x] Settings default voting notifications on and elevate MFA.
- [x] Profile stats include sessions participated, average time-to-vote, and approval percentage.

### 6. Backend And Data Readiness

- [x] Confirm voting detail exposes current user's vote state, tally, total members, pending members, and member list.
- [x] Confirm `VotingService::vote()` locks session and rejects duplicate votes.
- [x] Confirm vote response includes enough data to refresh tally and confirmation chip.
- [x] Confirm reports endpoint can return executive-member-scoped participation stats.
- [x] Confirm external FX confirmation download policy denies this role.

## Tests List

### Frontend Unit And Component

- [ ] `role-surfaces.test.ts`: Executive Member has vote action only, no close/finalize/FX/SWIFT/admin controls.
- [ ] `ExecutiveDashboard.test.ts`: pending vote strip, KPI links, queue sorting, row emphasis, empty/error states.
- [ ] `workflow-buckets.test.ts`: executive tab matching including `pending_my_vote` and `voted_by_me`.
- [ ] `VotingPanel.test.ts`: masked active votes, vote buttons for pending user, confirmation chip after vote, tie placeholder.
- [ ] `VotingRequestDetailPage.test.ts`: banners, tabs, locked external FX row, right rail anchor only.
- [ ] `ActionsPanel.voting.test.ts`: no Director controls for Executive Member.
- [ ] `reports.test.ts`: executive report sections and filters.

### Frontend Store And Composable

- [ ] `voting.store.test.ts`: vote success, duplicate vote error, session closed rollback.
- [ ] `useVoting.test.ts`: show/vote API payloads and error mapping.
- [ ] `dashboard.store.test.ts`: executive stats normalize optional voting queue.

### Backend Feature

- [ ] `VotingEngineTest.php`: one vote per member, pessimistic locking, duplicate vote rejected.
- [ ] `DashboardStatsTest.php`: pending my vote and decision counts.
- [ ] `ReportControllerTest.php`: member participation stats.
- [ ] `DocumentDownloadPermissionTest.php`: external FX confirmation denied.
- [ ] `UserRoleTest.php`: Executive Member and Director exclusivity if enforced at user layer.

### E2E, Visual, Accessibility

- [ ] Playwright executive flow: pending vote dashboard to detail, cast vote, confirmation chip, no change vote.
- [ ] Playwright closed-race flow: mocked 409 closes session before submit and rolls back optimistic UI.
- [ ] Playwright forbidden controls: close/finalize/tie/FX/SWIFT/admin controls absent.
- [ ] Visual snapshots: dashboard pending state, requests list, voting detail before vote, voting detail after vote, reports page.
- [ ] Accessibility: vote buttons have clear labels, member list not color-only, optimistic error announced, keyboard focus returns after vote submit.

