# Frontend Guide

# Frontend Overview

The frontend of Yemen Flow Hub is built as a workflow-oriented internal dashboard application.

The frontend is responsible for:

- Authentication UI
- Request management
- Workflow tracking
- Voting interfaces
- SWIFT upload
- Request timelines
- Role-based dashboards
- Organization-scoped visibility
- Role-scoped operational actions
- Queue-based operational dashboards
- Business-status abstraction

The UI must prioritize:

- Clarity
- Speed
- RTL usability
- Operational workflow visibility

---

# Frontend Stack

## Core Stack

- Nuxt 4
- Vue 4
- TypeScript
- Tailwind CSS v4
- shadcn-vue
- Pinia
- VueUse
- VeeValidate
- Zod

---

# Frontend Architecture Principles

## Workflow-Oriented UI

The frontend is not a generic admin panel.

All screens should revolve around:

- Workflow stages
- Queues
- Status tracking
- Approval actions
- Role-specific responsibilities

---

## RTL-First Design

The entire application must be built in RTL from the beginning.

Requirements:

- dir="rtl"
- Arabic typography
- RTL spacing
- RTL layouts
- RTL table alignment
- RTL form alignment

---

## Role-Aware UI

Every role sees different:

- Navigation items
- Pages
- Actions
- Buttons
- Workflow queues

## Organization-Scoped Operational UI

The frontend must NOT behave like a shared admin dashboard.

The platform uses:

- Organization-scoped visibility
- Role-scoped workflow actions
- Queue-based operational dashboards
- Business-oriented status presentation

All users inside the same bank can view all requests belonging to their bank.

However:

- Actions remain role-controlled
- Dashboards remain workflow-oriented
- UI visibility remains operationally scoped

Frontend permissions are for UI visibility only.

Backend authorization remains the source of truth.

---

## Per-Role UX Authority

Role-specific UI decisions (operational posture, dashboard structure, KPI semantics, banner variants, density, micro-copy, status presentation, non-visibility rules) are governed by the per-role specifications in `docs/user-view/`.

Authority order for any role-specific UI decision:

1. `docs/user-view/{role}.md` — the canonical per-role UX spec (8 files: data-entry, bank-reviewer, bank-admin, swift-officer, support-committee, executive-member, committee-director, cby-admin)
2. This document — generic frontend conventions

When `docs/user-view/` and a generic UI pattern in this document disagree, `docs/user-view/` wins for that role.

`docs/user-view/` covers, per role:

- Operational posture (work mode, primary surface, status language, visual density, decision tone)
- Sidebar navigation set
- Per-page structure (dashboard, requests list, request detail, role-specific pages)
- KPI cards (sources, colors, click-throughs)
- Banner variants and conditions
- Action panel behavior per status
- Forbidden actions / non-visibility enforcement
- Cross-role handoffs

UI work must start from the relevant `docs/user-view/{role}.md` rather than inventing role surfaces from scratch.

---

## Design Consistency Requirement

The UI prototype phase is complete. The shipped `frontend/` code is the visual source of truth. Every UI story must keep new and changed surfaces consistent with what is already built and with the design authority:

1. **Spec citation** — line reference into `docs/user-view/{role}.md` showing the intended operational posture and layout for the surface
2. **Token compliance** — every color, spacing, radius, shadow, and font drawn from `DESIGN.md` / `frontend/DESIGN.md`; no raw Tailwind color scales or hardcoded hex (see `frontend/SHADCN.md`)
3. **Pattern reuse** — the surface composes existing `frontend/` components and matches sibling pages of the same role rather than inventing new affordances

Screenshots for visual-regression of specific stories live under `docs/ui-parity/screenshots/` and are produced by the e2e gate specs (e.g. `frontend/tests/e2e/story-12-3-gates.spec.ts`). They capture the current app, not a separate prototype.

---

## Operational Density Composition

All roles share the same component library, but role surfaces compose differently based on operational posture:

| Posture | Roles | Density | Characteristics |
| ------- | ----- | ------- | --------------- |
| Distraction-free / low-density | SWIFT_OFFICER | Low | Focused upload work; minimal navigation; locked-data summaries; single-purpose pages |
| Operational queue density | DATA_ENTRY, BANK_REVIEWER, SUPPORT_COMMITTEE, EXECUTIVE_MEMBER | Medium | Queue-first; action-required strips; 3–4 KPI cards; primary work table |
| Governance / lifecycle density | COMMITTEE_DIRECTOR, CBY_ADMIN, BANK_ADMIN | High | Composite action strips (multi-row); 4–6 KPI grids; multiple queue/intelligence tables; oversight without operational claim authority |

Density is a property of the role's spec — not of the component library. The same `KpiCard`, `ActionRequiredStrip`, and `WorkflowProgress` components serve all three postures by composition. Do not fork components per role.

---

# Suggested Project Structure

```text
frontend/
├── app/
├── assets/
│
├── components/
│   ├── ui/
│   ├── forms/
│   ├── workflow/
│   ├── voting/
│   ├── dashboard/
│   ├── audit/
│   ├── tables/
│   └── layout/
│
├── composables/
├── layouts/
├── middleware/
├── pages/
├── plugins/
├── services/
├── stores/
├── types/
├── utils/
└── constants/
```

---

# Layout Structure

# 1. Auth Layout

Used for:

- Login page
- Authentication pages

---

# 2. Dashboard Layout

Main application layout.

Contains:

- Sidebar
- Header
- Notifications
- User menu
- Workflow navigation

---

# 3. Print Layout

Used for:

- External FX confirmation PDF views
- Printable pages

---

# Main Pages

# Authentication

```text
/login
```

---

# Dashboard

```text
/
/dashboard
```

Displays:

- Role-specific queues
- Operationally relevant requests
- Pending actions
- Workflow counts
- Queue summaries
- Quick workflow actions

Dashboards are operational workspaces, not global analytics pages.

---

# Requests (Dynamic Workflow Engine)

```text
/workflows
/workflows/new
/workflows/instances/[id]
```

The legacy `/requests`, `/requests/new`, and `/requests/[id]` routes no longer exist — the dynamic-engine request pages replaced them. Features:

- Request list
- Filters
- Search
- Status badges
- Timeline
- Documents
- Workflow actions based on role and request state (executed as stage transitions/actions, not a fixed per-status action set)
- Read-only workflow states after internal approval
- Organization-scoped request visibility
- Role-specific business statuses
- Queue-scoped request visibility

---

# Voting

Executive voting has no dedicated `/voting`/`/voting/[id]` route family — those legacy pages no longer exist. Voting is presented as part of the `/workflows/instances/[id]` request detail page when the request's current stage is a voting stage, using the same workflow actions/transitions UI as every other stage. Features:

- Pending votes
- Vote details
- Voting actions
- Vote summaries

---

# External FX Confirmation

```text
/customs
```

The legacy `/customs/[id]` detail route no longer exists; external FX confirmation content for a given request is presented on its `/workflows/instances/[id]` page. Features:

- External FX confirmation generation
- PDF preview
- Printable view

---

# Users & Banks (Admin)

The legacy top-level `/users` and `/banks` routes no longer exist. Admin management pages live under role-scoped paths instead:

```text
/admin/banks
/bank/users
/staff
```

Admin-only pages.

---

# Suggested Navigation by Role

# Data Entry

- Dashboard
- New Request
- Draft Requests
- Submitted Requests
- Returned Requests
- Rejected Requests
- Completed Requests

Data Entry dashboards should focus on business workflow visibility, NOT CBY operational internals.

---

# Bank Reviewer

- Dashboard
- Pending Internal Reviews
- Returned By Support Committee
- Bank Workflow Tracking
- Completed Requests

Bank Reviewers can monitor downstream workflow progress.

---

# SWIFT Officer

- Waiting for SWIFT Upload (status: `WAITING_FOR_SWIFT`)
- SWIFT Upload Queue (status: `SUPPORT_APPROVED` pending SWIFT action)
- Completed SWIFT Uploads (status: `SWIFT_UPLOADED` — retained for audit reference)

SWIFT Officers see requests in `SUPPORT_APPROVED` / `WAITING_FOR_SWIFT` as their active queue, and `SWIFT_UPLOADED` requests as a read-only historical list.

---

# Support Committee

- Pending Support Queue
- Claimed Reviews
- Approved Requests
- Rejected Requests
- Workflow Tracking

---

# Executive Committee

- Waiting For Voting Open
- Active Voting Sessions
- Voting History
- Final Decisions

---

# Executive Committee Director

- Voting Session Management
- Open Voting Sessions
- Closed Voting Sessions
- Final Decisions
- External FX Confirmation Issuance

---

# CBY Admin

- Dashboard
- Users
- Banks
- Audit Logs
- Reports
- External FX Confirmations

---

# State Management

# Pinia Stores

State management must support:

- Queue-based data
- Business-status mapping
- Role-specific visibility handling
- Voting session state management
- Workflow-aware visibility
- Role-scoped dashboards
- Real-time workflow transitions

Suggested stores:

```text
/auth.store.ts
/requests.store.ts
/workflow.store.ts
/voting.store.ts
/notifications.store.ts
```

---

# Composables

Suggested composables:

```text
/useAuth.ts
/usePermissions.ts
/useWorkflow.ts
/useVoting.ts
/useApi.ts
```

---

# API Structure

API calls should be separated from components.

Suggested structure:

```text
/services/api/
/services/auth/
/services/requests/
/services/voting/
```

---

# Authentication Flow

# Authentication Method

Using:

- Laravel Sanctum
- Secure HTTP-only cookies

---

# Frontend Authentication Responsibilities

- Login form
- Session handling
- Auth middleware
- Role hydration
- Protected routes
- Auto logout handling

---

# Middleware Structure

Suggested middleware:

```text
/auth.ts
/guest.ts
/admin.ts
/bank-reviewer.ts
/executive.ts
```

---

# Request UI Structure

# Request Details Page

The request details page is the most important page in the system.

It should contain:

- Request summary
- Current status
- Workflow timeline
- Documents section
- Approval actions
- Voting results
- Audit information
- Notes and comments
- Created By (`created_by_user` companion for `created_by`)
- Last Updated By (`last_updated_by_user` companion for `last_updated_by`)
- Submitted By (`submitted_by_user` companion for `submitted_by`)
- Internal Reviewer (`reviewed_by_user`, also aliased as `internal_reviewer`)
- Support Reviewer (`support_reviewed_by_user`, also aliased as `support_reviewer`)
- SWIFT Uploaded By (`swift_uploaded_by_user` companion for `swift_uploaded_by`)
- Rejected By (`rejected_by_user` companion for `rejected_by`)
- Resubmitted By (`resubmitted_by_user` companion for `resubmitted_by`)
- Voting Session Status (`voting_session_status`)
- Current Active Reviewer (`claimed_by`, also aliased as `support_claimed_by`)
- Locked/Read-only indicators

---

# Workflow Timeline

Every request should visually display:

- Workflow stages
- Current stage
- Previous actions
- Approval history
- Voting results
- Timestamps

---

# Queue-First Operational UX

The frontend should prioritize operational queues over analytics.

Every screen should help users answer:

"What operational work matters right now?"

The interface must prioritize:

- Pending work queues
- Workflow-specific actions
- Role-relevant statuses
- Actionable operational tasks
- Business workflow clarity

Avoid:

- Shared analytics dashboards
- Generic SaaS layouts
- Workflow-irrelevant metrics
- Excessive global visibility
- Unnecessary complexity

---

# Support Review Claiming UI

Support review claiming uses a temporary soft-lock model.

## Claim Behavior

When a support committee member actively reviews a request:

- The request becomes temporarily claimed
- Other support members cannot review simultaneously
- Other support users can still view the request
- The request displays current active reviewer

The claim automatically releases when:

- The reviewer leaves the page
- Session timeout occurs
- The connection closes

## UI Requirements

The frontend should display:

- Current active reviewer
- Claim status
- Claim timestamp
- Review lock indicators
- Disabled review actions for other users

Claim changes must update instantly in the UI.

---

# Status Badges

Status badges must be:

- Consistent
- RTL-friendly
- Operationally clear
- Business-oriented
- Role-aware

---

# Internal Workflow Statuses

Full canonical status enum (source of truth: `docs/03-database-and-models.md`):

- DRAFT
- DRAFT_REJECTED_INTERNAL
- SUBMITTED
- BANK_REVIEW
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

# Role-Specific Business Statuses

Data Entry users should NOT see detailed CBY operational workflow stages.

Examples of simplified statuses:

- Draft
- Returned For Correction
- Submitted To CBY
- Under CBY Processing
- Rejected
- Completed

Bank Reviewers may see more detailed workflow progress.

---

# Forms & Validation

# Form Handling

Use:

- VeeValidate
- Zod schemas

---

# Validation Rules

Frontend should validate:

- Required fields
- File sizes
- File types
- Numeric values
- Currency fields

Backend remains final validation authority.

---

# File Upload UI

The frontend must support:

- Multiple uploads
- Upload progress
- File previews
- File validation
- SWIFT-specific upload flow

---

# Voting UI

## Voting Session UX

Voting is session-based.

The Executive Committee Director controls:

- Opening voting
- Closing voting
- Finalizing voting results

---

## Vote Types

Users can:

- Approve
- Reject
- Abstain

The system may automatically assign:

- AUTO_ABSTAIN_TIMEOUT

for users who did not vote before session closure.

---

## Voting Interface Requirements

The voting interface should display:

- Request summary
- Documents
- Voting history
- Current vote counts
- Voting session status
- Director controls
- Final voting result

---

# Read-Only States

Some workflow stages require locked read-only interfaces.

Examples:

- After internal bank approval
- During active support review
- After support approval
- After SWIFT upload
- During executive voting
- After executive rejection
- After external FX confirmation issuance
- After completion

The UI must visually communicate locked workflow states.

Use:

- Disabled actions
- Lock indicators
- Read-only banners
- Timeline explanations
- Immutable workflow messaging

Executive rejected requests remain permanently locked.

External FX confirmation documents remain immutable permanently.

---

# Audit & Tracking UI

Request pages should display:

- Workflow history
- Stage transitions
- User actions
- Approval timestamps
- File upload history
- Voting session events
- Claim lifecycle history
- Workflow assignment history

---

# Styling Guidelines

# Typography

Use:

- IBM Plex Sans Arabic
- Inter (English fallback)

---

# UI Style

The interface should be:

- Clean
- Minimal
- Government-friendly
- Professional
- Easy to scan quickly
- Queue-oriented
- Operationally focused
- Organization-oriented
- Workflow-governed

Avoid:

- Heavy animations
- Complex interactions
- Over-designed dashboards
- Shared visibility admin panels
- Generic SaaS dashboards
- Analytics-heavy landing pages

---

# Mobile Responsiveness

The platform is desktop-first.

However:

- Executive voting pages should work on tablets/mobile
- Dashboards should remain readable on smaller screens

---

# Important Frontend Rules

# Never Trust Frontend Permissions

Frontend permissions are for:

- Hiding actions
- Improving UX

Backend authorization is mandatory.

---

# Do Not Put Business Logic Inside Components

Business logic should exist inside:

- composables
- stores
- services

Components should remain presentation-focused.

---

# Recommended Development Order

# Phase 1

- Authentication
- Dashboard layout
- Sidebar/navigation
- Request listing
- Request details page

---

# Phase 2

- Workflow actions
- Voting UI
- SWIFT upload
- Timeline
- Status badges

---

# Phase 3

- Notifications
- Audit pages
- Reports
- Print views
- Performance improvements

---

# Frontend Goal

Build a fast, RTL-first, workflow-oriented operational platform that feels like a real institutional banking governance system.

The frontend must prioritize:

- Queue-driven workflows
- Operational clarity
- Business-oriented visibility
- Role-specific workflow actions
- Organizational workflow ownership
- Workflow integrity
- Institutional usability

Avoid:

- Startup SaaS patterns
- Shared admin dashboards
- Analytics-first UX
- Generic CRUD interfaces
- Workflow-irrelevant UI patterns
