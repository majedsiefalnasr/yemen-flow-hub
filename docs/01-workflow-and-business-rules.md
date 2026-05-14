# Workflow and Business Rules

# Workflow Overview

Yemen Flow Hub is built around a controlled regulatory workflow.

The workflow controls:

- Permissions
- Editing access
- Approval authority
- Voting authority
- State transitions
- Audit tracking
- Queue-based visibility

The system is not based on free-form actions.
Every request moves through predefined stages with strict business rules.

---

# Operational Visibility Model

The platform uses a bank-owned operational workflow model.

Requests belong to the bank entity, NOT individual users.

All users inside the same bank can view all requests belonging to their bank.

However:

- Actions remain role-scoped
- Dashboards remain role-specific
- Workflow permissions remain strictly enforced

The workflow system is designed around:

- Queue-based operational dashboards
- Workflow-specific visibility
- Controlled organizational access
- Operational continuity
- Team collaboration
- Employee replacement resilience

---

# Main Workflow Stages

```text
DRAFT
→ SUBMITTED
→ BANK_REVIEW
→ BANK_APPROVED
→ SUPPORT_REVIEW_PENDING
→ SUPPORT_REVIEW_IN_PROGRESS
→ SUPPORT_APPROVED           (or SUPPORT_REJECTED → Bank Reviewer decides)
→ WAITING_FOR_SWIFT
→ SWIFT_UPLOADED
→ WAITING_FOR_VOTING_OPEN
→ EXECUTIVE_VOTING_OPEN
→ EXECUTIVE_VOTING_CLOSED
→ EXECUTIVE_APPROVED         (or EXECUTIVE_REJECTED — terminal)
→ CUSTOMS_DECLARATION_ISSUED
→ COMPLETED
```

---

# Workflow Stages Details

# 1. Draft Stage

## Owner

Bank Data Entry

## Allowed Actions

- Create request
- Edit request
- Delete request
- Upload documents
- Remove documents
- Submit request

## Restrictions

- Request is not visible to CBY
- Request cannot move forward without submission
- All Data Entry users inside the same bank can access the draft

---

# 2. Submitted Stage

## Owner

Bank Reviewer

## Allowed Actions

- Review request
- Approve request
- Reject request
- Return request to Data Entry

## Restrictions

- Data Entry user can no longer modify request directly
- Reviewer must belong to same bank
- All bank users can view submitted requests belonging to their bank

---

# 3. Bank Approved Stage

## Owner

CBY Support Committee

## Allowed Actions

- Review request
- Approve request
- Reject request

## Restrictions

- Request becomes permanently locked for editing
- Bank cannot modify request after internal approval

---

# 4. Support Review Pending

## Owner

CBY Support Committee

## Purpose

The request waits for a support committee member to start review.

Only support committee users can access this queue.

## Important Rule

Support review claiming uses a temporary soft-lock model.

A request becomes temporarily locked while a support reviewer is actively reviewing it.

The claim is automatically released when:

- The reviewer leaves the request page
- The reviewer disconnects
- Session timeout occurs

Only one support reviewer may actively review a request simultaneously.

All support users can still see the request inside the queue.

---

# 5. Support Review In Progress

## Owner

Assigned Support Committee Reviewer

## Allowed Actions

- Review request
- Approve request
- Reject request with reason

## Restrictions

- Only assigned reviewer can review the request
- Other support members can see request ownership but cannot review the request
- Request remains fully locked for editing

## Possible Outcomes

### Approved

Request moves to SWIFT stage.

### Rejected

Request returns to Bank Reviewer.

The Bank Reviewer can:

- Keep request rejected
- Return request to Data Entry for correction and resubmission

---

# 6. Support Approved Stage

## Owner

Bank SWIFT Officer

## Allowed Actions

- Upload SWIFT document

## Restrictions

- No request editing allowed
- Request data is read-only
- SWIFT upload is mandatory before executive voting

---

# 7. SWIFT Uploaded Stage

## Owner

Executive Committee

## Allowed Actions

- Start voting
- View request documents
- Review request details

## Restrictions

- No editing allowed
- No document modifications allowed

---

# 8. Executive Voting Stage

## Voting Governance

Executive voting is managed through controlled voting sessions.

## Executive Committee Director

The Executive Committee Director can:

- Open voting sessions
- Close voting sessions
- Vote as a normal committee member
- Resolve tie decisions
- Finalize executive decisions
- Issue customs declarations

---

## Voting States

- WAITING_FOR_VOTING_OPEN
- EXECUTIVE_VOTING_OPEN
- EXECUTIVE_VOTING_CLOSED

---

## Allowed Vote Types

- Approve
- Reject
- Abstain
- AUTO_ABSTAIN_TIMEOUT

---

## Voting Rules

- No minimum quorum exists
- The Director can close voting at any time
- Any member who did not vote before closing is marked as AUTO_ABSTAIN_TIMEOUT
- Auto abstain is different from manual abstain
- The Executive Committee Director also votes as a regular member
- Tie decisions are resolved using the Director's vote

---

# 9. Final Decision Stage

## Possible Results

- Approved
- Rejected

Rejected requests remain permanently locked and cannot return to editable workflow states.

## Restrictions

- Voting closes
- Votes become immutable
- Request becomes final

---

# 10. Customs Declaration Stage

## Owner

Executive Committee Director

## Allowed Actions

- Generate customs declaration
- Issue declaration number
- Export printable PDF
- Complete workflow

---

# Core Business Rules

# Request Editing Rules

## Editable States

Requests can only be edited before internal bank approval.

Editable states:

- Draft
- Draft (Rejected Internally)

If the Bank Reviewer rejects the request before approval:

- The request returns to Data Entry
- Data Entry can modify and resubmit

## Locked States

Requests become permanently read-only immediately after internal bank approval.

Locked stages include:

- Bank Approved
- Support Review Pending
- Support Review In Progress
- Support Approved
- Support Rejected
- Waiting For SWIFT
- SWIFT Uploaded
- Waiting For Voting Open
- Executive Voting Open
- Executive Voting Closed
- Executive Approved
- Executive Rejected
- Customs Declaration Issued
- Completed

---

# Request Ownership Rules

Each workflow stage has a responsible operational role.

Requests themselves belong to the bank entity, not individual users.

| Stage                      | Owner                     |
| -------------------------- | ------------------------- |
| Draft                      | Data Entry                |
| Submitted                  | Bank Reviewer             |
| Bank Approved              | Support Committee         |
| Support Review Pending     | Support Committee Queue   |
| Support Review In Progress | Assigned Support Reviewer |
| Support Approved           | SWIFT Officer             |
| Executive Voting           | Executive Committee       |
| Customs Declaration        | Committee Director        |

## Ownership Tracking

Every request should track:

- Created By
- Last Updated By
- Submitted By
- Internal Reviewer
- Support Reviewer
- SWIFT Uploaded By

This guarantees workflow accountability and operational traceability.

---

# Bank Separation Rules

## Important Rule

The Data Entry role and Bank Reviewer role must remain separated.

This ensures:

- Approval integrity
- Fraud prevention
- Regulatory compliance

## Restriction

The same user cannot:

- Create request
- Review the same request

---

# Visibility Rules

# Bank Reviewer Visibility

Bank Reviewer can always track:

- Support committee review
- SWIFT upload status
- Voting status
- Final result
- Customs declaration issuance

But cannot modify requests after approval.

---

# Support Committee Visibility

Support Committee can monitor request progress after approval.

---

# Voting Rules

# Allowed Votes

- Approve
- Reject
- Abstain

---

# Voting Restrictions

## One Vote Per Member

Each member can vote once.

## No Vote Changes After Finalization

Votes become locked after final decision.

## Only Active Voting Stage

Voting is only allowed during Executive Voting stage.

---

# SWIFT Rules

## SWIFT Upload Requirement

Executive voting cannot start before SWIFT upload.

## SWIFT Restrictions

After SWIFT upload:

- Request becomes fully read-only
- Documents cannot be replaced
- Workflow ownership moves to CBY

---

# Request Rejection Rules

# Internal Bank Reviewer Rejection

If the internal reviewer rejects the request before approval:

- The request returns to Data Entry
- Data Entry can modify and resubmit

---

# Support Committee Rejection

If the support committee rejects the request:

- The request status becomes `SUPPORT_REJECTED`
- The request returns to the Bank Reviewer queue
- The Bank Reviewer can keep the request rejected (status remains `SUPPORT_REJECTED`)
- The Bank Reviewer can return the request to Data Entry for correction (status becomes `DRAFT_REJECTED_INTERNAL`)
- Returned requests become editable again

## Post-Support-Rejection Transition Table

| Bank Reviewer Action       | Resulting Status          |
| -------------------------- | ------------------------- |
| Keep rejected (no action)  | `SUPPORT_REJECTED`        |
| Return to Data Entry       | `DRAFT_REJECTED_INTERNAL` |

---

# Executive Committee Rejection

Executive rejection is FINAL.

Once executive rejected:

- The request cannot be reopened
- The request cannot be edited
- The request cannot be resubmitted
- Admins cannot override the rejection

---

# Audit Rules

Every important action must be logged.

## Logged Actions

- Request creation
- Request modification
- Approval actions
- Rejection actions
- Voting actions
- SWIFT upload
- Customs declaration issuance
- Status transitions

## Audit Metadata

Each log should include:

- User
- Role
- Timestamp
- Action type
- Previous status
- New status
- Metadata

---

# Workflow Principles

The workflow system is based on:

- Strict approval hierarchy
- Controlled state transitions
- Immutable approvals
- Locked workflow stages
- Clear ownership transfer
- Regulatory compliance
- Full auditability
- Queue-based operational ownership
- Scoped workflow visibility

---

# Role Visibility Rules

## Data Entry Visibility

Data Entry users can view all requests belonging to their bank.

Their dashboard should primarily focus on:

- Drafts
- Submitted requests
- Returned requests
- Rejected requests
- Completed requests

Data Entry users should NOT see detailed CBY operational workflow stages.

Once a request leaves the bank approval stage, Data Entry users should generally see:

- Under CBY Processing
- Rejected
- Completed

## Internal → Simplified Status Mapping (Data Entry View)

| Internal Status (`current_status`)  | Simplified Business Status |
| ----------------------------------- | -------------------------- |
| `DRAFT`                             | Draft                      |
| `DRAFT_REJECTED_INTERNAL`           | Returned For Correction    |
| `SUBMITTED`                         | Submitted To CBY           |
| `BANK_REVIEW`                       | Submitted To CBY           |
| `BANK_APPROVED`                     | Under CBY Processing       |
| `SUPPORT_REVIEW_PENDING`            | Under CBY Processing       |
| `SUPPORT_REVIEW_IN_PROGRESS`        | Under CBY Processing       |
| `SUPPORT_APPROVED`                  | Under CBY Processing       |
| `SUPPORT_REJECTED`                  | Rejected                   |
| `WAITING_FOR_SWIFT`                 | Under CBY Processing       |
| `SWIFT_UPLOADED`                    | Under CBY Processing       |
| `WAITING_FOR_VOTING_OPEN`           | Under CBY Processing       |
| `EXECUTIVE_VOTING_OPEN`             | Under CBY Processing       |
| `EXECUTIVE_VOTING_CLOSED`           | Under CBY Processing       |
| `EXECUTIVE_APPROVED`                | Completed                  |
| `EXECUTIVE_REJECTED`                | Rejected                   |
| `CUSTOMS_DECLARATION_ISSUED`        | Completed                  |
| `COMPLETED`                         | Completed                  |

---

## Bank Reviewer Visibility

Bank Reviewers can access all requests belonging to their bank.

They can monitor:

- Support review progress
- SWIFT upload status
- Executive voting status
- Final workflow results
- Customs declaration issuance

---

## SWIFT Officer Visibility

SWIFT Officers can access:

- Requests waiting for SWIFT upload
- Requests belonging to their bank

---

## Support Committee Visibility

Support Committee users can access:

- Pending support review queue
- Claimed reviews
- Support workflow requests

Support users can see who is actively reviewing a request.

---

## Executive Committee Visibility

Executive Committee users can access:

- Executive voting queue
- Finalized voting requests

---

## CBY Admin Visibility

CBY Admin has full system visibility.

---

# Important Technical Notes

The backend must implement:

- Centralized Workflow Service
- Role-based authorization
- Transition validation
- State locking rules
- Voting engine
- Audit logging
- Immutable stage history

The frontend must implement:

- Role-aware UI
- Conditional actions
- Read-only states
- Workflow status tracking
- Timeline visualization
- Role-specific operational dashboards
- Queue-based request filtering
- Voting interface
- RTL-first layouts
