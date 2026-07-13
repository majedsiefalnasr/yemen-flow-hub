# API Reference

## Coverage status

**Verification method:** `php artisan route:list --path=api`, run
directly on this repository's `backend/` in the local development
environment, most recently on 2026-07-13 (originally 2026-07-12).
Route totals are **not** recorded here as a
fixed number — demo/switch-role routes (`api/auth/demo-users`,
`api/auth/switch-demo-user`, `api/auth/switch-demo-role`) register
conditionally on `config('demo.allowed_environments')`, so the total
route count varies by environment (e.g. staging/production with demo
routes disabled will show fewer routes than local). Re-run the command
above in the target environment to get an accurate count rather than
trusting any number recorded here.

**Re-verified 2026-07-13** (Step 13): every route returned by
`php artisan route:list --path=api` at that date is accounted for
below, either documented directly or explicitly excluded with a
reason (see "Route Families Intentionally Not Documented"). Every
endpoint documented was checked against a real registered route at
verification time, and the accompanying claims (settings keys, error
codes, permission matrices, request-validation fields, row limits)
were checked against the implementing source files — not carried over
unverified or invented where the implementation doesn't guarantee a
shape. Two families' Form Requests were verified for Store/Update
field lists but not for every nested/type-conditional validation
branch (Merchants' `owners`/`companies` array sub-fields; Field
Definitions' type-specific fields) — the controller/Form Request
source remains authoritative for exhaustive edge cases on those two.
`WorkflowVersionController@graph`'s exact node/edge response schema
and `ProfileController`'s exact "revoke all except current" semantics
were verified to exist and route correctly but their precise internal
shape was not independently re-derived from the service layer in this
pass — flagged inline at each occurrence above rather than asserted.

**Coverage is complete** for the registered application route surface.
The prior gap list (Workflow Designer admin, reference data admin,
org-structure admin, merchants, governance/compliance, analytics
reports, profile/MFA/session management, search, remaining
`AuthController` routes, and the smaller per-family gaps) is now fully
documented below, plus four routes found during this pass's route-list
re-run that weren't named in the original gap enumeration
(`api/admin/health`, `api/admin/notification-templates/*`,
`api/financing/utilization`, `api/dashboard/work` — the last of which
is documented by reference to
[`architecture/04-dashboard-architecture.md`](architecture/04-dashboard-architecture.md)
rather than restated here). `horizon/api/*` and the Swagger UI routes
(`api/documentation`, `api/oauth2-callback`) are intentionally excluded
as third-party package infrastructure, not application routes.

---

# API Overview

Yemen Flow Hub uses a REST API architecture.

The frontend communicates with the backend only through API endpoints.

Base URL example:

```text
/api
```

Authentication is handled using Laravel Sanctum.

---

# Authentication APIs

# Login

## Endpoint

```http
POST /api/auth/login
```

## Request Body

```json
{
  "email": "user@example.com",
  "password": "password"
}
```

## Response

```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "Ahmed Ali",
    "role": "BANK_REVIEWER"
  }
}
```

## Account Lockout Response

After `login_lockout_attempts` consecutive failed credentials from the same account/source pair (default **5**), the API returns HTTP `429 Too Many Requests` with a `Retry-After` header containing the seconds until the lockout window expires (default **15** minutes / `900` seconds via `login_lockout_duration`).

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 900
```

```json
{
  "success": false,
  "message": "Account is temporarily locked due to too many failed attempts.",
  "error_code": "ACCOUNT_LOCKED"
}
```

---

# Current User

## Endpoint

```http
GET /api/auth/me
```

---

# Logout

## Endpoint

```http
POST /api/auth/logout
```

---

# Request APIs (Dynamic Workflow Engine)

All request endpoints live under `/api/v1/engine-requests` and operate on `EngineRequest` records tied to a published `WorkflowVersion`. There is no `GET /api/requests` route — that fixed-path family belongs to the pre-engine architecture and no longer exists.

# Get Requests

## Endpoint

```http
GET /api/v1/engine-requests
```

## Query Parameters

- `workflow_id` — filter by workflow definition
- `workflow_version_id` — filter by workflow version
- `stage_id` — filter by `current_stage_id`
- `bank_id` — bank filter
- `merchant_id` — merchant filter
- `status` — `ACTIVE` | `CLOSED` | `REJECTED` | `CANCELLED` | `ABANDONED` (`EngineRequestListQuery::ALLOWED_STATUSES`; the coarse lifecycle flag, not a business status)
- `search` — matches `reference` or `invoice_number`
- `created_from`, `created_to` — created-at date range filters
- `sla_status` — `ok` | `nearing` | `breached`
- `per_page` — 1–100, default 25

Non-admin users are scoped to stages they may VIEW (via `stage_permissions`); `system_admin` is unscoped.

## Response

```json
{
  "data": [
    /* EngineRequestResource[] */
  ],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 25, "total": 62 }
}
```

---

# Get My Queue

## Endpoint

```http
GET /api/v1/engine-requests/my-queue
```

Same filters as `GET /api/v1/engine-requests`, but always scoped to `ACTIVE` requests whose current stage the caller may EXECUTE (not just VIEW), ordered by SLA priority (breached → nearing breach → oldest-in-stage).

---

# Get Request List Stats

## Endpoint

```http
GET /api/v1/engine-requests/stats
```

## Query Parameters

- `scope` — `all` (default) or `queue`
  - `all` — same visibility as `GET /api/v1/engine-requests` (VIEW-scoped stages; `system_admin` unscoped)
  - `queue` — same visibility as `GET /api/v1/engine-requests/my-queue` (EXECUTE-scoped active requests)
- All list filters from `GET /api/v1/engine-requests` (`search`, `status`, `sla_status`, `workflow_id`, `stage_id`, `bank_id`, `merchant_id`, `created_from`, `created_to`, …) — aggregates apply to the **full filtered dataset**, not the current paginated page.

## Response

```json
{
  "data": {
    "total": 62,
    "active": 48,
    "breached_sla": 5,
    "nearing_sla": 3,
    "unclaimed_active": 7,
    "by_status": { "ACTIVE": 48, "CLOSED": 10, "REJECTED": 4 }
  }
}
```

Stats queries use the same organization/stage scoping as list endpoints (`EngineRequest::forUser` + `StagePermissionResolver` + `EngineRequestListQuery::applyFilters`). They must never return counts wider than the caller may list.

---

# Request Visibility Rules

Request visibility combines **two independent dimensions** — being in
the same organization does not by itself grant access to every request
in that organization:

1. **Organization/bank scope** — `DataScope::forUser()` +
   `EngineRequest::scopeForUser()` (`applyTo(..., 'engine_requests.bank_id')`).
2. **Stage VIEW permission** — for any non-`system_admin` user, the list
   endpoint additionally intersects against
   `StagePermissionResolver::accessibleStageIds($user, StageAccessLevel::VIEW)`
   (`App\Http\Controllers\Api\V1\EngineRequestController::index()`). Only
   `system_admin` bypasses this and sees every stage.

A user must satisfy **both** to see a given request — organization/bank
membership alone is not sufficient. Role-scoped actions and queue-based
operational filtering apply on top of this base visibility.

Visibility enforcement happens at:

- Query level (`DataScope::applyTo()`, `accessibleStageIds()`)
- API level (controller composes both scopes)
- Policy level

The platform is NOT a shared admin dashboard.

All request APIs must return only operationally relevant data.

---

# Create Request

## Endpoint

```http
POST /api/v1/engine-requests
```

## Request Body

```json
{
  "workflow_version_id": 1,
  "bank_id": 3,
  "merchant_id": 12,
  "data": {
    "supplierName": "Supplier Ltd",
    "amount": 50000,
    "currency": "USD"
  }
}
```

`data` holds the dynamic field values defined by the workflow version's field groups/definitions (see `GET /api/v1/engine-requests/{id}/form-schema`) — there is no fixed `supplier_name`/`goods_description`/`port_of_entry` request schema; the actual required fields depend on the workflow version.

## Response

`201 Created`: `{ "success": true, "message": "Request created successfully.", "data": { /* EngineRequestResource */ }, "warnings"?: [ /* duplicate-invoice warnings */ ] }`

---

# Get Request Details

## Endpoint

```http
GET /api/v1/engine-requests/{id}
```

## Response Includes

- Request details (`data` JSON plus hybrid projection columns: `amount`, `currency`, `invoice_number`)
- `current_stage` (the `WorkflowStage` the request occupies — this is what expresses business status, not a `current_status` column)
- Whether the signed-in user may EXECUTE the current stage
- `claimed_by` (if the current stage requires a claim and one is held)
- Bank, merchant, creator
- `sla_status`

```json
{
  "success": true,
  "data": {
    "id": 42,
    "reference": "IF-2026-000042",
    "status": "ACTIVE",
    "current_stage": { "id": 3, "code": "SUPPORT", "name": "..." },
    "claimed_by": { "id": 33, "name": "..." },
    "created_by": 12,
    "data": { "...": "..." },
    "amount": 50000,
    "currency": "USD",
    "sla_status": "ok"
  }
}
```

There is no fixed set of `*_by`/`*_by_user` actor fields (e.g. `submitted_by`, `reviewed_by`, `swift_uploaded_by`) on this response — per-transition actor history is retrieved separately via `GET /api/v1/engine-requests/{id}/history`, where each entry carries its own `performed_by`.

---

# Get Request Form Schema

## Endpoint

```http
GET /api/v1/engine-requests/{id}/form-schema
```

Returns the field groups/fields defined for the request's workflow version, with per-current-stage visibility/editability/required resolved from stage field rules.

---

# Save Draft

## Endpoint

```http
PATCH /api/v1/engine-requests/{id}/draft
```

## Request Body

```json
{
  "data": { "...": "..." },
  "version": 3
}
```

`version` is the optimistic-concurrency token; it must match the request's current `version` column or the call fails with `REQUEST_STALE` (409).

---

# Update / Delete Request

There is no dedicated `PUT`/`DELETE /api/v1/engine-requests/{id}` endpoint. Requests are modified only via `PATCH .../draft` (while in an editable stage) or by executing a transition via `POST .../actions`; there is no request-deletion endpoint in the current API — draft requests are abandoned rather than deleted through this API.

"Editable" is not a fixed status-name whitelist — `PATCH .../draft` uses the same gate as executing a transition (`runtime_status: ACTIVE` + EXECUTE stage permission + claim held, if the stage requires one; see [`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md#savedraft-not-gated-by-a-fixed-editable-states-list)). The API enforces this by rejecting `draft`/`actions` calls with `REQUEST_CLOSED` (403) once the request is no longer `ACTIVE`, or `STAGE_EXECUTION_FORBIDDEN`/`CLAIM_NOT_HELD` if the caller no longer holds the required permission/claim on the current stage.

---

# Workflow APIs

There is no per-action fixed route family (`POST /api/workflow/{id}/submit`, `.../bank-approve`, `.../support-approve`, etc.) — every workflow action (submit, bank approve/reject, support approve/reject, SWIFT upload's status effect, executive review decision, finalize decision) is executed through one generic endpoint:

# Execute a Workflow Action (Generic)

## Endpoint

```http
POST /api/v1/engine-requests/{id}/actions
```

## Request Body

```json
{
  "transition_id": 7,
  "comment": "Optional reviewer comment",
  "data": { "...": "..." },
  "version": 3
}
```

- `transition_id` identifies a `WorkflowTransition` (a specific from-stage + action + to-stage combination) — the set of transitions available for a request is discoverable from its current stage via `GET /api/v1/engine-requests/{id}/graph` (which flags which transitions are currently `possible` for the caller). Graph edges include `confirmation_message`, `is_destructive`, and `is_default_submit`; the draft wizard uses `is_default_submit` (or the sole outgoing edge) to pick the submit transition.
- `comment` is required when the transition's `requires_comment` flag is set (e.g. a bank rejection reason).
- `version` is the optimistic-concurrency token; it must equal the request's current `version` column.

## Response

`200 OK`: `{ "success": true, "message": "Transition executed successfully.", "data": { /* EngineRequestResource */ }, "warnings"?: [...] }`

## Error Codes

| Code                        | HTTP | Meaning                                                                                                                                               |
| --------------------------- | ---- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| `REQUEST_CLOSED`            | 403  | Request is no longer `ACTIVE`                                                                                                                         |
| `REQUEST_STALE`             | 409  | `version` does not match the request's current version                                                                                                |
| `TRANSITION_NOT_AVAILABLE`  | 422  | The transition doesn't exist, or doesn't originate from the current stage                                                                             |
| `STAGE_EXECUTION_FORBIDDEN` | 403  | Caller lacks EXECUTE access to the current stage                                                                                                      |
| `CLAIM_NOT_HELD`            | 403  | The stage requires a claim and the caller doesn't hold it                                                                                             |
| `COMMENT_REQUIRED`          | 422  | Transition requires a comment and none was provided                                                                                                   |
| `STAGE_FIELDS_INVALID`      | 422  | Per-stage field validation failed (includes an `errors` array)                                                                                        |
| `STAGE_HOOK_FAILED`         | 422  | An unexpected error from a stage entry/exit hook (domain-specific exceptions, e.g. `FinancingLimitExceededException`, propagate with their own codes) |

Bank approval/rejection, support approval/rejection/return, an executive review decision, and finalizing a decision are all just different `transition_id` values executed through this one endpoint — they are not separate routes.

---

# Support Review Claiming

Claim endpoints are nested under the request, not under a fixed `/workflow/{id}/claim-support-review` path:

```http
POST   /api/v1/engine-requests/{id}/claim
POST   /api/v1/engine-requests/{id}/claim/heartbeat
DELETE /api/v1/engine-requests/{id}/claim
```

## Rules

- Only stages with `requires_claim = true` (e.g. support review) use this lifecycle
- Only one reviewer can hold an active claim at a time (`STAGE_CLAIMED`, 409, on conflicting claim attempts)
- Claims expire after a TTL — the live value is the admin-configurable `support_claim_ttl` setting, read by `EngineClaimService` via `SettingResolver::get()` (default 15 minutes, 5–60 range; `AdminSettingsService` owns the setting's catalog entry — default, valid range, admin-console exposure — but is not itself in the runtime read path; see Admin Operational Settings below), not the `config('workflow.support_claim_ttl_minutes')` key, which exists but is not read by the claim service — unless extended by a heartbeat
- The heartbeat endpoint must be called every 60 seconds by the frontend while the reviewer is on the request page, or the claim expires
- Only the current claim holder may heartbeat/release their own claim (`CLAIM_NOT_HELD`, 403, otherwise); `system_admin` may force-release any claim
- Claiming/heartbeat/release responses return the updated `EngineRequestResource` (`{ "success": true, "data": {...} }`)

---

# Executive Voting (out of V1 — no live routes)

Executive Voting has **no live or mounted V1 voting route, screen, or
action surface.** There is no `POST /api/voting/{id}/vote`, no `.../close`
endpoint, no dedicated `/api/voting/*` route family, no `request_votes`
table (physically dropped — see
[`architecture/06-database-and-models.md`](architecture/06-database-and-models.md)),
no `VotingSessionStatus`, no voting page/route in `frontend/app/pages/`,
and no route or middleware registration for a voting screen.

**This does not mean zero voting-related code exists — in either
`backend/app` or `frontend/app`.** Legacy compatibility/dead-code symbols
remain on both sides, traced individually and confirmed unreachable from
any live route, mounted component, or active data path (not assumed dead
from the symbol name alone):

**Backend:**

- `NotificationType::VOTING_OPENED` (enum case) and matching voting
  notification templates in the notification-template registry —
  unreferenced by any live transition.
- `AuditAction::VOTE_CAST` (enum case) — no code path constructs an
  audit log entry with this action.
- `App\DTOs\Voting\VotingTally` and `App\Http\Resources\VotingTallyResource`
  — dead classes, not constructed anywhere reachable from a controller.
- The dashboard-stats voting fields (`waiting_for_voting_open`,
  `active_voting_sessions`, `voting_queue`) — see the Dashboard APIs
  section below; hardcoded to zero/empty, not backed by live data.

**Frontend:**

- `VoteType` (`frontend/app/types/enums.ts`) and the `vote: VoteType`
  field on a model interface (`frontend/app/types/models.ts`) — zero
  consumers outside their own declarations and unit tests.
- `action.voting.cast` / `action.voting.close_finalize` role-surface
  capability strings (`frontend/app/constants/role-surfaces.ts`) — listed
  in the capability catalog for every role, but no composable, store, or
  component anywhere in `frontend/app` queries either specific key at
  runtime; not gating any mounted UI.
- `voting_session_timeout` / `secret_voting` typed fields
  (`useAdminSettings.ts`) and `voting_analytics` typed field
  (`useReports.ts`) — present in TypeScript interfaces, referenced only
  by unit-test fixtures, never read or rendered by any `.vue` template.
- A dead CSS rule, `.notification-row--voting`
  (`frontend/app/pages/notifications.vue`) — the class it styles is never
  conditionally applied to any element.

**Not residue — do not conflate with the above:** `var(--voting)` /
`var(--color-voting)` and the `MetricCard` `tone="voting"` prop value
(seen in `ActionRequiredStrip.vue`, `MetricCard.vue`,
`ActiveReviewBanner.vue`, `DashboardKpiCard.vue`, `reports/index.vue`)
are the shared **design-system color token** (indigo, `#5856d6`, per root
`DESIGN.md`) reused for unrelated KPI/banner styling — e.g. coloring an
"average processing time" metric card. These are visual tokens, not
voting functionality, and were individually checked to confirm they
render generic content, not vote-related data.

These are recorded here as **cleanup debt**, not corrected in this
documentation pass — removing dead code is out of scope for a
documentation correction. Do not build new functionality that depends on
any of these symbols; do not assume they are wired to anything live
because they still compile.

---

# Document APIs

There is no standalone `/api/documents/*` route family. Documents are nested under the owning request:

# Upload Request Document

## Endpoint

```http
POST /api/v1/engine-requests/{id}/documents
```

## Request Type

```text
multipart/form-data
```

## Request Body

- `file` (required, PDF only, max 10 MB)
- `field_id` (optional — links the document to a specific dynamic field on the request's workflow version)

## Allowed File Types

- PDF only
- Upload routes are authenticated and rate-limited: exceeding the upload throttle returns HTTP `429` with the standard JSON error envelope.
- Client-supplied filenames are sanitized before persistence and again before download headers are generated; the private on-disk filename remains UUID-based.

---

# List Request Documents

## Endpoint

```http
GET /api/v1/engine-requests/{id}/documents
```

---

# Delete Request Document

## Endpoint

```http
DELETE /api/v1/engine-requests/{id}/documents/{document}
```

Returns `DOCUMENT_LOCKED` (422) if the document was uploaded on a stage the request has already left — documents can only be deleted while the request is still on the stage they were uploaded for.

---

# Replace Request Document

Documents support **controlled versioned replacement**, not blanket
immutability. Not documented in the prior version of this file — added
here after finding
`App\Services\Documents\EngineRequestDocumentReplacementService` and its
route.

## Endpoint

```http
POST /api/v1/engine-requests/{engineRequest}/documents/{document}/replace
```

## Behavior

`EngineRequestDocumentReplacementService::replace()` — the existing
document is marked `status: Superseded` with `superseded_by` set to the
new document's ID; the new document is created `status: Active` with
`version` incremented by one. Both writes happen inside one DB
transaction, and the replacement is audited
(`AuditAction::DOCUMENT_REPLACED`).

## Restrictions

- Returns `DOCUMENT_NOT_REPLACEABLE` (422) if the target document is
  soft-deleted (`trashed()`), or if its current `status` is not `Active`
  (i.e. it was already superseded by an earlier replacement).
- **Not gated by stage or document type.** Replacement is not restricted
  to the stage the document was originally uploaded on, and there is no
  document-type exclusion (e.g. no SWIFT-specific carve-out) in
  `EngineRequestDocumentReplacementService` — the `DOCUMENT_LOCKED`
  stage-exit restriction described under Delete Request Document above
  applies only to **deletion**, not to replacement.
- PDF only, same upload validation as the initial upload endpoint.

---

# Upload SWIFT Document

There is no separate `/api/workflow/{id}/swift-upload` endpoint. The SWIFT officer uploads the SWIFT document through the same generic document endpoint used by every other stage:

```http
POST /api/v1/engine-requests/{id}/documents
```

## Restrictions

- Only SWIFT Officer role (enforced via stage permissions on the SWIFT-upload stage, not a hardcoded role check on this endpoint)
- Only while the request is on the stage that requires the SWIFT document (applies to the **initial upload**; a subsequent replacement goes through `POST .../documents/{document}/replace` above, which is not stage-gated — see that section for its own restrictions)
- Request remains read-only for that stage once the required document is present and the transition to the next stage executes
- PDF only
- Authenticated and rate-limited: exceeding the upload throttle returns HTTP `429` with the standard JSON error envelope
- Client-supplied filenames are sanitized before persistence and again before download headers are generated; the private on-disk filename remains UUID-based

---

# Download Document

## Endpoint

```http
GET /api/v1/engine-requests/{id}/documents/{document}/download
```

## Permission Matrix

| Role               | Request Documents | SWIFT Document | External FX Confirmation PDF |
| ------------------ | ----------------- | -------------- | ---------------------------- |
| DATA_ENTRY         | Own bank only     | No             | No                           |
| BANK_REVIEWER      | Own bank only     | Own bank only  | Own bank only                |
| BANK_ADMIN         | Own bank only     | Own bank only  | No                           |
| SWIFT_OFFICER      | Own bank only     | Own bank only  | No                           |
| SUPPORT_COMMITTEE  | Yes (all banks)   | No             | No                           |
| EXECUTIVE_MEMBER   | Yes (all banks)   | Yes            | No                           |
| COMMITTEE_DIRECTOR | Yes (all banks)   | Yes            | Yes                          |
| CBY_ADMIN          | Yes (all banks)   | Yes            | Yes                          |

Document access is validated at the backend policy layer (per-stage `stage_permissions` plus bank scoping). Frontend visibility is not sufficient.
Document API payloads do not expose a reusable `download_url`; clients should download with an authenticated request to the download endpoint above.

---

# Audit APIs

Legacy `GET /api/audit` and related `/api/audit/*` routes were removed in WP-14. Use the V1 engine audit trail exclusively.

# Get Audit Logs

## Endpoint

```http
GET /api/v1/audit-logs
```

## Related

```http
GET /api/v1/audit-logs/export
GET /api/v1/audit-logs/{id}
```

---

# Get Request History

## Endpoint

```http
GET /api/v1/engine-requests/{id}/history
```

## Response Includes

Ordered list of stage-to-stage transitions (`from_stage`, `to_stage`, `action_code`, `performed_by`, `comments`, `created_at`) — the per-transition audit trail for this request. There is no `/api/requests/{id}/history` route.

---

# External FX Confirmation APIs

There is no standalone `/api/customs/*` route family. External FX confirmation is nested under the owning request:

# Upload Signed FX Confirmation Document

## Endpoint

```http
POST /api/v1/engine-requests/{id}/fx-confirmation-signed
```

## Permissions

- Committee Director only (enforced in the request's own authorization check, not the general stage-permission check)

## Request Body

`multipart/form-data` with `signed_document` (PDF only, max 10 MB).

---

# Download External FX Confirmation Documents

## Endpoints

```http
GET /api/v1/engine-requests/{id}/customs-declaration/download
GET /api/v1/engine-requests/{id}/customs-declaration/signed-fx-download
```

---

# Users APIs (V1)

Legacy `/api/users` routes were removed in WP-14. All user administration uses the governance V1 namespace.

# Get Users

## Endpoint

```http
GET /api/v1/users
```

---

# Create User

## Endpoint

```http
POST /api/v1/users
```

---

# Update User

## Endpoint

```http
PUT /api/v1/users/{id}
```

---

# Account Recovery (admin)

```http
POST /api/v1/users/{user}/reset-password
POST /api/v1/users/{user}/reset-mfa
POST /api/v1/users/{user}/reset-pin
POST /api/v1/users/{user}/deactivate
```

---

# Banks APIs (V1)

Legacy `/api/banks` routes were removed in WP-14.

# Get Banks

## Endpoint

```http
GET /api/v1/banks
```

---

# Create Bank

## Endpoint

```http
POST /api/v1/banks
```

---

# Update Bank

## Endpoint

```http
PUT /api/v1/banks/{id}
```

## Lifecycle

```http
POST /api/v1/banks/{bank}/activate
POST /api/v1/banks/{bank}/deactivate
DELETE /api/v1/banks/{bank}
```

---

# Dashboard APIs

# Dashboard Statistics

## Endpoint

```http
GET /api/dashboard/stats
```

## Response Includes

- Total requests
- Pending requests
- Approved requests
- Rejected requests
- Workflow counts

Voting-related fields (`waiting_for_voting_open`, `active_voting_sessions`,
`voting_queue`) still appear in the response shape but are hardcoded to
zero/empty — Executive Voting is out of V1 and there is no live voting
data to populate them.

# Dashboard Philosophy

Dashboard APIs must return:

- Role-specific operational queues
- Organization-scoped workflow summaries
- Queue-based operational counts
- Workflow-relevant request metrics

Dashboards must NOT behave like shared analytics systems for operational users.

Data Entry users should primarily receive:

- Drafts
- Returned requests
- Submitted requests
- Rejected requests
- Completed requests

Data Entry users should NOT receive detailed CBY workflow stages.

---

# Settings APIs

Settings are split into three surfaces:

1. **Public branding** — unauthenticated, safe metadata only (`GET /api/settings/public`).
2. **User preferences** — per-user UI preferences (`GET/PUT /api/settings`, etc.).
3. **Operational settings** — scalar DB-backed keys consumed at runtime (`GET/PUT /api/admin/settings/{key}`). Requires `CBY_ADMIN`.

**Mail delivery is env-only.** The following SMTP admin endpoints were removed and must not be reintroduced without a runtime mailer that reads DB settings:

- `GET /api/admin/settings/smtp` (removed)
- `PUT /api/admin/settings/smtp` (removed)
- `POST /api/admin/settings/email/test` (removed)

Configure production SMTP via environment variables — see [`auth-and-recovery.md`](auth-and-recovery.md).

---

# Public Settings (unauthenticated)

## Endpoint

```http
GET /api/settings/public
```

No authentication required. Used by the login shell, layout branding, and frontend cache-busting.

## Safe payload

Exposes **only** `version`, `general`, and `branding`. Never operational, security, SMTP, claim, audit, or secret configuration.

```json
{
  "success": true,
  "message": "Public system settings retrieved.",
  "data": {
    "version": "defaults-v1",
    "general": {
      "platformName": "اللجنة الوطنية لتنظيم وتمويل الواردات",
      "platformNameEn": "The National Committee for Regulating & Financing Imports",
      "authority": "اللجنة الوطنية لتنظيم وتمويل الواردات",
      "authorityEn": "The National Committee for Regulating & Financing Imports",
      "language": "ar",
      "timeZone": "GMT+3"
    },
    "branding": {
      "brandColor": "#0066cc",
      "brandLogoName": "yemen-emblem.svg",
      "brandLogoUrl": "/brand/yemen-emblem.svg",
      "brandingPublished": true,
      "brandingChannels": {
        "securityQuestionnaires": false,
        "emails": true,
        "vendorReports": true
      }
    }
  }
}
```

`version` is the latest `updated_at` timestamp among `settings.general` and `settings.branding` rows, or `defaults-v1` when no rows exist. The frontend should treat it as a cache-bust stamp.

`brandLogoPath` and inline `brandLogoDataUrl` are never returned on this endpoint; only a resolved `brandLogoUrl` is exposed.

---

# User Preferences (authenticated)

## Endpoints

```http
GET /api/settings
PUT /api/settings
POST /api/settings/reset
POST /api/settings/save-section
```

`GET /api/settings` merges stored user preferences with defaults and includes `system` (same safe public payload as `GET /api/settings/public`).

`POST /api/settings/save-section` accepts user sections (`theming`, `notif`) for any authenticated user. System sections (`general`, `workflow`, `security`, and `theming` + `subsection=branding`) require `CBY_ADMIN` and persist to `system_settings` under `settings.{section}` keys — e.g. `settings.general`, `settings.workflow`, `settings.security`, and `settings.branding` (for `theming` + `subsection=branding`).

---

# Admin Operational Settings (`CBY_ADMIN`)

## Endpoints

```http
GET /api/admin/settings
PUT /api/admin/settings/{key}
POST /api/admin/settings/{key}/reset
```

`GET /api/admin/settings` returns all eight live scalar keys and their current values. `PUT` validates type/range per key; changes are audited and invalidate the `SettingResolver` cache for that key.

## Live settings keys and runtime consumers

| Key                        | Default | Validation        | Runtime consumer                                                                       |
| -------------------------- | ------- | ----------------- | -------------------------------------------------------------------------------------- |
| `support_claim_ttl`        | `15`    | 5–60 minutes      | `EngineClaimService` — claim TTL and heartbeat extension                               |
| `pdf_upload_size_limit`    | `10`    | 1–50 MB           | `UploadSizeLimit` — PDF upload `max:` rules on document/SWIFT/FX confirmation requests |
| `login_lockout_attempts`   | `5`     | 1–20 attempts     | `AuthSecuritySettings` → `AuthController` account lockout threshold                    |
| `login_lockout_duration`   | `15`    | 5–60 minutes      | `AuthSecuritySettings` → `AuthController` lockout window                               |
| `mfa_required`             | `false` | boolean           | `AuthSecuritySettings` → login MFA gate, profile MFA restrictions, admin display       |
| `duplicate_invoice_policy` | `warn`  | `warn` \| `block` | `DuplicateInvoiceChecker` — duplicate invoice precheck severity on create/transition   |
| `trusted_device_ttl_hours` | `24`    | 1–720 hours       | `AuthSecuritySettings` → `TrustedDeviceService` remembered-device expiry               |
| `step_up_window_minutes`   | `10`    | 1–120 minutes     | `AuthSecuritySettings` → `StepUpService` step-up verification window                   |

All reads go through `SettingResolver::get()` (DB row first, config/bootstrap default fallback, 1-hour cache). Updates via `AdminSettingsService` call `SettingResolver::forget()` on write.

Branding/general blobs (`settings.general`, `settings.branding`) are **not** part of the admin scalar index; they are managed via `POST /api/settings/save-section` and surfaced publicly through `GET /api/settings/public`.

---

# Report APIs

# Async Report Exports (v1)

## Endpoints

```http
POST   /api/v1/reports/exports
GET    /api/v1/reports/exports
GET    /api/v1/reports/exports/{id}
GET    /api/v1/reports/exports/{id}/download
```

Exports are generated asynchronously. Poll `GET .../exports/{id}` until `status` is `COMPLETED` or `FAILED`.

## Truncation

CSV exports cap at **10,000** rows (`GenerateReportExport::ROW_LIMIT`). When more rows match the filters, the job sets:

| Field             | Meaning                                         |
| ----------------- | ----------------------------------------------- |
| `total_matching`  | Rows matching filters before the cap            |
| `exported_count`  | Rows written to the file (≤ 10,000)             |
| `truncated`       | `true` when `total_matching` > `exported_count` |
| `truncation_note` | User-facing Arabic/English note when truncated  |

The CSV preamble (first line after BOM) repeats the truncation summary. Clients should surface `truncation_note` or an equivalent toast when `truncated` is true.

## Failed Exports

When generation fails, `status` is `FAILED`, `file_path` is cleared, and `GET .../download` returns `EXPORT_FAILED` (422). Clients should show a retry affordance rather than a download link.

---

# Report Presets (V1)

User-scoped saved report filters. Legacy `GET/POST/DELETE /api/report-presets` was removed in WP-14.

```http
GET    /api/v1/report-presets
POST   /api/v1/report-presets
DELETE /api/v1/report-presets/{id}
```

Presets are stored in the authenticated user's `user_preferences.report_presets` JSON and never bypass organization data scope.

---

# Legacy Report Endpoints (removed)

The following pre-engine report routes were removed in WP-14 and return **404**:

```http
GET /api/reports/workflow   (removed)
GET /api/reports/voting     (removed)
```

Use `/api/v1/reports/*` and `/api/v1/reports/exports` instead.

---

# Notification Inbox APIs (v1)

Legacy duplicate `/api/notifications` routes were removed in WP-14.

```http
GET    /api/v1/notifications
GET    /api/v1/notifications/unread-count
POST   /api/v1/notifications/{id}/read
POST   /api/v1/notifications/{id}/unread
POST   /api/v1/notifications/{id}/archive
POST   /api/v1/notifications/read-all
```

Engine notifications deep-link to `/workflows/instances/{id}` via the `action_url` field.

## Mark All Read

`POST /api/v1/notifications/read-all` marks **non-archived** unread inbox rows as read. Archived notifications (`archived_at` set) are left unchanged even if still unread.

---

# Error Response Format

# Validation Error

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "amount": ["The amount field is required"]
  }
}
```

---

# Unauthorized Error

```json
{
  "success": false,
  "message": "Unauthorized action"
}
```

---

# Success Response Format

```json
{
  "success": true,
  "message": "Request approved successfully",
  "data": {}
}
```

---

# API Security Rules

# Authentication Required

All endpoints except login require authentication.

---

# Role-Based Access

Every endpoint validates:

- User role
- Workflow state
- Organization scope
- Permissions
- Visibility scope
- Queue relevance

---

# File Security

Document endpoints must:

- Validate permissions
- Use private storage
- Prevent unauthorized downloads

---

# Workflow Security

Request status/stage may only change through:

- The generic `POST /api/v1/engine-requests/{id}/actions` transition endpoint
- `EngineTransitionService::execute()` (the only code path that advances `current_stage_id`)
- Controlled, permission-checked workflow transitions

Direct status/stage updates are prohibited.

Workflow locking rules guarantee:

- No editing after internal bank approval
- Temporary support review locking
- Immutable support-approved requests
- Immutable executive-rejected requests
- Immutable external FX confirmation documents

---

# Immutable State Enforcement

There is no `PUT`/`DELETE /api/requests/{id}` endpoint. The equivalent protection is enforced on `POST /api/v1/engine-requests/{id}/actions` and `PATCH /api/v1/engine-requests/{id}/draft`: once a request is no longer `ACTIVE`, both return:

```json
{
  "success": false,
  "message": "This request is closed and cannot be modified.",
  "error_code": "REQUEST_CLOSED"
}
```

HTTP Status: `403 Forbidden`

There is no separate `WORKFLOW_LOCKED_STATE` code for non-terminal locked stages — a transition attempt from a stage the caller cannot currently execute (whether because the stage is locked to other roles or the request has moved on) returns `TRANSITION_NOT_AVAILABLE` (422) or `STAGE_EXECUTION_FORBIDDEN` (403) instead.

---

# Transition Concurrency Protection

`EngineTransitionService::execute()` uses database-level pessimistic locking (`lockForUpdate()`) on the `EngineRequest` row for **every** transition through `POST /api/v1/engine-requests/{id}/actions` — this is the same locking already described under Execute a Workflow Action above, not a mechanism specific to any one transition type.

Behavior:

- A transition attempted against a stale `version` is rejected with `REQUEST_STALE` (409) rather than applied.
- All transitions are transactional.

---

# Visibility Security Rules

The backend must guarantee:

- Users never receive requests outside organization scope
- Bank visibility is organization-scoped
- Actions remain role-scoped
- Queue visibility is operationally scoped
- Every role's queue (Support, Executive, and all others) resolves through the same stage-permission-scoped mechanism (`StagePermissionResolver`/`UserActionableRequestQuery`) — none has a bespoke scoping rule

Data Entry users:

- Can view requests within their bank's scope **that they also hold VIEW
  stage-permission on** — bank membership is necessary but not
  sufficient; see "Request Visibility Rules" above for the two-dimension
  model.
- Should receive simplified business statuses
- Should NOT receive detailed CBY workflow stages

Frontend filtering alone is NOT sufficient.

All visibility rules must be enforced at API level.

---

# AuthController — Remaining Routes

**Verified:** 2026-07-13, against
`backend/app/Http/Controllers/Api/AuthController.php` directly. Beyond
the already-documented login/logout/me, these routes exist:

```http
POST /api/auth/login-pin
POST /api/auth/verify-otp
POST /api/auth/password/forgot
POST /api/auth/password/verify
POST /api/auth/password/reset
GET  /api/auth/demo-users
POST /api/auth/switch-demo-user
POST /api/auth/switch-demo-role
```

## PIN Login

```http
POST /api/auth/login-pin
```

Body: `email` (required), `pin` (required, exactly 6 digits). Subject
to the same account-lockout mechanism as password login
(`login_pin_fail` throttle key, independent counter from
`login_lockout_attempts`/`login_lockout_duration` used by password
login). Returns 403 if the account is inactive; a validation error
(not a generic auth failure) if the account has no PIN configured
(`pin_enabled` false or no `pin_code_hash`) — the Arabic message
explicitly tells the user to set a PIN from their profile first.

## MFA OTP Verification (Login Step 2)

```http
POST /api/auth/verify-otp
```

Body: `email` (required), `otp` (required, 6–16 chars), `challenge_id`
(required UUID), `trust_device` (optional bool). Completes a login that
was gated on MFA — verifies via `MfaService::verify()`, then issues the
session. If `trust_device` is true, sets a trusted-device cookie so
future logins from the same browser can skip the OTP step.

## Password Recovery

```http
POST /api/auth/password/forgot
POST /api/auth/password/verify
POST /api/auth/password/reset
```

**`forgot`**: `email` (required). Always returns the same generic
success message regardless of whether the email exists
(`PasswordRecoveryService::genericMessage()`) — does not leak account
existence.

**`verify`**: `email` (required), `otp` (required, exactly 6 digits).
Verifies the recovery OTP without resetting the password yet.

**`reset`**: `email`, `otp` (exactly 6 digits), `password` (required,
`PasswordPolicy::rules()` + `confirmed`). Re-validates the new password
against `PasswordPolicy::validate()` for the specific user (e.g.
password-reuse or user-attribute-based checks) in addition to the
generic policy rules, before calling
`PasswordRecoveryService::reset()`.

## Demo Session Switching

Registered only when `config('demo.allowed_environments')` includes
the current environment — absent entirely from `route:list` output in
environments where it isn't (e.g. typically disabled in
staging/production).

```http
GET  /api/auth/demo-users
POST /api/auth/switch-demo-user
POST /api/auth/switch-demo-role
```

**`demo-users`**: lists active demo accounts available for quick
switching.

**`switch-demo-user`**: body `user_id` (required int) — switches the
current session directly to that user.

**`switch-demo-role`**: body `role` (required, must be a valid
`UserRole` enum value) — switches to the first active user holding
that role (`orderBy('id')->first()`), 404 if none exists. Both switch
endpoints audit `AuditAction::DEMO_USER_SWITCH` with the target user id
and switch type, and both are separately throttled
(`throttle:20,1`) from the general auth rate limits.

---

# Small Gaps on Already-Documented Families

## Available Workflows

```http
GET /api/v1/engine-requests/available-workflows
```

`EngineRequestController::availableWorkflows()`. Returns `PUBLISHED`
workflow versions whose initial stage the requesting user can `EXECUTE`
(via `StagePermissionResolver`) — the set of workflows the user is
actually allowed to start a new request under. A user who cannot create
requests (`RequestCreationGate::userCanCreateRequests()` returns
`false`) gets `{"data": []}` rather than a 403.

```json
{
  "data": [
    {
      "id": 1,
      "code": "IMPORT_FINANCING",
      "name": "Import Financing",
      "version_id": 39,
      "version_number": 2
    }
  ]
}
```

## Abandon Draft

```http
POST /api/v1/engine-requests/{engineRequest}/abandon
```

Authorized via the `abandon` Policy ability on `EngineRequest`.

**Request body:**

```json
{ "version": 3 }
```

`version` is required (optimistic lock). Delegates to
`EngineTransitionService::abandonDraft()`.

```json
{
  "success": true,
  "message": "Draft abandoned successfully.",
  "data": { "...": "EngineRequestResource" }
}
```

## Audit Log Export — Full Lifecycle

The existing "Related" block under Audit Logs documents only
`GET /api/v1/audit-logs/export` (list) and `GET /api/v1/audit-logs/{id}`
(show a log entry). The export **creation, status, and download** steps
were not yet documented:

```http
POST /api/v1/audit-logs/export
GET  /api/v1/audit-logs/export/{reportExport}
GET  /api/v1/audit-logs/export/{reportExport}/download
```

All three authorize via `viewAny` on `AuditLog`, plus
`guardAuditExportOwnership()` on show/download (a requester may only
view/download their own export, or a `SYSTEM_ADMIN` may access any).

`POST .../export` accepts filters via `$request->only(['user', 'role',
'event', 'entity', 'request', 'from', 'to', 'ip', 'correlation_id'])`
and creates a `ReportExport` row (`report_type: 'audit-logs'`, `format:
'csv'`, `status: 'PENDING'`) processed asynchronously — same
`ReportExport` model as the `reports/exports` family documented above.

`GET .../download` returns:

| Condition                               | Response                                          |
| --------------------------------------- | ------------------------------------------------- |
| `status: FAILED`                        | 422, `{"error":{"code":"EXPORT_FAILED", ...}}`    |
| `status` not `COMPLETED` or no file yet | 422, `{"error":{"code":"EXPORT_NOT_READY", ...}}` |
| Otherwise                               | the file stream                                   |

---

# Workflow Designer Admin API

**Verified:** 2026-07-13, against
`backend/app/Http/Controllers/Api/V1/{WorkflowDefinition,WorkflowVersion,WorkflowAction,WorkflowStage,WorkflowTransition,StagePermission,StageFieldRule,FieldGroup,FieldDefinition}Controller.php`
and their Form Request classes directly.

Every Store/Update endpoint below is Policy-gated
(`$this->authorize('create'|'update'|'delete'|'view'|'viewAny', ...)`)
— consult the relevant `App\Policies\*Policy` class for the exact grant
logic; this section documents routes, validation, and response
behavior, not the policy internals themselves.

**Optimistic locking is universal across this family.** Every mutating
endpoint below except `store` requires an integer `version` field
matching the row's current `version` column; a mismatch returns
`STALE_RESOURCE` (409). Every endpoint that mutates a stage, transition,
field, field group, or stage permission additionally requires the
parent `WorkflowVersion` to be in `DRAFT` state — attempting a mutation
against a `PUBLISHED`/`ARCHIVED` version returns
`WorkflowVersionImmutableException`'s error code (409). Structural
delete conflicts (e.g. deleting a workflow definition that has
published versions) return a `WorkflowDesignProtectionException`
error code (422) instead.

## Workflow Definitions

```http
GET    /api/v1/workflow-definitions
POST   /api/v1/workflow-definitions
GET    /api/v1/workflow-definitions/{workflowDefinition}
PUT    /api/v1/workflow-definitions/{workflowDefinition}
DELETE /api/v1/workflow-definitions/{workflowDefinition}
```

`index` accepts `search`, `sort` (`code`\|`name`\|`is_active`\|`created_at`),
`direction` (`asc`\|`desc`), `per_page` (max 100) — paginated, each
item includes `versions` with `stages`/`transitions`/`fields` counts.

**Store** (`StoreWorkflowDefinitionRequest`): `code` (required, `alpha_dash`,
max 100, unique), `name` (required, max 255), `description` (optional,
max 1000).

**Update** (`UpdateWorkflowDefinitionRequest`): `code` is **prohibited**
(immutable after creation — attempting to change it adds a validation
error even though the field itself isn't required); `name` (sometimes
required), `description` (optional), `version` (required).

`DELETE` returns 204 on success, or 422 with the
`WorkflowDesignProtectionException` code if the definition has
versions that block deletion.

## Workflow Versions

```http
GET    /api/v1/workflow-versions/{workflowVersion}
PUT    /api/v1/workflow-versions/{workflowVersion}
DELETE /api/v1/workflow-versions/{workflowVersion}
POST   /api/v1/workflow-versions/{workflowVersion}/clone
POST   /api/v1/workflow-versions/{workflowVersion}/publish
POST   /api/v1/workflow-versions/{workflowVersion}/validate
POST   /api/v1/workflow-versions/{workflowVersion}/archive
GET    /api/v1/workflow-versions/{workflowVersion}/graph
```

There is no `POST /api/v1/workflow-versions` (index/create) route —
new versions are created only via `clone`.

**Update** (`UpdateWorkflowVersionRequest`): only `version` (required,
optimistic lock) — the version resource itself has no other directly
editable scalar field today; stages/actions/transitions/fields carry
the actual editable payload.

**`clone`**: creates a new `DRAFT` version from the source (any state),
audited as `AuditAction::WORKFLOW_CLONED`. Returns 201.

**`publish`**: requires `version` (int, min 1). On success, audits
`AuditAction::WORKFLOW_PUBLISHED`, notifies all active `SYSTEM_ADMIN`
users, and clears all screen-permission caches
(`PermissionService::clearAllScreenPermissionCaches()`). Failure modes:

| Condition                          | Response                                                              |
| ---------------------------------- | --------------------------------------------------------------------- |
| Stale `version`                    | 409 `STALE_RESOURCE`                                                  |
| Version not editable/already final | 409, `WorkflowVersionImmutableException` code                         |
| Validation errors present          | 422, `{"error":{"code":"WORKFLOW_VALIDATION_FAILED","errors":[...]}}` |

**`validate`**: read-only — runs the same validation the publish path
enforces without side effects. Returns `{"data":{"errors":[...],
"warnings":[...]}}`; an empty `errors` array means the version is
publishable.

**`archive`**: requires `version`; optional `reason` (max 500).
Clears screen-permission caches. If archiving removes the definition's
**last** `PUBLISHED` version, the response includes a
`meta.warnings[].code: "LAST_PUBLISHED_ARCHIVED"` entry warning that
new request creation stops for that definition until another version
publishes.

**`graph`**: read-only process graph derived from the version's stages
and transitions (`WorkflowGraphService::build()`) — response shape is
`{"data": {...}}` with the service's node/edge structure; this
documentation does not restate that service's internal schema, since
verifying its exact shape was out of this pass's scope — treat the
service source as authoritative for the graph's node/edge fields.

## Workflow Stages

```http
GET    /api/v1/workflow-versions/{workflowVersion}/stages
POST   /api/v1/workflow-versions/{workflowVersion}/stages
GET    /api/v1/workflow-versions/{workflowVersion}/stages/{workflowStage}
PUT    /api/v1/workflow-versions/{workflowVersion}/stages/{workflowStage}
DELETE /api/v1/workflow-versions/{workflowVersion}/stages/{workflowStage}
GET    /api/v1/workflow-stages/{workflowStage}/effective-executors
```

**Store** (`StoreWorkflowStageRequest`): `code` (required, `alpha_dash`,
unique within the version), `name` (required), `description` (optional),
`sort_order` (optional int), `is_initial`/`is_final`/`requires_claim`
(optional bool), `final_outcome` (required and validated as a
`FinalOutcome` enum value **only if** `is_final` is true; **prohibited**
otherwise), `sla_duration_minutes` (optional int, min 1), `status`
(`ACTIVE`\|`INACTIVE`).

**Update** (`UpdateWorkflowStageRequest`): same fields as Store, all
`sometimes`, plus `version`. `final_outcome`'s required/prohibited
rule is re-evaluated against the submitted (or existing, if omitted)
`is_final` value.

**`effective-executors`**: read-only. Returns, per stage, the total
count of users who could `EXECUTE` at that stage
(`StagePermissionAudience::executeHolderIds()`) and a breakdown by
`stage_permissions` row (`access_level`, `matched_users` count per
row):

```json
{
  "data": {
    "total_executors": 4,
    "permissions": [{ "id": 12, "access_level": "EXECUTE", "matched_users": 2 }]
  }
}
```

## Workflow Actions

```http
GET    /api/v1/workflow-actions
POST   /api/v1/workflow-actions
GET    /api/v1/workflow-actions/{workflowAction}
PUT    /api/v1/workflow-actions/{workflowAction}
DELETE /api/v1/workflow-actions/{workflowAction}
POST   /api/v1/workflow-actions/{workflowAction}/activate
POST   /api/v1/workflow-actions/{workflowAction}/deactivate
```

Actions are not scoped to a single `WorkflowVersion` — they are a
shared catalog referenced by transitions across versions.

**Store** (`StoreWorkflowActionRequest`): `code` (required, `alpha_dash`,
unique), `name` (required), `kind` (required, `WorkflowActionKind` enum).

**Update** (`UpdateWorkflowActionRequest`): `code` (`sometimes`, but see
below), `name` (required), `kind` (`sometimes`), `version` (required).
Changing `code` on update is logged as an
`AuditAction::AUTHORIZATION_FAILURE` audit entry (the Form Request's
`after()` hook flags a code-change attempt specifically — the intent
being to make definition-identity changes visible in the audit trail
even though `Rule::` does not outright reject it the way stage/definition
code changes do).

`index`/`show` each attach a computed, non-persisted `is_in_use` flag
(`true` if any `workflow_transitions` row references the action).

`activate`/`deactivate` each require `version` and return the updated
resource.

## Workflow Transitions

```http
GET    /api/v1/workflow-versions/{workflowVersion}/transitions
POST   /api/v1/workflow-versions/{workflowVersion}/transitions
GET    /api/v1/workflow-versions/{workflowVersion}/transitions/{workflowTransition}
PUT    /api/v1/workflow-versions/{workflowVersion}/transitions/{workflowTransition}
DELETE /api/v1/workflow-versions/{workflowVersion}/transitions/{workflowTransition}
```

**Store** (`StoreWorkflowTransitionRequest`): `from_stage_id` (required,
must belong to the version, unique per `action_id` — a stage cannot
have two transitions for the same action), `to_stage_id` (required,
must belong to the version), `action_id` (required, must reference an
active `WorkflowAction`), `requires_comment`/`is_default_submit`/
`is_self_loop`/`is_destructive` (optional bool), `confirmation_message`
(optional, max 500), `transition_type` (optional, `WorkflowTransitionType`
enum).

**Update**: same optional fields (no `from_stage_id`/`action_id` —
those are immutable once created), plus `version`. A duplicate
`(from_stage_id, action_id)` pair on either store or update throws a
`ValidationException` on the `action_id` field, not a raw DB
constraint error.

## Stage Permissions

```http
GET    /api/v1/workflow-stages/{workflowStage}/permissions
POST   /api/v1/workflow-stages/{workflowStage}/permissions
GET    /api/v1/workflow-stages/{workflowStage}/permissions/{stagePermission}
PUT    /api/v1/workflow-stages/{workflowStage}/permissions/{stagePermission}
DELETE /api/v1/workflow-stages/{workflowStage}/permissions/{stagePermission}
```

**Store** (`StoreStagePermissionRequest`): `organization_id` (required),
`team_id`/`role_id` (nullable, optional scoping refinements), `user_id`
(**prohibited** — see `03-permission-model.md`; individual-user grants
are not supported through this endpoint), `access_level` (required,
`StageAccessLevel` enum), `display_label` (required, max 255). Runs
`StagePermissionConsistency::check()` in an `after()` hook — validates
that any set `team_id`/`role_id` actually belongs to the submitted
`organization_id` (data-integrity check, not a role-exclusion rule —
see `03-permission-model.md`).

**Update** (`UpdateStagePermissionRequest`): same fields, all
`sometimes` except `user_id` (still prohibited), plus `version`.

Store/update/destroy all call
`PermissionService::clearAllScreenPermissionCaches()` after the
mutation, since stage-permission changes can affect derived capability
resolution.

## Stage Field Rules

```http
GET    /api/v1/workflow-stages/{workflowStage}/field-rules
POST   /api/v1/workflow-stages/{workflowStage}/field-rules
DELETE /api/v1/workflow-stages/{workflowStage}/field-rules/{stageFieldRule}
```

No `show`/`update` route — a field rule is set (created or replaced)
via `store` only. **Store** (`SetStageFieldRuleRequest`): `field_id`
(required, must belong to the same workflow version as the stage),
`is_visible`/`is_editable`/`is_required` (optional bool, default
presumably false/unset when omitted — this document does not assert a
specific default without reading `FieldDesignerService::setStageFieldRule()`
directly; treat omitted booleans as service-determined, not
documented-here-as-guaranteed-false).

## Field Groups

```http
GET    /api/v1/workflow-versions/{workflowVersion}/field-groups
POST   /api/v1/workflow-versions/{workflowVersion}/field-groups
PUT    /api/v1/workflow-versions/{workflowVersion}/field-groups/{fieldGroup}
DELETE /api/v1/workflow-versions/{workflowVersion}/field-groups/{fieldGroup}
```

**Store** (`StoreFieldGroupRequest`): `name` (required, `alpha_dash`,
max 100), `label` (required, max 255), `sort_order` (optional int).

**Update** (`UpdateFieldGroupRequest`): `label`, `sort_order`
(`sometimes`), `version` (required) — `name` is not updatable.

`index` eager-loads each group's `fields`, ordered by `sort_order`.

## Field Definitions

```http
GET    /api/v1/workflow-versions/{workflowVersion}/fields
POST   /api/v1/workflow-versions/{workflowVersion}/fields
PUT    /api/v1/workflow-versions/{workflowVersion}/fields/{fieldDefinition}
DELETE /api/v1/workflow-versions/{workflowVersion}/fields/{fieldDefinition}
GET    /api/v1/workflow-versions/{workflowVersion}/fields/{fieldDefinition}/options
```

**Store** (`StoreFieldDefinitionRequest`): `field_group_id` (required,
must belong to the version), `key` (required, `alpha_dash`, unique
within the version), `semantic_tag` (optional, `FieldSemanticTag`
enum), `label` (required), `type` (required, `FieldType` enum),
`placeholder`/`help_text`/`default_value`/`regex_pattern` (optional
strings), `min_value`/`max_value` (optional numeric),
`min_length`/`max_length` (optional int), plus type-specific fields
(`options`, `allowed_file_types`, `max_file_size`, `multiple`,
`is_required`, `sort_order`) — see the Form Request class for the full
list; `key` and `type` are immutable after creation (both prohibited
on update rather than merely `sometimes`).

**`options`**: read-only. Resolves the selectable options for a
`DYNAMIC_SELECT`-type field (`DynamicFieldOptionsResolver::resolve()`),
scoped to the requesting user.

---

# Org-Structure Admin API

**Verified:** 2026-07-13, against
`backend/app/Http/Controllers/Api/V1/{Organization,Team,Role,Screen,RoleScreenPermission}Controller.php`.

Organizations, Teams, and Roles share one consistent shape: Policy-gated
CRUD + `activate`/`deactivate`, optimistic locking (`version`) on
mutations, and a shared governance-deletion/deactivation guard
(`assertCanDeactivateGovernanceEntity()` / `assertCanDeleteGovernanceEntity()`)
that checks whether the entity is referenced by a **published** workflow
version before allowing the change — see `GovernanceImpactController`
below for the read-only version of that same check.

## Organizations

```http
GET    /api/v1/organizations
POST   /api/v1/organizations
GET    /api/v1/organizations/{organization}
PUT    /api/v1/organizations/{organization}
DELETE /api/v1/organizations/{organization}
POST   /api/v1/organizations/{organization}/activate
POST   /api/v1/organizations/{organization}/deactivate
```

**Store** (`StoreOrganizationRequest`): `code` (required, `alpha_dash`,
unique), `name` (required), `classification` (required,
`OrganizationClassification` enum — this is the field `DataScope`
resolves system-wide vs. own-bank vs. deny-by-default access from, see
`03-permission-model.md`).

**Update**: `code` prohibited if changed, `name` required, `classification`
optional, `version` required.

**`deactivate`** is blocked (422, `ORGANIZATION_IN_USE`) if the
organization has any active teams, roles, users, or banks.
**`destroy`** is blocked (422, `ORGANIZATION_PROTECTED`) if the
organization `isProtected()` or has any teams/roles/users/banks at
all (active or not). Both additionally run the governance
published-workflow-reference guard before their own checks.

## Teams

```http
GET    /api/v1/teams
POST   /api/v1/teams
GET    /api/v1/teams/{team}
PUT    /api/v1/teams/{team}
DELETE /api/v1/teams/{team}
POST   /api/v1/teams/{team}/activate
POST   /api/v1/teams/{team}/deactivate
```

**Store** (`StoreTeamRequest`): `organization_id` (required),
`code` (required, `alpha_dash`, unique within the organization), `name`
(required), `role_code` (**prohibited** — teams do not carry a role
code directly through this endpoint).

**Update**: `organization_id`/`code` prohibited if changed from the
existing value, `name` required, `version` required.

## Roles

```http
GET    /api/v1/roles
POST   /api/v1/roles
GET    /api/v1/roles/{role}
PUT    /api/v1/roles/{role}
DELETE /api/v1/roles/{role}
POST   /api/v1/roles/{role}/activate
POST   /api/v1/roles/{role}/deactivate
GET    /api/v1/roles/{role}/screen-permissions
PUT    /api/v1/roles/{role}/screen-permissions
GET    /api/v1/screen-permissions/matrix
```

**Store** (`StoreRoleRequest`): `organization_id` (required), `code`
(required, `alpha_dash`, unique within the organization), `name`
(required).

**Update**: `organization_id`/`code` prohibited if changed, `name`
required, `version` required.

### Screen permissions per role

`show`/`update` gate on the `screen_permissions` screen capability
(`VIEW`/`MANAGE` respectively via `PermissionService`), not a Policy
class. `matrix` returns the full role × screen × capability grid in
one call.

**`update`** — `grants` (required object, keyed by screen key, each
value an array of `ScreenCapability` values). Server-side validation
rejects three categories of screen key even though the request shape
otherwise allows them: `requests` (access is derived from Designer
stage assignments, not manually granted), `UNIVERSAL_SCREENS`
(always-on), and `ADMIN_ONLY_SCREENS` (system-admin-only, not
delegable) — rejecting these here (not just hiding them in the UI)
closes a self-escalation path. The full replace runs inside a
transaction with a lock guarding against removing the last
`system_admin`-capable role concurrently (`guardLastAdmin()`).

## Screens

```http
GET /api/v1/screens
```

Read-only catalog (`id`, `key`, `label`), gated on `screen_permissions`
`VIEW` capability. No create/update/delete route — screens are
seeder-defined (`ScreenPermissionSeeder`), not runtime-creatable.

---

# Reference Data Admin API

**Verified:** 2026-07-13, against
`backend/app/Http/Controllers/Api/V1/{ReferenceTable,ReferenceValue}Controller.php`.

Same Policy-gated CRUD + activate/deactivate + optimistic-locking shape
as the org-structure family above.

## Reference Tables

```http
GET    /api/v1/reference-tables
POST   /api/v1/reference-tables
GET    /api/v1/reference-tables/{reference_table}
PUT    /api/v1/reference-tables/{reference_table}
DELETE /api/v1/reference-tables/{reference_table}
POST   /api/v1/reference-tables/{reference_table}/activate
POST   /api/v1/reference-tables/{reference_table}/deactivate
```

## Reference Values

```http
GET    /api/v1/reference-values
POST   /api/v1/reference-values
GET    /api/v1/reference-values/{reference_value}
PUT    /api/v1/reference-values/{reference_value}
DELETE /api/v1/reference-values/{reference_value}
POST   /api/v1/reference-values/{reference_value}/activate
POST   /api/v1/reference-values/{reference_value}/deactivate
```

Both resources route through `ReferenceDataService` for their
mutations.

**Reference Table Store** (`StoreReferenceTableRequest`): `key`
(required, `alpha_dash`, max 100, unique), `label` (required, max 255),
`sort_order` (optional int). **Update**: `key` prohibited if changed,
`label` required, `sort_order` optional, `version` required.

**Reference Value Store** (`StoreReferenceValueRequest`):
`reference_table_id` (required, must exist), `key` (required,
`alpha_dash`, unique **within the parent table**, not globally),
`label` (required), `sort_order` (optional). **Update**:
`reference_table_id`/`key` both prohibited if changed, `label`
required, `version` required — a value cannot be moved to a different
table via update.

---

# Merchants API

**Verified:** 2026-07-13, against
`backend/app/Http/Controllers/Api/V1/MerchantController.php` and its
Form Requests.

```http
GET    /api/v1/merchants
POST   /api/v1/merchants
GET    /api/v1/merchants/{merchant}
PUT|PATCH /api/v1/merchants/{merchant}
DELETE /api/v1/merchants/{merchant}
```

Policy-gated (`viewAny`/`view`/`create`/`update`/`delete` on
`Merchant`).

**Store** (`StoreMerchantRequest`): `bank_id` (nullable, must exist),
`name` (required), `tax_number` (required), `tax_card_expiry`
(nullable date), `address`/`phone` (nullable strings), `status`
(`ACTIVE`\|`SUSPENDED`, default presumably `ACTIVE`), `owners` (array,
each with required `name` + `ownership_percentage` 0–100), `companies`
(array, each with required `name` + `commercial_registration_number`).

**Update** (`UpdateMerchantRequest`): same shape, all `sometimes`
except `version` (required, optimistic lock).

---

# Governance & Compliance API

**Verified:** 2026-07-13, against
`backend/app/Http/Controllers/Api/V1/{GovernanceImpactController,ComplianceController}.php`.

## Governance Impact

```http
GET /api/v1/governance/impact
GET /api/v1/banks/{bank}/lifecycle-impact
```

**`governance/impact`** — query params: `entity_type` (required,
`GovernanceReferenceEntityType` enum: organization/team/role/
reference-table/reference-value/user), `entity_id` (required),
`action` (optional, `delete`\|`deactivate`). Authorizes `view` on the
resolved entity, then delegates to
`PublishedWorkflowReferenceGuard::impact()` — the same guard the
org-structure/reference-data deactivate/delete endpoints call
internally, exposed here read-only so a UI can preview the impact
before attempting the mutation. Reference-table entities always get
`bank_context: null`; an organization `deactivate` impact check adds
`draft_only_warning`.

**`banks/{bank}/lifecycle-impact`** — a separate, non-workflow-
permission-based usage report for a specific bank (Policy `view` on
`Bank`): counts of users, merchants (including soft-deleted), total/
in-flight/closed engine requests, plus `can_suspend` (always `true`)
and `can_delete` (`false` if the bank has any users, merchants, or
engine requests at all).

```json
{
  "data": {
    "entity_type": "bank",
    "entity_id": 3,
    "usage": {
      "users": 4,
      "merchants": 12,
      "engine_requests_total": 56,
      "engine_requests_in_flight": 8,
      "engine_requests_closed": 48
    },
    "warnings": [
      "Bank has 8 in-flight request(s); suspension is allowed but new activity will be blocked."
    ],
    "can_suspend": true,
    "can_delete": false
  }
}
```

## Compliance

```http
GET /api/v1/compliance/duplicate-invoices
GET /api/v1/compliance/expired-documents
GET /api/v1/compliance/sla-breaches
```

All three gate on the `audit` screen `VIEW` capability
(`PermissionService::userHasCapability()`, not a Policy), apply
`DataScope::applyTo()`, and accept `bank_id`/`per_page` (max 100) query
params where applicable — paginated responses with the standard
`current_page`/`last_page`/`per_page`/`total` meta shape.

- **`duplicate-invoices`** — groups `engine_requests` by
  `invoice_number` having `COUNT(*) > 1`, returns each group's request
  list (id, reference, bank, merchant, amount, currency, status, stage,
  created_at).
- **`expired-documents`** — merchants whose `tax_card_expiry` is in the
  past; each row's `expired_documents` array currently contains only
  the tax-card entry (`type: "tax_card"`).
- **`sla-breaches`** — active requests whose current stage has an SLA
  and whose deadline (`EngineRequest::slaDeadlineEpochSql()`) has
  passed, ordered soonest-breached first, including `sla_status` and
  `stage_entered_at`.

---

# Analytics Reports API

**Verified:** 2026-07-13, against
`backend/app/Http/Controllers/Api/V1/ReportController.php`. Distinct
from the async `reports/exports` family documented above — these
endpoints return computed aggregates synchronously.

```http
GET /api/v1/reports/summary
GET /api/v1/reports/requests-over-time
GET /api/v1/reports/by-workflow-stage
GET /api/v1/reports/by-bank
GET /api/v1/reports/by-merchant
GET /api/v1/reports/by-sector
GET /api/v1/reports/by-currency
GET /api/v1/reports/stage-duration
GET /api/v1/reports/sla
GET /api/v1/reports/team-performance
```

All ten gate on the `reports` screen `VIEW` capability and apply
`DataScope::applyTo(..., 'engine_requests.bank_id')`. Every endpoint's
result is cached per (`endpoint`, resolved `DataScope`, raw query
string) via an aggregate-result cache
(`$this->aggregateCache->remember(...)`) — repeat calls with identical
scope+params do not re-scan the database.

**Filtering:** endpoints accept `from`/`to` date-range query params.
`stage-duration`, `sla`, and `team-performance` additionally default to
a **90-day window** when no explicit range is given and `?all=true` is
not passed — pass `?all=true` for an explicit unbounded pull (e.g. a
compliance review). `summary` and the `by-*` dashboard-widget endpoints
remain all-time-by-default; they are not subject to the 90-day default.

`summary` returns one grouped query's worth of per-`runtime_status`
counts plus a total amount sum — not seven separate full-table scans
(a documented performance fix, not incidental).

---

# Profile, MFA, and Session Management API

**Verified:** 2026-07-13, against
`backend/app/Http/Controllers/Api/ProfileController.php`. All routes
under `api/profile/*` operate on the authenticated user only (no
`{user}` route parameter) — there is no admin-on-behalf-of-user profile
endpoint in this family; admin-initiated resets go through
`POST /api/v1/users/{user}/reset-*` instead (already documented above).

```http
GET    /api/profile
PUT    /api/profile
PUT    /api/profile/avatar
POST   /api/profile/change-password
POST   /api/profile/change-temporary-password
GET    /api/profile/sessions
POST   /api/profile/sessions/revoke-all
DELETE /api/profile/sessions/{tokenId}
POST   /api/profile/mfa/toggle
POST   /api/profile/mfa/setup
POST   /api/profile/mfa/setup/verify
POST   /api/profile/mfa/disable
POST   /api/profile/mfa/recovery-codes/regenerate
POST   /api/profile/mfa/step-up/initiate
POST   /api/profile/mfa/step-up/verify
POST   /api/profile/pin
DELETE /api/profile/pin
```

## PIN login setup (`POST`/`DELETE /api/profile/pin`)

Both require a fresh step-up verification first
(`ensureStepUp($request)` — returns a 403-equivalent response if the
user hasn't completed a recent step-up challenge; see `mfa/step-up/*`
below).

**Set/change PIN** (`POST`): `new_pin` (required, exactly 6 digits),
`current_pin` (required only if the user already has a PIN enabled —
verified via `Hash::check` against `pin_code_hash`; wrong current PIN
returns 422 and logs `AuditAction::AUTHORIZATION_FAILURE`). Audits
`PIN_SET` on first set, `PIN_CHANGED` on change.

**Disable PIN** (`DELETE`): `current_pin` required, same verification.
Audits `PIN_DISABLED`.

## MFA (`mfa/*`)

**`toggle`**: flips `mfa_enabled`. Blocked (403) if
`AuthSecuritySettings::mfaRequired()` — MFA cannot be disabled when
system-enforced.

**`setup`**/**`setup/verify`**: TOTP enrollment — `setup` issues a new
TOTP secret/QR payload; `setup/verify` requires `code` (exactly 6
chars) to confirm enrollment before it takes effect.

**`disable`**: requires `code` (exactly 6 chars, current TOTP code) to
turn TOTP off.

**`recovery-codes/regenerate`**: issues a fresh set of MFA recovery
codes, invalidating the previous set.

**`step-up/initiate`**: sends a fresh email-based step-up challenge
(`StepUpService::initiateEmailChallenge()`) — this is the mechanism
`setPin`/`disablePin` require before allowing the PIN mutation.

**`step-up/verify`**: `code` (required, 6–16 chars,
alphanumeric-with-hyphen), `challenge_id` (optional UUID). Wrong/expired
code returns 422 with an Arabic message
(`"رمز التحقق غير صحيح أو منتهي الصلاحية."`).

## Sessions

`GET /api/profile/sessions` lists active Sanctum tokens for the user.
`POST .../revoke-all` revokes every token except (implementation-
dependent — verify `revokeAllSessions()` directly if "except current"
behavior matters for a specific integration) the mechanism used;
`DELETE .../{tokenId}` revokes one specific token by ID.

---

# Search API

**Verified:** 2026-07-13, against
`backend/app/Http/Controllers/Api/SearchController.php`.

```http
GET /api/search
GET /api/search/recent
```

**`search`** — query param `q`. Below `MIN_QUERY_LENGTH` (2 characters)
returns all-empty result groups rather than an error. Searches four
groups in parallel, each independently role-gated and `DataScope`-scoped:

| Group      | Gate                                                                      | Notes                                                                                                                                    |
| ---------- | ------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `requests` | Any authenticated user                                                    | Via `EngineRequestReadModel::queryFor($user)` — same scoping as the main request list                                                    |
| `users`    | `SYSTEM_ADMIN` or `BANK_ADMIN` only                                       | `BANK_ADMIN` further restricted to `RoleCodes::BANK_ADMIN_MANAGED` role codes                                                            |
| `banks`    | `SYSTEM_ADMIN` only                                                       | —                                                                                                                                        |
| `customs`  | Any authenticated user, `DataScope`-scoped via the linked `EngineRequest` | Response keys `request_id`/`reference_number` are a fixed public API contract (frontend `GlobalSearch.vue` depends on these exact names) |

Each group is capped at 10 results (`MAX_RESULTS_PER_GROUP`). A
successful search persists the query into the user's
`recent_searches` preference (deduped, most-recent-first, capped at 10) via `saveQuietly()` — failures here are swallowed (fire-and-forget)
so a preferences-write error never fails the search response itself.

**`recent`** — returns the caller's own `recent_searches` list from
`user_preferences`. No query params.

---

# Additional Discovered Endpoints (Outside the Original Gap List)

These routes exist in `backend/routes/api.php` but were not named in
the Coverage status section's original gap enumeration — found during
this pass's `route:list` re-run and documented for completeness.

## System Health

```http
GET /api/admin/health
```

`AdminHealthController::index()`. Gated via `Gate::authorize('cbyAdmin', ...)`.
Returns scheduler staleness per retention command
(`config('retention.scheduler_stale_minutes')`), the 10 most recent
`failed_jobs` rows, `failed_jobs` total count, last-run timestamps for
5 retention commands, and the active mail driver:

```json
{
  "data": {
    "scheduler": [
      {
        "command": "...",
        "last_ran_at": "...",
        "status": "...",
        "stale": false
      }
    ],
    "queue": { "failed_jobs_count": 0, "recent_failures": [] },
    "retention": {
      "last_runs": {
        "notifications:purge-old": "...",
        "reports:purge-old-exports": "...",
        "documents:purge-orphans": "...",
        "documents:archive-superseded": "...",
        "audit:archive-old": "..."
      }
    },
    "mail": { "driver": "smtp" }
  }
}
```

This is the endpoint referenced by `docs/production-guide.md`'s Quick
health check — confirmed unversioned (`/api/admin/health`, not
`/api/v1/...`).

## Notification Templates (Admin)

```http
GET  /api/admin/notification-templates
GET  /api/admin/notification-templates/{type}
PUT  /api/admin/notification-templates/{type}
POST /api/admin/notification-templates/{type}/preview
```

All four gate on `Gate::authorize('cbyAdmin', ...)`. `{type}` is a
`NotificationType` enum value that must also be flagged
`admin_editable` in the `NotificationRegistry` catalog — a non-editable
or unknown type 404s on every method.

`update`: `subject` (required, max 255), `body` (required, max 65535)
— sanitized via `TemplateValidator::validateForSave()`. Writes a new
`NotificationTemplate` version and an `AuditAction::EMAIL_TEMPLATE_UPDATED`
audit entry inside one transaction (atomic: a failed audit write must
not leave an un-audited template change committed).

`preview`: same request body shape as `update`, but does not persist —
renders the submitted subject/body against a fixed set of sample
template variables (`reference_number`, `bank_name`, `importer_name`,
`amount`, `currency`, `status`, `action_url`, `user_name`) and returns
both the raw source and the rendered output.

## Financing Utilization

```http
GET /api/financing/utilization
```

`FinancingController::utilization()`. Gated on `requests` `CREATE` or
`audit` `VIEW` capability. Query params: `tax_number` (required),
`invoice_number` (required), `exclude_request_id` (optional, excludes
one request from the calculation — used when re-checking utilization
while editing an existing draft).

**Cross-bank probe denial (S-7):** for a non-system-wide `DataScope`,
if the resolved merchant (by `tax_number`) belongs to a different bank
than the caller's own, the endpoint returns 403 ("Cross-bank probe
denied") rather than silently returning a zero/empty utilization figure
— this specifically prevents a bank user from learning whether a given
tax number belongs to another bank's merchant by observing response
differences.

```json
{
  "data": {
    "used_percent": 42.5,
    "remaining_percent": 57.5,
    "blocked": false
  }
}
```

## Dashboard Work

```http
GET /api/dashboard/work
```

Already fully documented in
[`architecture/04-dashboard-architecture.md`](architecture/04-dashboard-architecture.md)'s
"The `GET /api/dashboard/work` contract" section — not restated here;
that document is the authoritative source for this endpoint's exact
response shape (`actionable`/`claimed`/`tracking`/`sla`/
`recent_activity`/`metrics`).

---

# Route Families Intentionally Not Documented

`horizon/api/*` (21 routes) and `api/documentation`, `api/oauth2-callback`
are third-party package infrastructure (Laravel Horizon's queue
dashboard API, L5-Swagger's UI backend) — not part of Yemen Flow Hub's
own application API surface. Consult the respective package's own
documentation if these need to be understood; they are excluded from
this reference by design, not by omission.

---

# API Design Principles

The API should remain:

- Consistent
- Predictable
- RESTful
- Workflow-oriented
- Permission-aware
- Organization-aware
- Queue-oriented
- Secure-by-default

---

# Recommended Future Improvements

Future API improvements may include:

- API versioning
- OpenAPI/Swagger documentation
- Rate limiting
- Webhooks
- Real-time notifications
- Integration APIs
