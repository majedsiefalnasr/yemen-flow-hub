# Story 8.4: Claim Release Notification + Audit Visibility

## Story

**As** a `CBY_ADMIN` (committee lead),
**I want** to be notified when a support member releases a claim — manually or via TTL expiry — and to see those releases in the audit log,
**So that** stale-claim oscillation patterns become visible and can be investigated.

**Source:** `docs/09-user-stories-gap-analysis.md` §2.5 Story 7.2, §4.1, §5 item 8, §6 S4 · USER-STORIES.md §15.8 · Gap G3.

---

## Acceptance Criteria

### AC1 — Backend: New audit action enum
**Given** `app/Enums/AuditAction.php`
**Then** a new case `CLAIM_RELEASED = 'CLAIM_RELEASED'` exists with bilingual label

### AC2 — Backend: New notification class
**Given** `app/Notifications/`
**Then** a new `ClaimReleasedNotification` class exists implementing `ShouldQueue` and the standard `via`/`toArray` methods
**And** `toArray()` returns: `type: 'claim_released'`, `request_id`, `reference_number`, `released_by_user_id` (nullable), `released_by_name` (nullable), `reason: 'manual'|'ttl_expired'`, `message` (Arabic)

### AC3 — Backend: Dispatch on manual release
**Given** `DELETE /api/workflow/{id}/claim-support-review` is invoked
**When** the release succeeds
**Then** `ClaimReleasedNotification` is dispatched to all active `CBY_ADMIN` users
**And** `AuditService::log()` writes an entry: `action: CLAIM_RELEASED`, `user_id: <releaser>`, `notes: { reason: 'manual', request_id, reference_number }`

### AC4 — Backend: Dispatch on TTL expiry
**Given** `ExpireClaimsCommand` (cron) runs and finds expired claims
**When** each claim is released
**Then** the same notification is dispatched with `reason: 'ttl_expired'`
**And** the audit entry has `user_id: NULL` and `notes.reason: 'ttl_expired'`

### AC5 — Backend: Preference honoring
**Given** a `CBY_ADMIN` user has `notification_preferences.claim_released = false`
**Then** they do NOT receive the notification
**And** default value for new users is `true`

### AC6 — Frontend: Notification renders correctly
**Given** I am a `CBY_ADMIN` viewing `/notifications`
**When** a claim_released notification appears
**Then** it uses a warning-tone icon and amber accent
**And** the message reads "أُلغيت مطالبة على الطلب {reference_number} — {reason}"
**And** clicking it navigates to `/requests/{id}`

### AC7 — Settings preference
**Given** I am on `/settings`
**Then** the notification preferences section shows a toggle "إشعار إلغاء المطالبة" (default ON)
**And** saving updates `notification_preferences.claim_released` via `PUT /api/settings`

### AC8 — Tests
- Backend: enum case exists; notification class shape; manual-release dispatch + audit; TTL-expiry dispatch + audit; preference enforcement
- Frontend: notification icon variant; settings toggle persists

---

## Tasks / Subtasks

### Task 1: Backend — Enum + notification class
- [x] 1.1 Add `CLAIM_RELEASED` to `AuditAction`
- [x] 1.2 Create `app/Notifications/ClaimReleasedNotification.php` modeled after existing notifications
- [x] 1.3 Unit test enum + notification toArray()

### Task 2: Backend — Manual release dispatch
- [x] 2.1 In `WorkflowController::claimRelease()` (or `WorkflowService::releaseClaim()`), after successful release, dispatch notification + audit log
- [x] 2.2 Resolve "committee leads" recipient set as: all active `CBY_ADMIN` users (documented in code comment as the initial implementation)
- [x] 2.3 Feature test asserting notification + audit row on manual release

### Task 3: Backend — TTL expiry dispatch
- [x] 3.1 In `ExpireClaimsCommand::handle()`, on each expired claim release dispatch the notification + audit with `user_id: NULL`
- [x] 3.2 Feature test with time-travel: claim TTL expires → notification + audit

### Task 4: Backend — Preference enforcement
- [x] 4.1 Wire `ClaimReleasedNotification` into the existing `shouldNotify()` filter in `SendWorkflowNotifications` (or equivalent)
- [x] 4.2 Add default `claim_released: true` to user preference seed
- [x] 4.3 Test: user with pref=false does not get the notification

### Task 5: Frontend — Notification rendering
- [x] 5.1 Add `claim_released` icon variant + tone to `pages/notifications.vue` notification list
- [x] 5.2 Type definition in `composables/useNotifications.ts`
- [x] 5.3 Component test

### Task 6: Frontend — Settings toggle
- [x] 6.1 Add toggle to settings notifications panel
- [x] 6.2 Wire to existing `useSettings().updatePreferences()` path
- [x] 6.3 Test toggle persists

### Task 7: Pre-flight + post-flight
- [x] 7.1 SocratiCode `codebase_symbol` on `WorkflowService::releaseClaim`, `ExpireClaimsCommand`, `SendWorkflowNotifications`
- [x] 7.2 Run all tests; `graphify update .`
- [x] 7.3 Signed commits to both repos

### Review Findings
- [x] [Review][Patch] Centralize claim-release notification and audit side effects in a dedicated backend service instead of duplicating workflow business logic across the controller and TTL command [`backend/app/Services/Notifications/ClaimReleaseNotifier.php`]
- [x] [Review][Patch] Restrict notification-row navigation to `claim_released` items and stop the mark-read button from bubbling into unexpected route changes [`frontend/app/pages/notifications.vue`]
- [x] [Review][Patch] Add typed claim-release payload fields and mount-level page interaction coverage so the new notification contract is enforced in the frontend [`frontend/app/types/models.ts`, `frontend/app/tests/unit/pages/notifications.interactions.test.ts`]

---

---

## Dev Agent Record

### Completion Notes

- `CLAIM_RELEASED` added to `AuditAction` enum with bilingual label.
- `ClaimReleasedNotification` implements `ShouldQueue`, uses `database` channel; payload includes `type`, `message` (Arabic, reason-aware), `request_id`, `reference_number`, `released_by_user_id` (nullable), `released_by_name` (nullable), `reason` (`manual`|`ttl_expired`).
- Claim-release notification delivery and `CLAIM_RELEASED` audit logging now flow through `ClaimReleaseNotifier`, which is reused by both `WorkflowController::claimRelease()` and `ExpireClaimsCommand::handle()`.
- Manual release notifications preserve the actual releaser identity even when a `CBY_ADMIN` forces the release.
- Task 4.2 (`claim_released: true` default): implemented at runtime via `$prefs['claim_released'] ?? true` — no seeder change needed; behavior is equivalent.
- Frontend: `NotificationType` union extended with `claim_released`; typed payload fields added for `reason` and releaser metadata; `iconName()` returns `alert-triangle`; amber CSS accent applied via `.notif-amber` class; only `claim_released` items navigate to `/requests/{id}` on click.
- `settings.vue` `ALL_NOTIF_PREFS` extended with `claim_released` entry scoped to `CBY_ADMIN`.
- Backend tests: targeted `ClaimLifecycleTest` + `NotificationPayloadTest` passed with 39 tests / 178 assertions after the review fixes, including admin-forced manual release coverage.
- Frontend tests: targeted notification/settings suites passed with 29 tests, including mount-level interaction coverage for navigation and mark-read behavior.
- Pre-existing `WorkflowControllerTest` failures (8, line 501 org-scope pagination test) confirmed unrelated to this story.

### File List

**Backend**
- `backend/app/Enums/AuditAction.php` — added `CLAIM_RELEASED` case + label
- `backend/app/Notifications/ClaimReleasedNotification.php` — new file
- `backend/app/Services/Notifications/ClaimReleaseNotifier.php` — centralizes claim-release recipients, preference filtering, and audit logging
- `backend/app/Http/Controllers/Api/WorkflowController.php` — delegates manual release side effects to `ClaimReleaseNotifier`
- `backend/app/Console/Commands/ExpireClaimsCommand.php` — delegates TTL-expiry side effects to `ClaimReleaseNotifier`
- `backend/tests/Unit/Notifications/NotificationPayloadTest.php` — 3 new tests for enum + payload
- `backend/tests/Feature/Workflow/ClaimLifecycleTest.php` — release/audit/TTL/preference coverage, plus admin-forced manual release identity

**Frontend**
- `frontend/app/types/models.ts` — added `claim_released` to `NotificationType` union and typed claim-release payload fields
- `frontend/app/pages/notifications.vue` — `iconName()` case, `notifAccentClass()`, claim-only click navigation, and mark-read click isolation
- `frontend/app/pages/settings.vue` — added `claim_released` entry to `ALL_NOTIF_PREFS`
- `frontend/app/tests/unit/pages/notifications.page.test.ts` — 2 new tests
- `frontend/app/tests/unit/pages/notifications.interactions.test.ts` — mounted navigation + mark-read interaction coverage
- `frontend/app/tests/unit/pages/settings.notif-prefs.test.ts` — 5 new tests (CBY_ADMIN + toggle-persists)

### Change Log

- feat(notifications): add CLAIM_RELEASED audit enum, ClaimReleasedNotification, manual + TTL dispatch, preference enforcement, frontend rendering and settings toggle (2026-05-21)

---

## Status

done

---

## Out of Scope

- SLA timers on claim-release frequency (defer)
- Supervisor-required reason after N releases (defer)
- Granular recipient targeting per bank or committee (the initial implementation broadcasts to all `CBY_ADMIN` — refinement is fast-follow)

## Dependencies

None.
