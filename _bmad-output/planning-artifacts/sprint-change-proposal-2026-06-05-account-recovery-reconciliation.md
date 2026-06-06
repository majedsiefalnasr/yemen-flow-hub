# Sprint Change Proposal - Account Recovery Reconciliation

Date: 2026-06-05
Project: Yemen Flow Hub
Workflow: `bmad-correct-course`
Mode: Batch, based on user-approved recovery discussion
Author: Codex for MAJED

---

## 1. Issue Summary

The account recovery requirement has matured after the original authentication, user management, Bank Admin, and role-surface stories were already implemented.

The current system has working authentication, MFA/TOTP, PIN login, profile password change, CBY user management, Bank Admin staff management, and server-side role scoping. Those working paths must not be disrupted.

The new requirement is to reconcile the BMAD story chain with the current code and add a dedicated account recovery story covering:

- Email OTP self-service password recovery.
- Generic response for unknown email addresses.
- Admin-assisted temporary-password reset as an emergency fallback.
- Forced password change after admin-created or admin-reset temporary password.
- Separate password reset from Authenticator/PIN/MFA reset.
- Production SMTP through CBY/government mail only.
- Mailpit only for local development and Playwright testing.
- No WhatsApp/phone recovery.

This is a security correction and feature addition, not a rewrite of the existing login/MFA/PIN flows.

## 2. Current Code Findings

### Working Behavior To Preserve

- `backend/app/Http/Controllers/Api/AuthController.php` implements email/password login, PIN login, MFA challenge, OTP verification, login lockout, token/cookie issuance, and audit logging.
- `backend/app/Services/Auth/MfaService.php` already supports short-lived 6-digit random OTP challenges and TOTP verification for login/MFA.
- `backend/app/Http/Controllers/Api/ProfileController.php` implements authenticated password change, PIN setup/disable, and TOTP setup/disable.
- `backend/app/Policies/UserPolicy.php`, `StoreUserRequest`, and `UpdateUserRequest` enforce server-side user management scoping.
- `BANK_ADMIN` can manage only own-bank `DATA_ENTRY` and `BANK_REVIEWER` users. They cannot manage `BANK_ADMIN`, `SWIFT_OFFICER`, CBY users, or other-bank users.
- `CBY_ADMIN` can create/update users through `/api/users`.
- `frontend/app/pages/login.vue` already links to `/reset-password`.
- `frontend/app/pages/reset-password.vue` exists and uses shadcn-vue components, VeeValidate, Zod, RTL Arabic copy, and password requirement UI.
- `frontend/app/pages/staff.vue` and `StaffModal.vue` already support Bank Admin creating/updating staff with an optional password field on edit.
- `frontend/app/pages/admin/cby-staff.vue` already supports CBY Admin creating/updating CBY-side users with optional password update on edit.

### Gaps

- `/reset-password` is currently a placeholder. It has a comment saying the backend endpoint will be wired in a dedicated story.
- There is no dedicated backend forgot-password/email-OTP recovery API.
- Laravel password reset broker config exists, but no project-specific reset token table or flow is implemented.
- Current MFA random OTP is login-oriented. Password recovery should use a separate cache namespace/service so reset OTPs do not collide with login MFA challenges.
- Admin password update is currently part of general user update. It does not mark the password as temporary or force change on next login.
- There is no explicit forced-password-change state on `users`.
- Bank creation UI shows `حساب مدير البنك` name/email fields, but the create payload sent to `/api/banks` does not include admin name/email/password, and `BankController::store()` only creates the bank record.
- There are no dedicated reset-MFA or reset-PIN admin endpoints with stronger permission checks.
- `.env.example` lacks mail configuration entries.
- `docker-compose.yml` has no Mailpit service and backend mail environment is not configured for local SMTP.
- Playwright E2E does not yet inspect Mailpit for reset emails.

## 3. Change Navigation Checklist Results

| Item | Status | Result |
| --- | --- | --- |
| 1.1 Trigger | Done | User clarified the account recovery model after several auth/admin features had already been implemented outside a fresh BMAD story chain. |
| 1.2 Core problem | Done | New security requirement emerged from stakeholder discussion. BMAD artifacts do not yet reflect current recovery policy. |
| 1.3 Evidence | Done | Existing code has working auth/admin paths, but `/reset-password` is explicitly placeholder-only and mail/admin temporary password behavior is missing. |
| 2.1 Current epic viability | Done | Existing completed auth/admin stories remain valid. Do not reopen or roll back them. |
| 2.2 Epic-level changes | Done | Add a focused follow-up story under the auth/security area instead of rewriting Epic 1 or Epic 5. |
| 2.3 Future epics | Done | Epic 12 remains UI uplift work. Account recovery should be tracked separately because it is security/backend/frontend/E2E. |
| 2.4 New epic necessity | Action-needed | A full new epic is optional. A single reconciliation story is enough if scoped tightly. |
| 2.5 Priority | Done | High priority before production because password recovery and SMTP configuration are operational readiness requirements. |
| 3.1 PRD impact | Done | Product scope unchanged; security requirements need explicit recovery behavior. |
| 3.2 Architecture impact | Done | Add auth recovery service/API, user temporary-password state, mail configuration, and Mailpit local service. |
| 3.3 UI/UX impact | Done | Wire existing `/reset-password`; add forced password change dialog/screen; add admin reset actions in scoped admin surfaces. |
| 3.4 Secondary artifacts | Done | Update `.env.example`, Docker Compose, auth/security docs, BMAD story/status artifacts, and Playwright E2E tests. |
| 4.1 Direct adjustment | Viable | Create one dedicated story and implement additively. Risk medium because auth/security paths are sensitive. |
| 4.2 Rollback | Not viable | Current code works and should be preserved. Rollback would increase risk. |
| 4.3 MVP review | Not viable | No MVP reduction needed. |
| 4.4 Recommended path | Done | Direct adjustment through a dedicated reconciliation story, then dev-story, code review, and QA. |
| 5.1-5.5 Proposal | Done | This document. |
| 6.1-6.5 Final handoff | Pending | Requires user approval before updating sprint status or creating the story. |

## 4. Recommended Approach

Use **Direct Adjustment**.

Do not modify working login, MFA, PIN, or generic user-update behavior as a first move. Create a dedicated story titled:

`Account Recovery: Email OTP and Admin-Assisted Temporary Password Reset`

The story should be implemented additively:

1. Add backend recovery service/endpoints.
2. Add user temporary-password state and forced-change flow.
3. Wire existing `/reset-password` frontend page to real APIs.
4. Add admin reset actions with server-side permission checks.
5. Add Mailpit local/dev config and CBY SMTP env docs.
6. Add backend feature tests and Playwright E2E.

## 5. Proposed Story Scope

### Story: Account Recovery: Email OTP and Admin-Assisted Temporary Password Reset

As an institutional user,
I want a secure account recovery path using my institutional email,
So that I can recover access without exposing account existence or weakening MFA/PIN controls.

As a CBY Admin or Bank Admin,
I want emergency reset actions limited by my authority,
So that users who cannot access their email can regain access through a controlled temporary-password flow.

### Acceptance Criteria

1. Forgot-password request accepts institutional email and always returns:
   `If this email exists, a recovery code has been sent.`
2. Existing and non-existing emails return the same HTTP status and generic message.
3. Existing active users receive a short-lived email OTP through Laravel mail.
4. Reset OTP is stored separately from login MFA OTP, expires, is attempt-limited, and is single-use.
5. Valid OTP allows setting a new password and redirects user to login on success.
6. Invalid or expired OTP fails safely without revealing account existence.
7. Password recovery resets password only; it does not reset MFA, Authenticator/TOTP, or PIN.
8. CBY Admin can reset passwords for CBY users and Bank Admin users.
9. CBY Admin can reset Bank Admin password from the bank management surface.
10. Bank Admin can reset passwords only for own-bank `DATA_ENTRY` and `BANK_REVIEWER` staff.
11. Bank Admin cannot reset another Bank Admin, SWIFT Officer, CBY user, other-bank user, or themselves through the staff reset flow.
12. Admin reset marks the target password as temporary and forces password change on next login.
13. Forced password change clears the temporary flag only after a successful new password save.
14. Reset MFA/PIN are separate server-side endpoints/actions with stronger authorization than password reset.
15. No WhatsApp or phone recovery is implemented.
16. Production mail config uses CBY/government SMTP environment variables.
17. Local development and Playwright tests use Mailpit only.
18. Mailpit is not allowed or documented as a production provider.

## 6. Implementation Notes

### Backend

- Add migration fields to `users`, likely:
  - `must_change_password` boolean default false
  - `temporary_password_set_at` nullable timestamp
  - optionally `password_changed_at` nullable timestamp
- Add a dedicated password recovery service. Do not reuse `MfaService` cache keys directly.
- Suggested public endpoints:
  - `POST /api/auth/password/forgot`
  - `POST /api/auth/password/verify`
  - `POST /api/auth/password/reset`
- Suggested authenticated endpoints:
  - `POST /api/users/{user}/reset-password`
  - `POST /api/users/{user}/reset-mfa`
  - `POST /api/users/{user}/reset-pin`
  - `POST /api/profile/complete-temporary-password-change`
- Keep reset-password and reset-MFA/PIN separate in code, routes, tests, and audit logs.
- Add explicit authorization methods, not frontend-only checks.
- Add dedicated audit metadata for actor, target role, target bank, reset type, and temporary-password flag. Never log raw OTPs or passwords.

### Frontend

- Reuse `frontend/app/pages/reset-password.vue`; replace placeholder delay with real API calls.
- Prefer a two or three-step flow:
  - email request
  - OTP entry
  - new password
- Keep copy generic and Arabic/RTL-compatible.
- Add forced password change dialog or guarded route after login when backend returns/reflects `must_change_password`.
- Add admin reset actions:
  - CBY Admin: `/admin/cby-staff` for CBY users.
  - CBY Admin: `/admin/banks` bank edit/details surface for Bank Admin credentials.
  - Bank Admin: `/staff` for own-bank manageable staff.
- Do not surface reset controls for unauthorized roles, but rely on backend as authority.

### Mail

- Add `.env.example` entries:
  - `MAIL_MAILER=smtp`
  - `MAIL_HOST=`
  - `MAIL_PORT=`
  - `MAIL_USERNAME=`
  - `MAIL_PASSWORD=`
  - `MAIL_ENCRYPTION=`
  - `MAIL_FROM_ADDRESS=`
  - `MAIL_FROM_NAME=`
- Add local Docker Mailpit:
  - service `mailpit`
  - SMTP `mailpit:1025`
  - web UI likely `8025`
- Production docs must say CBY/government SMTP is required.
- Do not configure Mailpit as production default.

### Tests

Backend feature tests:

- Forgot request with existing email sends mail and returns generic response.
- Forgot request with non-existing email returns the same generic response.
- Reset OTP expires.
- Reset OTP is single-use.
- Valid reset changes password.
- Invalid OTP fails safely.
- Password reset does not clear TOTP/PIN fields.
- CBY Admin can reset Bank Admin password.
- CBY Admin can reset CBY user password.
- Bank Admin can reset own-bank staff password.
- Bank Admin cannot reset other-bank user.
- Bank Admin cannot reset Bank Admin or SWIFT Officer.
- Admin reset forces password change on next login.
- MFA/PIN reset endpoints require separate permission.

Frontend/unit tests:

- `reset-password.vue` step transitions, loading states, generic messages, invalid OTP state.
- Auth store or login guard handles `must_change_password`.
- Admin surfaces show/hide reset actions according to role and target.

Playwright:

- Forgot password flow with Mailpit email inspection.
- Unknown email generic response.
- OTP entry and new password success.
- Forced password change after admin reset.
- Permission visibility/actions for CBY Admin and Bank Admin.

## 7. BMAD Handoff

Recommended next workflow:

1. Update `sprint-status.yaml` only after user approval, adding a backlog story for account recovery reconciliation.
2. Run `bmad-create-story` for:
   `Account Recovery: Email OTP and Admin-Assisted Temporary Password Reset`
3. Validate with `bmad-create-story` action `validate`.
4. Optional but recommended: run `bmad-testarch-atdd` before implementation.
5. Run `bmad-dev-story`.
6. Run `bmad-code-review`.
7. Run `bmad-qa-generate-e2e-tests` if Playwright coverage needs expansion.

## 8. Explicit Non-Goals

- Do not implement WhatsApp recovery.
- Do not use unofficial WhatsApp libraries.
- Do not expose whether an email exists.
- Do not reset MFA/PIN as part of password recovery.
- Do not allow frontend-only authorization.
- Do not use Mailpit in production.
- Do not rewrite the existing login/MFA/PIN flows unless tests prove a direct integration need.

## 9. Approval

Status: Pending user review.

After approval, the next step is to create the BMAD story artifact. Application code should remain untouched until the story is created and validated.
