# Story 5.1: BANK_ADMIN Role, Hierarchical RBAC & Scoped Bank Administration

Status: done

## Story

As a commercial bank administrator,
I want to manage users and profile metadata for my own bank only,
So that banks can operate independently without gaining CBY privileges or cross-bank visibility.

## Acceptance Criteria

1. `BANK_ADMIN` exists in backend and frontend canonical role handling, validation, seeders, policies, navigation constants, and tests; all existing MVP roles retain behavior.
2. `BANK_ADMIN` users require a non-null `bank_id` and cannot be created with `bank_id = NULL`.
3. `BANK_ADMIN` user listing is query-scoped to the actor's own `bank_id` before serialization and never returns CBY or another bank's users.
4. `BANK_ADMIN` can create, update, deactivate, and reset passwords only for `DATA_ENTRY` and `BANK_REVIEWER` users in the actor's own bank.
5. `BANK_ADMIN` cannot create, update, assign, or deactivate CBY roles, `SWIFT_OFFICER`, or another `BANK_ADMIN`, and cannot assign users to another bank.
6. `BANK_ADMIN` can view own-bank operational dashboard data and cannot access global CBY dashboards, global audit logs, other-bank requests, or cross-bank reports.
7. `BANK_ADMIN` can update only allowed profile metadata for their own bank and cannot change workflow-critical identifiers or CBY-controlled fields.
8. Successful and failed `BANK_ADMIN` authorization-sensitive actions are audit logged with actor, actor role, bank context, target, action, metadata, IP/user agent, and timestamp.
9. Focused backend feature tests and frontend unit tests cover same-bank allowed and cross-bank/role-denied cases.

## Tasks / Subtasks

- [x] Backend canonical role and RBAC foundation
  - [x] Add `BANK_ADMIN` to `UserRole` with bank-scoped classification helpers.
  - [x] Update permission seeding and enum tests for the post-MVP canonical role.
  - [x] Enforce non-null `bank_id` for `BANK_ADMIN` in store/update validation.
- [x] Backend scoped user administration
  - [x] Scope user listing for `BANK_ADMIN` to own bank at query level.
  - [x] Restrict `BANK_ADMIN` create/update/deactivate/password reset to own-bank `DATA_ENTRY` and `BANK_REVIEWER`.
  - [x] Audit successful user administration actions and rely on global authorization failure auditing for denials.
- [x] Backend own-bank profile and dashboard access
  - [x] Permit `BANK_ADMIN` to view/update only own bank allowed profile fields.
  - [x] Add own-bank dashboard stats without exposing global CBY data.
  - [x] Confirm reports, audit logs, workflow transitions, voting, support claims, SWIFT upload, and customs issuance remain unavailable to `BANK_ADMIN`.
- [x] Frontend role, navigation, and scoped user UI
  - [x] Add `BANK_ADMIN` to TypeScript role enum, labels, role groupings, store getters, nav, and route map.
  - [x] Allow the existing users page for `BANK_ADMIN` with role/bank options constrained to own-bank `DATA_ENTRY` and `BANK_REVIEWER`.
  - [x] Route bank profile/settings visibility to own-bank administration only.
- [x] Tests and verification
  - [x] Add backend feature tests for same-bank allowed and cross-bank/forbidden-role denied cases.
  - [x] Add frontend unit tests for navigation visibility, route guard role map, and role option constraints.
  - [x] Run targeted backend and frontend test suites.
- [x] BMAD completion
  - [x] Update Dev Agent Record, File List, Change Log, and status.
  - [x] Run BMAD code review and apply required fixes.

## Dev Notes

- Source requirements: `_bmad-output/planning-artifacts/epics.md` Story 5.1 and `_bmad-output/planning-artifacts/sprint-5-institutional-operations-platform-plan.md`.
- Existing code paths to reuse:
  - Backend: `UserRole`, `UserPolicy`, `UserController`, `StoreUserRequest`, `UpdateUserRequest`, `BankPolicy`, `BankController`, `DashboardController`, `AuditService`.
  - Frontend: `app/types/enums.ts`, `app/constants/workflow.ts`, `app/stores/auth.store.ts`, `app/pages/users.vue`.
- Project rules:
  - Scope all bank visibility at query level before serialization.
  - Do not modify `lovable/`.
  - Do not allow `BANK_ADMIN` to override workflow governance or CBY-global reporting/audit.
  - Use SocratiCode before modifying existing implementation files.

## Dev Agent Record

### Debug Log

- 2026-05-17: Story file created from Sprint 5 planning sources because sprint status had Story 5.1 in `backlog` and no implementation artifact existed.
- 2026-05-17: SocratiCode index verified with query `WorkflowService transition`.
- 2026-05-17: Context7 consulted for Laravel 11 policy/FormRequest patterns and Nuxt 4 route middleware/page metadata.
- 2026-05-17: Targeted backend and frontend tests passed; frontend typecheck remains blocked by pre-existing unrelated repo errors.
- 2026-05-17: BMAD code review found one frontend status-filter gap for `BANK_ADMIN`; fixed by adding draft/returned statuses to the role filter and using the `BANK_ADMIN` badge role in the bank admin dashboard.

### Completion Notes

- Implemented `BANK_ADMIN` as a bank-scoped role with own-bank user management for `DATA_ENTRY` and `BANK_REVIEWER` only.
- Added own-bank bank profile update, own-bank dashboard stats, scoped bank/user API responses, and audit logging for successful admin changes plus key forbidden global areas.
- Added frontend role constants, route/nav visibility, users page constraints, own-bank bank profile access, and a bank admin dashboard.
- BMAD code review outcome: Approved after minor filter fix.

### File List

- `_bmad-output/implementation-artifacts/5-1-bank-admin-role-hierarchical-rbac-scoped-bank-administration.md`
- `_bmad-output/implementation-artifacts/sprint-status.yaml`
- `backend/app/Enums/AuditAction.php`
- `backend/app/Enums/UserRole.php`
- `backend/app/Http/Controllers/Api/AuditController.php`
- `backend/app/Http/Controllers/Api/BankController.php`
- `backend/app/Http/Controllers/Api/DashboardController.php`
- `backend/app/Http/Controllers/Api/ReportController.php`
- `backend/app/Http/Controllers/Api/UserController.php`
- `backend/app/Http/Requests/StoreBankRequest.php`
- `backend/app/Http/Requests/StoreUserRequest.php`
- `backend/app/Http/Requests/UpdateBankRequest.php`
- `backend/app/Http/Requests/UpdateUserRequest.php`
- `backend/app/Http/Resources/BankResource.php`
- `backend/app/Policies/BankPolicy.php`
- `backend/app/Policies/UserPolicy.php`
- `backend/database/seeders/PermissionSeeder.php`
- `backend/tests/Feature/Admin/BankAdminRbacTest.php`
- `backend/tests/Unit/Enums/UserRoleTest.php`
- `frontend/app/components/dashboard/BankAdminDashboard.vue`
- `frontend/app/composables/useBanks.ts`
- `frontend/app/composables/useDashboard.ts`
- `frontend/app/constants/workflow.ts`
- `frontend/app/pages/banks.vue`
- `frontend/app/pages/dashboard.vue`
- `frontend/app/pages/users.vue`
- `frontend/app/stores/auth.store.ts`
- `frontend/app/tests/unit/constants/workflow-status.test.ts`
- `frontend/app/tests/unit/pages/DashboardPage.test.ts`
- `frontend/app/types/enums.ts`

### Change Log

- 2026-05-17: Created implementation story artifact and started dev-story execution.
- 2026-05-17: Implemented backend and frontend `BANK_ADMIN` role, scoped administration, audit logging, dashboard/profile access, and focused tests.
- 2026-05-17: Completed BMAD review and applied status-filter follow-up.

## Senior Developer Review (AI)

Outcome: Approve

Review Date: 2026-05-17

Findings:

- Medium: `BANK_ADMIN` status filter initially omitted draft/returned statuses despite own-bank visibility. Fixed by adding `DRAFT` and `DRAFT_REJECTED_INTERNAL` to `ROLE_FILTER_STATUSES[BANK_ADMIN]`.

Residual Risk:

- `npm run typecheck` is still blocked by unrelated existing frontend type errors and a `vue-router/volar` package export warning; targeted story tests pass.
