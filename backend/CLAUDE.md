@../AGENTS.md

# Claude Code — Backend

Yemen Flow Hub Laravel 11 API backend.

## Git Scope

Backend code lives under `backend/` in the root monorepo (`git@github.com:majedsiefalnasr/yemen-flow-hub.git`). It is tracked as normal root files, not as a submodule or nested Git repository.

Commit backend changes from the root repository:

```bash
git add backend/<files>
git commit -m "feat(workflow): add support claim heartbeat endpoint"
```

Conventional commit format: `type(scope): description`
All commits must stay signed. Never use `--no-gpg-sign`, `--no-sign`, or `-c commit.gpgsign=false`; if signing fails, fix signing first.
Never add or commit generated artifacts from `graphify-out/`, `_bmad-output/implementation-artifacts/`, or `_bmad-output/test-artifacts/`. Keep them local only.

Examples:

- `feat(workflow): add support claim heartbeat endpoint`
- `fix(workflow): prevent race condition on concurrent stage transitions`
- `chore(db): add role column to audit_logs migration`

## Stack

- PHP 8.2+, Laravel 11
- Sanctum (HTTP-only cookie auth)
- MySQL + Redis
- Queue Workers (Redis driver)

## Architecture Rules

### EngineTransitionService is mandatory

All `EngineRequest` status/stage changes must go through `EngineTransitionService::execute()`, which validates stage permissions (`StagePermissionResolver`), field rules (`StageFieldRuleValidator`), and claim ownership before advancing the request along a `WorkflowTransition`. There is no `ImportRequest` model or `DirectStatusMutationException` guard in the current architecture — `EngineRequest` is the request model, and direct status mutation is prevented by routing all writes through the service layer rather than by an exception thrown from the model itself.

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
│   ├── Workflow/    ← EngineTransitionService, WorkflowDesignerService, EngineClaimService, EngineRequestService (core dynamic engine)
│   ├── Audit/       ← AuditService
│   ├── Documents/   ← DocumentService
│   └── Notifications/
└── Support/
```

### Visibility enforcement

Organization-scoped filtering must happen at the **Eloquent query level** — never trust frontend scope.

### Audit logging

Every workflow transition logs to BOTH `workflow_history` (the per-transition stage log, tied to `engine_requests`; replaces the dropped `request_stage_history` table) AND `audit_logs`. The `audit_logs` table includes `user_role` (role at time of action) plus `old_values`/`new_values` JSON — there are no dedicated `from_status`/`to_status` columns.

### Transition concurrency

`EngineTransitionService::execute()` locks the `EngineRequest` row (`lockForUpdate()`) before applying any stage transition, guarding every workflow action against concurrent moves on the same request — not a voting-specific mechanism. Executive Voting is not part of V1: there is no `/api/voting/*` route family, no vote-casting UI, and no voting session lifecycle in the current runtime. The underlying `VoteType`/vote-related enums remain in the codebase (backend voting-model deletion is gated to a later cleanup phase) but are not exercised by any live transition or UI surface.

### Support claim TTL

- TTL: 15 minutes by default (`config('workflow.support_claim_ttl_minutes')`), tracked in the `claim_expires_at` column on `engine_requests` (DB is the sole source of truth)
- Claim endpoint: `POST /api/engine-requests/{id}/claim`
- Heartbeat endpoint extends TTL: `POST /api/engine-requests/{id}/claim/heartbeat`
- Release endpoint: `DELETE /api/engine-requests/{id}/claim`

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

- All workflow actions execute through the generic engine-request transition endpoint: `POST /api/engine-requests/{id}/actions` (the specific transition/action is identified in the request body, not the URL)
- Document uploads (including SWIFT documents): `POST /api/engine-requests/{id}/documents`
- Error format:
  ```json
  {
    "success": false,
    "message": "...",
    "error_code": "REQUEST_CLOSED"
  }
  ```
- Immutable/terminal (non-`ACTIVE`) requests return HTTP 403 with `REQUEST_CLOSED` — the distinct `WORKFLOW_IMMUTABLE_STATE` code (HTTP 409) applies only to editing a published/archived workflow _version_ in the designer, not to runtime request state

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
vendor/bin/pint app/Services/Workflow/EngineTransitionService.php --test
```

## Browser Automation

When backend work needs browser-based verification (integration paths, auth/session behavior, UI-linked APIs), use `playwright-cli` and keep the `playwright-cli` command prefix permanently allowlisted in local tool permissions.

## Docs Reference

Full rules in `../docs/` and `../AGENTS.md`. Key files:

- `../docs/architecture/02-workflow-engine.md` — workflow engine: Designer lifecycle, topology, publishing, runtime transitions
- `../docs/architecture/06-database-and-models.md` — table schemas and enums
- `../docs/backend-guide.md` — backend architecture
- `../docs/api-reference.md` — API contracts
