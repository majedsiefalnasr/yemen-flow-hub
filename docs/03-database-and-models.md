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

| Field         | Type               |
| ------------- | ------------------ |
| id            | bigint             |
| bank_id       | foreignId nullable |
| role          | enum               |
| name          | string             |
| email         | string unique      |
| password      | string             |
| is_active     | boolean            |
| last_login_at | timestamp nullable |
| created_at    | timestamp          |
| updated_at    | timestamp          |

---

# 3. import_requests

Main workflow table.

Stores financing requests and current workflow state.

## Fields

| Field                  | Type               |
| ---------------------- | ------------------ |
| id                     | bigint             |
| request_number         | string unique      |
| bank_id                | foreignId          |
| current_status         | enum               |
| currency               | enum               |
| amount                 | decimal            |
| supplier_name          | string             |
| goods_description      | text               |
| port_of_entry          | string             |
| notes                  | text nullable      |
| swift_uploaded_at      | timestamp nullable |
| final_decision_at      | timestamp nullable |
| customs_declaration_id | foreignId nullable |
| created_by             | foreignId          |
| last_updated_by        | foreignId nullable |
| submitted_by           | foreignId nullable |
| reviewed_by            | foreignId nullable |
| rejected_by            | foreignId nullable |
| resubmitted_by         | foreignId nullable |
| support_reviewed_by    | foreignId nullable |
| support_claimed_by     | foreignId nullable |
| support_claimed_at     | timestamp nullable |
| swift_uploaded_by      | foreignId nullable |
| voting_opened_by       | foreignId nullable |
| voting_opened_at       | timestamp nullable |
| voting_closed_by       | foreignId nullable |
| voting_closed_at       | timestamp nullable |
| voting_session_status  | enum nullable      |
| created_at             | timestamp          |
| updated_at             | timestamp          |

---

# 4. request_documents

Stores uploaded request files.

## Supported Documents

- Invoices
- Financial documents
- SWIFT documents
- Customs declaration PDFs

## File Rules

- PDF only
- Immutable uploads
- SWIFT documents cannot be replaced

## Fields

| Field         | Type            |
| ------------- | --------------- |
| id            | bigint          |
| request_id    | foreignId       |
| uploaded_by   | foreignId       |
| document_type | enum            |
| original_name | string          |
| file_name     | string          |
| mime_type     | string          |
| file_size     | bigint          |
| storage_path  | string          |
| checksum      | string nullable |
| created_at    | timestamp       |

---

# 5. request_votes

Stores executive committee votes.

## Fields

| Field         | Type          |
| ------------- | ------------- |
| id            | bigint        |
| request_id    | foreignId     |
| user_id       | foreignId     |
| vote          | enum          |
| vote_source   | enum          |
| justification | text nullable |
| voted_at      | timestamp     |
| created_at    | timestamp     |

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

---

# 6. request_stage_history

Stores workflow transition history.

## Purpose

Tracks all workflow movement.

## Fields

| Field        | Type          |
| ------------ | ------------- |
| id           | bigint        |
| request_id   | foreignId     |
| from_status  | enum nullable |
| to_status    | enum          |
| action       | string        |
| performed_by | foreignId     |
| notes        | text nullable |
| created_at   | timestamp     |

---

# 7. audit_logs

Stores security and system audit events.

## Fields

| Field         | Type               |
| ------------- | ------------------ |
| id            | bigint             |
| user_id       | foreignId nullable |
| role          | enum nullable      |
| action        | string             |
| entity_type   | string             |
| entity_id     | bigint             |
| from_status   | enum nullable      |
| to_status     | enum nullable      |
| metadata      | json nullable      |
| created_at    | timestamp          |

Note: `role` captures the user's role at the time of the action (not current role), preserving audit integrity even if roles change. `from_status` and `to_status` record workflow state changes; they are null for non-transition events (e.g., login, file upload). The `request_stage_history` table tracks workflow transitions in detail; `audit_logs` provides the broader security and compliance audit trail.

---

# 8. customs_declarations

Stores customs declaration information.

## Fields

| Field              | Type          |
| ------------------ | ------------- |
| id                 | bigint        |
| request_id         | foreignId     |
| declaration_number | string unique |
| issued_by          | foreignId     |
| pdf_path           | string        |
| issued_at          | timestamp     |
| created_at         | timestamp     |

---

# Database Relationships

# Main Relationships

```text
Bank
 └── Users
 └── Import Requests

Import Request
 └── Organizational Workflow Ownership
 └── Documents
 └── Votes
 └── Stage History
 └── Audit Logs
 └── Customs Declaration
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
SWIFT_OFFICER
SUPPORT_COMMITTEE
EXECUTIVE_MEMBER
COMMITTEE_DIRECTOR
CBY_ADMIN
```

## Role Hierarchy Notes

- `COMMITTEE_DIRECTOR` inherits all `EXECUTIVE_MEMBER` permissions plus director-specific actions (open/close voting sessions, resolve ties, finalize decisions, issue customs declarations).
- A user cannot simultaneously hold `COMMITTEE_DIRECTOR` and `EXECUTIVE_MEMBER`. The director role is the sole executive role for that user.
- `bank_id` is `NULL` for all CBY roles (`SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, `CBY_ADMIN`).
- `bank_id` is required (non-null) for all bank roles (`DATA_ENTRY`, `BANK_REVIEWER`, `SWIFT_OFFICER`).

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

## Relationship Between `current_status` and `voting_session_status`

`voting_session_status` is a **denormalized sub-state cache** for the executive voting phase only.

- When `current_status` is in the executive voting range (`WAITING_FOR_VOTING_OPEN`, `EXECUTIVE_VOTING_OPEN`, `EXECUTIVE_VOTING_CLOSED`), `voting_session_status` mirrors `current_status` for fast queue filtering without joining.
- Outside the executive voting phase, `voting_session_status` is `NULL`.
- `current_status` is always the authoritative source of truth.
- `voting_session_status` must be kept in sync by `WorkflowService` and `VotingService` during every voting-phase transition.

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

Workflow actor fields exist for auditability only.

| Field               | Purpose                               |
| ------------------- | ------------------------------------- |
| created_by          | Original draft creator                |
| last_updated_by     | Last draft editor                     |
| submitted_by        | Submitted request for internal review |
| reviewed_by         | Internal bank reviewer                |
| rejected_by         | Last rejecting workflow actor         |
| resubmitted_by      | Last user who resubmitted request     |
| support_claimed_by  | Active support reviewer               |
| support_claimed_at  | Active claim timestamp                |
| support_reviewed_by | Final support reviewer                |
| swift_uploaded_by   | SWIFT uploader                        |
| voting_opened_by    | Voting session opener                 |
| voting_closed_by    | Voting session closer                 |

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
- Issue customs declarations

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

## Recommended Indexes

- request_number
- current_status
- support_claimed_by
- support_claimed_at
- bank_id
- voting_session_status
- voting_opened_at
- voting_closed_at
- voted_at
- created_at

---

# Recommended Backend Architecture

The backend should use:

- Laravel Eloquent Models
- Service classes
- Enums for statuses and roles
- Workflow service
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
