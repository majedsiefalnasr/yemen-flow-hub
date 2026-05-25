# Roles Reference

This document is the practical reference for every production role in Yemen Flow Hub.

It answers, for each role:

- What the role is
- What the role is responsible for
- What the role can do
- What the role cannot do
- What should appear in its dashboard
- What must not appear in its dashboard

This file is written in English, with Arabic labels where they help match the UI.

## Source of Truth

This document is derived from the current project source of truth, in this order:

1. `docs/01-workflow-and-business-rules.md`
2. `docs/03-database-and-models.md`
3. `docs/06-api-reference.md`
4. `docs/05-backend-guide.md`
5. `docs/04-frontend-guide.md`
6. `DESIGN.md`
7. Current backend and frontend enforcement (`TransitionMap`, policies, permission seeder, dashboards, sidebar)

If a UI hint conflicts with backend enforcement, the backend wins.

## Important Reading Rules

- This platform is not a shared SaaS dashboard. It is a role-scoped institutional workflow system.
- Requests belong to the bank entity, not to individual staff users.
- Visibility must be scoped by role and organization.
- Workflow actions are not free-form; every action is tied to role + status.
- Auditability is mandatory. Important actions must be recorded in both workflow history and audit logs.
- The Data Entry role and the Bank Reviewer role must remain separated.
- A reviewer cannot review or reject their own created request.
- The Committee Director is the executive chair role and also participates in the executive voting flow.
- Executive voting opens automatically after support approval.
- The Committee Director cannot close voting until all active executive members have voted.
- SWIFT upload happens after executive approval, not before executive voting.
- Customs declaration terminology has been replaced by external FX confirmation (`تأكيد مصارفة خارجية`).
- `COMMITTEE_DIRECTOR` is distinct from `EXECUTIVE_MEMBER`; the same user should not hold both roles simultaneously.

## High-Level Role Families

### Bank-side roles

- `DATA_ENTRY`
- `BANK_REVIEWER`
- `BANK_ADMIN`
- `SWIFT_OFFICER`

These roles are bank-scoped. They must have a non-null `bank_id` and cannot operate on other banks' requests.

### CBY-side roles

- `SUPPORT_COMMITTEE`
- `EXECUTIVE_MEMBER`
- `COMMITTEE_DIRECTOR`
- `CBY_ADMIN`

These roles are CBY/global roles. They are not bank-scoped and can see cross-bank information where the role permits it.

## Workflow Ownership by Stage

| Stage | Operational owner |
| --- | --- |
| Draft | Data Entry |
| Submitted / Bank Review | Bank Reviewer |
| Support review pending / in progress | Support Committee |
| Support approved / executive voting open | Executive side |
| Executive approved / waiting for SWIFT | SWIFT Officer |
| SWIFT uploaded / FX confirmation pending | Committee Director |
| Completed | Final read-only state |

## Document Download Scope

| Role | Request documents | SWIFT document | FX request document | External FX confirmation PDF |
| --- | --- | --- | --- | --- |
| `DATA_ENTRY` | Own bank only | No | No | No |
| `BANK_REVIEWER` | Own bank only | Own bank only | Own bank only | Own bank only |
| `BANK_ADMIN` | Own bank only | Own bank only | Own bank only | No |
| `SWIFT_OFFICER` | Own bank only | Own bank only | Own bank only | No |
| `SUPPORT_COMMITTEE` | All banks | No | No | No |
| `EXECUTIVE_MEMBER` | All banks | Yes | Yes | No |
| `COMMITTEE_DIRECTOR` | All banks | Yes | Yes | Yes |
| `CBY_ADMIN` | All banks | Yes | Yes | Yes |

## Implementation Notes

- The current implementation includes return/rejection statuses such as `BANK_RETURNED`, `SUPPORT_RETURNED`, and `BANK_REJECTED` in addition to older documentation language around `DRAFT_REJECTED_INTERNAL`.
- Bank Admin currently has create/edit/submit capability for drafts as an operational bank-level permission, but is still not a governance approver or reviewer.
- External FX confirmation completion is Director-only in backend enforcement, even if a UI or older assumption suggests broader access.

---

## 1. `DATA_ENTRY` - Bank Data Entry

Arabic label: `موظف إدخال البيانات`

### What this role is

The operational intake role inside a bank. This user prepares requests, uploads the required request documents, corrects returned submissions, and tracks the business outcome of the bank's own requests.

### Core mission

- Prepare new financing/import requests
- Save drafts
- Submit requests into the workflow
- Fix returned requests and resubmit them
- Track business outcomes after submission

### Visibility scope

- Can see requests belonging to the user's own bank
- Must not see other banks' requests
- Should see simplified business statuses, not deep CBY internal workflow detail

### What this role can do

- Create a draft request
- Edit own-bank drafts and returned requests
- Submit a request to the bank review stage
- Re-submit returned requests after correction
- Upload normal request documents for own-bank requests
- View notifications related to the bank's requests
- View request progress in a simplified business-friendly way

### What this role cannot do

- Cannot review requests as the bank reviewer
- Cannot approve or reject bank-stage requests
- Cannot claim support reviews
- Cannot approve or reject support-stage decisions
- Cannot upload SWIFT documents
- Cannot vote in executive workflow
- Cannot open, close, or finalize voting sessions
- Cannot issue, download for signing, or upload external FX confirmations
- Cannot see other banks' data
- Cannot see full CBY operational internals as the main business presentation

### Request/document permissions

- Request documents: yes, own bank only
- SWIFT document: no
- FX request document: no
- External FX confirmation PDF: no

### Dashboard should show

- Greeting + role subtitle
- A clear `New Request` action
- KPI cards focused on:
  - Drafts
  - Returned requests
  - Under CBY processing
  - Completed requests
- Quick actions such as:
  - New request
  - Follow my requests
  - Notifications
- A visible alert block when requests need correction
- Drafts table
- Recent activity / recent requests table
- Simplified statuses such as:
  - Draft
  - Returned for correction
  - Submitted to CBY
  - Under CBY processing
  - Rejected
  - Completed

### Dashboard must not show

- Support-claim ownership or reviewer assignment mechanics
- SWIFT upload controls
- Executive voting tallies
- Cross-bank analytics
- Audit/compliance internals
- Deep CBY stage-by-stage status labels as the primary UX

### Typical pages/navigation

- Dashboard
- Requests
- New Request
- Notifications

### Operational boundaries

- Once the request leaves bank approval, this role should mostly see a business-facing state such as "Under CBY Processing", "Rejected", or "Completed"
- This role should never become the approving/checking authority for the same workflow

---

## 2. `BANK_REVIEWER` - Bank Internal Reviewer

Arabic label: `مراجع داخلي بالبنك`

### What this role is

The internal control gate inside the bank. This role validates submitted requests before they leave the bank and monitors the downstream lifecycle after bank approval.

### Core mission

- Review submitted bank requests
- Approve eligible requests to move to CBY
- Return requests to Data Entry for correction
- Apply terminal bank rejection when needed
- Monitor downstream status after bank approval

### Visibility scope

- Can see all requests belonging to the user's own bank
- Can monitor downstream progress after the request leaves the bank
- Cannot see other banks' requests

### What this role can do

- Start bank review on submitted requests
- Approve requests at bank review stage
- Return a request to intake/Data Entry with a mandatory reason
- Permanently reject a request at bank review stage with a mandatory reason
- After a support rejection:
  - Keep the request rejected
  - Return it to Data Entry for correction
- Monitor support review progress
- Monitor executive voting progress
- Monitor SWIFT and FX request upload progress after executive approval
- Monitor final decision and external FX confirmation outcome

### What this role cannot do

- Cannot create requests as their primary business responsibility
- Cannot review a request they created themselves
- Cannot claim support-review work
- Cannot upload SWIFT documents
- Cannot cast executive votes
- Cannot finalize executive decisions
- Cannot issue, download for signing, or upload external FX confirmations
- Cannot manage users outside the bank
- Cannot see other banks' requests

### Request/document permissions

- Request documents: yes, own bank only
- SWIFT document: yes, own bank only
- FX request document: yes, own bank only
- External FX confirmation PDF: yes, own bank only

### Dashboard should show

- KPI cards focused on:
  - Pending review
  - At CBY
  - Returned by support
  - Approved/completed
- Quick actions leading to:
  - Review queue
  - Full bank request list
- A review queue table with:
  - Reference number
  - Supplier
  - Amount
  - Current status
  - Progress
  - Open/view action

### Dashboard must not show

- Cross-bank queues
- Support claim buttons
- SWIFT upload action buttons
- Voting controls
- External FX confirmation issuance/upload controls
- Generic admin analytics dashboard noise

### Typical pages/navigation

- Dashboard
- Requests
- Notifications

### Operational boundaries

- This role is the bank approval checkpoint
- It is explicitly separated from Data Entry for control and fraud-prevention reasons

---

## 3. `BANK_ADMIN` - Bank Administrator

Arabic label: `مسؤول البنك`

### What this role is

The bank-level operational administrator. This role manages bank users and bank master/operational surfaces. It is not the bank approval authority.

### Core mission

- Manage the bank's staff accounts
- Oversee the bank's request portfolio operationally
- Access bank-level reporting and administration
- Support draft/request preparation where the implementation allows it

### Visibility scope

- Can see users belonging to the same bank
- Can see requests belonging to the same bank
- Can see bank-level operational metrics
- Cannot see or manage other banks' staff/users

### What this role can do

- Manage own-bank users
- Create, update, activate, or deactivate own-bank staff
- Assign only bank-manageable roles:
  - `DATA_ENTRY`
  - `BANK_REVIEWER`
- View bank-level reports and trends
- Manage merchants where bank-scoped access is allowed
- In current implementation, create/edit/submit drafts for own-bank requests as an operational fallback

### What this role cannot do

- Cannot assign CBY roles
- Cannot manage users outside the bank
- Cannot act as Bank Reviewer approval authority
- Cannot claim support reviews
- Cannot approve or reject support-stage requests
- Cannot upload SWIFT or FX request documents unless they are actually the `SWIFT_OFFICER`
- Cannot vote or finalize executive decisions
- Cannot issue, download for signing, or upload external FX confirmations
- Cannot override workflow governance

### Request/document permissions

- Request documents: yes, own bank only
- SWIFT document: yes, own bank only
- FX request document: yes, own bank only
- External FX confirmation PDF: no

### Dashboard should show

- KPI cards focused on:
  - Total bank requests
  - Pending/internal review
  - Approved
  - Rejected
- Quick actions to:
  - Bank requests
  - Merchants
  - Bank staff
  - Reports
- Monthly trend chart for bank requests
- Recent bank requests table
- Bank operational overview, not CBY governance controls

### Dashboard must not show

- Review decision buttons intended for `BANK_REVIEWER`
- Support claim workflow
- SWIFT upload workflow
- Executive voting controls
- External FX confirmation issuance/upload controls
- Cross-bank compliance oversight

### Typical pages/navigation

- Dashboard
- Requests
- Staff
- Merchants
- Reports
- In current UI, may also access draft creation/new request flows

### Operational boundaries

- This role is an administrator, not a bank governance approver
- It can support operations, but it must not collapse the segregation between requester and reviewer

---

## 4. `SWIFT_OFFICER` - Bank SWIFT Officer

Arabic label: `موظف السويفت بالبنك`

### What this role is

The bank-side specialist responsible for uploading the SWIFT proof/document and the FX confirmation request document after executive approval.

### Core mission

- Work the post-executive-approval SWIFT queue
- Download the FX confirmation request template
- Upload the SWIFT document for eligible requests
- Upload the completed FX confirmation request document
- Provide a clean handoff to the Committee Director for external FX confirmation

### Visibility scope

- Can see waiting-for-SWIFT requests for the same bank after executive approval
- Can see own-bank requests relevant to the SWIFT stage
- Cannot see other banks' operational queues

### What this role can do

- View the SWIFT upload queue after executive approval
- Download the FX confirmation request template (`تحميل نموذج طلب تأكيد مصارفة`)
- Upload the SWIFT document for eligible requests
- Upload the FX confirmation request document for eligible requests
- Submit the SWIFT stage only after both required PDFs are uploaded
- Track own-bank requests through the SWIFT and FX request upload phase

### What this role cannot do

- Cannot review bank submissions
- Cannot claim support reviews
- Cannot approve or reject support-stage requests
- Cannot vote in executive workflow
- Cannot open/close/finalize voting
- Cannot issue, download for signing, or upload external FX confirmations
- Cannot see other banks' SWIFT queues

### Request/document permissions

- Request documents: yes, own bank only
- SWIFT document: yes, own bank only
- FX request document: yes, own bank only
- External FX confirmation PDF: no

### Dashboard should show

- KPI cards focused on:
  - Pending SWIFT upload
  - Uploaded
  - Final approved
  - Final rejected
- A quick action to open the SWIFT queue
- A queue table with:
  - Reference number
  - Bank
  - Amount
  - Status
  - Progress
  - `Download FX Request Template` action
  - `Upload SWIFT` action
  - `Upload FX Request` action

### Dashboard must not show

- Bank review actions
- Support claim/release actions
- Executive voting actions
- External FX confirmation issuance/upload actions
- Cross-bank analytics

### Typical pages/navigation

- Dashboard
- Requests
- Notifications
- Request-specific SWIFT upload view

### Operational boundaries

- SWIFT upload cannot begin until the executive decision is approved
- Both the SWIFT PDF and FX confirmation request PDF are required before the request can leave the SWIFT stage
- After both documents are submitted, the request becomes read-only for this stage and ownership moves forward to the Committee Director

---

## 5. `SUPPORT_COMMITTEE` - Support Committee Member

Arabic label: `عضو لجنة المساندة`

### What this role is

The CBY operational reviewer for support-stage validation. This role works a shared global queue using claim/release ownership rules.

### Core mission

- Work the support-review queue
- Claim a request for review
- Approve, reject, or return the request to intake
- Keep queue ownership disciplined and traceable

### Visibility scope

- Can see support-review queues across all banks
- Can see claimed reviews
- Can see active reviewers/claim ownership
- This is a CBY-global queue, not bank-scoped

### What this role can do

- Claim a support review
- Release a support review
- Keep a claim alive via heartbeat while actively reviewing
- Approve a claimed support review
- Reject a claimed support review
- Return a claimed support review to Data Entry/intake with a mandatory reason
- Monitor the support queue across banks

### What this role cannot do

- Cannot decide on a support request they have not claimed
- Cannot upload SWIFT documents
- Cannot vote in executive workflow
- Cannot open/close/finalize voting
- Cannot issue, download for signing, or upload external FX confirmations
- Cannot manage bank users
- Cannot act outside the support-review portion of the workflow

### Request/document permissions

- Request documents: yes, all banks
- SWIFT document: no
- FX request document: no
- External FX confirmation PDF: no

### Dashboard should show

- KPI cards focused on:
  - Waiting for claim
  - Active by me
  - Claimed by others
  - Recently approved
- Quick actions to:
  - Support queue
  - Notifications
- Support queue table with:
  - Reference number
  - Supplier
  - Amount
  - Status
  - Claim owner
  - Progress
  - View/open action
- Visual distinction between:
  - Claimed by me
  - Claimed by others
  - Unclaimed

### Dashboard must not show

- SWIFT upload controls
- Executive voting controls
- External FX confirmation issuance/upload controls
- Bank-admin staff management
- Bank-scoped-only assumptions

### Typical pages/navigation

- Dashboard
- Requests
- Notifications
- Reports if enabled for the role in the deployed UI

### Operational boundaries

- Claims expire after 15 minutes of inactivity
- The frontend must heartbeat every 60 seconds while the reviewer is active
- This role is operational, not executive/governance

---

## 6. `EXECUTIVE_MEMBER` - Executive Committee Member

Arabic label: `عضو اللجنة التنفيذية`

### What this role is

The executive decision participant. This role votes on requests after support approval automatically opens executive voting.

### Core mission

- Review executive-stage requests
- Cast one vote per request/session
- Participate in the final governance decision

### Visibility scope

- Can see executive voting queues across all banks
- Can see finalized executive requests
- Can view voting details relevant to the session

### What this role can do

- View open voting sessions after support approval
- Cast a vote during active executive voting
- View voting outcomes and finalized decisions
- View vote-completion state, including which executive members have not yet voted where permitted
- View executive-level reports where enabled

### What this role cannot do

- Cannot manually open a voting session
- Cannot close a voting session
- Cannot finalize the executive decision
- Cannot issue, download for signing, or upload external FX confirmations
- Cannot upload SWIFT documents
- Cannot perform support-review claim/approval actions
- Cannot manage banks, users, settings, or document rules

### Request/document permissions

- Request documents: yes, all banks
- SWIFT document: yes
- FX request document: yes
- External FX confirmation PDF: no

### Dashboard should show

- KPI cards focused on:
  - Active voting sessions
  - Approved decisions
  - Rejected decisions
- Quick actions to:
  - Voting queue
  - Reports
- Voting queue table with:
  - Reference number
  - Supplier
  - Amount
  - Status
  - Voting completion state
  - View/open action

### Dashboard must not show

- Manual open-voting controls
- Close/finalize controls reserved to the Director
- External FX confirmation issuance/upload controls
- SWIFT upload controls
- Support-claim controls
- System admin surfaces

### Typical pages/navigation

- Dashboard
- Requests
- Notifications
- Reports

### Operational boundaries

- Voting is only allowed during the executive voting stage
- Executive voting opens automatically after support approval
- Each active executive member must vote before the Director can close voting
- Each member may vote once
- Votes become locked after finalization

---

## 7. `COMMITTEE_DIRECTOR` - Executive Committee Director

Arabic label: `مدير اللجنة التنفيذية`

### What this role is

The executive chair and final workflow authority. This role closes/finalizes automatically opened voting sessions, resolves final executive outcomes, and completes the external FX confirmation workflow after SWIFT upload.

### Core mission

- Monitor automatically opened executive voting sessions
- Close executive voting only after all active executive members have voted
- Finalize the executive outcome
- Resolve tie scenarios using the director decision path where applicable
- Download the generated external FX confirmation PDF
- Upload the signed/stamped external FX confirmation PDF to complete the workflow

### Visibility scope

- Can see executive-stage requests across all banks
- Can see voting sessions and their tallies
- Can see FX-confirmation-pending requests after SWIFT upload
- Can access high-level governance/audit surfaces

### What this role can do

- Vote as part of the executive process
- Monitor automatically opened voting sessions
- Close a voting session only after all active executive members have voted
- Finalize a voting decision
- Apply a director override/tie-break decision where required
- Download generated external FX confirmation PDFs for approved requests after SWIFT upload
- Upload signed/stamped external FX confirmation PDFs
- Complete requests after signed/stamped external FX confirmation upload
- Access reports and audit/compliance views where enabled

### What this role cannot do

- Cannot act as Data Entry
- Cannot act as Support Committee claimant/reviewer
- Cannot upload SWIFT documents
- Cannot manually open executive voting; voting opens automatically after support approval
- Cannot manage CBY system settings the way `CBY_ADMIN` does
- Should not use this role as a generic super-admin account

### Request/document permissions

- Request documents: yes, all banks
- SWIFT document: yes
- FX request document: yes
- External FX confirmation PDF: yes

### Dashboard should show

- KPI cards focused on:
  - Active/open voting workload
  - Approved decisions
  - Rejected decisions
  - Director-specific workload if applicable
- Quick actions to:
  - Voting queue
  - Reports
- Voting queue table
- FX confirmation pending table for SWIFT-uploaded requests
- Director-only workflow controls on eligible requests:
  - Close voting when all active executive members have voted
  - Finalize decision
  - Director override/tie-break where applicable
  - Download external FX confirmation PDF
  - Upload signed/stamped external FX confirmation PDF

### Dashboard must not show

- Bank-admin user-management widgets as the main role identity
- SWIFT upload workflow controls
- Support claim workflow controls
- Any "shared admin dashboard" abstraction that hides the governance-specific nature of the role

### Typical pages/navigation

- Dashboard
- Requests
- External FX Confirmation
- Reports
- Audit
- Notifications

### Operational boundaries

- External FX confirmation can only be completed after `SWIFT_UPLOADED` / `FX_CONFIRMATION_PENDING`
- Voting cannot be closed until all active executive members have voted
- Executive rejection is final
- The Director is a workflow authority, not a free-form override of all platform constraints

---

## 8. `CBY_ADMIN` - Central System Administrator

Arabic label: `مسؤول النظام (CBY)`

### What this role is

The platform/system administrator for CBY. This role owns global visibility, entities, staff, settings, document rules, and platform administration. It is not the business owner of every workflow action.

### Core mission

- Administer the platform
- Manage banks/entities and CBY-side system users
- Maintain document rules and settings
- Monitor system-wide activity, compliance, and analytics

### Visibility scope

- Full system visibility across all banks
- Cross-bank operational and analytical visibility
- Access to admin surfaces and system settings

### What this role can do

- View all requests across all banks
- View global dashboard metrics
- Manage banks/entities
- Manage system users
- Manage roles/permissions reference surfaces
- Manage document rules
- Access system settings
- Access audit/compliance surfaces
- Access cross-bank reporting and analytics
- Download external FX confirmation PDFs where permitted

### What this role cannot do

- Must not be treated as a substitute for every workflow actor
- Cannot upload SWIFT documents as a business workflow action
- Cannot cast executive votes unless the user actually holds an executive role
- Cannot finalize voting unless the user actually holds Director authority
- Cannot complete external FX confirmation in backend-enforced workflow rules
- Cannot bypass immutable workflow-state protections

### Request/document permissions

- Request documents: yes, all banks
- SWIFT document: yes
- FX request document: yes
- External FX confirmation PDF: yes

### Dashboard should show

- Global KPI cards such as:
  - Total requests
  - In-process requests
  - Approved requests
  - Number of active banks
- Quick actions to:
  - Request registry
  - Reports
  - System users
  - Audit/events
- Cross-bank charts, for example:
  - Monthly request movement
  - Category distribution
- Recent requests table
- Compliance/risk insight panels, for example:
  - Duplicate suppliers
  - High-value requests
  - Stale pending requests
  - Most active banks

### Dashboard must not show

- Role-inappropriate workflow buttons such as:
  - SWIFT upload
  - Support claim/release
  - Vote casting
  - Director finalization actions
- Bank-scoped-only assumptions
- Simplified business-only view intended for Data Entry users

### Typical pages/navigation

- Dashboard
- Requests
- Reports
- Audit
- Notifications
- Banks/entities admin
- CBY staff admin
- Roles/permissions admin
- Workflow document rules
- Settings

### Operational boundaries

- This role is a platform administrator, not the default workflow approver
- Full visibility does not mean unlimited workflow action authority

---

## Summary Matrix

| Role | Main job | Scope | Key decisions | Dashboard style |
| --- | --- | --- | --- | --- |
| `DATA_ENTRY` | Prepare and submit requests | Own bank | No approval authority | Simple business dashboard |
| `BANK_REVIEWER` | Internal bank review | Own bank | Bank approve / return / terminal reject | Review queue dashboard |
| `BANK_ADMIN` | Bank administration | Own bank | No governance decisions | Bank ops dashboard |
| `SWIFT_OFFICER` | Upload SWIFT and FX request documents | Own bank | SWIFT + FX request upload only | Post-approval SWIFT work queue |
| `SUPPORT_COMMITTEE` | Support review | All banks | Claim / approve / reject / return | Queue + claim ownership |
| `EXECUTIVE_MEMBER` | Vote on decisions | All banks | Cast vote | Voting dashboard |
| `COMMITTEE_DIRECTOR` | Final executive authority | All banks | Close/finalize voting, complete external FX confirmation | Governance dashboard |
| `CBY_ADMIN` | Platform administration | All banks | System admin, not workflow governance by default | Global admin/compliance dashboard |

## Final Guidance

- If you are deciding what a role should see, start from the role's operational queue and only then add supporting metrics.
- If you are deciding whether a role can perform an action, check backend transition rules and permissions, not only frontend visibility.
- If there is any doubt, prefer least privilege and role separation.
