# Yemen Flow Hub — Project Brief

## Project Overview

Yemen Flow Hub is an internal regulatory workflow platform for the Central Bank of Yemen (CBY) used to manage and review commercial bank import financing requests.

The platform digitizes the full import financing lifecycle from initial bank submission to final customs declaration issuance.

The system is not public-facing and is intended only for:

- Commercial banks
- Central Bank of Yemen staff
- Regulatory committees

The platform is Arabic-first, RTL-based, secure, and workflow-driven.

---

# Main Objective

The goal of the platform is to replace manual and fragmented approval processes currently handled through:

- Paper documents
- Emails
- Phone calls
- Manual committee coordination

With a centralized digital workflow that provides:

- Controlled approval stages
- Role-based permissions
- Full audit tracking
- Executive committee voting
- SWIFT document management
- Customs declaration issuance

---

# Main Actors

## Commercial Bank

Responsible for:

- Creating financing requests
- Reviewing requests internally
- Uploading SWIFT documents
- Following request status
- Operating through organization-scoped workflow queues

### Bank Roles

#### Data Entry

Responsible for:

- Creating requests
- Editing requests before approval
- Updating requests before internal approval
- Tracking bank workflow requests through simplified business statuses

#### Bank Reviewer

Responsible for:

- Reviewing requests
- Approving requests internally
- Rejecting requests
- Rejecting requests before approval and returning them to Data Entry
- Following request status throughout the lifecycle
- Monitoring bank-level workflow queues

#### SWIFT Officer

Responsible for:

- Uploading SWIFT document after CBY support approval

---

## Central Bank of Yemen (CBY)

Responsible for:

- Reviewing requests
- Voting on requests
- Issuing customs declarations
- Monitoring workflow compliance

### CBY Roles

#### Support Committee

Responsible for:

- Reviewing submitted requests
- Approving requests
- Rejecting requests with rejection reasons
- Temporarily claiming requests for active review
- Following request status after approval

#### Executive Committee

Responsible for:

- Voting on requests
- Approve / Reject / Abstain voting

#### Committee Director

Responsible for:

- Opening executive voting sessions
- Closing executive voting sessions
- Participating in voting
- Resolving tied voting decisions
- Finalizing executive decisions
- Issuing customs declarations

---

# Workflow Lifecycle

The platform is based on a controlled multi-stage workflow.

## Stage 1 — Request Creation

The commercial bank Data Entry user creates a financing request.

At this stage:

- The request can still be edited
- Documents can be modified
- The request is considered draft

---

## Stage 2 — Bank Internal Review

A Bank Reviewer reviews the request.

Important rule:

- The Data Entry user and the Reviewer are different roles
- The same user cannot review their own request

The reviewer can:

- Approve the request
- Reject the request before approval
- Return it to Data Entry for modification before approval

After approval:

- The request becomes locked
- No direct editing is allowed
- The request enters CBY workflow processing

Requests only become editable again if:

- Support Committee rejects the request
- Bank Reviewer returns it to Data Entry

---

## Stage 3 — CBY Support Committee Review

The Support Committee reviews approved bank requests.

Important review rules:

- Only one support committee member can actively review a request at a time
- Claims are temporary and presence-based
- Claims automatically release on disconnect or timeout
- Other support users can still view the request in the queue

Possible outcomes:

### Approved

The request moves to the SWIFT upload stage.

### Rejected

The request returns to the Bank Reviewer.

The Bank Reviewer can:

- Keep the request rejected
- Return the request to Data Entry for correction and resubmission

Returned requests become editable again.

---

## Stage 4 — SWIFT Upload

The commercial bank uploads the SWIFT document.

Rules:

- Request data is already locked from previous stages
- No modifications are allowed
- SWIFT upload is required before executive voting

---

## Stage 5 — Executive Committee Voting

Executive voting is managed through controlled voting sessions.

The Executive Committee contains 6 members.

Each member can vote:

- Approve
- Reject
- Abstain

The Committee Director:

- Opens voting sessions
- Closes voting sessions
- Participates in voting
- Resolves tied decisions
- Finalizes voting outcomes

Voting rules:

- No minimum quorum exists
- Voting can be closed at any time by the Director
- Members who did not vote before closure become AUTO_ABSTAIN_TIMEOUT
- AUTO_ABSTAIN_TIMEOUT differs from manual abstain
- Director vote resolves ties

If rejected:

- The request becomes permanently rejected
- No further modifications are allowed
- The request cannot return to editable workflow states
- The rejection cannot be overridden

---

## Stage 6 — Final Decision

After executive approval:

- The request is marked as approved
- Final status is generated

---

## Stage 7 — Customs Declaration Issuance

The Committee Director issues the official customs declaration document.

The declaration:

- Is generated as printable RTL PDF
- Contains official approval information
- Finalizes the request lifecycle
- Becomes a permanent immutable workflow artifact

---

# Workflow Characteristics

The system is not a simple CRUD application.

It is a workflow-driven regulatory platform based on:

- Controlled state transitions
- Role-based approvals
- Request locking rules
- Audit logging
- Voting governance
- Controlled approval locking
- Queue-based operational workflows
- Organization-scoped visibility
- Role-scoped operational actions
- Voting session governance

---

# Operational Visibility Model

The platform is NOT a shared admin dashboard.

It is an organization-scoped operational workflow platform.

All users inside the same bank can view all requests belonging to their bank.

However:

- Actions remain role-scoped
- Dashboards remain workflow-oriented
- Visibility remains operationally scoped

The system is designed around:

- Queue-based operational workflows
- Workflow-specific dashboards
- Institutional governance
- Operational continuity
- Organizational collaboration

---

# Visibility Rules By Role

## Data Entry

Data Entry users can view all requests belonging to their bank.

Their dashboards primarily focus on:

- Draft requests
- Returned requests
- Submitted requests
- Rejected requests
- Completed requests

Data Entry users should NOT see detailed CBY workflow internals.

---

## Bank Reviewer

Bank Reviewers can see:

- All requests belonging to their bank

They can monitor:

- Support review progress
- SWIFT status
- Executive voting progress
- Final decisions
- Customs declaration issuance

---

## SWIFT Officer

SWIFT Officers can access:

- Requests waiting for SWIFT upload
- Requests belonging to their bank

---

## Support Committee

Support Committee users can access:

- Support review queues
- Claimed reviews
- Support workflow requests

They can also see:

- Active reviewers
- Claim states

---

## Executive Committee

Executive Committee users can access:

- Executive voting queues
- Voting sessions
- Finalized executive decisions

---

## Committee Director

Committee Director can:

- Manage voting sessions
- Finalize executive decisions
- Issue customs declarations

---

## CBY Admin

CBY Admin has full system visibility.

---

## Core Business Rules

### Request Editing Rules

#### Editable

Requests can only be edited before internal bank approval.

Editable workflow stages:

- Draft
- Draft (Rejected Internally)

If the internal reviewer rejects the request before approval:

- The request returns to Data Entry
- Data Entry can modify and resubmit

#### Locked

Requests become permanently locked immediately after internal bank approval.

Locked stages include:

- Bank Approved
- Support Review
- SWIFT Upload
- Executive Voting
- Final Decision
- Customs Declaration

---

## Visibility Rules

### Data Entry

Data Entry users:

- Can view all bank requests
- Primarily interact with business-oriented workflow statuses
- Should NOT see detailed CBY operational workflow stages

Their dashboard focuses on:

- Drafts
- Returned requests
- Submitted requests
- Rejected requests
- Completed requests

### Bank Reviewer

Bank Reviewers can monitor:

- Support review
- SWIFT stage
- Executive voting
- Final result
- Customs declaration issuance

### Support Committee

Support Committee users can monitor:

- Claimed reviews
- Support workflow queues
- Review ownership state

---

## Voting Rules

Vote types:

- Approve
- Reject
- Abstain
- AUTO_ABSTAIN_TIMEOUT

Decision logic:

- Committee Director controls voting sessions
- Director participates in voting
- Director resolves ties
- No quorum requirement exists
- Executive rejection is terminal

---

# Technical Direction

## Frontend

- Nuxt 4
- Vue 4
- TypeScript
- Tailwind CSS
- shadcn-vue
- RTL-first UI

## Backend

- Laravel 11 API
- Sanctum Authentication
- Workflow Service Architecture
- Role-based authorization
- Queue workers

## Database

- MySQL

## Infrastructure

- Redis
- Object storage for documents
- Secure file handling

---

# Key System Features

- Multi-stage workflow engine
- Role-based access control
- SWIFT document management
- Executive committee voting
- Audit logging
- Request tracking
- Queue-based dashboards
- Organization-scoped visibility
- Voting session governance
- Customs declaration generation
- Arabic RTL interface
- Secure internal authentication

---

# Project Goal

Build a secure, scalable, workflow-oriented institutional platform that enables the Central Bank of Yemen to regulate import financing requests through:

- Controlled workflow stages
- Organization-scoped operational visibility
- Role-scoped workflow actions
- Executive voting governance
- Queue-based operational workflows
- Immutable workflow enforcement
- Enterprise-grade auditability

The platform should behave like a real institutional banking governance system rather than a generic admin dashboard.
