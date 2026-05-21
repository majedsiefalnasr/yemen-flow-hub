# Story 8.2: SUPPORT_RETURNED Status & Direct Return-from-Support Transition

## Story

**As** a `SUPPORT_COMMITTEE` member,
**I want** to return a `SUPPORT_REVIEW_IN_PROGRESS` request directly to data entry with a mandatory comment,
**So that** intake receives a clear, dedicated signal that the support committee flagged an issue distinct from internal bank rejection.

**Source:** `docs/09-user-stories-gap-analysis.md` §1.3, §2.5 Story 7.4, §6 S2 · USER-STORIES.md §7.4, Workflow C · Gap G2.

---

## Acceptance Criteria

### AC1 — Backend: New canonical status `SUPPORT_RETURNED`
**Given** the canonical enum
**Then** a new case `SUPPORT_RETURNED = 'SUPPORT_RETURNED'` exists with label
**And** `isEditable()` returns `true`
**And** `isTerminal()` returns `false`

### AC2 — Backend: New transition `support_return_to_intake`
**Given** `TransitionMap::definitions()`
**Then** the entry has `from: [SUPPORT_REVIEW_IN_PROGRESS]`, `to: SUPPORT_RETURNED`, `roles: [SUPPORT_COMMITTEE]`, `next_owner: DATA_ENTRY`

### AC3 — Backend: New endpoint `POST /api/workflow/{id}/support-return`
**Given** a request in `SUPPORT_REVIEW_IN_PROGRESS`, claimed by the current `SUPPORT_COMMITTEE` actor
**When** I call `POST /api/workflow/{id}/support-return` with `{ "comment": "..." }`
**Then** the request transitions to `SUPPORT_RETURNED`
**And** the support claim (Redis key `support_claim:{id}`) is released atomically as part of the transition
**And** `next_owner_role` becomes `DATA_ENTRY`
**And** `request_stage_history` + `audit_logs` carry the comment

### AC4 — Backend: Mandatory comment + claim guard
**Given** an empty comment → 422
**Given** the actor does not hold the active claim → 403 with `error_code: CLAIM_NOT_HELD`
**Given** the actor is not `SUPPORT_COMMITTEE` → 403 `WORKFLOW_FORBIDDEN_ROLE`

### AC5 — Backend: Submit transition accepts `SUPPORT_RETURNED`
**Given** a request in `SUPPORT_RETURNED`
**When** intake calls `submit`
**Then** the request transitions to `SUBMITTED` and re-enters the bank reviewer queue (NOT directly to support)

### AC6 — Backend: Notification
**Given** a successful return
**Then** `RequestReturnedNotification` is dispatched to all `DATA_ENTRY` users in the source bank
**And** payload includes `from_role: 'SUPPORT_COMMITTEE'`, `comment`, `reference_number`, `request_id`

### AC7 — Frontend: Enums + constants
**Then** `RequestStatus.SUPPORT_RETURNED` is defined
**And** `STATUS_LABEL[SUPPORT_RETURNED]` reads "إعادة من المساندة"
**And** `STATUS_PROGRESS[SUPPORT_RETURNED]` is 20
**And** `ROLE_BUCKETS` for `DATA_ENTRY` includes it under "مُعادة"

### AC8 — Frontend: ActionsPanel support-side "إعادة للمدخل" button
**Given** I am the claiming `SUPPORT_COMMITTEE` member viewing a `SUPPORT_REVIEW_IN_PROGRESS` request
**Then** the actions panel shows "إعادة للمدخل" alongside "اعتماد" and "رفض"
**And** the button opens a comment modal and calls the new endpoint

### AC9 — Frontend: Banner variant on intake side
**Given** a request in `SUPPORT_RETURNED`
**Then** `CorrectionBanner.vue` renders the variant "إعادة من لجنة المساندة — يرجى التعديل وإعادة الإرسال"
**And** the support member's comment is visible

### AC10 — Frontend: Bank reviewer "re-submitted after support return" hint
**Given** a request in `SUBMITTED` whose previous status (in `request_stage_history`) was `SUPPORT_RETURNED`
**When** the bank reviewer opens the detail page
**Then** an informational chip "إعادة بعد عودة من المساندة" is visible above the actions panel, linking to the original support comment

### AC11 — Tests
- New backend tests: enum, transition map, endpoint happy-path, 422, 403 (role + claim guards), claim release on transition, notification dispatch
- New frontend tests: status badge, ROLE_BUCKETS membership, ActionsPanel button visibility, CorrectionBanner variant, reviewer chip

---

## Tasks / Subtasks

### Task 1: Backend — Enum + transition map
- [x] 1.1 Add `SUPPORT_RETURNED` to `app/Enums/RequestStatus.php`
- [x] 1.2 Add `support_return_to_intake` to `TransitionMap`
- [x] 1.3 Add `SUPPORT_RETURNED` to `submit` transition `from` list
- [x] 1.4 Unit test enum + transition map

### Task 2: Backend — Endpoint + claim release
- [x] 2.1 Route `POST /api/workflow/{importRequest}/support-return`
- [x] 2.2 `WorkflowController::supportReturn(SupportReturnRequest)`
- [x] 2.3 `SupportReturnRequest` validates `comment` required min 3
- [x] 2.4 In `WorkflowService`, release claim Redis key inside the same DB transaction
- [x] 2.5 Feature test: happy path, 422, 403 (no claim), 403 (wrong role), claim key released

### Task 3: Backend — Notification + history
- [x] 3.1 `RequestReturnedNotification::toArray()` includes `from_role`
- [x] 3.2 Verify `request_stage_history` order is preserved (chronological)

### Task 4: Frontend — Enums + constants
- [x] 4.1 Add `SUPPORT_RETURNED` to `app/types/enums.ts`
- [x] 4.2 Extend `STATUS_LABEL`, `STATUS_PROGRESS`, `ROLE_BUCKETS`
- [x] 4.3 Map to business-status for `DATA_ENTRY` simplified view

### Task 5: Frontend — ActionsPanel support button
- [x] 5.1 Add "إعادة للمدخل" button in `ActionsPanel.vue` for `SUPPORT_REVIEW_IN_PROGRESS`
- [x] 5.2 Reuse comment modal pattern from Story 8.1
- [x] 5.3 Wire to `useRequests().supportReturn(id, comment)`

### Task 6: Frontend — Banners and chips
- [x] 6.1 Add `SUPPORT_RETURNED` variant to `CorrectionBanner.vue`
- [x] 6.2 Add "re-submitted after support return" chip on reviewer side, sourced from history API
- [x] 6.3 Snapshot tests

### Task 7: Pre-flight + post-flight
- [x] 7.1 SocratiCode: `codebase_symbol` on touched files; `codebase_impact` on `WorkflowService`
- [x] 7.2 Run all backend + frontend tests
- [x] 7.3 `graphify update .`
- [x] 7.4 Signed commits to both repos

### Review Findings

- [x] [Review][Patch] Normalize support-return forbidden/claim error codes and role precedence [backend/app/Http/Controllers/Api/WorkflowController.php]
- [x] [Review][Patch] Restrict the reviewer hint to the immediate prior `SUPPORT_RETURNED` cycle and link it to the audit history [frontend/app/pages/requests/[id]/index.vue]

---

## Out of Scope

- Removing legacy `bank_return_after_support_reject` route (deferred one release window)
- Routing re-submission directly to support (preserve SOD invariant; resubmit always re-enters bank review)

## Dependencies

- Story 8.1 (banner pattern + comment modal pattern)

---

## Dev Agent Record

### Completion Notes

- Added `SUPPORT_RETURNED` as the 20th canonical workflow status (`isEditable=true`, `isTerminal=false`) to both backend PHP enum and frontend TypeScript enum
- Added `support_return_to_intake` transition: `SUPPORT_REVIEW_IN_PROGRESS → SUPPORT_RETURNED`, roles `[SUPPORT_COMMITTEE]`, next_owner `DATA_ENTRY`
- `SUPPORT_RETURNED` added to `submit` from-list so data entry can resubmit — re-enters bank review queue per SOD invariant
- Claim guard in `WorkflowService` extended to include `support_return_to_intake`; claim DB fields and Redis key released atomically in the same transition
- `SupportReturnRequest` form request validates `comment` required, min 3 chars
- `WorkflowController::supportReturn()` now returns top-level `CLAIM_NOT_HELD` / `WORKFLOW_FORBIDDEN_ROLE` codes with role checked before claim ownership
- `ImportRequestResource` exposes `support_return_comment` field (populated only when status is SUPPORT_RETURNED)
- `SendWorkflowNotifications` listener dispatches `RequestReturnedNotification` on SUPPORT_RETURNED with `from_role: SUPPORT_COMMITTEE`
- Frontend: STATUS_COLORS, STATUS_ICONS, STATUS_LABELS, STATUS_PROGRESS, DATA_ENTRY_REPRESENTATIVE_STATUS, DATA_ENTRY_STATUS_LABELS all updated; ROLE_BUCKETS updated for DATA_ENTRY/BANK_ADMIN/CBY_ADMIN
- `ActionsPanel.vue`: "إعادة للمدخل" button for SUPPORT_COMMITTEE + comment modal; DATA_ENTRY sees "تعديل وإعادة تقديم" link when SUPPORT_RETURNED
- `CorrectionBanner.vue`: new `support_returned` variant with Arabic message + support comment display
- Request detail page: `supportReturnHint` now appears only when the immediately previous cycle was `SUPPORT_RETURNED` and links reviewers to the audit history entry
- `useDocumentPermissions`: SUPPORT_RETURNED added to unlocked editable states
- `WorkflowTimeline.vue`: SUPPORT_RETURNED + BANK_RETURNED added to WORKFLOW_STAGE_ORDER and BRANCH_STATUSES
- 15 new backend tests + 20 new frontend tests (1487 pass / 2 pre-existing reka-ui failures)

### Debug Log

- `RequestStatusTest` count updated from 19→20 after adding SUPPORT_RETURNED
- `WorkflowTimeline.test.ts` local WORKFLOW_STAGE_ORDER copy needed SUPPORT_RETURNED added (test maintains its own copy)
- `useDocumentPermissions` test filters needed SUPPORT_RETURNED excluded from "locked" iteration
- `BANK_RETURNED` was also missing from `WORKFLOW_STAGE_ORDER` — fixed together with SUPPORT_RETURNED

---

## File List

### Backend
- `app/Enums/RequestStatus.php` — added SUPPORT_RETURNED case, label, isEditable
- `app/Services/Workflow/TransitionMap.php` — added support_return_to_intake entry; SUPPORT_RETURNED in submit from-list
- `app/Services/Workflow/WorkflowService.php` — claim guard, resubmit tracking, claim release for support_return_to_intake
- `app/Listeners/SendWorkflowNotifications.php` — SUPPORT_RETURNED notification dispatch
- `app/Http/Resources/ImportRequestResource.php` — support_return_comment field
- `app/Http/Controllers/Api/WorkflowController.php` — supportReturn() method
- `app/Http/Requests/SupportReturnRequest.php` — NEW
- `routes/api.php` — POST workflow/{importRequest}/support-return route
- `tests/Unit/Enums/RequestStatusTest.php` — count 19→20, SUPPORT_RETURNED assertions
- `tests/Feature/Workflow/SupportReturnTest.php` — NEW (15 tests)

### Frontend
- `app/types/enums.ts` — SUPPORT_RETURNED added (20th status)
- `app/types/models.ts` — support_return_comment field on ImportRequest
- `app/constants/workflow.ts` — STATUS_COLORS, STATUS_ICONS, STATUS_LABELS, STATUS_PROGRESS, DATA_ENTRY_REPRESENTATIVE_STATUS, DATA_ENTRY_STATUS_LABELS, ROLE_BUCKETS updated
- `app/components/ui/CorrectionBanner.vue` — support_returned variant + supportComment prop
- `app/components/requests/ActionsPanel.vue` — support return button + modal; DATA_ENTRY SUPPORT_RETURNED block
- `app/composables/useRequests.ts` — supportReturn() function
- `app/stores/requests.store.ts` — supportReturn() action
- `app/composables/useDocumentPermissions.ts` — SUPPORT_RETURNED in editable states
- `app/components/workflow/WorkflowTimeline.vue` — BANK_RETURNED + SUPPORT_RETURNED in WORKFLOW_STAGE_ORDER + BRANCH_STATUSES
- `app/pages/requests/[id]/index.vue` — isSupportReturned, supportReturnHint, isEditable/hasActions updated, history pre-load, banner + chip
- `app/tests/unit/types/enums.test.ts` — count 19→20, SUPPORT_RETURNED in expected array
- `app/tests/unit/constants/workflow-buckets.test.ts` — description strings updated to 20
- `app/tests/unit/composables/useDocumentPermissions.test.ts` — SUPPORT_RETURNED in unlocked filters + new explicit tests
- `app/tests/unit/components/CorrectionBanner.test.ts` — support_returned variant tests + reviewer chip tests
- `app/tests/unit/components/WorkflowTimeline.test.ts` — SUPPORT_RETURNED + BANK_RETURNED in local WORKFLOW_STAGE_ORDER/BRANCH_STATUSES; count 19→20

---

## Change Log

- Added SUPPORT_RETURNED status (20th canonical workflow state) with full backend + frontend integration (2026-05-21)

---

## Status

done
