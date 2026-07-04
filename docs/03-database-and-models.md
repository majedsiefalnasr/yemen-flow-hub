# Database and Models

# Database Overview

Yemen Flow Hub uses a workflow-oriented relational database structure.

The database is designed to support:

- Regulatory workflows
- Organization-scoped workflow visibility
- Request lifecycle tracking
- Voting management
- Audit logging
- Secure document handling
- Workflow history

Database engine:

- MySQL

---

# Core Database Tables

# 1. banks

Represents commercial banks.

## Fields

| Field      | Type      |
| ---------- | --------- |
| id         | bigint    |
| name_ar    | string    |
| name_en    | string    |
| code       | string    |
| status     | enum      |
| created_at | timestamp |
| updated_at | timestamp |

---

# 2. users

Represents all system users.

## Fields

| Field           | Type               |
| --------------- | ------------------ |
| id              | bigint             |
| organization_id | foreignId nullable |
| bank_id         | foreignId nullable |
| role            | string             |
| name            | string             |
| email           | string unique      |
| password        | string             |
| is_active       | boolean            |
| last_login_at   | timestamp nullable |
| created_at      | timestamp          |
| updated_at      | timestamp          |

`role` is a legacy single-value column retained for backward compatibility. Live authorization for the dynamic workflow engine is resolved through the `organizations` / `teams` / `roles` governance tables below via the `user_roles` and `user_teams` pivot tables, not this column alone.

---

# 3. Governance Tables: organizations, teams, roles

The dynamic workflow engine's permission model is built on an organization/team/role hierarchy rather than a single fixed `role` enum.

## organizations

| Field     | Type          |
| --------- | ------------- |
| id        | bigint        |
| code      | string unique |
| name      | string        |
| is_system | boolean       |
| is_active | boolean       |

## teams

| Field           | Type      |
| --------------- | --------- |
| id              | bigint    |
| organization_id | foreignId |
| code            | string    |
| name            | string    |
| is_system       | boolean   |
| is_active       | boolean   |

Unique on `(organization_id, code)`.

## roles

| Field           | Type      |
| --------------- | --------- |
| id              | bigint    |
| organization_id | foreignId |
| code            | string    |
| name            | string    |
| is_system       | boolean   |
| is_active       | boolean   |

Unique on `(organization_id, code)`.

## user_roles / user_teams (pivots)

`user_roles` links `users` to `roles` (unique per `user_id`/`role_id` pair); `user_teams` links `users` to `teams` (unique per `user_id`/`team_id` pair). A user may hold multiple roles/teams; these pivots are what `stage_permissions` matches against.

---

# 4. Dynamic Workflow Engine Tables

The workflow is not hardcoded — it is authored as data across these tables.

## workflow_definitions

| Field       | Type          |
| ----------- | ------------- |
| id          | bigint        |
| code        | string unique |
| name        | string        |
| description | text nullable |
| is_active   | boolean       |
| version     | integer       |

## workflow_versions

One definition can have multiple versions; only one is normally `PUBLISHED` at a time.

| Field                   | Type               |
| ----------------------- | ------------------ |
| id                      | bigint             |
| workflow_definition_id  | foreignId          |
| version_number          | integer            |
| state                   | string (`DRAFT` \| `PUBLISHED` \| `ARCHIVED`) |
| published_at            | timestamp nullable |
| version                 | integer            |

Unique on `(workflow_definition_id, version_number)`.

## workflow_stages

| Field                 | Type          |
| --------------------- | ------------- |
| id                    | bigint        |
| workflow_version_id   | foreignId     |
| code                  | string        |
| name                  | string        |
| description           | text nullable |
| sort_order            | integer       |
| is_initial            | boolean       |
| is_final              | boolean       |
| sla_duration_minutes  | integer nullable |
| requires_claim        | boolean       |
| status                | string (`ACTIVE` \| `INACTIVE`) |
| version               | integer       |

Unique on `(workflow_version_id, code)`. `requires_claim` marks stages (e.g. support review) that need the claim/heartbeat/release lifecycle before another user can also act on the request.

## workflow_actions

| Field     | Type          |
| --------- | ------------- |
| id        | bigint        |
| code      | string unique |
| name      | string        |
| kind      | string (`DRAFT`\|`APPROVE`\|`REJECT`\|`RETURN`\|`CLOSE`\|`INFO`\|`CUSTOM`) |
| is_active | boolean       |
| is_system | boolean       |
| version   | integer       |

## workflow_transitions

| Field                 | Type          |
| --------------------- | ------------- |
| id                    | bigint        |
| workflow_version_id   | foreignId     |
| from_stage_id         | foreignId     |
| action_id             | foreignId     |
| to_stage_id           | foreignId     |
| requires_comment      | boolean       |
| confirmation_message  | string nullable |
| version               | integer       |

Unique on `(from_stage_id, action_id)` — a stage can only wire a given action to one destination stage.

## stage_permissions

Controls who can view or execute a stage. At least one of `organization_id`/`team_id`/`role_id`/`user_id` is set to scope the grant.

| Field           | Type               |
| --------------- | ------------------ |
| id              | bigint             |
| stage_id        | foreignId          |
| organization_id | foreignId nullable |
| team_id         | foreignId nullable |
| role_id         | foreignId nullable |
| user_id         | foreignId nullable |
| access_level    | string (`VIEW` \| `EXECUTE`) |
| display_label   | string             |
| version         | integer            |

---

# 5. engine_requests

Main workflow table (replaces the legacy `import_requests` table, which has been physically dropped from the schema).

Stores financing requests and their position in a published `WorkflowVersion`.

## Fields

| Field                | Type               |
| -------------------- | ------------------ |
| id                   | bigint             |
| workflow_version_id  | foreignId          |
| current_stage_id     | foreignId          |
| reference            | string unique      |
| status               | string (`ACTIVE` \| `CLOSED` \| `REJECTED`, default `ACTIVE`) |
| created_by           | foreignId          |
| claimed_by           | foreignId nullable |
| claimed_at           | timestamp nullable |
| claim_expires_at     | timestamp nullable |
| bank_id              | foreignId nullable |
| merchant_id          | foreignId nullable |
| data                 | json nullable      |
| version              | integer            |
| amount               | decimal nullable   |
| currency             | string nullable    |
| invoice_number       | string nullable    |
| request_percentage   | decimal nullable   |
| created_at           | timestamp          |
| updated_at           | timestamp          |

`status` here is a coarse lifecycle flag (`ACTIVE`/`CLOSED`/`REJECTED`), not the fine-grained business status enum in `docs/01-workflow-and-business-rules.md`. The request's position in the business-rules lifecycle is expressed by `current_stage_id` (which `WorkflowStage` the request currently occupies) combined with its dynamic `data`. `amount`, `currency`, `invoice_number`, and `request_percentage` are "hybrid projection" columns: indexed copies of values that otherwise live inside the `data` JSON, kept in sync by `RequestProjectionSync` so reports/filters never need to scan JSON.

`claimed_by`/`claimed_at`/`claim_expires_at` implement the claim lifecycle (used by stages where `requires_claim` is true, e.g. support review) — this replaces the older `support_claimed_by`/`support_claimed_at` fields once modeled directly on the request row.

---

# 6. engine_request_documents

Stores uploaded request files (replaces the legacy `request_documents` table, which has been physically dropped from the schema).

## Supported Documents

- Invoices
- Financial documents
- SWIFT documents
- External FX confirmation PDFs

## File Rules

- PDF only
- Immutable uploads
- SWIFT documents cannot be replaced

## Fields

| Field         | Type                  |
| ------------- | --------------------- |
| id            | bigint                |
| request_id    | foreignId             |
| field_id      | foreignId nullable    |
| uploaded_by   | foreignId             |
| stage_id      | foreignId             |
| original_name | string                |
| path          | string                |
| mime          | string(50)            |
| size          | bigint                |
| checksum      | string(64) nullable   |
| version       | unsignedInteger       |
| deleted_at    | timestamp (soft delete) |
| created_at    | timestamp             |

---

# Voting Rules

- One vote per executive member
- Votes cannot change after voting session closure
- Executive rejection is terminal
- Director vote resolves ties
- AUTO_ABSTAIN_TIMEOUT differs from manual abstain

Vote types:

- APPROVE
- REJECT
- ABSTAIN
- AUTO_ABSTAIN_TIMEOUT

There is no dedicated `request_votes` table in the current schema (the legacy `request_votes` table has been physically dropped). Executive voting is executed as stage transitions/actions on `engine_requests` through the dynamic workflow engine; see `docs/01-workflow-and-business-rules.md` for the full voting business rules.

---

# 7. workflow_history

Stores workflow transition history (replaces the legacy `request_stage_history` table, which has been physically dropped from the schema).

## Purpose

Tracks all workflow movement for an `engine_requests` row.

## Fields

| Field        | Type          |
| ------------ | ------------- |
| id           | bigint        |
| request_id   | foreignId (→ engine_requests) |
| from_stage_id| foreignId nullable (→ workflow_stages) |
| to_stage_id  | foreignId (→ workflow_stages) |
| action_code  | string nullable |
| performed_by | foreignId     |
| comments     | text nullable |
| created_at   | timestamp     |

Note: this table tracks stage-to-stage movement (`from_stage_id`/`to_stage_id`), not the business-status-to-business-status movement (`from_status`/`to_status`) an earlier design described — stage identity is workflow-version-specific.

---

# 8. audit_logs

Stores security and system audit events.

## Fields

| Field                  | Type               |
| ---------------------- | ------------------ |
| id                     | bigint             |
| user_id                | foreignId nullable |
| user_role              | string nullable    |
| actor_role_id          | foreignId nullable (→ roles) |
| action                 | string             |
| subject_type           | string nullable    |
| subject_id             | bigint nullable    |
| workflow_instance_id   | foreignId nullable (→ engine_requests) |
| correlation_id         | string nullable    |
| ip_address             | string nullable    |
| user_agent             | string nullable    |
| metadata               | json nullable      |
| old_values             | json nullable      |
| new_values             | json nullable      |
| created_at             | timestamp          |

Note: `user_role` captures the user's role label at the time of the action (not current role), preserving audit integrity even if roles change; `actor_role_id` links this to the governance `roles` table. `subject_type`/`subject_id` identify the audited entity polymorphically (an `EngineRequest`, a `WorkflowVersion`, a user, etc.) rather than a fixed `entity_type`/`entity_id` pair scoped only to requests. There are no dedicated `from_status`/`to_status` columns; state-change detail for a given action is carried in `old_values`/`new_values` (or `metadata`), while `workflow_history` (above) is the authoritative stage-to-stage transition log for `engine_requests`.

---

# 9. customs_declarations

Stores external FX confirmation document information. The table name remains `customs_declarations` as a legacy compatibility name.

## Fields

| Field              | Type               |
| ------------------ | ------------------ |
| id                 | bigint             |
| request_id         | foreignId nullable |
| engine_request_id  | foreignId nullable (→ engine_requests) |
| declaration_number | string unique      |
| issued_by          | foreignId          |
| pdf_path           | string             |
| issued_at          | timestamp          |
| created_at         | timestamp          |

`request_id` is the legacy foreign key (now nullable, kept only because pre-engine rows still reference it). `engine_request_id` is the current foreign key used for declarations tied to `engine_requests`; exactly one of the two is populated per row.

---

# Database Relationships

# Main Relationships

```text
Bank
 └── Users
 └── Engine Requests

Engine Request (via published WorkflowVersion → WorkflowStage)
 └── Stage Permissions (organization / team / role / user scoped)
 └── Documents
 └── Workflow History
 └── Audit Logs
 └── External FX Confirmation
```

---

# Workflow Status Enum

# Request Statuses

```text
DRAFT
DRAFT_REJECTED_INTERNAL
SUBMITTED
BANK_REVIEW
BANK_APPROVED
SUPPORT_REVIEW_PENDING
SUPPORT_REVIEW_IN_PROGRESS
SUPPORT_APPROVED
SUPPORT_REJECTED
WAITING_FOR_SWIFT
SWIFT_UPLOADED
WAITING_FOR_VOTING_OPEN
EXECUTIVE_VOTING_OPEN
EXECUTIVE_VOTING_CLOSED
EXECUTIVE_APPROVED
EXECUTIVE_REJECTED
CUSTOMS_DECLARATION_ISSUED
COMPLETED
```

---

# User Roles Enum

```text
DATA_ENTRY
BANK_REVIEWER
BANK_ADMIN
SWIFT_OFFICER
SUPPORT_COMMITTEE
EXECUTIVE_MEMBER
COMMITTEE_DIRECTOR
CBY_ADMIN
```

## Role Hierarchy Notes

- `COMMITTEE_DIRECTOR` inherits all `EXECUTIVE_MEMBER` permissions plus director-specific actions (open/close voting sessions, resolve ties, finalize decisions, issue external FX confirmation documents).
- A user cannot simultaneously hold `COMMITTEE_DIRECTOR` and `EXECUTIVE_MEMBER`. The director role is the sole executive role for that user.
- `bank_id` is `NULL` for all CBY roles (`SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, `CBY_ADMIN`).
- `bank_id` is required (non-null) for all bank roles (`DATA_ENTRY`, `BANK_REVIEWER`, `BANK_ADMIN`, `SWIFT_OFFICER`).

---

# Vote Types Enum

```text
APPROVE
REJECT
ABSTAIN
AUTO_ABSTAIN_TIMEOUT
```

# Voting Session Status Enum

```text
WAITING_FOR_VOTING_OPEN
EXECUTIVE_VOTING_OPEN
EXECUTIVE_VOTING_CLOSED
```

## How Executive Voting Phase Is Tracked

There is no dedicated `current_status`/`voting_session_status` pair of columns on `engine_requests` — that denormalized-cache design predates the dynamic engine. Instead:

- `engine_requests.current_stage_id` points at the `WorkflowStage` the request currently occupies within its published `WorkflowVersion` (e.g. the stage corresponding to `EXECUTIVE_VOTING_OPEN` for the seeded Import Financing workflow).
- `engine_requests.status` is a coarse lifecycle flag (`ACTIVE`/`CLOSED`/`REJECTED`) unrelated to the business-status enum above; it does not distinguish voting sub-states.
- The business-status labels in the "Request Statuses" enum above (including the voting-phase values) describe which `WorkflowStage` a request is in, not a literal column value stored on the request row.
- `EngineTransitionService::execute()` is the single mechanism that advances `current_stage_id` for every transition, including opening/closing a voting stage — there is no separate `WorkflowService`/`VotingService` pair keeping two columns in sync.

---

# Currency Enum

```text
USD
EUR
SAR
```

---

# Request Workflow Actors

Requests belong to the bank entity, NOT individual users.

`engine_requests` does not carry one dedicated `*_by` column per lifecycle event the way the legacy `import_requests` table did. Instead:

| Field            | Purpose                                                                 |
| ---------------- | ------------------------------------------------------------------------ |
| created_by        | Original draft creator                                                  |
| claimed_by        | Current active claimant (e.g. the support reviewer actively reviewing)   |
| claimed_at        | When the current claim was acquired                                     |
| claim_expires_at  | When the current claim's TTL expires absent a heartbeat                 |

Every other workflow actor (submitter, reviewer, rejector, SWIFT uploader, voting session opener/closer, etc.) is recorded per-transition in `workflow_history.performed_by`, not as a column on the request row — auditability comes from replaying the request's transition history rather than from a fixed set of actor columns.

---

# Request Visibility Model

The platform uses organization-scoped workflow visibility.

Requests belong to the bank organization.

All users inside the same bank can view all bank requests.

However:

- Actions remain role-scoped
- Dashboards remain operationally scoped
- Queue visibility remains workflow-specific

The system is designed as an institutional workflow platform, NOT a shared admin dashboard.

---

# Visibility Rules By Role

## DATA_ENTRY

Can access:

- All requests belonging to their bank

Dashboard focus:

- Drafts
- Returned requests
- Submitted requests
- Rejected requests
- Completed requests

Should receive simplified business statuses.

---

## BANK_REVIEWER

Can access:

- All requests belonging to their bank

Can monitor:

- Support review progress
- SWIFT upload status
- Executive workflow progress
- Final decisions

---

## BANK_ADMIN

Can access:

- All users in their own bank
- All requests belonging to their bank
- Bank-level operational dashboard data

Can manage:

- Create/update/activate/deactivate users in own bank only
- Assign only bank-scoped manageable roles (`DATA_ENTRY`, `BANK_REVIEWER`)

Restrictions:

- Cannot assign CBY roles
- Cannot manage users outside own bank
- Cannot execute workflow decisions or governance overrides

---

## SWIFT_OFFICER

Can access:

- Requests waiting for SWIFT upload
- Requests belonging to their bank

---

## SUPPORT_COMMITTEE

Can access:

- Support review queues
- Claimed reviews
- Support workflow requests

Support users can see active reviewers.

---

## EXECUTIVE_MEMBER

Can access:

- Executive voting queues
- Finalized executive requests

---

## COMMITTEE_DIRECTOR

Can:

- Open voting sessions
- Close voting sessions
- Finalize voting
- Issue external FX confirmation documents

---

## CBY_ADMIN

Has full system visibility.

---

# Workflow History Rules

Each workflow transition must create:

- Stage history record
- Audit log record
- Voting session events
- Claim lifecycle events
- Workflow action records

This ensures:

- Full traceability
- Regulatory compliance
- Immutable workflow history
- Governance transparency

---

# Future Scalability Notes

The database structure should support future features including:

- Notifications
- Workflow SLA tracking
- Multi-language support
- External integrations
- S3 object storage
- AML integrations
- Reporting and analytics

---

# Technical Recommendations

## Indexes on `engine_requests`

- reference (unique)
- status
- bank_id
- merchant_id
- claimed_by
- claim_expires_at
- amount, currency, invoice_number (hybrid projection columns, indexed for reporting/filtering)
- composite: (status, current_stage_id)
- composite: (workflow_version_id, status)
- created_at

---

# Recommended Backend Architecture

The backend should use:

- Laravel Eloquent Models
- Service classes (`EngineTransitionService`, `WorkflowDesignerService`, `EngineClaimService`, and related `Services/Workflow/` classes — see `docs/02-system-architecture.md`)
- Enums for statuses and roles
- Policy-based authorization
- Centralized audit logging

---

# Important Design Principle

The database is workflow-driven, not CRUD-driven.

The most important concepts are:

- State transitions
- Organizational workflow governance
- Auditability
- Request lifecycle tracking
- Controlled approvals
- Organization-scoped visibility
- Queue-based operational access
