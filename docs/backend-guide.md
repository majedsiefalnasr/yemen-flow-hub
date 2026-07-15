# Backend Guide

**Verified:** 2026-07-13, against `backend/routes/api.php`,
`backend/app/Services/{Workflow,Auth,Authorization,Audit,Customs,Settings}/`,
`backend/app/Http/Controllers/Api/{,V1/}`, `backend/app/Http/Controllers/Api/AuthController.php`,
`backend/app/Providers/AppServiceProvider.php`,
`backend/config/{auth_security,demo}.php`, `backend/app/Models/EngineRequest.php`,
and `php artisan route:list --path=api` directly — not carried over
from the legacy `docs/05-backend-guide.md`, which predates the dynamic
workflow engine and describes a fixed 18-value status vocabulary, an
active Voting Service, and a "Suggested API Structure" listing route
families (`/api/voting`, `/api/customs`, `/api/support-review`,
`/api/workflow`) that were never built as separate top-level groups.
Re-checked and corrected 2026-07-13 after an independent review found
6 issue groups (route topology, authentication mode duality, the scope
of the transition-enforcement rule, voting-residue reachability
precision, claim-setting ownership, and the plan record's file
accounting), then a follow-up review found 2 residual overstatements
(voting-residue dependency direction; the transition-enforcement rule
still read as absolute against `PerfLoadScenarioCommand`'s synthetic
fixture inserts) — see the Step 4C accuracy-correction record and its
follow-up in
[`archive/audit-functional/22-documentation-consolidation-plan.md`](archive/audit-functional/22-documentation-consolidation-plan.md).

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

`routes/api.php` registers **three surfaces**, not two — do not
describe the route topology as "unversioned `auth/` plus versioned
`v1/`" alone:

- `Route::prefix('auth')` — unversioned, public and Sanctum-protected
  auth endpoints (login, logout, password/MFA flows, demo-user
  switching where environment-gated).
- `Route::prefix('v1')->middleware(['auth:sanctum', 'active',
'throttle:api-default'])` — the versioned surface most engine/
  designer/admin-governance endpoints live under.
- **Additional unversioned route groups outside both prefixes** —
  verified directly in `routes/api.php`: a public
  `GET /api/settings/public`, and two `auth:sanctum`-protected groups
  covering `profile/*`, `settings/*`, `financing/utilization`,
  `admin/*` (health, settings, notification templates — distinct from
  the `v1`-prefixed admin/governance endpoints), `search/*`, and
  `dashboard/*`.

A controller living under the `App\Http\Controllers\Api\V1` PHP
namespace does not imply its route carries a `/v1` URL prefix, and vice
versa — namespace and URL prefix are independent; verify the actual
`Route::prefix()` nesting in `routes/api.php`, not the controller's
namespace, when determining whether an endpoint is versioned.

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

## Ordinary workflow transitions: `execute()` is the mandatory path — but it is not the only sanctioned mutation

There is no `ImportRequest` model in the current codebase — the request
model is `EngineRequest` (table `engine_requests`). For an **ordinary
workflow transition** (moving the request along a `WorkflowTransition`
in response to a user action), `current_stage_id` and `status` must
only change through `EngineTransitionService::execute()`, never by ad
hoc direct attribute assignment in a controller or arbitrary service
code. This is **not** enforced by a model-level `setAttribute()` guard
— verified directly: `EngineRequest` does not override `setAttribute()`,
and `App\Exceptions\DirectStatusMutationException` exists in the
codebase but is never thrown anywhere. Enforcement for transitions is
by convention and code review — controllers must route every ordinary
status/stage change through `execute()`, which itself locks the row
(`lockForUpdate()`), re-checks `isActive()` and the optimistic
`version`, validates the transition, permissions, claim ownership, and
field rules — see
[`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)'s
full 16-step `execute()` breakdown, not repeated here.

**`execute()` is not the only place in the engine that writes
`current_stage_id`/`status`, and that is by design, not a gap.**
`EngineRequestService` sets `current_stage_id`/`status` (`'ACTIVE'`)
directly when **creating** a new request — there is no "transition
into" the initial stage to execute; creation establishes the starting
state.

> **Lifecycle redesign in progress.** The `/draft` and `/abandon`
> endpoints — and `EngineTransitionService::abandonDraft()` — have been
> removed. A request is created and submitted as part of one atomic
> operation instead of being created eagerly and edited in place; see
> [`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)
> for the current state of that work. Do not cite `abandonDraft()` as a
> live sanctioned mutation path until this note is removed.

**This rule governs production request-handling paths — controllers,
services, jobs, and listeners that act on live data — not every line of
code in the repository.** `backend/app/Console/Commands/PerfLoadScenarioCommand.php`
is a real counterexample if the rule is read as absolute: it bulk-inserts
`current_stage_id`/`status` directly into `engine_requests` rows via a
chunked raw `insert()` (not Eloquent, not `execute()`), to generate
synthetic fixtures for performance-load testing. This is a **console
command generating throwaway test data**, not a request-handling code
path, and it is not evidence that direct mutation is acceptable in
production code — do not cite it as a supported mutation pattern.
Synthetic performance/test/seed fixture generation (this command, and
similarly `backend/database/seeders/`) is the one carved-out category
allowed to bypass `execute()`/`EngineRequestService`, precisely because
it never runs against live requests.

The rule this section states, precisely: in production request-handling
code, no path outside the engine's own service-managed lifecycle
operations (`execute()` for transitions, `EngineRequestService` for
creation) may write these columns directly. Synthetic fixture/seed
generation is a separate, non-production category, not an exception
that weakens the production rule.

```php
// ✅ Ordinary transition — the mandatory path
$engineTransitionService->execute($engineRequest, $transitionId, $comment, $data, $version, $user);

// ✅ Also sanctioned — explicit, narrower service-managed lifecycle operation,
// not ad hoc mutation: EngineRequestService (creation) sets
// current_stage_id/status directly, outside execute(), by design.

// ⚠️ Synthetic fixture generation (PerfLoadScenarioCommand, seeders) also
// writes these columns directly — but only for throwaway test data, never
// for a live request. Not a production mutation pattern to imitate.

// ❌ Never ad hoc direct assignment from a controller or arbitrary
// production service code — not blocked by a model guard, but forbidden
// by convention
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

## Authentication: cookie mode and token mode, selected per request

Laravel Sanctum, but **not cookie-only** — `AuthController::issueSession()`
branches on `$request->hasSession()`, verified directly:

- **Cookie mode** (`hasSession()` true — the typical SPA case): logs
  the user into the `web` guard (`Auth::guard('web')->login($user)`)
  and calls `$request->session()->regenerate()` (session-fixation
  protection). The response payload's `token`/`token_type` are `null`.
- **Token mode** (`hasSession()` false — e.g. non-cookie API clients):
  skips the guard/session entirely and issues a Sanctum personal access
  token via `$user->createToken(...)`, returned as
  `{token, token_type: 'Bearer'}`.

`logout()` mirrors this asymmetrically, not uniformly: it **always**
revokes the current personal access token when one is present
(`$user->currentAccessToken()->delete()`, unconditional), but only
invalidates the session and regenerates the CSRF token
(`session()->invalidate()` + `regenerateToken()`) **inside `if
($request->hasSession())`** — a token-mode request has no session to
invalidate. Do not describe session invalidation/CSRF regeneration on
logout as unconditional; it only runs for cookie-mode requests.

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

`EngineClaimService::ttlMinutes()` reads the `support_claim_ttl`
setting **through its injected `SettingResolver`** (constructor
dependency, verified directly — not `AdminSettingsService`) — this is
the value enforced at runtime, **not** a Redis-based TTL key.
`AdminSettingsService` owns the setting's catalog entry (default 15
minutes, 5–60 minute range) that ends up in the `SystemSetting` row
`SettingResolver` reads and caches, but it is not the class
`EngineClaimService` calls at runtime — do not attribute `ttlMinutes()`
to `AdminSettingsService` directly. `config('workflow.support_claim_ttl_minutes')`
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

## Executive Voting: out of V1 — dead, unwired residue, verified individually

There is no Voting Service, no vote-casting route, and no voting
session lifecycle in the current backend. A targeted search found real
residue — an isolated cluster of classes with no path into any
controller or route — verified individually rather than assumed dead
or assumed zero:

- `app/Http/Resources/VotingTallyResource.php` imports and directly
  references `app/DTOs/Voting/VotingTally.php` (`use
App\DTOs\Voting\VotingTally;`, typed as its `$resource`, reading its
  properties in `toArray()`). The dependency is **one-way**:
  `VotingTallyResource` depends on `VotingTally`; `VotingTally` itself
  is a plain DTO with zero imports and no reference back to the
  resource (verified by reading `VotingTally.php` directly). What makes
  both dead is that **neither is reachable from any controller or
  route**: grepped `app/Http/Controllers/` and `routes/` directly for
  both class names, zero matches.
- `app/Http/Resources/VoteResource.php` is a separate, standalone class
  (no dependency on `VotingTally`) — same zero-reachability result from
  the same grep.
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
  prefix groups. The actual structure is the three-surface topology
  described above (`auth/` unversioned, `v1/` versioned, and several
  additional unversioned protected/public groups) — see
  [`api-reference.md`](api-reference.md) for the full route inventory
  rather than a second, manually-duplicated list here.
- Fixed per-role "Visibility Scope By Role" sections describing static
  access rules per role name — visibility today runs through
  `DataScope`/`stage_permissions`/screen capabilities, documented in
  [`architecture/03-permission-model.md`](architecture/03-permission-model.md),
  not a hardcoded per-role table.
- The Redis-based-TTL-key claim lifecycle description — the live TTL
  source is the `SettingResolver`-backed `support_claim_ttl` setting
  (its catalog entry owned by `AdminSettingsService`), not a Redis key
  (see above).
- The unqualified "10 consecutive failures, 15-minute lockout" figure
  — lockout is admin-configurable; the config defaults are 5 attempts /
  15 minutes (see Security, above).
