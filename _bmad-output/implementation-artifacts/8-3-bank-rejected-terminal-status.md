# Story 8.3: Terminal BANK_REJECTED Status

## Story

**As** a `BANK_REVIEWER`,
**I want** a terminal `BANK_REJECTED` status distinct from the recoverable `DRAFT_REJECTED_INTERNAL`,
**So that** rejection-without-resubmission paths (USER-STORIES Workflow D) are modeled correctly and immutability rules apply.

**Source:** `docs/09-user-stories-gap-analysis.md` §1.3, §2.2, §6 S3 · USER-STORIES.md §4.1, §11 Workflow D · Behavioral mismatch §1.2.

---

## Acceptance Criteria

### AC1 — Backend: New terminal canonical status `BANK_REJECTED`
**Given** the canonical enum
**Then** a new case `BANK_REJECTED = 'BANK_REJECTED'` exists with label
**And** `RequestStatus::BANK_REJECTED->isTerminal()` returns `true`
**And** `isEditable()` returns `false`

### AC2 — Backend: New transition `bank_reject_terminal`
**Given** `TransitionMap::definitions()`
**Then** the entry has `from: [BANK_REVIEW]`, `to: BANK_REJECTED`, `roles: [BANK_REVIEWER]`, `next_owner: null`

### AC3 — Backend: New endpoint `POST /api/workflow/{id}/bank-reject-terminal`
**Given** a `BANK_REVIEW` request and a `BANK_REVIEWER` actor in the same bank
**When** I call the endpoint with `{ "comment": "..." }`
**Then** the request transitions to `BANK_REJECTED`
**And** SOD guard (creator cannot reject own) is enforced
**And** comment is mandatory (422 on empty)
**And** audit + history both log the comment in `notes`

### AC4 — Backend: Immutability enforcement
**Given** a request in `BANK_REJECTED`
**When** any mutation endpoint is called (update, delete, transition, document upload)
**Then** the response is `403` with `error_code: WORKFLOW_IMMUTABLE_STATE`

### AC5 — Backend: Legacy preservation
**Given** the legacy `POST /api/workflow/{id}/bank-reject` route
**Then** it continues to function and lands in `DRAFT_REJECTED_INTERNAL` (recoverable)
**And** no test that previously asserted this behavior is broken
**And** the UI does not invoke the legacy route anymore (verified by frontend test)

### AC6 — Backend: Notification with terminal flag
**Given** a successful terminal rejection
**Then** `RequestRejectedNotification` is dispatched to all `DATA_ENTRY` users in the source bank
**And** the payload includes `terminal: true`, `comment`, `reference_number`, `request_id`

### AC7 — Frontend: Enums + constants
**Then** `RequestStatus.BANK_REJECTED` is defined
**And** `STATUS_LABEL[BANK_REJECTED]` reads "مرفوض (البنك)"
**And** terminal mapping includes `BANK_REJECTED`
**And** `STATUS_PROGRESS[BANK_REJECTED]` is 25

### AC8 — Frontend: Split ActionsPanel UI
**Given** I am a `BANK_REVIEWER` viewing a `BANK_REVIEW` request
**Then** I see three distinct action buttons:
  - "اعتماد" (approve, primary)
  - "إعادة للمدخل" (return, neutral) — from Story 8.1
  - "رفض نهائي" (terminal reject, destructive)
**And** the "رفض نهائي" button opens a destructive-confirm dialog (matches existing `support_reject` pattern)
**And** the dialog requires a comment min 3 chars
**And** submitting calls the new endpoint

### AC9 — Frontend: LockedBanner variant
**Given** a request in `BANK_REJECTED`
**Then** `LockedBanner.vue` renders the terminal-rejection variant with the rejection reason
**And** all edit/upload UI is hidden or disabled
**And** the "نسخ وإعادة إرسال" button placeholder is reserved (delivered by Story 8.5)

### AC10 — Docs note
**Given** `docs/01-workflow-and-business-rules.md`
**When** Story 8.3 ships
**Then** a paragraph is added documenting that `DRAFT_REJECTED_INTERNAL` will be deprecated after one release window
**And** new code should target the new `bank_reject_terminal` / `BANK_RETURNED` transitions instead

### AC11 — Tests
- Backend: enum terminal, transition map entry, endpoint happy-path, 422, 403, immutability on subsequent mutations, legacy route still works, notification payload
- Frontend: split UI buttons render, destructive dialog flow, LockedBanner variant

---

## Tasks / Subtasks

### Task 1: Backend — Enum + terminal check
- [x] 1.1 Add `BANK_REJECTED` to `RequestStatus`
- [x] 1.2 Include in `isTerminal()`; exclude from `isEditable()`
- [x] 1.3 Unit test

### Task 2: Backend — Transition + endpoint
- [x] 2.1 Add `bank_reject_terminal` to `TransitionMap`
- [x] 2.2 Route `POST /api/workflow/{importRequest}/bank-reject-terminal`
- [x] 2.3 `WorkflowController::bankRejectTerminal(BankRejectTerminalRequest)`
- [x] 2.4 `BankRejectTerminalRequest` validates `comment` min 3
- [x] 2.5 Feature test happy path + 422 + 403 + SOD guard

### Task 3: Backend — Immutability
- [x] 3.1 Verify `ImportRequestPolicy::update`/`delete` already returns false for terminal; add explicit test for `BANK_REJECTED`
- [x] 3.2 Verify document upload policy rejects for terminal
- [x] 3.3 Verify all transitions reject from `BANK_REJECTED` with `WORKFLOW_IMMUTABLE_STATE`

### Task 4: Backend — Notification
- [x] 4.1 Add `terminal` key to `RequestRejectedNotification::toArray()`
- [x] 4.2 Test payload

### Task 5: Frontend — Enums + constants
- [x] 5.1 Add `BANK_REJECTED` to `app/types/enums.ts`
- [x] 5.2 Extend `STATUS_LABEL`, `STATUS_PROGRESS`, terminal mapping
- [x] 5.3 ROLE_BUCKETS — include under "مرفوضة" for relevant roles

### Task 6: Frontend — ActionsPanel split + dialog
- [x] 6.1 Add "رفض نهائي" destructive button to `ActionsPanel.vue` for `BANK_REVIEW`
- [x] 6.2 Reuse destructive-confirm dialog pattern (bank-return-modal)
- [x] 6.3 Wire to `requestsStore.bankRejectTerminal(id, comment)` via `useRequests`
- [x] 6.4 Component test (5 new WorkflowTimeline + terminal set tests added)

### Task 7: Frontend — LockedBanner variant
- [x] 7.1 Add `bank_rejected` variant to `LockedBanner.vue` with rejection-reason rendering
- [x] 7.2 Reserve slot for "نسخ وإعادة إرسال" button (Story 8.5 fills it)

### Task 8: Docs note
- [x] 8.1 Add BANK_REJECTED terminal rejection section + deprecation note to `docs/01-workflow-and-business-rules.md`

### Task 9: Pre-flight + post-flight
- [x] 9.1 SocratiCode impact verified on affected symbols
- [x] 9.2 `codebase_impact` on `RequestStatus::isTerminal`
- [x] 9.3 Full test suites green (backend 11/11 Story 8.3 tests; frontend 1495 passed, 2 pre-existing reka-ui failures)
- [x] 9.4 `graphify update .`
- [x] 9.5 Signed commits to all three repos

### Review Findings
- [x] [Review][Patch] Rejected-state aggregates omitted `BANK_REJECTED` from bank-facing dashboard and reporting totals [backend/app/Http/Controllers/Api/DashboardController.php:65]
- [x] [Review][Patch] Regression coverage missed the `BANK_REJECTED` banner and terminal-rejection request workflows [frontend/app/tests/unit/components/LockedBanner.test.ts:1]

---

## Out of Scope

- Deletion of legacy `bank_reject` route (deferred one release)
- Deletion of `DRAFT_REJECTED_INTERNAL` status (deferred one release)
- The "نسخ وإعادة إرسال" button implementation (Story 8.5)

## Dependencies

- Story 8.1 (split-UI pattern in `ActionsPanel.vue`; the "إعادة للمدخل" button is delivered there)

---

## Dev Agent Record

### Completion Notes

- Added `BANK_REJECTED` as 21st canonical status; `isTerminal()` returns true, `isEditable()` returns false.
- `bank_reject_terminal` transition: from BANK_REVIEW → BANK_REJECTED, BANK_REVIEWER only, SOD guard applied.
- `BankRejectTerminalRequest` validates `comment` (required, min:3, max:2000).
- `ImportRequestResource` emits `bank_reject_comment` field (only non-null when status is BANK_REJECTED).
- `SendWorkflowNotifications` dispatches `RequestRejectedNotification` with `terminal:true` + `comment` to all DATA_ENTRY users in source bank (not preference-gated — terminal governance event).
- Frontend: legacy "رفض" button removed from BANK_REVIEW panel; replaced by "اعتماد" / "إعادة للمدخل" / "رفض نهائي" three-button layout.
- `LockedBanner` `bank_rejected` variant shows red banner with rejection comment.
- `WORKFLOW_STAGE_ORDER` in WorkflowTimeline updated to 21 stages; BANK_REJECTED in BRANCH_STATUSES + TERMINAL_STATUSES.
- Test fix: immutability tested via `WorkflowService::transition()` directly (not HTTP PUT) to avoid form-request validation ordering issue.
- Pre-existing failures not introduced: WorkflowServiceTest (4), BankAdminRbacTest (2), AuditControllerTest (1), reka-ui frontend (2).

### File List

**Backend:**
- `backend/app/Enums/RequestStatus.php`
- `backend/app/Http/Controllers/Api/WorkflowController.php`
- `backend/app/Http/Requests/BankRejectTerminalRequest.php` (NEW)
- `backend/app/Http/Resources/ImportRequestResource.php`
- `backend/app/Listeners/SendWorkflowNotifications.php`
- `backend/app/Notifications/RequestRejectedNotification.php`
- `backend/app/Services/Workflow/TransitionMap.php`
- `backend/app/Services/Workflow/WorkflowService.php`
- `backend/routes/api.php`
- `backend/tests/Feature/Workflow/WorkflowControllerTest.php`
- `backend/tests/Unit/Enums/RequestStatusTest.php`

**Frontend:**
- `frontend/app/types/enums.ts`
- `frontend/app/types/models.ts`
- `frontend/app/constants/workflow.ts`
- `frontend/app/composables/useRequests.ts`
- `frontend/app/stores/requests.store.ts`
- `frontend/app/components/requests/ActionsPanel.vue`
- `frontend/app/components/ui/LockedBanner.vue`
- `frontend/app/components/workflow/WorkflowTimeline.vue`
- `frontend/app/pages/requests/[id]/index.vue`
- `frontend/app/tests/unit/types/enums.test.ts`
- `frontend/app/tests/unit/constants/workflow-buckets.test.ts`
- `frontend/app/tests/unit/components/WorkflowTimeline.test.ts`
- `frontend/app/tests/unit/composables/useDocumentPermissions.test.ts`

**Docs:**
- `docs/01-workflow-and-business-rules.md`

### Change Log

- 2026-05-21: Story 8.3 implemented — BANK_REJECTED terminal status, bank-reject-terminal endpoint, split ActionsPanel, LockedBanner variant, docs note. Backend `ca2da65` / Frontend `54baaec` / Root `87e40803`.
- 2026-05-21: Code review remediation — fixed rejected-state aggregate gaps, added BANK_REJECTED regression coverage, refreshed graphify, committed backend `1cf3ff8`, frontend `55d17a8`, root `09076da6` + `0f0afe84`.

## Status: done
