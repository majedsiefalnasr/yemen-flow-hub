---
stepsCompleted: [step-01-document-discovery, step-02-prd-analysis, step-03-epic-coverage-validation, step-04-ux-alignment, step-05-epic-quality-review, step-06-final-assessment]
documentInventory:
  prd: []
  architecture: []
  epics:
    - _bmad-output/planning-artifacts/epics.md
  ux: []
  projectContext:
    - _bmad-output/planning-artifacts/project-context.md
  specDocs:
    - docs/00-project-brief.md
    - docs/01-workflow-and-business-rules.md
    - docs/02-system-architecture.md
    - docs/03-database-and-models.md
    - docs/04-frontend-guide.md
    - docs/05-backend-guide.md
    - docs/06-api-reference.md
    - docs/07-task-breakdown.md
  designSystem:
    - DESIGN.md
  uxReference:
    - lovable/ (React prototype — reference only)
---

# Implementation Readiness Assessment Report

**Date:** 2026-05-15
**Project:** Yemen Flow Hub

---

## PRD Analysis

> **PRD Source:** `docs/00-project-brief.md`, `docs/01-workflow-and-business-rules.md`, `docs/07-task-breakdown.md`, plus architecture/API/DB sections from `project-context.md` (compiled from `docs/02–06`).

### Functional Requirements

**Authentication & Authorization**

| ID | Requirement |
|---|---|
| FR01 | System authenticates users via email/password using Laravel Sanctum (HTTP-only cookie SPA mode) |
| FR02 | 7 canonical roles: DATA_ENTRY, BANK_REVIEWER, SWIFT_OFFICER, SUPPORT_COMMITTEE, EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR, CBY_ADMIN |
| FR03 | All routes are protected; unauthenticated users are redirected to login |
| FR04 | Navigation is role-scoped — menu items visible only to authorized roles |
| FR05 | Organization-scoped visibility: bank users see only requests belonging to their bank |
| FR06 | CBY users (SUPPORT_COMMITTEE, EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR, CBY_ADMIN) see all banks' requests |

**Request Lifecycle**

| ID | Requirement |
|---|---|
| FR07 | DATA_ENTRY can create, edit, and submit import financing requests |
| FR08 | DATA_ENTRY can delete requests in DRAFT state only |
| FR09 | Request fields: merchant, amount, currency, supplier_name, goods_description, port_of_entry, payment_terms, supplier_country, invoice_number/date, notes |
| FR10 | Requests generate unique reference number: `YFH-{YEAR}-{NNNNNN}` |
| FR11 | Self-review prohibition: the user who created a request cannot be the bank reviewer |
| FR12 | BANK_REVIEWER can approve, reject (pre-approval), or return to DATA_ENTRY |
| FR13 | After bank approval, request is permanently read-only (no direct editing allowed) |
| FR14 | BANK_REVIEWER can return SUPPORT_REJECTED requests to DATA_ENTRY for correction |
| FR15 | DRAFT_REJECTED_INTERNAL state makes request editable again |
| FR16 | Bank Reviewer can choose to keep request in SUPPORT_REJECTED (no further action required) |

**Workflow State Machine**

| ID | Requirement |
|---|---|
| FR17 | 18 canonical request statuses per docs: DRAFT → DRAFT_REJECTED_INTERNAL → SUBMITTED → BANK_REVIEW → BANK_APPROVED → SUPPORT_REVIEW_PENDING → SUPPORT_REVIEW_IN_PROGRESS → SUPPORT_APPROVED → SUPPORT_REJECTED → WAITING_FOR_SWIFT → SWIFT_UPLOADED → WAITING_FOR_VOTING_OPEN → EXECUTIVE_VOTING_OPEN → EXECUTIVE_VOTING_CLOSED → EXECUTIVE_APPROVED → EXECUTIVE_REJECTED → CUSTOMS_DECLARATION_ISSUED → COMPLETED |
| FR18 | All state transitions go through WorkflowService::transition() — direct model status mutation is forbidden |
| FR19 | Every workflow transition is logged to BOTH `request_stage_history` AND `audit_logs` |
| FR20 | Terminal states (EXECUTIVE_REJECTED, CUSTOMS_DECLARATION_ISSUED, COMPLETED) return HTTP 403 + WORKFLOW_IMMUTABLE_STATE on any mutation attempt |
| FR21 | Workflow tracks actor at each stage: created_by, submitted_by, internal_reviewer, support_reviewer, swift_uploaded_by, voting_opened_by, voting_closed_by |

**Support Committee Review**

| ID | Requirement |
|---|---|
| FR22 | SUPPORT_COMMITTEE can claim requests from the SUPPORT_REVIEW_PENDING queue (exclusive soft-lock) |
| FR23 | Only one support committee member can actively review a request at a time |
| FR24 | Claim auto-releases on disconnect/timeout (15-minute inactivity window; frontend pings heartbeat every 60 seconds) |
| FR25 | Only the claim holder can approve or reject; other support users can view but not act |
| FR26 | SUPPORT_COMMITTEE can approve or reject (with mandatory reason) a claimed request |
| FR27 | SUPPORT_REJECTED returns request to BANK_REVIEWER queue |

**SWIFT Upload**

| ID | Requirement |
|---|---|
| FR28 | SWIFT_OFFICER can upload the SWIFT document (PDF only) for SUPPORT_APPROVED requests |
| FR29 | SWIFT upload auto-chains to executive voting stage (no manual trigger needed) |
| FR30 | After SWIFT upload, request is fully read-only; documents cannot be replaced |
| FR31 | Executive voting cannot start before SWIFT document is uploaded |

**Executive Voting**

| ID | Requirement |
|---|---|
| FR32 | COMMITTEE_DIRECTOR opens and closes voting sessions |
| FR33 | Each EXECUTIVE_MEMBER (including COMMITTEE_DIRECTOR) can vote APPROVE, REJECT, or ABSTAIN once per request |
| FR34 | Members who did not vote before session closes are assigned AUTO_ABSTAIN_TIMEOUT (distinct from manual ABSTAIN) |
| FR35 | Voting auto-finalizes if approve ≥ 4 OR reject ≥ 4 in a 6-member committee |
| FR36 | Tie (3:3 after all 6 voted) requires COMMITTEE_DIRECTOR to resolve with tie-break vote |
| FR37 | COMMITTEE_DIRECTOR can override and finalize at any time during voting (requires justification) |
| FR38 | EXECUTIVE_REJECTED is terminal: permanently locked, cannot be reopened, edited, or overridden by anyone |

**Customs Declaration**

| ID | Requirement |
|---|---|
| FR39 | COMMITTEE_DIRECTOR issues customs declaration after EXECUTIVE_APPROVED |
| FR40 | Customs declaration generated as printable RTL PDF via DomPDF |
| FR41 | Declaration has unique number: `CD-{YEAR}-{NNNNNN}` |
| FR42 | Declaration is immutable after issuance (permanent workflow artifact) |
| FR43 | Customs declaration generation triggers CUSTOMS_DECLARATION_ISSUED → COMPLETED in a single database transaction |

**Document Management**

| ID | Requirement |
|---|---|
| FR44 | All document uploads must be PDF only (MIME type validated) |
| FR45 | Documents stored in private storage (never publicly accessible) |
| FR46 | Document download access governed by role permission matrix (varies by role + document type) |
| FR47 | Request documents can be uploaded/deleted only in editable states (DRAFT, DRAFT_REJECTED_INTERNAL) |
| FR48 | SWIFT document is a separate document type from request documents |

**Dashboard & Visibility**

| ID | Requirement |
|---|---|
| FR49 | DATA_ENTRY sees simplified business status (not raw CBY internal statuses) |
| FR50 | Dashboard is queue-oriented: answers "what work matters right now?" — not analytics |
| FR51 | BANK_REVIEWER can monitor full lifecycle: support review, SWIFT, voting, final result, customs |
| FR52 | CBY_ADMIN has global visibility across all banks (read-only) |
| FR53 | Support committee queue shows claim state and active reviewer information |

**Administration**

| ID | Requirement |
|---|---|
| FR54 | CBY_ADMIN can create, edit, and deactivate banks |
| FR55 | CBY_ADMIN can create, edit, and manage CBY staff users |
| FR56 | CBY_ADMIN can manage document type configurations |
| FR57 | CBY_ADMIN can view audit log |
| FR58 | BANK_MANAGER (or bank admin role) manages bank users |
| FR59 | Merchants management accessible to BANK_MANAGER and CBY_ADMIN |

**Audit Logging**

| ID | Requirement |
|---|---|
| FR60 | Every action logged: creation, modification, approval, rejection, voting, SWIFT upload, customs issuance, status transitions |
| FR61 | Audit log includes: user_id, user_role (at time of action), timestamp, action_type, from_status, to_status, IP address, user_agent, metadata |
| FR62 | Failed authentication attempts logged with user_id: NULL |

**Total FRs: 62**

---

### Non-Functional Requirements

**Performance**

| ID | Requirement |
|---|---|
| NFR01 | Pagination: default 20 items per page on all list endpoints |
| NFR02 | No N+1 queries: always eager-load relations (bank, merchant, claimedBy) on list queries |
| NFR03 | Database indexes on: status, bank_id, claimed_by, claim_expires_at, current_owner_role, created_at |
| NFR04 | Redis caching for dashboard stats and queue counts |
| NFR05 | Frontend: lazy-load pages and components; no heavy analytics on dashboard |

**Security**

| ID | Requirement |
|---|---|
| NFR06 | Sanctum HTTP-only cookie auth (SPA mode); Bearer token for API clients |
| NFR07 | Login rate limit: 5 attempts/minute per IP |
| NFR08 | Account lockout: 10 consecutive failures → 15-minute lockout |
| NFR09 | Organization scoping enforced at database query level (not frontend-only) |
| NFR10 | Pessimistic locking (lockForUpdate()) required for vote submission and voting session closure |
| NFR11 | File validation: PDF MIME type + max size enforcement |
| NFR12 | File storage: private disk only (storage/private/) — never public |
| NFR13 | CSRF enforced by Sanctum SPA mode |
| NFR14 | Failed auth attempts logged to audit_logs with user_id: NULL |

**Usability**

| ID | Requirement |
|---|---|
| NFR15 | Arabic-first RTL layout (dir="rtl") — default direction, not an afterthought |
| NFR16 | Desktop-first; responsive degradation at ≤ 600px |
| NFR17 | Executive voting pages must work on tablets |
| NFR18 | Typography: IBM Plex Sans Arabic for Arabic text, Inter for English |
| NFR19 | Minimum touch target: 48px (voting pages) |
| NFR20 | Status badges must use both color AND icon — never color-only |
| NFR21 | All actions remain accessible without hover (no hover-only interactions) |
| NFR22 | Transitions: 120ms fade/slide only; no bounce, parallax, or background animation |

**Code Quality & Architecture**

| ID | Requirement |
|---|---|
| NFR23 | TypeScript strict mode; no `any` types |
| NFR24 | All status/role values from typed enums — never raw strings |
| NFR25 | Zod schemas for all form validation |
| NFR26 | Controllers must be thin: receive → validate → authorize → call service → return response |
| NFR27 | Business logic in services only (never in controllers, Vue components, or routes) |
| NFR28 | Vue components: presentation only (no direct API calls, no business logic) |
| NFR29 | All workflow transitions atomic and transactional |
| NFR30 | Customs declaration generation wrapped in a single database transaction |

**Total NFRs: 30**

---

### Additional Requirements / Constraints

| ID | Constraint |
|---|---|
| C01 | No shared admin dashboards — every view is queue-scoped and role-scoped |
| C02 | `lovable/` prototype: reference only — do NOT copy code or modify it |
| C03 | MVP excludes: notifications, real-time, advanced reports, email/SMS, SSO, AML, advanced analytics, cloud object storage, multi-language |
| C04 | Workflow statuses must use the canonical 18-value enum (not implementation's diverged enum) |
| C05 | COMMITTEE_DIRECTOR inherits all EXECUTIVE_MEMBER permissions plus session management and customs issuance |
| C06 | No public access — platform is strictly internal (commercial banks + CBY staff only) |
| C07 | Requests belong to bank entity (not individual users) — full bank team can view |
| C08 | Support claim TTL: 15-minute inactivity window (not hours-based as currently implemented) |
| C09 | Claim heartbeat: frontend must ping every 60 seconds |

---

---

## Epic Coverage Validation

> **Epic Source:** `_bmad-output/planning-artifacts/epics.md` — contains its own Requirements Inventory (FR1–FR91, NFR1–NFR22, AR1–AR14, UX-DR1–UX-DR42) plus a FR Coverage Map and 4 epics / 14 stories.

### Epic Internal Coverage Map (from epics.md)

| Range | Description | Epic Assignment |
|---|---|---|
| FR1–FR8 | Auth, session, rate limiting, lockout | Epic 1 |
| FR9–FR12 | User & bank management, 7 canonical roles | Epic 1 |
| FR13–FR26 | Request CRUD, submission, bank review | Epic 2 |
| FR27–FR39 | Support claim lifecycle, approve/reject, post-rejection | Epic 3 |
| FR40–FR43 | SWIFT upload, immutability, queue | Epic 3 |
| FR44–FR55 | Executive voting engine, sessions, tally, tie, terminal | Epic 3 |
| FR56–FR61 | Customs declaration — transaction, RTL PDF, COMPLETED | Epic 3 |
| FR62–FR63 | Organization-scoped visibility, business-status abstraction | Epic 2 |
| FR64–FR65 | BANK_REVIEWER and SWIFT_OFFICER scoped visibility | Epic 2+3 |
| FR66 | SUPPORT_COMMITTEE queue visibility + claim state | Epic 3 |
| FR67 | Executive queue visibility | Epic 3 |
| FR68 | CBY_ADMIN full system visibility | Epic 4 |
| FR69–FR70 | Business-status abstraction for DATA_ENTRY | Epic 2 |
| FR71–FR76 | Audit logging, stage history, audit API | Epic 4 |
| FR77–FR82 | Document download permission matrix | Epic 4 |
| FR83–FR88 | Dashboard queue APIs (per role) | Epic 2+3 |
| FR89–FR91 | Immutable/locked state enforcement, DirectStatusMutationException | Epic 2 |
| AR1–AR14 | Architecture: project setup, services, stores, Redis, seeding | Epics 1–3 |
| NFR1–NFR22 | All non-functional requirements | Epics 1–4 |
| UX-DR1–UX-DR42 | All UX design requirements | Epics 1–4 |

**Internal coverage: 91/91 FRs, 22/22 NFRs, 14/14 ARs, 42/42 UX-DRs — 100% mapped within the epics document's own numbering.**

---

### FR Coverage Analysis — PRD/Docs vs. Epics

Mapping my extracted PRD FRs (step 2) against the epic coverage:

| PRD FR | PRD Requirement (short) | Epic Coverage | Status |
|---|---|---|---|
| FR01 | Sanctum cookie auth | Epic FR1, Story 1.2 | ✓ Covered |
| FR02 | 7 canonical roles | Epic FR12, Story 1.1 | ✓ Covered |
| FR03 | Route protection / auth middleware | Epic FR7, Story 1.3 | ✓ Covered |
| FR04 | Role-scoped navigation | Epic UX-DR4, Story 1.3 | ✓ Covered |
| FR05 | Org-scoped visibility (bank users) | Epic FR62, Story 2.3 | ✓ Covered |
| FR06 | CBY users see all banks | Epic FR66–68, Stories 3.2, 4.5 | ✓ Covered |
| FR07 | DATA_ENTRY CRUD on requests | Epic FR13–16, Story 2.1 | ✓ Covered |
| FR08 | Delete DRAFT only | Epic FR16, Story 2.1 | ✓ Covered |
| FR09 | Request fields | Epic FR13, Story 2.5 | ⚠️ Partial — FR13/Story 2.5 omit merchant selection, payment_terms, supplier_country, invoice_number/date that the 4-step form design requires |
| FR10 | Reference number auto-generation | Epic FR14, Story 2.1 | ✓ Covered |
| FR11 | Self-review prohibition | Epic FR25, Story 2.3 | ✓ Covered |
| FR12 | BANK_REVIEWER actions | Epic FR23–24, Story 2.3 | ✓ Covered |
| FR13 | Locked after bank approval | Epic FR26, Story 2.3 | ✓ Covered |
| FR14 | BANK_REVIEWER return after support reject | Epic FR38, Story 3.1 | ✓ Covered |
| FR15 | DRAFT_REJECTED_INTERNAL editable | Epic FR15, Story 2.1 | ✓ Covered |
| FR16 | BANK_REVIEWER keep SUPPORT_REJECTED | Epic FR39, Story 3.1 | ✓ Covered |
| FR17 | 18 canonical request statuses | Epic AR4, Story 1.1 | ✓ Covered |
| FR18 | WorkflowService::transition() enforcement | Epic FR91/AR5, Stories 2.1, 3.1 | ✓ Covered |
| FR19 | Both history tables per transition | Epic FR71, Story 4.1 | ✓ Covered |
| FR20 | Terminal state → HTTP 403 | Epic FR89, Story 2.1 | ✓ Covered |
| FR21 | Workflow actor tracking fields | Epic UX-DR17, Story 2.6 | ✓ Covered |
| FR22 | Support claim lifecycle | Epic FR28–34, Stories 3.1, 3.2 | ✓ Covered |
| FR23 | One reviewer at a time | Epic FR30, Story 3.1 | ✓ Covered |
| FR24 | Claim auto-release on timeout | Epic FR31–34, Story 3.1 | ✓ Covered |
| FR25 | Claim holder exclusivity | Epic FR35, Story 3.1 | ✓ Covered |
| FR26 | SUPPORT_COMMITTEE approve/reject | Epic FR35–36, Story 3.1 | ✓ Covered |
| FR27 | SUPPORT_REJECTED → BANK_REVIEWER queue | Epic FR37, Story 3.1 | ✓ Covered |
| FR28 | SWIFT_OFFICER upload PDF | Epic FR41, Story 3.3 | ✓ Covered |
| FR29 | SWIFT auto-chains to voting stage | Epic FR41, Story 3.3 | ✓ Covered |
| FR30 | SWIFT immutable after upload | Epic FR42, Story 3.3 | ✓ Covered |
| FR31 | No voting before SWIFT | Epic FR44 (implied), Story 3.4 | ✓ Covered |
| FR32 | Director opens/closes voting | Epic FR44/FR48, Story 3.4 | ✓ Covered |
| FR33 | Each member votes once | Epic FR46, Story 3.4 | ✓ Covered |
| FR34 | AUTO_ABSTAIN_TIMEOUT on close | Epic FR49–50, Story 3.4 | ✓ Covered |
| FR35 | Auto-finalize at ≥4 votes | Epic FR52 (partial) | ⚠️ Partial — Epics describe majority-wins logic at finalization; the implemented auto-finalize-at-4 optimization is NOT in any story acceptance criteria |
| FR36 | Tie-break by Director | Epic FR52–53, Story 3.4 | ✓ Covered |
| FR37 | Director override-and-finalize | NOT FOUND in epics | ❌ Missing — The implemented `overrideAndFinalize()` VotingService method is not in any story acceptance criteria or FR |
| FR38 | EXECUTIVE_REJECTED terminal | Epic FR55, Story 3.4 | ✓ Covered |
| FR39 | Customs declaration issuance | Epic FR56–61, Story 3.6 | ✓ Covered |
| FR40 | PDF-only uploads | Epic FR20, Story 2.2 | ✓ Covered |
| FR41 | Private document storage | Epic AR8, NFR4, Story 2.2 | ✓ Covered |
| FR42 | Download permission matrix | Epic FR77–82, Story 4.3 | ✓ Covered |
| FR43 | Upload/delete in editable states only | Epic FR18–19, Story 2.2 | ✓ Covered |
| FR44 | SWIFT as separate document type | Epic FR40–42, Story 3.3 | ✓ Covered |
| FR45 | Simplified business status (DATA_ENTRY) | Epic FR69–70, Story 2.4 | ✓ Covered |
| FR46 | Queue-oriented dashboards | Epic NFR20/UX-DR5–10, Stories 2.7, 3.2, 4.5, 4.6 | ✓ Covered |
| FR47 | BANK_REVIEWER downstream visibility | Epic FR64, Story 2.7 | ✓ Covered |
| FR48 | CBY_ADMIN global visibility | Epic FR68, Story 4.5 | ✓ Covered |
| FR49 | Support queue shows claim state | Epic FR66/UX-DR36, Story 3.2 | ✓ Covered |
| FR50 | CBY_ADMIN manage banks | Epic FR10, Story 1.4 | ✓ Covered |
| FR51 | CBY_ADMIN manage CBY users | Epic FR9, Story 1.4 | ✓ Covered |
| FR52 | CBY_ADMIN manage document types | NOT FOUND | ❌ Missing — No story covers document type management despite navigation item |
| FR53 | CBY_ADMIN audit log | Epic FR76, Story 4.1 | ✓ Covered |
| FR54 | Bank user management (BANK_MANAGER) | NOT FOUND | ❌ Missing — No story covers `/bank/users` or bank user management (BANK_MANAGER role not in canonical 7-role spec either) |
| FR55 | Merchant management | NOT FOUND | ❌ Missing — No story covers `/merchants` page despite navigation and DB schema FK |
| FR56 | Reports page (frontend) | NOT FOUND (only API) | ⚠️ Partial — Story 4.5 covers reports APIs but no story covers the frontend /reports page |
| FR57 | Audit log (workflow actions) | Epic FR71–74, Story 4.1 | ✓ Covered |
| FR58 | Failed auth logged | Epic FR6, Story 1.2 | ✓ Covered |
| FR59 | Voting tally panel | Epic UX-DR25–29, Story 3.5 | ✓ Covered |
| FR60 | Member votes once per request | Epic FR46, Story 3.4 | ✓ Covered |

---

### Missing Requirements

#### Critical Missing FRs

**Epic Gap 1: Director Override-and-Finalize (FR37)**
- **Requirement:** COMMITTEE_DIRECTOR can override and finalize at any time during an active voting session (requires justification). Implemented in `VotingService::overrideAndFinalize()`.
- **Impact:** High — without a story covering this, the `POST /api/voting/{id}/override` endpoint and its frontend trigger are unspecified. Developers may implement it incorrectly or skip it.
- **Recommendation:** Add acceptance criteria to Story 3.4 and Story 3.5 covering the override scenario, endpoint behavior, and UI trigger.

**Epic Gap 2: Merchant Management (FR55)**
- **Requirement:** Merchant management page (`/merchants`) for BANK_MANAGER and CBY_ADMIN. Import requests have a `merchant_id` FK. The form step 1 selects a merchant.
- **Impact:** High — without merchants, the request creation form cannot populate merchant dropdown. The `merchant_id` FK in `import_requests` would always be NULL, breaking the form flow.
- **Recommendation:** Add Story 2.x: Merchant CRUD APIs + `/merchants` frontend page before or alongside Story 2.5 (form).

**Epic Gap 3: Document Type Management (FR52)**
- **Requirement:** CBY_ADMIN can configure document types via `/admin/workflow-docs`. The `document_types` table exists in the schema.
- **Impact:** Medium — without document type config, the document checklist is static. The story scope may change based on whether document types are MVP or post-MVP.
- **Recommendation:** Either add Story 4.x covering document type management, or explicitly mark this as post-MVP in the epics document.

#### High Priority Missing Coverage

**Epic Gap 4: BANK_REVIEW Endpoint (FR22 nuance)**
- **Requirement:** Epic FR22 specifies `POST /api/workflow/{id}/bank-review` (begin active review: SUBMITTED → BANK_REVIEW). This endpoint does NOT appear in the implemented backend routes (`project-context.md §9`). The backend currently jumps from SUBMITTED directly to BANK_APPROVED/DRAFT_REJECTED_INTERNAL.
- **Impact:** High — Story 2.3 acceptance criteria include this endpoint, but it needs to be added to the backend.
- **Recommendation:** Ensure Story 2.3 is tracked as backend work to add this endpoint and transition.

**Epic Gap 5: Form Field Scope (FR09)**
- **Requirement:** The Lovable/UX-approved 4-step form design includes: merchant selection, payment_terms, supplier_country, invoice_number, invoice_date (steps 1 and 2). Epic FR13 and Story 2.5 only specify: currency, amount, supplier_name, goods_description, port_of_entry, notes.
- **Impact:** High — developers implementing Story 2.5 will build a simpler form than the UX design requires, resulting in rework.
- **Recommendation:** Update FR13 and Story 2.5 acceptance criteria to include the full field list from the 4-step UX design.

**Epic Gap 6: Support Claim Release — DELETE vs POST**
- **Requirement:** Epic FR33 specifies `DELETE /api/workflow/{id}/claim-support-review`. The implemented backend uses `POST /api/workflow/{id}/support-release`.
- **Impact:** High — the frontend (Story 3.2) will be written against one convention; the backend is implemented differently. Must align before implementation.
- **Recommendation:** Decide which convention to use (DELETE per docs/06 spec is preferred) and update backend implementation accordingly.

#### Medium Priority

**Epic Gap 7: Auto-Finalize at Vote Threshold**
- **Requirement:** The implemented VotingService auto-finalizes if approve ≥ 4 or reject ≥ 4. The epics describe only Director-controlled close + finalize. This behavior is undocumented in any story.
- **Impact:** Medium — if kept, it must be in Story 3.4 acceptance criteria and tested. If removed, the VotingService implementation needs to change.
- **Recommendation:** Explicitly document this behavior in Story 3.4 or remove it from the implementation to avoid silent divergence.

**Epic Gap 8: Reports Frontend Page**
- **Requirement:** Navigation includes `/reports` visible to CBY_ADMIN, SUPPORT_COMMITTEE, EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR, BANK_MANAGER. Story 4.5 covers the reports API but no story covers the frontend page.
- **Impact:** Low-medium — reports APIs are covered; frontend is the gap. Given docs/07 marks "Advanced reports" as MVP-excluded, basic reports may be acceptable.
- **Recommendation:** Explicitly label as MVP-excluded in epics, or add a minimal frontend story.

**Epic Gap 9: Settings & Profile Pages**
- **Requirement:** Navigation design includes `/settings` (CBY_ADMIN) and `/profile` (all users). No story covers these.
- **Impact:** Low — likely post-MVP, but should be explicitly noted.
- **Recommendation:** Explicitly exclude from MVP scope in epics document.

---

### Coverage Statistics

| Metric | Count |
|---|---|
| PRD FRs extracted (step 2) | 62 |
| Fully covered in epics | 54 |
| Partially covered | 3 (FR09, FR35, FR56) |
| Missing from epics | 5 (FR37, FR52, FR54, FR55, FR56) |
| **Coverage rate** | **87%** (54/62 fully) |
| Epic internal FRs (own numbering) | 91 |
| Epic internal coverage | 100% (91/91 mapped) |
| Critical gaps requiring action before implementation | 3 |
| High priority gaps requiring pre-implementation decision | 3 |
| Medium priority gaps | 3 |

---

---

## Epic Quality Review

### Epic Structure Validation

#### Epic 1: Foundation, Auth & Infrastructure

| Check | Result |
|---|---|
| User-centric title? | ⚠️ Partial — subtitle references infrastructure ("project skeleton") |
| Delivers user value standalone? | ✓ Yes — users can log in, access role-aware shell |
| Independent (no forward deps)? | ✓ Yes — foundational |
| Stories appropriately sized? | ✓ Yes — 4 stories, each distinct |

**Verdict:** Acceptable. Foundation epics follow BMAD convention. The mix of infrastructure + user-facing features is justified for Epic 1. Minor: "developers" as a user in Story 1.1 is a technical story pattern — acceptable for project scaffold.

---

#### Epic 2: Bank Workflow — Request Lifecycle

| Check | Result |
|---|---|
| User-centric? | ✓ Yes — DATA_ENTRY and BANK_REVIEWER deliver their complete workflow |
| Delivers value standalone? | ✓ Yes — bank-internal workflow is complete after this epic |
| Independent (only needs Epic 1)? | ✓ Yes |
| No forward dependencies to Epic 3+? | ⚠️ Partial — see Story issues below |

**Verdict:** Good. One story-level forward dependency concern (Story 2.6 references download permission logic defined in Epic 4, but manageable since backend enforces it).

---

#### Epic 3: CBY Operational Workflow — Support → SWIFT → Voting → Customs

| Check | Result |
|---|---|
| User-centric? | ✓ Yes — complete CBY workflow delivers full end-to-end value |
| Delivers value standalone? | ✓ Yes — CBY ops fully functional after this epic |
| Independent (needs Epics 1+2)? | ✓ Yes |
| No forward dependencies to Epic 4? | ✓ Yes |

**Verdict:** Good. Large but cohesive scope. The end-to-end CBY workflow is the right grouping.

---

#### Epic 4: System Completion — Audit, Documents, Dashboards & Polish

| Check | Result |
|---|---|
| User-centric? | ⚠️ Weak — "System Completion" is technical milestone language |
| Delivers value standalone? | ✓ Yes — audit visibility, download access, refined dashboards |
| Independent (needs Epics 1–3)? | ✓ Yes |
| No forward dependencies? | ✓ Yes |

**Verdict:** Acceptable. Title is weak ("completion" feels like a cleanup catch-all), but story content is coherent and delivers real user value. Recommend renaming to something like "Audit Trail, Document Access & CBY Admin Operations."

---

### Story Quality Assessment

#### 🔴 Critical Violations

**EQ-C1: Schema mismatch — Stories assume columns that don't exist in the current implementation**

Stories 2.3, 3.1, 3.3, 3.4, 3.6 set fields that are listed as missing from the currently implemented backend (per project-context.md §11):

| Story | Missing Column Required | Current State |
|---|---|---|
| Story 2.3 | `reviewed_by` (bank internal reviewer) | Not in schema |
| Story 3.1 | `support_reviewed_by` | Not in schema |
| Story 3.3 | `swift_uploaded_by`, `swift_uploaded_at` | Not in schema |
| Story 3.4 | `voting_opened_by`, `voting_opened_at`, `voting_closed_by`, `voting_closed_at`, `voting_session_status` | Not in schema |
| Story 3.6 | `customs_declaration_id` (FK on import_requests) | Not in schema |
| Story 3.4 | `AUTO_ABSTAIN_TIMEOUT` in `request_votes.vote` enum | DB enum missing this value |

**Impact:** These stories CANNOT be completed as specified without first adding these columns via migration. Story 1.1 is already done but left these columns unimplemented.

**Required Action:** Create a remediation story or include migration tasks at the start of Epics 2 and 3 to add missing columns. Alternatively, treat this as a known prerequisite at the start of each affected story.

---

**EQ-C2: Backend enum must be corrected before Epic 3 stories can be implemented**

Story 3.3 AC says status transitions to `WAITING_FOR_VOTING_OPEN`. Story 3.4 references `WAITING_FOR_VOTING_OPEN`, `EXECUTIVE_VOTING_OPEN`, `EXECUTIVE_VOTING_CLOSED`. But the implemented backend enum uses different names (`EXECUTIVE_VOTING` instead of `EXECUTIVE_VOTING_OPEN`, missing `EXECUTIVE_VOTING_CLOSED`, `WAITING_FOR_VOTING_OPEN`, etc.).

**Impact:** All Epic 3 stories will fail to implement correctly against the current backend enum. The backend enum MUST be reconciled with the canonical 18-value spec before Epic 3 implementation begins.

**Required Action:** Create a "Backend Enum Reconciliation" task as a prerequisite for Epic 3 — update `RequestStatus.php` to use canonical values, update all references (TransitionMap, controllers, queries, seeders).

---

#### 🟠 Major Issues

**EQ-M1: Story 1.4 — Field name conflict (name_ar/name_en vs single name)**

Story 1.4 AC specifies creating banks with `name_ar, name_en, code, status` fields. The implemented Story 1.4 backend uses a single `name` field. The AC and implementation are out of sync. The docs/03-database-and-models.md specifies `name_ar` and `name_en` separately.

**Required Action:** Before re-entering this story, align the bank schema. Either: (a) add `name_ar`/`name_en` columns and migrate existing data, or (b) formally accept the single `name` compromise and update Story 1.4 AC and docs/03 to match. This decision must be made explicitly.

---

**EQ-M2: Story 2.3 — `bank-review` endpoint missing from implemented backend**

Story 2.3 AC includes `POST /api/workflow/{id}/bank-review` (SUBMITTED → BANK_REVIEW transition). This endpoint does NOT exist in the implemented backend routes. The implemented backend jumps SUBMITTED directly to BANK_APPROVED/DRAFT_REJECTED_INTERNAL.

**Required Action:** Add the `bank-review` endpoint and WorkflowService transition to the backend as part of Story 2.3 implementation.

---

**EQ-M3: Story 2.5 — Form fields incomplete relative to approved UX**

Story 2.5 AC specifies: Currency, Amount, Supplier Name, Goods Description, Port of Entry, Notes.
The approved 4-step UX form requires additionally: Merchant selection, Payment Terms, Supplier Country, Invoice Number, Invoice Date.

No AC covers the 4-step wizard navigation (Next/Back buttons, step indicators, step validation).

**Required Action:** Update Story 2.5 AC to include all fields from the approved UX design. Consider whether the 4-step wizard is MVP or whether a simplified form is acceptable.

---

**EQ-M4: Story 3.1 — DELETE vs POST method conflict for claim release**

Story 3.1 AC specifies `DELETE /api/workflow/{id}/claim-support-review` for claim release. The implemented backend has `POST /api/workflow/{id}/support-release`.

**Required Action:** Align to one convention before Story 3.2 (frontend) is implemented. Recommendation: use `DELETE` per docs/06 specification.

---

**EQ-M5: Story 3.4 — Director override-and-finalize not in AC**

The implemented `VotingService::overrideAndFinalize()` and `POST /api/voting/{id}/override` endpoint are not covered by any story acceptance criteria. Story 3.5 shows no UI for this action.

**Required Action:** Add acceptance criteria to Story 3.4 for the override endpoint and to Story 3.5 for the Director override UI trigger.

---

**EQ-M6: Missing Merchant Management story — blocks Story 2.5**

Story 2.5 form Step 1 requires a merchant dropdown. Without merchant CRUD API and seed data, the form cannot be tested or completed. No story covers merchant management.

**Required Action:** Add Story 2.0 (or pre-2.5) for Merchant CRUD API + `/merchants` frontend page.

---

#### 🟡 Minor Concerns

**EQ-m1: Story 2.6 — SWIFT_OFFICER view of request detail not defined**

The detail page AC covers DATA_ENTRY and BANK_REVIEWER views. What does a SWIFT_OFFICER see when they open a request detail page (not the SWIFT upload page)? No AC covers this.

**Recommendation:** Add a brief AC for the SWIFT_OFFICER read-only view of request details.

---

**EQ-m2: Story 2.5 — "Save as draft" action not in AC**

The 4-step form design (Step 4) should have a "Save as Draft" option alongside "Submit." This isn't in the Story 2.5 AC.

**Recommendation:** Add AC for saving a partially-completed request as DRAFT without submitting.

---

**EQ-m3: Story 4.5 — Compliance alerts are undefined**

Story 4.5 AC mentions "a compliance alerts panel shows flagged issues (duplicate invoice numbers if detected)." No FR/NFR defines what constitutes a compliance alert, what triggers detection, or what the detection logic is.

**Recommendation:** Either remove the compliance alerts panel from Story 4.5 AC (descope as post-MVP) or add a clear specification for what triggers a compliance alert.

---

**EQ-m4: Story 1.2 — Lockout expiry not covered**

Story 1.2 AC covers lockout after 10 failures but doesn't cover the 15-minute auto-expiry (the account unlocking itself after 15 minutes).

**Recommendation:** Add AC: "After 15 minutes the lockout expires automatically and the user can attempt login again."

---

**EQ-m5: Story 4.1 — Title misleads about AuditService scope**

Story 4.1 is "AuditService, Stage History & Audit API" but AuditService is called by WorkflowService from Epic 2 onward. The service logging is actually wired in Stories 2.3, 3.1, etc. Story 4.1 covers the API access layer (GET endpoints + CBY_ADMIN access), not the core logging infrastructure.

**Recommendation:** Rename Story 4.1 to "Audit API & CBY Admin Audit Access" to clarify scope and avoid confusion about when AuditService logging is first wired.

---

### Dependency Analysis

**Within-Epic Dependencies (Acceptable)**

| Story | Depends On | Verdict |
|---|---|---|
| 1.2 | 1.1 (DB + schema) | ✓ Correct order |
| 1.3 | 1.1 + 1.2 (auth API) | ✓ Correct order |
| 1.4 | 1.1 + 1.2 | ✓ Correct order |
| 2.1–2.7 | 2.1 < 2.2 < 2.3, frontend after backend | ✓ Correct order |
| 3.1 before 3.2, 3.3, 3.4 before 3.5 | ✓ Backend before frontend pattern | ✓ Correct |
| 4.1–4.6 | Sequential audit → components → access | ✓ Correct |

**Cross-Epic Forward References (Minor)**

| Story | References | Verdict |
|---|---|---|
| Story 2.6 | Download rules "per FR77-82" (Epic 4) | ⚠️ Forward ref — manageable since backend enforces it; frontend just handles 403 |
| Story 2.7 | "Returned by Support count" (Epic 3) | ⚠️ Acceptable — count is 0 until Epic 3, non-blocking |
| Story 2.5 | Merchant dropdown (no story) | ❌ Missing prerequisite — merchant story needed |

**Database Table Creation**

Story 1.1 creates all 8 tables upfront. This deviates from the "create tables when first needed" best practice but is the BMAD-accepted pattern for greenfield foundation stories. The deviation is intentional and justified for this project. ✓

---

### Best Practices Compliance Checklist

| Epic | User Value | Independent | Stories Sized OK | No Forward Deps | DB Tables | Clear AC | FR Traceability |
|---|---|---|---|---|---|---|---|
| Epic 1 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Epic 2 | ✓ | ✓ | ✓ | ⚠️ Minor | ✓ | ⚠️ Story 2.5 incomplete | ✓ |
| Epic 3 | ✓ | ✓ | ✓ | ✓ | ❌ Missing cols | ⚠️ Stories 3.1/3.4 gaps | ✓ |
| Epic 4 | ✓ | ✓ | ✓ | ✓ | ✓ | ⚠️ Story 4.5 vague alerts | ✓ |

---

### Epic Quality Summary

| Severity | Count | Issues |
|---|---|---|
| 🔴 Critical | 2 | Missing schema columns (6 sets), Backend enum mismatch |
| 🟠 Major | 6 | name_ar/en conflict, bank-review endpoint, form fields, DELETE/POST conflict, override not in AC, no merchant story |
| 🟡 Minor | 5 | SWIFT_OFFICER detail view, save-as-draft, vague compliance alerts, lockout expiry, AuditService story title |

---

### PRD Completeness Assessment

The project documentation is **comprehensive and well-structured**. The `docs/` directory functions as a multi-file PRD with clear authority ordering. All major feature areas have formal specification. 

**Strengths:** Workflow state machine is exhaustively defined; RBAC matrix is complete; API contract is well-documented; design system is precise.

**Notable gaps in the PRD itself:** No explicit NFR for concurrent user load targets; no stated SLA for API response times; no formal data retention policy for audit logs; no defined migration path for the canonical enum mismatch between docs and implementation.

---

## Summary and Recommendations

### Overall Readiness Status

**⚠️ NEEDS WORK — Implementation can begin on Epics 1 and 2 (partially), but Epic 3+ has critical blockers that must be resolved first.**

The planning artifacts are of high quality overall. The specification is thorough, the workflow design is sound, and the epics cover the full product scope. However, 2 critical technical blockers and 6 major story-level issues must be addressed before the team can confidently implement later epics without rework.

---

### Critical Issues Requiring Immediate Action Before Epic 3

**BLOCKER 1: Backend enum mismatch must be resolved before Epic 3**

The implemented `RequestStatus.php` uses different values from the canonical 18-value enum (docs/AGENTS.md). Epic 3 stories write status transitions using canonical values (`WAITING_FOR_SWIFT`, `WAITING_FOR_VOTING_OPEN`, `EXECUTIVE_VOTING_OPEN`, `EXECUTIVE_VOTING_CLOSED`) that don't exist in the current backend.

*Action:* Update `RequestStatus.php` and all references (TransitionMap, controllers, queries, test seeders) to use the canonical enum before starting Story 3.1.

---

**BLOCKER 2: Missing schema columns must be added before affected stories**

6 sets of columns are defined in docs/03-database-and-models.md but missing from the current implementation. Stories 2.3 (reviewed_by), 3.1 (support_reviewed_by), 3.3 (swift_uploaded_by/at), 3.4 (voting_opened/closed tracking, voting_session_status), and 3.6 (customs_declaration_id) all set these fields in their acceptance criteria.

*Action:* Create a migration before beginning Story 2.3 that adds all missing columns to `import_requests` and the `AUTO_ABSTAIN_TIMEOUT` value to `request_votes.vote` enum.

---

### Recommended Next Steps (Priority Order)

**Immediate (before Story 2.2):**

1. **Add missing schema migration** — Add all missing `import_requests` columns (reviewed_by, support_reviewed_by, swift_uploaded_by, swift_uploaded_at, voting_opened_by, voting_opened_at, voting_closed_by, voting_closed_at, voting_session_status, final_decision_at, customs_declaration_id) and fix `request_votes.vote` enum to include `AUTO_ABSTAIN_TIMEOUT`.

2. **Decide bank name field convention** — Either add `name_ar`/`name_en` to the banks table (matching docs/03 and Story 1.4 AC) or formally update Story 1.4 AC and docs/03 to accept single `name`. Record the decision.

**Before Story 2.5 (Request Form):**

3. **Add Merchant Management story** — Create Story 2.0: Merchant CRUD API (`GET/POST/PUT /api/merchants`) and `/merchants` frontend page. This unblocks the request creation form's merchant dropdown.

4. **Update Story 2.5 AC** — Add the full field list from the approved 4-step UX form: merchant_id, payment_terms, supplier_country, invoice_number, invoice_date. Clarify whether a 4-step wizard or simplified single-form is acceptable for MVP.

**Before Epic 3:**

5. **Reconcile backend enum** — Update `RequestStatus.php` to 18 canonical values, update TransitionMap and all references.

6. **Resolve DELETE vs POST for claim release** — Implement `DELETE /api/workflow/{id}/claim-support-review` (per docs/06) and remove or deprecate `POST /api/workflow/{id}/support-release`. Update Story 3.1 AC to confirm final method.

**Story AC updates (can be done alongside implementation):**

7. **Story 2.3** — Add the `POST /api/workflow/{id}/bank-review` endpoint explicitly to the story scope.

8. **Story 3.4 + 3.5** — Add AC for `overrideAndFinalize()`: `POST /api/voting/{id}/override` endpoint (backend) and Director override button in the voting panel (frontend).

9. **Story 3.4** — Explicitly document the auto-finalize-at-4 behavior or remove it from the implementation.

10. **Story 1.2** — Add AC for lockout auto-expiry after 15 minutes.

**Post-Epic 3 (Epic 4 prep):**

11. **Scope decisions** — Explicitly mark as MVP-excluded (or create minimal stories for): Document type management, Settings page, Profile page, Reports frontend, Notifications. Update epics document.

12. **Story 4.5** — Remove or formally specify the compliance alerts panel. Define what triggers a compliance alert before implementing it.

---

### Issues Summary

| Category | Critical | Major | Minor | Total |
|---|---|---|---|---|
| Epic Coverage (FR gaps) | 3 | 3 | 0 | 6 |
| UX Alignment | 1 | 2 | 2 | 5 |
| Epic Quality | 2 | 6 | 5 | 13 |
| **Total** | **6** | **11** | **7** | **24** |

---

### What Is Already Well-Prepared

- **Specification quality:** The `docs/` directory and `project-context.md` provide exceptional engineering context. All workflow rules, RBAC, API contracts, and design tokens are precisely specified.
- **Epic internal FR coverage:** 91/91 epic FRs have epic assignments — no requirement is untracked within the epics document.
- **Architecture choices:** The Nuxt 4 + Laravel 11 + Sanctum + Redis stack is appropriate and all UX requirements are architecturally supportable.
- **Epic sequencing:** The 4-epic progression (Foundation → Bank Workflow → CBY Workflow → System Completion) is logical with correct dependency ordering.
- **Story AC quality:** Most acceptance criteria use proper Given/When/Then BDD format and cover happy path and error conditions.
- **Stories 1.1, 1.2, 1.3, 1.4** are already implemented and passing tests — the foundation is solid.

---

### Final Note

This assessment identified **24 issues** across 3 categories (coverage, UX alignment, epic quality). The 6 critical issues (2 technical blockers + 4 missing story coverage items) should be resolved before implementation of affected stories begins. The 11 major issues represent stories that will require rework or clarification mid-implementation if not addressed now. The 7 minor concerns can be addressed inline during implementation.

**Recommended approach:** Address critical blockers and major issues in a one-day "epic grooming" session before starting Story 2.3. Stories 1.x are already done and don't need revision. Stories 2.1–2.2 can proceed now. Story 2.3 onward requires the missing columns migration first.

---

*Assessment completed: 2026-05-15*  
*Assessor: BMAD Implementation Readiness Checker*  
*Report file: `_bmad-output/planning-artifacts/implementation-readiness-report-2026-05-15.md`*

---

## UX Alignment Assessment

### UX Document Status

**No standalone UX document found** in `_bmad-output/planning-artifacts/`. However, UX is comprehensively documented across distributed sources:

| UX Source | Coverage |
|---|---|
| `lovable/` React prototype | Approved operational UX baseline — screen structure, navigation, component hierarchy |
| `DESIGN.md` | Visual design system — colors, typography, layout, component sizing |
| `project-context.md §8` | Extracted UX patterns from Lovable (navigation, screens by role, key components) |
| `docs/04-frontend-guide.md` | Frontend architecture rules and UI guidelines |
| Epics `UX-DR1–UX-DR42` | 42 formal UX design requirements covering all screens and components |

**Assessment:** UX is fully specified, distributed rather than consolidated. The 42 UX-DRs in the epics effectively serve as the formal UX specification. No standalone UX file is required given this coverage.

---

### UX ↔ PRD Alignment Issues

**UX-A1 (Critical): 4-Step Form vs. Simple Form (Story 2.5)**

- The approved Lovable UX specifies a **4-step request creation wizard**: Step 1 (merchant, amount, currency, payment terms), Step 2 (supplier name/country, invoice number/date, port of entry), Step 3 (document upload), Step 4 (review + submit).
- Epic FR13 and Story 2.5 specify a **1-step form** with only: currency, amount, supplier_name, goods_description, port_of_entry, notes.
- **Gap:** The story acceptance criteria don't match the approved UX design. Developers implementing Story 2.5 will build the wrong thing.
- **Required fields missing from FR13/Story 2.5:** merchant selection, payment_terms, supplier_country, invoice_number, invoice_date.
- **Impact:** High — form implementation will be incomplete relative to UX. Requires AC update before Story 2.5 implementation.

**UX-A2 (High): Merchant Selection Requires Merchants API**

- UX Step 1 includes merchant selection (dropdown populated from `/api/merchants`). 
- The merchant management story is missing (Epic Gap 2 from coverage step).
- **Gap:** Without merchant data seeded and a merchants API, the form Step 1 cannot function at all.
- **Impact:** High — Story 2.5 cannot be completed without merchant data available. Merchant story must precede or accompany Story 2.5.

**UX-A3 (Medium): No Real-Time Updates — UX Degradation Acknowledged**

- UX-DR designs imply live data: claim state updates, vote tally refresh, queue count changes.
- Real-time updates (WebSockets/SSE) are **MVP-excluded** per docs/07.
- **Gap:** The UX implies responsive live state, but the implementation will require manual page refresh or polling for state changes.
- **Impact:** Medium — acceptable for MVP, but must be communicated to stakeholders. Queue counts and claim states may be stale until refreshed. Story-level implementation should include a "Refresh" trigger or periodic polling where critical (e.g., voting tally).

**UX-A4 (Medium): PDF Generation Performance — No Async Strategy**

- UX-DR32–33 show a customs declaration download button expecting an immediate response.
- DomPDF RTL PDF generation with Arabic text can take 2–10 seconds for complex documents.
- No queued/async PDF generation is specified in Story 3.6; the endpoint is synchronous.
- **Gap:** A slow synchronous PDF response will block the request thread and degrade UX. No loading state is specified in the acceptance criteria.
- **Impact:** Medium — functional but potentially poor UX for large PDFs. Recommend Story 3.6 AC include a loading state for the download button.

---

### UX ↔ Architecture Alignment

**UX-AR1 (Positive): Stack supports all UX requirements**

| UX Requirement | Architecture Support | Status |
|---|---|---|
| RTL-first layout (dir="rtl") | Nuxt 4 + Tailwind v4 `dir="rtl"` | ✓ Supported |
| IBM Plex Sans Arabic + Inter | `assets/fonts/` + CSS font-face | ✓ Supported |
| Right-aligned sidebar (264px, RTL) | Flex layout in AppShell | ✓ Supported |
| Status badges: icon + color | shadcn-vue Badge component | ✓ Supported |
| Voting panel touch targets (48px) | CSS min-height on buttons | ✓ Supported |
| Auto-claim on page mount | `onMounted` hook → claim API | ✓ Supported |
| Heartbeat composable (60s) | `useWorkflow.ts` + setInterval | ✓ Supported |
| Customs RTL PDF | barryvdh/laravel-dompdf | ✓ Supported |
| Table search + pagination | Backend query params + frontend state | ✓ Supported |
| Pulsing voting badge | CSS keyframe animation | ✓ Supported |

**UX-AR2 (Warning): AUTO_ABSTAIN_TIMEOUT display requires vote_source field**

- UX-DR27 requires AUTO_ABSTAIN_TIMEOUT votes to be visually distinct from ABSTAIN.
- This requires the frontend to distinguish `vote: ABSTAIN` from `vote: AUTO_ABSTAIN_TIMEOUT` in the voting roster.
- The `request_votes` DB table stores `vote` as an enum — the VoteType PHP enum has `AUTO_ABSTAIN_TIMEOUT` as a value, but the DB schema enum is `APPROVE, REJECT, ABSTAIN` only (missing AUTO_ABSTAIN_TIMEOUT — already noted as a backend gap in project-context.md §11).
- **Impact:** High — if AUTO_ABSTAIN_TIMEOUT cannot be stored in DB, it cannot be displayed distinctly in UX. Requires DB migration to add AUTO_ABSTAIN_TIMEOUT to the vote enum.

**UX-AR3 (Warning): Toast notification system — no story covers global notification setup**

- Story 2.5 mentions "a success toast notification is shown" but no story covers establishing the global toast/notification system.
- Architecture mentions `shadcn-vue` (which includes Toast component) but no Pinia store for global notifications is specified.
- **Impact:** Low — easily implemented inline, but should be acknowledged as infrastructure needed in Epic 1 or Story 1.3.

**UX-AR4 (Low): Claim state staleness on support queue**

- When Support Committee Member A claims a request, Member B's support queue table will not update unless they refresh.
- UX-DR36 shows "Claimed by [Name]" locked indicator — this requires polling or refresh to show correct state.
- **Impact:** Low for MVP — acceptable behavior with periodic refresh, but should be documented in Story 3.2.

---

### UX Alignment Summary

| Category | Count |
|---|---|
| Critical UX-PRD misalignments | 1 (form field scope) |
| High UX-Architecture gaps | 2 (merchant dependency, AUTO_ABSTAIN_TIMEOUT DB) |
| Medium warnings | 2 (real-time degradation, PDF async) |
| Low warnings | 2 (toast system, claim staleness) |
| UX requirements fully supported by architecture | All 42 UX-DRs structurally supportable |
