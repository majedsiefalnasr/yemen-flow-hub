# System Architecture

**Verified:** 2026-07-12, against `backend/app/`, `frontend/app/`, and
`frontend/package.json` directly.

Yemen Flow Hub is a separated frontend/backend regulatory workflow
platform: API-first, workflow-first backend design, capability-based
access control, secure document handling, RTL-first frontend.

```text
Nuxt 4 Frontend
        ↓
Laravel 11 API
        ↓
MySQL Database
        ↓
Redis (queues, cache)
        ↓
Object Storage (Documents)
```

---

## Frontend architecture

### Stack

Nuxt 4, **Vue 3.5** (`frontend/package.json` pins `^3.5.13`), TypeScript,
Tailwind CSS v4, shadcn-vue, Pinia, VueUse, Zod, VeeValidate.

### Principles

RTL-first, workflow-oriented, capability-aware (not a static per-role
map), organization-scoped visibility, queue-first, component-driven,
readable and operationally focused.

### Responsibilities

Authentication UI, dashboard views, workflow tracking, request forms,
SWIFT upload screens, external FX confirmation views, capability-based
navigation, organization-scoped request visibility, queue-scoped
dashboards, request timelines, read-only and editable states — reading
`runtime_status`/`current_stage`/`final_outcome` from the API rather than
reconstructing status from a static frontend enum (see
[`05-request-state-model.md`](05-request-state-model.md)).

### Folder structure

```text
frontend/app/
├── components/
├── composables/
├── constants/
├── layouts/
├── lib/
├── middleware/
├── pages/
├── plugins/
├── schemas/
├── stores/
├── tests/
├── types/
└── utils/
```

---

## Operational visibility architecture

The platform is not a shared admin dashboard — it's an organization-scoped
operational workflow platform. **Bank membership alone does not grant
visibility into every request that bank owns** — see
[`architecture/06-database-and-models.md`](06-database-and-models.md)'s
"Request visibility model" section for the precise two-dimension
composition (organization/bank scope via `DataScope`, plus per-request
VIEW stage-permission via `StagePermissionResolver`; a non-`system_admin`
user needs both). Actions remain capability-scoped, and dashboards remain
queue/operationally scoped, not role-name-branched.

The system enforces: organization/bank-scoped visibility, stage
VIEW-permission-scoped visibility (both required together, not either
alone), capability-scoped operational actions, queue-based operational
workflows, workflow-aware request filtering. See
[`03-permission-model.md`](03-permission-model.md) for the mechanism —
two independent authorization systems (screen-capability,
stage-permission), neither of which is a bespoke per-role rule.

**Dashboard visibility, by role/capability family**: this section
previously enumerated a fixed per-role dashboard for each of Data Entry,
Bank Reviewer, SWIFT Officer, Support Committee, Executive Committee,
Committee Director, and CBY Admin, including voting-queue content for
Executive/Director roles. That model is **superseded** — see
[`04-dashboard-architecture.md`](04-dashboard-architecture.md) for the
current two-family model (`MyWorkDashboard.vue` operational family for
every workflow-executor role vs. dedicated analytics dashboards for
governance roles) and the shared actionable-work invariant that replaced
per-role dashboard branching. Executive Voting is out of V1 — there is no
voting queue or voting-session dashboard content in the current UI.

---

## Backend architecture

### Stack

Laravel 11, Laravel Sanctum, MySQL, Redis, queue workers, REST API.

### Principles

Workflow logic, state transitions, capability-based authorization, audit
logging, notifications, file handling, validation, and security all live
in service classes, not controllers — organization-scoped visibility and
queue-scoped operational access are centralized there too. The backend
follows a service-oriented architecture.

### Folder structure

```text
backend/app/
├── Console/
├── DTOs/
├── Enums/
├── Exceptions/
├── Http/
├── Jobs/
├── Mail/
├── Models/
├── OpenApi/
├── Policies/
├── Providers/
├── Rules/
├── Services/
└── Support/
│
routes/
database/
storage/
config/
```

### Dynamic workflow engine (`app/Services/Workflow/`)

Not a single monolithic service — a set of focused services operating on
data-driven workflow definitions. Confirmed present:

- `WorkflowDesignerService` / `WorkflowVersionValidator` — author,
  validate, clone, publish, archive `WorkflowVersion`s.
- `StagePermissionResolver` — resolves VIEW/EXECUTE access to a
  `WorkflowStage` from `stage_permissions` rows (see
  [`03-permission-model.md`](03-permission-model.md)).
- `EngineTransitionService` — executes a transition: validates stage
  permissions, field rules, claim ownership, optimistic-locking version,
  then moves the request and runs registered stage hooks/effects.
- `EngineClaimService` — claim/heartbeat/release lifecycle for stages
  where `requires_claim` is true.
- `StageFieldRuleValidator` — enforces per-stage field rules against the
  request's dynamic field data.
- `RequestProjectionSync` — projects selected `data` JSON fields onto
  indexed `engine_requests` columns (amount, currency, invoice number)
  for fast querying/reporting.

There is no fixed, hardcoded state machine — request lifecycles are
defined by published `WorkflowVersion` data and interpreted by these
services at runtime. Executive review/decision is executed through this
same generic engine, not a separate dedicated Voting Service — that
service and its concept were removed along with the rest of the
pre-V1-scope voting model (see
[`architecture/06-database-and-models.md`](06-database-and-models.md)'s
"Legacy tables" section).

### Audit service (`app/Services/Audit/AuditService.php`)

Responsible for action logging and, jointly with `workflow_history`,
per-transition history. This is a **manual, per-call** contract, not
automatic, and coverage is not universal across every mutating
service — see [`03-permission-model.md`](03-permission-model.md) §5 for
the caller-verification caveat before assuming any given mutation is
audited.

### Request visibility service layer

The backend enforces organization-scoped visibility at query level, API
level, policy level, and workflow level. Frontend visibility is never
trusted. See [`03-permission-model.md`](03-permission-model.md) for
`DataScope`, the mechanism that implements this — a plain service method
invoked explicitly per read surface, not an automatic query filter.

---

## Database architecture

MySQL. See
[`architecture/06-database-and-models.md`](06-database-and-models.md) for
the core-workflow schema (not an exhaustive database catalog — see that
document's own Coverage status section for the table families it
excludes), verified directly against migrations.

**Core entities**: `users`, `banks`; `organizations`/`teams`/`roles` (the
governance model `stage_permissions` binds workflow access to);
`workflow_definitions`/`workflow_versions`/`workflow_stages`/
`workflow_actions`/`workflow_transitions` (the dynamic workflow engine's
definition tables); `stage_permissions`; `engine_requests` (replaces the
dropped legacy `import_requests`); `engine_request_documents` (replaces
the dropped legacy `request_documents`); `workflow_history` (replaces the
dropped legacy `request_stage_history`); `audit_logs`;
`customs_declarations`.

The legacy `import_requests`, `request_documents`, `request_votes`, and
`request_stage_history` tables have been physically dropped from the
schema by migration, not merely superseded in application code — there
is no `request_votes` table live or otherwise (Executive Voting is out of
V1).

Each `engine_requests` row tracks state via **two separate columns**,
not one: `status` (a plain `string(20)`, application-constrained to the
5-value runtime lifecycle `ACTIVE`\|`CLOSED`\|`REJECTED`\|`CANCELLED`\|`ABANDONED`
via `App\Support\EngineRequestStatus` — this is what
`EngineRequestResource` maps to the API-facing `runtime_status`) and
`current_stage_id` (which `WorkflowStage` the request currently
occupies, carrying the fine-grained business position — designer-defined
stage name, `semantic_role`, `final_outcome` — that `status` alone cannot
express). Neither column substitutes for the other: `status` is the
coarse lifecycle flag, `current_stage_id` is the business-detail pointer.
The row also tracks organizational workflow ownership (`bank_id`),
workflow history (via `workflow_history`), documents, audit events, and
claim lifecycle. There is no vote tracking — see the database doc's
"Legacy tables" section for what was dropped.

---

## Authentication & authorization

**Authentication**: Laravel Sanctum, secure session authentication,
HTTP-only cookies.

**Authorization**: capability-based, not a single role-name check
scattered through code. See
[`03-permission-model.md`](03-permission-model.md) for the two
independent systems (screen-capability, stage-permission) and the
canonical 8-value `UserRole` enum. Permissions are enforced in backend
services/policies; visibility is enforced through organization-scoped
database queries (`DataScope`). Users never receive requests outside
their organizational scope; actions remain capability-scoped.

---

## File storage architecture

Document types: request documents, SWIFT documents, external FX
confirmations. Storage rules: private access, PDF-only validation, audit
tracking on upload/download, sanitized filenames (client-supplied names
sanitized before persistence and before download headers; the private
on-disk filename stays UUID-based) — see
[`api-reference.md`](../api-reference.md) for the specific upload/download
endpoints and their permission matrix.

---

## Queue & notification architecture

Redis is used for queue workers, background jobs, and cache. The system
supports request-status, approval, rejection, and SWIFT-upload-request
notifications via `App\Services\Notifications\NotificationRegistry` and
the notification inbox API (`GET /api/v1/notifications`, see
[`api-reference.md`](../api-reference.md)).

---

## Technical principles

**API-first** — the frontend communicates only through APIs.
**Workflow-first** — workflow rules are centralized inside backend
services; queue visibility and organization-scoped rules must not
duplicate into the frontend. **RTL-first** — Arabic RTL support is built
into the frontend architecture from the beginning, not adapted after the
fact. **Secure-by-default** — secure authentication, capability
validation, audit logging, protected document access are baseline
requirements, not optional hardening.
