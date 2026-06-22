---
stepsCompleted: ["step-01-validate-prerequisites", "step-02-design-epics", "step-03-create-stories", "step-04-final-validation"]
status: complete
inputDocuments:
  - _bmad-output/planning-artifacts/prd-dynamic-workflow-engine.md
  - _bmad-output/planning-artifacts/architecture-dynamic-workflow-engine.md
---

# Yemen Flow Hub — Dynamic Workflow Engine (Epic 18) — Epic Breakdown

## Overview

Epic 18 breakdown: decomposes the Dynamic Workflow Engine PRD + Architecture into implementable stories. Separate from base `epics.md` (Epic 1–16) and `epics-national-committee.md` (Epic 17), which are NOT touched. Backend-first per phase; Nuxt frontend; clean start; engine seeded with the National Committee IMPORT_FINANCING workflow.

> Full requirement text lives in the two input documents. Below is the inventory; each FR/NFR is referenced by code in the coverage map and stories.

## Requirements Inventory

### Functional Requirements

**FR-AUTH — Auth & session (reuse infra)**
- FR-AUTH1 JWT login/mfa/refresh/logout/me/forgot/reset/change; Bearer access + HttpOnly refresh; blacklist; no localStorage tokens
- FR-AUTH2 TOTP MFA (phase 1)
- FR-AUTH3 Deactivation / sensitive-perm change invalidates all sessions
- FR-AUTH4 Rate limit login/MFA/password-reset
- FR-AUTH5 `/auth/me` returns user + organization + team + role + bank|null + computed screen permissions + capabilities

**FR-GOV — Governance**
- FR-GOV1 Organizations CRUD + activate/deactivate; protected system defaults; code immutable
- FR-GOV2 Teams (one org, no role_code, one team per user); cannot delete/deactivate if users
- FR-GOV3 Roles (one org; one role per user matching org); screen perms attach to role; protected defaults
- FR-GOV4 Banks (under commercial_banks org); immutable org after use; cannot deactivate if referenced
- FR-GOV5 Users (one org/team/role; bank iff bank-org); validation, no hard delete, deactivate invalidates JWT; reset-password/reset-mfa
- FR-GOV6 Forms return nested relation objects (org/team/role/bank)

**FR-MERCH — Merchants (canonical, DI-5)**
- FR-MERCH1 Merchant→one bank, owners[], companies[], requests[]
- FR-MERCH2 tax_number unique system-wide; company commercial_registration unique
- FR-MERCH3 Bank-scope visibility; global-scope sees all with bank filter
- FR-MERCH4 Soft delete; no suspend with active requests; bank immutable after first request; audited
- FR-MERCH5 Merchant fields (status ACTIVE|SUSPENDED)
- FR-MERCH6 Owners (0–100%), companies (CR number/expiry/sector/is_active)
- FR-MERCH7 Nested owners+companies in one transaction
- FR-MERCH8 List filters + business errors (TAX_EXISTS / CR_EXISTS / HAS_ACTIVE_REQUESTS / BANK_IMMUTABLE / OUT_OF_SCOPE)

**FR-REF — Reference data**
- FR-REF1 Default tables (sector_activity/arrival_port/origin_country); reference_tables + reference_values
- FR-REF2 Keys immutable/unique; store ID/key not label; used-table/value protected; defaults protected
- FR-REF3 CRUD + activate/deactivate

**FR-WD — Workflow designer**
- FR-WD1 Definitions + versions (DRAFT/PUBLISHED/ARCHIVED); edit draft only; publish final; clone for changes; request keeps original version
- FR-WD2 Stages (one initial, ≥1 final, code unique, SLA, non-final needs transition+executor)
- FR-WD3 Actions catalog (kind DRAFT/APPROVE/REJECT/RETURN/CLOSE/INFO/CUSTOM; immutable code; used-action protected)
- FR-WD4 Transitions (from/action/to, requires_comment, confirmation_message; no dup action per stage)
- FR-WD5 Stage permissions (stage_permissions; AND-in-row/OR-across-rows; EXECUTE⊇VIEW; display_label; sole routing source)
- FR-WD6 Fields & groups (9 types incl DYNAMIC_SELECT; settings; key immutable; protected defaults)
- FR-WD7 Per-stage field rules (visible/editable/required; enforced on draft + transition)
- FR-WD8 Process graph (nodes/edges; derived; visibility from stage_permissions)
- FR-WD9 Validate-before-publish; publish rejects invalid config

**FR-REQ — Requests & "دوري" queue**
- FR-REQ1 Request = instance of published version (data JSON + version)
- FR-REQ2 Create if EXECUTE on initial stage; first history+audit
- FR-REQ3 List by VIEW/EXECUTE on current stage + scope; filters
- FR-REQ4 `GET /requests/my-queue` derived from current_stage_id + stage_permissions; SLA sort
- FR-REQ5 `POST /requests/{id}/actions` transactional (lock→version→stage→EXECUTE→fields→update→history→audit→notify)
- FR-REQ6 Draft (EXECUTE holders; required only on leaving action)
- FR-REQ7 Documents (per request+field+user+stage; deletable before lock)
- FR-REQ8 History + graph
- FR-REQ9 Duplicate-invoice compliance warning; business errors

**FR-AUD — Audit & compliance**
- FR-AUD1 audit_logs append-only (full field set incl correlation_id)
- FR-AUD2 Log auth/admin/perms/workflow/request/document/export events; workflow_history linked
- FR-AUD3 APIs + filters; duplicate-invoices compliance
- FR-AUD4 Compliance phase 1 (duplicate invoice, expired docs, SLA breach)

**FR-RPT — Reports**
- FR-RPT1 10 aggregated report APIs, user-scoped
- FR-RPT2 Shared filters
- FR-RPT3 Exports (queued jobs, same filters)
- FR-RPT4 Privacy (individual perf separate permission; team/role default; scope-bound)

**FR-PERM — Screen permissions**
- FR-PERM1 Screen catalog + capabilities (VIEW/CREATE/UPDATE/DELETE/EXPORT/MANAGE); screen_permissions role→screen→capability
- FR-PERM2 No hardcoded role-code gating; request view/execute from stage_permissions
- FR-PERM3 Default sysadmin all perms; protect last admin
- FR-PERM4 APIs (screens, role screen-perms get/put, me/permissions)

**FR-NOTIF — Notifications**
- FR-NOTIF1 notifications + notification_recipients
- FR-NOTIF2 Workflow/SLA/compliance/publish/perm events
- FR-NOTIF3 In-platform only; audience→users at send; own copy; queued after commit
- FR-NOTIF4 APIs (list/unread-count/read/unread/archive/read-all)

### NonFunctional Requirements

- NFR1 Platform (Laravel ^11/PHP ^8.2/MySQL 8/Redis/Swagger/JWT; Nuxt4/Vue/TS/Tailwind v4/shadcn-vue/Pinia/VeeValidate+Zod)
- NFR2 API conventions (/api/v1, snake_case, version on every resource, list envelope, error envelope, HTTP codes)
- NFR3 Concurrency (version + 409 STALE_RESOURCE; transition transaction + row lock)
- NFR4 Files (private disk, metadata+path, authorized download, PDF-only, backend validation, backups)
- NFR5 Hybrid storage + index set (DI-2)
- NFR6 Security (query-level scope; screen+stage permissions never role codes; transactional transitions; audit; rate limits)
- NFR7 Ops (Nginx/PHP-FPM/Supervisor/Cron/single server)
- NFR8 UX (Arabic RTL, desktop-first, queue-first, badge color+icon, WCAG 2.2 AA)
- NFR9 Per-phase definition-of-done (migration+seed, FormRequests+Policies, Resources, feature tests, Swagger, wired UI, no mock/localStorage)

### Additional Requirements

- Replace-core-keep-infra; identity migration (users.role → org→team→role); retire voting/traders/21-status/WorkflowService (architecture §1).
- DI-1 M:N identity seed one-each; DI-2 Hybrid; DI-3 voting removed; DI-4 stage-hook registry for domain side-effects; DI-5 merchants canonical.
- Default seed = IMPORT_FINANCING 8-stage (architecture §8 / PRD §8).

### UX Design Requirements

No separate UX spec document. UX reference = the (transient) `dynamic-workflow-engine/` React app screens + DESIGN.md/frontend SHADCN.md tokens. Per-screen UX captured inside each story; absorbed so the reference app is deletable.

### FR Coverage Map

- FR-AUTH1–5: Epic 18.1 — auth reshaped to org→team→role identity, `/auth/me`, session invalidation, MFA reuse
- FR-GOV1–6: Epic 18.1 — organizations/teams/roles/banks/users CRUD + identity model
- FR-MERCH1–8: Epic 18.2 — merchants/owners/companies (canonical dynamic_select source)
- FR-REF1–3: Epic 18.3 — reference tables/values (feeds designer fields)
- FR-WD1–9: Epic 18.4 — workflow designer + engine-core tables + validate/publish/versioning/graph
- FR-REQ1–9: Epic 18.5 — request instances + دوري queue + DI-4 stage hooks + Hybrid storage
- FR-AUD1–4: Epic 18.6 — append-only audit + compliance
- FR-RPT1–4: Epic 18.6 — scoped aggregated reports + exports
- FR-PERM1–4: Epic 18.7 — screen-permission catalog + management (ScreenGuard primitive lands in 18.1)
- FR-NOTIF1–4: Epic 18.7 — in-platform notifications

No FR unmapped.

## Epic List

### Epic 18.1: Governance & Identity Foundation
Admins manage the full identity graph (organizations, teams, roles, banks, users); everyone authenticates and is scoped under the org→team→role model; system seeded with protected governance defaults; ScreenGuard primitive reads computed permissions from `/auth/me`. Replaces the fixed 8-role enum and migrates existing `users.role`.
**FRs covered:** FR-AUTH1–5, FR-GOV1–6
**Stories (planned):** 18.1.1 identity foundation + auth + seed + ScreenGuard primitive · 18.1.2 organizations (`/admin/orgs`) · 18.1.3 teams (`/admin/teams`) · 18.1.4 roles (`/admin/roles`) · 18.1.5 banks (`/admin/entities`) · 18.1.6 users (`/admin/cby-staff` + `/bank/users`)

### Epic 18.2: Merchants
Banks manage merchants, owners, and related companies — the canonical `DYNAMIC_SELECT` source. Org/bank-scoped; soft delete; bank-immutable-after-first-request; nested owners+companies in one transaction.
**FRs covered:** FR-MERCH1–8

### Epic 18.3: Reference Data
Admins manage reference tables and values (sector_activity, arrival_port, origin_country, + custom) that feed designer fields. Keys immutable; used tables/values deactivate-only; defaults protected. Foundation + admin screen together (same files); lands before the designer because `DYNAMIC_SELECT`/reference fields depend on it.
**FRs covered:** FR-REF1–3

### Epic 18.4: Workflow Designer
Admins design, version, validate, and publish workflows: definitions/versions (DRAFT/PUBLISHED/ARCHIVED), stages, actions catalog, transitions, stage permissions, field groups + fields, per-stage field rules, process graph, validate-before-publish. Includes the engine-core tables. Published versions immutable; clone-to-edit.
**FRs covered:** FR-WD1–9

### Epic 18.5: Requests & دوري Queue
Users create and act on requests as instances of a published version; the "دوري" queue derives from `current_stage_id` + `stage_permissions`; concurrency-safe transactional transitions; history + graph; documents; duplicate-invoice compliance. Hybrid storage (DI-2) and stage entry/exit hooks (DI-4) re-bind customs/FX-confirmation PDF and financing ledger.
**FRs covered:** FR-REQ1–9

### Epic 18.6: Audit & Reports
Append-only audit log with full event coverage and filters; phase-1 compliance (duplicate invoice, expired docs, SLA breach); ten scoped aggregated report APIs with shared filters and queued exports; individual-performance privacy gate.
**FRs covered:** FR-AUD1–4, FR-RPT1–4

### Epic 18.7: Screen Permissions & Notifications
Central screen catalog + capability grants (role→screen→capability) replacing role-code gating; protect last system admin; full management UI (ScreenGuard primitive already in 18.1). In-platform notifications with per-user read/archive, queued after commit, for workflow/SLA/compliance/publish/permission events.
**FRs covered:** FR-PERM1–4, FR-NOTIF1–4

**Dependency flow:** 18.1 → (18.2, 18.3) → 18.4 → 18.5 → (18.6, 18.7)

---

## Epic 18.1: Governance & Identity Foundation

Replace the fixed 8-role enum with a configurable org→team→role identity graph, seed protected governance defaults, reshape auth to carry the new identity + computed permissions, and give admins CRUD over organizations, teams, roles, banks, and users. Backend-first per story; Nuxt screen wired against the finalized contract. After this epic, every user authenticates and is scoped under the new identity, and the fixed `users.role` semantics are retired.

**FRs covered:** FR-AUTH1–5, FR-GOV1–6 · **Decisions:** DI-1 (M:N join tables, one-each seeded/UI), replace-core-keep-infra, identity migration.

### Story 18.1.1: Identity schema & governance seed

As a system administrator,
I want the org→team→role identity tables created and seeded with protected defaults,
So that the platform has a configurable identity foundation that replaces the fixed role enum.

**Acceptance Criteria:**

**Given** the migrations run on a clean database
**When** the schema is created
**Then** `organizations`, `teams`, `roles` tables exist with `code`, `name`, `is_system`, `is_active` (+ `organization_id` on teams/roles)
**And** join tables `user_teams` and `user_roles` exist (M:N, DI-1) while user create/update enforces exactly one team and one role
**And** unique constraints hold: `organizations.code`, `teams(organization_id,code)`, `roles(organization_id,code)`

**Given** the seeder runs
**When** governance defaults are seeded
**Then** orgs `commercial_banks`, `national_committee`, `system_administration` exist with `is_system = true`
**And** the seeded teams (7) and roles (8) from PRD §8 are created under their orgs
**And** the seeded data matches the IMPORT_FINANCING identity model

**Given** existing users with a legacy `users.role` string
**When** the migration runs
**Then** each user is mapped to one `user_roles` row (+ inferred `team_id`) and `organization_id`/`bank_id` set per role
**And** `users.role` is retained transitionally as a denormalized cache (dropped post-cutover)

**Given** a default `is_system` org/team/role
**When** a delete is attempted
**Then** the operation is rejected (defaults are delete-protected)

### Story 18.1.2: Auth identity reshape & ScreenGuard primitive

As an authenticated user,
I want my session to carry my organization, team, role, bank, and computed screen permissions,
So that the UI and backend scope me correctly without relying on hardcoded role codes.

**Acceptance Criteria:**

**Given** a logged-in user
**When** `GET /auth/me` is called
**Then** the response includes user + nested `organization`, `team`, `role`, `bank|null`, computed screen permissions, and relevant general capabilities

**Given** an admin deactivates a user or changes sensitive permissions
**When** the change commits
**Then** all of that user's JWT sessions are invalidated (blacklist) and subsequent requests return 401

**Given** the frontend renders a screen or control
**When** the ScreenGuard primitive evaluates access
**Then** it uses the computed permissions from `/auth/me` (not role codes) and hides unmounted/forbidden surfaces
**And** MFA (TOTP) and rate limits on login/MFA/password-reset continue to work from existing infra

**Given** any auth event (login, logout, failed attempt)
**When** it occurs
**Then** it is written to `audit_logs` (failed/unauthenticated with null user)

### Story 18.1.3: Organizations management

As a system administrator,
I want to create, edit, activate, and deactivate organizations,
So that I can model the institution's governance tiers.

**Acceptance Criteria:**

**Given** the organizations screen (`/admin/orgs`)
**When** I create an organization with `code` and `name`
**Then** it is persisted and appears in the list with `is_active = true`

**Given** a system-default organization
**When** I attempt to delete it, deactivate it while in use, or change its `code`
**Then** the action is rejected; only the display `name` is editable when policy allows

**Given** an organization referenced by teams/roles/users
**When** I attempt to deactivate it
**Then** it is blocked with a clear in-use error

**Given** any create/update
**When** it succeeds
**Then** the response returns the full object incl. `version`, and the change is audited

### Story 18.1.4: Teams management

As a system administrator,
I want to manage teams that each belong to one organization,
So that users can be grouped within their organization without a fixed role.

**Acceptance Criteria:**

**Given** the teams screen (`/admin/teams?organization_id=`)
**When** I create a team under an organization
**Then** it is persisted with `organization_id`, `code`, `name` and carries no role_code

**Given** a team linked to users
**When** I attempt to delete or deactivate it
**Then** the action is blocked with an in-use error

**Given** the unique constraint `teams(organization_id, code)`
**When** I create a duplicate code within the same org
**Then** a 422 validation error is returned

### Story 18.1.5: Roles management

As a system administrator,
I want to manage roles scoped to an organization,
So that users hold an org-consistent role that screen permissions attach to.

**Acceptance Criteria:**

**Given** the roles screen (`/admin/roles?organization_id=`)
**When** I create a role under an organization
**Then** it is persisted with `organization_id`, `code`, `name`

**Given** a role assigned to any user, or a default role
**When** I attempt to delete or deactivate it
**Then** the action is blocked

**Given** a user's role
**When** it is validated
**Then** the role must belong to the user's organization (else 422)

### Story 18.1.6: Banks management

As a system administrator,
I want to manage banks under the commercial-banks organization,
So that bank users and merchants can be scoped to a specific bank.

**Acceptance Criteria:**

**Given** the banks screen (`/admin/entities`)
**When** I create a bank
**Then** it is persisted with `organization_id` (commercial_banks), `code`, `name`, `license_number`, `swift_code`, `status`
**And** unique constraints hold for `banks.code` and nullable `swift_code`

**Given** a bank referenced by users, merchants, or requests
**When** I attempt to delete or deactivate it
**Then** the action is blocked

**Given** a bank that has been used
**When** I attempt to change its organization
**Then** the change is rejected (org immutable after use)

### Story 18.1.7: Users management

As a system administrator,
I want to manage users with a single organization, team, role, and (for bank org) bank,
So that every actor has a valid, scoped identity.

**Acceptance Criteria:**

**Given** the users screens (`/admin/cby-staff`, `/bank/users`)
**When** I create a user
**Then** the user has exactly one organization, one team, one role, and one bank iff org = commercial_banks (else `bank_id` null)
**And** the team and role must belong to the user's organization (else 422)
**And** the create/update response returns nested organization/team/role/bank objects

**Given** a user with active work
**When** I deactivate them
**Then** reassignment/closure is required per the module rule, the user is never hard-deleted, and their JWT sessions are invalidated

**Given** an admin action
**When** I call `reset-password` or `reset-mfa` for a user
**Then** the action succeeds and is audited

---

## Epic 18.2: Merchants

Banks manage merchants, their owners/shareholders, and related companies — the canonical `DYNAMIC_SELECT` source for the engine (DI-5). Org/bank-scoped visibility, soft delete, and integrity guards.

**FRs covered:** FR-MERCH1–8 · **Decisions:** DI-5 (merchants canonical; trader tables retire/alias).

### Story 18.2.1: Merchant CRUD, scope & filters

As a bank user,
I want to create and manage merchants for my bank,
So that requests can reference an authorized merchant.

**Acceptance Criteria:**

**Given** the merchants screen (`/merchants`)
**When** I create a merchant
**Then** it persists with `bank_id`, `name`, `tax_number`, `tax_card_expiry`, `address`, `phone`, `status` (`ACTIVE|SUSPENDED`)

**Given** I am a bank user
**When** I list merchants
**Then** I see only my bank's merchants; a global-scope user sees all with a `bank_id` filter

**Given** the list
**When** I apply filters
**Then** `search`, `bank_id`, `status`, `sector_id`, `tax_number` all work and lists are paginated

**Given** a merchant
**When** I delete it
**Then** it is soft-deleted (never hard-deleted) and the change is audited

### Story 18.2.2: Owners & related companies (nested transaction)

As a bank user,
I want to manage a merchant's owners and related companies in one save,
So that ownership and company data stay consistent.

**Acceptance Criteria:**

**Given** the merchant create/update form
**When** I submit nested `owners` and `companies`
**Then** all are persisted in a single transaction
**And** owners accept `ownership_percentage` 0–100 (DB stores all; UI surfaces ≥25%)
**And** companies persist `name`, `commercial_registration_number`, `commercial_registration_expiry`, `sector_reference_value_id`, `is_active`

**Given** a company commercial-registration number that already exists
**When** I save
**Then** a `COMMERCIAL_REGISTRATION_EXISTS` error is returned

### Story 18.2.3: Merchant integrity guards

As a system,
I want uniqueness and immutability rules enforced,
So that merchant data cannot violate business invariants.

**Acceptance Criteria:**

**Given** a tax number already used system-wide
**When** I create/update a merchant
**Then** `MERCHANT_TAX_NUMBER_EXISTS` is returned

**Given** a merchant with active requests
**When** I attempt to suspend it
**Then** `MERCHANT_HAS_ACTIVE_REQUESTS` is returned

**Given** a merchant that already has its first request
**When** I attempt to change its bank
**Then** `MERCHANT_BANK_IMMUTABLE` is returned

**Given** a request references a merchant outside the user's scope
**When** validated
**Then** `MERCHANT_OUT_OF_SCOPE` is returned

---

## Epic 18.3: Reference Data

Admins manage reference tables and values that feed designer `SELECT`/`DYNAMIC_SELECT`/reference fields. Lands before the designer because field options depend on it.

**FRs covered:** FR-REF1–3.

### Story 18.3.1: Reference tables & values CRUD + seed

As a system administrator,
I want to manage reference tables and their values,
So that workflow fields can offer controlled option lists.

**Acceptance Criteria:**

**Given** migrations + seed run
**Then** `reference_tables` (`key`, `label`, `sort_order`, `is_active`, `is_system`) and `reference_values` (`reference_table_id`, `key`, `label`, `sort_order`, `is_active`, `is_system`) exist
**And** default tables `sector_activity`, `arrival_port`, `origin_country` are seeded with `is_system = true`

**Given** the reference-data screen (`/admin/reference-data`)
**When** I create/update a table or value or toggle activation
**Then** the change persists and is audited

### Story 18.3.2: Reference protection rules

As a system,
I want reference keys and used values protected,
So that history and published versions stay valid.

**Acceptance Criteria:**

**Given** an existing table/value
**When** I attempt to change its `key`
**Then** the change is rejected (keys immutable & unique)

**Given** a table used by a published version, or a value used by a request
**When** I attempt to delete it
**Then** deletion is blocked; only deactivation is allowed (preserving history)

**Given** a request saves a reference selection
**When** persisted
**Then** it stores the value ID/key, never the label

---

## Epic 18.4: Workflow Designer

Admins design, version, validate, and publish workflows. Includes the engine-core tables. Published versions are immutable; editing clones a new draft.

**FRs covered:** FR-WD1–9.

### Story 18.4.1: Workflow definitions & versions (lifecycle)

As a workflow admin,
I want to create workflow definitions and manage versions through DRAFT/PUBLISHED/ARCHIVED,
So that I can evolve workflows safely without breaking running requests.

**Acceptance Criteria:**

**Given** the designer (`/admin/workflows`)
**When** I create a definition
**Then** `workflow_definitions` persists with unique `code`; a first `DRAFT` version is created

**Given** a DRAFT version
**When** I edit it
**Then** edits are allowed; **PUBLISHED/ARCHIVED versions reject edits**

**Given** a PUBLISHED version
**When** I clone it
**Then** an independent new DRAFT version is produced and the original is unchanged

**Given** unique constraint `workflow_versions(workflow_definition_id, version_number)`
**When** I create a duplicate
**Then** 422 is returned

### Story 18.4.2: Stages

As a workflow admin,
I want to define stages within a version,
So that the workflow has ordered steps.

**Acceptance Criteria:**

**Given** a DRAFT version
**When** I add stages
**Then** each persists `code`, `name`, `description`, `sort_order`, `is_initial`, `is_final`, `sla_duration_minutes`, `status` with unique `code` within the version

**Given** the version
**When** validated
**Then** exactly one initial stage and at least one final stage are required

**Given** a stage bound to a transition or request
**When** I delete it
**Then** the delete is blocked

### Story 18.4.3: Actions catalog

As a workflow admin,
I want a reusable catalog of actions,
So that transitions reference consistent verbs.

**Acceptance Criteria:**

**Given** the actions catalog
**When** I create an action
**Then** it persists `code` (unique, immutable), `name` (editable), `kind` (`DRAFT|APPROVE|REJECT|RETURN|CLOSE|INFO|CUSTOM`), `is_active`, `is_system`

**Given** an action used in a transition
**When** I delete or deactivate it
**Then** the action is blocked

### Story 18.4.4: Transitions

As a workflow admin,
I want to connect stages with actions,
So that requests can move through the workflow.

**Acceptance Criteria:**

**Given** a DRAFT version
**When** I add a transition
**Then** it persists `from_stage_id`, `action_id`, `to_stage_id`, `requires_comment`, `confirmation_message`

**Given** the unique constraint `workflow_transitions(from_stage_id, action_id)`
**When** I add the same action twice from one stage
**Then** it is rejected; self-stage transitions are allowed

### Story 18.4.5: Stage permissions

As a workflow admin,
I want to grant VIEW/EXECUTE on each stage by org/team/role/user,
So that the queue and request access derive from configuration.

**Acceptance Criteria:**

**Given** a stage
**When** I add a `stage_permissions` row
**Then** it persists `organization_id`, `team_id`, `role_id`, `user_id?`, `access_level` (`VIEW|EXECUTE`), `display_label`
**And** set fields within a row match with AND; different rows match with OR; `EXECUTE` implies `VIEW`

**Given** unique `stage_permissions(role_id, screen_id, capability)` is not applicable here, but request permissions
**When** evaluated
**Then** they derive solely from `stage_permissions` (no parallel routing source)

### Story 18.4.6: Field groups & fields

As a workflow admin,
I want to define field groups (tabs) and typed fields,
So that the request form is configurable.

**Acceptance Criteria:**

**Given** a DRAFT version
**When** I add field groups and fields
**Then** groups order as tabs; fields persist the full settings set (`key`, `label`, `type`, validation bounds, `regex_pattern`, `options`, `reference_table_id`, `dynamic_source`, file settings, `is_system`)
**And** field `type` is one of `TEXT, NUMBER, DATE, SELECT, DYNAMIC_SELECT, TEXTAREA, FILE, CURRENCY, CHECKBOX`
**And** `key` is unique within the version and immutable after the version is used

**Given** a `DYNAMIC_SELECT` field
**When** I set its source
**Then** it resolves from merchants / merchant_companies / reference_data

**Given** a field used by a request, or a default field
**When** I delete it
**Then** the delete is blocked (changes happen via a new version)

### Story 18.4.7: Per-stage field rules

As a workflow admin,
I want to set visibility/editability/required per field per stage,
So that the form adapts to each stage.

**Acceptance Criteria:**

**Given** a stage and a field
**When** I set a `stage_field_rules` row
**Then** it persists `is_visible`, `is_editable`, `is_required`

**Given** a draft save or a transition
**When** the backend validates
**Then** it enforces the stage's field rules

### Story 18.4.8: Process graph

As a workflow admin,
I want a generated graph of the workflow,
So that I can see branches, returns, and final states.

**Acceptance Criteria:**

**Given** a version's stages and transitions
**When** I request the graph
**Then** the API returns `nodes` and `edges` derived from real config
**And** node visibility derives from `stage_permissions`; `display_label` provides contextual naming

### Story 18.4.9: Validate & publish

As a workflow admin,
I want validation before publishing,
So that only valid workflows go live.

**Acceptance Criteria:**

**Given** a DRAFT version
**When** I call validate
**Then** displayable errors are returned for: bad/missing initial stage, no final stage, non-final stage without transition or executor, transition to invalid resource, duplicate codes/keys, invalid field source

**Given** a version with any validation error
**When** I attempt to publish
**Then** publish is rejected

**Given** a valid version
**When** I publish
**Then** it becomes the active published version and is immutable; new requests use it

---

## Epic 18.5: Requests & دوري Queue

Users create and act on requests as instances of a published version. The queue and permissions derive from `stage_permissions`. Hybrid storage (DI-2) and stage hooks (DI-4) keep customs/FX/ledger working.

**FRs covered:** FR-REQ1–9 · **Decisions:** DI-2 (Hybrid), DI-4 (stage hooks).

### Story 18.5.1: Create request as instance (Hybrid storage)

As an authorized user,
I want to create a request from the active published version,
So that work enters the workflow.

**Acceptance Criteria:**

**Given** I have `EXECUTE` on the initial stage
**When** I create a request
**Then** a `requests` row persists `workflow_version_id`, `current_stage_id`, backend-generated unique `reference`, `status = ACTIVE`, `created_by`, `bank_id`, `merchant_id`, `data`, `version`
**And** explicit indexed columns are populated for bank, merchant, status, stage, reference, amount, currency, invoice_number (DI-2)
**And** data is validated against the initial stage's field rules
**And** the first `workflow_history` and `audit_logs` entries are written

### Story 18.5.2: Request list (scoped & filtered)

As an authorized user,
I want to see requests I can view or act on,
So that I find my work.

**Acceptance Criteria:**

**Given** the requests screen
**When** I list
**Then** I see requests where I have `VIEW` or `EXECUTE` on the current stage, within org/bank scope
**And** filters work: `workflow_id`, `workflow_version_id`, `stage_id`, `bank_id`, `merchant_id`, `status`, `created_from/to`, `sla_status`, `search`

### Story 18.5.3: "دوري" queue

As an executor,
I want a queue of requests awaiting my action,
So that I act in priority order.

**Acceptance Criteria:**

**Given** `GET /requests/my-queue`
**When** called
**Then** it returns only `ACTIVE` requests whose current stage grants me `EXECUTE` (matching org/team/role/user/bank), derived from `current_stage_id` + `stage_permissions`
**And** default sort is SLA-breached → nearest-to-breach → oldest-in-stage

### Story 18.5.4: Execute a transition (transactional, concurrency-safe)

As an executor,
I want to perform a stage action,
So that the request advances.

**Acceptance Criteria:**

**Given** `POST /requests/{id}/actions` with `transition_id`, `comment`, `data`, `version`
**When** executed
**Then** within one transaction the system locks the request, checks `version` (mismatch → `409 REQUEST_STALE`), checks current stage, checks `EXECUTE` (`STAGE_EXECUTION_FORBIDDEN`), validates fields (`STAGE_FIELDS_INVALID`) and comment (`COMMENT_REQUIRED`), updates data/stage/status + Hybrid columns, appends `workflow_history` + `audit_logs`, then queues notifications after commit

**Given** the same request and `version`
**When** two transitions race
**Then** only one succeeds; the other gets `409`

**Given** a transition not available from the current stage
**When** attempted
**Then** `TRANSITION_NOT_AVAILABLE` is returned

### Story 18.5.5: Save draft

As an executor,
I want to save partial data without leaving the stage,
So that I can resume later.

**Acceptance Criteria:**

**Given** `PATCH /requests/{id}/draft`
**When** I save as an `EXECUTE` holder on the current stage
**Then** editable fields are validated and saved without changing the stage
**And** required fields are enforced only on the leaving action, unless a rule says otherwise

### Story 18.5.6: Request documents

As an executor,
I want to attach and manage PDF documents on a request,
So that supporting files are captured.

**Acceptance Criteria:**

**Given** the request documents endpoints
**When** I upload a PDF
**Then** it is stored on the private disk with metadata + path in DB, tied to request + field + user + stage; backend validates type/size/extension (PDF-only)

**Given** a document before its field is locked / the stage is left
**When** I delete it
**Then** it is logically deleted and audited; download is via an authorized endpoint only

### Story 18.5.7: Request history & graph

As an authorized user,
I want to see a request's movement and path,
So that I understand its lifecycle.

**Acceptance Criteria:**

**Given** `GET /requests/{id}/history`
**Then** it returns the request's stage movements
**Given** `GET /requests/{id}/graph`
**Then** it returns nodes/edges with executed, current, and possible paths

### Story 18.5.8: Duplicate-invoice compliance

As a reviewer,
I want a warning when an invoice number repeats,
So that I can check for duplication.

**Acceptance Criteria:**

**Given** a request with an invoice number matching another
**When** created or advanced
**Then** the backend surfaces a compliance warning (not an automatic block, unless a business rule is added)
**And** the check uses the indexed `invoice_number` column

### Story 18.5.9: Domain stage hooks (customs/FX-confirmation, financing ledger)

As the system,
I want stage entry/exit hooks to run domain side-effects,
So that customs/FX-confirmation PDFs and the financing ledger keep working under the engine.

**Acceptance Criteria:**

**Given** a stage-hook / action-effect registry (DI-4)
**When** a transition enters/exits a configured stage
**Then** the bound side-effect runs inside the transition's transaction (e.g., ledger capacity check/reserve, customs/FX PDF generation)
**And** the financing ledger reads the Hybrid typed columns (tax/invoice/amount/percentage/status) with the named-lock → row-lock → sum protocol
**And** hook failures roll the transition back atomically

---

## Epic 18.6: Audit & Reports

Append-only audit with full event coverage and filters, phase-1 compliance, and scoped aggregated reports with queued exports.

**FRs covered:** FR-AUD1–4, FR-RPT1–4.

### Story 18.6.1: Audit log & query

As an auditor,
I want a complete append-only audit log,
So that every sensitive action is traceable.

**Acceptance Criteria:**

**Given** the `audit_logs` table
**Then** it stores `actor_user_id`, `actor_role_id`, `event_code`, `entity_type`, `entity_id`, `request_id`, `workflow_instance_id`, `old_values`, `new_values`, `metadata`, `ip_address`, `user_agent`, `correlation_id`, `created_at` and is append-only (no app edit/delete)

**Given** sensitive actions (auth, admin CRUD/deactivate, permission changes, workflow clone/validate/publish, request create/draft/actions, document upload/download/delete, exports)
**When** they occur
**Then** an audit entry is written; `workflow_history` links to audit where possible

**Given** `GET /audit-logs` (+ `/{id}`, `/export`)
**When** filtered by user/role/event/entity/request/date/IP/correlation_id
**Then** scoped, paginated results are returned

### Story 18.6.2: Compliance (phase 1)

As a compliance officer,
I want duplicate/expiry/SLA signals,
So that I can monitor risk.

**Acceptance Criteria:**

**Given** `GET /compliance/duplicate-invoices`
**Then** invoice-number duplicates are listed
**And** expired documents are detected from recorded data
**And** SLA-breach is displayed
**And** no speculative fraud indicators are fabricated

### Story 18.6.3: Aggregated reports

As an authorized user,
I want scoped analytics,
So that I can understand throughput.

**Acceptance Criteria:**

**Given** the report APIs (`summary`, `requests-over-time`, `by-workflow-stage`, `by-bank`, `by-merchant`, `by-sector`, `by-currency`, `stage-duration`, `sla`, `team-performance`)
**When** called
**Then** each applies the user's scope/permissions and the shared filters (date, workflow, version, bank, org, stage, status, currency)
**And** aggregations use indexed Hybrid columns, never unindexed JSON scans

### Story 18.6.4: Report exports & privacy

As an authorized user,
I want to export reports,
So that I can share results.

**Acceptance Criteria:**

**Given** `POST /reports/exports` (+ `/{id}`, `/download`)
**When** I request a large export
**Then** it runs as a queued job using the same filters as the screen

**Given** individual-performance reports
**When** accessed
**Then** they require a separate permission; defaults show team/role performance and never return data outside the user's bank/org scope

---

## Epic 18.7: Screen Permissions & Notifications

Central screen-capability governance replacing role-code gating, plus in-platform notifications.

**FRs covered:** FR-PERM1–4, FR-NOTIF1–4.

### Story 18.7.1: Screen catalog & permission grants

As a system administrator,
I want to grant capabilities per role per screen,
So that access is data-driven, not code-driven.

**Acceptance Criteria:**

**Given** the screen catalog
**Then** every screen (organizations, teams, roles, banks, users, merchants, workflow_designer, requests, reports, audit, reference_data, screen_permissions, notifications, settings) and capabilities (`VIEW, CREATE, UPDATE, DELETE, EXPORT, MANAGE`) are registered

**Given** `GET/PUT /roles/{id}/screen-permissions`, `GET /screens`, `GET /auth/me/permissions`
**When** I grant capabilities to a role
**Then** `screen_permissions(role_id, screen_id, capability)` persists (unique) and gating uses these, never hardcoded role codes
**And** request view/execute still derives from `stage_permissions`

**Given** the last active system administrator
**When** I try to remove their permission-management capability
**Then** it is blocked; the default system admin holds all permissions

### Story 18.7.2: Notification creation & events

As the system,
I want to emit per-user notifications on key events,
So that users are informed in-platform.

**Acceptance Criteria:**

**Given** the `notifications` + `notification_recipients` tables
**When** an event occurs (request reaching an executable stage; approve/reject/return; SLA near/breach; invoice duplicate/compliance; version published; sensitive permission change)
**Then** a notification is created via a queued job after the triggering transaction commits
**And** the audience resolves to actual users at send time (unique `notification_recipients(notification_id, user_id)`)
**And** the channel is in-platform only (phase 1)

### Story 18.7.3: Notification inbox

As a user,
I want to read, archive, and track my notifications,
So that I manage my own alerts.

**Acceptance Criteria:**

**Given** `GET /notifications`, `/unread-count`, `POST /{id}/read|unread|archive`, `/read-all`
**When** I use them
**Then** I act only on my own recipient copy; a shared notification is never deleted
**And** the header bell shows the unread count
