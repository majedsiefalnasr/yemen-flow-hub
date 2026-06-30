@../AGENTS.md

# Claude Code — Backend

Yemen Flow Hub Laravel 11 API backend.

## Git Scope

Backend code lives in **two repos simultaneously**:

- **Backend team repo** (`git@github.com:ultimate-eg/yemen-flow-hub-backend.git`) — backend team's standalone repo
- **Root monorepo** (`git@github.com:majedsiefalnasr/yemen-flow-hub.git`) — tracked under `backend/`

Every backend change must be committed to **both**:

```bash
# 1. From inside backend/ — commit to backend team repo
git add <files>
git commit -m "feat(workflow): add support claim heartbeat endpoint"

# 2. From root — commit same change to root monorepo
cd ..
git add backend/<files>
git commit -m "feat(workflow): add support claim heartbeat endpoint"
```

Conventional commit format: `type(scope): description`
All commits must stay signed. Never use `--no-gpg-sign`, `--no-sign`, or `-c commit.gpgsign=false`; if signing fails, fix signing first.
Never add or commit generated artifacts from `graphify-out/`, `_bmad-output/implementation-artifacts/`, or `_bmad-output/test-artifacts/`. Keep them local only.

Examples:

- `feat(workflow): add support claim heartbeat endpoint`
- `fix(voting): prevent race condition on session closure`
- `chore(db): add role column to audit_logs migration`

## Stack

- PHP 8.2+, Laravel 11
- Sanctum (HTTP-only cookie auth)
- MySQL + Redis
- Queue Workers (Redis driver)

## Architecture Rules

### WorkflowService is mandatory

All `current_status` changes must go through `WorkflowService::transition()`. The `ImportRequest` model must throw `DirectStatusMutationException` on direct status assignment outside of this service.

### Service-oriented structure

```
app/
├── Actions/        ← Single-purpose action classes
├── DTOs/           ← Data transfer objects
├── Enums/          ← Status, Role, Vote enums (must match AGENTS.md canonical enums)
├── Http/
│   ├── Controllers/ ← Thin: receive, validate, call service, return
│   ├── Middleware/
│   └── Requests/    ← Form Request validation classes
├── Jobs/
├── Models/
├── Policies/        ← Authorization policies
├── Services/
│   ├── Workflow/    ← WorkflowService (core)
│   ├── Voting/      ← VotingService
│   ├── Audit/       ← AuditService
│   ├── Documents/   ← DocumentService
│   └── Notifications/
└── Support/
```

### Visibility enforcement

Organization-scoped filtering must happen at the **Eloquent query level** — never trust frontend scope.

### Audit logging

Every workflow transition logs to BOTH `request_stage_history` AND `audit_logs`. The `audit_logs` table includes `role` (role at time of action), `from_status`, and `to_status`.

### Voting concurrency

Vote submission (`POST /api/voting/{id}/vote`) and session closure (`POST /api/voting/{id}/close`) must use pessimistic locking (`lockForUpdate()`). Session closure atomically applies `AUTO_ABSTAIN_TIMEOUT` to all non-voted members.

### Support claim TTL

- TTL: 15 minutes (Redis key: `support_claim:{request_id}`)
- Heartbeat endpoint extends TTL: `POST /api/workflow/{id}/claim-support-review/heartbeat`
- Release endpoint: `DELETE /api/workflow/{id}/claim-support-review`

### Security

- Login rate limit: 5/min per IP (use Laravel's `RateLimiter`)
- Account lockout: 10 consecutive failures → 15-minute lockout
- File uploads: PDF only, private storage
- Failed auth: logged to `audit_logs` with appropriate action codes

## Canonical Enums (must match exactly)

Status enum values are defined in `AGENTS.md`. Implement as PHP-backed enums in `app/Enums/`.

Role enum values: `DATA_ENTRY`, `BANK_REVIEWER`, `SWIFT_OFFICER`, `SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, `CBY_ADMIN`

`COMMITTEE_DIRECTOR` inherits all `EXECUTIVE_MEMBER` permissions plus director actions.

## API Conventions

- All workflow actions: `POST /api/workflow/{id}/{action}`
- SWIFT upload: `POST /api/workflow/{id}/swift-upload` (NOT `/api/documents/`)
- Error format:
  ```json
  {
    "success": false,
    "message": "...",
    "error_code": "WORKFLOW_IMMUTABLE_STATE"
  }
  ```
- Immutable terminal states return HTTP 403 with `WORKFLOW_IMMUTABLE_STATE`

## Context7 Usage

Before writing Laravel/PHP implementation:

```bash
npx ctx7@latest library "Laravel" "<your question>"
npx ctx7@latest docs <id> "<your question>"
```

Use for: Sanctum, Eloquent, Queue, Redis, Policies, Form Requests, etc.

## SocratiCode Usage

Before modifying any service or model:

1. Search for the symbol: `codebase_symbol`
2. Check what calls it: `codebase_flow`
3. Assess impact: `codebase_impact`

## Verification Ladder

Before editing, run `git -c core.fsmonitor=false status --short` from `backend/` and report existing dirty files. Do not modify dirty files unless directly in scope.

Default verification is focused:

1. Run the smallest relevant PHPUnit file or `--filter` for the touched behavior.
2. Run Pint only for touched PHP files where possible.
3. Do not run full `php artisan test` by default.
4. Full backend suites are required only for release checks, broad refactors, security-critical changes, or explicit user requests.
5. If the full suite is known red, report the known baseline and do not treat unrelated failures as task failures.

Focused commands:

```bash
php artisan test tests/Feature/Auth/PasswordRecoveryTest.php
php artisan test --filter=PasswordRecoveryTest
php artisan test --filter='password reset with valid otp'
vendor/bin/pint app/Services/Workflow/WorkflowService.php --test
```

## Browser Automation

When backend work needs browser-based verification (integration paths, auth/session behavior, UI-linked APIs), use `playwright-cli` and keep the `playwright-cli` command prefix permanently allowlisted in local tool permissions.

## Docs Reference

Full rules in `../docs/` and `../AGENTS.md`. Key files:

- `../docs/01-workflow-and-business-rules.md` — workflow stages
- `../docs/03-database-and-models.md` — table schemas and enums
- `../docs/05-backend-guide.md` — backend architecture
- `../docs/06-api-reference.md` — API contracts
