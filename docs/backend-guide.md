# Backend Guide

**Verified:** 2026-07-13, against `backend/routes/api.php`,
`backend/app/Services/{Workflow,Auth,Authorization,Audit,Customs}/`,
`backend/app/Http/Controllers/Api/`, `backend/app/Providers/AppServiceProvider.php`,
`backend/config/{auth_security,demo}.php`, `backend/app/Models/EngineRequest.php`,
and `php artisan route:list --path=api` directly — not carried over
from the legacy `docs/05-backend-guide.md`, which predates the dynamic
workflow engine and describes a fixed 18-value status vocabulary, an
active Voting Service, and a "Suggested API Structure" listing route
families (`/api/voting`, `/api/customs`, `/api/support-review`,
`/api/workflow`) that were never built as separate top-level groups.

This document covers backend-specific conventions and operational
rules. It is not the primary authority for topics that already have a
canonical document — those are linked, not duplicated:

- [`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)
  — Designer lifecycle, workflow topology, publishing, runtime
  transitions (the authoritative source for everything about
  `EngineTransitionService::execute()`).
- [`architecture/03-permission-model.md`](architecture/03-permission-model.md)
  — full authorization mechanics: screen capabilities, stage
  permissions, `DataScope`.
- [`architecture/05-request-state-model.md`](architecture/05-request-state-model.md)
  — the four-field request-state model.
- [`architecture/06-database-and-models.md`](architecture/06-database-and-models.md)
  — table schemas.
- [`api-reference.md`](api-reference.md) — the route inventory and its
  Coverage status section; also the verified Executive Voting
  cleanup-debt inventory. **This document does not attempt to list
  routes.** Route totals are environment-dependent (demo/switch-role
  routes register only when `config('demo.allowed_environments')`
  permits it) — re-run `php artisan route:list --path=api` in the
  target environment rather than trusting any count.

---

## Stack

Laravel 11, PHP 8.2+ (`backend/composer.json`'s `require.php` — verify
against that file rather than assuming a specific minor), MySQL, Redis
(queues, cache, claim TTL is admin-setting-backed, not Redis-backed —
see below), Laravel Sanctum, REST API, service-oriented architecture.

---

## Service-oriented architecture — no monolithic services

There is no single monolithic "Workflow Service" or "Voting Service"
class. The workflow engine is a set of focused services in
`app/Services/Workflow/` — see
[`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)
for the complete service table
(`EngineTransitionService`/`WorkflowDesignerService`/
`StagePermissionResolver`/`EngineClaimService`/`StageFieldRuleValidator`/
`SemanticResolver`/`StageHookRegistry`/etc.). Business logic lives in
services, not controllers, models, or route closures.

```text
backend/app/
├── Actions/
├── DTOs/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/Api/{, V1/}
│   ├── Middleware/
│   └── Requests/
├── Jobs/
├── Listeners/
├── Models/
├── Notifications/
├── Policies/
├── Services/
│   ├── Workflow/       ← EngineTransitionService, WorkflowDesignerService, EngineClaimService, etc.
│   ├── Authorization/  ← DataScope, DataScopeContext, StagePermissionResolver
│   ├── Auth/           ← AuthSecuritySettings, session/lockout logic
│   ├── Audit/          ← AuditService
│   ├── Customs/        ← EngineCustomsService, CustomsDeclarationGenerator (see below — legacy naming, live code)
│   └── Notifications/  ← EngineNotificationDispatcher
└── Support/
```

---

## Immutable workflow-version state — enforced by service gating, not a database trigger

`WorkflowVersionState::isEditable()` is true only for `DRAFT`, enforced
by `WorkflowDesignerService` (stages/transitions/stage permissions) and
independently by `FieldDesignerService` (field groups/definitions/stage
field rules) before every mutation — a `PUBLISHED` or `ARCHIVED`
version's topology cannot be edited through either service; attempting
to do so throws `WorkflowVersionImmutableException`. Full detail,
including the exact `ensureValidStateTransition()` match arm and the
publish/archive sequencing, is in
[`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)
— not duplicated here.

---

## Runtime transition enforcement — the only path for a stage/status change

There is no `ImportRequest` model in the current codebase — the request
model is `EngineRequest` (table `engine_requests`). The request's
`current_stage_id` (and coarse `status`) must only change through
`EngineTransitionService::execute()`, never by direct attribute
assignment on the model. This is **not** enforced by a model-level
`setAttribute()` guard — verified directly: `EngineRequest` does not
override `setAttribute()`, and `App\Exceptions\DirectStatusMutationException`
exists in the codebase but is never thrown anywhere. Enforcement today
is by convention and code review — controllers and services must route
every status/stage change through `EngineTransitionService::execute()`,
which itself locks the row (`lockForUpdate()`), re-checks `isActive()`
and the optimistic `version`, validates the transition, permissions,
claim ownership, and field rules — see
[`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)'s
full 16-step `execute()` breakdown, not repeated here.

```php
// ✅ Only path for a stage/status change
$engineTransitionService->execute($engineRequest, $transitionId, $comment, $data, $version, $user);

// ❌ Never direct assignment — not blocked by a model guard, but forbidden by convention
$engineRequest->current_stage_id = $someOtherStageId;
$engineRequest->save();
```

---

## Organization and data-scope enforcement

Visibility enforcement runs through `App\Services\Authorization\DataScope`
and `DataScopeContext` — the same mechanism documented in full in
[`architecture/03-permission-model.md`](architecture/03-permission-model.md).
Requests must never be exposed outside a user's organization scope;
enforce this at the query level (scoped query builders/services), not
by filtering an unscoped result set after the fact.

---

## Authentication

Laravel Sanctum, secure session authentication with HTTP-only cookies.
Session fixation protection on login is real, verified directly:
`AuthController` calls `$request->session()->regenerate()` on
successful login and `regenerateToken()` on logout-related paths.

---

## Security — verified numbers, not assumed ones

- **Login rate limit:** `throttle:5,1` on `POST /api/auth/login`
  (verified in `routes/api.php`) — 5 attempts/minute per the throttle
  key (IP by default).
- **Account lockout:** admin-configurable, **not** a fixed constant.
  `AuthSecuritySettings::lockoutAttempts()`/`lockoutDurationMinutes()`
  resolve through `SettingResolver` against
  `config('auth_security.login_lockout_attempts')` /
  `login_lockout_duration`. The **config defaults are 5 attempts / 15
  minutes** (`config/auth_security.php`) — not the "10 consecutive
  failures" figure some prior documentation cited. Treat the live
  admin setting as authoritative for the actual enforced value in any
  given environment, the same pattern as the claim TTL below.
- **API rate limit:** `RateLimiter::for('api-default', ...)` in
  `AppServiceProvider` — `config('auth_security.api_throttle_per_minute',
  120)` requests/minute, keyed by authenticated user ID when present,
  by IP otherwise.
- **CSRF / session:** Sanctum SPA-mode CSRF cookie flow; session
  regeneration on login (see above).
- **Failed-auth audit logging:** `AuthController::logFailedLogin()`
  calls `AuditService::log(AuditAction::LOGIN_FAILED, null, null, [...])`
  — actor is explicitly `null` for unauthenticated failures, verified
  directly.
- **File uploads:** PDF-only, enforced by `mimetypes:application/pdf`
  validation rules — verified across `UploadDocumentRequest`,
  `UploadSwiftRequest`, and `FxConfirmationUploadRequest`. Private
  storage; size limits resolved via an injected upload-size-limit
  service, not a hardcoded constant.
- **Response envelope:** `App\Support\ApiResponse` provides the general
  `{success, message, data}` / `{success, message, errors, error_code}`
  shape used broadly; some workflow-designer endpoints use a distinct
  `{error: {code, message, fields, request_id}}` shape instead (see
  `WorkflowActionController::error()` for one example). Do not assume a
  single envelope shape across every endpoint — check the specific
  controller, or see [`api-reference.md`](api-reference.md) for
  documented response shapes per endpoint family.

---

## Claim lifecycle: admin setting, not a Redis TTL key

`EngineClaimService::ttlMinutes()` reads the admin-configurable
`support_claim_ttl` setting (`AdminSettingsService`, default 15
minutes, 5–60 range) — this is the value enforced at runtime, **not**
a Redis-based TTL key. `config('workflow.support_claim_ttl_minutes')`
exists but is not read by the runtime claim service; it is still read
directly by `backend/database/seeders/Support/EngineRequestScenarioBuilder.php`
when constructing claimed-request seed scenarios. Full claim-lifecycle
detail (heartbeat cadence, release conditions, the permission-before-claim
check order) is in
[`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)'s
Claim lifecycle section — not repeated here.

---

## Audit logging

`AuditService` writes to `audit_logs`. Every workflow transition also
writes a `workflow_history` row (this table replaces the dropped
`request_stage_history` table) — see
[`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)'s
`execute()` breakdown for the exact write order and shared
`correlation_id`. Include `role` (at time of action) in every audit log
entry involving a user.

---

## Executive Voting: out of V1 — some backend residue is live-referenced dead code, verified individually

There is no Voting Service, no vote-casting route, and no voting
session lifecycle in the current backend. A targeted search found real
residue, verified individually rather than assumed dead or assumed
zero:

- `app/DTOs/Voting/VotingTally.php` and `app/Http/Resources/VoteResource.php`
  — confirmed self-referencing only; no controller, route, or other
  service references either class. Genuinely dead code.
- `app/Http/Resources/VotingTallyResource.php` — same: no route or
  controller wiring found.
- `routes/api.php` has zero routes containing "voting" or "vote" in
  their path.

None of this is active backend behavior. For the complete, previously
verified backend/frontend cleanup-debt inventory (including any dead
enum cases not covered above), see
[`api-reference.md`](api-reference.md)'s "Executive Voting (out of V1
— no live routes)" section — not re-inventoried here.

---

## Customs-named code is legacy naming on a live path, not dead code — do not conflate the two

Unlike the voting residue above, the `Customs`-named backend surface is
**actively wired into the live external FX confirmation flow**, verified
directly:

- `app/Models/CustomsDeclaration.php` backs the live `customs_declarations`
  database table.
- `app/Services/Customs/EngineCustomsService.php` and
  `CustomsDeclarationGenerator.php` are referenced by
  `EngineFxConfirmationController` and registered in
  `AppServiceProvider` — actively called on the request path, not dead.
- `app/Services/Workflow/Effects/CustomsFxPdfEffect.php` is a real,
  registered stage-hook effect.
- `app/Exceptions/CustomsException.php` is one of the domain exceptions
  `EngineTransitionService::execute()` explicitly propagates as-is
  during stage hook/effect execution (see
  [`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)).
- Two of `EngineFxConfirmationController`'s endpoints use the URL
  segment `customs-declaration` (e.g.
  `GET .../customs-declaration/download`) — a legacy path segment on an
  otherwise current controller, not a separate customs feature.

Align new copy and new code to external FX confirmation
(`تأكيد مصارفة خارجية`) terminology — do not introduce new
customs-declaration—facing language. But do not describe the existing
`Customs`-named classes, the `customs_declarations` table, or the
`customs-declaration` URL segments as dead code slated for removal;
they are the live implementation, carrying forward pre-rename naming.

---

## What this document removes from the legacy source

The following legacy content is **not** carried forward, and must not
be reintroduced:

- The fixed 18-value status vocabulary (`SUPPORT_REVIEW_PENDING`,
  `WAITING_FOR_SWIFT`, `CUSTOMS_DECLARATION_ISSUED`, etc.) and its
  "Editable States"/"Locked States" framing — replaced entirely by the
  four-field model in
  [`architecture/05-request-state-model.md`](architecture/05-request-state-model.md).
- `support_claimed_by`/`support_claimed_at`/`current_status` as live
  field names — the current model uses `claimed_by`/`claimed_at`/
  `claim_expires_at`/`status` on `EngineRequest`; see
  [`architecture/06-database-and-models.md`](architecture/06-database-and-models.md)
  for the exact schema.
- A dedicated "Voting Service," vote-creation/majority-calculation/tie-
  handling logic, and the full "Voting Rules"/"Voting Session
  Rules"/"Voting Restrictions" sections describing a live feature.
  Executive Voting is out of V1; see the section above for what
  residue actually still exists.
- "Suggested API Structure" listing `/api/workflow`, `/api/support-review`,
  `/api/voting`, `/api/customs` as separate top-level route groups —
  verified against `routes/api.php`: none of these exist as top-level
  prefix groups. The actual structure is `Route::prefix('auth')`
  (unversioned) and `Route::prefix('v1')` (everything else,
  `auth:sanctum` + `active` + `throttle:api-default` gated). See
  [`api-reference.md`](api-reference.md) for the full route inventory
  rather than a second, manually-duplicated list here.
- Fixed per-role "Visibility Scope By Role" sections describing static
  access rules per role name — visibility today runs through
  `DataScope`/`stage_permissions`/screen capabilities, documented in
  [`architecture/03-permission-model.md`](architecture/03-permission-model.md),
  not a hardcoded per-role table.
- The Redis-based-TTL-key claim lifecycle description — the live TTL
  source is the `AdminSettingsService`-backed `support_claim_ttl`
  setting, not a Redis key (see above).
- The unqualified "10 consecutive failures, 15-minute lockout" figure
  — lockout is admin-configurable; the config defaults are 5 attempts /
  15 minutes (see Security, above).
