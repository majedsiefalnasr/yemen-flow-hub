# Yemen Flow Hub — GitHub Copilot Instructions (Backend)

Laravel 11 API for Yemen Flow Hub — an internal government banking regulatory workflow platform for the Central Bank of Yemen.

## Source of Truth

Read `AGENTS.md` (one level up at `../AGENTS.md`) and `CLAUDE.md` in this directory. All workflow rules and enums are defined there.

## Git

Backend code lives in two repos. Commit every change to both:

1. From `backend/` → `git@github.com:ultimate-eg/yemen-flow-hub-backend.git` (team repo)
2. From root `/` → `git@github.com:majedsiefalnasr/yemen-flow-hub.git` (monorepo, stage `backend/<files>`)

Commit format: `type(scope): description`

## Critical Rules

### Status changes via WorkflowService only

```php
// ✅
$workflowService->transition($request, 'support_approve', $user);
// ❌ Never direct assignment
$request->current_status = 'SUPPORT_APPROVED';
```

### Canonical status enum (use exactly these values)

DRAFT, DRAFT_REJECTED_INTERNAL, SUBMITTED, BANK_REVIEW, BANK_APPROVED,
SUPPORT_REVIEW_PENDING, SUPPORT_REVIEW_IN_PROGRESS, SUPPORT_APPROVED,
SUPPORT_REJECTED, WAITING_FOR_SWIFT, SWIFT_UPLOADED, WAITING_FOR_VOTING_OPEN,
EXECUTIVE_VOTING_OPEN, EXECUTIVE_VOTING_CLOSED, EXECUTIVE_APPROVED,
EXECUTIVE_REJECTED, CUSTOMS_DECLARATION_ISSUED, COMPLETED

### Visibility scoping

Every query for `import_requests` by bank users must filter by `bank_id`. Never return unscoped results.

### Audit logging

Both `request_stage_history` AND `audit_logs` on every transition. Include `role`, `from_status`, `to_status` in `audit_logs`.

### File uploads

PDF only. Private storage. SWIFT documents immutable after upload.

### Voting

Use `lockForUpdate()` in transactions for vote submission and session closure.

### Immutable states

Return HTTP 403 + `WORKFLOW_IMMUTABLE_STATE` error code for mutations on `EXECUTIVE_REJECTED`, `CUSTOMS_DECLARATION_ISSUED`, `COMPLETED`.

## Context7

```bash
npx ctx7@latest library "Laravel" "<question>"
npx ctx7@latest docs <id> "<question>"
```

## SocratiCode

Use semantic codebase search before modifying any service, model, or policy.

## Verification Ladder

Before editing, check `git -c core.fsmonitor=false status --short` from `backend/` and report existing dirty files. Do not modify dirty files unless directly in scope.

Default verification is focused:

- Run the smallest relevant PHPUnit file or `--filter` for the touched behavior.
- Run Pint only for touched PHP files where possible.
- Do not run full `php artisan test` by default.
- Full suites are required only for release checks, broad refactors, security-critical changes, or explicit user requests.
- If the full suite is known red, report the known baseline and do not treat unrelated failures as task failures.

Focused commands:

```bash
php artisan test tests/Feature/Auth/PasswordRecoveryTest.php
php artisan test --filter=PasswordRecoveryTest
php artisan test --filter='password reset with valid otp'
vendor/bin/pint app/Services/Workflow/WorkflowService.php --test
```
