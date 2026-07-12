# API Reference

## Coverage status

**Verification method:** `php artisan route:list --path=api`, run
directly on this repository's `backend/` in the local development
environment on 2026-07-12. Route totals are **not** recorded here as a
fixed number — demo/switch-role routes (`api/auth/demo-users`,
`api/auth/switch-demo-user`, `api/auth/switch-demo-role`) register
conditionally on `config('demo.allowed_environments')`, so the total
route count varies by environment (e.g. staging/production with demo
routes disabled will show fewer routes than local). Re-run the command
above in the target environment to get an accurate count rather than
trusting any number recorded here.

Every endpoint documented below was checked against a real registered
route at verification time, and the accompanying claims (settings keys,
error codes, permission matrices, row limits) were checked against the
implementing source files — not carried over unverified from the prior
version of this document. Where verification found this document was
stale, this move corrected it (see the claim-TTL note under Support
Review Claiming, and the removal of the Voting section below — Executive
Voting has no live V1 route, service, or session model, though some
legacy compatibility symbols remain in the codebase; see that section for
the precise scope of what still exists).

**This document is not yet a complete API reference.** It accurately
covers the primary `EngineRequest` lifecycle, authentication basics,
document/FX-confirmation endpoints, settings, notifications, and report
exports — but the following registered route families exist in
`backend/routes/api.php` and are **not yet documented here**:

- The full Workflow Designer admin API — `workflow-definitions`,
  `workflow-versions` (+ `clone`/`validate`/`publish`/`archive`/`graph`),
  `workflow-versions/{v}/stages`, `workflow-actions`,
  `workflow-versions/{v}/transitions`, `workflow-stages/{s}/permissions`,
  `workflow-stages/{s}/field-rules`, `field-groups`, `fields` (+
  `options`).
- Reference data admin — `reference-tables`, `reference-values` (+
  activate/deactivate lifecycle on both).
- Org-structure admin — `organizations`, `teams`, `roles` (+
  activate/deactivate), `screens`, `screen-permissions/matrix`.
- `merchants` (full CRUD).
- Governance/compliance — `governance/impact`,
  `banks/{bank}/lifecycle-impact`, `compliance/duplicate-invoices`,
  `compliance/expired-documents`, `compliance/sla-breaches`.
- Analytics report endpoints on `ReportController` — `reports/by-bank`,
  `by-currency`, `by-merchant`, `by-sector`, `by-workflow-stage`,
  `requests-over-time`, `sla`, `stage-duration`, `summary`,
  `team-performance` (distinct from the `reports/exports` async-export
  family, which **is** documented below).
- `Profile`/MFA/session management (`api/profile/*`) and `Search`
  (`api/search`, `api/search/recent`).
- Several `AuthController` routes beyond login/logout/me: PIN login,
  password forgot/reset/verify, OTP verification, demo-user/demo-role
  switching.
- Smaller gaps on already-documented families:
  `GET /api/v1/engine-requests/available-workflows`,
  `POST /api/v1/engine-requests/{id}/abandon`,
  `POST /api/v1/engine-requests/{id}/documents/{document}/replace`,
  `POST /api/v1/audit-logs/export`,
  `GET /api/v1/audit-logs/export/{reportExport}`,
  `GET /api/v1/audit-logs/export/{reportExport}/download`.

**Documenting these families is Step 13 of the consolidation plan**
(`docs/audit-functional/22-documentation-consolidation-plan.md`, "Complete
API Reference Coverage") — it has an assigned step, not an open-ended
"someday"; this document should not be treated as the complete canonical
API reference until that step lands. Until then, for any route not
covered above, `php artisan route:list` and the registered controller in
`backend/app/Http/Controllers/Api/` are the authoritative source.

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
- `status` — `ACTIVE` | `CLOSED` | `REJECTED` (the coarse lifecycle flag, not a business status)
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

Editable states are still governed by the canonical business rules in `docs/01-workflow-and-business-rules.md` (editable only in `DRAFT`/`DRAFT_REJECTED_INTERNAL`/`BANK_RETURNED`/`SUPPORT_RETURNED`); the API enforces this by rejecting `draft`/`actions` calls with `REQUEST_CLOSED` (403) once the request leaves an editable stage.

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
- Claims expire after a TTL — the live value is the admin-configurable `support_claim_ttl` setting (`AdminSettingsService`, default 15 minutes, 5–60 range; see Admin Operational Settings below), not the `config('workflow.support_claim_ttl_minutes')` key, which exists but is not read by the claim service — unless extended by a heartbeat
- The heartbeat endpoint must be called every 60 seconds by the frontend while the reviewer is on the request page, or the claim expires
- Only the current claim holder may heartbeat/release their own claim (`CLAIM_NOT_HELD`, 403, otherwise); `system_admin` may force-release any claim
- Claiming/heartbeat/release responses return the updated `EngineRequestResource` (`{ "success": true, "data": {...} }`)

---

# Executive Voting (out of V1 — no live routes)

Executive Voting has **no live V1 workflow routes, vote-casting service,
session model, or active vote data model.** There is no
`POST /api/voting/{id}/vote`, no `.../close` endpoint, no dedicated
`/api/voting/*` route family, no `request_votes` table (physically
dropped — see
[`architecture/06-database-and-models.md`](architecture/06-database-and-models.md)),
and no `VotingSessionStatus` or vote-casting UI anywhere in the shipped
frontend.

**This does not mean zero voting-related code exists in `backend/app`.**
Legacy compatibility/dead-code symbols remain, none of them reachable
from a live route or active data path:

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

Configure production SMTP via environment variables — see `docs/07-account-recovery-and-mail.md`.

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
