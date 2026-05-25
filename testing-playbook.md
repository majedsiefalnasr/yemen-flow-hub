# Testing Playbook

This document explains how to test Yemen Flow Hub across all roles and across the full request lifecycle from beginning to end.

It is intended for manual QA, UAT, role-based smoke testing, and structured regression testing.

This file is written in English, with Arabic UI labels where useful.

## Purpose

Use this playbook to verify:

- Role-based access control
- Role-specific dashboards
- Workflow transitions from draft to completion
- Rejection and return branches
- Document access permissions
- Immutable and locked state behavior
- Cross-role handoff integrity

## Scope

This playbook covers:

- All production roles
- The happy-path workflow from `DRAFT` to `COMPLETED`
- Important branch paths:
  - bank return
  - bank terminal rejection
  - support rejection
  - support return to intake
  - executive rejection

## Related Documents

- [roles-reference.md](/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/roles-reference.md)
- `docs/01-workflow-and-business-rules.md`
- `docs/06-api-reference.md`
- `docs/03-database-and-models.md`

## Testing Principles

- Always test with real roles, not role-switch assumptions, unless demo mode is intentionally enabled for non-production QA.
- Prefer browser-based verification for UI flows.
- Verify both visibility and non-visibility.
- Verify both allowed actions and blocked actions.
- For every workflow action, verify:
  - status change
  - owner-role change if applicable
  - visible UI state change
  - notification or follow-up availability where relevant
- Treat backend enforcement as the final authority if UI behavior looks inconsistent.

## Roles Under Test

| Role | Primary area |
| --- | --- |
| `DATA_ENTRY` | Drafting, submission, correction, request tracking |
| `BANK_REVIEWER` | Internal bank review |
| `BANK_ADMIN` | Bank administration and bank-level visibility |
| `SWIFT_OFFICER` | SWIFT upload |
| `SUPPORT_COMMITTEE` | Support claim/review queue |
| `EXECUTIVE_MEMBER` | Executive voting |
| `COMMITTEE_DIRECTOR` | Voting lifecycle and external FX confirmation issuance |
| `CBY_ADMIN` | Global system administration and oversight |

## Canonical Workflow Path

Primary happy path:

`DRAFT`
-> `SUBMITTED`
-> `BANK_REVIEW`
-> `BANK_APPROVED`
-> `SUPPORT_REVIEW_PENDING`
-> `SUPPORT_REVIEW_IN_PROGRESS`
-> `SUPPORT_APPROVED`
-> `EXECUTIVE_VOTING_OPEN`
-> `EXECUTIVE_VOTING_CLOSED`
-> `EXECUTIVE_APPROVED`
-> `WAITING_FOR_SWIFT`
-> `SWIFT_UPLOADED`
-> `FX_CONFIRMATION_PENDING`
-> `COMPLETED`

Important auto-chains to verify:

- `BANK_APPROVED` automatically moves into support queue behavior
- `SUPPORT_APPROVED` automatically opens executive voting as `EXECUTIVE_VOTING_OPEN`
- `EXECUTIVE_APPROVED` automatically moves to `WAITING_FOR_SWIFT`
- `SWIFT_UPLOADED` automatically moves to `FX_CONFIRMATION_PENDING`
- Uploading the signed/stamped external FX confirmation completes the workflow to `COMPLETED`

## Pre-Test Setup

Before running this playbook, prepare:

- One active user for each role
- At least one bank with:
  - `DATA_ENTRY`
  - `BANK_REVIEWER`
  - `BANK_ADMIN`
  - `SWIFT_OFFICER`
- CBY-side users for:
  - `SUPPORT_COMMITTEE`
  - `EXECUTIVE_MEMBER`
  - `COMMITTEE_DIRECTOR`
  - `CBY_ADMIN`
- A clean request scenario with valid merchant and document data
 - PDF files ready for:
   - request documents
   - SWIFT document upload
   - FX confirmation request upload
   - signed/stamped external FX confirmation upload

Recommended test data:

- One request for happy-path completion
- One request for bank return flow
- One request for terminal bank rejection
- One request for support rejection flow
- One request for support return-to-intake flow
- One request for executive rejection flow

## Test Evidence to Capture

For each major step, capture:

- Acting role
- Starting status
- Action taken
- Resulting status
- Expected owner role
- Screenshot of UI state
- Any audit/history confirmation if visible

Minimum evidence per end-to-end run:

- Dashboard screenshot for each acting role
- Request detail screenshot before and after each transition
- Final completed request screenshot

## Part 1: Role Smoke Tests

Run these before deep workflow testing.

### 1. `DATA_ENTRY` smoke test

Verify:

- Can log in
- Lands on role-appropriate dashboard
- Sees `New Request` / `طلب جديد`
- Can access:
  - dashboard
  - requests
  - notifications
- Does not see:
  - SWIFT upload actions
  - support claim actions
  - voting controls
  - customs issuance controls
  - admin-only navigation
- Sees simplified statuses on requests

### 2. `BANK_REVIEWER` smoke test

Verify:

- Can access own-bank requests
- Sees review-oriented dashboard and queue
- Can open submitted requests
- Does not see:
  - staff management
  - SWIFT upload UI
  - support claim UI
  - executive voting controls
  - customs issuance controls

### 3. `BANK_ADMIN` smoke test

Verify:

- Can access dashboard, bank staff, merchants, and reports
- Can see bank-only users
- Can create or edit bank-manageable users only
- Cannot assign CBY roles
- Does not see Director-only workflow controls

### 4. `SWIFT_OFFICER` smoke test

Verify:

- Sees only SWIFT-relevant queue behavior
- Can access SWIFT upload route only where appropriate
- Can download the FX confirmation request template
- Can upload:
  - SWIFT document PDF
  - FX confirmation request PDF
- Cannot submit SWIFT stage unless both required documents are uploaded
- Does not see bank-review or voting controls
- No non-SWIFT role should see the SWIFT upload workflow UI

### 5. `SUPPORT_COMMITTEE` smoke test

Verify:

- Sees global support queue
- Can distinguish:
  - unclaimed
  - claimed by me
  - claimed by others
- Can claim and release where valid
- Does not see SWIFT or customs actions

### 6. `EXECUTIVE_MEMBER` smoke test

Verify:

- Sees executive queue only
- Can open voting details
- Can vote only when voting is open
- Cannot open/close/finalize sessions
- Cannot issue customs declarations

### 7. `COMMITTEE_DIRECTOR` smoke test

Verify:

- Sees executive governance dashboard
- Cannot close voting while one or more executive members have not voted
- Can close and finalize voting only after all executive members vote
- Can access external FX confirmation workflow for approved requests
- Can download generated external FX confirmation PDF
- Can upload signed/stamped external FX confirmation document
- Does not see unrelated bank-admin user-management flows as the main role job

### 8. `CBY_ADMIN` smoke test

Verify:

- Sees global system dashboard
- Can access:
  - banks/entities
  - system users
  - roles/permissions page
  - document rules
  - settings
  - audit
  - reports
- Cannot perform Director-only workflow actions unless backend explicitly allows it

## Part 2: Full End-to-End Happy Path

This is the main "from beginning to end" scenario.

### Step 1: Create draft as `DATA_ENTRY`

Action:

- Log in as `DATA_ENTRY`
- Open `New Request`
- Fill all mandatory fields
- Upload required request documents as PDFs
- Save draft if available

Verify:

- Request is created in `DRAFT`
- Request appears in draft list/dashboard
- `DATA_ENTRY` can reopen and edit it

### Step 2: Submit request as `DATA_ENTRY`

Action:

- Submit the request

Verify:

- Status becomes `SUBMITTED`
- Owner moves to `BANK_REVIEWER`
- Request disappears from editable draft behavior
- `DATA_ENTRY` sees a business-facing submitted status
- `BANK_REVIEWER` can now see the request in review flow

### Step 3: Begin review as `BANK_REVIEWER`

Action:

- Log in as `BANK_REVIEWER`
- Open the submitted request
- Start review

Verify:

- Status becomes `BANK_REVIEW`
- Review controls are visible
- Reviewer can approve, return, or terminally reject

### Step 4: Approve as `BANK_REVIEWER`

Action:

- Approve the request

Verify:

- Status passes through `BANK_APPROVED`
- Request enters support queue behavior as `SUPPORT_REVIEW_PENDING`
- `SUPPORT_COMMITTEE` can see the request
- `DATA_ENTRY` no longer sees bank-editable behavior

### Step 5: Claim as `SUPPORT_COMMITTEE`

Action:

- Log in as `SUPPORT_COMMITTEE`
- Open support queue
- Claim the request

Verify:

- Status becomes `SUPPORT_REVIEW_IN_PROGRESS`
- Claim owner is visible
- Another support user cannot take decision actions on this request
- Heartbeat behavior is active while the page remains open

### Step 6: Approve as `SUPPORT_COMMITTEE`

Action:

- Approve the claimed request

Verify:

- Status becomes `SUPPORT_APPROVED`
- Executive voting opens automatically as `EXECUTIVE_VOTING_OPEN`
- `EXECUTIVE_MEMBER` users can now vote
- `COMMITTEE_DIRECTOR` can now monitor and close the voting session

### Step 7: Vote as `EXECUTIVE_MEMBER`

Action:

- Log in as all active `EXECUTIVE_MEMBER` users
- Cast votes

Verify:

- Votes are recorded successfully
- Members cannot vote twice
- Voting details are visible where permitted
- Voting remains open until explicitly closed by `COMMITTEE_DIRECTOR`

### Step 8: Close voting as `COMMITTEE_DIRECTOR`

Action:

- Attempt to close voting before all members vote
- Verify closure is blocked
- After all members vote, close the session

Verify:

- Voting cannot close while any active executive member has not voted
- Status becomes `EXECUTIVE_VOTING_CLOSED`
- No further member voting is allowed

### Step 9: Finalize decision as `COMMITTEE_DIRECTOR`

Action:

- Finalize the session

Verify:

- Status becomes either:
  - `EXECUTIVE_APPROVED`
  - or `EXECUTIVE_REJECTED`
- For happy path, continue only if result is `EXECUTIVE_APPROVED`
- `EXECUTIVE_APPROVED` automatically moves to `WAITING_FOR_SWIFT`

### Step 10: Upload SWIFT and FX request documents as `SWIFT_OFFICER`

Action:

- Log in as `SWIFT_OFFICER`
- Open the request from SWIFT queue
- Download the FX confirmation request template
- Upload:
  - SWIFT PDF
  - FX confirmation request PDF

Verify:

- Both uploads accept PDF files only
- SWIFT stage submission is blocked until both files exist
- Status becomes `SWIFT_UPLOADED`
- Auto-chain moves request to `FX_CONFIRMATION_PENDING`
- Request becomes read-only for SWIFT stage
- `COMMITTEE_DIRECTOR` can now access the external FX confirmation workflow

### Step 11: Upload signed/stamped external FX confirmation as `COMMITTEE_DIRECTOR`

Action:

- Download the generated external FX confirmation PDF
- Print/sign/stamp externally
- Upload the signed/stamped PDF

Verify:

- Generated PDF downloads successfully
- Signed/stamped upload accepts PDF only
- Status becomes `COMPLETED`
- Authorized users can access the final signed confirmation document

### Step 13: Final verification as all relevant roles

Verify:

- `DATA_ENTRY` sees request as completed
- `BANK_REVIEWER` sees final outcome
- `COMMITTEE_DIRECTOR` can access the final external FX confirmation documents
- `CBY_ADMIN` can see completed request globally
- Unauthorized roles still cannot access restricted documents/actions

## Part 3: Branch and Exception Tests

These are required for full confidence.

### A. Bank return to intake

Actor: `BANK_REVIEWER`

Start state:

- `BANK_REVIEW`

Action:

- Return request to intake with mandatory comment

Verify:

- Status becomes `BANK_RETURNED`
- `DATA_ENTRY` can edit and resubmit
- Return reason is visible where expected

### B. Bank terminal rejection

Actor: `BANK_REVIEWER`

Start state:

- `BANK_REVIEW`

Action:

- Perform terminal rejection with mandatory comment

Verify:

- Status becomes `BANK_REJECTED`
- Request cannot be reopened, edited, or resubmitted
- `DATA_ENTRY` receives final rejection context
- Mutations are blocked afterward

### C. Support rejection

Actor: `SUPPORT_COMMITTEE`

Start state:

- `SUPPORT_REVIEW_IN_PROGRESS`

Action:

- Reject with comment

Verify:

- Status becomes `SUPPORT_REJECTED`
- `BANK_REVIEWER` sees request again
- `BANK_REVIEWER` can either:
  - keep it rejected
  - return it to Data Entry

### D. Support return to intake

Actor: `SUPPORT_COMMITTEE`

Start state:

- `SUPPORT_REVIEW_IN_PROGRESS`

Action:

- Return to intake with mandatory comment

Verify:

- Status becomes `SUPPORT_RETURNED`
- `DATA_ENTRY` can edit and resubmit

### E. Executive rejection

Actors:

- `EXECUTIVE_MEMBER`
- `COMMITTEE_DIRECTOR`

Flow:

- Cast rejecting votes from all active executive members
- Close voting after all votes are submitted
- Finalize rejection

Verify:

- Status becomes `EXECUTIVE_REJECTED`
- Request is terminal and immutable
- No resubmission path exists
- Admin cannot override it through normal workflow actions

### F. Claim expiry

Actor: `SUPPORT_COMMITTEE`

Flow:

- Claim request
- Stop heartbeats / simulate inactivity

Verify:

- Claim expires after 15 minutes of inactivity
- Request returns to `SUPPORT_REVIEW_PENDING`
- Another support user can claim it


## Part 4: Permission and Security Tests

### Cross-bank isolation

Verify:

- Bank-side users cannot access other banks' requests by list or direct URL
- Cross-bank data does not leak in dashboards, search, or downloads

### Role-action mismatch

Verify:

- `DATA_ENTRY` cannot review/approve
- `BANK_REVIEWER` cannot upload SWIFT
- `SUPPORT_COMMITTEE` cannot vote
- `EXECUTIVE_MEMBER` cannot finalize
- `CBY_ADMIN` cannot upload or finalize external FX confirmation documents through the Director-only workflow path

### Document permissions

Verify:

- Each role can download only the document types it is allowed to access
- Unauthorized document URLs are blocked by backend policy, not just hidden in UI

### Immutable and locked states

Verify:

- Terminal states reject mutation attempts
- Locked states reject edits even if the request is still visible
- Completed/external-FX-confirmation data is no longer editable

## Part 5: Dashboard Expectations by Role

Use this as a fast acceptance checklist.

### `DATA_ENTRY`

- Sees simplified business statuses
- Sees drafts, returned, processing, completed
- Sees new-request action

### `BANK_REVIEWER`

- Sees review queue
- Sees downstream tracking
- No SWIFT, support, or voting actions

### `BANK_ADMIN`

- Sees bank operations, staff, merchants, reports
- No reviewer governance actions

### `SWIFT_OFFICER`

- Sees SWIFT queue and upload action
- No non-SWIFT workflow controls

### `SUPPORT_COMMITTEE`

- Sees claim-aware support queue
- Sees claimed/unclaimed states clearly

### `EXECUTIVE_MEMBER`

- Sees voting queue
- Can vote only when session is open
- Sees which executive members have not yet voted

### `COMMITTEE_DIRECTOR`

- Sees voting lifecycle controls
- Sees FX-confirmation-pending queue

### `CBY_ADMIN`

- Sees global oversight, compliance, reports, and admin areas
- Does not become Director by visibility alone

## Suggested Test Execution Order

Run in this order:

1. Role smoke tests
2. Happy-path end-to-end flow
3. Return/rejection branches
4. Claim expiry and voting-completion enforcement
5. Permission and document security checks
6. Final dashboard regression pass

## Exit Criteria

Testing is considered complete when:

- Every role has passed smoke testing
- One request has successfully gone from `DRAFT` to `COMPLETED`
- All major rejection/return branches have been tested
- Document permissions have been verified
- Locked and terminal states block mutation correctly
- No role sees controls that belong to another role

## Recommended Result Format

For each test run, log:

- Test ID
- Role
- Request reference number
- Start status
- Action
- Expected result
- Actual result
- Pass / Fail
- Screenshot link or filename
- Notes

Example:

| Test ID | Role | Start status | Action | Expected | Actual | Result |
| --- | --- | --- | --- | --- | --- | --- |
| E2E-10 | `SWIFT_OFFICER` | `WAITING_FOR_SWIFT` | Upload SWIFT + FX request PDFs | Status moves to `FX_CONFIRMATION_PENDING` | Matched | Pass |

## Final Note

If time is limited, do not skip the handoff points. The highest-risk moments in this system are where ownership changes between roles:

- `DATA_ENTRY` -> `BANK_REVIEWER`
- `BANK_REVIEWER` -> `SUPPORT_COMMITTEE`
- `SUPPORT_COMMITTEE` -> executive voting side
- executive voting side -> `SWIFT_OFFICER`
- `SWIFT_OFFICER` -> `COMMITTEE_DIRECTOR`
- `COMMITTEE_DIRECTOR` -> completed external FX confirmation outcome
