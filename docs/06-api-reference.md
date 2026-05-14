# API Reference

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

# Request APIs

# Get Requests

## Endpoint

```http
GET /api/requests
```

## Features

Supports:

- Pagination
- Filtering
- Status filtering
- Search
- Bank scoping
- Queue-based filtering
- Workflow-stage filtering
- Organization-scoped visibility
- Role-scoped operational filtering
- Queue-scoped operational filtering

---

# Request Visibility Rules

The API must NEVER return requests outside the user's organization scope.

The platform uses:

- Organization-scoped visibility
- Role-scoped actions
- Queue-based operational filtering

Visibility enforcement must happen at:

- Query level
- API level
- Workflow service level
- Policy level

The platform is NOT a shared admin dashboard.

All request APIs must return only operationally relevant data.

---

# Create Request

## Endpoint

```http
POST /api/requests
```

## Request Body

```json
{
  "currency": "USD",
  "amount": 50000,
  "supplier_name": "Supplier Ltd",
  "goods_description": "Medical Supplies",
  "port_of_entry": "Aden Port",
  "notes": "Optional notes"
}
```

---

# Get Request Details

## Endpoint

```http
GET /api/requests/{id}
```

## Response Includes

- Request details
- Internal workflow status
- Role-specific display status
- Documents
- Timeline
- Votes
- Audit data

## Ownership Metadata

Request details responses should include:

```json
{
  "created_by": {},
  "last_updated_by": {},
  "submitted_by": {},
  "internal_reviewer": {},
  "support_reviewer": {},
  "resubmitted_by": {},
  "rejected_by": {},
  "swift_uploaded_by": {}
}
```

---

# Update Request

## Restrictions

Editable states:

- DRAFT
- DRAFT_REJECTED_INTERNAL

Rules:

- Any Data Entry user within the same bank can edit editable requests
- Requests become locked after internal bank approval
- Support-approved requests are immutable
- Executive-rejected requests are terminal and immutable

---

# Delete Request

## Endpoint

```http
DELETE /api/requests/{id}
```

## Restrictions

Allowed only during:

- DRAFT

---

# Workflow APIs

# Submit Request

## Endpoint

```http
POST /api/workflow/{id}/submit
```

---

# Bank Approval

## Endpoint

```http
POST /api/workflow/{id}/bank-approve
```

---

# Bank Rejection

## Endpoint

```http
POST /api/workflow/{id}/bank-reject
```

---

# Bank Return to Data Entry After Support Rejection

## Endpoint

```http
POST /api/workflow/{id}/bank-return-after-support-reject
```

## Purpose

After a support committee rejection, the Bank Reviewer returns the request to Data Entry for correction.

## Permissions

- Bank Reviewer only
- Only when `current_status` is `SUPPORT_REJECTED`

## Result

- `current_status` → `DRAFT_REJECTED_INTERNAL`
- Request becomes editable again

---

# Bank Finalize Rejection After Support Rejection

## Endpoint

```http
POST /api/workflow/{id}/bank-finalize-rejection
```

## Purpose

After a support committee rejection, the Bank Reviewer confirms the request remains permanently rejected.

## Permissions

- Bank Reviewer only
- Only when `current_status` is `SUPPORT_REJECTED`

## Result

- `current_status` remains `SUPPORT_REJECTED`
- Request is marked as workflow-terminal for the bank

---

# Support Approval

## Endpoint

```http
POST /api/workflow/{id}/support-approve
```

---

# Support Rejection

## Endpoint

```http
POST /api/workflow/{id}/support-reject
```

---

# Claim Support Review

## Endpoint

```http
POST /api/workflow/{id}/claim-support-review
```

## Purpose

Temporarily claims a request for active support review.

## Rules

- Only one reviewer can actively review at a time
- Claims are temporary and presence-based
- Claims auto-release after 15 minutes of inactivity
- Support queues remain visible to all support users
- Claiming must be atomic

---

# Release Support Review Claim

## Endpoint

```http
DELETE /api/workflow/{id}/claim-support-review
```

## Purpose

Manually releases an active support review claim (e.g., reviewer navigates away).

## Permissions

- Support Committee only
- Only the current claim holder can release their own claim

---

# Support Review Heartbeat

## Endpoint

```http
POST /api/workflow/{id}/claim-support-review/heartbeat
```

## Purpose

Extends the claim TTL by 15 minutes. Must be called every 60 seconds by the frontend while the reviewer is actively on the request page.

## Permissions

- Support Committee only
- Only the current claim holder

---

# Open Voting Session

## Endpoint

```http
POST /api/voting/{id}/open
```

## Permissions

- Executive Committee Director only

---

# Close Voting Session

## Endpoint

```http
POST /api/voting/{id}/close
```

## Permissions

- Executive Committee Director only

---

# Finalize Executive Decision

## Endpoint

```http
POST /api/workflow/{id}/finalize-decision
```

---

# Voting APIs

# Get Voting Queue

## Endpoint

```http
GET /api/voting
```

---

# Get Voting Details

## Endpoint

```http
GET /api/voting/{id}
```

---

# Submit Vote

## Endpoint

```http
POST /api/voting/{id}/vote
```

## Request Body

```json
{
  "vote": "APPROVE",
  "justification": "Approved after review"
}
```

---

# Allowed Votes

```text
APPROVE
REJECT
ABSTAIN
AUTO_ABSTAIN_TIMEOUT
```

---

# Voting Rules

- Voting only during EXECUTIVE_VOTING_OPEN
- Voting sessions controlled by Executive Committee Director
- Director also votes as a regular member
- Director resolves ties
- No minimum quorum exists
- Any member not voting before closure becomes AUTO_ABSTAIN_TIMEOUT
- AUTO_ABSTAIN_TIMEOUT differs from manual ABSTAIN
- Executive rejected requests remain permanently locked

---

# Document APIs

# Upload Request Document

## Endpoint

```http
POST /api/documents/upload
```

## Request Type

```text
multipart/form-data
```

## Allowed File Types

- PDF only

---

# Upload SWIFT Document

## Endpoint

```http
POST /api/workflow/{id}/swift-upload
```

Note: `{id}` is the import request ID. This endpoint follows the workflow-centric path convention used by all other workflow action endpoints.

## Restrictions

- Only SWIFT Officer role
- Only when request status is `WAITING_FOR_SWIFT`
- SWIFT cannot be replaced after upload
- Request remains permanently read-only after upload
- PDF only

---

# Download Document

## Endpoint

```http
GET /api/documents/{id}/download
```

## Permission Matrix

| Role               | Request Documents | SWIFT Document | Customs Declaration PDF |
| ------------------ | ----------------- | -------------- | ----------------------- |
| DATA_ENTRY         | Own bank only     | No             | No                      |
| BANK_REVIEWER      | Own bank only     | Own bank only  | Own bank only           |
| SWIFT_OFFICER      | Own bank only     | Own bank only  | No                      |
| SUPPORT_COMMITTEE  | Yes (all banks)   | No             | No                      |
| EXECUTIVE_MEMBER   | Yes (all banks)   | Yes            | No                      |
| COMMITTEE_DIRECTOR | Yes (all banks)   | Yes            | Yes                     |
| CBY_ADMIN          | Yes (all banks)   | Yes            | Yes                     |

Document access is validated at the backend policy layer. Frontend visibility is not sufficient.

---

# Audit APIs

# Get Audit Logs

## Endpoint

```http
GET /api/audit
```

---

# Get Request History

## Endpoint

```http
GET /api/requests/{id}/history
```

## Response Includes

- Workflow transitions
- Status history
- User actions
- Timestamps

---

# Customs APIs

# Generate Customs Declaration

## Endpoint

```http
POST /api/customs/{id}/generate
```

## Permissions

- Executive Committee Director only

---

# Get Customs Declaration

## Endpoint

```http
GET /api/customs/{id}
```

---

# Download Customs PDF

## Endpoint

```http
GET /api/customs/{id}/download
```

---

# Users APIs

# Get Users

## Endpoint

```http
GET /api/users
```

---

# Create User

## Endpoint

```http
POST /api/users
```

---

# Update User

## Endpoint

```http
PUT /api/users/{id}
```

---

# Banks APIs

# Get Banks

## Endpoint

```http
GET /api/banks
```

---

# Create Bank

## Endpoint

```http
POST /api/banks
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
- Voting statistics
- Workflow counts

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

# Reports APIs

# Workflow Report

## Endpoint

```http
GET /api/reports/workflow
```

Reports must respect request visibility scope and user permissions.

---

# Voting Report

## Endpoint

```http
GET /api/reports/voting
```

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

Request statuses may only change through:

- Workflow APIs
- Workflow Service
- Controlled workflow transitions

Direct status updates are prohibited.

Workflow locking rules guarantee:

- No editing after internal bank approval
- Temporary support review locking
- Immutable support-approved requests
- Immutable executive-rejected requests
- Immutable customs declarations

---

# Immutable State Enforcement

Any mutation endpoint (`PUT /api/requests/{id}`, `DELETE /api/requests/{id}`, or any workflow action endpoint) must return the following error when the request is in an immutable terminal state:

```json
{
  "success": false,
  "message": "This request is in an immutable workflow state and cannot be modified.",
  "error_code": "WORKFLOW_IMMUTABLE_STATE",
  "current_status": "EXECUTIVE_REJECTED"
}
```

HTTP Status: `403 Forbidden`

Immutable terminal states that must block all mutation:

- `EXECUTIVE_REJECTED`
- `CUSTOMS_DECLARATION_ISSUED`
- `COMPLETED`

Locked (non-editable but not terminal) states must return `422 Unprocessable Entity` with `WORKFLOW_LOCKED_STATE` error code when edit attempts are made.

---

# Voting Concurrency Protection

Vote submission (`POST /api/voting/{id}/vote`) and session closure (`POST /api/voting/{id}/close`) must use database-level pessimistic locking to prevent race conditions.

Behavior:
- If a vote is submitted within the same transaction as a session closure, the vote is rejected with `VOTING_SESSION_CLOSED` error.
- All vote submissions are transactional.
- Session closure atomically marks all non-voted members as `AUTO_ABSTAIN_TIMEOUT`.

---

# Visibility Security Rules

The backend must guarantee:

- Users never receive requests outside organization scope
- Bank visibility is organization-scoped
- Actions remain role-scoped
- Queue visibility is operationally scoped
- Support queues are workflow-scoped
- Executive queues are voting-scoped

Data Entry users:

- Can view all bank requests
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
