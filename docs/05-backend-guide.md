# Backend Guide

# Backend Overview

The backend of Yemen Flow Hub is responsible for the complete workflow engine and regulatory business logic.

The backend controls:

- Workflow transitions
- Permissions and authorization
- Request lifecycle
- Voting logic
- Audit logging
- File management
- Notifications
- Organizational visibility
- Role-scoped actions
- Queue-based operational workflows
- Voting session governance
- Security validation

The backend is the source of truth for all permissions and workflow rules.

---

# Backend Stack

## Core Stack

- Laravel 11
- PHP 8.3+
- MySQL
- Redis
- Laravel Sanctum
- Queue Workers
- REST API

---

# Backend Architecture Principles

# Workflow-First Architecture

The backend is not CRUD-oriented.

The system is built around:

- Workflow transitions
- Organizational workflow ownership
- Request locking
- Approval hierarchy
- Role-scoped operational access
- Queue visibility
- Auditability

---

# Service-Oriented Architecture

Business logic must not exist inside:

- Controllers
- Models
- Routes

Business logic should exist inside:

- Services
- Actions
- Policies
- Workflow handlers

---

# Suggested Backend Structure

```text
backend/
├── app/
│   ├── Actions/
│   ├── DTOs/
│   ├── Enums/
│   ├── Events/
│   ├── Exceptions/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   │
│   ├── Jobs/
│   ├── Listeners/
│   ├── Models/
│   ├── Notifications/
│   ├── Policies/
│   ├── Services/
│   │   ├── Workflow/
│   │   ├── Voting/
│   │   ├── Audit/
│   │   ├── Documents/
│   │   └── Notifications/
│   │
│   └── Support/
│
├── routes/
├── database/
├── storage/
└── config/
```

---

# Core Backend Services

# 1. Workflow Service

The Workflow Service is the core of the platform.

Responsible for:

- Request transitions
- Stage validation
- Status locking
- Permission checks
- Support review claiming
- Single reviewer locking
- Workflow history
- Queue assignment
- Organizational visibility handling
- Voting session lifecycle
- Executive governance enforcement
- Immutable workflow enforcement

---

# Workflow Responsibilities

The workflow engine must validate:

- Current status
- User role
- Allowed action
- Transition rules
- Editing restrictions
- Voting state
- Support review claiming
- Single reviewer locking
- Organizational access validation
- Role-scoped action validation
- Voting session validation
- Final rejection validation
- Queue visibility validation

---

# Example Workflow Transition

```php
$workflow->transition(
    request: $request,
    action: 'support_approve',
    user: auth()->user()
);
```

---

# Support Review Claiming

Support review claiming uses a temporary soft-lock model.

The workflow engine must:

- Temporarily lock requests while actively reviewed
- Automatically release claims on disconnect or timeout
- Prevent simultaneous active reviews
- Preserve queue visibility for all support users

Claiming must be implemented atomically.

---

# Claim Rules

- Only one support reviewer can actively review a request at a time
- Claims are temporary and presence-based
- Claims auto-release on disconnect or timeout
- Support queues remain visible to all support users
- Support users can see who is actively reviewing a request

---

# Claim Validation

The workflow engine must validate:

- Request is in SUPPORT_REVIEW_PENDING state
- Request is not already claimed
- User has SUPPORT_COMMITTEE role

---

# Claim Result

While actively reviewing:

- current_status becomes SUPPORT_REVIEW_IN_PROGRESS
- support_claimed_by is assigned
- support_claimed_at is recorded

When claim releases:

- support_claimed_by becomes null
- support_claimed_at becomes null
- request returns to SUPPORT_REVIEW_PENDING if unfinished

---

# Request Visibility Rules

The backend enforces organization-scoped visibility with role-scoped actions.

The system must NEVER expose requests outside the user's organization scope.

Visibility enforcement must happen at:

- Query level
- API level
- Policy level
- Service level

---

# Visibility Scope By Role

## DATA_ENTRY

Can access:

- All requests belonging to their bank

Dashboard focus:

- Drafts
- Submitted requests
- Returned requests
- Rejected requests
- Completed requests

Should NOT receive detailed CBY operational workflow stages.

---

## BANK_REVIEWER

Can access:

- All requests belonging to their bank

Can monitor:

- Support review progress
- SWIFT upload progress
- Executive voting progress
- Customs declaration issuance

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
- Finalized executive voting requests

---

## COMMITTEE_DIRECTOR

Can:

- Open voting sessions
- Close voting sessions
- Finalize executive decisions
- Issue customs declarations

---

## CBY_ADMIN

Has full system visibility.

---

# 2. Voting Service

Responsible for:

- Vote creation
- Vote validation
- Majority calculation
- Tie handling
- Final decision logic

---

# Voting Rules

## Vote Types

- APPROVE
- REJECT
- ABSTAIN
- AUTO_ABSTAIN_TIMEOUT

---

## Voting Session Rules

- Voting sessions are controlled by the Executive Committee Director
- No minimum quorum exists
- Director can close voting at any time
- Any member who did not vote before closure becomes AUTO_ABSTAIN_TIMEOUT
- AUTO_ABSTAIN_TIMEOUT is different from manual abstain
- Director also votes as a regular member
- Director vote resolves ties

---

# Voting Restrictions

- One vote per member
- No duplicate voting
- No vote modification after finalization
- Voting only during executive voting stage
- Rejected requests remain permanently locked

---

# 3. Audit Service

Responsible for:

- Audit logging
- Workflow history
- Security tracking
- Action tracking

---

# Audit Events

Every important action must generate audit logs.

Examples:

- Login
- Logout
- Request creation
- Request update
- Approval
- Rejection
- Voting
- File upload
- Status transitions
- Customs declaration generation

---

# 4. Document Service

Responsible for:

- File uploads
- File validation
- Secure storage
- File downloads
- SWIFT document handling

---

# File Rules

Allowed file types:

- PDF

Restrictions:

- Private storage only
- Size validation required
- Uploaded PDFs are immutable
- SWIFT PDFs cannot be replaced after upload

---

# Authentication

# Authentication Method

Using:

- Laravel Sanctum
- Secure session authentication
- HTTP-only cookies

---

# Authentication Responsibilities

Backend handles:

- Login
- Logout
- Session validation
- Token validation
- Role hydration
- Session expiration

---

# Authorization

# Authorization Strategy

Authorization is role-based and workflow-aware.

Permissions depend on:

- User role
- Current workflow stage
- Organization scope
- Role-scoped operational permissions
- Request status
- Workflow queue relevance

---

# Main Roles

```text
DATA_ENTRY
BANK_REVIEWER
SWIFT_OFFICER
SUPPORT_COMMITTEE
EXECUTIVE_MEMBER
COMMITTEE_DIRECTOR
CBY_ADMIN
```

---

# Policy Rules

Policies should validate:

- Can view request
- Can edit request
- Can approve request
- Can reject request
- Can return request to Data Entry after support rejection
- Can finalize rejection after support rejection
- Can upload SWIFT
- Can vote
- Can issue customs declaration
- Can access queue
- Can access request visibility scope

---

# Request Lifecycle Logic

# Editable States

Requests can only be edited before internal bank approval.

Editable states:

- DRAFT
- DRAFT_REJECTED_INTERNAL

If the internal reviewer rejects the request before approval:

- The request returns to Data Entry
- Data Entry can modify and resubmit

## SUBMITTED → BANK_REVIEW Transition

When a Bank Reviewer opens a submitted request to begin active review, the status transitions from `SUBMITTED` to `BANK_REVIEW`. This is analogous to `SUPPORT_REVIEW_IN_PROGRESS` for the support stage. The Bank Reviewer then approves (→ `BANK_APPROVED`) or rejects (→ `DRAFT_REJECTED_INTERNAL`). `SUBMITTED` is the queue state; `BANK_REVIEW` is the active-review state.

---

# Locked States

Requests become permanently read-only immediately after internal bank approval.

Locked stages include:

- BANK_APPROVED
- SUPPORT_REVIEW_PENDING
- SUPPORT_REVIEW_IN_PROGRESS
- SUPPORT_APPROVED
- SUPPORT_REJECTED
- WAITING_FOR_SWIFT
- SWIFT_UPLOADED
- WAITING_FOR_VOTING_OPEN
- EXECUTIVE_VOTING_OPEN
- EXECUTIVE_VOTING_CLOSED
- EXECUTIVE_APPROVED
- EXECUTIVE_REJECTED
- CUSTOMS_DECLARATION_ISSUED
- COMPLETED

---

# Rejection Logic

# Internal Reviewer Rejection

Before internal approval:

- Request returns to Data Entry
- Data Entry can modify and resubmit

---

# Support Committee Rejection

After support rejection:

- Request returns to Bank Reviewer
- Bank Reviewer can keep rejection final
- Bank Reviewer can return request to Data Entry
- Returned requests become editable again

---

# Executive Committee Rejection

Executive rejection is terminal.

After executive rejection:

- Request becomes permanently locked
- Request cannot be reopened
- Request cannot be resubmitted
- Admins cannot override rejection

---

# Workflow History

Each workflow transition must create:

- request_stage_history record
- audit_logs record
- voting session events
- claim lifecycle events

---

# Notifications Architecture

# Notification Types

The system should support:

- Approval notifications
- Rejection notifications
- Returned request notifications
- Voting notifications
- SWIFT upload notifications
- Voting session opened
- Voting session closed
- Customs declaration issued

---

# Queue System

Redis queues should handle:

- Notifications
- PDF generation
- Audit exports
- Future integrations

---

# API Architecture

# API Style

REST API architecture.

---

# API Responsibilities

Controllers should:

- Receive request
- Validate input
- Call services
- Handle workflow claim actions
- Apply ownership filtering
- Apply queue-scoped filtering
- Return response

Controllers should NOT contain workflow logic.

---

# Suggested API Structure

```text
/api/auth
/api/requests
/api/workflow
/api/support-review
/api/voting
/api/documents
/api/customs
/api/audit
```

---

# Validation Strategy

Use Laravel Form Requests for:

- Request validation
- File validation
- Voting validation
- Workflow action validation

---

# Scoped Query Architecture

The backend must use scoped query services.

Examples:

## Data Entry Queries

Must return:

- All bank requests

Default dashboard filters should focus on:

- Drafts
- Submitted requests
- Returned requests
- Rejected requests
- Completed requests

---

## Bank Reviewer Queries

Must return:

- Bank review queue
- Bank workflow tracking requests

---

## Support Committee Queries

Must return:

- Support review pending queue
- Claimed reviews
- Recently processed support requests

---

## Executive Queries

Must return:

- Executive voting queue
- Finalized executive decisions

---

# Error Handling

The backend should return consistent API responses.

Suggested format:

```json
{
  "success": false,
  "message": "Unauthorized action",
  "errors": {}
}
```

---

# File Storage

# Storage Strategy

Documents should be stored using:

- Private storage
- Structured folders
- Unique file names

---

# Suggested File Structure

```text
/storage/requests/{request_id}/
/storage/swift/{request_id}/
/storage/customs/{request_id}/
```

---

# Security Rules

# Required Security Features

- Role-based authorization
- Workflow validation
- Private file access
- Audit logging
- Secure authentication
- Validation on every action
- Login rate limiting (maximum 5 attempts per minute per IP)
- API rate limiting per authenticated user
- CSRF token validation (enforced by Sanctum SPA mode)
- Session fixation protection on login
- Audit logging of failed authorization attempts (role mismatch, wrong workflow state)

---

# Immutable Workflow State Enforcement

The `ImportRequest` model must guard against direct status mutation.

Implementation requirement:

The `current_status` attribute must only be changed through `WorkflowService::transition()`.

Any attempt to set `current_status` directly on the model outside of `WorkflowService` must throw a `DirectStatusMutationException`.

This can be enforced by overriding `setAttribute()` in the model:

```php
public function setAttribute($key, $value): static
{
    if ($key === 'current_status' && ! app()->bound('workflow.transition.active')) {
        throw new DirectStatusMutationException(
            'current_status must only be changed via WorkflowService::transition()'
        );
    }
    return parent::setAttribute($key, $value);
}
```

`WorkflowService` must bind `workflow.transition.active` during transitions and release it after.

---

# Support Claim Timeout

Support review claims have a TTL of **15 minutes** of inactivity.

The claim is extended by a heartbeat ping every 60 seconds while the reviewer is on the request page.

If no heartbeat is received for 15 minutes, the claim is automatically released:
- `support_claimed_by` → NULL
- `support_claimed_at` → NULL
- `current_status` → `SUPPORT_REVIEW_PENDING`

This is enforced by a Redis-based TTL key per request, managed by the Workflow Service.

---

# Important Backend Rules

# Never Trust Frontend Permissions

Users must never receive unrelated requests from backend APIs.

Visibility scoping is mandatory.

All permissions must be validated in backend.

---

# Support Review Locking Is Mandatory

The backend must guarantee:

- Only one support reviewer can actively review a request
- Claiming is atomic
- Claims auto-release on disconnect or timeout
- Queue visibility remains available to all support users

---

# Never Allow Direct Status Updates

Request statuses must only change through:

- Workflow Service

Direct database updates are not allowed.

---

# Workflow Service Is Mandatory

The workflow engine is the heart of the platform.

All transitions must go through centralized workflow logic.

Queue visibility and ownership rules must also be centralized.

---

# Recommended Development Order

# Phase 1

- Authentication
- User roles
- Banks
- Import request model
- Workflow service
- Request transitions

---

# Phase 2

- Voting service
- Document uploads
- Audit logging
- Notifications
- Workflow history

---

# Phase 3

- PDF generation
- Reports
- Queue optimizations
- Performance improvements
- Security hardening

---

# Backend Goal

Build a secure, workflow-oriented backend that guarantees:

- Controlled workflow transitions
- Organization-scoped visibility with role-scoped actions
- Queue-based operational access
- Immutable approval stages
- Regulatory auditability
- Workflow integrity
- Enterprise-grade authorization

The backend should behave like a real banking regulatory workflow engine rather than a generic CRUD admin system.
