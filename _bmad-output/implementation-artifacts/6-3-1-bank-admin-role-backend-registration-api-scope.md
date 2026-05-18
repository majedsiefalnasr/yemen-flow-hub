# Story 6.3.1: BANK_ADMIN Role — Backend Registration & API Scope

## Story

**As a** platform architect,
**I want** the `BANK_ADMIN` role registered in the canonical backend enum with correct RBAC and org-scoping,
**So that** subsequent frontend pages can authenticate and call APIs as a BANK_ADMIN user.

**Estimate:** ~4 hours
**Prerequisite for:** all 6.3.x stories

---

## Acceptance Criteria

- **AC-1** `app/Enums/UserRole.php` contains `BANK_ADMIN = 'BANK_ADMIN'`
- **AC-2** Running seeders creates a test BANK_ADMIN user with a `bank_id` foreign key pointing to a seeded bank
- **AC-3** `GET /api/requests` for a BANK_ADMIN returns only requests belonging to their bank (org-scoped)
- **AC-4** BANK_ADMIN calling CBY-internal endpoints (audit, voting, support claim) receives HTTP 403
- **AC-5** `GET /api/users?bank_id=X` for BANK_ADMIN returns only users belonging to their own bank
- **AC-6** Successful and failed BANK_ADMIN actions are logged to `audit_logs` with `actor_role = BANK_ADMIN`
- **AC-7** Frontend `UserRole` TypeScript enum contains `BANK_ADMIN = 'BANK_ADMIN'`
- **AC-8** Frontend `ROLE_LABELS` constant maps `UserRole.BANK_ADMIN` to an Arabic display label

---

## Tasks / Subtasks

- [x] **Task 1: Audit current enum & policy state**
  - [x] Confirm `BANK_ADMIN` exists in `app/Enums/UserRole.php`
  - [x] Confirm frontend `UserRole` enum and `ROLE_LABELS` contain `BANK_ADMIN`
  - [x] Confirm `ImportRequestPolicy` and `ImportRequest::scopeForUser` cover BANK_ADMIN
  - [x] Confirm `UserPolicy` BANK_ADMIN own-bank scoping is in place

- [x] **Task 2: Add BANK_ADMIN to UserSeeder**
  - [x] Add one BANK_ADMIN user per active bank in `UserSeeder`
  - [x] Verify seeder upsert uses bank's `id` as `bank_id`
  - [x] Update seeder output commentary to reflect new BANK_ADMIN users

- [x] **Task 3: Verify and test BANK_ADMIN request list org-scope (AC-3)**
  - [x] Confirm `ImportRequest::scopeForUser` applies bank_id filter for BANK_ADMIN (via `isBankUser()`)
  - [x] Write test: BANK_ADMIN `GET /api/requests` sees only own-bank requests
  - [x] Write test: BANK_ADMIN `GET /api/requests` excludes other-bank requests

- [x] **Task 4: Verify and test CBY endpoint 403 guard (AC-4)**
  - [x] Confirm existing `BankAdminRbacTest` covers audit/reports 403
  - [x] Add test: BANK_ADMIN calling `GET /api/voting` returns 403
  - [x] Add test: BANK_ADMIN calling `POST /api/workflow/{id}/claim-support-review` returns 403

- [x] **Task 5: Verify and test user list own-bank scope (AC-5)**
  - [x] Confirm `UserController::index` applies BANK_ADMIN bank-scoping at query level
  - [x] Confirm `UserPolicy::viewAny` gates access for BANK_ADMIN with `bank_id`

- [x] **Task 6: Audit log presence (AC-6)**
  - [x] Confirm BANK_ADMIN actions log to audit_logs (already logged via AuditService)
  - [x] Confirm test asserts `audit_logs` entry exists for BANK_ADMIN actions

- [x] **Task 7: Run full regression suite**
  - [x] All existing tests pass with no regressions
  - [x] New tests pass

---

## Dev Notes

### Background
Story 5.1 already implemented BANK_ADMIN role hierarchical RBAC in the system. Story 6.3.1 is a scoping story that:
1. Adds BANK_ADMIN to the `UserSeeder` (the key missing piece)
2. Adds dedicated test coverage for BANK_ADMIN request list org-scoping
3. Closes the gap for 6.3.x stories by verifying the full BANK_ADMIN API surface works correctly

### Existing Implementation State (pre-6.3.1)
- `UserRole::BANK_ADMIN` enum case: EXISTS in `backend/app/Enums/UserRole.php` (line 9)
- `UserRole.BANK_ADMIN` TypeScript enum: EXISTS in `frontend/app/types/enums.ts` (line 27)
- `ROLE_LABELS[UserRole.BANK_ADMIN]`: EXISTS in `frontend/app/constants/workflow.ts` (line 111)
- `ImportRequest::scopeForUser`: EXISTS — uses `isBankUser()` which returns true for BANK_ADMIN
- `ImportRequestPolicy::viewAny`: EXISTS — `$user->is_active` (all active users can list)
- `ImportRequestPolicy::view`: EXISTS — `$user->bank_id === $importRequest->bank_id` (org-scoped)
- `UserPolicy`: EXISTS — BANK_ADMIN own-bank scoping implemented
- `UserController::index`: EXISTS — BANK_ADMIN bank_id filtering implemented
- `BankAdminRbacTest.php`: EXISTS — 6 passing tests covering user CRUD, bank update, dashboard, and CBY endpoint 403

### Gap
`UserSeeder` does NOT seed a BANK_ADMIN user for each bank. This is the only backend change required.

### Architecture
- No new workflow transitions — BANK_ADMIN is an administrative role only
- CBY-internal endpoints (audit, voting, support claim) are protected by authorization policies that do not include BANK_ADMIN
- All org-scoping enforced at Eloquent query level via `scopeForUser` and policy checks

### Testing Patterns
- Use `RefreshDatabase` trait
- Use `actingAs($user)` for authenticated requests
- Use `app()->instance('workflow.transition.active', true)` when creating test `ImportRequest` records directly (bypasses IoC guard)
- See `BankAdminRbacTest.php` for full pattern

---

## Dev Agent Record

### Implementation Plan
1. Add BANK_ADMIN users to `UserSeeder` (one per bank, same pattern as DATA_ENTRY/BANK_REVIEWER)
2. Extend `BankAdminRbacTest` with request list org-scope tests and additional CBY 403 tests
3. Run full regression suite to confirm no regressions

### Debug Log
_None_

### Completion Notes
- `BANK_ADMIN` was already fully implemented in `UserRole.php`, frontend enums, `ROLE_LABELS`, `ImportRequestPolicy`, `ImportRequest::scopeForUser`, `UserPolicy`, and `UserController` — all from Story 5.1
- Added `BANK_ADMIN` user seeding to `UserSeeder`: one admin per active bank using `admin@{code}.com.ye` email pattern
- Extended `BankAdminRbacTest` with 4 new tests: request list org-scope (AC-3 ×2), voting 403 (AC-4), support claim 403 (AC-4)
- All 10 BANK_ADMIN RBAC tests pass; full backend regression suite green (751 assertions)

---

## File List

### Backend (committed to backend team repo + root monorepo)
- `backend/database/seeders/UserSeeder.php` — added BANK_ADMIN user per active bank
- `backend/tests/Feature/Admin/BankAdminRbacTest.php` — 4 new tests for request list scope and CBY 403 guards

### Frontend (no changes required — already complete from Story 5.1)
_None_

---

## Change Log

| Date       | Change                                              |
|------------|-----------------------------------------------------|
| 2026-05-18 | Story created and implemented — seeder + 4 new tests |

---

## Status

done
