---
stepsCompleted: ["step-01-validate-prerequisites", "step-02-design-epics", "step-03-create-stories", "step-04-final-validation"]
inputDocuments:
  - docs/00-project-brief.md
  - docs/01-workflow-and-business-rules.md
  - docs/02-system-architecture.md
  - docs/03-database-and-models.md
  - docs/04-frontend-guide.md
  - docs/05-backend-guide.md
  - docs/06-api-reference.md
  - docs/07-task-breakdown.md
  - DESIGN.md
  - _bmad-output/planning-artifacts/project-context.md
  - lovable/ (approved UX reference — workflow, dashboards, component hierarchy, RTL patterns)
---

# Yemen Flow Hub - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for Yemen Flow Hub, decomposing requirements from the domain-specific architecture documents (docs/), visual design system (DESIGN.md), and approved UX reference (lovable/) into implementable stories.

**Source Authority:**
- docs/01-workflow-and-business-rules.md — highest authority for all workflow behavior
- docs/ — architecture and business source of truth
- lovable/ — approved operational UX baseline (interaction patterns, screen structure, component hierarchy)
- DESIGN.md — visual design system (colors, typography, layout, component styling)

**Confirmed Governance Rules (non-negotiable):**
1. **Organization-scoped visibility** — requests belong to the bank entity, not individual users. Any DATA_ENTRY user inside a bank can access all bank requests.
2. **Business-status abstraction** — DATA_ENTRY users see simplified statuses only; never internal CBY operational stages.
3. **SUBMITTED vs BANK_REVIEW** — SUBMITTED is the queue/waiting state; BANK_REVIEW is the active-review state (Bank Reviewer has opened the request). Parallel to SUPPORT_REVIEW_PENDING vs SUPPORT_REVIEW_IN_PROGRESS.
4. **Support claims are temporary and presence-based** — not permanent assignments. Auto-release on disconnect, navigation, or 15-min timeout.
5. **No quorum for executive voting** — Director closes session at any time; non-voters get AUTO_ABSTAIN_TIMEOUT.
6. **Customs declaration: COMMITTEE_DIRECTOR only** — no separate customs officer role.
7. **PDF only for all uploads** — request documents, SWIFT, customs; all other file types rejected.
8. **Read-only unlock path** — a post-bank-approval request only becomes editable again via SUPPORT_REJECTED → Bank Reviewer explicitly returns to Data Entry → DRAFT_REJECTED_INTERNAL.
9. **Dashboards are operational queues** — not analytics. Each role sees only actionable workflow-relevant work.
10. **EXECUTIVE_REJECTED is terminal** — no admin override, no reopening, ever.

---

## Requirements Inventory

### Functional Requirements

**Authentication & Session**

FR1: The system must authenticate users via email/password using Laravel Sanctum (HTTP-only cookie session).
FR2: The system must expose a `GET /api/auth/me` endpoint that returns the authenticated user's id, name, role, and bank_id.
FR3: The system must log out users by invalidating the session via `POST /api/auth/logout`.
FR4: The system must enforce login rate limiting of 5 attempts per minute per IP.
FR5: The system must lock accounts after 10 consecutive failed logins (15-minute lockout).
FR6: The system must log failed authentication attempts to audit_logs with user_id: NULL.
FR7: The frontend must protect all non-login routes with auth middleware and redirect unauthenticated users to /login.
FR8: The frontend must hydrate the current user's role on login and make it available system-wide.

**User & Bank Management**

FR9: CBY Admin can create, list, and update system users.
FR10: CBY Admin can create, list, and update commercial banks.
FR11: Bank users must have a non-null bank_id; CBY users must have bank_id = NULL.
FR12: The system must support 7 canonical roles: DATA_ENTRY, BANK_REVIEWER, SWIFT_OFFICER, SUPPORT_COMMITTEE, EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR, CBY_ADMIN.

**Import Request — Creation & Editing**

FR13: DATA_ENTRY users can create import requests with fields: currency (USD/EUR/SAR), amount, supplier_name, goods_description, port_of_entry, and optional notes.
FR14: Requests are auto-assigned a unique request_number on creation.
FR15: DATA_ENTRY users can edit requests in DRAFT or DRAFT_REJECTED_INTERNAL status only.
FR16: DATA_ENTRY users can delete requests in DRAFT status only.
FR17: Any DATA_ENTRY user within the same bank can edit, continue, or resubmit any editable request belonging to their bank — requests are bank-owned, not user-owned. The workflow must never depend on a specific employee existing.
FR18: DATA_ENTRY users can upload PDF documents to a request while it is editable.
FR19: DATA_ENTRY users can remove documents from a request while it is editable.
FR20: All document uploads must validate PDF-only file type.

**Import Request — Submission & Bank Review**

FR21: DATA_ENTRY users can submit a draft request, transitioning it to SUBMITTED status.
FR22: SUBMITTED = request submitted by Data Entry and waiting in the Bank Reviewer queue. BANK_REVIEW = Bank Reviewer is actively reviewing internally (the reviewer has opened the request). When a BANK_REVIEWER begins active review of a SUBMITTED request, status transitions to BANK_REVIEW. This is the bank-internal active-review state — conceptually parallel to SUPPORT_REVIEW_IN_PROGRESS but occurring inside the bank review stage. It must be handled consistently in WorkflowService, frontend status handling, and queue logic.
FR23: BANK_REVIEWER can approve a BANK_REVIEW request → BANK_APPROVED.
FR24: BANK_REVIEWER can reject a BANK_REVIEW request (before approval) → DRAFT_REJECTED_INTERNAL.
FR25: The same user who created a request cannot be the BANK_REVIEWER who approves it (separation of duties).
FR26: After BANK_APPROVED, the request becomes permanently locked for editing.

**Support Committee Review**

FR27: SUPPORT_COMMITTEE users can view all requests in SUPPORT_REVIEW_PENDING or SUPPORT_REVIEW_IN_PROGRESS queues.
FR28: A SUPPORT_COMMITTEE user can claim a SUPPORT_REVIEW_PENDING request for active review (atomic operation). Claims are TEMPORARY and PRESENCE-BASED — not permanent assignments.
FR29: Claiming a request transitions it to SUPPORT_REVIEW_IN_PROGRESS and sets support_claimed_by and support_claimed_at.
FR30: Only one SUPPORT_COMMITTEE user may actively review (claim) a request at a time.
FR31: The claim TTL is 15 minutes of inactivity; it auto-releases if no heartbeat is received. Claims also auto-release on disconnect or navigation away.
FR32: The frontend must send a heartbeat ping (POST /api/workflow/{id}/claim-support-review/heartbeat) every 60 seconds while the reviewer is on the request page.
FR33: The frontend must call DELETE /api/workflow/{id}/claim-support-review when the reviewer navigates away (presence-based release).
FR34: When a claim auto-releases (timeout, disconnect, or navigation), status reverts to SUPPORT_REVIEW_PENDING, support_claimed_by → NULL, support_claimed_at → NULL. The request re-enters the queue for any support member to claim.
FR35: The claim holder can approve the request → SUPPORT_APPROVED (then WAITING_FOR_SWIFT).
FR36: The claim holder can reject the request with a mandatory reason → SUPPORT_REJECTED.
FR37: After SUPPORT_REJECTED, the request appears in the BANK_REVIEWER's queue.
FR38: BANK_REVIEWER can return a SUPPORT_REJECTED request to Data Entry → DRAFT_REJECTED_INTERNAL (request becomes editable again). This is the ONLY path by which a post-approval request becomes editable — it requires both a support rejection AND an explicit Bank Reviewer return action.
FR39: BANK_REVIEWER can finalize a SUPPORT_REJECTED request as permanently rejected (status remains SUPPORT_REJECTED). The request becomes a terminal workflow artifact for the bank.

**SWIFT Upload**

FR40: SWIFT_OFFICER users can view requests in WAITING_FOR_SWIFT status belonging to their bank.
FR41: SWIFT_OFFICER can upload a SWIFT document (PDF only) → transitions status to SWIFT_UPLOADED then WAITING_FOR_VOTING_OPEN. Only PDF files are accepted; all other file types must be rejected.
FR42: The SWIFT document cannot be replaced or deleted after upload — it is immutable from the moment of successful upload.
FR43: After SWIFT upload, the request is fully read-only; no request data or documents can be modified.

**Executive Voting**

FR44: COMMITTEE_DIRECTOR can open a voting session for a WAITING_FOR_VOTING_OPEN request → EXECUTIVE_VOTING_OPEN.
FR45: EXECUTIVE_MEMBER and COMMITTEE_DIRECTOR can cast votes (APPROVE / REJECT / ABSTAIN) on an EXECUTIVE_VOTING_OPEN request.
FR46: Each executive member can vote exactly once per request. No vote changes are permitted after the voting session is closed.
FR47: Votes are locked and immutable after voting session closure.
FR48: COMMITTEE_DIRECTOR can close the voting session at any time — there is NO quorum requirement.
FR49: On session closure, any executive member who did not vote is automatically assigned AUTO_ABSTAIN_TIMEOUT.
FR50: AUTO_ABSTAIN_TIMEOUT is semantically distinct from manual ABSTAIN (different meaning in audit trail and tally logic).
FR51: After session closure, COMMITTEE_DIRECTOR finalizes the executive decision: majority wins; Director's vote resolves ties; no quorum is required.
FR52: Decision logic: approve > reject → EXECUTIVE_APPROVED; reject > approve → EXECUTIVE_REJECTED; on tie, Director's vote is the deciding vote.
FR53: If Director has not voted on a tie, the decision defaults to EXECUTIVE_REJECTED (safe stance).
FR54: Vote submission and session closure must use database-level pessimistic locking (lockForUpdate) to prevent race conditions.
FR55: EXECUTIVE_REJECTED is a permanent terminal state — the request cannot be reopened, edited, resubmitted, or overridden by any role including CBY_ADMIN. No admin override exists.

**Customs Declaration**

FR56: Only COMMITTEE_DIRECTOR can generate and issue customs declarations — there is no separate customs officer role.
FR57: Customs declaration generation must occur within a single database transaction.
FR58: The generated declaration is a printable RTL PDF containing official approval information and a unique declaration_number.
FR59: After customs declaration issuance, the request status becomes CUSTOMS_DECLARATION_ISSUED then COMPLETED.
FR60: Customs declarations are permanent immutable artifacts — they cannot be deleted, replaced, or modified after issuance.
FR61: COMMITTEE_DIRECTOR can download the customs declaration PDF.

**Visibility & Scoping**

FR62: All request queries must be organization-scoped — users must never receive requests outside their bank scope.
FR63: DATA_ENTRY users see ALL requests belonging to their bank (organization-scoped, not creator-scoped), but receive simplified business statuses — never internal CBY operational statuses.
FR64: BANK_REVIEWER sees all requests belonging to their bank including downstream CBY workflow progress.
FR65: SWIFT_OFFICER sees only requests in SUPPORT_APPROVED / WAITING_FOR_SWIFT / SWIFT_UPLOADED for their bank.
FR66: SUPPORT_COMMITTEE sees all support review queues (SUPPORT_REVIEW_PENDING, SUPPORT_REVIEW_IN_PROGRESS) and can see who has claimed each request.
FR67: EXECUTIVE_MEMBER and COMMITTEE_DIRECTOR see the executive voting queues (WAITING_FOR_VOTING_OPEN, EXECUTIVE_VOTING_OPEN, EXECUTIVE_VOTING_CLOSED, EXECUTIVE_APPROVED, EXECUTIVE_REJECTED).
FR68: CBY_ADMIN has full system visibility across all banks and all statuses.

**Business Status Abstraction**

FR69: DATA_ENTRY users must see simplified business statuses as defined in docs/01-workflow-and-business-rules.md (e.g., BANK_APPROVED → "Under CBY Processing", EXECUTIVE_APPROVED → "Completed").
FR70: Internal CBY operational statuses (SUPPORT_REVIEW_IN_PROGRESS, WAITING_FOR_SWIFT, etc.) must not be exposed to DATA_ENTRY users.

**Audit Logging**

FR71: Every workflow transition must create both a request_stage_history record and an audit_logs record.
FR72: Audit log entries must capture: user_id, role (at time of action), action, entity_type, entity_id, from_status, to_status, metadata, created_at.
FR73: The role field in audit_logs captures the user's role at the time of the action (not current role).
FR74: Non-transition audit events (login, file upload) must also be logged; from_status and to_status will be NULL for these.
FR75: The system must expose GET /api/requests/{id}/history returning all workflow transitions for a request.
FR76: CBY_ADMIN can access the full audit log via GET /api/audit.

**Document Download**

FR77: Document downloads must enforce the permission matrix defined in docs/06-api-reference.md.
FR78: DATA_ENTRY may download request documents for their own bank only; no SWIFT or customs access.
FR79: BANK_REVIEWER may download request documents and SWIFT for their own bank, and customs for their own bank.
FR80: SUPPORT_COMMITTEE may download request documents for all banks; no SWIFT or customs access.
FR81: EXECUTIVE_MEMBER may download request documents and SWIFT for all banks; no customs access.
FR82: COMMITTEE_DIRECTOR and CBY_ADMIN may download all document types.

**Dashboard & Queue APIs**

FR83: Dashboard API (GET /api/dashboard/stats) must return role-specific operational queue counts, not global analytics.
FR84: DATA_ENTRY dashboard queue must include: draft count, returned count, submitted count, rejected count, completed count.
FR85: BANK_REVIEWER dashboard must include: pending internal review count, CBY processing count, returned by support count.
FR86: SWIFT_OFFICER dashboard must show: pending SWIFT upload count.
FR87: SUPPORT_COMMITTEE dashboard must show: pending support queue count, claimed by me count, claimed by others count.
FR88: Executive dashboard must show: waiting for voting open count, active voting sessions count, finalized decisions count.

**Immutable State Enforcement**

FR89: Any mutation on a terminal immutable state (EXECUTIVE_REJECTED, CUSTOMS_DECLARATION_ISSUED, COMPLETED) must return HTTP 403 with error_code WORKFLOW_IMMUTABLE_STATE.
FR90: Any edit attempt on a locked (non-terminal) state must return HTTP 422 with error_code WORKFLOW_LOCKED_STATE.
FR91: current_status on the ImportRequest model must only be mutated through WorkflowService::transition(); direct attribute assignment must throw DirectStatusMutationException.

---

### Non-Functional Requirements

NFR1: **Security — Auth Rate Limiting:** Login endpoint enforces 5 attempts/minute per IP; accounts lock after 10 consecutive failures for 15 minutes.
NFR2: **Security — Authorization:** All permissions validated on the backend; frontend visibility is UI-only, never trusted for access control.
NFR3: **Security — Organization Scoping:** Visibility rules enforced at query, API, policy, and service levels — never frontend-only.
NFR4: **Security — File Access:** All document storage is private; downloads require backend policy validation.
NFR5: **Security — CSRF:** CSRF tokens validated via Sanctum SPA mode.
NFR6: **Security — Session:** Session fixation protection on login; HTTP-only cookies.
NFR7: **Security — Audit:** Failed authorization attempts (role mismatch, wrong workflow state) are logged to audit_logs.
NFR8: **Performance — Concurrency:** Vote submission and voting session closure use database-level pessimistic locking (lockForUpdate).
NFR9: **Performance — Claims:** Support claim TTL managed via Redis key `support_claim:{request_id}` with 15-minute TTL.
NFR10: **Performance — Indexes:** Required DB indexes: request_number, current_status, support_claimed_by, support_claimed_at, bank_id, voting_session_status, voting_opened_at, voting_closed_at, voted_at, created_at.
NFR11: **Reliability — Transactions:** Customs declaration generation must be wrapped in a single database transaction.
NFR12: **Reliability — Immutability:** Workflow transitions are atomic; EXECUTIVE_REJECTED and COMPLETED states are permanently immutable.
NFR13: **Maintainability — Architecture:** Business logic lives only in Services, Actions, Policies — never in Controllers, Models, or Vue components.
NFR14: **Maintainability — Workflow Engine:** All workflow transitions go through WorkflowService::transition(); no direct status mutations.
NFR15: **Usability — RTL:** The entire frontend is built RTL-first with dir="rtl"; all layouts, tables, forms, and components default to right-to-left flow.
NFR16: **Usability — Arabic Typography:** IBM Plex Sans Arabic for Arabic content; Inter for English; antialiased font rendering.
NFR17: **Usability — Desktop-First:** Platform targets desktop as primary; executive voting pages must also work on tablets.
NFR18: **Usability — Responsive Breakpoint:** Graceful degradation at ≤600px: sidebar → top nav, cards stack full-width, tables collapse to key-value pairs.
NFR19: **Usability — Accessibility:** 4.5:1 contrast minimum; all icons/buttons have ARIA labels; all actions keyboard-accessible; no color-only indicators.
NFR20: **Usability — Operational Clarity:** Every dashboard answers "what work is relevant to this user right now?" — not analytics or global statistics.
NFR21: **API — Consistency:** All API responses follow the defined success/error format: { success, message, data/errors }.
NFR22: **API — REST:** RESTful endpoints; workflow actions follow workflow-centric path convention (e.g., /api/workflow/{id}/support-approve).

---

### Additional Requirements (Architecture)

AR1: **Backend Project Setup:** Laravel 11 with PHP 8.3+, Sanctum, MySQL, Redis, Queue Workers — no starter template; greenfield setup per docs/05-backend-guide.md.
AR2: **Frontend Project Setup:** Nuxt 4, Vue 4, TypeScript, Tailwind CSS v4, shadcn-vue, Pinia, VueUse, VeeValidate, Zod — RTL configured from project init.
AR3: **Database Migrations:** All 8 core tables (banks, users, import_requests, request_documents, request_votes, request_stage_history, audit_logs, customs_declarations) must be created via migrations with all fields from docs/03-database-and-models.md.
AR4: **Enums:** PHP enums and TypeScript enums must match exactly the canonical status, role, currency, vote, and voting_session_status enums.
AR5: **WorkflowService:** Centralized service class with transition() method that: validates current status, validates user role, enforces org scope, runs transition atomically, writes to request_stage_history AND audit_logs, binds/releases `workflow.transition.active` container key.
AR6: **VotingService:** Separate service for vote creation, tally calculation, tie resolution, AUTO_ABSTAIN_TIMEOUT assignment, and final decision locking.
AR7: **AuditService:** Service responsible for all audit logging; called by WorkflowService, VotingService, DocumentService, and auth handlers.
AR8: **DocumentService:** Handles file validation (PDF-only), private storage at /storage/requests/{id}/, /storage/swift/{id}/, /storage/customs/{id}/, immutability enforcement.
AR9: **Pinia Stores:** auth.store.ts, requests.store.ts, workflow.store.ts, voting.store.ts — business logic in stores/composables, not in Vue components.
AR10: **API Service Layer:** All API calls abstracted into /services/api/, /services/requests/, /services/voting/ — no direct fetch calls from components.
AR11: **Middleware:** Frontend route middleware: auth.ts, guest.ts, and role-specific guards.
AR12: **Redis:** Used for queue workers (notifications, PDF generation, background jobs) and support claim TTL keys.
AR13: **Seeding:** Database seeders for demo users (all 7 roles), banks (minimum 3), and sample import requests covering all workflow stages.
AR14: **voting_session_status:** Denormalized sub-state cache field; must be kept in sync with current_status during every voting-phase transition by WorkflowService and VotingService.

---

### UX Design Requirements (from DESIGN.md + lovable/ prototype)

**Layout & Navigation**

UX-DR1: Sidebar is fixed-width (264px), right-aligned (RTL), white background (#ffffff). Active item uses #0071e3 background + white text. No collapsible sidebar. Full labels always shown.
UX-DR2: At ≤600px, sidebar transforms into a top navigation bar; cards stack vertically full-width; table columns collapse to key-value pairs.
UX-DR3: Dashboard layout contains: right sidebar (RTL), header with role-aware title and contextual action button (e.g., "New Request" for DATA_ENTRY), and main content area.
UX-DR4: Navigation items are role-specific — each role sees only its relevant queues and pages (see docs/04-frontend-guide.md navigation by role).

**Dashboard Screens (per role)**

UX-DR5: DATA_ENTRY dashboard: KPI grid (drafts, returned/needs editing, in-process, completed) — counts are bank-scoped not creator-scoped. Quick Actions section (New Request, View All Requests). "Requests Needing Your Attention" highlighted card (amber left border) for returned requests. Recent requests table showing simplified business statuses only (Draft / Submitted To CBY / Under CBY Processing / Rejected / Completed). Must NOT show CBY internal stages to this role.
UX-DR6: BANK_REVIEWER dashboard: KPI grid (pending review queue count, at-CBY count, returned count, approved count), review queue table with direct "View" links.
UX-DR7: SWIFT_OFFICER dashboard: KPI grid (pending SWIFT upload count, uploaded count, final approved, final rejected), SWIFT upload queue table.
UX-DR8: SUPPORT_COMMITTEE dashboard: KPI grid (waiting for claim, active by me, claimed by others, recently approved), support queue table showing claim state per request.
UX-DR9: Executive/Director dashboard: KPI grid (voting queue count, approved decisions, rejected decisions), voting queue table with "voting open" pulse badge on active sessions.
UX-DR10: CBY_ADMIN dashboard: full-system KPI grid, compliance alerts panel, most-active banks bar chart, monthly request area chart.

**Request List Screen**

UX-DR11: Request list is a table with columns: Reference (monospace, linked), Importer/Supplier, Amount + Currency, Status Badge, Progress bar (role-aware percentage), Action button.
UX-DR12: Status badges are pill-shaped (24px height), include icon + label, use semantic colors from DESIGN.md.
UX-DR13: Progress bar shows workflow completion percentage adjusted for the current user's role (e.g., SWIFT_OFFICER sees 0% until SWIFT stage, not global progress).
UX-DR14: Active voting sessions display a pulsing "voting open" badge (Voting Indigo #5856d6) on the request row.
UX-DR15: Table has search, status filter, and pagination. No zebra striping. Row height 44px.

**Request Details Screen**

UX-DR16: Request details page is organized into tabs: Overview, Documents, Workflow Timeline, Votes (if applicable), Audit History.
UX-DR17: Overview tab shows: request metadata (reference, bank, currency, amount, supplier, goods, port, notes), current status badge, workflow actor fields (created_by, submitted_by, internal_reviewer, support_reviewer, swift_uploaded_by, rejected_by, resubmitted_by), and support claim state.
UX-DR18: Locked/read-only state: overlay surface with #f5f5f7 background, lock icon, "Locked" badge (#8e8e93), disabled action buttons, no hover/focus affordances on locked items.
UX-DR19: Workflow actions (approve, reject, return, upload, vote, etc.) are displayed in a contextual action panel at the bottom of the details page, shown only to the role with action authority at the current stage.
UX-DR20: Rejection actions must include a mandatory rejection reason textarea.
UX-DR21: Support review claim state is prominently displayed: shows current active reviewer name, claim timestamp, "Claim" button (Primary Blue) for unclaimed requests, and disabled/locked state for requests claimed by others.

**Workflow Timeline Component**

UX-DR22: Hybrid rail + timeline: a vertical workflow rail (right-aligned in RTL) shows all stages with current stage highlighted in Primary Blue; an audit timeline below shows all actions with timestamps and actors.
UX-DR23: Completed stages show green checkmark; current stage shows blue highlight with subtle elevation; future stages are neutral/gray; locked stages show lock icon (#8e8e93).
UX-DR24: The audit timeline uses neutral colors for metadata and semantic colors only for status-change events.

**Voting Interface**

UX-DR25: Voting panel displays: request summary, document access, current tally (approve/reject/abstain counts), each member's vote status, voting session status badge, and Director controls (Open/Close voting).
UX-DR26: Vote action buttons: Approve (green), Reject (red), Abstain (gray) — 44px minimum height, 12px radius.
UX-DR27: AUTO_ABSTAIN_TIMEOUT votes are visually distinct from manual ABSTAIN (different icon/label).
UX-DR28: Executive voting pages must meet 48px minimum touch target for tablet compatibility.
UX-DR29: After session closure, all vote counts are locked and displayed as immutable. Final decision badge is prominently shown.

**SWIFT Upload Screen**

UX-DR30: Dedicated SWIFT upload page (/requests/{id}/swift) with: request summary (read-only), document upload zone (PDF drag-and-drop + file picker), upload progress indicator, immutability warning after successful upload.
UX-DR31: After SWIFT upload, the upload zone is replaced by the uploaded file metadata (name, size, uploaded by, timestamp) with a download link.

**Customs Declaration**

UX-DR32: Customs declaration view shows declaration details and a "Print/Download" button. Print layout uses dedicated print CSS with RTL formatting.
UX-DR33: Printable RTL PDF contains: declaration number, bank name, request reference, approval details, committee director signature block, issue date.

**Document Checklist**

UX-DR34: Documents tab in request details shows a checklist of required documents per stage, upload state per document, download links (role-permissioned), and upload action for editable states.

**Support Review Claim UX**

UX-DR35: When a SUPPORT_COMMITTEE user opens a request page for an unclaimed SUPPORT_REVIEW_PENDING request, claim is auto-initiated on page load — no manual "Claim" button required (approved UX baseline from lovable/). The claim is TEMPORARY and PRESENCE-BASED; it is not a permanent assignment.
UX-DR36: While reviewing, a visible "Reviewing" / "Active Review" indicator is shown to the claiming reviewer. Other support users who open the same request see a "Claimed by [Name]" locked indicator and cannot take review actions. All support users can still see the request in the queue.
UX-DR37: Heartbeat is transparent to the user (no visible UI indicator). Claim release happens silently on navigation away (frontend calls DELETE claim endpoint). On timeout or disconnect, the backend auto-releases the claim and the request returns to SUPPORT_REVIEW_PENDING.

**Status Badges & Semantic Colors**

UX-DR38: Status badge color mapping: DRAFT → neutral gray; SUBMITTED/BANK_REVIEW → Pending Amber; BANK_APPROVED/SUPPORT_REVIEW → Voting Indigo; WAITING_FOR_SWIFT/SWIFT_UPLOADED → SWIFT Cyan; EXECUTIVE_VOTING_* → Voting Indigo with pulse; EXECUTIVE_APPROVED/CUSTOMS_DECLARATION_ISSUED/COMPLETED → Approval Green; EXECUTIVE_REJECTED/SUPPORT_REJECTED → Rejected Red; LOCKED states → Locked Gray.

**Forms & Validation**

UX-DR39: Request creation/edit form: field spacing 24px vertical, labels above fields (Caption style #6e6e73), inputs 44px height 12px radius, focus border 1.5px #0071e3, validation errors inline below field, required fields marked with asterisk (#ff3b30), button placement right-aligned (RTL).
UX-DR40: All forms use VeeValidate + Zod for client-side validation; backend remains the authoritative validation source.

**Read-Only State Indicators**

UX-DR41: Locked workflow displays a prominent LockedBanner component at the top of the request details page explaining the locked state and reason (e.g., "Request locked after internal bank approval").
UX-DR42: All action buttons are hidden or disabled on locked requests; the panel is replaced by a read-only summary.

---

### FR Coverage Map

```
FR1–FR8:    Epic 1 — Auth (login, logout, rate limiting, lockout, session, middleware, layout)
FR9–FR12:   Epic 1 — Users, Banks, roles, CBY Admin CRUD
FR13–FR26:  Epic 2 — Full bank workflow (create, edit, submit, review, approve, reject, return)
FR27–FR39:  Epic 3 — Support claim lifecycle + approve/reject + bank post-rejection actions
FR40–FR43:  Epic 3 — SWIFT upload, immutability, queue
FR44–FR55:  Epic 3 — Executive voting engine (open/close, votes, tally, tie, terminal rejection)
FR56–FR61:  Epic 3 — Customs declaration (transaction, RTL PDF, COMPLETED)
FR62–FR63:  Epic 2 — Organization-scoped visibility, business-status abstraction
FR64–FR65:  Epics 2+3 — BANK_REVIEWER and SWIFT_OFFICER scoped visibility
FR66:       Epic 3 — SUPPORT_COMMITTEE queue visibility + claim state
FR67:       Epic 3 — Executive queue visibility
FR68:       Epic 4 — CBY_ADMIN full visibility
FR69–FR70:  Epic 2 — Business-status abstraction for DATA_ENTRY
FR71–FR76:  Epic 4 — Audit logging, stage history, audit API
FR77–FR82:  Epic 4 — Document download permission matrix
FR83–FR88:  Epics 2+3 — Dashboard queue APIs (introduced per epic, refined in Epic 4)
FR89–FR91:  Epic 2 — Immutable/locked state enforcement (WorkflowService foundation)
AR1–AR4:    Epic 1 — Project setup, migrations, enums, seeders
AR5:        Epics 2–3 — WorkflowService (introduced Epic 2, extended Epic 3)
AR6:        Epic 3 — VotingService
AR7:        Epic 4 — AuditService complete wiring
AR8:        Epics 2+3 — DocumentService (upload Epic 2, SWIFT/customs Epic 3)
AR9–AR13:   Epic 1 — Pinia stores, API layer, middleware, Redis, seeders
AR14:       Epic 3 — voting_session_status sync
NFR1–NFR7:  Epics 1+2 — Security baseline (auth Epic 1, workflow guards Epic 2)
NFR8:       Epic 3 — Pessimistic locking (voting)
NFR9:       Epic 3 — Redis claim TTL
NFR10:      Epic 1 — DB indexes in migrations
NFR11:      Epic 3 — Transaction for customs
NFR12:      Epics 2+3 — Immutability
NFR13–14:   All — Architectural rule enforced throughout
NFR15–16:   Epic 1 — RTL + typography
NFR17–18:   Epics 1+3 — Desktop-first, tablet voting
NFR19:      Epic 4 — Accessibility audit pass
NFR20:      Epics 2+3 — Operational dashboard philosophy
NFR21–22:   All — API response consistency
UX-DR1–4:   Epic 1 — Sidebar, nav, layout shell, role routing
UX-DR5–10:  Epics 2+3 — Per-role dashboards (inline with each workflow epic)
UX-DR11–15: Epic 2 — Request list table
UX-DR16–20: Epic 2 — Request details page (tabs, actions, locked state)
UX-DR21:    Epic 3 — Support claim UI
UX-DR22–24: Epic 4 — Workflow timeline + audit timeline
UX-DR25–29: Epic 3 — Voting interface
UX-DR30–31: Epic 3 — SWIFT upload screen
UX-DR32–33: Epic 3 — Customs declaration view + print
UX-DR34:    Epic 4 — Document checklist
UX-DR35–37: Epic 3 — Support claim auto-claim, heartbeat, release
UX-DR38:    Epic 2 — Status badge color mapping
UX-DR39–40: Epic 2 — Form & validation patterns
UX-DR41–42: Epic 2 — LockedBanner + locked state UX
```

---

## Epic List

### Epic 1: Foundation, Auth & Infrastructure
Every developer and user can log in, land on a role-aware application shell, and be protected by authentication. The full project skeleton — backend, frontend, database schema, enums, seeders, RTL layout, sidebar navigation, and shared infrastructure — is in place and ready for feature development.
**FRs covered:** FR1–FR12, AR1–AR4, AR9–AR13, NFR1–NFR7, NFR10, NFR15–16, UX-DR1–4

### Epic 2: Bank Workflow — Request Lifecycle
DATA_ENTRY users can create, edit, upload documents to, and submit financing requests. BANK_REVIEWER can open, actively review, approve, or reject requests. The full bank-internal workflow is functional with organization-scoped visibility, business-status abstraction for DATA_ENTRY, role-specific dashboards for both bank roles, request list with status badges, request details page with tabs, and locked/read-only state enforcement.
**FRs covered:** FR13–FR26, FR62–FR65, FR69–FR70, FR83–FR85, FR89–FR91, AR5 (WorkflowService foundation), AR8 (DocumentService upload), NFR12–14, NFR20–22, UX-DR5–6, UX-DR11–20, UX-DR38–42

### Epic 3: CBY Operational Workflow — Support → SWIFT → Voting → Customs
The complete CBY operational workflow is functional end-to-end. Support Committee members auto-claim requests, approve or reject with reason, and claims auto-release via heartbeat/TTL. SWIFT Officers upload the SWIFT document. Committee Director opens and closes voting sessions. Executive members vote; tie resolution, AUTO_ABSTAIN_TIMEOUT, pessimistic locking, and terminal rejection are enforced. Director finalizes the decision and issues the printable RTL customs declaration PDF. Request reaches COMPLETED.
**FRs covered:** FR27–FR61, FR66–FR68, FR86–FR88, AR5 (extensions), AR6, AR8 (extensions), AR14, NFR8–9, NFR11, UX-DR7–9, UX-DR21, UX-DR25–33, UX-DR35–37

### Epic 4: System Completion — Audit, Documents, Dashboards & Polish
Full audit trail is wired across all workflow events. Workflow timeline and audit timeline components are complete on the request details page. Document download permission matrix is enforced for all roles. Per-role dashboards are refined with correct queue counts and operational clarity. CBY Admin has full system visibility and audit log access. RTL and accessibility pass. Basic workflow and voting reports available.
**FRs covered:** FR68, FR71–FR82, AR7, NFR7, NFR19–20, UX-DR22–24, UX-DR34, UX-DR5–10 (refinement)

---

## Epic 1: Foundation, Auth & Infrastructure

Every user can log in with their role, land on the correct application shell, and be blocked from unauthorized routes. The complete project skeleton is in place: backend Laravel API, frontend Nuxt 4 app, full database schema with all migrations, canonical enums, demo seeders, RTL-first layout, role-aware sidebar navigation, and shared infrastructure (Redis, queues, API service layer).

### Story 1.1: Backend Project Scaffold, Database Schema & Seeders

As a developer,
I want a fully configured Laravel 11 backend with all migrations, enums, and demo seed data,
So that the database matches the canonical schema and every role has a working demo user from day one.

**Acceptance Criteria:**

**Given** a fresh Laravel 11 installation with Sanctum, MySQL, and Redis configured
**When** `php artisan migrate --seed` is run
**Then** all 8 tables exist: `banks`, `users`, `import_requests`, `request_documents`, `request_votes`, `request_stage_history`, `audit_logs`, `customs_declarations`
**And** all fields match docs/03-database-and-models.md exactly (types, nullability, foreign keys)
**And** PHP enums exist for: `RequestStatus` (18 values), `UserRole` (7 values), `Currency` (USD/EUR/SAR), `VoteType` (APPROVE/REJECT/ABSTAIN/AUTO_ABSTAIN_TIMEOUT), `VotingSessionStatus` (3 values), `DocumentType`
**And** required DB indexes are created: `request_number`, `current_status`, `bank_id`, `support_claimed_by`, `support_claimed_at`, `voting_session_status`, `voting_opened_at`, `voting_closed_at`, `voted_at`, `created_at`
**And** seeders create: 3 demo banks, 7 demo users (one per role), and sample import requests covering all 18 workflow statuses
**And** `bank_id` is NULL for CBY roles (SUPPORT_COMMITTEE, EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR, CBY_ADMIN) and non-null for bank roles
**And** `ImportRequest::setAttribute()` overrides direct `current_status` mutation and throws `DirectStatusMutationException` when `workflow.transition.active` is not bound
**And** backend folder structure matches docs/05-backend-guide.md (Actions/, DTOs/, Enums/, Services/Workflow/, Services/Voting/, Services/Audit/, Services/Documents/, Policies/)

---

### Story 1.2: Authentication API — Login, Logout & Session

As any system user,
I want to log in with my email and password and receive a secure session,
So that I can access the application and my role is available for all subsequent requests.

**Acceptance Criteria:**

**Given** a valid user exists in the database
**When** `POST /api/auth/login` is called with correct credentials
**Then** a Sanctum HTTP-only cookie session is created
**And** the response returns `{ success: true, user: { id, name, role, bank_id } }`
**And** `GET /api/auth/me` returns the authenticated user's id, name, role, and bank_id
**And** `POST /api/auth/logout` invalidates the session and returns 200

**Given** invalid credentials are submitted
**When** `POST /api/auth/login` is called with wrong password
**Then** HTTP 422 is returned with validation error
**And** the failed attempt is logged to `audit_logs` with `user_id: NULL`, `action: "login_failed"`
**And** after 5 failed attempts within 60 seconds from the same IP, HTTP 429 is returned
**And** after 10 consecutive failures, the account is locked for 15 minutes and HTTP 423 is returned

**Given** an authenticated session exists
**When** `GET /api/auth/me` is called
**Then** CSRF token validation is enforced (Sanctum SPA mode)
**And** session fixation protection is active (session ID regenerated on login)

---

### Story 1.3: Nuxt 4 Frontend Scaffold — RTL Layout, Auth Flow & Role-Aware Shell

As any authenticated user,
I want to open the app, log in, and land on an RTL Arabic application shell with role-specific navigation,
So that I immediately see the workspace relevant to my role with no access to unauthorized areas.

**Acceptance Criteria:**

**Given** a user opens the application
**When** they are not authenticated
**Then** they are redirected to `/login`
**And** the login page is RTL (`dir="rtl"`), uses IBM Plex Sans Arabic, and matches DESIGN.md (white surface, #0071e3 button, 44px inputs, 12px radius)

**Given** a user logs in successfully
**When** their session is established
**Then** they are redirected to `/dashboard`
**And** the application shell renders with: right-aligned sidebar (264px, RTL), header with role label, and main content area
**And** the sidebar shows only navigation items relevant to the user's role (per docs/04-frontend-guide.md navigation by role)
**And** the active sidebar item uses `#0071e3` background with white text
**And** the Pinia `auth.store.ts` holds `{ user: { id, name, role, bank_id }, isAuthenticated }`

**Given** an authenticated user tries to access a route outside their role
**When** the route middleware evaluates the request
**Then** they are redirected to `/dashboard` (not a 404)

**Given** the application is loaded
**When** any page renders
**Then** `dir="rtl"` is set on `<html>`, IBM Plex Sans Arabic is loaded, Inter is loaded as English fallback, and antialiased font rendering is applied
**And** at ≤600px: sidebar collapses to top nav bar, cards stack full-width

---

### Story 1.4: User & Bank Management (CBY Admin)

As a CBY Admin,
I want to create and manage commercial banks and all system users,
So that the platform has real institutional accounts ready for workflow operations.

**Acceptance Criteria:**

**Given** I am logged in as CBY_ADMIN
**When** I navigate to `/banks`
**Then** I see a list of all banks (name_ar, name_en, code, status)
**And** I can create a new bank via `POST /api/banks` with name_ar, name_en, code, status
**And** I can update an existing bank via `PUT /api/banks/{id}`

**Given** I am logged in as CBY_ADMIN
**When** I navigate to `/users`
**Then** I see a list of all users (name, email, role, bank, is_active)
**And** I can create a new user via `POST /api/users` with name, email, password, role, bank_id (nullable for CBY roles)
**And** I can update a user via `PUT /api/users/{id}`
**And** creating a bank-role user (DATA_ENTRY, BANK_REVIEWER, SWIFT_OFFICER) with `bank_id: NULL` returns HTTP 422
**And** creating a CBY-role user (SUPPORT_COMMITTEE, EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR, CBY_ADMIN) with a non-null `bank_id` returns HTTP 422
**And** all user/bank endpoints are CBY_ADMIN only — any other role receives HTTP 403

---

## Epic 2: Bank Workflow — Request Lifecycle

DATA_ENTRY users can create, edit, upload documents to, and submit financing requests. BANK_REVIEWER can view submitted requests, begin active review, approve, or reject. Organization-scoped visibility is enforced at query level. DATA_ENTRY sees only simplified business statuses. Role-specific dashboards, request list with badges, and request details page with tabs are all operational. Locked/read-only state is visually enforced.

### Story 2.1: WorkflowService Foundation & Request CRUD APIs

As a DATA_ENTRY user,
I want to create, edit, and delete draft import requests via the API,
So that financing request data is captured accurately and securely in the system.

**Acceptance Criteria:**

**Given** I am authenticated as DATA_ENTRY
**When** I call `POST /api/requests` with currency, amount, supplier_name, goods_description, port_of_entry, and optional notes
**Then** a new import request is created in DRAFT status
**And** a unique `request_number` is auto-generated
**And** `created_by` is set to my user ID, `bank_id` is set from my user record
**And** the response matches `{ success: true, data: { id, request_number, current_status, ... } }`

**Given** a request is in DRAFT or DRAFT_REJECTED_INTERNAL status belonging to my bank
**When** I call `PUT /api/requests/{id}` with updated fields
**Then** the request is updated and `last_updated_by` is set to my user ID
**And** any DATA_ENTRY user within the same bank can update the request (bank-owned, not creator-owned)

**Given** a request is in any status other than DRAFT or DRAFT_REJECTED_INTERNAL
**When** I attempt `PUT /api/requests/{id}`
**Then** HTTP 422 is returned with `error_code: "WORKFLOW_LOCKED_STATE"`

**Given** a request is in DRAFT status
**When** I call `DELETE /api/requests/{id}`
**Then** the request is deleted
**And** attempts to delete a non-DRAFT request return HTTP 422 with `WORKFLOW_LOCKED_STATE`

**Given** a request is in EXECUTIVE_REJECTED, CUSTOMS_DECLARATION_ISSUED, or COMPLETED status
**When** any mutation endpoint is called
**Then** HTTP 403 is returned with `error_code: "WORKFLOW_IMMUTABLE_STATE"` and `current_status` in the response body

**And** `WorkflowService` class exists with `transition(request, action, user)` method that: validates current status, validates user role, enforces org scope, uses `workflow.transition.active` IoC binding, and throws `DirectStatusMutationException` on bypass attempts

---

### Story 2.2: Document Upload for Requests

As a DATA_ENTRY user,
I want to upload PDF documents to a draft request and remove them before submission,
So that all required supporting files are attached before the request enters the review workflow.

**Acceptance Criteria:**

**Given** a request is in DRAFT or DRAFT_REJECTED_INTERNAL status
**When** I call `POST /api/documents/upload` with a multipart PDF file and `request_id`
**Then** the file is stored privately at `/storage/requests/{request_id}/`
**And** a `request_documents` record is created with document_type, original_name, file_name, mime_type, file_size, storage_path, checksum, uploaded_by
**And** only PDF files are accepted — any other MIME type returns HTTP 422 with a clear validation error

**Given** an uploaded document exists on a DRAFT request
**When** I call `DELETE /api/documents/{id}`
**Then** the document record is removed and the file is deleted from storage

**Given** a request has been approved (any locked status)
**When** I attempt to upload or delete a document via the API
**Then** HTTP 422 is returned with `WORKFLOW_LOCKED_STATE`

**And** `DocumentService` class handles: PDF validation, private storage, structured paths, and immutability enforcement

---

### Story 2.3: Request Submission & Bank Review Workflow

As a DATA_ENTRY user,
I want to submit a draft request for internal bank review,
So that it enters the Bank Reviewer's queue and the approval process begins.

**Acceptance Criteria:**

**Given** a request is in DRAFT status and I am DATA_ENTRY for the same bank
**When** I call `POST /api/workflow/{id}/submit`
**Then** `current_status` transitions to SUBMITTED via `WorkflowService::transition()`
**And** `submitted_by` is set to my user ID
**And** both a `request_stage_history` record and an `audit_logs` record are created for the transition
**And** the audit log captures: user_id, role (DATA_ENTRY at time of action), action, from_status (DRAFT), to_status (SUBMITTED), entity_id, created_at

**Given** a request is in SUBMITTED status and I am BANK_REVIEWER for the same bank
**When** I call `POST /api/workflow/{id}/bank-review` (begin active review)
**Then** `current_status` transitions to BANK_REVIEW via WorkflowService
**And** `reviewed_by` is set to my user ID
**And** stage history and audit log records are created

**Given** I am BANK_REVIEWER and the request is in BANK_REVIEW status
**When** I call `POST /api/workflow/{id}/bank-approve`
**Then** `current_status` transitions to BANK_APPROVED
**And** the request becomes permanently locked — all subsequent edit attempts return `WORKFLOW_LOCKED_STATE`
**And** `reviewed_by` is set, stage history and audit records are created

**Given** I am BANK_REVIEWER and the request is in BANK_REVIEW status
**When** I call `POST /api/workflow/{id}/bank-reject`
**Then** `current_status` transitions to DRAFT_REJECTED_INTERNAL
**And** the request returns to an editable state
**And** stage history and audit records are created

**Given** I am the same user who created the request and it is in SUBMITTED or BANK_REVIEW status
**When** I attempt `POST /api/workflow/{id}/bank-approve` or `bank-reject`
**Then** HTTP 403 is returned (separation of duties — creator cannot review own request)

**And** `GET /api/requests` enforces organization-scoped filtering: bank-role users only receive requests belonging to their `bank_id`

---

### Story 2.4: Request List, Status Badges & Business-Status Abstraction (Frontend)

As a DATA_ENTRY or BANK_REVIEWER user,
I want to see a list of my bank's requests with clear status indicators,
So that I can quickly identify what needs my attention and act on it.

**Acceptance Criteria:**

**Given** I am logged in as DATA_ENTRY
**When** I navigate to `/requests`
**Then** I see all requests belonging to my bank (organization-scoped, not creator-scoped)
**And** each request shows: Reference (monospace font, linked to detail), Supplier Name, Amount + Currency, Status Badge, and a "View" action button
**And** status badges use simplified business statuses: Draft / Returned For Correction / Submitted To CBY / Under CBY Processing / Rejected / Completed
**And** status badges are pill-shaped (24px height), include icon + label, and use DESIGN.md semantic colors
**And** I never see internal CBY statuses (SUPPORT_REVIEW_IN_PROGRESS, WAITING_FOR_SWIFT, EXECUTIVE_VOTING_OPEN, etc.)

**Given** I am logged in as BANK_REVIEWER
**When** I navigate to `/requests`
**Then** I see internal workflow statuses (SUBMITTED, BANK_REVIEW, BANK_APPROVED, SUPPORT_REVIEW_PENDING, etc.)
**And** the table has: search by reference/supplier, status filter dropdown, and pagination
**And** table rows are 44px height, no zebra striping, 1px #d2d2d7 row borders

**Given** any status badge is rendered
**When** it displays
**Then** it includes both a color AND an icon (never color-only)
**And** the color mapping matches UX-DR38 exactly

---

### Story 2.5: Request Creation & Edit Form (Frontend)

As a DATA_ENTRY user,
I want to create and edit import requests through a validated Arabic form,
So that I can accurately capture all required financing request details.

**Acceptance Criteria:**

**Given** I navigate to `/requests/new`
**When** the form renders
**Then** I see fields: Currency (select: USD/EUR/SAR), Amount (number), Supplier Name (text), Goods Description (textarea), Port of Entry (text), Notes (textarea, optional)
**And** the form is RTL-first: `dir="rtl"`, right-aligned buttons, labels above fields (Caption style, #6e6e73), 44px input height, 12px radius, 24px vertical field spacing
**And** required fields show asterisk (#ff3b30)
**And** client-side validation uses VeeValidate + Zod; errors appear inline below each field

**Given** I fill the form and click Submit
**When** `POST /api/requests` succeeds
**Then** I am redirected to the new request's detail page
**And** a success toast notification is shown

**Given** I navigate to `/requests/{id}` for a DRAFT or DRAFT_REJECTED_INTERNAL request
**When** I click Edit
**Then** the same form is pre-populated with existing values
**And** on save, `PUT /api/requests/{id}` is called and the page refreshes with updated data

**Given** the request is in any locked status
**When** I open the request details
**Then** no Edit button is visible
**And** a LockedBanner component is displayed explaining the locked state reason

---

### Story 2.6: Request Details Page — Tabs, Workflow Actors & Actions Panel (Frontend)

As any bank user,
I want to view a request's full details, current status, workflow actor trail, and available actions in one place,
So that I have complete operational context and can take the right action without navigating away.

**Acceptance Criteria:**

**Given** I open `/requests/{id}`
**When** the page loads
**Then** I see a tabbed layout: Overview | Documents | Workflow Timeline | Audit History
**And** the Overview tab shows: request_number, bank name, currency, amount, supplier_name, goods_description, port_of_entry, notes, current status badge, created_at
**And** the Overview tab shows workflow actor fields: Created By, Submitted By, Internal Reviewer, Support Reviewer, SWIFT Uploaded By, Rejected By, Resubmitted By (null values shown as "—")
**And** the Documents tab shows: list of uploaded documents with name, size, upload date, uploader name, and a download link (role-permissioned per FR77–82)

**Given** the request is in a locked state (any status after BANK_APPROVED)
**When** the page renders
**Then** a LockedBanner is displayed at the top with the lock reason
**And** all edit/delete actions are hidden
**And** form fields use #f5f5f7 background, #8e8e93 text, no hover/focus affordances

**Given** I am BANK_REVIEWER and the request is in SUBMITTED or BANK_REVIEW status
**When** I open the request details
**Then** an Actions Panel appears at the bottom with: "Begin Review" (if SUBMITTED), "Approve" (green), "Reject" (red) buttons — each 44px height, 12px radius
**And** clicking "Reject" reveals a mandatory rejection reason textarea before confirming
**And** all workflow actions call the corresponding `/api/workflow/{id}/...` endpoint
**And** the page status badge and actor fields update immediately after a successful action

**Given** I am DATA_ENTRY and the request is in DRAFT_REJECTED_INTERNAL
**When** I open the request
**Then** the Actions Panel shows "Edit & Resubmit" as the primary action
**And** the LockedBanner explains the request was returned for correction

---

### Story 2.7: Role-Specific Dashboards — Data Entry & Bank Reviewer

As a DATA_ENTRY or BANK_REVIEWER user,
I want a queue-first dashboard that shows exactly what needs my attention right now,
So that I never have to search for pending work — it is always surfaced immediately.

**Acceptance Criteria:**

**Given** I am logged in as DATA_ENTRY
**When** I navigate to `/dashboard`
**Then** I see a KPI grid with 4 cards: Draft count, Returned/Needs Editing count, Under CBY Processing count, Completed count — all bank-scoped (not creator-scoped)
**And** if there are returned requests, a highlighted alert card (amber left border, alert icon) lists them with direct links
**And** a Quick Actions section shows: "New Request" (primary), "View All Requests"
**And** a recent requests table shows the last 5 requests with simplified business statuses
**And** `GET /api/dashboard/stats` returns role-scoped counts matching the above

**Given** I am logged in as BANK_REVIEWER
**When** I navigate to `/dashboard`
**Then** I see a KPI grid: Pending Internal Review count, At CBY count, Returned by Support count, Approved/Completed count
**And** a "Review Queue" table shows all SUBMITTED and BANK_REVIEW requests with direct "View" links
**And** the dashboard is operational and queue-first — no analytics charts, no vanity metrics
**And** `GET /api/dashboard/stats` returns BANK_REVIEWER-scoped counts

**Given** any dashboard screen renders
**When** it displays
**Then** it answers "what work is relevant to this user right now?" — not global analytics
**And** all counts are organization-scoped to the user's bank
**And** the sidebar active item correctly highlights the current page

---

## Epic 3: CBY Operational Workflow — Support → SWIFT → Voting → Customs

The complete CBY operational workflow is functional end-to-end with all governance rules enforced. Support Committee auto-claim lifecycle with heartbeat and TTL. SWIFT Officer queue and immutable upload. Executive voting with session governance, tie resolution, AUTO_ABSTAIN_TIMEOUT, and pessimistic locking. COMMITTEE_DIRECTOR-only customs declaration issuance. Request reaches COMPLETED.

### Story 3.1: Support Committee Review — Claim Lifecycle & Approve/Reject

As a SUPPORT_COMMITTEE user,
I want to open a pending support request and automatically claim it for active review, then approve or reject it,
So that only one reviewer is active at a time and the workflow progresses correctly.

**Acceptance Criteria:**

**Given** a request is in SUPPORT_REVIEW_PENDING status
**When** a SUPPORT_COMMITTEE user calls `POST /api/workflow/{id}/claim-support-review`
**Then** the claim is atomic — concurrent claim attempts by two users result in exactly one succeeding
**And** `current_status` transitions to SUPPORT_REVIEW_IN_PROGRESS via WorkflowService
**And** `support_claimed_by` is set to the claimant's user ID, `support_claimed_at` to current timestamp
**And** a Redis key `support_claim:{request_id}` is created with 15-minute TTL
**And** stage history and audit records are created

**Given** a request is claimed by User A
**When** User B (also SUPPORT_COMMITTEE) attempts `POST /api/workflow/{id}/claim-support-review`
**Then** HTTP 409 is returned with a message identifying the current claim holder

**Given** a claim is active and no heartbeat is received for 15 minutes
**When** the Redis TTL key expires (enforced by a scheduled job/listener)
**Then** `current_status` reverts to SUPPORT_REVIEW_PENDING via WorkflowService
**And** `support_claimed_by` → NULL, `support_claimed_at` → NULL
**And** stage history and audit records are created for the auto-release

**Given** a heartbeat is sent while a claim is active
**When** `POST /api/workflow/{id}/claim-support-review/heartbeat` is called by the claim holder
**Then** the Redis TTL is reset to 15 minutes
**And** HTTP 200 is returned

**Given** the reviewer navigates away
**When** `DELETE /api/workflow/{id}/claim-support-review` is called by the claim holder
**Then** the claim is released immediately: `current_status` → SUPPORT_REVIEW_PENDING, fields nulled
**And** only the claim holder (or CBY_ADMIN) can release the claim; others get HTTP 403

**Given** I am the claim holder and the request is in SUPPORT_REVIEW_IN_PROGRESS
**When** I call `POST /api/workflow/{id}/support-approve`
**Then** `current_status` → SUPPORT_APPROVED then WAITING_FOR_SWIFT via WorkflowService
**And** `support_reviewed_by` is set, stage history and audit records created

**Given** I am the claim holder
**When** I call `POST /api/workflow/{id}/support-reject` with a `reason` field
**Then** `current_status` → SUPPORT_REJECTED
**And** the `reason` is stored in `request_stage_history.notes`
**And** the request appears in the BANK_REVIEWER's queue

**Given** a request is in SUPPORT_REJECTED and I am BANK_REVIEWER for the same bank
**When** I call `POST /api/workflow/{id}/bank-return-after-support-reject`
**Then** `current_status` → DRAFT_REJECTED_INTERNAL (request becomes editable again)
**And** this is the ONLY path that makes a post-bank-approval request editable

**Given** a request is in SUPPORT_REJECTED and I am BANK_REVIEWER
**When** I call `POST /api/workflow/{id}/bank-finalize-rejection`
**Then** `current_status` remains SUPPORT_REJECTED and is marked as workflow-terminal for the bank

---

### Story 3.2: Support Committee Frontend — Queue, Auto-Claim UX & Dashboard

As a SUPPORT_COMMITTEE user,
I want to see the support review queue, auto-claim requests on page open, and have clear visibility of who is reviewing what,
So that support work is organized, claim conflicts are obvious, and review actions are always available.

**Acceptance Criteria:**

**Given** I am logged in as SUPPORT_COMMITTEE
**When** I navigate to `/dashboard`
**Then** I see a KPI grid: Waiting for Claim count, Active by Me count, Claimed by Others count, Recently Approved count
**And** a support queue table shows all SUPPORT_REVIEW_PENDING and SUPPORT_REVIEW_IN_PROGRESS requests
**And** each row shows: request reference, bank name, amount, status, and current claimer name (if claimed)

**Given** I open `/requests/{id}` for an unclaimed SUPPORT_REVIEW_PENDING request
**When** the page loads (auto-claim on mount)
**Then** `POST /api/workflow/{id}/claim-support-review` is called automatically
**And** the UI shows an "Active Review" indicator (green badge/banner) — I am now the claim holder
**And** a heartbeat composable starts sending `POST .../heartbeat` every 60 seconds silently
**And** when I navigate away, `DELETE .../claim-support-review` is called via `onBeforeUnmount`

**Given** I open a request that is claimed by another support member
**When** the page loads
**Then** the auto-claim is NOT attempted (the request is already SUPPORT_REVIEW_IN_PROGRESS with a different `support_claimed_by`)
**And** a "Claimed by [Name]" locked indicator is shown prominently
**And** all approve/reject actions are disabled — I can view but not act

**Given** I am the claim holder on `/requests/{id}`
**When** I see the Actions Panel
**Then** I see "Approve" (green) and "Reject" (red) buttons
**And** clicking "Reject" shows a mandatory rejection reason textarea before confirming

---

### Story 3.3: SWIFT Officer — Queue, Upload & Immutability

As a SWIFT_OFFICER user,
I want to see requests waiting for SWIFT upload, upload the SWIFT PDF, and have the upload be permanently immutable,
So that the SWIFT document is securely attached before executive voting can begin.

**Acceptance Criteria:**

**Given** I am logged in as SWIFT_OFFICER
**When** I navigate to `/dashboard`
**Then** I see a KPI grid: Pending SWIFT Upload count, Uploaded count, Final Approved count, Final Rejected count
**And** a SWIFT queue table shows all WAITING_FOR_SWIFT requests for my bank
**And** I cannot see requests from other banks

**Given** a request is in WAITING_FOR_SWIFT and belongs to my bank
**When** I call `POST /api/workflow/{id}/swift-upload` with a PDF file
**Then** the SWIFT document is stored privately at `/storage/swift/{request_id}/`
**And** a `request_documents` record is created with `document_type: SWIFT`
**And** `swift_uploaded_by` and `swift_uploaded_at` are recorded on the request
**And** `current_status` transitions SWIFT_UPLOADED → WAITING_FOR_VOTING_OPEN via WorkflowService
**And** stage history and audit records are created

**Given** a SWIFT document has been uploaded
**When** any user attempts to upload another SWIFT document for the same request
**Then** HTTP 422 is returned — SWIFT is immutable after upload

**Given** I navigate to `/requests/{id}/swift` for a request in WAITING_FOR_SWIFT
**When** the page renders
**Then** I see the request summary (read-only), a PDF drop zone, and an upload progress indicator
**And** only PDF files are accepted; other types show an inline validation error

**Given** SWIFT has been uploaded
**When** the page renders
**Then** the upload zone is replaced by uploaded file metadata: name, size, uploaded by, timestamp, and a download link
**And** an immutability warning is displayed ("SWIFT document cannot be replaced or deleted")

---

### Story 3.4: Executive Voting Engine — VotingService, Session Lifecycle & APIs

As an EXECUTIVE_MEMBER or COMMITTEE_DIRECTOR,
I want to participate in governed voting sessions with accurate tally computation, tie resolution, and automatic timeout abstention,
So that every executive decision is made with integrity, auditability, and no race conditions.

**Acceptance Criteria:**

**Given** a request is in WAITING_FOR_VOTING_OPEN and I am COMMITTEE_DIRECTOR
**When** I call `POST /api/voting/{id}/open`
**Then** `current_status` → EXECUTIVE_VOTING_OPEN via WorkflowService
**And** `voting_session_status` is set to EXECUTIVE_VOTING_OPEN (denormalized cache kept in sync)
**And** `voting_opened_by` and `voting_opened_at` are recorded
**And** stage history and audit records are created

**Given** the voting session is EXECUTIVE_VOTING_OPEN
**When** `POST /api/voting/{id}/vote` is called with `{ vote: "APPROVE"|"REJECT"|"ABSTAIN", justification? }`
**Then** the vote is recorded in `request_votes` with `vote_source: MANUAL`
**And** each executive member (EXECUTIVE_MEMBER or COMMITTEE_DIRECTOR) can vote exactly once
**And** a second vote attempt by the same user returns HTTP 422
**And** vote submission uses `lockForUpdate()` pessimistic locking to prevent race conditions

**Given** I am COMMITTEE_DIRECTOR
**When** I call `POST /api/voting/{id}/close`
**Then** the session closure is wrapped in a database transaction with `lockForUpdate()` on the request
**And** any executive member who has not yet voted is assigned `vote: AUTO_ABSTAIN_TIMEOUT`, `vote_source: TIMEOUT` in `request_votes`
**And** `current_status` → EXECUTIVE_VOTING_CLOSED
**And** `voting_closed_by` and `voting_closed_at` are recorded

**Given** the session is EXECUTIVE_VOTING_CLOSED
**When** I call `POST /api/workflow/{id}/finalize-decision`
**Then** VotingService computes the tally: approve count vs reject count (ABSTAIN and AUTO_ABSTAIN_TIMEOUT excluded from majority)
**And** if approve > reject → `current_status` → EXECUTIVE_APPROVED
**And** if reject > approve → `current_status` → EXECUTIVE_REJECTED
**And** on a tie: if Director has voted non-abstain → Director's vote is the deciding vote
**And** on a tie where Director has not voted or abstained → EXECUTIVE_REJECTED (safe stance)
**And** EXECUTIVE_REJECTED is permanent: no further transitions are possible; any mutation returns HTTP 403 `WORKFLOW_IMMUTABLE_STATE`
**And** `voting_session_status` is kept in sync with `current_status` throughout all voting transitions

---

### Story 3.5: Executive Voting Frontend — Voting Interface, Director Controls & Dashboard

As an EXECUTIVE_MEMBER or COMMITTEE_DIRECTOR,
I want a clear voting interface that shows the request details, current tally, each member's vote status, and Director controls,
So that I can cast my vote or manage the session with full context and confidence.

**Acceptance Criteria:**

**Given** I am logged in as EXECUTIVE_MEMBER or COMMITTEE_DIRECTOR
**When** I navigate to `/dashboard`
**Then** I see a KPI grid: Voting Queue count, Approved Decisions count, Rejected Decisions count
**And** a voting queue table shows all WAITING_FOR_VOTING_OPEN and EXECUTIVE_VOTING_OPEN requests
**And** EXECUTIVE_VOTING_OPEN rows display a pulsing "Voting Open" badge (Voting Indigo #5856d6)

**Given** I open `/requests/{id}` for a request in EXECUTIVE_VOTING_OPEN
**When** the Votes tab renders
**Then** I see: request summary, current tally (approve/reject/abstain counts), each executive member's vote status (voted/not voted/abstained)
**And** vote action buttons are shown if I have not yet voted: Approve (green, #34c759), Reject (red, #ff3b30), Abstain (gray, #8e8e93) — all 44px minimum height, 48px touch target
**And** AUTO_ABSTAIN_TIMEOUT entries are visually distinct from manual ABSTAIN (different icon and label)

**Given** I am COMMITTEE_DIRECTOR
**When** the request is in WAITING_FOR_VOTING_OPEN
**Then** an "Open Voting Session" button is visible in the Actions Panel
**And** when the session is EXECUTIVE_VOTING_OPEN, a "Close Voting Session" button is visible (red, with confirmation dialog)
**And** when the session is EXECUTIVE_VOTING_CLOSED, a "Finalize Decision" button is visible

**Given** the voting session has been finalized
**When** the page renders
**Then** all vote inputs are locked — no further voting is possible
**And** the final decision badge (EXECUTIVE_APPROVED in green, EXECUTIVE_REJECTED in red) is prominently displayed
**And** EXECUTIVE_REJECTED state shows a permanent "Terminal — No Further Actions Possible" banner

---

### Story 3.6: Customs Declaration Issuance — Transaction, RTL PDF & Completion

As a COMMITTEE_DIRECTOR,
I want to generate and issue the official customs declaration PDF for an approved request, completing the workflow,
So that the request has a permanent, immutable customs document and the workflow reaches COMPLETED.

**Acceptance Criteria:**

**Given** a request is in EXECUTIVE_APPROVED and I am COMMITTEE_DIRECTOR
**When** I call `POST /api/customs/{id}/generate`
**Then** the entire operation runs within a single database transaction
**And** a `customs_declarations` record is created with a unique `declaration_number`, `issued_by`, `issued_at`, and `pdf_path`
**And** `current_status` → CUSTOMS_DECLARATION_ISSUED → COMPLETED via WorkflowService (two transitions in the transaction)
**And** the `import_request.customs_declaration_id` foreign key is set
**And** stage history and audit records are created for both transitions
**And** if any step fails, the entire transaction rolls back and no partial state is persisted

**Given** a customs declaration has been generated
**When** `GET /api/customs/{id}` is called
**Then** the response includes: declaration_number, issued_by user details, issued_at, request reference, bank name

**Given** I am COMMITTEE_DIRECTOR
**When** I call `GET /api/customs/{id}/download`
**Then** a PDF is returned with RTL formatting: declaration number, bank name, request reference, approval details, director signature block, issue date, CBY header
**And** the PDF is generated using barryvdh/laravel-dompdf with RTL Arabic text
**And** after issuance, the customs declaration is immutable — no further modifications are possible

**Given** I am on the request details page for a COMPLETED request
**When** the page renders
**Then** a "Customs Declaration" section shows: declaration number, issue date, issued by, and a "Download PDF" button
**And** clicking "Download PDF" calls `GET /api/customs/{id}/download`
**And** a print layout is available (basic RTL print CSS — functional, not pixel-perfect)
**And** the request status shows COMPLETED in Approval Green (#34c759)

---

## Epic 4: System Completion — Audit, Documents, Dashboards & Polish

Full audit trail wired across all workflow events. Workflow timeline and audit timeline components complete on the request details page. Document download permission matrix enforced for all roles. Per-role dashboards refined with all correct queue counts. CBY Admin full-system visibility and audit log access. RTL pass. Basic reports.

### Story 4.1: AuditService, Stage History & Audit API

As a CBY Admin or authorized user,
I want every workflow action, file upload, login event, and authorization failure to be captured in a full audit trail,
So that the platform is fully auditable, regulatory-grade, and every action is traceable to a user, role, and timestamp.

**Acceptance Criteria:**

**Given** any workflow transition occurs (at any stage)
**When** WorkflowService::transition() executes
**Then** both a `request_stage_history` record AND an `audit_logs` record are created atomically
**And** the audit log captures: user_id, role (role at time of action — not current role), action string, entity_type ("ImportRequest"), entity_id, from_status, to_status, metadata (JSON), created_at
**And** non-transition events (login, logout, file upload, failed auth) are also logged with from_status and to_status as NULL

**Given** an authorization failure occurs (role mismatch, wrong workflow state, org scope violation)
**When** the backend policy or WorkflowService rejects the action
**Then** the failed attempt is logged to `audit_logs` with the attempted action, user_id, and reason

**Given** I am authenticated as any role
**When** I call `GET /api/requests/{id}/history`
**Then** the response returns all `request_stage_history` records for the request in chronological order: from_status, to_status, action, performed_by user details, notes, created_at

**Given** I am logged in as CBY_ADMIN
**When** I navigate to `/audit` or call `GET /api/audit`
**Then** I see the full audit log across all banks and all requests: user, role, action, entity, timestamps, IP
**And** the audit log is paginated and filterable by date range, user, and action type
**And** no other role can access the full audit log endpoint (HTTP 403)

---

### Story 4.2: Workflow Timeline & Audit Timeline Components (Frontend)

As any authenticated user,
I want to see the complete workflow history and audit trail visualized on the request details page,
So that I have full operational context — every stage transition, action, and timestamp is visible.

**Acceptance Criteria:**

**Given** I open the "Workflow Timeline" tab on `/requests/{id}`
**When** the tab renders
**Then** a vertical workflow rail is displayed (right-aligned in RTL) showing all 18 canonical workflow stages
**And** completed stages show a green checkmark icon
**And** the current stage is highlighted with Primary Blue (#0071e3) and subtle elevation
**And** future/unreached stages are neutral gray
**And** locked/terminal stages (EXECUTIVE_REJECTED, COMPLETED) show a lock icon (#8e8e93)
**And** the current stage label includes the timestamp it was entered and the actor who triggered it

**Given** I open the "Audit History" tab on `/requests/{id}`
**When** the tab renders
**Then** a chronological audit timeline is displayed showing all `request_stage_history` records for the request
**And** each entry shows: action description, actor name + role, from_status → to_status (for transitions), timestamp
**And** status-change events use semantic colors (green for approvals, red for rejections, amber for pending, indigo for review)
**And** non-status-change entries (document uploads, claim events) use neutral colors

**Given** the workflow timeline renders for a request in EXECUTIVE_REJECTED
**When** the timeline displays
**Then** the terminal state is clearly marked as permanent with a "Terminal — No Further Actions" label

---

### Story 4.3: Document Download Permission Matrix

As any authenticated user,
I want to download request documents, SWIFT documents, and customs declarations according to my role's permissions,
So that sensitive documents are only accessible to authorized roles and the backend enforces all access rules.

**Acceptance Criteria:**

**Given** I call `GET /api/documents/{id}/download`
**When** the backend evaluates my role
**Then** access is granted or denied according to the permission matrix:
- DATA_ENTRY: request documents for own bank only; SWIFT = denied; customs = denied
- BANK_REVIEWER: request docs + SWIFT for own bank; customs for own bank
- SWIFT_OFFICER: request docs + SWIFT for own bank; customs = denied
- SUPPORT_COMMITTEE: request docs for all banks; SWIFT = denied; customs = denied
- EXECUTIVE_MEMBER: request docs + SWIFT for all banks; customs = denied
- COMMITTEE_DIRECTOR: all document types
- CBY_ADMIN: all document types

**And** violations return HTTP 403 with a clear error message
**And** all document storage is private — files are never served directly from storage URLs
**And** the backend generates a signed temporary URL or streams the file through the API — never exposes raw storage paths

**Given** any document download is attempted
**When** the request is processed
**Then** the download event is logged to `audit_logs` with user_id, role, document_id, document_type, and created_at

---

### Story 4.4: Document Checklist Component (Frontend)

As any authenticated user,
I want the Documents tab to show a clear checklist of required and uploaded documents with role-appropriate actions,
So that I can verify document completeness and download what I'm permitted to access.

**Acceptance Criteria:**

**Given** I open the "Documents" tab on `/requests/{id}`
**When** the tab renders
**Then** a document list shows all `request_documents` records: document type, original filename, file size, uploaded by, upload date
**And** each document has a "Download" button — visible only if my role permits download per FR77–82
**And** for DRAFT/DRAFT_REJECTED_INTERNAL requests: an "Upload Document" button is shown for DATA_ENTRY users
**And** for locked requests: the upload button is hidden; a "Locked — documents cannot be modified" note is shown
**And** SWIFT documents are clearly labeled with a SWIFT badge (SWIFT Cyan #32ade6)
**And** the customs declaration PDF appears in the list once issued, downloadable by authorized roles only

---

### Story 4.5: CBY Admin Dashboard & Full System Visibility

As a CBY_ADMIN,
I want a full-system operational dashboard with visibility across all banks and all workflow stages,
So that I can monitor platform-wide activity, identify compliance issues, and manage the system.

**Acceptance Criteria:**

**Given** I am logged in as CBY_ADMIN
**When** I navigate to `/dashboard`
**Then** I see a full-system KPI grid: Total Requests (all banks), Approved count, In-Process count, Rejected count
**And** a compliance alerts panel shows flagged issues (duplicate invoice numbers if detected)
**And** a "Most Active Banks" section shows request counts per bank
**And** I can navigate to `/audit` for the full audit log
**And** I can navigate to `/users` and `/banks` for management

**Given** I am logged in as CBY_ADMIN
**When** I call `GET /api/requests`
**Then** I receive requests from ALL banks (not scoped to a single bank)
**And** I can filter by bank, status, date range

**Given** I call `GET /api/reports/workflow`
**Then** I receive workflow summary counts: total by status, total by bank, date-range filterable
**And** `GET /api/reports/voting` returns voting summary: total sessions, approve/reject/abstain tallies

---

### Story 4.6: SWIFT Officer & Executive Dashboards + RTL Final Pass

As a SWIFT_OFFICER or Executive Committee member,
I want accurate operational dashboards with correct queue counts,
And the entire platform must be fully RTL-correct with no broken layouts or misaligned components.

**Acceptance Criteria:**

**Given** I am logged in as SWIFT_OFFICER
**When** I navigate to `/dashboard`
**Then** I see: Pending SWIFT Upload count (WAITING_FOR_SWIFT for my bank), Uploaded count (SWIFT_UPLOADED), Final Approved count (EXECUTIVE_APPROVED+COMPLETED), Final Rejected count (EXECUTIVE_REJECTED)
**And** all counts are scoped to my bank_id

**Given** I am logged in as COMMITTEE_DIRECTOR
**When** I navigate to `/dashboard`
**Then** I see Waiting for Voting Open count, Active Voting Sessions count, Finalized Decisions count
**And** a "Customs Declaration Pending" section shows EXECUTIVE_APPROVED requests awaiting declaration issuance

**Given** any page renders across all roles
**When** the RTL final pass is applied
**Then** all layouts default to `dir="rtl"` with correct RTL flow
**And** sidebar is right-aligned (264px), content area is to the left of sidebar
**And** all tables: column order is right-to-left; action columns are leftmost in RTL
**And** all forms: labels above fields, buttons right-aligned
**And** status badges render correctly in RTL with icon on the right side of text
**And** at ≤600px: sidebar becomes top nav, cards stack full-width, tables degrade gracefully
**And** executive voting pages meet 48px minimum touch target on all interactive elements
