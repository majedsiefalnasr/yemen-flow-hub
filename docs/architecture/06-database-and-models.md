# Database and Models

**Verified:** 2026-07-12, against `backend/database/migrations/` and
`backend/app/Models/` directly (not carried over from the prior version
of this document).

## Coverage status

**This is a core-workflow schema reference, not an exhaustive database
catalog.** It covers the tables the dynamic workflow engine, permission
model, and request lifecycle depend on directly. The following table
families exist in `backend/database/migrations/` but are **not**
documented here: reference data (`reference_tables`, `reference_values`),
`merchants`, notification tables (`notifications`, notification
templates/preferences), report-export tables (`report_exports`,
`report_presets`), `audit_log_archives`/`workflow_history_archives`
(retention-policy archival, mentioned only in passing below),
`screens`/`screen_permissions` (documented instead in
[`03-permission-model.md`](03-permission-model.md) §1), and any
migration-only/data-backfill tables with no ongoing application read
path. Extending this document to cover those families is a candidate for
a future step, not assumed complete by omission.

MySQL. The schema is workflow-oriented: request state is expressed by
which `workflow_stages` row a request currently occupies, not by a fixed
business-status column. For the 4-concept request-state model
(`runtime_status`/`current_stage`/`semantic_role`/`final_outcome`) these
tables implement, see
[`05-request-state-model.md`](05-request-state-model.md). For permission
mechanics on top of the governance tables below, see
[`03-permission-model.md`](03-permission-model.md).

---

## Core tables

### banks

| Field           | Type                                           |
| --------------- | ---------------------------------------------- |
| id              | bigint                                         |
| organization_id | foreignId, nullable                            |
| name            | string, unique                                 |
| code            | string, unique                                 |
| license_number  | string, nullable                               |
| swift_code      | string, nullable, unique                       |
| status          | enum (`ACTIVE`\|`SUSPENDED`), default `ACTIVE` |
| is_active       | boolean                                        |
| version         | unsigned bigint, default 1                     |
| created_at      | timestamp                                      |
| updated_at      | timestamp                                      |

`name` survived an add-then-revert cycle (a migration briefly split it
into `name_ar`/`name_en`, then a later migration reverted to the single
`name` column) — the schema has only ever had `name` as its live state.
`organization_id`, `license_number`, `swift_code`, `status`, and
`version` were added later, alongside `organizations.classification`
(below) — see `Bank::$fillable`.

### users

**`users.role` was dropped** (`2026_07_07_000001_drop_users_role_column.php`)
and no longer exists on this table — it is not merely deprecated. Live
authorization is resolved entirely through the
`organizations`/`teams`/`roles` governance tables below via the
`user_roles`/`user_teams` pivot tables — see
[`03-permission-model.md`](03-permission-model.md).

| Field                     | Type                            |
| ------------------------- | ------------------------------- |
| id                        | bigint                          |
| organization_id           | foreignId, nullable             |
| bank_id                   | foreignId, nullable             |
| name                      | string                          |
| email                     | string, unique                  |
| locale                    | string, nullable, default `ar`  |
| phone                     | string, nullable                |
| password                  | string (hashed)                 |
| pin_code_hash             | string, nullable                |
| pin_enabled               | boolean, default false          |
| must_change_password      | boolean, default false          |
| temporary_password_set_at | timestamp, nullable             |
| password_changed_at       | timestamp, nullable             |
| is_active                 | boolean, default true           |
| user_preferences          | json, nullable                  |
| avatar_variant            | string(20), default `beam`      |
| mfa_enabled               | boolean, default true           |
| totp_secret               | string, nullable                |
| totp_enabled              | boolean, default false          |
| totp_recovery_codes       | json, nullable                  |
| last_login_at             | timestamp, nullable             |
| email_verified_at         | timestamp, nullable             |
| remember_token            | string, nullable (Laravel std.) |
| version                   | unsigned bigint, default 1      |
| created_at                | timestamp                       |
| updated_at                | timestamp                       |

Confirmed dropped, no longer present: `role` (as above); `avatar_color`
and `avatar_colors` (both added then dropped in the same migration
cycle — only `avatar_variant` survives as the live avatar-related
column).

### Governance tables: organizations, teams, roles

The permission model is built on an organization/team/role hierarchy
rather than a single fixed `role` enum.

**organizations** — `id`, `code` (unique), `name`, **`classification`**
(string(40), not-null, indexed — added
`2026_07_06_000001_add_classification_to_organizations_table.php`,
backfilled from `OrganizationClassification`; this is the field
`DataScope::forUser()` reads to decide system-wide vs. own-bank scope,
see [`03-permission-model.md`](03-permission-model.md) §3), `is_system`,
`is_active`, **`version`** (unsigned bigint, default 1), timestamps.

**teams** — `id`, `organization_id` (foreignId), `code`, `name`,
`is_system`, `is_active`, **`version`** (unsigned bigint, default 1),
timestamps. Unique on `(organization_id, code)`.

**roles** — `id`, `organization_id` (foreignId), `code`, `name`,
`is_system`, `is_active`, **`version`** (unsigned bigint, default 1),
timestamps. Unique on `(organization_id, code)`.

**user_roles** (pivot) — `id`, `user_id` (foreignId), `role_id`
(foreignId), **`is_active`** (boolean, default true — added
`2026_07_06_000001_add_is_active_to_user_roles_table.php`, alongside a
data backfill enforcing "exactly one active role per user"), timestamps.
Unique on `(user_id, role_id)`. `User::roles()` exposes
`withPivot('is_active')`; `User::activeRoles()` filters on it — a user
may hold multiple `user_roles` rows, but only the active one counts for
authorization purposes.

**user_teams** (pivot) — `id`, `user_id` (foreignId), `team_id`
(foreignId), timestamps. Unique on `(user_id, team_id)`. **No
`is_active` or equivalent column exists on `user_teams`** — unlike
`user_roles`, team membership has no active/inactive distinction at the
pivot level.

---

## Dynamic workflow engine tables

The workflow is not hardcoded — it is authored as data across these
tables. See [`02-workflow-engine.md`](02-workflow-engine.md) for how the
engine consumes them.

### workflow_definitions

`id`, `code` (unique), `name`, `description` (nullable), `is_active`,
`version`, timestamps.

### workflow_versions

One definition can have multiple versions; only one is normally
`PUBLISHED` at a time.

`id`, `workflow_definition_id` (foreignId), `version_number`, `state`
(`DRAFT`\|`PUBLISHED`\|`ARCHIVED`, indexed, default `DRAFT`),
`published_at` (nullable), `version`, timestamps. Unique on
`(workflow_definition_id, version_number)`.

### workflow_stages

| Field                | Type                          |
| -------------------- | ----------------------------- |
| id                   | bigint                        |
| workflow_version_id  | foreignId                     |
| code                 | string                        |
| name                 | string                        |
| description          | text, nullable                |
| sort_order           | integer                       |
| is_initial           | boolean                       |
| is_final             | boolean                       |
| **semantic_role**    | string(50), nullable          |
| **attached_effects** | json, nullable                |
| **final_outcome**    | string, nullable              |
| sla_duration_minutes | integer, nullable             |
| requires_claim       | boolean                       |
| status               | string (`ACTIVE`\|`INACTIVE`) |
| version              | integer                       |

Unique on `(workflow_version_id, code)`. `requires_claim` marks stages
(e.g. support review) that need the claim/heartbeat/release lifecycle
before another user can also act on the request. **`semantic_role`** and
**`attached_effects`** were added by a later migration
(`2026_07_06_000006_wp4_semantic_columns.php`) — `semantic_role` is the
`StageSemanticRole` value the stage plays (see
[`03-permission-model.md`](03-permission-model.md) and the dynamic-vs-fixed
doc), `attached_effects` is the JSON array of effect codes the stage
fires on entry. **`final_outcome`** was added separately
(`2026_07_06_000002_add_final_outcome_to_workflow_stages_table.php`) and
lives on the terminal stage a request reached — never combined with
`semantic_role` on the same stage row.

### workflow_actions

`id`, `code` (unique), `name`, `kind`
(`DRAFT`\|`APPROVE`\|`REJECT`\|`RETURN`\|`CLOSE`\|`INFO`\|`CUSTOM`),
`is_active`, `is_system`, `version`.

### workflow_transitions

`id`, `workflow_version_id` (foreignId), `from_stage_id` (foreignId),
`action_id` (foreignId), `to_stage_id` (foreignId), `requires_comment`,
`confirmation_message` (nullable), **`is_default_submit`** (boolean,
default false), **`is_self_loop`** (boolean, default false — backfilled
`true` where `from_stage_id = to_stage_id`), **`transition_type`**
(string(20), default `FORWARD`, cast to `WorkflowTransitionType`;
backfilled from the joined action's `kind` — `REJECT`→`REJECT`,
`CLOSE`→`CLOSE`, else `FORWARD`), **`is_destructive`** (boolean, default
false), `version`. The four bolded columns were all added by
`2026_07_06_000004_wp3_designer_validation_columns.php`, after the base
create migration. Unique on `(from_stage_id, action_id)` — a stage can
only wire a given action to one destination stage.

`is_default_submit` is what the frontend draft wizard uses to pick the
submit transition when a stage has multiple outgoing edges (see
[`api-reference.md`](../api-reference.md)'s Execute a Workflow Action
section). `is_self_loop` is the flag `WorkflowPublishRulePack::validateSelfLoops()`
requires be explicitly set on any transition whose `from_stage_id` and
`to_stage_id` match — see
[`../engine/dynamic-vs-fixed.md`](../engine/dynamic-vs-fixed.md).

### stage_permissions

Controls who can VIEW or EXECUTE a stage. Each of
`organization_id`/`team_id`/`role_id`/`user_id` is independently optional
— see [`03-permission-model.md`](03-permission-model.md) for how these
resolve as an identity-set match, not a single foreign key.

`id`, `stage_id` (foreignId), `organization_id`/`team_id`/`role_id`/`user_id`
(all foreignId, nullable), `access_level` (`VIEW`\|`EXECUTE`),
`display_label`, `version`.

### field_groups

Groups related `field_definitions` under one `WorkflowVersion`, for
rendering the dynamic form in sections. Not documented in the prior
version of this file.

`id`, `workflow_version_id` (foreignId), `name`, `label`, `sort_order`
(unsigned int, default 0), `version` (unsigned int, default 1),
timestamps. Index on `(workflow_version_id, sort_order)`.

### stage_field_rules

Per-stage visibility/editability/requiredness override for a
`field_definitions` row.

`id`, `stage_id` (foreignId), `field_id` (foreignId →
`field_definitions`), `is_visible` (boolean, default true), `is_editable`
(boolean, default true), `is_required` (boolean, default false),
`version` (unsigned int, default 1), timestamps. Unique on `(stage_id,
field_id)`.

### field_definitions

Carries the dynamic per-workflow-version field catalog. Full column
list, reconstructed from the create migration plus later additions —
this table has substantially more columns than form-field basics:

`id`, `workflow_version_id` (foreignId), **`field_group_id`** (foreignId
→ `field_groups`), `key`, **`semantic_tag`** (string(50), nullable,
indexed with `workflow_version_id` — added alongside
`workflow_stages.semantic_role` by the same migration; resolved by
`SemanticResolver::fieldForTag()`), `label`, `type` (cast to `FieldType`
— `TEXT`\|`NUMBER`\|`DATE`\|`SELECT`\|`DYNAMIC_SELECT`\|`TEXTAREA`\|`FILE`\|`CURRENCY`\|`CHECKBOX`),
`placeholder` (nullable), `help_text` (nullable), `default_value`
(nullable), `min_value`/`max_value` (decimal(20,4), nullable),
`min_length`/`max_length` (unsigned int, nullable), `regex_pattern`
(nullable), `options` (json, nullable), `reference_table_id` (foreignId,
nullable), `dynamic_source` (nullable, cast to `DynamicFieldSource` —
`MERCHANTS`\|`MERCHANT_COMPANIES`\|`REFERENCE_DATA`), `allowed_file_types`
(json, nullable), `max_file_size` (unsigned int, nullable), `multiple`
(boolean, default false), `is_required`, `is_system`, `sort_order`
(unsigned int, default 0), `version`, timestamps. Unique on
`(workflow_version_id, key)`.

See [`engine/extension-guide.md`](../engine/extension-guide.md) for
adding a new field.

---

## engine_requests

Main workflow table (replaces the legacy `import_requests` table, which
was physically dropped — see below).

| Field                     | Type                                       |
| ------------------------- | ------------------------------------------ |
| id                        | bigint                                     |
| workflow_version_id       | foreignId                                  |
| current_stage_id          | foreignId                                  |
| reference                 | string, unique                             |
| status                    | string(20), plain varchar — see note below |
| created_by                | foreignId                                  |
| claimed_by                | foreignId, nullable                        |
| claimed_at                | timestamp, nullable                        |
| claim_expires_at          | timestamp, nullable                        |
| claim_stage_id            | foreignId, nullable                        |
| stage_entered_at          | timestamp, nullable                        |
| sla_deadline_epoch        | integer, nullable                          |
| bank_id                   | foreignId, nullable                        |
| merchant_id               | foreignId, nullable                        |
| data                      | json, nullable                             |
| version                   | integer (optimistic-concurrency token)     |
| amount                    | decimal, nullable (hybrid projection)      |
| currency                  | string, nullable (hybrid projection)       |
| invoice_number            | string, nullable (hybrid projection)       |
| invoice_number_normalized | string, nullable, indexed                  |
| request_percentage        | decimal, nullable, indexed                 |
| created_at                | timestamp                                  |
| updated_at                | timestamp                                  |

**`status` is a plain `string(20)` column, not a database-level enum.**
Nothing in the schema restricts its values — the constraint is
application-level only, via `App\Support\EngineRequestStatus`, which
defines exactly **5** values: `ACTIVE`, `CLOSED`, `REJECTED`,
`CANCELLED`, `ABANDONED` (matching AGENTS.md's canonical
`runtime_status`). This is the field `EngineRequestResource` maps to the
API-facing `runtime_status` name — see
[`03-permission-model.md`](03-permission-model.md) for the
persistence-vs-API-name distinction. `current_stage_id` (which
`WorkflowStage` the request currently occupies) is what expresses
business-status detail, combined with the stage's `semantic_role` and
`final_outcome` — not a fine-grained status enum on this row.

`amount`, `currency`, `invoice_number`, `invoice_number_normalized`, and
`request_percentage` are "hybrid projection" columns: indexed copies of
values that otherwise live inside the `data` JSON, kept in sync so
reports/filters never need to scan JSON.

`claimed_by`/`claimed_at`/`claim_expires_at`/`claim_stage_id` implement
the claim lifecycle (stages where `requires_claim` is true). The **live**
claim TTL is admin-configurable via `AdminSettingsService`, not a static
config value — see [`03-permission-model.md`](03-permission-model.md) §4.
`stage_entered_at`/`sla_deadline_epoch` back SLA tracking (breached/nearing
computation) independent of the claim lifecycle.

### Indexes on engine_requests

`reference` (unique); `status`; `bank_id`; `merchant_id`; `claimed_by`;
`claim_expires_at`; `amount`/`currency`/`invoice_number` (each
individually indexed); `invoice_number_normalized`; `request_percentage`;
composite `(status, current_stage_id)`; composite `(workflow_version_id,
status)`; composite `(bank_id, created_at, id)`; composite
`(current_stage_id, created_at DESC, id ASC)`; composite
`(current_stage_id, stage_entered_at)`; composite `(current_stage_id,
sla_deadline_epoch, stage_entered_at, id)`. `created_at` has no
standalone index — only inside the composites above.

### engine_request_reference_sequences

`year` (string(4), primary key), `last_value` (unsigned int), timestamps.
Added `2026_07_11` to replace the dropped
`import_request_reference_sequences` table (below), for atomic per-year
reference-number allocation.

---

## engine_request_documents

Stores uploaded request files (replaces the legacy `request_documents`
table, dropped — see below).

Supported: invoices, financial documents, SWIFT documents, external FX
confirmation PDFs. PDF only. **Uploads support controlled versioned
replacement, not blanket immutability** — see
[`api-reference.md`](../api-reference.md)'s "Replace Request Document"
section for the endpoint and
`App\Services\Documents\EngineRequestDocumentReplacementService` for the
mechanism. Deletion (not replacement) is what's stage-gated — see
`status` below.

| Field         | Type                                                   |
| ------------- | ------------------------------------------------------ |
| id            | bigint                                                 |
| request_id    | foreignId                                              |
| field_id      | foreignId, nullable                                    |
| uploaded_by   | foreignId                                              |
| stage_id      | foreignId                                              |
| original_name | string                                                 |
| path          | string, **nullable**                                   |
| mime          | string(50)                                             |
| size          | bigint                                                 |
| checksum      | string(64), nullable                                   |
| scan_status   | (added later)                                          |
| status        | string, cast `DocumentStatus` (`active`\|`superseded`) |
| superseded_by | foreignId, nullable, self-referential (added later)    |
| version       | unsignedInteger                                        |
| deleted_at    | timestamp (soft delete)                                |
| created_at    | timestamp                                              |

`path` was made nullable by a later migration; `scan_status`, `status`,
and `superseded_by` were added by later migrations not present in the
base create. On replacement, the superseded document's `status` becomes
`superseded` and its `superseded_by` points at the new row; the new
document is created with `status: active` and `version` incremented.

---

## workflow_history

Stores per-transition stage-to-stage history (replaces the legacy
`request_stage_history` table, dropped — see below).

`id`, `request_id` (foreignId → `engine_requests`), `from_stage_id`
(foreignId, nullable → `workflow_stages`), `to_stage_id` (foreignId,
**nullable** → `workflow_stages`), `action_code` (nullable),
`performed_by` (foreignId), `comments` (nullable), **`correlation_id`**
(uuid, nullable, indexed), `created_at`.

`to_stage_id` was made nullable by a later migration
(`2026_07_06_000003_make_workflow_history_to_stage_nullable.php`).
`correlation_id` was added by
`2026_06_24_200003_add_correlation_id_to_workflow_history.php` — this is
the UUID shared with the paired `audit_logs` row written by
`EngineTransitionService::execute()` (see
[`03-permission-model.md`](03-permission-model.md) §5). `workflow_history`
itself carries **no role column** — role attribution lives only in the
linked `audit_logs` row.

This table tracks stage-to-stage movement, not a
business-status-to-business-status movement — stage identity is
workflow-version-specific, not a fixed vocabulary.

---

## audit_logs

Stores security and system audit events.

Base columns: `id`, `user_id` (nullable), `user_role` (nullable),
`action`, `subject_type` (nullable), `subject_id` (nullable),
`ip_address` (nullable), `user_agent` (nullable), `metadata` (json,
nullable), `created_at`.

Added later (`2026_06_24_200001_add_engine_columns_to_audit_logs.php`):
`actor_role_id` (foreignId → `roles`, nullable), `workflow_instance_id`
(foreignId → `engine_requests`, nullable), `correlation_id` (string(36),
nullable), `old_values` (json, nullable), `new_values` (json, nullable).

Added later still (`2026_07_09_100005_add_bank_id_to_audit_logs.php`):
`bank_id` (unsigned bigint, nullable).

`user_role` captures the user's role label at the time of the action
(not current role), preserving audit integrity even if roles change;
`actor_role_id` links this to the governance `roles` table.
`subject_type`/`subject_id` identify the audited entity polymorphically
rather than a fixed pair scoped only to requests. There are no dedicated
`from_status`/`to_status` columns; state-change detail is carried in
`old_values`/`new_values` (or `metadata`), while `workflow_history`
(above) is the authoritative stage-to-stage transition log for
`engine_requests`.

**Archive table:** `audit_log_archives` exists (from a later migration)
for retention-policy-driven archival of old audit rows — see the
production/operations docs for the retention policy itself.

---

## customs_declarations

Stores external FX confirmation document information. The table name
remains `customs_declarations` as a legacy compatibility name — new work
must not introduce customs-facing UI copy for this; see AGENTS.md's note
on external FX confirmation terminology.

Base: `id`, `request_id` (legacy nullable FK — **removed by the same
migration that drops the legacy tables below**, see note),
`engine_request_id` (foreignId → `engine_requests`),
`declaration_number` (unique), `issued_by` (**now nullable**), `pdf_path`,
`issued_at`, `created_at`.

Confirmed additional columns beyond the original doc's list: `metadata`
(json), `signed_fx_doc_path`, `signed_fx_doc_uploaded_at`,
`signed_fx_doc_uploaded_by`, `generated_by`, `signed_uploaded_by`.

`engine_request_id` is the current, live foreign key. `request_id`, the
legacy pre-engine FK, was dropped (column, FK, and its unique index) by
`2026_07_01_000001_p5_drop_legacy_import_request_tables.php` — the same
migration that drops the legacy tables in the next section — so it no
longer exists on this table at all, not merely deprecated-but-nullable.

---

## Legacy tables — physically dropped

`2026_07_01_000001_p5_drop_legacy_import_request_tables.php` drops, in
FK-safe order: `request_votes`, `import_requests`, `request_documents`,
`request_stage_history`, and `import_request_reference_sequences`. None
of these tables exist in the current schema. There is no `request_votes`
table, live or otherwise — Executive Voting is out of V1 (see AGENTS.md),
and even the pre-V1-scope-decision vote data model was dropped along with
the rest of the pre-engine schema. Executive review is executed purely as
stage transitions/actions on `engine_requests` through the dynamic
workflow engine, exactly like every other stage.

---

## Database relationships

```text
Bank
 └── Users
 └── Engine Requests

Engine Request (via published WorkflowVersion → WorkflowStage)
 └── Stage Permissions (organization / team / role / user scoped)
 └── Documents
 └── Workflow History (correlation_id-linked to Audit Logs)
 └── Audit Logs
 └── External FX Confirmation
```

---

## Request workflow actors

Requests belong to the bank entity, not individual users.
`engine_requests` does not carry one dedicated `*_by` column per
lifecycle event:

| Field            | Purpose                                                                |
| ---------------- | ---------------------------------------------------------------------- |
| created_by       | Original draft creator                                                 |
| claimed_by       | Current active claimant (e.g. the support reviewer actively reviewing) |
| claimed_at       | When the current claim was acquired                                    |
| claim_expires_at | When the current claim's TTL expires absent a heartbeat                |

Every other workflow actor (submitter, reviewer, rejector, SWIFT
uploader, etc.) is recorded per-transition in
`workflow_history.performed_by`, not as a column on the request row —
auditability comes from replaying the request's transition history
rather than from a fixed set of actor columns.

---

## Request visibility model

Requests belong to the bank organization, but **bank membership alone
does not grant visibility into every request that bank owns.** Two
independent scopes both apply: `DataScope` (organization/bank, via
`EngineRequest::scopeForUser()`) restricts which bank's requests a query
can see at all; a non-`system_admin` user must **additionally** hold
VIEW stage-permission on a request's current stage
(`StagePermissionResolver::accessibleStageIds()`) to see that specific
request — see [`api-reference.md`](../api-reference.md)'s "Request
Visibility Rules" section for the exact composition and
[`03-permission-model.md`](03-permission-model.md) for the underlying
mechanism. Actions remain role/capability-scoped on top of this base
visibility; dashboards remain operationally scoped (see
[`04-dashboard-architecture.md`](04-dashboard-architecture.md)).
