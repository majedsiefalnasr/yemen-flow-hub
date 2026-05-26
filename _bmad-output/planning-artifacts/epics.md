---
stepsCompleted: ["step-01-validate-prerequisites", "step-02-design-epics", "step-03-create-stories", "step-04-final-validation", "epic-6-production-readiness", "correct-course-lovable-1-1-ui-parity"]
inputDocuments:
  - docs/00-project-brief.md
  - docs/01-workflow-and-business-rules.md
  - docs/02-system-architecture.md
  - docs/03-database-and-models.md
  - docs/04-frontend-guide.md
  - docs/05-backend-guide.md
  - docs/06-api-reference.md
  - docs/07-task-breakdown.md
  - docs/08-prototype-gap-analysis.md
  - docs/ux/missing-ui-states.md
  - DESIGN.md
  - _bmad-output/planning-artifacts/project-context.md
  - _bmad-output/planning-artifacts/lovable-prototype-current-project-audit-2026-05-17.md
  - _bmad-output/planning-artifacts/sprint-change-proposal-2026-05-19.md
  - lovable/ (approved UX reference — workflow, dashboards, component hierarchy, RTL patterns)
lastUpdated: "2026-05-19"
---

# Yemen Flow Hub - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for Yemen Flow Hub, decomposing requirements from the domain-specific architecture documents (docs/), visual design system (DESIGN.md), and approved UX reference (lovable/) into implementable stories.

**Source Authority:**
- docs/01-workflow-and-business-rules.md — highest authority for all workflow behavior
- docs/ — architecture and business source of truth
- lovable/screenshots/ — final visual authority for 1:1 UI parity
- lovable/src/ — React source reference for layout and component intent; adapt intent only, do not copy code
- DESIGN.md — visual design system that must be updated when screenshots prove a conflict

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
**And** the account lock auto-expires after 15 minutes — a successful login after expiry must reset all failure counters and the lock flag

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
**Then** I see a multi-section form with the following fields:

**Importer Data:**
- Importer Name (text, required)
- Importer Commercial Registration Number (text, required)
- Importer Address (textarea, optional)

**Supplier / Exporter Data:**
- Supplier Name (text, required) — free-text field, NO merchant dropdown, NO merchant lookup dependency
- Supplier Country (text, required)
- Supplier Address (textarea, optional)

**Goods Data:**
- Goods Description (textarea, required)
- HS Code / Goods Classification (text, optional)
- Port of Entry (text, required)
- Expected Delivery Date (date, optional)

**Banking / Financial Metadata:**
- Currency (select: USD / EUR / SAR, required)
- Amount (number, required)
- Bank Reference Number (text, optional)

**Attachments:**
- Document upload section: each upload requires a document type label; PDF files only; multiple uploads allowed

**Notes / Remarks:**
- Notes (textarea, optional)

**And** the form is RTL-first: `dir="rtl"`, right-aligned buttons, labels above fields (Caption style, #6e6e73), 44px input height, 12px radius, 24px vertical field spacing
**And** required fields show asterisk (#ff3b30)
**And** client-side validation uses VeeValidate + Zod; errors appear inline below each field
**And** the Attachments section sends documents via `POST /api/requests/{id}/documents` after the request is created

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
**Then** the vote is recorded in `request_votes`
**And** each executive member (EXECUTIVE_MEMBER or COMMITTEE_DIRECTOR) can vote exactly once — COMMITTEE_DIRECTOR is also a voting member, not just a lifecycle manager
**And** a second vote attempt by the same user returns HTTP 422
**And** vote submission uses `lockForUpdate()` pessimistic locking to prevent race conditions
**And** no automatic finalization occurs at any vote count threshold — finalization only occurs when the Director explicitly closes the session

**Given** I am COMMITTEE_DIRECTOR
**When** I call `POST /api/voting/{id}/close`
**Then** the session closure is wrapped in a database transaction with `lockForUpdate()` on the request
**And** any executive member (EXECUTIVE_MEMBER or COMMITTEE_DIRECTOR) who has not yet voted is assigned `vote: AUTO_ABSTAIN_TIMEOUT` in `request_votes`
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

**Given** the voting session is EXECUTIVE_VOTING_OPEN and I am COMMITTEE_DIRECTOR
**When** I call `POST /api/voting/{id}/override` with `{ decision: "APPROVE"|"REJECT", justification: "..." }`
**Then** the Director's decision overrides the current tally unconditionally — a mandatory non-empty justification is required
**And** the current tally state at time of override is snapshotted and stored in `audit_logs` metadata
**And** the session is immediately closed (EXECUTIVE_VOTING_OPEN → EXECUTIVE_VOTING_CLOSED) and finalized (→ EXECUTIVE_APPROVED or EXECUTIVE_REJECTED) in one atomic operation
**And** all non-voted members receive AUTO_ABSTAIN_TIMEOUT records as part of the same transaction

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
**And** when the session is EXECUTIVE_VOTING_OPEN:
  - A "Close Voting Session" button is visible (red, with confirmation dialog)
  - A "Director Override" button is visible (amber, with a modal requiring decision + mandatory justification textarea)
  - Director Override modal shows the current tally before confirming
**And** when the session is EXECUTIVE_VOTING_CLOSED, a "Finalize Decision" button is visible with final tally summary
**And** clicking "Director Override" calls `POST /api/voting/{id}/override` — success immediately redirects to the finalized request state

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
**And** a compliance alerts panel shows simple query-driven operational alerts — MVP scope only:
  - Duplicate supplier names (same supplier_name appearing across 2+ requests in the current period)
  - Unusually high amount (requests exceeding a configurable threshold, default: USD 1,000,000)
  - Stale pending requests (requests in non-terminal, non-DRAFT status with no transition for >14 days)
  - **Excluded from MVP:** no AI risk scoring, no fraud detection engine, no AML integration, no advanced pattern recognition
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

---

## MVP Explicit Exclusions

The following features are **explicitly out of scope for MVP**. They must not be built, stubbed, or scaffolded unless a future story explicitly adds them:

| Feature | Exclusion Reason |
| ------- | ---------------- |
| Notifications module (push, email, SMS, in-app) | Post-MVP; no notification infrastructure planned |
| Settings module (user preferences, system config UI) | Post-MVP; configuration is env/seeder-driven |
| Profile management (avatar, password change, personal settings) | Post-MVP; not required for workflow operation |
| Advanced document type taxonomy (categories, hierarchies, metadata) | Post-MVP; flat document type list is sufficient |
| Merchant / Supplier management CRUD | Post-MVP; `supplier_name` is free-text for MVP |
| AI risk engine, fraud detection, AML integration | Post-MVP; compliance alerts are query-driven only |
| Real-time notifications (WebSocket, SSE, Pusher) | Post-MVP |
| Email/SMS delivery | Post-MVP |
| SSO / external identity provider | Post-MVP |
| Analytics / BI dashboards | Post-MVP; basic reports in Story 4.5 only |

---

## Epic 5: Post-MVP Institutional Operations Platform

Yemen Flow Hub evolves from MVP workflow coverage into an institutional operational platform. Governance, auditability, usability, discoverability, and operational efficiency become first-class concerns while preserving strict workflow immutability, role/org scoping, and audit requirements.

**Epic Goal:** Add scoped bank administration, production profile/settings, in-app notifications, global search, customs print preview, and advanced operational reporting without introducing prototype-only demo behavior or weakening regulatory workflow governance.

**Source Authority:**
- Stakeholder-accepted `lovable/` prototype for product surface and UX expectations.
- `_bmad-output/planning-artifacts/lovable-prototype-current-project-audit-2026-05-17.md` for current gap analysis.
- User-confirmed post-MVP scope from 2026-05-17.
- Existing project docs remain authoritative for workflow, status, role, audit, document, and API behavior.

**Non-negotiable Governance Rules:**
1. No prototype demo controls in production: no role switcher, fake login picker, mock-state admin actions, or demo reset tools.
2. Only expose navigation pages when fully implemented.
3. All new capabilities must enforce role and organization scoping at policy, query, API, and UI layers.
4. Every administrative action must be audit logged with actor user, actor role, target entity, before/after state where applicable, and timestamp.
5. Workflow governance is never overridden by admin, reporting, search, notification, preview, profile, or settings features.
6. PDF remains the canonical legal customs document; browser preview is operational UX only.
7. Reporting stays operational and governance-focused; no external BI, data warehouse, or AI analytics in this sprint.

### Story 5.1: BANK_ADMIN Role, Hierarchical RBAC & Scoped Bank Administration

As a commercial bank administrator,
I want to manage users and profile metadata for my own bank only,
So that banks can operate independently without gaining CBY privileges or cross-bank visibility.

**Acceptance Criteria:**

**Given** the system role enums are updated
**When** roles are listed in backend PHP enums, frontend TypeScript enums, validation rules, seeders, policies, navigation constants, and tests
**Then** `BANK_ADMIN` exists as a canonical post-MVP role
**And** all existing seven MVP roles retain their existing behavior
**And** `BANK_ADMIN` users must have a non-null `bank_id`
**And** `BANK_ADMIN` users cannot be created with `bank_id = NULL`

**Given** I am logged in as `BANK_ADMIN`
**When** I list users
**Then** I only receive users from my own `bank_id`
**And** I never receive CBY users or users from another bank
**And** query-level scoping enforces this before serialization

**Given** I am logged in as `BANK_ADMIN`
**When** I create or update a user
**Then** I can only create/deactivate/reset passwords for `DATA_ENTRY` and `BANK_REVIEWER` accounts in my own bank
**And** I cannot create, update, or assign `CBY_ADMIN`, `SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, `SWIFT_OFFICER`, or another `BANK_ADMIN`
**And** I cannot assign a user to another bank
**And** policy, request validation, controller/API, and UI all enforce the same constraints

**Given** I am logged in as `BANK_ADMIN`
**When** I view dashboards
**Then** I can view bank-level operational dashboard data for my own bank
**And** I cannot access global CBY dashboards, global audit logs, other-bank requests, or cross-bank reports

**Given** I am logged in as `BANK_ADMIN`
**When** I update bank profile metadata
**Then** I can update only allowed metadata fields for my own bank
**And** I cannot change workflow-critical identifiers or CBY-controlled fields unless explicitly allowed by backend policy

**Given** any `BANK_ADMIN` action succeeds or fails authorization
**When** the request completes
**Then** an audit log entry is written with actor user, actor role, bank_id, target entity, action, before/after metadata where applicable, IP/user agent where available, and timestamp
**And** authorization failures are logged without leaking cross-bank data in the response

**Technical Requirements:**

- RBAC must support hierarchical role scope: global CBY roles, bank-scoped admin roles, bank operational roles.
- Add focused backend feature tests for same-bank allowed and cross-bank denied cases.
- Add frontend tests for navigation visibility and role guard behavior.
- Do not allow `BANK_ADMIN` to override workflow transitions, immutable states, voting, support claims, SWIFT uploads, customs issuance, or audit-log access.

---

### Story 5.2: Profile, Settings & Navigation Completion

As an authenticated user,
I want production profile and settings pages,
So that account preferences and operational defaults are managed inside the platform instead of through prototype/demo UI.

**Acceptance Criteria:**

**Given** I am authenticated
**When** I navigate to `/profile`
**Then** I see my name, email, role display, bank affiliation when applicable, recent activity summary, and session/security information
**And** I can change my password through a real backend endpoint with validation and audit logging
**And** all fields are read-only unless an explicit edit action is supported by backend policy

**Given** I am authenticated
**When** I navigate to `/settings`
**Then** I can manage production-safe preferences: language preference, notification preferences, dashboard preferences, table density/page size, and default filters
**And** preferences persist through backend storage, not local mock state
**And** defaults are applied consistently across dashboards, tables, notifications, and reports where implemented

**Given** I am `CBY_ADMIN`
**When** I open admin settings
**Then** I can view and manage system configuration that is safe for runtime administration: workflow timing values, upload limits, feature toggles, and reporting controls
**And** each configurable value has validation, audit logging, and a clear fallback/default
**And** settings cannot bypass workflow governance or immutable-state rules

**Given** any sidebar or header navigation item is visible
**When** I click it
**Then** the destination page is fully implemented, role-guarded, and backed by real data
**And** no unimplemented route remains exposed in production navigation

**Technical Requirements:**

- Add profile/settings APIs where missing.
- Add storage for user preferences and safe system settings if not already present.
- Add backend policy coverage for personal settings vs admin settings.
- Exclude prototype demo reset tools, role switcher, and fake theme/language behavior unless persisted through production APIs.

---

### Story 5.3: In-App Notifications Phase 1

As an operational user,
I want scoped in-app notifications for workflow and admin events,
So that I can discover urgent work without manually checking every queue.

**Acceptance Criteria:**

**Given** a workflow or admin event occurs
**When** the event is relevant to a user
**Then** an in-app notification is created for the correct role/org scope only
**And** it never leaks cross-bank information to bank users

**Notification Events Included in Phase 1:**

- Workflow assignment alerts.
- Review and claim alerts.
- Support rejection alerts.
- Voting opened and voting closed alerts.
- Customs issued alerts.
- Account/admin actions relevant to the affected user or bank.

**Given** I am authenticated
**When** I open `/notifications`
**Then** I see a paginated notification center scoped to my role and organization
**And** I can mark one notification as read
**And** I can mark all notifications as read
**And** unread counters are shown in the production header when the feature is enabled

**Given** notification preferences exist
**When** I update preferences in `/settings`
**Then** only the allowed notification categories are enabled/disabled for my account
**And** critical governance notifications cannot be disabled if product policy requires mandatory delivery

**Technical Requirements:**

- Use existing backend notification infrastructure where possible.
- Add missing notification creation hooks in workflow/admin services.
- Role/org scoping must be enforced before notification creation and again before notification retrieval.
- Realtime websocket updates are explicitly out of scope for this story; this is Phase 1 in-app polling/API UX.

---

### Story 5.4: Global Search Phase 1

As an authenticated user,
I want global search across the entities I am allowed to see,
So that I can quickly find requests, banks, customs declarations, users, and workflow records without navigating through multiple queues.

**Acceptance Criteria:**

**Given** I use global search
**When** I type a query
**Then** results are fetched through a debounced async backend API
**And** results are always scoped to my role and organization before response serialization
**And** the UI shows filter chips and grouped result types
**And** selecting a result deep-links to the correct production page

**Searchable Entities:**

- Request number.
- Importer/merchant.
- Supplier.
- Bank.
- Customs declaration number.
- Workflow status.
- SWIFT references where stored.
- Users for admin roles only.

**Given** I am a bank-scoped role
**When** search results are returned
**Then** I only see records allowed by my bank and role scope
**And** I never see other-bank requests, users, SWIFT data, customs data, or audit-sensitive metadata beyond my permission matrix

**Given** I am an admin role
**When** I search users
**Then** user results follow the same hierarchy rules as user-management APIs
**And** `BANK_ADMIN` can only search own-bank `DATA_ENTRY` and `BANK_REVIEWER` users
**And** `CBY_ADMIN` can search users according to global admin policy

**Technical Requirements:**

- Add a dedicated backend search endpoint with indexed query paths.
- Add recent searches per user if storage exists or as a small persisted preference.
- Add keyboard shortcut support only if it does not conflict with browser/system shortcuts and is accessible.
- Do not search raw audit logs for non-`CBY_ADMIN` users.

---

### Story 5.5: Customs Print Preview Page

As a bank or CBY operations user with customs visibility,
I want a browser print-preview page for issued customs declarations,
So that I can inspect and print the declaration operationally while the official PDF remains the legal document.

**Acceptance Criteria:**

**Given** a customs declaration exists for a request
**When** I navigate to `/requests/{id}/customs-preview`
**Then** the page renders a print-optimized RTL preview using the same data source as the official PDF
**And** the preview is read-only and immutable
**And** the page includes a "Download Official PDF" action
**And** browser print support works using print-specific CSS
**And** watermark/status indicators are shown when useful, such as "Preview" or "Official PDF is canonical"

**Given** I do not have permission to view the customs declaration
**When** I request the preview
**Then** the backend/API and frontend guard deny access according to the document permission matrix
**And** no customs data is leaked in the error response

**Given** the official PDF and preview are generated
**When** data is compared
**Then** both use the same canonical declaration/request fields
**And** the browser preview does not introduce editable or unofficial values

**Technical Requirements:**

- Add route `/requests/{id}/customs-preview`.
- Reuse existing customs declaration API or add a preview-safe endpoint returning the same canonical fields.
- Add print CSS for Arabic RTL layout.
- PDF download remains canonical legal output; preview is operational UX only.

---

### Story 5.6: Advanced Operational Reporting

As a CBY or authorized bank operations user,
I want advanced operational and governance-focused reports,
So that I can monitor throughput, queue aging, SLA risk, voting behavior, and bank activity without external BI tools.

**Acceptance Criteria:**

**Operational Reports:**

**Given** I have reporting access
**When** I open `/reports`
**Then** I can view workflow throughput, pending queue aging, SLA delay indicators, approval/rejection ratios, support committee activity, voting outcomes, and customs issuance metrics
**And** all report widgets support date-range filtering
**And** metrics are scoped to my role and organization

**Bank Reports:**

**Given** I am a bank-scoped reporting user
**When** I view bank reports
**Then** I see only bank-specific request statistics, importer/supplier activity, approval success rates, and historical workflow volume for my own bank
**And** `CBY_ADMIN` can view cross-bank comparisons where policy permits

**Executive Reports:**

**Given** I am `COMMITTEE_DIRECTOR`, `EXECUTIVE_MEMBER`, or `CBY_ADMIN`
**When** I view executive reports
**Then** I see voting participation, abstention metrics, committee performance, and trend dashboards according to my role permissions
**And** bank-scoped users cannot see executive-only details beyond their permitted request/document visibility

**Exports and Presets:**

**Given** I have reporting access
**When** I export a report
**Then** I can export to Excel and PDF using the same role-scoped filtered dataset
**And** export events are audit logged
**And** saved filters/presets can be created, updated, and reused by the owning user or role scope as allowed

**Technical Requirements:**

- Review and harden existing `ReportController` deferred issues before building UI polish.
- Use performant indexed queries and aggregation; avoid full-table loading for large datasets.
- Add backend tests for role-scoped reporting visibility and export datasets.
- No external BI dependencies, data warehouse complexity, or AI analytics.
- Reporting must remain operational and governance-focused, not speculative risk scoring.

---

### Story 5.7: Approved Lovable Prototype Parity & Production UI Alignment

As a stakeholder and product owner,
I want the production Nuxt application to match the accepted Lovable prototype as closely as possible within the current production tech stack,
So that stakeholder-approved UX intent is preserved while replacing demo-only behavior with secure, audited, production implementations.

**Acceptance Criteria:**

**Given** the accepted prototype in `lovable/`
**When** the parity audit runs
**Then** every stakeholder-facing route in `lovable/src/routes/*` is mapped to one of:
- implemented production route,
- production-equivalent route with a different path/name,
- intentionally excluded demo-only behavior,
- explicitly deferred post-sprint item with reason.

**Given** a prototype screen is part of the approved stakeholder UX
**When** its production equivalent is implemented or reviewed
**Then** the Nuxt/Vue/Tailwind UI matches the Lovable prototype as closely as practical for:
- page structure,
- RTL layout,
- sidebar/header shell,
- page headers and actions,
- tables,
- cards,
- badges,
- forms,
- dialogs,
- tabs,
- notification surfaces,
- search surfaces,
- print-preview layout,
- report/dashboard widgets,
- empty/loading/error states.

**Given** the current production tech stack is Nuxt 4, Vue, TypeScript, Tailwind CSS, shadcn-vue-compatible patterns, and Pinia
**When** prototype UI is translated
**Then** the implementation uses the existing production stack and component conventions
**And** it does not copy React/TanStack code from Lovable directly
**And** it does not use Lovable mock stores as production state
**And** it adapts visual intent rather than importing prototype implementation details.

**Given** a Lovable feature is demo-only
**When** parity is assessed
**Then** it is excluded from production implementation
**And** the exclusion is documented in the parity checklist
**And** excluded items include at minimum: role switcher, fake demo login picker, mock-state edits, demo reset tools, prototype footer/demo labels, and UI-only authorization shortcuts.

**Given** a route appears in production navigation
**When** the user opens it
**Then** it is fully implemented, role-guarded, scoped, backed by production APIs, visually aligned with the accepted prototype, and has no placeholder-only state
**And** if a page is not ready, it is not exposed in navigation.

**Given** prototype parity is complete
**When** QA reviews the app
**Then** a final parity report is produced showing:
- route-by-route comparison,
- implemented production equivalents,
- known visual differences and rationale,
- demo-only exclusions,
- remaining deferred items,
- screenshots or manual verification notes for key flows.

**Technical Requirements:**

- Use `lovable/` as read-only reference; do not modify prototype files.
- Use production docs and `DESIGN.md` as constraints where prototype details conflict with governance, canonical roles, status enums, security, or audit rules.
- Keep Arabic RTL first and desktop-first responsive behavior.
- Verify core pages at desktop and <=600px responsive widths where practical.
- This story is a parity/signoff story; it should run after Stories 5.1-5.6 or be used as a rolling QA checklist across the sprint.

---

## Epic 6: Production Readiness & Full Prototype Parity

**Purpose:** Close every gap identified in `docs/08-prototype-gap-analysis.md` so that the live production app matches the stakeholder-approved Lovable prototype (in `lovable/`) for all 8 roles. This epic covers design tokens, appshell, the new BANK-ADMIN role, login/auth, missing CBY Admin pages, request-detail/voting parity, and final polish including dark mode.

**Source authorities (in order):**
1. `docs/08-prototype-gap-analysis.md` — gap registry and sprint plan
2. `docs/ux/missing-ui-states.md` — UX specs for RequestWizard, SwiftUploadModal, EmptyState, SkeletonLoaders, FormValidation
3. `DESIGN.md` — corrected design token system (updated 2026-05-18)
4. `lovable/screenshots/` — visual reference for all 8 roles (80+ screenshots)
5. `lovable/src/` — prototype source (read-only; adapt intent, do not copy code)

**Governance constraints (non-negotiable):**
- Canonical role enum: `DATA_ENTRY`, `BANK_REVIEWER`, `SWIFT_OFFICER`, `SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, `CBY_ADMIN`, `BANK_ADMIN` (eighth role added in Story 6.3.1)
- Canonical status enum: 18 values — frozen, as defined in `AGENTS.md`
- All workflow transitions via `WorkflowService::transition()` — never direct model mutation
- Do NOT modify `lovable/` contents
- All frontend changes committed to both frontend team repo and root monorepo

**Estimated total effort:** ~56 hours across 7 sprints

---

### Story 6.1: Design Token & Typography Foundation

As a developer and stakeholder reviewer,
I want the production frontend to use the correct design tokens from the confirmed Lovable prototype,
So that every page renders with the stakeholder-approved colors, fonts, and spacing system instead of the previously incorrect values.

> **Status: COMPLETED** (committed 2026-05-18 — Sprint 6.1)
> Closes gaps: A1, A2, A3, A4, A5, A6, A9, E3

**Acceptance Criteria:**

**Given** `frontend/app/assets/css/main.css`
**When** the page loads
**Then** `--color-primary` is `#0066cc` (not `#0071e3`)
**And** `--color-background` is `#ffffff` (not `#f5f5f7`)
**And** all 8 surface-container variants are defined (`surface-dim` through `surface-container-highest`)
**And** semantic status triplets exist for success/error/warning/info (text + bg + border each)
**And** elevation tokens `--shadow-sm`, `--shadow-md`, `--shadow-lg`, `--shadow-focus` are defined
**And** `--container-max` is `1600px`
**And** `--sidebar-expanded` is `280px` and `--sidebar-collapsed` is `72px`

**Given** `frontend/app/assets/css/fonts.css`
**When** the stylesheet loads
**Then** Cairo (weights 400/500/600/700) is imported for headlines
**And** Tajawal (weights 400/500/700) is imported for section headers
**And** IBM Plex Sans Arabic (all weights) is imported for body copy
**And** Inter is imported as Latin fallback

**Given** `frontend/app/layouts/default.vue`
**When** the layout renders
**Then** `max-width` of `.app-content` uses `var(--container-max, 1600px)`
**And** sidebar offset uses `var(--sidebar-expanded, 280px)`

**Given** a heading element (`h1`, `h2`, `h3`) anywhere in the app
**When** rendered
**Then** it uses the Cairo typeface

**Given** a section header or role label
**When** rendered
**Then** it uses the Tajawal typeface

**Given** body copy, form labels, or table data
**When** rendered
**Then** it uses IBM Plex Sans Arabic

**Technical Requirements:**
- All token changes made in `@theme {}` block only — no hardcoded hex values in component CSS
- Legacy aliases (`--color-text-primary`, `--color-approved`, etc.) retained for backward compat with existing components until migrated
- `*:focus-visible` uses `box-shadow: var(--shadow-focus)` instead of `outline: 2px solid primary`
- No new components; only `main.css`, `fonts.css`, and `default.vue` are changed

---

### Story 6.2: AppShell & Layout Parity

As a user of any role,
I want the sidebar, header, and overall app shell to match the stakeholder-approved prototype layout,
So that navigation feels consistent with the approved design and all role-specific pages are accessible.

> **Estimate:** ~6 hours
> Closes gaps: B1, B2, A7, E1, E2, E4

**Acceptance Criteria:**

**Given** any authenticated user viewing the app
**When** they click the collapse chevron at the bottom of the sidebar
**Then** the sidebar shrinks from 280px to 72px (icon-only, no text labels)
**And** the main content area expands accordingly
**And** the collapsed/expanded state persists in `localStorage` across page reloads

**Given** the sidebar is in collapsed state (72px)
**When** the user hovers over a nav icon
**Then** a tooltip appears showing the nav item label in Arabic

**Given** any authenticated page
**When** the user scrolls down
**Then** the sticky header remains visible with a semi-transparent blur background (`bg-surface/80 backdrop-blur-md`)
**And** content behind the header is blurred, not visible through solid white

**Given** the sidebar component
**When** rendered with sidebar-specific CSS tokens defined
**Then** it uses `--sidebar`, `--sidebar-foreground`, `--sidebar-primary`, `--sidebar-primary-foreground`, `--sidebar-accent`, `--sidebar-border`, `--sidebar-ring` tokens
**And** these tokens are separate from the main surface tokens (enabling future dark mode)

**Given** a user with role `BANK_ADMIN`
**When** they view the sidebar
**Then** they see nav items: لوحة التحكم, الطلبات, التجار, الموظفون, التقارير, الإشعارات

**Given** a user with role `CBY_ADMIN`
**When** they view the sidebar
**Then** they see admin nav items including: لوحة التحكم, إدارة المستخدمين (cby-staff), الكيانات (entities), الصلاحيات (roles), الإعدادات, التقارير, التدقيق

**Given** viewport ≤ 600px
**When** the mobile menu is toggled
**Then** the sidebar overlays the content as a drawer
**And** content padding reduces to 12px

**Given** viewport 601px–1024px (tablet)
**When** the page loads
**Then** content padding reduces to 16px

**Technical Requirements:**
- Sidebar collapse toggle: chevron button at sidebar bottom; `useSidebar` composable managing state + localStorage
- Header: `position: sticky; top: 0; z-index: 30; backdrop-filter: blur(12px)` with `background: rgba(255,255,255,0.8)`
- Sidebar nav items defined in a typed config array with `role[]` guards — not hardcoded in template
- All new sidebar tokens added to `@theme {}` in `main.css` (not inline styles)
- Commit to both frontend team repo and root monorepo

---

### Story 6.3.1: BANK_ADMIN Role — Backend Registration & API Scope

As a platform architect,
I want the `BANK_ADMIN` role registered in the canonical backend enum with correct RBAC and org-scoping,
So that subsequent frontend pages can authenticate and call APIs as a BANK_ADMIN user.

> **Estimate:** ~4 hours
> Prerequisite for all 6.3.x stories

**Acceptance Criteria:**

**Given** `app/Enums/UserRole.php`
**When** inspected
**Then** `BANK_ADMIN = 'BANK_ADMIN'` exists in the enum

**Given** `database/seeders/`
**When** seeder runs
**Then** a test BANK_ADMIN user exists with a `bank_id` foreign key pointing to a seeded bank

**Given** a `BANK_ADMIN` user is authenticated
**When** they call `GET /api/requests`
**Then** the response includes only requests belonging to their bank (org-scoped)
**And** they can see full internal workflow statuses (not simplified DATA_ENTRY statuses)

**Given** a `BANK_ADMIN` user
**When** they call any CBY-internal endpoint (audit logs, voting, support claim, etc.)
**Then** the API returns `HTTP 403 WORKFLOW_IMMUTABLE_STATE` or role-guard 403

**Given** a `BANK_ADMIN` user
**When** they call `GET /api/users?bank_id={their_bank}`
**Then** they receive only users belonging to their bank (not all system users)

**Given** any `BANK_ADMIN` action
**When** it succeeds or fails authorization
**Then** it is logged to `audit_logs` with `actor_role = BANK_ADMIN`

**Technical Requirements:**
- Add `BANK_ADMIN` to `UserRole` enum (backend) and `UserRole` TypeScript enum (frontend)
- Add `BANK_ADMIN` to `ROLE_LABELS` constant in `frontend/app/constants/workflow.ts`
- Update `RequestPolicy` to allow BANK_ADMIN read access to own-bank requests
- Update `UserPolicy` to allow BANK_ADMIN to list/manage own-bank users only
- No new workflow transitions — BANK_ADMIN is an administrative role, not a workflow actor
- Commit backend changes to both backend team repo and root monorepo
- Commit frontend enum/constants changes to both frontend team repo and root monorepo

---

### Story 6.3.2: BANK_ADMIN Dashboard

As a BANK_ADMIN user,
I want a role-specific dashboard showing my bank's request activity, KPIs, and quick actions,
So that I can monitor and manage my bank's financing operations at a glance.

> **Estimate:** ~3 hours
> Depends on: Story 6.3.1

**Acceptance Criteria:**

**Given** I am logged in as `BANK_ADMIN` and navigate to `/dashboard`
**When** the page loads
**Then** I see 5 KPI cards: total requests, pending requests, approved requests, rejected requests, total financed amount

**Given** the dashboard has loaded
**When** I view the chart section
**Then** a line chart titled "حركة طلبات البنك الشهرية" is visible
**And** it shows request volume over the last 6 months for my bank only

**Given** the dashboard
**When** I view the quick actions section
**Then** I see a "تقديم طلب جديد" primary button that navigates to `/requests/new`

**Given** the dashboard
**When** I view the recent requests table
**Then** it shows up to 10 most recent requests for my bank with: reference ID, merchant, amount, status badge, date

**Given** my bank has no requests yet
**When** the dashboard loads
**Then** the recent requests table shows the EmptyState component (variant: `dashboard-queue` from `docs/ux/missing-ui-states.md` Spec 4)
**And** KPI cards show `0` values, not loading errors

**Given** the dashboard is loading data
**When** the API call is in-flight
**Then** skeleton loaders (stat-card variant from `docs/ux/missing-ui-states.md` Spec 5) replace the KPI cards
**And** the table shows skeleton-row loaders

**Technical Requirements:**
- Component: `BankAdminDashboard.vue` in `frontend/app/components/dashboard/`
- KPI data: extend `GET /api/dashboard` or add `GET /api/dashboard/bank` returning bank-scoped stats
- Chart: use a lightweight chart library (Chart.js via vue-chartjs) or an SVG sparkline — no new heavy dependencies unless already installed
- Dashboard page delegates to `BankAdminDashboard.vue` based on `auth.user.role === BANK_ADMIN`
- Commit to both repos

---

### Story 6.3.3: BANK_ADMIN Merchant Management

As a BANK_ADMIN user,
I want to manage my bank's registered merchants via a card-grid interface with add/edit/suspend actions,
So that I can maintain accurate importer records for financing requests.

> **Estimate:** ~3 hours
> Depends on: Story 6.3.1

**Acceptance Criteria:**

**Given** I navigate to `/merchants`
**When** the page loads as `BANK_ADMIN`
**Then** I see a card grid (3 columns on desktop, 2 on tablet, 1 on mobile) of merchant cards
**And** each card shows: merchant name, commercial registration number, tax ID, status badge (نشط / موقوف), edit button, suspend/activate toggle

**Given** I click "إضافة تاجر جديد"
**When** the modal opens
**Then** it contains: اسم التاجر (required), رقم السجل التجاري (required), الرقم الضريبي (required), العنوان (optional), نوع النشاط (select, optional)
**And** the "حفظ" button is disabled until required fields are valid
**And** submitting calls `POST /api/merchants`

**Given** I click "تعديل" on a merchant card
**When** the edit modal opens
**Then** it is pre-filled with the merchant's current data
**And** submitting calls `PUT /api/merchants/{id}`

**Given** I click "تعليق" on an active merchant
**When** confirmed in a confirmation dialog
**Then** the merchant status changes to "موقوف" and the card badge updates
**And** the action calls `PUT /api/merchants/{id}` with `status: suspended`

**Given** there are no merchants yet
**When** the page loads
**Then** the EmptyState component shows (variant: `merchants` from `docs/ux/missing-ui-states.md` Spec 4)
**And** the CTA "تسجيل تاجر جديد" opens the add merchant modal

**Given** the page is loading merchants
**When** the API is in-flight
**Then** skeleton card loaders (card-grid variant from `docs/ux/missing-ui-states.md` Spec 5) are shown

**Technical Requirements:**
- Backend: `MerchantController` with `index` (org-scoped), `store`, `update` actions; `MerchantPolicy` scopes to `bank_id`
- Frontend: `merchants.vue` page + `MerchantCard.vue` + `MerchantModal.vue` components
- Card grid uses CSS Grid: `grid-template-columns: repeat(auto-fill, minmax(280px, 1fr))`
- Modal uses shadcn-vue Dialog pattern
- Commit to both repos

---

### Story 6.3.4: BANK_ADMIN Staff Management

As a BANK_ADMIN user,
I want to manage my bank's staff members with role and department assignments,
So that I can onboard new bank employees and control their access levels.

> **Estimate:** ~3 hours
> Depends on: Story 6.3.1

**Acceptance Criteria:**

**Given** I navigate to `/staff`
**When** the page loads as `BANK_ADMIN`
**Then** I see a table of bank staff with columns: الاسم, الدور, القسم, الحالة (badge), تاريخ الإنضمام, الإجراءات
**And** rows show only users where `bank_id = my_bank_id`

**Given** I click "إضافة موظف"
**When** the modal opens
**Then** it contains: الاسم الكامل (required), البريد الإلكتروني (required), الدور (select: DATA_ENTRY | BANK_REVIEWER only), القسم (text, optional), كلمة المرور الأولية (required)
**And** submitting calls `POST /api/users` with `bank_id` automatically set to my bank

**Given** I click "تعديل" on a staff row
**When** the edit modal opens
**Then** the role select only shows `DATA_ENTRY` and `BANK_REVIEWER` (BANK_ADMIN cannot create CBY-level roles)
**And** submitting calls `PUT /api/users/{id}`

**Given** I deactivate a staff member
**When** confirmed
**Then** `is_active` is set to `false` via `PUT /api/users/{id}`
**And** the status badge changes to "غير نشط"

**Given** there are no staff members yet
**When** the page loads
**Then** EmptyState variant `staff` is shown with CTA "إضافة أول موظف"

**Technical Requirements:**
- Backend: extend existing `UserController` with BANK_ADMIN policy: can only manage `DATA_ENTRY` and `BANK_REVIEWER` users in own bank
- Frontend: `staff.vue` page + `StaffModal.vue` component
- Role select in modal filtered to `['DATA_ENTRY', 'BANK_REVIEWER']` for BANK_ADMIN (cannot escalate to CBY roles)
- Commit to both repos

---

### Story 6.3.5: BANK_ADMIN Request Wizard (4-Step)

As a BANK_ADMIN or DATA_ENTRY user,
I want to submit a new financing request through a clear 4-step wizard,
So that I can provide all required information in a guided, validated, step-by-step form before sending for review.

> **Estimate:** ~6 hours
> Depends on: Story 6.3.1
> UX spec: `docs/ux/missing-ui-states.md` Spec 1 (RequestWizard), Spec 3 (FormValidation)

**Acceptance Criteria:**

**Given** I navigate to `/requests/new`
**When** the page loads
**Then** I see a horizontal stepper with 4 steps: بيانات الطلب → بيانات المورد → الوثائق المطلوبة → المراجعة والإرسال
**And** the active step is highlighted in primary blue with a ring; completed steps show a green checkmark; future steps show a gray empty circle

**Given** I am on Step 1 (بيانات الطلب)
**When** I fill in the form
**Then** I see: نوع الواردات (select, required), مبلغ التمويل (number with inline currency selector, required), العملة (select, required), شروط الدفع (select, required), تاريخ الاستحقاق (date picker, optional), المستورد (searchable select for BANK_ADMIN; read-only prefilled for DATA_ENTRY), ملاحظات إضافية (textarea, optional)

**Given** I click "التالي" on Step 1 with empty required fields
**When** validation runs
**Then** each empty required field shows a 2px `#c62828` border + ⚠ icon + Arabic error message below
**And** a form-level error banner appears at the top: `#fff8e1` background, "يوجد (N) حقول تحتاج إلى تصحيح قبل المتابعة."
**And** the page auto-scrolls to the first error field

**Given** I am on Step 2 (بيانات المورد)
**When** I fill in the form
**Then** I see: اسم المورد (required), رقم الفاتورة (required), بلد المنشأ (searchable select, required), تاريخ الفاتورة (date, required), ميناء الوصول (select: عدن/الحديدة/المكلا, required), ميناء الشحن (text, optional), الجمارك المختصة (auto-filled from port, overridable select), رقم بوليصة الشحن (text, optional)

**Given** I am on Step 3 (الوثائق المطلوبة)
**When** I view the upload grid
**Then** I see a 2×2 grid of upload zones: الفاتورة الأولية (إلزامي), السجل التجاري (إلزامي), البطاقة الضريبية (إلزامي), مستندات إضافية (اختياري)
**And** each zone has a dashed `#cccccc` border with upload icon, title, format hint, and "أضغط للرفع" button
**And** drag-and-drop is supported

**Given** I upload a file in Step 3
**When** upload succeeds
**Then** the zone shows: file name chip + ✓ icon + ✗ remove button + green `#1b5e20` border + `#f1f8f4` background

**Given** I upload a non-PDF file in Step 3
**When** upload is attempted
**Then** the zone shows an error state: 2px `#c62828` border + "يجب أن يكون الملف بصيغة PDF أو JPG" message

**Given** I am on Step 4 (المراجعة والإرسال)
**When** I view the page
**Then** I see a read-only summary card of all entered data grouped by section
**And** I see an acknowledgment checkbox: "أُقر بأن جميع البيانات والمستندات المقدمة صحيحة وكاملة."
**And** the "إرسال للمراجعة" primary button is disabled until the checkbox is checked

**Given** I click "حفظ كمسودة" on any step
**When** the action completes
**Then** the request is saved as `DRAFT` status via `POST /api/requests` with `status: draft`
**And** I remain on the current step

**Given** I click "إرسال للمراجعة" on Step 4 with checkbox checked
**When** the submission succeeds
**Then** the request status changes to `SUBMITTED`
**And** I am redirected to the request detail page with a success toast

**Technical Requirements:**
- Component: `RequestWizard.vue` with child step components: `WizardStep1.vue`, `WizardStep2.vue`, `WizardStep3.vue`, `WizardStep4.vue`
- Stepper: `WizardStepper.vue` — receives `steps[]`, `currentStep`, emits `step-click` (only for completed steps)
- State: managed via `useRequestWizard()` composable; no business logic in Vue components
- Validation: VeeValidate + Zod per-step schemas; only current step validated on "التالي"
- File uploads: reuse `DocumentUploadZone.vue` pattern from Story 2.2
- Bottom nav bar: sticky, `position: sticky; bottom: 0; background: #ffffff; border-top: 1px solid #cccccc; z-index: 10`
- DATA_ENTRY: merchant field rendered as read-only text, pre-filled from `auth.user.organization`
- Commit to both repos

---

### Story 6.4: Login Page Redesign & OTP/MFA Flow

As a user on any role,
I want to log in through a professional two-column login page with an OTP verification step,
So that the first interaction with the system matches the stakeholder-approved design and security requirements.

> **Estimate:** ~4 hours
> Closes gaps: C1, D1
> Reference screenshots: `CBY_ADMIN/login.png`, `CBY_ADMIN/login-otp.png`

**Acceptance Criteria:**

**Given** I navigate to `/login`
**When** the page loads
**Then** I see a two-column layout: left column (50%) with the login form, right column (50%) with a primary blue (`#0066cc`) branded hero panel
**And** the hero panel shows the CBY logo, platform name in Arabic, and a tagline

**Given** the login page on desktop
**When** rendered
**Then** the form column has a centered card with: CBY logo, platform name, email input, password input, "تسجيل الدخول" primary button, "مصادقة متعددة العوامل (MFA) مفعّلة" footer note

**Given** the login page on mobile (≤600px)
**When** rendered
**Then** only the form column is shown; the hero panel is hidden

**Given** I submit valid credentials
**When** the API returns a response requiring MFA
**Then** the page transitions to the OTP step without a full page reload
**And** I see 6 individual single-digit input cells, a "تأكيد ودخول" primary button, and a "رجوع" link

**Given** I am on the OTP step
**When** I type a digit
**Then** focus automatically moves to the next cell

**Given** I am on the OTP step
**When** I paste a 6-digit code
**Then** all 6 cells are filled automatically

**Given** I submit a correct OTP
**When** the API validates it
**Then** I am redirected to `/dashboard`

**Given** I submit an incorrect OTP
**When** the API returns an error
**Then** all 6 cells show a red error border (`#c62828`)
**And** an error message appears: "الرمز المدخل غير صحيح. حاول مرة أخرى."

**Technical Requirements:**
- Login page: `login.vue` — two-column CSS Grid layout (50/50 on ≥ 1024px, full-width below)
- OTP step: rendered in the same `login.vue` page via `v-if="otpStep"` — not a separate route
- OTP cells: 6× `<input type="text" maxlength="1">` with `keydown` handler for auto-advance and `paste` handler for fill-all
- Backend: existing Sanctum login + add `POST /api/auth/verify-otp` endpoint (or extend current login to support MFA token validation)
- The demo RoleSwitcher (persona picker) in the Lovable login form is a **demo-only feature** — do not implement it for production parity. Use real backend-authenticated users per role.
- Commit to both repos

---

### Story 6.5: Missing CBY Admin Pages

As a CBY_ADMIN user,
I want access to system user management, entity management, role management, a full settings page, and a profile page,
So that I can administer the platform fully from the browser without backend-only tooling.

> **Estimate:** ~10 hours
> Closes gaps: C4, C6, C7, C8, C9

**Acceptance Criteria:**

**Given** I navigate to `/admin/cby-staff`
**When** the page loads as `CBY_ADMIN`
**Then** I see a table of all system users (all banks, all roles) with: name, email, role badge, bank/entity, status, last-seen, actions (edit, deactivate)
**And** I can filter by role, bank, and status

**Given** I click "إضافة مستخدم نظام"
**When** the modal opens
**Then** it contains fields for all canonical roles including `CBY_ADMIN` and `BANK_ADMIN`
**And** bank_id is required only for bank roles (`DATA_ENTRY`, `BANK_REVIEWER`, `BANK_ADMIN`)

**Given** I navigate to `/admin/entities`
**When** the page loads
**Then** I see a table of all registered banks/entities with: entity name, entity type, license number, status, assigned users count, actions (edit, activate/deactivate)

**Given** I navigate to `/admin/roles`
**When** the page loads
**Then** I see a read-only table of all 8 canonical roles with their descriptions, permissions list, and user count

**Given** I navigate to `/settings`
**When** the page loads as `CBY_ADMIN`
**Then** I see 5 production tabs: سير العمل, البريد الإلكتروني, الإشعارات, الأمن, عام
**And** demo-data reset controls are not rendered in production parity

**Given** I navigate to `/settings` tab "الأمن"
**When** I view the content
**Then** I see account lockout configuration (threshold, duration), session timeout settings, and MFA enforcement toggle

**Given** I navigate to `/profile`
**When** the page loads for any authenticated user
**Then** I see: avatar upload/change, full name, email (read-only), role badge, stats (total actions, last login), recent activity list
**And** I see "تغيير كلمة المرور" button and "تفعيل/إلغاء MFA" toggle

**Technical Requirements:**
- `/admin/cby-staff` route: guard with `role === CBY_ADMIN` middleware
- `/admin/entities`: uses existing banks API (`GET /api/banks`) — extend if needed
- `/admin/roles`: static data component; role definitions from `AGENTS.md` constants — no API needed
- `/settings`: 5-tab production layout using shadcn-vue `Tabs`; each tab is a separate component; do not implement "بيانات العرض التوضيحي" demo reset controls in production parity
- `/profile`: available to all authenticated roles; uses `GET /api/auth/user` for data; avatar upload calls `POST /api/profile/avatar`
- Commit to both repos

---

### Story 6.6: Request Detail & Voting Panel Parity

As a workflow participant (any role with access to request detail),
I want the request detail page to match the prototype with full voting panel, document checklist, and correct tab structure,
So that all workflow actions are visible and usable as approved.

> **Estimate:** ~8 hours
> Closes gaps: B4, B6, B8, C3, D7, D8

**Acceptance Criteria:**

**Given** I view any request detail page
**When** the page loads
**Then** I see 3 to 4 tabs depending on workflow stage: المعلومات, الوثائق, الأطراف, (التصويت — only when `EXECUTIVE_VOTING_OPEN` or `EXECUTIVE_VOTING_CLOSED`)

**Given** I view the "الوثائق" tab
**When** it renders
**Then** I see `DocumentChecklist.vue`: a list of required and optional documents for the current workflow stage
**And** each document row shows: document type, required/optional badge, upload status (مرفوع / مطلوب / غير مطلوب), file name if uploaded, download button if uploaded

**Given** the request is in `EXECUTIVE_VOTING_OPEN` state and I view the "التصويت" tab
**When** it renders
**Then** I see the voting tally bar: موافقة N | رفض N | امتناع N
**And** I see 6 committee member rows with: member name, email (@cby.gov.ye), and individual vote status badge (موافق / رافض / ممتنع / لم يصوت بعد)
**And** if I have not yet voted, I see a vote form: optional textarea + 3 buttons (موافق green / رافض red / ممتنع gray)

**Given** I am `COMMITTEE_DIRECTOR` viewing the voting tab
**When** the session is not yet open
**Then** I see "فتح جلسة التصويت" button only
**When** the session is open
**Then** I see "إغلاق جلسة التصويت" button + tie-break notice if `yesCount === noCount`

**Given** `yesCount === noCount` in an open voting session
**When** any user views the voting panel
**Then** a notice banner appears: "تعادل — يُرجَّح صوت المدير عند التعادل"

**Given** a request is in a locked state
**When** I view the detail page
**Then** `LockedBanner.vue` shows one of 3 variants:
- `locked`: Lock icon, "هذا الطلب مقفل ولا يمكن اتخاذ أي إجراء عليه" (terminal states)
- `readonly`: Eye icon, "هذا الطلب في وضع القراءة فقط" (intermediate locked states)
- `pending`: Clock icon, "هذا الطلب قيد المراجعة — لا يمكن إجراء تعديلات حتى اكتمال المرحلة الحالية"

**Given** the support claim is held by another user
**When** I view the request in `SUPPORT_REVIEW_IN_PROGRESS`
**Then** a blocking banner appears: "هذا الطلب محجوز حالياً بواسطة [اسم المستخدم]" (from `isClaimedByOther()`)
**And** all action buttons are disabled

**Technical Requirements:**
- `DocumentChecklist.vue`: accepts `requestId`, `currentStatus` props; calls `GET /api/requests/{id}/documents`; shows per-stage required docs from a stage-to-docs mapping constant
- `VotingPanel.vue`: refactor existing component to show 6 member rows using `GET /api/voting/{sessionId}/votes`; tally bar using `computed` from votes array
- `LockedBanner.vue`: add `variant` prop (`'locked' | 'readonly' | 'pending'`); map variant to icon + message
- Tie-break notice: `computed(() => votes.yes === votes.no && session.isOpen)` → renders notice banner
- `isClaimedByOther()`: existing helper — verify it renders a banner on the detail page (not just console warning)
- Commit to both repos

---

### Story 6.7: Polish, Dark Mode & Final Parity

As a stakeholder reviewing the application,
I want dark mode, a consistent Lucide icon system, the customs print page, and final component polish,
So that the application is production-ready and matches the full approved prototype for the final acceptance review.

> **Estimate:** ~8 hours
> Closes gaps: B9, B11, C10, D4

**Acceptance Criteria:**

**Given** any authenticated page
**When** I click the dark/light toggle in the header
**Then** the entire interface switches to a dark color scheme without page reload
**And** the preference is persisted in `localStorage`

**Given** dark mode is active
**When** I view any page
**Then** `background` renders as `#0c121a` (inverse-surface)
**And** `on-surface` text renders as `#f0f0f0` (inverse-on-surface)
**And** `primary` color remains `#0066cc` but interactive elements use `inverse-primary` (`#4da6ff`) for contrast

**Given** any icon in the app (sidebar, actions, status indicators)
**When** rendered
**Then** it uses a Lucide Vue icon (`lucide-vue-next`) rather than a hardcoded SVG path
**And** `SidebarIcon.vue` is replaced by direct Lucide icon usage

**Given** I navigate to `/customs/{id}/print`
**When** the page loads
**Then** I see an A4 paper preview of the customs declaration document
**And** I see zoom controls and a "طباعة" button
**And** an issuance confirmation dialog appears before the print action executes

**Given** a shadcn-vue Dialog (modal)
**When** it opens
**Then** the overlay has `background: rgba(12, 18, 26, 0.4)` with `backdrop-filter: blur(4px)`

**Technical Requirements:**
- Dark mode: CSS class strategy — add/remove `dark` class on `<html>`; define dark-variant CSS custom properties in `[html.dark]` selector block in `main.css`
- Lucide Vue: `npm install lucide-vue-next`; create `Icon.vue` wrapper for consistent sizing; replace all hardcoded SVGs in `SidebarIcon.vue` and throughout
- Customs print page: `pages/customs/[id]/print.vue`; uses `@media print` CSS for clean output; zoom via CSS `transform: scale()`
- shadcn-vue Dialog overlay: override `.dialog-overlay` CSS in `main.css`
- Commit to both repos

---

## Epic 7: Lovable 1:1 UI Parity Rework

**Purpose:** Rework the production Nuxt UI screen-by-screen until it visually matches the stakeholder-approved Lovable React prototype. This epic exists because prior prototype parity work closed functional and route gaps, but did not enforce screenshot-level acceptance. Epic 7 is the acceptance pass.

**Correct-course decision date:** 2026-05-19

**Source authorities:**
1. `docs/01-workflow-and-business-rules.md` and `docs/03-database-and-models.md` remain final authority for workflow, roles, statuses, security, and audit behavior.
2. `lovable/screenshots/` is final authority for visual UI parity. If `DESIGN.md` conflicts with screenshots, update `DESIGN.md` and the implementation to match the screenshot.
3. `lovable/src/` is the React source reference for layout and component intent. Adapt intent only; do not copy React/TanStack code.
4. `DESIGN.md` is the tokenized design-system expression of the screenshots and must be kept current.
5. `frontend/app/` must implement with Nuxt 4, Vue, TypeScript, Tailwind CSS v4, Pinia, and shadcn-vue.

**Definition of 1:1 parity:**
- Same layout structure, spacing, alignment, typography, color, border radius, shadow, component states, and responsive behavior.
- No obvious visual difference when comparing the Nuxt screen to the Lovable screenshot at matching viewport widths.
- All production data comes from Laravel APIs. If a Lovable screen needs data that no API provides, the story must create the backend API, policy, tests, and frontend integration.
- Every screen uses shadcn-vue primitives as the base where a matching primitive exists, then customizes the primitive to match Lovable.
- Demo-only features remain excluded even if visible in the prototype: role switchers, demo login shortcuts, demo reset tools, mock-state editing, fake authorization bypasses, and prototype/demo labels.

**Required story evidence:**
- Lovable React source path(s)
- Lovable screenshot path(s)
- Nuxt target path(s)
- Backend API path(s), if the story requires data not already exposed
- Desktop Playwright screenshot comparison
- Mobile <=600px Playwright screenshot comparison
- Story completion checklist documenting any intentional demo-only omissions

**Common technical requirements for all Epic 7 stories:**
- Run SocratiCode before modifying existing files: `codebase_symbol` / `codebase_search`, then `codebase_impact` for touched components/services.
- Keep `lovable/` read-only.
- Keep business logic out of Vue components; use composables/stores/services.
- Preserve real backend authorization and organization scoping.
- Commit frontend changes to frontend team repo and root monorepo; commit backend changes to backend team repo and root monorepo.
- After code changes, run targeted frontend/backend tests and `graphify update .`.

---

### Story 7.1: AppShell and Login 1:1 Parity

As any authenticated or unauthenticated user,
I want the application shell and login flow to match the Lovable prototype,
So that the first visible experience and persistent navigation are stakeholder-approved.

**Lovable React references:**
- `lovable/src/components/layout/AppShell.tsx`
- `lovable/src/routes/__root.tsx`
- `lovable/src/routes/login.tsx`

**Lovable screenshot references:**
- `lovable/screenshots/login.png`
- `lovable/screenshots/login-otp.png`
- `lovable/screenshots/CBY_ADMIN /dashboard.png`
- `lovable/screenshots/CBY_ADMIN /dashboard-sidebar-collapsed.png`
- `lovable/screenshots/CBY_ADMIN /notifications-dropdown.png`
- `lovable/screenshots/CBY_ADMIN /notifications-empty.png`

**Nuxt targets:**
- `frontend/app/layouts/default.vue`
- `frontend/app/layouts/auth.vue`
- `frontend/app/components/layout/AppSidebar.vue`
- `frontend/app/components/layout/AppHeader.vue`
- `frontend/app/pages/login.vue`
- `frontend/app/assets/css/main.css`
- `frontend/app/assets/css/fonts.css`

**Acceptance criteria:**
- Sidebar, header, search, notifications dropdown, profile/menu area, collapsed state, spacing, and sticky behavior match screenshots.
- Login is two-column on desktop and single-column on mobile, matching `login.png`.
- OTP step uses six individual cells and matches `login-otp.png`.
- Demo RoleSwitcher/persona picker is intentionally omitted.
- If OTP backend support is incomplete, implement the real backend endpoint and tests in the same story.

---

### Story 7.2: Dashboard 1:1 Parity by Role

As a user in any production role,
I want my dashboard to match the Lovable role-specific dashboard,
So that each role starts from the exact operational workspace stakeholders approved.

**Lovable React references:**
- `lovable/src/routes/index.tsx`
- `lovable/src/components/layout/AppShell.tsx`
- `lovable/src/lib/governance.ts`

**Lovable screenshot references:**
- `lovable/screenshots/BANK-ADMIN/dashboard.png`
- `lovable/screenshots/BANK_REVIEWER /dashboard.png`
- `lovable/screenshots/CBY_ADMIN /dashboard.png`
- `lovable/screenshots/COMMITTEE_DIRECTOR/dashboard.png`
- `lovable/screenshots/DATA_ENTRY/dashboard.png`
- `lovable/screenshots/EXECUTIVE_MEMBER/dashboard.png`
- `lovable/screenshots/SUPPORT_COMMITTEE /dashboard.png`
- `lovable/screenshots/SWIFT_OFFICER/dashboard.png`

**Nuxt targets:**
- `frontend/app/pages/dashboard.vue`
- `frontend/app/components/dashboard/*.vue`
- `frontend/app/stores/dashboard.store.ts`
- `frontend/app/composables/useDashboard.ts`

**Acceptance criteria:**
- Each role dashboard matches its screenshot for KPI count, card layout, charts, queues, quick actions, labels, empty/loading/error states, and responsive layout.
- Charts appear only where the prototype shows them.
- DATA_ENTRY sees simplified business statuses; BANK_ADMIN and CBY roles see appropriate internal workflow visibility.
- Any missing dashboard metrics must be added through real backend API responses and tests.

---

### Story 7.3: Requests List 1:1 Parity

As a user reviewing workflow queues,
I want the requests list to match the Lovable list layout for my role,
So that request scanning, filtering, and actions behave exactly as approved.

**Lovable React references:**
- `lovable/src/routes/requests.index.tsx`
- `lovable/src/components/workflow/WorkflowProgress.tsx`
- `lovable/src/components/ui/table.tsx`
- `lovable/src/components/ui/badge.tsx`

**Lovable screenshot references:**
- `lovable/screenshots/BANK-ADMIN/requests-list.png`
- `lovable/screenshots/CBY_ADMIN /requests.png`
- `lovable/screenshots/COMMITTEE_DIRECTOR/requests-list.png`
- `lovable/screenshots/EXECUTIVE_MEMBER/requests-list.png`
- `lovable/screenshots/SUPPORT_COMMITTEE /requests-list.png`
- `lovable/screenshots/SWIFT_OFFICER/requests-list.png`

**Nuxt targets:**
- `frontend/app/pages/requests/index.vue`
- `frontend/app/constants/workflow.ts`
- `frontend/app/stores/requests.store.ts`
- `frontend/app/composables/useRequests.ts`
- `frontend/app/components/ui/StatusBadge.vue`

**Acceptance criteria:**
- Table/card structure, search, filters, status badges, progress indicators, actions, pagination, and row spacing match Lovable for each role.
- Role-specific differences are captured in the story checklist.
- List data comes from `GET /api/requests` or an explicitly extended backend endpoint.

---

### Story 7.4: Request Detail 1:1 Parity

As any role with request access,
I want request detail pages to match the Lovable detail screens for my role and request status,
So that workflow state, documents, parties, voting, and actions are visually and operationally clear.

**Lovable React references:**
- `lovable/src/routes/requests.$id.tsx`
- `lovable/src/routes/customs.$id.print.tsx`
- `lovable/src/components/workflow/AuditTimeline.tsx`
- `lovable/src/components/workflow/DocumentChecklist.tsx`
- `lovable/src/components/workflow/LockedBanner.tsx`
- `lovable/src/components/workflow/VotingPanel.tsx`
- `lovable/src/components/workflow/WorkflowProgress.tsx`

**Lovable screenshot references:**
- `lovable/screenshots/BANK-ADMIN/request-view-info-tab.png`
- `lovable/screenshots/BANK-ADMIN/request-view-documents-tab.png`
- `lovable/screenshots/BANK-ADMIN/request-view-parties-tab.png`
- `lovable/screenshots/BANK-ADMIN/request-view-support-rejected.png`
- `lovable/screenshots/BANK-ADMIN/request-view-voting-stage.png`
- `lovable/screenshots/BANK_REVIEWER /request-view-actions-expanded.png`
- `lovable/screenshots/BANK_REVIEWER /request-view-internal-review.png`
- `lovable/screenshots/CBY_ADMIN /requests-view-request.png`
- `lovable/screenshots/CBY_ADMIN /requests-view-request-tab.png`
- `lovable/screenshots/CBY_ADMIN /requests-view-request-tab2.png`
- `lovable/screenshots/CBY_ADMIN /requests-view-request-view-file.png`
- `lovable/screenshots/COMMITTEE_DIRECTOR/request-view-voting-open-director.png`
- `lovable/screenshots/COMMITTEE_DIRECTOR/request-view-voting-pending-open.png`
- `lovable/screenshots/EXECUTIVE_MEMBER/request-view-voting-open-cast-vote.png`
- `lovable/screenshots/SUPPORT_COMMITTEE /request-view-claimed-actions.png`
- `lovable/screenshots/SUPPORT_COMMITTEE /request-view-pending-claim.png`
- `lovable/screenshots/SWIFT_OFFICER/request-view-pending-swift.png`

**Nuxt targets:**
- `frontend/app/pages/requests/[id]/index.vue`
- `frontend/app/pages/requests/[id]/swift.vue`
- `frontend/app/pages/requests/[id]/customs-preview.vue`
- `frontend/app/components/requests/ActionsPanel.vue`
- `frontend/app/components/requests/DocumentChecklist.vue`
- `frontend/app/components/ui/LockedBanner.vue`
- `frontend/app/components/voting/VotingPanel.vue`
- `frontend/app/components/workflow/*.vue`

**Acceptance criteria:**
- Detail layout, workflow rail, tabs, document checklist, action panel, support claim banners, locked/read-only states, voting panel, customs issue/print subflow, and file preview states match role/status screenshots.
- Any missing actor names, document data, vote roster data, or customs preview data must be provided by real backend APIs.

---

### Story 7.5: Request Wizard 1:1 Parity

As a bank user creating a financing request,
I want the request wizard to match the Lovable four-step flow,
So that submission feels guided and identical to the approved prototype.

**Lovable React references:**
- `lovable/src/routes/requests.new.tsx`
- `lovable/src/components/ui/form.tsx`
- `lovable/src/components/ui/input.tsx`
- `lovable/src/components/ui/select.tsx`

**Lovable screenshot references:**
- `lovable/screenshots/BANK-ADMIN/new-request-step1-basic-info.png`
- `lovable/screenshots/BANK-ADMIN/new-request-step2-supplier.png`
- `lovable/screenshots/BANK-ADMIN/new-request-step3-documents.png`
- `lovable/screenshots/BANK-ADMIN/new-request-step4-review-submit.png`

**Nuxt targets:**
- `frontend/app/pages/requests/new.vue`
- `frontend/app/components/wizard/RequestWizard.vue`
- `frontend/app/components/wizard/WizardStepper.vue`
- `frontend/app/components/wizard/WizardStep*.vue`
- `frontend/app/composables/useRequestWizard.ts`
- `frontend/app/schemas/wizard.schema.ts`

**Acceptance criteria:**
- Stepper, fields, validation states, upload zones, summary card, acknowledgment checkbox, sticky footer actions, and responsive behavior match the four screenshots.
- DATA_ENTRY and BANK_ADMIN differences are explicit and tested.
- File upload and draft/submit actions use real backend APIs.

---

### Story 7.6: Merchants 1:1 Parity

As a bank admin or CBY admin,
I want merchant management to match the Lovable merchant screens,
So that merchant cards, forms, and status actions are visually consistent with the prototype.

**Lovable React references:**
- `lovable/src/routes/merchants.tsx`
- `lovable/src/components/ui/dialog.tsx`
- `lovable/src/components/ui/card.tsx`

**Lovable screenshot references:**
- `lovable/screenshots/BANK-ADMIN/merchants-list-cards.png`
- `lovable/screenshots/BANK-ADMIN/merchants-list-suspended.png`
- `lovable/screenshots/BANK-ADMIN/merchants-add-modal.png`
- `lovable/screenshots/BANK-ADMIN/merchants-edit-modal.png`
- `lovable/screenshots/CBY_ADMIN /merchants.png`
- `lovable/screenshots/CBY_ADMIN /merchants-view-merchant.png`

**Nuxt targets:**
- `frontend/app/pages/merchants.vue`
- `frontend/app/components/merchants/MerchantCard.vue`
- `frontend/app/components/merchants/MerchantModal.vue`
- `frontend/app/components/merchants/SuspendConfirmDialog.vue`
- `frontend/app/composables/useMerchants.ts`

**Acceptance criteria:**
- BANK_ADMIN merchant page uses card grid parity; CBY_ADMIN merchant page follows the CBY screenshot parity.
- Add/edit/suspend modal states match screenshots.
- All merchant data and status changes use real backend APIs and bank/CBY authorization.

---

### Story 7.7: Staff and Administration 1:1 Parity

As a BANK_ADMIN or CBY_ADMIN,
I want staff, CBY staff, entity, and role administration screens to match Lovable,
So that administrative workflows have the same approved visual structure.

**Lovable React references:**
- `lovable/src/routes/bank.users.tsx`
- `lovable/src/routes/admin.cby-staff.tsx`
- `lovable/src/routes/admin.entities.tsx`
- `lovable/src/routes/admin.roles.tsx`

**Lovable screenshot references:**
- `lovable/screenshots/BANK-ADMIN/staff-list.png`
- `lovable/screenshots/BANK-ADMIN/staff-edit-modal.png`
- `lovable/screenshots/BANK-ADMIN/staff-edit-modal2.png`
- `lovable/screenshots/CBY_ADMIN /staff.png`
- `lovable/screenshots/CBY_ADMIN /staff-add-member.png`
- `lovable/screenshots/CBY_ADMIN /staff-edit-member.png`
- `lovable/screenshots/CBY_ADMIN /banks.png`
- `lovable/screenshots/CBY_ADMIN /banks-add-bank.png`
- `lovable/screenshots/CBY_ADMIN /banks-view-bank.png`
- `lovable/screenshots/CBY_ADMIN /roles.png`
- `lovable/screenshots/CBY_ADMIN /roles2-readonly-view.png`

**Nuxt targets:**
- `frontend/app/pages/staff.vue`
- `frontend/app/pages/admin/cby-staff.vue`
- `frontend/app/pages/admin/entities.vue`
- `frontend/app/pages/admin/roles.vue`
- `frontend/app/pages/users.vue`
- `frontend/app/pages/banks.vue`
- `frontend/app/components/staff/StaffModal.vue`
- `frontend/app/composables/useUsers.ts`
- `frontend/app/composables/useBanks.ts`

**Acceptance criteria:**
- BANK_ADMIN staff management and CBY_ADMIN administration pages match the relevant screenshots.
- Role definitions remain production-safe; no editable permissions matrix unless backed by real permission APIs and governance approval.
- Any missing user/entity fields or counts are added through backend APIs and tests.

---

### Story 7.8: Reports 1:1 Parity

As a reporting user,
I want reports pages to match the Lovable reports screens for my role,
So that operational analysis appears exactly as stakeholders reviewed it.

**Lovable React references:**
- `lovable/src/routes/reports.tsx`
- `lovable/src/components/ui/chart.tsx`

**Lovable screenshot references:**
- `lovable/screenshots/BANK-ADMIN/reports.png`
- `lovable/screenshots/CBY_ADMIN /reports.png`
- `lovable/screenshots/COMMITTEE_DIRECTOR/reports.png`
- `lovable/screenshots/EXECUTIVE_MEMBER/reports.png`
- `lovable/screenshots/SUPPORT_COMMITTEE /reports.png`

**Nuxt targets:**
- `frontend/app/pages/reports/index.vue`
- `frontend/app/composables/useReports.ts`
- `frontend/app/stores/reports.store.ts`
- `backend/app/Http/Controllers/Api/ReportController.php`

**Acceptance criteria:**
- KPI cards, filters, charts, tables, exports, and role-specific report variants match screenshots.
- Report numbers are computed from stable backend data, not status-only shortcuts that can be distorted by auto-chained workflow transitions.
- Any missing report dataset is implemented in the backend with tests before the UI is marked done.

---

### Story 7.9: Audit 1:1 Parity

As an audit/compliance user,
I want audit pages to match Lovable's compliance views,
So that activity, duplicate invoice, and risk views support compliance review exactly as approved.

**Lovable React references:**
- `lovable/src/routes/audit.tsx`
- `lovable/src/components/workflow/AuditTimeline.tsx`

**Lovable screenshot references:**
- `lovable/screenshots/CBY_ADMIN /audit.png`
- `lovable/screenshots/CBY_ADMIN /audit-tab2.png`
- `lovable/screenshots/CBY_ADMIN /audit-tab3.png`
- `lovable/screenshots/COMMITTEE_DIRECTOR/audit-log-list.png`

**Nuxt targets:**
- `frontend/app/pages/audit.vue`
- `frontend/app/composables/useAudit.ts`
- `frontend/app/components/workflow/AuditTimeline.vue`
- `backend/app/Http/Controllers/Api/AuditController.php`

**Acceptance criteria:**
- Audit page tabs, KPI cards, filters, table density, risk cards, duplicate invoice indicators, and empty/error states match screenshots.
- If duplicate invoice or risk indicator data has no real API, create backend endpoints and tests rather than using mock data.

---

### Story 7.10: Settings and Profile 1:1 Parity

As an authenticated user or CBY admin,
I want settings and profile screens to match Lovable while excluding demo-only controls,
So that account and system configuration screens are production-safe and visually approved.

**Lovable React references:**
- `lovable/src/routes/settings.tsx`
- `lovable/src/routes/profile.tsx`

**Lovable screenshot references:**
- `lovable/screenshots/CBY_ADMIN /settings.png`
- `lovable/screenshots/CBY_ADMIN /settings2.png`
- `lovable/screenshots/CBY_ADMIN /settings3.png`
- `lovable/screenshots/CBY_ADMIN /settings4.png`
- `lovable/screenshots/CBY_ADMIN /settings5.png`
- `lovable/screenshots/CBY_ADMIN /settings6.png`
- `lovable/screenshots/CBY_ADMIN /profile.png`

**Nuxt targets:**
- `frontend/app/pages/settings.vue`
- `frontend/app/pages/admin/settings.vue`
- `frontend/app/pages/profile.vue`
- `frontend/app/composables/useSettings.ts`
- `frontend/app/composables/useAdminSettings.ts`
- `frontend/app/composables/useProfile.ts`

**Acceptance criteria:**
- Profile layout, avatar card, stats, recent activity, settings tabs, form controls, toggles, and responsive behavior match screenshots.
- Demo-data reset tooling is intentionally omitted, even if visible in the screenshots.
- Any setting displayed in production must be backed by a real API, persisted value, authorization rule, and test.

---

## Epic 8: Workflow & Production Completion

**Purpose:** Close the final workflow-completeness and production-readiness gaps identified in `docs/09-user-stories-gap-analysis.md`. This epic is NOT a new architecture phase; all prior epics 1–7 are canonical and complete. Epic 8 only organizes the residual in-scope gaps into executable stories.

**Created:** 2026-05-21
**Source authorities:**
1. `docs/09-user-stories-gap-analysis.md` — defines S1–S10 scope, dependencies, and acceptance.
2. `docs/08-prototype-gap-analysis.md` — Lovable visual parity reference (referenced, not reopened).
3. `docs/01-workflow-and-business-rules.md` and `docs/03-database-and-models.md` remain final authority for workflow rules, roles, statuses, and audit behavior.
4. `AGENTS.md` canonical enums remain the source of truth. The three new statuses (`BANK_RETURNED`, `SUPPORT_RETURNED`, `BANK_REJECTED`) extend — never replace — the existing 18-status enum.

**Hard scope boundaries (non-negotiable):**
- Treat all stories in Epics 1–7 as canonical and completed. Do NOT reopen unless explicitly listed below.
- No architecture rewrites. No new MVP scope expansion. No deferred or out-of-scope items from `docs/09` §5.
- No Lovable parity rework except what is already tracked in `docs/08`.
- All commits must remain signed; commit to both team repo and root monorepo per `AGENTS.md`.
- All workflow status changes continue to go through `WorkflowService::transition()`.

**Definition of done for Epic 8:**
- Three new canonical statuses are live and routed correctly through `WorkflowService::transition()` and `TransitionMap`.
- All 9 USER-STORIES.md gaps (G1–G5 + 4 polish items) are closed per `docs/09` §6.
- All existing tests (~1,447 frontend + ~564 backend) still green; new stories add their own targeted tests.
- `sprint-status.yaml` reflects epic-8 progress; no prior epic statuses are mutated.

**Common technical requirements for all Epic 8 stories:**
- Run SocratiCode before modifying existing files: `codebase_symbol` / `codebase_search`, then `codebase_impact` for touched services/components.
- Keep `lovable/` read-only.
- Keep business logic out of Vue components; use composables/stores/services.
- Preserve real backend authorization and organization scoping.
- After code changes, run targeted frontend/backend tests and `graphify update .`.

---

### Story 8.1: BANK_RETURNED Status & Return-to-Intake Transition

As a `BANK_REVIEWER`,
I want to return a `BANK_REVIEW` request to the data-entry queue with a mandatory comment (instead of being forced to reject or approve),
So that intake can fix surface-level issues without the request being treated as rejected.

**Source authority:**
- `docs/09-user-stories-gap-analysis.md` §1.3 (new status), §2.2 Story 4.1, §6 S1
- USER-STORIES.md §4.1 (return-with-comment path), Workflow B
- Gap G1

**Nuxt + Laravel targets:**
- `backend/app/Enums/RequestStatus.php` — add `BANK_RETURNED` case + label + editable check
- `backend/app/Services/Workflow/TransitionMap.php` — add `bank_return_to_intake` transition
- `backend/app/Services/Workflow/WorkflowService.php` — emit `STATUS_TRANSITION` audit with notes
- `backend/app/Http/Controllers/Api/WorkflowController.php` — add `bankReturn()` action
- `backend/routes/api.php` — `POST /api/workflow/{importRequest}/bank-return`
- `backend/app/Notifications/RequestReturnedNotification.php` — already exists; verify payload includes `from_role: BANK_REVIEWER`
- `backend/database/migrations/2026_05_21_xxxxxx_add_bank_returned_status.php` — index-only DDL (no enum table change needed; status is a string column)
- `frontend/app/types/enums.ts` — add `BANK_RETURNED`
- `frontend/app/constants/workflow.ts` — extend STATUS_PROGRESS / STATUS_LABEL / ROLE_BUCKETS
- `frontend/app/components/requests/ActionsPanel.vue` — add "إعادة للمدخل" action with comment textarea
- `frontend/app/components/ui/CorrectionBanner.vue` — variant: returned-from-bank-review
- `frontend/app/pages/requests/[id]/edit.vue` — allow edit when status === `BANK_RETURNED`

**Acceptance criteria:**
- New canonical status `BANK_RETURNED` exists and `isEditable()` returns true for it.
- `POST /api/workflow/{id}/bank-return { comment: string }` transitions a `BANK_REVIEW` request to `BANK_RETURNED`; 422 if comment is empty/whitespace.
- Transition is rejected (403 `WORKFLOW_FORBIDDEN_ROLE`) for non-BANK_REVIEWER actors.
- Self-review SOD: same `DATA_ENTRY` user who submitted cannot also act as reviewer (existing guard reused).
- `request_stage_history` and `audit_logs` capture comment as `notes`.
- Intake `request.update` continues to work on `BANK_RETURNED` like on `DRAFT_REJECTED_INTERNAL`.
- Intake `submit` transition accepts `BANK_RETURNED` as a valid `from` state.
- Frontend banner reads "إعادة من المراجع — يرجى التعديل وإعادة الإرسال" and shows the reviewer's comment.
- Notification `RequestReturnedNotification` is dispatched to bank intake users in the same bank.

**Out of scope:** support-return-to-intake (covered by Story 8.2), terminal bank rejection (covered by Story 8.3).

---

### Story 8.2: SUPPORT_RETURNED Status & Direct Return-from-Support Transition

As a `SUPPORT_COMMITTEE` member,
I want to return a `SUPPORT_REVIEW_IN_PROGRESS` request directly to data entry with a mandatory comment (without going through rejection-then-bank-return),
So that intake gets a clear signal that the support committee flagged an issue distinct from internal bank rejection.

**Source authority:**
- `docs/09-user-stories-gap-analysis.md` §1.3 (new status), §2.5 Story 7.4, §6 S2
- USER-STORIES.md §7.4, Workflow C
- Gap G2

**Nuxt + Laravel targets:**
- `backend/app/Enums/RequestStatus.php` — add `SUPPORT_RETURNED` case + label + editable check
- `backend/app/Services/Workflow/TransitionMap.php` — add `support_return_to_intake` transition; release the support claim atomically
- `backend/app/Services/Workflow/WorkflowService.php` — release claim + notify
- `backend/app/Http/Controllers/Api/WorkflowController.php` — add `supportReturn()` action
- `backend/routes/api.php` — `POST /api/workflow/{importRequest}/support-return`
- `frontend/app/types/enums.ts` — add `SUPPORT_RETURNED`
- `frontend/app/constants/workflow.ts` — STATUS_PROGRESS / STATUS_LABEL / ROLE_BUCKETS / business-status mapping
- `frontend/app/components/requests/ActionsPanel.vue` — support-side "إعادة للمدخل" action with comment
- `frontend/app/components/ui/CorrectionBanner.vue` — variant: returned-from-support
- `frontend/app/pages/requests/[id]/edit.vue` — allow edit when status === `SUPPORT_RETURNED`

**Acceptance criteria:**
- New canonical status `SUPPORT_RETURNED` exists and `isEditable()` returns true for it.
- `POST /api/workflow/{id}/support-return { comment: string }` transitions a `SUPPORT_REVIEW_IN_PROGRESS` request to `SUPPORT_RETURNED`; 422 on empty comment.
- The support claim Redis key is released atomically as part of the transition.
- Only the `SUPPORT_COMMITTEE` user who currently holds the claim may invoke this action (403 otherwise).
- Intake banner reads "إعادة من لجنة المساندة — يرجى التعديل وإعادة الإرسال" and shows the support member's comment.
- After intake edits and submits, request goes back through `BANK_REVIEW` first (preserving the SOD invariant — the bank reviewer sees a re-submitted-after-support-return flag in the UI).
- Audit logs capture `from_status: SUPPORT_REVIEW_IN_PROGRESS`, `to_status: SUPPORT_RETURNED`, comment in `notes`.
- Existing `bank_return_after_support_reject` path is preserved but documented as the legacy path; new flow is preferred.

**Out of scope:** removing legacy `bank_return_after_support_reject` (deferred until one release window passes).

**Depends on:** S1 (Story 8.1 establishes the return-to-intake banner pattern; S2 reuses it).

---

### Story 8.3: Terminal BANK_REJECTED Status

As a `BANK_REVIEWER`,
I want a separate terminal `BANK_REJECTED` status (distinct from the recoverable `DRAFT_REJECTED_INTERNAL`),
So that the system correctly models rejection-without-resubmission paths required by USER-STORIES Workflow D.

**Source authority:**
- `docs/09-user-stories-gap-analysis.md` §1.3, §2.2 Story 4.1, §6 S3
- USER-STORIES.md §4.1 (reject path) and §11 Workflow D
- Behavioral mismatch flagged in §1.2

**Nuxt + Laravel targets:**
- `backend/app/Enums/RequestStatus.php` — add `BANK_REJECTED`; include in `isTerminal()`; NOT in `isEditable()`
- `backend/app/Services/Workflow/TransitionMap.php` — add new `bank_reject_terminal` transition `BANK_REVIEW → BANK_REJECTED`
- `backend/app/Services/Workflow/WorkflowService.php` — keep existing `bank_reject` (recoverable) for one release; new `bank_reject_terminal` is the preferred action
- `backend/app/Http/Controllers/Api/WorkflowController.php` — add `bankRejectTerminal()` action; existing `bankReject()` continues to function
- `backend/routes/api.php` — `POST /api/workflow/{importRequest}/bank-reject-terminal`
- `backend/app/Policies/ImportRequestPolicy.php` — `update`/`delete` return false for `BANK_REJECTED`
- `frontend/app/types/enums.ts` — add `BANK_REJECTED`
- `frontend/app/constants/workflow.ts` — terminal mapping + STATUS_LABEL + STATUS_PROGRESS
- `frontend/app/components/requests/ActionsPanel.vue` — split UI: "إعادة للمدخل" (S1) vs "رفض نهائي" (S3) with destructive-confirm dialog
- `frontend/app/components/ui/LockedBanner.vue` — variant for terminal bank rejection (S5 "نسخ وإعادة إرسال" appears here)

**Acceptance criteria:**
- `BANK_REJECTED` is included in `RequestStatus::isTerminal()`.
- `POST /api/workflow/{id}/bank-reject-terminal { comment: string }` transitions `BANK_REVIEW → BANK_REJECTED`; 422 on empty comment; 403 if non-reviewer.
- Mutations on `BANK_REJECTED` return HTTP 403 with error_code `WORKFLOW_IMMUTABLE_STATE`.
- The legacy `bank_reject` route remains operational and continues to land in `DRAFT_REJECTED_INTERNAL` (recoverable) — UI does not invoke it anymore.
- A migration plan note is added to `docs/01-workflow-and-business-rules.md` documenting that `DRAFT_REJECTED_INTERNAL` will be deprecated after one release.
- Frontend destructive-confirm dialog matches the existing pattern used in `support_reject`.
- Notification `RequestRejectedNotification` is dispatched with `terminal: true` payload key.

**Out of scope:** Deleting `bank_reject` legacy route. Deleting `DRAFT_REJECTED_INTERNAL` status.

**Depends on:** S1 (Story 8.1 establishes the split UI for "return" vs other reviewer actions).

---

### Story 8.4: Claim Release Notification + Audit Visibility

As a `BANK_ADMIN` or CBY support committee lead,
I want to be notified when a support member releases a claim (manually or via TTL expiry),
So that long-running claim oscillation is visible and stale-claim patterns can be investigated.

**Source authority:**
- `docs/09-user-stories-gap-analysis.md` §2.5 Story 7.2, §4.1, §5 item 8, §6 S4
- USER-STORIES.md §15.8
- Gap G3

**Nuxt + Laravel targets:**
- `backend/app/Notifications/ClaimReleasedNotification.php` — new notification class
- `backend/app/Services/Workflow/WorkflowService.php` — dispatch on manual release path
- `backend/app/Console/Commands/ExpireClaimsCommand.php` — dispatch on TTL expiry path
- `backend/app/Enums/AuditAction.php` — add `CLAIM_RELEASED` case + label
- `backend/app/Services/Audit/AuditService.php` — log on both release paths
- `frontend/app/pages/notifications.vue` — already paginated; render `claim_released` icon variant
- `frontend/app/composables/useNotifications.ts` — type definition

**Acceptance criteria:**
- `DELETE /api/workflow/{id}/claim-support-review` emits a `ClaimReleasedNotification` to all `SUPPORT_COMMITTEE` leads (defined as a configurable set; for now: all `CBY_ADMIN` users).
- `ExpireClaimsCommand` (cron) emits the same notification on TTL expiry with `reason: ttl_expired`.
- `audit_logs` entry: `action: CLAIM_RELEASED`, `notes: { reason: manual|ttl_expired }`, `user_id` is the releaser (NULL for TTL).
- Notification preferences allow toggling `claim_released` off; defaults ON for `CBY_ADMIN`.
- Notification payload includes `request_id`, `reference_number`, `released_by_name` (nullable), `reason`.
- Frontend notification center shows a yellow/warning icon for `claim_released`.

**Out of scope:** SLA timer on claim-release frequency, supervisor-required reason after N releases (deferred).

---

### Story 8.5: Copy & Resubmit on Terminal Rejections

As a `DATA_ENTRY` or `BANK_ADMIN` user,
I want to clone a terminally-rejected request into a new draft (pre-filled from the original, sans documents),
So that I do not have to re-key all wizard fields from scratch when responding to a permanent rejection.

**Source authority:**
- `docs/09-user-stories-gap-analysis.md` §5 item 10, §6 S5
- USER-STORIES.md §15.10
- Gap G4

**Nuxt + Laravel targets:**
- `backend/app/Http/Controllers/Api/ImportRequestController.php` — add `clone()` action
- `backend/routes/api.php` — `POST /api/requests/{importRequest}/clone`
- `backend/app/Policies/ImportRequestPolicy.php` — `clone` ability: actor must be `DATA_ENTRY` or `BANK_ADMIN` in same bank as source; source must be terminal-rejected
- `backend/app/Services/Workflow/WorkflowService.php` — clone helper that creates a new `DRAFT` request with copied wizard fields and `revision_count` linked
- `frontend/app/composables/useRequests.ts` — `cloneRequest(id)` returning new request id
- `frontend/app/pages/requests/[id]/index.vue` — render "نسخ وإعادة إرسال" button on terminal-rejected statuses (`BANK_REJECTED`, `SUPPORT_REJECTED`, `EXECUTIVE_REJECTED`)
- `frontend/app/pages/requests/new.vue` — accept `?clone_of=<id>` query param to pre-fill the wizard

**Acceptance criteria:**
- `POST /api/requests/{id}/clone` returns the new draft request id; 403 if source is not terminal-rejected; 403 if actor not in source's bank or not in allowed role.
- Cloned request has: copied wizard fields (currency, amount, supplier_name, goods_description, port_of_entry, goods_type, payment_terms, due_date, invoice_number, invoice_date, origin_country, arrival_port, shipping_port, customs_office, bl_number, merchant_id, notes), status `DRAFT`, new `reference_number`, `revision_count = source.revision_count + 1`.
- Documents are NOT copied (re-upload required for audit integrity).
- A `REQUEST_CREATED` audit entry is logged with `notes: { cloned_from: <source_id> }`.
- Frontend button is visible only when current status is terminal-rejected AND current user has appropriate role.
- After clone, user is redirected to `/requests/<new_id>/edit`.

**Out of scope:** Bulk clone, partial-field clone, document carry-over.

---

### Story 8.6: Cross-Bank Duplicate Invoice Detection

As a `BANK_REVIEWER`, `SUPPORT_COMMITTEE` member, or `CBY_ADMIN`,
I want the system to detect duplicate `invoice_number` values across all banks (not only within the actor's own bank),
So that the same supplier invoice cannot be financed twice through different commercial banks.

**Source authority:**
- `docs/09-user-stories-gap-analysis.md` §3 Workflow F, §5 item 13, §6 S6
- USER-STORIES.md §15.13 and §15.14
- Gap G5

**Nuxt + Laravel targets:**
- `backend/database/migrations/2026_05_21_xxxxxx_add_index_invoice_number_to_import_requests.php` — composite index `(invoice_number, deleted_at)` for fast scans
- `backend/app/Services/DuplicateDetectionService.php` — new service: `findDuplicatesForInvoice(string $invoiceNumber, int $excludeRequestId = null)` returns peer rows across all banks (no scope filter)
- `backend/app/Http/Controllers/Api/ImportRequestController.php` — `show()` includes `duplicate_warnings` array in response when actor has audit/reviewer permission
- `backend/app/Http/Controllers/Api/AuditController.php` — `duplicates()` query updated to use the new service for cross-bank scan
- `backend/app/Services/Settings/SystemSettings.php` — new key `duplicate_invoice_policy` with values `warn` (default) or `block`
- `backend/app/Http/Requests/StoreImportRequest.php` — when policy=`block`, return 422 on duplicate detection
- `frontend/app/composables/useRequests.ts` — surface `duplicate_warnings` in the typed model
- `frontend/app/pages/requests/[id]/index.vue` — duplicate-warning badge + side-by-side compare widget showing the other row(s)
- `frontend/app/pages/audit.vue` — duplicates tab queries the cross-bank scan endpoint
- `frontend/app/pages/admin/settings.vue` — surface the `duplicate_invoice_policy` toggle for `CBY_ADMIN`

**Acceptance criteria:**
- `ImportRequest::show()` returns a `duplicate_warnings` array (possibly empty) for reviewer/auditor roles; bank-scoped roles see only the count + bank names of matches, not full data of other banks' rows.
- `CBY_ADMIN` and `SUPPORT_COMMITTEE` see full peer-row payload (reference number, bank name, amount, currency, created_at).
- `audit.vue` duplicates tab lists cross-bank duplicates grouped by `invoice_number`.
- `duplicate_invoice_policy = block` causes wizard submission to fail with 422 + arabic error message; `warn` (default) creates the request and surfaces the warning in detail view.
- A "نسخ وإعادة إرسال" overlap with S5 is NOT triggered by duplicate detection — these are independent.
- Audit log entry `REQUEST_CREATED` includes `notes.duplicate_count` when > 0.

**Out of scope:** Side-by-side amount/currency diff highlighting (covered as part of the same UI widget but acceptance is shipped if both rows render — full diff highlighting is a fast-follow).

---

### Story 8.7: Audit Metadata Polish

As a `CBY_ADMIN`,
I want audit entries to record real client IP (proxy-aware), browser user agent, and before/after values on role/permission changes,
So that compliance investigations can reconstruct who did what from where without guesswork.

**Source authority:**
- `docs/09-user-stories-gap-analysis.md` §4.2, §5 items 22 and 25, §6 S7
- USER-STORIES.md §13.1 and §15.22 and §15.25

**Nuxt + Laravel targets:**
- `backend/bootstrap/app.php` — configure `TrustProxies` with proper header set
- `backend/database/migrations/2026_05_21_xxxxxx_add_user_agent_to_audit_logs.php` — add nullable `user_agent` column
- `backend/app/Services/Audit/AuditService.php` — populate `ip`, `user_agent` from request; on role/permission change include `before` and `after` values in `notes` JSON
- `backend/app/Http/Controllers/Api/UserController.php::update()` — capture old role before update; pass to AuditService
- `backend/app/Http/Resources/AuditLogResource.php` — expose `user_agent` and `metadata` in the audit feed
- `frontend/app/pages/audit.vue` — render UA + before/after metadata in the row expansion

**Acceptance criteria:**
- All new audit entries (post-deployment) carry non-null `ip` resolved through trusted proxies and non-null `user_agent`.
- `UserController::update()` logs `USER_UPDATED` with `notes: { before: { role, is_active, ... }, after: { ... } }` when fields change.
- `audit.vue` row expansion renders the UA string (truncated with full on hover) and a "before/after" diff for administrative changes.
- Existing audit rows (pre-deployment) continue to render without errors (nullable column).
- No existing test breaks; new tests assert UA capture + before/after payload.

**Out of scope:** Geolocation lookup from IP, signed audit entries, audit-log immutability proof (separate concern).

---

### Story 8.8: Request Detail Print Page

As any user with read access to a request,
I want a print-friendly view of the request detail,
So that I can produce a paper trail for offline review or signature collection without screenshots.

**Source authority:**
- `docs/09-user-stories-gap-analysis.md` §5 item 18, §6 S8
- USER-STORIES.md §15.18
- Pattern reference: existing `frontend/app/pages/customs/[id]/print.vue`

**Nuxt + Laravel targets:**
- `frontend/app/pages/requests/[id]/print.vue` — new print-optimized page; mirrors styling of `customs/[id]/print.vue`
- `frontend/app/components/requests/RequestPrintable.vue` — extracted printable block (mirrors `customs/PrintablePermit.vue` pattern)
- `frontend/app/pages/requests/[id]/index.vue` — add "طباعة" button in the page header
- `backend/app/Http/Controllers/Api/ImportRequestController.php::show()` — already returns required fields; no API change needed
- `backend/app/Policies/ImportRequestPolicy.php` — `view` already gates access; reused for print

**Acceptance criteria:**
- `/requests/{id}/print` is reachable to any user with `view` permission on the request.
- The page renders: reference number, status badge, requester bank, requester user, wizard fields, document list (names + dates, no inline PDF), audit timeline (compact), workflow timeline (compact).
- `@media print` styles hide nav/header/sidebar; A4 portrait page sized; RTL layout preserved.
- A "العودة" link (hidden in print) returns to the detail page.
- No new API endpoint; data comes from existing `GET /api/requests/{id}` + `GET /api/requests/{id}/history`.
- No editing controls visible on the print page.

**Out of scope:** PDF generation server-side (browser-driven print is sufficient).

---

### Story 8.9: Advanced Request List Filters

As a `BANK_REVIEWER`, `SUPPORT_COMMITTEE` member, or `CBY_ADMIN`,
I want to filter the requests list by date range, amount range, and (where applicable) assigned reviewer,
So that I can narrow down high-volume queues during peak periods.

**Source authority:**
- `docs/09-user-stories-gap-analysis.md` §5 item 20, §6 S9
- USER-STORIES.md §15.20

**Nuxt + Laravel targets:**
- `backend/app/Http/Controllers/Api/ImportRequestController.php::index()` — accept `created_from`, `created_to`, `amount_min`, `amount_max`, `assigned_reviewer_id` query params; validate; apply to Eloquent query
- `backend/tests/Feature/RequestListFilterTest.php` — new tests
- `frontend/app/composables/useRequests.ts` — extend list query payload
- `frontend/app/pages/requests/index.vue` — add filter UI: date range pickers, two numeric inputs, reviewer select (visible only for CBY/admin scope); preserve existing ref/importer/invoice/bank/currency filters
- `frontend/app/components/requests/RequestFilters.vue` — extracted from `requests/index.vue` if it grows too large (optional refactor)

**Acceptance criteria:**
- `GET /api/requests?created_from=YYYY-MM-DD&created_to=YYYY-MM-DD&amount_min=...&amount_max=...&assigned_reviewer_id=...` returns filtered results; bad input returns 422.
- Bank-scoped roles ignore the `assigned_reviewer_id` param if the reviewer is outside their bank scope (silent).
- Filters compose with existing filters and existing pagination; no N+1 regression (verified via test).
- Empty-range inputs (`amount_min` without `amount_max`) are accepted and treated as one-sided ranges.
- `assigned_reviewer_id` only filters by the actor that last performed a review action (`reviewed_by`).
- Frontend persists last-applied filters in URL query params for shareable links.

**Out of scope:** Risk-level filter (no risk model exists yet), full-text search across all fields (covered by global search story 5.4).

---

### Story 8.10: Inactivity-Lock UI Banner

As any authenticated user,
I want the UI to warn me before my session expires due to inactivity, and to safely log me out when it does,
So that compliance-grade session-timeout behavior is visible and predictable.

**Source authority:**
- `docs/09-user-stories-gap-analysis.md` §5 item 5, §6 S10
- USER-STORIES.md §15.5

**Nuxt + Laravel targets:**
- `frontend/app/composables/useInactivityTimer.ts` — new composable: tracks last input timestamp via `mousemove`/`keydown`/`pointerdown`/`scroll` debounced events; computes time-until-expiry from a configured threshold
- `frontend/app/components/layout/InactivityBanner.vue` — sticky top banner shown when expiry < 2 minutes; "أنت على وشك الخروج بسبب عدم النشاط — انقر للبقاء"
- `frontend/app/layouts/default.vue` — mount `InactivityBanner` + initialize composable
- `frontend/app/stores/auth.store.ts` — `extendSession()` calls `GET /api/auth/me` to renew the Sanctum cookie; `forceLogout()` calls `POST /api/auth/logout` and redirects to `/login?reason=inactivity`
- `frontend/app/pages/login.vue` — render an info banner if `?reason=inactivity` is present
- `backend/config/sanctum.php` — session lifetime confirmed (no backend code change required if already configured)

**Acceptance criteria:**
- Inactivity threshold is 15 minutes (configurable via a Nuxt runtime config key); warning fires at T-2 minutes.
- Banner is non-blocking and dismissible by any user interaction (clicking the banner OR continuing to use the app).
- At T-0, the user is logged out via `forceLogout()`; redirected to `/login?reason=inactivity`.
- Login page surfaces a non-error info message: "تم تسجيل خروجك بسبب عدم النشاط".
- Composable correctly cleans up event listeners on layout unmount (no memory leaks; verified by Vitest).
- Works in RTL; banner text is right-aligned; close icon is on the left.
- Existing tests are unaffected; new tests cover the composable and banner separately.

**Out of scope:** Multi-tab sync of activity (a refresh-tab approach can be a fast-follow), keystroke-level idle detection beyond the listed events.

---

## Epic 9: Lovable Parity Enforcement & Remediation

**Purpose:** Epic 7 declared 1:1 Lovable parity as a goal across 10 stories and all 10 shipped, yet visible drift remains. The root cause is structural: the screenshot-pair acceptance criterion in Epic 7 was a doc-level rule, not a workflow-level gate. Stories could be marked complete without a committed before/after screenshot pair. Epic 9 promotes parity from "intended" to "enforced" — it (a) wires a hard pre-completion gate into the BMad dev-story workflow so no UI story can ship without committed parity evidence, (b) re-audits every Epic 7 surface against the new gate, (c) remediates confirmed drift in the production Nuxt UI, and (d) locks the final state behind visual regression baselines so future stories cannot silently degrade parity.

**Decision date:** 2026-05-22

**Relationship to Epic 7:** Epic 7's scope, source authorities, and story-level acceptance criteria remain canonical. Epic 9 does not re-do Epic 7's work — it enforces what Epic 7 declared, and surgically remediates what slipped through. Epic 7 stories that pass the Story 9.2 re-audit are not reopened.

**Source authorities (unchanged from Epic 7):**
1. `docs/01-workflow-and-business-rules.md`, `docs/03-database-and-models.md` — final authority for workflow, roles, statuses, security, audit.
2. `lovable/screenshots/` — final visual authority for UI parity. If `DESIGN.md` conflicts with a screenshot, update `DESIGN.md` to match the screenshot.
3. `lovable/src/` — React source reference for layout and component intent. Adapt intent only; do not copy React/TanStack code.
4. `DESIGN.md` — tokenized expression of the screenshots; kept current as parity evidence reveals deltas.
5. `frontend/app/` must remain Nuxt 4, Vue, TypeScript, Tailwind CSS v4, Pinia, shadcn-vue.

**Definition of "enforced parity":**
- Every user-visible Vue page or component under `frontend/app/**` has a corresponding `_bmad-output/parity-evidence/<area>/<page>/{lovable.png, current.png, side-by-side.png}` triplet committed to the repo.
- Lovable layout is mirrored for RTL (sidebar-on-right, icon flips, chevron directions) while preserving Arabic copy. "1:1" means visually equivalent under RTL mirror — not character-identical to the LTR English source.
- Any story that touches `frontend/app/**/*.vue` or `frontend/app/assets/css/**` cannot be marked complete by the dev agent without producing/updating the evidence triplet for every affected page. The BMad dev-story workflow enforces this; CI fails otherwise.
- Routes may differ between lovable and the Nuxt app (e.g., lovable `/login`, Nuxt `/signin`). Route differences are explicitly allowed; only rendered UI must match.

**Hard scope boundaries:**
- No new feature work. No backend logic changes except to expose data a lovable screen requires that no current API provides (and only as a documented sub-task of the parity story that needs it).
- No re-doing Epic 7 stories that pass the Story 9.2 re-audit. Pass = ship as-is.
- Demo-only features remain excluded even if visible in the prototype (role switcher, demo login shortcuts, demo reset tools, mock-state editing).
- `lovable/` stays read-only.
- Workflow, roles, statuses, security, and audit behavior must not regress. Story 9.3 and 9.4 are UI-only.

**Common technical requirements for all Epic 9 stories:**
- Run SocratiCode before modifying existing files: `codebase_symbol` / `codebase_search`, then `codebase_impact` for touched components.
- Use dev-browser to capture both lovable and current-app screenshots at matching viewports (desktop 1440×900 and mobile 390×844).
- Commit frontend changes to frontend team repo and root monorepo; commit any backend changes to backend team repo and root monorepo.
- After code changes, run targeted Vitest + Playwright tests and `graphify update .`.

---

### Story 9.1: Parity Workflow Gate, Doctrine Update & Initial Tooling

As a dev agent (Claude or other) implementing any UI-touching story,
I want the BMad dev-story workflow to refuse to mark UI stories complete without a committed parity-evidence triplet,
So that "1:1 with lovable" stops being an aspirational doc rule and becomes a structural pre-completion gate.

**Source authority:**
- Epic 7 post-completion gap (drift persists despite shipped stories) — captured in this epic's purpose.
- AGENTS.md `lovable/` line — currently says "reference prototype, do not copy" and must be rewritten.

**Targets:**

*BMad workflow gate:*
- `_bmad/custom/bmad-dev-story.toml` — add `persistent_facts` and `activation_steps_append` entries enforcing the parity-evidence rule. New persistent fact: "Any story touching `frontend/app/**/*.vue` or `frontend/app/assets/css/**` is INCOMPLETE until a `_bmad-output/parity-evidence/<area>/<page>/` directory exists with `lovable.png`, `current.png`, and `side-by-side.png` committed in the same change. Missing or stale evidence is a HALT condition before marking the story complete."
- New activation step: "If the active story touches frontend UI files, list its target pages and verify (or queue creation of) parity-evidence triplets for each before claiming completion."

*Doctrine docs:*
- `AGENTS.md` — replace the `lovable/` rule: "lovable/ is the **visual source of truth** for all UI work. Clone it 1:1; translate React idioms to Vue; mirror for RTL; preserve Arabic copy. lovable/ itself remains read-only."
- `CLAUDE.md` (root) — mirror the same line update.
- `docs/04-frontend-guide.md` — add a new top-level section "Visual Parity Workflow" pointing all UI work to the parity workflow doc and the BMad gate.
- `DESIGN.md` — add a "Source of truth" note: when DESIGN.md tokens conflict with `lovable/screenshots/` rendered values, update DESIGN.md to match the screenshot.
- New: `docs/ui-parity/clone-page-workflow.md` — codify the per-page port procedure (open lovable file → both apps in dev-browser at matched viewport → screenshot lovable → screenshot current → produce side-by-side composite → port markup → re-wire composables → re-screenshot → commit triplet → user sign-off).

*CI/CD gate (optional but recommended):*
- A new `frontend/scripts/check-parity-evidence.ts` script that, given a list of changed Vue files, asserts each has a corresponding evidence triplet under `_bmad-output/parity-evidence/`. Wired as a pre-push hook or CI step. Failures block merge.

*Skill alias:*
- `.claude/skills/clone-page/SKILL.md` — short skill alias `/clone-page <lovable-file>` that runs the per-page port procedure documented above.

**Acceptance criteria:**
- BMad dev-story workflow refuses to claim completion on a UI-touching story without the evidence triplet present.
- AGENTS.md, CLAUDE.md, frontend-guide, DESIGN.md, and the new clone-page-workflow doc all consistently declare lovable as visual source of truth.
- A dry-run on a sample UI story (e.g., re-touching `frontend/app/pages/login.vue`) demonstrates the gate firing.
- `/clone-page` skill is callable and produces the expected per-page port artifacts.
- Existing dev-story workflow steps (SocratiCode index checks, persistent facts) are preserved; the new facts are additive.

**Out of scope:** Running the gate retroactively against shipped Epic 7 stories — that is Story 9.2's job.

---

### Story 9.2: Epic 7 Re-Audit & Parity Verdict Matrix

As a planner verifying Epic 7's actual delivered state,
I want a fresh, evidence-backed re-audit of every Epic 7 page against its lovable screenshot,
So that the remediation backlog in Stories 9.3 and 9.4 is grounded in confirmed gaps, not speculation.

**Source authority:**
- Epic 7 (Stories 7.1–7.10) — defines every page in scope.
- `lovable/screenshots/` — visual baseline for each page.
- The parity gate from Story 9.1 — defines the evidence artifact format.

**Targets:**
- `_bmad-output/parity-evidence/<area>/<page>/{lovable.png, current.png, side-by-side.png}` — one triplet per page that Epic 7 declared in scope.
- `docs/ui-parity/parity-matrix.md` — single matrix with one row per page:

| area | lovable screenshot path | current Vue path | viewport | verdict | gap summary | remediation story |
|---|---|---|---|---|---|---|

Verdict values: `PASS` (no drift visible), `MINOR_DRIFT` (spacing/color/density), `MAJOR_DRIFT` (layout/structure), `MISSING` (lovable page not rendered at all in current app), `SKIP` (lovable-only page not in workflow scope).

**Coverage requirement:**
- Every screenshot path referenced in Story 7.1 through 7.10 must appear as a row.
- Both desktop (1440×900) and mobile (390×844) screenshots are captured per page.
- Demo-only features remain excluded; rows for those are tagged `SKIP — demo-only` with a one-line reason.

**Acceptance criteria:**
- Matrix is complete (every Epic 7 in-scope screenshot represented).
- Every row's evidence triplet is committed under `_bmad-output/parity-evidence/`.
- Every non-PASS row carries a one-paragraph gap summary and a target remediation story (9.3 or 9.4).
- User signs off on the matrix before Stories 9.3/9.4 begin.

**Out of scope:** Code changes. This story produces evidence and a remediation backlog only.

---

### Story 9.3: Remediate Workflow Surface Drift (Auth, Dashboards, Requests)

As any user inside the daily workflow,
I want the auth screens, role dashboards, and request screens to match lovable 1:1 under RTL mirror,
So that the surface users touch every day is visually identical to the stakeholder-approved prototype.

**Source authority:**
- Story 9.2 verdict matrix rows tagged `MINOR_DRIFT`, `MAJOR_DRIFT`, or `MISSING` and assigned to this story.
- Epic 7 stories 7.1, 7.2, 7.3, 7.4, 7.5 — original source-authority lists remain valid.

**Targets (only pages with non-PASS verdicts; final list from 9.2):**

*Auth:*
- `frontend/app/pages/login.vue`, OTP step, forgot-password, reset-password
- `frontend/app/layouts/auth.vue`

*Dashboards (per role):*
- `frontend/app/pages/dashboard.vue` and `frontend/app/components/dashboard/*.vue` for any role with drift

*Requests:*
- `frontend/app/pages/requests/index.vue`
- `frontend/app/pages/requests/[id]/index.vue`
- `frontend/app/pages/requests/new.vue`
- `frontend/app/pages/requests/[id]/edit.vue`
- `frontend/app/pages/requests/[id]/print.vue`
- `frontend/app/pages/requests/[id]/swift.vue`
- `frontend/app/components/requests/*.vue`, `frontend/app/components/wizard/*.vue`, `frontend/app/components/workflow/*.vue`, `frontend/app/components/voting/VotingPanel.vue`

**Acceptance criteria:**
- Every non-PASS row from 9.2 in this story's scope flips to PASS in a re-screenshot pass.
- Updated evidence triplets committed for every touched page.
- DESIGN.md tokens updated where 9.2 surfaced a token-vs-screenshot delta.
- Vitest + Playwright suites green.
- No regression in workflow, security, or audit behavior (verified by re-running existing test suites).

**Posture per page (carried from 9.2):** Each page is tagged `patch` (incremental class/spacing adjustments) or `teardown` (delete current markup, port lovable's markup wholesale, re-wire existing composables/stores). 9.3 honors the per-page posture.

**Out of scope:** Admin, settings/profile, reports, audit, customs, merchants, notifications (Story 9.4). Visual regression baselines (Story 9.5).

---

### Story 9.4: Remediate Management Surface Drift (Admin, Settings/Profile, Reports, Misc)

As an admin, auditor, or reporting user,
I want the management and supporting screens to match lovable 1:1 under RTL mirror,
So that the full app — not just the hot path — reaches the parity bar Epic 7 set.

**Source authority:**
- Story 9.2 verdict matrix rows assigned to this story.
- Epic 7 stories 7.6, 7.7, 7.8, 7.9, 7.10 — original source-authority lists remain valid.

**Targets (only pages with non-PASS verdicts; final list from 9.2):**

*Admin:*
- `frontend/app/pages/banks.vue`, `frontend/app/pages/users.vue`, `frontend/app/pages/staff.vue`
- `frontend/app/pages/admin/cby-staff.vue`, `frontend/app/pages/admin/entities.vue`, `frontend/app/pages/admin/roles.vue`, `frontend/app/pages/admin/workflow-docs.vue`

*Settings & profile:*
- `frontend/app/pages/settings.vue`, `frontend/app/pages/admin/settings.vue`, `frontend/app/pages/profile.vue`

*Reports:*
- `frontend/app/pages/reports/index.vue` and any chart components

*Misc / supporting:*
- `frontend/app/pages/notifications.vue`, `frontend/app/pages/audit.vue`, `frontend/app/pages/customs.vue`, `frontend/app/pages/merchants.vue`, `frontend/app/components/merchants/*.vue`

**Acceptance criteria:**
- Every non-PASS row from 9.2 in this story's scope flips to PASS in a re-screenshot pass.
- Updated evidence triplets committed for every touched page.
- DESIGN.md tokens updated where 9.2 surfaced a delta not already addressed in 9.3.
- Vitest + Playwright suites green.
- No regression in admin RBAC, settings persistence, report computation, or audit data integrity.

**Posture per page (carried from 9.2):** patch vs teardown per row.

**Out of scope:** Auth, dashboards, requests (Story 9.3). Visual regression baselines (Story 9.5).

---

### Story 9.5: Visual Regression Lock & Future-Drift Prevention

As a project maintainer,
I want every parity-locked page to fail CI if its rendered output drifts from the committed baseline,
So that future stories cannot silently regress the UI we just remediated.

**Source authority:**
- Stories 9.1, 9.2, 9.3, 9.4 — produced the evidence triplets that become the baselines.
- Existing Playwright test infrastructure under `frontend/tests/`.

**Targets:**
- `frontend/tests/visual/*.spec.ts` — one spec per parity-locked page; uses Playwright's `toHaveScreenshot()` against the `current.png` baseline from `_bmad-output/parity-evidence/`.
- `frontend/playwright.config.ts` — visual project configuration (threshold, max diff pixels, animation handling).
- `.github/workflows/visual-regression.yml` (or equivalent CI step) — runs the visual project on every PR touching `frontend/app/**`.
- `_bmad/custom/bmad-dev-story.toml` — add a new persistent fact: "If a story intentionally changes a parity-locked page, the dev agent MUST update both the `_bmad-output/parity-evidence/<page>/current.png` and the Playwright baseline in the same change. Updating one without the other is a HALT condition."
- `docs/ui-parity/visual-regression.md` — how to update a baseline when an intentional UI change is made.

**Acceptance criteria:**
- Every page that ended Story 9.3 or 9.4 with a PASS verdict has a corresponding Playwright visual spec and baseline.
- CI runs the visual project on PRs touching `frontend/app/**` and fails on baseline mismatch.
- A test-the-test exercise: deliberately introduce a 4px padding change on one parity-locked page, confirm CI fails, revert, confirm CI passes.
- Documentation for baseline updates is clear enough that a future dev (or agent) can update intentionally without breaking the gate.

**Out of scope:** Performance budgets, accessibility regression, semantic HTML linting — separate concerns.

---

## Epic 11: Role Surface Governance and External FX Confirmation Alignment

**Purpose:** Align the production app with `roles-reference.md` and `testing-playbook.md` after those files became the practical source of truth for role responsibilities, role-specific UI rendering, document access, lifecycle QA, and external FX confirmation terminology. This epic corrects governance drift: the app must render different operational workspaces per role, not a shared surface with disabled or backend-rejected controls.

**Decision date:** 2026-05-25

**Source authorities:**
1. `roles-reference.md` - final authority for each role's responsibilities, dashboard content, visible surfaces, forbidden surfaces, and document access.
2. `testing-playbook.md` - final authority for role smoke coverage, lifecycle handoff tests, document permission tests, and non-visibility checks.
3. Backend enforcement (`TransitionMap`, policies, permission seeder, controllers) - final authority for whether an action is allowed.
4. Existing docs remain valid where they do not conflict with the two new references.

**Correction rules:**
- Role-inappropriate UI is not rendered. Do not rely on disabled buttons, hidden CSS, or backend rejection as the normal UX.
- UI visibility is not a security boundary. Backend policies and workflow guards must continue to reject unauthorized direct calls.
- Start every role surface from the role's operational queue, then add supporting metrics.
- `CBY_ADMIN` has broad visibility but is not a substitute workflow actor for Director, SWIFT, Support, Bank Reviewer, or Executive Member actions.
- Customs declaration terminology is legacy for the Director completion workflow. New user-facing work should use external FX confirmation (`تأكيد مصارفة خارجية`) and the `FX_CONFIRMATION_PENDING` handoff unless a migration story explicitly preserves a compatibility alias.
- Real authenticated users per role are required for testing; do not introduce demo role switching.

**Common technical requirements for all Epic 11 stories:**
- Run SocratiCode before modifying existing files: `codebase_search`, then `codebase_symbol` and `codebase_impact` for touched symbols/components.
- Use browser verification for UI-facing stories.
- Add targeted tests for both visibility and non-visibility.
- Keep `_bmad-output/implementation-artifacts/`, `_bmad-output/test-artifacts/`, and `graphify-out/` local-only and unstaged.

---

### Story 11.1: Role Surface Authority Matrix and Navigation Contract

As a workflow participant in any production role,
I want the app shell, navigation, page access, quick actions, and request actions to be derived from one role-surface authority matrix,
So that each role sees only the product surfaces that belong to its operational job.

**Source authority:**
- `roles-reference.md`
- `testing-playbook.md` Part 1 and Part 5
- `frontend/app/constants/workflow.ts`
- `frontend/app/components/AppSidebar.vue`
- `frontend/app/middleware/role.ts`
- Request detail action components and document permission composables

**Targets:**
- A canonical role-surface matrix in frontend constants or an adjacent typed module.
- Sidebar/nav rendering migrated away from local hardcoded role lists when those duplicate or conflict with the canonical contract.
- Route metadata and `ROUTE_ROLE_MAP` checked against the matrix.
- Request detail actions, dashboard quick actions, search shortcuts, and document rows audited for role-inappropriate rendering.

**Acceptance criteria:**
- Every role from `roles-reference.md` has explicit visible and non-visible navigation surfaces.
- `AppSidebar.vue` no longer maintains divergent role lists for routes already covered by the canonical contract.
- `DATA_ENTRY` sees New Request, Requests, Notifications, and business-facing dashboard surfaces, but no SWIFT, support, voting, admin, or Director controls.
- `BANK_REVIEWER` sees review/request tracking surfaces, but no staff management, SWIFT upload, support claim, voting, or external FX controls.
- `BANK_ADMIN` sees bank operations, staff, merchants, reports where allowed, but no reviewer governance, SWIFT upload, support claim, voting, or Director controls.
- `SWIFT_OFFICER` sees only SWIFT-relevant queue/upload surfaces and no non-SWIFT workflow controls.
- `SUPPORT_COMMITTEE` sees support claim-aware queue surfaces and no SWIFT, voting, external FX, or bank-admin staff controls.
- `EXECUTIVE_MEMBER` sees voting queue/report surfaces and no close/finalize, SWIFT, support, external FX, or system-admin controls.
- `COMMITTEE_DIRECTOR` sees governance, voting lifecycle, and external FX confirmation surfaces, but not bank-admin widgets or SWIFT/support controls.
- `CBY_ADMIN` sees global admin/oversight surfaces, but no role-inappropriate workflow action buttons.
- Tests assert that role-forbidden UI is not rendered, not merely disabled.

**Out of scope:** Backend status migration and external FX data model changes; those belong to Story 11.2.

---

### Story 11.2: External FX Confirmation Status and Terminology Migration

As the Committee Director and SWIFT Officer,
I want the final post-SWIFT workflow to use external FX confirmation terminology and status semantics,
So that the product matches the approved institutional process instead of legacy customs-declaration wording.

**Source authority:**
- `roles-reference.md`
- `testing-playbook.md`
- Backend `RequestStatus`, `TransitionMap`, document services, customs/external-FX controllers, resources, tests
- Frontend status constants, routes, request detail, SWIFT upload page, Director completion surfaces, document checklist

**Acceptance criteria:**
- A migration decision is documented: replace `CUSTOMS_DECLARATION_ISSUED`, introduce `FX_CONFIRMATION_PENDING`, and either migrate or alias legacy customs database/API names.
- The happy path follows: `EXECUTIVE_APPROVED` -> `WAITING_FOR_SWIFT` -> `SWIFT_UPLOADED` -> `FX_CONFIRMATION_PENDING` -> `COMPLETED`.
- SWIFT Officer uploads both SWIFT PDF and FX confirmation request PDF before the request leaves SWIFT ownership.
- Committee Director can download generated external FX confirmation PDF and upload signed/stamped external FX confirmation PDF.
- `CBY_ADMIN` may view/download where permitted, but cannot complete the Director-only workflow.
- User-facing labels no longer say customs declaration for the Director external FX completion workflow.
- Existing historical data remains readable.
- Backend and frontend enums match exactly after migration.
- Tests cover allowed and forbidden roles for external FX documents and actions.

**Out of scope:** Visual parity polish unrelated to terminology/status correctness.

---

### Story 11.3: Role Dashboard and Request Detail Alignment

As a production user,
I want my dashboard and request detail view to match my role responsibilities,
So that I start from the right queue and never see controls belonging to another workflow actor.

**Source authority:**
- `roles-reference.md` dashboard sections
- `testing-playbook.md` role smoke tests and Part 5 dashboard checklist
- `frontend/app/pages/dashboard.vue`
- `frontend/app/components/dashboard/*.vue`
- `frontend/app/pages/requests/[id]/index.vue`
- `ActionsPanel`, `DocumentChecklist`, `VotingPanel`, support claim and SWIFT components
- `GET /api/dashboard/stats`

**Acceptance criteria:**
- Each dashboard's KPI cards, quick actions, tables, and empty/loading/error states match the role's operational mission from `roles-reference.md`.
- Dashboard data is scoped correctly by bank/global role.
- Request detail actions are rendered only for the eligible role/status combination.
- Support claim state distinguishes unclaimed, claimed by me, and claimed by others.
- Director sees external FX pending workload after SWIFT upload.
- Data Entry sees simplified business status and no deep CBY operational internals as the primary UX.
- Tests cover dashboard role smoke expectations for all eight roles.

**Out of scope:** Creating new workflow powers not already approved by backend rules.

---

### Story 11.4: Role Smoke and Lifecycle Test Automation

As the project maintainer,
I want the manual testing playbook converted into repeatable automated or scripted checks,
So that role-surface regressions and lifecycle handoff regressions are caught before release.

**Source authority:**
- `testing-playbook.md`
- Existing backend feature tests
- Existing frontend Vitest and Playwright setup

**Acceptance criteria:**
- Automated role smoke tests cover all eight roles.
- Visibility and non-visibility are both asserted.
- Happy-path lifecycle coverage verifies status, owner role, visible UI state, and document handoffs from draft to completed.
- Branch coverage includes bank return, bank terminal rejection, support rejection, support return, executive rejection, claim expiry, document permissions, cross-bank isolation, and immutable states.
- Test evidence format follows the playbook fields: role, request reference, start status, action, expected, actual, result.
- Browser-based verification is used for UI flows.

**Out of scope:** Load/performance testing and production monitoring.

---

## Epic 12: Role-Driven UX/UI Enhancement

**Purpose:** Bring the shipped frontend up to the per-role operational-posture fidelity specified in `docs/user-view/`. Epic 7 delivered role-distinct dashboards; Epic 10 transplanted Lovable pages; Epic 11.1 enforced what is rendered per role; this epic enforces *how the visible surface should look, feel, and behave* per role's operational posture.

**Decision date:** 2026-05-25

**Source authorities:**
1. `docs/user-view/*.md` — final authority for per-role operational posture, dashboard structure, page interaction patterns, KPI semantics, density, micro-copy, status presentation, empty/loading/error states, and RTL behaviour.
2. `roles-reference.md` — non-visibility and visibility contract (must remain consistent with Epic 11.1 matrix).
3. `testing-playbook.md` — role smoke and lifecycle assertions.
4. `DESIGN.md` — visual token constraints (typography, colour, spacing, motion). New UX must compose existing tokens; no new tokens introduced by this epic.
5. Existing lovable/ screenshots and current frontend screens — parity-evidence visual baselines (Epic 9 triplet rule).

**Correction rules:**
- Compose existing shadcn-vue components and `DESIGN.md` tokens. Do not introduce new design primitives.
- Every story produces the Epic 9 parity-evidence triplet: spec citation + visual reference + diff.
- Operational-posture uplift must not loosen Epic 11.1 non-visibility contracts.
- External FX completion surfaces (Director + SWIFT) use whatever terminology currently ships in production code at story start; do not introduce terminology divergence between the spec and the deployed copy.
- Use `/ui-ux-pro-max` during dev for design intelligence; do not invent new styles.

**Common technical requirements for all Epic 12 stories:**
- Run SocratiCode before modifying existing files: `codebase_search`, then `codebase_symbol` and `codebase_impact` for touched components.
- Use browser verification (dev-browser) for UI-facing changes.
- Add Vitest role-specific assertions and Playwright visual baselines per surface.
- Update `docs/ui-parity/parity-matrix.md` (Story 9.2 artefact) with triplets for each touched surface.
- Keep `_bmad-output/implementation-artifacts/`, `_bmad-output/test-artifacts/`, and `graphify-out/` local-only and unstaged.

---

### Story 12.1: Tier 1 — High-Traffic Operational Roles UX Uplift

As a daily operational user (DATA_ENTRY, BANK_REVIEWER, SUPPORT_COMMITTEE, or EXECUTIVE_MEMBER),
I want my dashboard and primary work surfaces to embody my role's operational posture as specified in `docs/user-view/<role>.md`,
So that the product feels like a focused work surface tuned to my job rather than a generic admin console.

**Source authority:**
- `docs/user-view/data-entry.md`
- `docs/user-view/bank-reviewer.md`
- `docs/user-view/support-committee.md`
- `docs/user-view/executive-member.md`
- `roles-reference.md` (non-visibility cross-check)
- `frontend/app/pages/dashboard.vue` and role-specific dashboard components
- `frontend/app/pages/requests/index.vue` and `frontend/app/pages/requests/[id]/index.vue`
- Wizard, ActionsPanel, DocumentChecklist, VotingPanel, support claim, and inactivity-banner components

**Targets:**
- DATA_ENTRY: task-oriented intake surface; simplified business-status presentation; returned-queue prominence; document checklist density and validation tone per spec.
- BANK_REVIEWER: review-gate posture; submitted queue prominence; clear separation between self-bank visibility and CBY downstream tracking; ActionsPanel decision affordances per spec.
- SUPPORT_COMMITTEE: claim-aware presence posture; queue ↔ claim distinction; claim-by-me / claim-by-other / unclaimed visual states; release/heartbeat surface cues per spec.
- EXECUTIVE_MEMBER: voting-focused posture; voting queue + closed-decisions framing; vote affordances and justification flow per spec.

**Acceptance criteria:**
- Each of the four roles' dashboard surfaces match the spec for: KPI set, KPI ordering, density, empty/loading/error states, micro-copy, primary CTA presence, and quick-action set.
- Request-list surface per role matches spec for: tab/filter set, default tab, status-label presentation (simplified business labels for DATA_ENTRY), bulk action visibility, and empty-state copy.
- Request-detail surface per role matches spec for: tab order, ActionsPanel rendering rules, document checklist scoping, support-claim presence states (SUPPORT only), voting panel framing (EXECUTIVE only).
- Status presentation: DATA_ENTRY sees simplified business labels; other three roles see canonical workflow labels.
- Non-visibility holds: no SWIFT, no FX, no admin, no governance surfaces leak into these four roles.
- Parity-evidence triplet recorded per surface in `docs/ui-parity/parity-matrix.md`.
- Vitest role-specific assertions cover spec micro-copy, KPI presence/order, density classes.
- Playwright baselines updated for the four dashboards and four request-detail variants.
- `/ui-ux-pro-max` invoked during dev; design rationale captured in the story validation report.

**Out of scope:** Tier 2 admin roles, Tier 3 finalization roles, backend changes, new design tokens, FX terminology (deferred to 11.2 and 12.3).

---

### Story 12.2: Tier 2 — Administrative Roles UX Uplift

As an administrator (CBY_ADMIN or BANK_ADMIN),
I want my dashboard and admin surfaces to embody the oversight/governance posture specified in `docs/user-view/<role>.md`,
So that I quickly answer platform-health and bank-operations questions rather than navigating an operator console.

**Source authority:**
- `docs/user-view/cby-admin.md` (85 KB — the most detailed spec)
- `docs/user-view/bank-admin.md`
- `roles-reference.md` (CBY_ADMIN read-only oversight contract; BANK_ADMIN bank-internal authority contract)
- `frontend/app/pages/dashboard.vue` and admin-role dashboard components
- `frontend/app/pages/admin/*.vue` (entities, cby-staff, workflow-docs, roles)
- `frontend/app/pages/staff.vue` (BANK_ADMIN staff management)
- Reports and audit surfaces (CBY_ADMIN)

**Targets:**
- CBY_ADMIN dashboard: strategic governance surface — KPI strip (system-health, bottleneck, risk, executive-decision-delay, compliance-anomaly metrics), bank filter + date filter toolbar, export Executive Summary PDF, no New Request CTA, "إشراف فقط" read-only oversight badge.
- CBY_ADMIN admin surfaces (entities, cby-staff, workflow-docs, roles): governance tone, full-bank visibility, density and micro-copy per spec, no workflow action affordances leak into oversight pages.
- BANK_ADMIN dashboard: bank-internal operations posture — bank-scoped KPIs, sparkline trend, quick-action, recent requests table.
- BANK_ADMIN staff management: bank-scoped staff CRUD, role assignment limited to BANK_REVIEWER / DATA_ENTRY / SWIFT_OFFICER, deactivation flow.

**Acceptance criteria:**
- CBY_ADMIN dashboard renders the governance KPI strip and toolbar per spec; no operational action buttons leak.
- CBY_ADMIN admin pages match spec density, micro-copy, and empty/loading/error states.
- BANK_ADMIN dashboard renders bank-scoped KPIs and recent-requests table per spec.
- BANK_ADMIN staff management enforces bank-scoped role allowlist and matches spec for modal/dialog tone.
- Non-visibility holds: CBY_ADMIN must not see workflow action buttons; BANK_ADMIN must not see CBY-side admin surfaces.
- Parity-evidence triplet recorded per surface.
- Vitest assertions cover oversight-badge presence, action-button absence, role-allowlist.
- Playwright baselines updated for the two dashboards and key admin pages.
- `/ui-ux-pro-max` invoked during dev.

**Out of scope:** Tier 1 operational roles, Tier 3 finalization roles, backend changes, new analytics endpoints (reuse existing `GET /api/dashboard/stats` and reports).

---

### Story 12.3: Tier 3 — Lifecycle Finalization Roles UX Uplift

As a lifecycle-finalization user (COMMITTEE_DIRECTOR or SWIFT_OFFICER),
I want my dashboard and stage-specific surfaces to embody the role-specific finalization posture specified in `docs/user-view/<role>.md`,
So that voting lifecycle management, external FX completion, and SWIFT + FX-confirmation-request upload feel like first-class workflows rather than generic detail pages.

**Source authority:**
- `docs/user-view/committee-director.md`
- `docs/user-view/swift-officer.md`
- `roles-reference.md` (Director governance authority; SWIFT scope)
- Current shipped status enum, document model, API naming, and PDF generation as deployed at story start
- `frontend/app/pages/dashboard.vue` and finalization-role dashboard components
- VotingPanel, ActionsPanel director controls, finalization completion surfaces, SWIFT upload page

**Targets:**
- COMMITTEE_DIRECTOR dashboard: governance + lifecycle posture — voting-open queue, voting-closed-awaiting-finalize queue, finalization workload after SWIFT upload, recent finalized decisions, decision-delay metric.
- COMMITTEE_DIRECTOR request-detail: voting-session controls (open/close), tie-break affordance, override-and-finalize affordance with justification, finalization completion affordances (download generated PDF, upload signed/stamped PDF) using whatever terminology currently ships.
- SWIFT_OFFICER dashboard: focused upload queue posture — SUPPORT_APPROVED queue, post-SWIFT awaiting queue, uploads completed today.
- SWIFT_OFFICER request-detail: SWIFT upload affordance and any FX-confirmation-request upload affordance that currently ships, gated to SUPPORT_APPROVED status.

**Acceptance criteria:**
- COMMITTEE_DIRECTOR dashboard surfaces the voting lifecycle queues and post-SWIFT finalization workload per spec.
- VotingPanel + ActionsPanel director controls match spec tone, density, and confirmation flows.
- Finalization completion surfaces (Director side) use whatever terminology currently ships in production code; no terminology divergence between spec citations and deployed copy.
- SWIFT_OFFICER dashboard surfaces SWIFT-relevant queues only; no voting / finalization / admin surfaces leak.
- SWIFT upload and any post-SWIFT upload surfaces (SWIFT side) match spec.
- Non-visibility holds per `roles-reference.md`.
- Parity-evidence triplet recorded per surface.
- Vitest assertions cover director-only and SWIFT-only affordance presence/absence; terminology assertions reference the currently-shipping copy, not future migration targets.
- Playwright baselines updated for the two dashboards and finalization request-detail variants.
- `/ui-ux-pro-max` invoked during dev.

**Out of scope:** Tier 1 operational roles, Tier 2 administrative roles, backend changes, any external FX terminology migration (out of Epic 12's scope).
