# Yemen Flow Hub — Project Context

> LLM-optimized implementation reference. Source of truth: `docs/` directory. Lovable prototype: UI/UX reference only (do NOT copy code).

---

## 1. Project Identity & Constraints

| Field | Value |
|---|---|
| Name | Yemen Flow Hub |
| Type | Internal government banking regulatory workflow platform |
| Client | Central Bank of Yemen (CBY) |
| Nature | NOT a SaaS app — enterprise-grade, audit-sensitive, institutional workflow platform |
| Users | Commercial banks + CBY staff + regulatory committees (no public access) |
| Language | Arabic-first, RTL default |
| Layout | Desktop-first; executive voting pages must work on tablets |
| File uploads | PDF only, private storage, immutable after upload |

**Non-negotiable constraints:**
- Arabic RTL is the default direction (`dir="rtl"`) — never an afterthought
- No shared admin dashboards — every view is queue-scoped and role-scoped
- Business logic belongs in services/composables/stores — never in controllers or Vue components
- Backend is the only authority for permissions and workflow rules
- All workflow status changes must go through `WorkflowService::transition()` — no direct model mutation
- Every workflow transition logs to BOTH `request_stage_history` AND `audit_logs`
- Customs declaration generation is a single database transaction
- Pessimistic locking (`lockForUpdate()`) required for vote submission and session closure

---

## 2. Repository & Tech Stack

### Repository Structure (3 repos)

| Repo | Remote | Tracks |
|---|---|---|
| Root monorepo | `git@github.com:majedsiefalnasr/yemen-flow-hub.git` | Everything |
| Backend team repo | `git@github.com:ultimate-eg/yemen-flow-hub-backend.git` | `backend/` only |
| Frontend team repo | `git@github.com:ultimate-eg/yemen-flow-hub-frontend.git` | `frontend/` only |

Every backend change: commit to backend team repo AND root monorepo.  
Every frontend change: commit to frontend team repo AND root monorepo.

### Backend Stack (`backend/`)
- PHP 8.2+, Laravel 11
- Laravel Sanctum (HTTP-only cookie auth + Bearer token for API clients)
- MySQL (primary DB)
- Redis (queues, cache, support claim TTL)
- barryvdh/laravel-dompdf (PDF generation)
- Queue Workers (Redis driver)
- REST API, service-oriented architecture

### Frontend Stack (`frontend/`) — TO BE BUILT
- Nuxt 4, Vue 4, TypeScript
- Tailwind CSS v4
- shadcn-vue
- Pinia (state management)
- VueUse
- VeeValidate + Zod (form validation)
- IBM Plex Sans Arabic + Inter (typography)
- RTL-first, Arabic-first

### What Exists vs. What Needs Building

| Area | Status |
|---|---|
| `backend/` | Significant implementation exists (see §4) |
| `frontend/` | EMPTY — only a stub `CLAUDE.md` exists |
| `lovable/` | React prototype (reference only, do NOT modify or copy) |
| `docs/` | Complete specification (source of truth) |

---

## 3. Canonical Enums

### RequestStatus (18 values — backend uses different names, see §4 gap note)

**Canonical values per docs (must be used in frontend and enforced in backend):**

| # | Value | Stage Description |
|---|---|---|
| 1 | `DRAFT` | Request created, editable |
| 2 | `DRAFT_REJECTED_INTERNAL` | Returned to Data Entry after bank/support rejection |
| 3 | `SUBMITTED` | Submitted to bank reviewer queue |
| 4 | `BANK_REVIEW` | Bank reviewer actively reviewing |
| 5 | `BANK_APPROVED` | Bank approved, in CBY support queue |
| 6 | `SUPPORT_REVIEW_PENDING` | Waiting for support committee claim |
| 7 | `SUPPORT_REVIEW_IN_PROGRESS` | Support committee member actively reviewing (claimed) |
| 8 | `SUPPORT_APPROVED` | Support approved, awaiting SWIFT upload |
| 9 | `SUPPORT_REJECTED` | Support rejected, in bank reviewer's queue |
| 10 | `WAITING_FOR_SWIFT` | (synonym for SUPPORT_APPROVED in some contexts) |
| 11 | `SWIFT_UPLOADED` | SWIFT uploaded, awaiting voting open |
| 12 | `WAITING_FOR_VOTING_OPEN` | Awaiting director to open voting session |
| 13 | `EXECUTIVE_VOTING_OPEN` | Active executive voting session |
| 14 | `EXECUTIVE_VOTING_CLOSED` | Voting closed, awaiting finalization |
| 15 | `EXECUTIVE_APPROVED` | Approved, awaiting customs declaration |
| 16 | `EXECUTIVE_REJECTED` | Terminal rejection — permanently locked |
| 17 | `CUSTOMS_DECLARATION_ISSUED` | Declaration issued |
| 18 | `COMPLETED` | Workflow complete |

> **CRITICAL GAP**: The implemented backend (`RequestStatus.php`) uses a different, non-canonical enum. See §11.

### UserRole (7 canonical values)

| Value | Description | bank_id |
|---|---|---|
| `DATA_ENTRY` | Bank data entry — creates/edits requests | Required |
| `BANK_REVIEWER` | Bank internal reviewer | Required |
| `SWIFT_OFFICER` | Bank SWIFT document uploader | Required |
| `SUPPORT_COMMITTEE` | CBY support committee reviewer | NULL |
| `EXECUTIVE_MEMBER` | CBY executive committee voter | NULL |
| `COMMITTEE_DIRECTOR` | CBY executive director — manages voting, issues customs | NULL |
| `CBY_ADMIN` | Full system visibility and management | NULL |

> Note: `COMMITTEE_DIRECTOR` inherits all `EXECUTIVE_MEMBER` permissions plus: open/close voting, resolve ties, finalize decisions, issue customs declarations.

### VoteType Enum

| Value | Description |
|---|---|
| `APPROVE` | Affirmative vote |
| `REJECT` | Negative vote |
| `ABSTAIN` | Manual abstention |
| `AUTO_ABSTAIN_TIMEOUT` | Auto-assigned on session closure to non-voters — distinct from ABSTAIN |

### WorkflowAction Enum (from implemented code)

`SUBMIT`, `BANK_APPROVE`, `BANK_REJECT`, `RETURN_TO_DATA_ENTRY`, `SUPPORT_APPROVE`, `SUPPORT_REJECT`, `UPLOAD_SWIFT`, `START_EXECUTIVE_VOTING`, `EXECUTIVE_APPROVE`, `EXECUTIVE_REJECT`, `ISSUE_CUSTOMS`, `COMPLETE`

---

## 4. What the Backend Already Implements

### Services

| Service | File | What It Does |
|---|---|---|
| `WorkflowService` | `app/Services/Workflow/WorkflowService.php` | All state transitions via `transition($request, $action, $actor, $reason, $metadata)`. Validates from-status, role, org scope, self-review, claim ownership. Creates `RequestStageHistory`, fires `RequestTransitioned` event, calls `AuditService`. **Auto-chains swift_upload → executive_voting** inline. |
| `TransitionMap` | `app/Services/Workflow/TransitionMap.php` | Static `definitions()` array — maps action → from[], to, roles[], next_owner |
| `VotingService` | `app/Services/Voting/VotingService.php` | `castVote()`, `tally()`, `finalize()` (tie-break), `overrideAndFinalize()`. Auto-finalizes if approve≥4 or reject≥4 after each vote. |
| `AuditService` | `app/Services/Audit/AuditService.php` | `log($action, $actor, $subject, $metadata)` — writes to `audit_logs` with IP, user agent |
| `DocumentService` | `app/Services/Documents/DocumentService.php` | `uploadRequestDocument()`, `uploadSwift()`, `download()`, `delete()`. Swift upload triggers `swift_upload` workflow transition. |
| `CustomsService` | `app/Services/Customs/CustomsService.php` | `generate()` — creates PDF via DomPDF, saves to `private/customs/{id}/`, creates `CustomsDeclaration` record, triggers `issue_customs` then `complete` transitions. `getPdfStream()` for download. |

### Models Implemented

- `ImportRequest` — has `scopeForUser()` (bank scoping), `isEditable()`, `isClaimed()`, `isClaimedBy()`. Auto-generates `reference_number` as `YFH-{YEAR}-{NNNNNN}`.
- `User` — has `hasRole()`, `isBankUser()`, `isCbyUser()`, `hasPermission()` (delegates to `PermissionService`)
- `RequestVote` — has `is_director_override` boolean
- `Bank`, `Merchant`, `RequestDocument`, `RequestStageHistory`, `AuditLog`, `CustomsDeclaration`, `DocumentType`, `Permission`

### Controllers & Endpoints Implemented

See §9 for full route table. Key controllers:
- `AuthController` — login (cookie + token modes), logout, me
- `ImportRequestController` — full CRUD + history; support claim filter (`?claim_filter=all|available|mine`)
- `WorkflowController` — all workflow actions (thin, delegates to WorkflowService)
- `VotingController` — index, show, vote, director-decide, override
- `DocumentController` — uploadRequestDocument, uploadSwift, download, destroy
- `CustomsController` — generate, show, download
- `AuditController`, `BankController`, `UserController`, `MerchantController`, `DashboardController`, `ReportController`, `NotificationController`

### What Backend Does NOT Implement Yet

See §11 (Backend Gaps) for the complete list.

---

## 5. Database Schema Summary

### banks
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string unique | (no name_ar/name_en split — docs differ) |
| code | string unique | |
| is_active | boolean | default true |

> Gap vs. docs/03: Docs specify `name_ar` and `name_en` separately; implementation uses single `name`.

### users
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| bank_id | bigint FK nullable | NULL for CBY roles |
| name | string | |
| email | string unique | |
| password | string hashed | |
| role | string enum | UserRole values |
| is_active | boolean | |
| last_login_at | timestamp nullable | |

### import_requests
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| reference_number | string unique | Auto: `YFH-{YEAR}-{NNNNNN}` |
| bank_id | bigint FK | |
| merchant_id | bigint FK nullable | Added in later migration |
| created_by | bigint FK → users | |
| currency | string(3) | USD/EUR/SAR/AED/CNY |
| amount | decimal(18,2) | |
| supplier_name | string | |
| goods_description | text | |
| port_of_entry | string | |
| notes | text nullable | |
| status | string | RequestStatus enum value |
| current_owner_role | string | UserRole enum value |
| claimed_by | bigint FK nullable | Active support reviewer |
| claimed_at | timestamp nullable | |
| claim_expires_at | timestamp nullable | |
| submitted_at | timestamp nullable | |
| bank_approved_at | timestamp nullable | |
| support_approved_at | timestamp nullable | |
| swift_uploaded_at | timestamp nullable | |
| executive_decided_at | timestamp nullable | |
| customs_issued_at | timestamp nullable | |
| revision_count | unsigned int | default 0 |
| deleted_at | timestamp nullable | SoftDeletes |

> Gap vs. docs/03: Missing many actor-tracking columns defined in docs (submitted_by, reviewed_by, rejected_by, resubmitted_by, support_reviewed_by, swift_uploaded_by, voting_opened_by, voting_closed_by, voting_session_status). Also missing `voting_opened_at`, `voting_closed_at`, `final_decision_at`, `customs_declaration_id` FK.

### request_stage_history
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| request_id | bigint FK | |
| from_status | string nullable | |
| to_status | string | |
| from_owner_role | string nullable | |
| to_owner_role | string nullable | |
| actor_id | bigint FK → users | |
| actor_role | string | Role at time of action |
| action | string | Workflow action name |
| reason | text nullable | |
| metadata | json nullable | |

### audit_logs
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK nullable | NULL for unauthenticated |
| user_role | string nullable | Role at time of action |
| action | string | AuditAction enum value |
| subject_type | string nullable | Model class |
| subject_id | bigint nullable | |
| ip_address | string(45) nullable | |
| user_agent | string nullable | |
| metadata | json nullable | |
| created_at | timestamp | |

> Gap vs. docs/03: Implementation uses `subject_type`/`subject_id` (polymorphic) vs. docs' `entity_type`/`entity_id`. Also missing explicit `from_status`/`to_status` columns on audit_logs — these are stored in `metadata` JSON instead.

### request_documents
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| request_id | bigint FK | |
| uploaded_by | bigint FK → users | |
| type | enum | REQUEST_DOC / SWIFT / CUSTOMS |
| original_filename | string | |
| stored_path | string | Relative path under `private/` |
| mime_type | string | |
| size_bytes | bigint | |
| document_type_id | bigint FK nullable | Added in later migration for configurable doc types |

### request_votes
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| request_id | bigint FK | |
| user_id | bigint FK | |
| vote | enum | APPROVE / REJECT / ABSTAIN |
| justification | text nullable | |
| is_director_override | boolean | default false |
| unique(request_id, user_id) | | One vote per member |

> Gap: `AUTO_ABSTAIN_TIMEOUT` is not in the DB enum — only in VoteType PHP enum. This means auto-abstain votes cannot be stored without a migration change.

### customs_declarations
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| request_id | bigint FK unique | One declaration per request |
| declaration_number | string unique | Auto: `CD-{YEAR}-{NNNNNN}` |
| issued_by | bigint FK → users | |
| issued_at | timestamp | |
| pdf_path | string | Relative path |
| metadata | json nullable | Snapshot of request data at issuance |

### Other Tables
- `merchants` — name, tax_number, commercial_register, address, contact, category, status, bank_id
- `document_types` — configurable document type definitions
- `permissions` + `role_permissions` — RBAC permission tables
- `notifications` — Laravel notifications
- `cache`, `jobs`, `sessions`, `personal_access_tokens` — framework tables

---

## 6. Workflow State Machine

### Complete Transition Table

| Action (in code) | From Status(es) | To Status | Allowed Roles | Notes |
|---|---|---|---|---|
| `submit` | DRAFT, RETURNED_TO_DATA_ENTRY | SUBMITTED | DATA_ENTRY, BANK_MANAGER | |
| `bank_approve` | SUBMITTED | BANK_APPROVED | BANK_REVIEWER, BANK_MANAGER | Self-review blocked (actor ≠ created_by) |
| `bank_reject` | SUBMITTED | BANK_REJECTED | BANK_REVIEWER, BANK_MANAGER | Reason required |
| `return_to_entry` | SUBMITTED, SUPPORT_REJECTED, EXECUTIVE_REJECTED | RETURNED_TO_DATA_ENTRY | BANK_REVIEWER, BANK_MANAGER | Reason required; increments revision_count |
| `support_claim` | BANK_APPROVED, SUPPORT_UNDER_REVIEW | SUPPORT_UNDER_REVIEW | SUPPORT_COMMITTEE | Soft-lock with TTL; sets claimed_by, claimed_at, claim_expires_at |
| `support_release` | SUPPORT_UNDER_REVIEW | BANK_APPROVED | SUPPORT_COMMITTEE | Clears claim fields |
| `support_approve` | SUPPORT_UNDER_REVIEW | SUPPORT_APPROVED | SUPPORT_COMMITTEE | Must hold active claim |
| `support_reject` | SUPPORT_UNDER_REVIEW | SUPPORT_REJECTED | SUPPORT_COMMITTEE | Must hold active claim; reason required |
| `swift_upload` | SUPPORT_APPROVED | SWIFT_UPLOADED → EXECUTIVE_VOTING (auto-chained) | SWIFT_OFFICER, BANK_MANAGER | Auto-chains immediately to EXECUTIVE_VOTING |
| `start_voting` | SWIFT_UPLOADED | EXECUTIVE_VOTING | EXECUTIVE_MEMBER | (In TransitionMap but auto-chained from swift_upload) |
| `finalize_approved` | EXECUTIVE_VOTING | EXECUTIVE_APPROVED | EXECUTIVE_DIRECTOR | Via VotingService auto or director action |
| `finalize_rejected` | EXECUTIVE_VOTING | EXECUTIVE_REJECTED | EXECUTIVE_DIRECTOR | Terminal — permanent |
| `issue_customs` | EXECUTIVE_APPROVED | CUSTOMS_ISSUED | EXECUTIVE_DIRECTOR | Called by CustomsService |
| `complete` | CUSTOMS_ISSUED | COMPLETED | EXECUTIVE_DIRECTOR | Called by CustomsService after issue_customs |

> **Implementation vs. Docs discrepancy**: The implemented `TransitionMap` uses different enum values than the canonical docs. See §11 for details.

### Claim TTL Rules
- TTL: configured via `workflow.support_claim_ttl_hours` (docs spec: 15 min of inactivity; implementation: hours-based config)
- Heartbeat endpoint documented in `docs/06`: `POST /api/workflow/{id}/claim-support-review/heartbeat` — NOT yet implemented in routes
- Release: `POST /api/workflow/{id}/support-release` (implemented, uses POST not DELETE contrary to docs/06)
- Redis key: `support_claim:{request_id}` (per AGENTS.md spec)

### Voting Decision Logic (VotingService)
- Cast vote → auto-finalize if approve ≥ 4 OR reject ≥ 4 (6-member committee)
- Tie (3:3 when all 6 voted) → director must call `finalize()` with tie-break vote
- Director override: `overrideAndFinalize()` — requires justification; can override any time during voting
- `AUTO_ABSTAIN_TIMEOUT`: assigned to non-voters when session closes (not yet implemented in routes)

### Editable States
Only `DRAFT` and `RETURNED_TO_DATA_ENTRY` (code: `RETURNED_TO_DATA_ENTRY`) allow editing.

### Terminal/Immutable States
`EXECUTIVE_REJECTED`, `CUSTOMS_ISSUED`, `COMPLETED` → return HTTP 403 `WORKFLOW_IMMUTABLE_STATE` on any mutation attempt.

---

## 7. RBAC & Visibility Matrix

### Per-Role Access Summary

| Role | Sees | Can Do | Queue Focus |
|---|---|---|---|
| `DATA_ENTRY` | All bank requests | Create, edit (editable states), delete (DRAFT only), submit | Drafts, returned, submitted |
| `BANK_REVIEWER` | All bank requests | Bank approve/reject, return to entry (incl. after support reject), monitor downstream | Submitted queue, support-rejected queue |
| `SWIFT_OFFICER` | Bank requests in SUPPORT_APPROVED | Upload SWIFT | SUPPORT_APPROVED queue |
| `SUPPORT_COMMITTEE` | All CBY requests | Claim, release, approve, reject (only own claimed) | BANK_APPROVED queue, claimed reviews |
| `EXECUTIVE_MEMBER` | All CBY requests in voting | Vote (APPROVE/REJECT/ABSTAIN) | EXECUTIVE_VOTING queue |
| `COMMITTEE_DIRECTOR` | All CBY requests | All EXECUTIVE_MEMBER actions + open voting, close voting, finalize, issue customs | Voting management, finalized decisions |
| `CBY_ADMIN` | ALL requests all banks | Read-only operational visibility + user/bank management | Global |

### Organization Scoping
- Bank users (`DATA_ENTRY`, `BANK_REVIEWER`, `SWIFT_OFFICER`, `BANK_MANAGER`): always filtered to `bank_id` match
- CBY users: see all requests
- Enforcement: `ImportRequest::scopeForUser()` at Eloquent query level + `ImportRequestPolicy::view()`

### Document Download Permission Matrix

| Role | Request Docs | SWIFT Doc | Customs PDF |
|---|---|---|---|
| DATA_ENTRY | Own bank only | No | No |
| BANK_REVIEWER | Own bank only | Own bank only | Own bank only |
| SWIFT_OFFICER | Own bank only | Own bank only | No |
| SUPPORT_COMMITTEE | All banks | No | No |
| EXECUTIVE_MEMBER | All banks | Yes | No |
| COMMITTEE_DIRECTOR | All banks | Yes | Yes |
| CBY_ADMIN | All banks | Yes | Yes |

### Self-Review Prohibition
`bank_approve` action: if `actor.id === request.created_by` → throws `SelfReviewException`.

### Support Claim Exclusivity
Only the claim holder can `support_approve` or `support_reject`. Other support users can view but cannot act.

---

## 8. Approved UI/UX (Lovable Prototype Reference)

> Lovable is a React/TanStack Router prototype. The approved UI patterns, navigation structure, and visual design are extracted here. Do NOT copy Lovable code — build in Nuxt 4/Vue 4 following these patterns.

### Navigation Structure (from AppShell.tsx)

**Right-side sidebar** (RTL), 264px fixed width, no collapse in production design (Lovable has collapse button, production DESIGN.md says no collapsible):

| Nav Item (Arabic) | Route | Visible To |
|---|---|---|
| اللوحة الرئيسية | `/` | All |
| طلبات التمويل | `/requests` | All |
| تقديم طلب جديد | `/requests/new` | DATA_ENTRY, BANK_MANAGER |
| إدارة التجار | `/merchants` | BANK_MANAGER, CBY_ADMIN |
| البيان الجمركي | `/customs` | COMMITTEE_DIRECTOR |
| التقارير والتحليلات | `/reports` | CBY_ADMIN, SUPPORT, EXECUTIVE, COMMITTEE_DIRECTOR, BANK_MANAGER |
| التدقيق والامتثال | `/audit` | CBY_ADMIN |
| الإشعارات | `/notifications` | All |
| إدارة البنوك | `/admin/entities` | CBY_ADMIN |
| مستخدمي النظام | `/admin/cby-staff` | CBY_ADMIN |
| قواعد المستندات | `/admin/workflow-docs` | CBY_ADMIN |
| الأدوار والصلاحيات | `/admin/roles` | CBY_ADMIN |
| موظفو الجهة | `/bank/users` | BANK_MANAGER |
| إعدادات النظام | `/settings` | CBY_ADMIN |

### Screens by Role

**DATA_ENTRY:**
- Dashboard: KPI cards (draft count, submitted, returned), quick actions (new request), recent requests table
- `/requests` — filtered to bank, status badges, business-status display (not raw CBY internals)
- `/requests/new` — 4-step wizard: بيانات الطلب → بيانات المورد والشحنة → الوثائق المطلوبة → المراجعة والإرسال
- `/requests/{id}` — request detail with workflow progress rail, locked banner when non-editable

**BANK_REVIEWER:**
- Dashboard: KPIs (pending review, support-rejected), submitted queue, workflow tracking
- `/requests` — all bank requests with full status visibility
- `/requests/{id}` — approve/reject/return-to-entry actions, workflow progress

**SWIFT_OFFICER:**
- Dashboard: SUPPORT_APPROVED queue
- `/requests/{id}/swift` — dedicated SWIFT upload screen (separate route in Lovable)

**SUPPORT_COMMITTEE:**
- Dashboard: pending support queue with claim actions, claimed reviews
- `/requests/{id}` — claim action (auto-claim on page open in Lovable prototype), approve/reject when claimed, claim indicator when claimed by other

**EXECUTIVE_MEMBER + COMMITTEE_DIRECTOR:**
- Dashboard: voting queue, finalized decisions
- `/requests/{id}` — VotingPanel component: vote counts, member roster, approve/reject/abstain buttons
- Director: session open/close controls, director-decide (tie-break), override-and-finalize

**CBY_ADMIN:**
- Dashboard: global stats
- `/admin/entities` — bank management
- `/admin/cby-staff` — CBY user management
- `/bank/users` — bank user management (for BANK_MANAGER)
- `/audit` — audit log viewer

### Key UI Components (from Lovable — to be rebuilt in Vue/shadcn-vue)

| Component | File | Purpose |
|---|---|---|
| AppShell | `layout/AppShell.tsx` | RTL layout wrapper, sidebar, header with search + notifications + user menu |
| WorkflowProgress | `workflow/WorkflowProgress.tsx` | Vertical rail showing workflow steps, current stage highlighted, completed/pending states |
| VotingPanel | `workflow/VotingPanel.tsx` | Executive voting interface with tally bars, member list, vote buttons, director controls |
| AuditTimeline | `workflow/AuditTimeline.tsx` | Horizontal timeline of workflow actions and actors |
| LockedBanner | `workflow/LockedBanner.tsx` | Banner shown when request is in read-only state |
| DocumentChecklist | `workflow/DocumentChecklist.tsx` | Document list with upload/download actions |
| RoleGuard | `workflow/RoleGuard.tsx` | Conditional rendering based on user role |

### Request Detail Page Structure
Tabs: `التفاصيل` | `المستندات` | `سير العملية` | `التصويت` (if applicable) | `سجل الأحداث`

Key fields displayed:
- Reference number, bank name, merchant, amount+currency, supplier, goods description, port of entry
- Current status badge (semantic color)
- Workflow progress rail (right-aligned in RTL)
- Actor tracking: created_by, submitted_by, internal_reviewer, support_reviewer, swift_uploaded_by
- Documents section with download links
- Voting panel (executive stages only)
- Audit timeline

### New Request Form (4 Steps)
1. بيانات الطلب — merchant selection, amount, currency, payment terms
2. بيانات المورد والشحنة — supplier name, country, invoice number/date, port of entry
3. الوثائق المطلوبة — PDF upload with checklist
4. المراجعة والإرسال — summary + submit or save as draft

### Approved Visual Patterns
- RTL sidebar on RIGHT side
- Search bar in header (global)
- Notification bell with badge in header
- User menu dropdown in header
- Role label displayed under user name
- Status badges: pill-shaped, icon + label
- Cards: no shadow-heavy, clean borders
- Tables: no zebra striping, status badge always visible
- Locked states: `LockedBanner` with gray overlay + lock icon
- Workflow rail: vertical, right-aligned (RTL), numbered nodes

---

## 9. API Contract Summary

### Auth Routes (public: login; rest: auth:sanctum)

| Method | Path | Controller | Notes |
|---|---|---|---|
| POST | `/api/auth/login` | AuthController@login | Cookie or Bearer token mode |
| POST | `/api/auth/logout` | AuthController@logout | |
| GET | `/api/auth/me` | AuthController@me | |

### Requests Routes

| Method | Path | Controller | Notes |
|---|---|---|---|
| GET | `/api/requests` | ImportRequestController@index | Filter: status, bank_id (CBY only), search, from_date, to_date, claim_filter (support) |
| POST | `/api/requests` | ImportRequestController@store | Creates DRAFT |
| GET | `/api/requests/{id}` | ImportRequestController@show | |
| PUT | `/api/requests/{id}` | ImportRequestController@update | Editable states only |
| DELETE | `/api/requests/{id}` | ImportRequestController@destroy | DRAFT only |
| GET | `/api/requests/{id}/history` | ImportRequestController@history | Stage history |
| POST | `/api/requests/{id}/documents` | DocumentController@uploadRequestDocument | Editable states only |

### Workflow Routes

| Method | Path | Action |
|---|---|---|
| POST | `/api/workflow/{id}/submit` | submit |
| POST | `/api/workflow/{id}/bank-approve` | bank_approve |
| POST | `/api/workflow/{id}/bank-reject` | bank_reject (reason required) |
| POST | `/api/workflow/{id}/return-to-entry` | return_to_entry (reason required) |
| POST | `/api/workflow/{id}/support-claim` | support_claim |
| POST | `/api/workflow/{id}/support-release` | support_release |
| POST | `/api/workflow/{id}/support-approve` | support_approve |
| POST | `/api/workflow/{id}/support-reject` | support_reject (reason required) |
| POST | `/api/workflow/{id}/swift-upload` | swift_upload (multipart PDF) |
| POST | `/api/workflow/{id}/finalize-decision` | finalize_approved or finalize_rejected (body: `decision: "approve"|"reject"`) |

### Voting Routes

| Method | Path | Notes |
|---|---|---|
| GET | `/api/voting` | EXECUTIVE_VOTING queue (executive roles only) |
| GET | `/api/voting/{id}` | Detail with tally and my_vote |
| POST | `/api/voting/{id}/vote` | `{ vote: "APPROVE"|"REJECT", justification? }` |
| POST | `/api/voting/{id}/director-decide` | Tie-break `{ vote: "APPROVE"|"REJECT" }` |
| POST | `/api/voting/{id}/override` | Director override `{ decision, justification }` |

### Documents Routes

| Method | Path | Notes |
|---|---|---|
| GET | `/api/documents/{id}/download` | Streamed PDF |
| DELETE | `/api/documents/{id}` | REQUEST_DOC only, editable state |

### Customs Routes

| Method | Path | Notes |
|---|---|---|
| POST | `/api/customs/{id}/generate` | EXECUTIVE_APPROVED only, COMMITTEE_DIRECTOR only |
| GET | `/api/customs/{id}` | Declaration metadata |
| GET | `/api/customs/{id}/download` | Streamed PDF |

### Other Routes

| Method | Path | Notes |
|---|---|---|
| GET/POST/PUT/DELETE | `/api/banks` | apiResource |
| GET/POST/PUT/DELETE | `/api/users` | apiResource |
| GET/POST/PUT/DELETE | `/api/merchants` | apiResource |
| GET/POST/PUT/DELETE | `/api/document-types` | |
| GET | `/api/audit` | AuditController |
| GET | `/api/notifications` | |
| POST | `/api/notifications/{id}/read` | |
| POST | `/api/notifications/read-all` | |
| GET | `/api/dashboard/stats` | Role-scoped operational stats |
| GET | `/api/reports/workflow` | |
| GET | `/api/reports/voting` | |

### Discrepancies vs. docs/06

| docs/06 Spec | Actual Implementation |
|---|---|
| `POST /api/workflow/{id}/bank-return-after-support-reject` | `POST /api/workflow/{id}/return-to-entry` (merged, also handles SUBMITTED returns) |
| `POST /api/workflow/{id}/bank-finalize-rejection` | Not implemented |
| `DELETE /api/workflow/{id}/claim-support-review` | `POST /api/workflow/{id}/support-release` (POST not DELETE) |
| `POST /api/workflow/{id}/claim-support-review/heartbeat` | Not implemented |
| `POST /api/voting/{id}/open` | Not implemented |
| `POST /api/voting/{id}/close` | Not implemented (voting auto-closes via finalize logic) |
| `GET /api/audit` | Implemented |

### Standard Response Formats

**Success:**
```json
{ "success": true, "message": "...", "data": {} }
```

**Error:**
```json
{ "success": false, "message": "...", "error_code": "WORKFLOW_IMMUTABLE_STATE", "current_status": "..." }
```

**Immutable state:** HTTP 403 + `WORKFLOW_IMMUTABLE_STATE`  
**Locked (non-editable):** HTTP 422 + `WORKFLOW_LOCKED_STATE`  
**Voting session closed:** HTTP 422 + `VOTING_SESSION_CLOSED`

---

## 10. Frontend Build Scope

`frontend/` is empty (only a CLAUDE.md stub). Must build from scratch: Nuxt 4 app that replicates Lovable's approved UX with correct business logic from docs.

### Directory Structure to Create

```
frontend/
├── app/
├── assets/
│   └── fonts/         (IBM Plex Sans Arabic, Inter)
├── components/
│   ├── ui/            (shadcn-vue components)
│   ├── workflow/      (WorkflowProgress, VotingPanel, AuditTimeline, LockedBanner)
│   ├── forms/
│   ├── dashboard/
│   ├── tables/
│   └── layout/        (AppShell, Sidebar, Header)
├── composables/
│   ├── useAuth.ts
│   ├── usePermissions.ts
│   ├── useWorkflow.ts
│   ├── useVoting.ts
│   └── useApi.ts
├── layouts/
│   ├── auth.vue
│   ├── dashboard.vue
│   └── print.vue
├── middleware/
│   ├── auth.ts
│   ├── guest.ts
│   └── role.ts
├── pages/
│   ├── login.vue
│   ├── index.vue              (dashboard)
│   ├── requests/
│   │   ├── index.vue          (list)
│   │   ├── new.vue            (create form — DATA_ENTRY only)
│   │   └── [id]/
│   │       ├── index.vue      (detail)
│   │       └── swift.vue      (SWIFT upload — SWIFT_OFFICER only)
│   ├── voting/
│   │   └── index.vue
│   ├── customs/
│   │   ├── index.vue
│   │   └── [id]/
│   │       ├── index.vue
│   │       └── print.vue      (RTL PDF print view)
│   ├── admin/
│   │   ├── entities.vue       (banks — CBY_ADMIN)
│   │   ├── cby-staff.vue      (CBY users — CBY_ADMIN)
│   │   ├── workflow-docs.vue  (doc types — CBY_ADMIN)
│   │   └── roles.vue
│   ├── bank/
│   │   └── users.vue          (bank users — BANK_MANAGER)
│   ├── audit.vue
│   ├── reports.vue
│   ├── notifications.vue
│   ├── profile.vue
│   └── settings.vue
├── services/
│   ├── api/              (base axios/fetch setup)
│   ├── auth/
│   ├── requests/
│   ├── voting/
│   └── workflow/
├── stores/
│   ├── auth.store.ts
│   ├── requests.store.ts
│   ├── workflow.store.ts
│   └── voting.store.ts
├── types/
│   ├── enums.ts          (RequestStatus, UserRole, VoteType — must match canonical)
│   ├── models.ts
│   └── api.ts
└── constants/
    └── workflow.ts       (status → display label mappings, including Data Entry simplified view)
```

### Feature Areas to Build

| Feature | Priority | Key Rules |
|---|---|---|
| Auth + route protection | P0 | Sanctum cookie mode, role hydration, redirect by role |
| RTL layout + sidebar | P0 | Right-side sidebar, dir="rtl", IBM Plex Sans Arabic |
| Request list (scoped) | P0 | Organization-scoped, role-filtered queues, status badges |
| Request detail page | P0 | Tabs, workflow rail, locked states, actor tracking display |
| New request form (4-step) | P0 | DATA_ENTRY only, VeeValidate + Zod, PDF upload |
| Workflow actions UI | P0 | Conditional buttons per role + status, confirmation dialogs |
| Support claim UI | P1 | Auto-claim on page open, locked indicator for others, heartbeat |
| SWIFT upload screen | P1 | SWIFT_OFFICER only, SUPPORT_APPROVED status only |
| Voting panel | P1 | Executive roles, tally display, director controls |
| Customs declaration | P1 | COMMITTEE_DIRECTOR only, RTL PDF print view |
| Dashboard per role | P1 | Queue-oriented KPIs, no analytics charts |
| Audit timeline | P2 | Stage history display, user + timestamp |
| Notifications | P2 | Bell badge, notification list |
| Admin screens | P2 | Users, banks, document types (CBY_ADMIN) |
| Reports | P3 | CBY roles only |

### Business Status Mapping for Data Entry View

| Internal Status | Data Entry Sees |
|---|---|
| DRAFT | مسودة (Draft) |
| DRAFT_REJECTED_INTERNAL / RETURNED_TO_DATA_ENTRY | معاد للتعديل (Returned for Correction) |
| SUBMITTED / BANK_REVIEW | مقدّم للمراجعة (Submitted) |
| BANK_APPROVED → SWIFT_UPLOADED | قيد معالجة CBY (Under CBY Processing) |
| SUPPORT_REJECTED | مرفوض (Rejected) |
| EXECUTIVE_REJECTED | مرفوض نهائياً (Permanently Rejected) |
| EXECUTIVE_APPROVED / CUSTOMS_DECLARATION_ISSUED / COMPLETED | مكتمل (Completed) |

---

## 11. Backend Gaps

### Critical: Enum Mismatch

The implemented `RequestStatus.php` uses DIFFERENT enum values from the canonical docs:

| Canonical (docs/AGENTS.md) | Implemented Backend | Gap |
|---|---|---|
| `DRAFT_REJECTED_INTERNAL` | `RETURNED_TO_DATA_ENTRY` | Different name |
| `BANK_REVIEW` | Not present | Missing |
| `BANK_APPROVED` (CBY queue) | `BANK_APPROVED` | OK |
| `SUPPORT_REVIEW_PENDING` | Not present | Missing |
| `SUPPORT_REVIEW_IN_PROGRESS` | `SUPPORT_UNDER_REVIEW` | Different name (combined) |
| `SUPPORT_APPROVED` | `SUPPORT_APPROVED` | OK |
| `WAITING_FOR_SWIFT` | Not separate | Merged with SUPPORT_APPROVED |
| `SWIFT_UPLOADED` | `SWIFT_UPLOADED` | OK |
| `WAITING_FOR_VOTING_OPEN` | Not present | Missing |
| `EXECUTIVE_VOTING_OPEN` | `EXECUTIVE_VOTING` | Different name (collapsed) |
| `EXECUTIVE_VOTING_CLOSED` | Not present | Missing |
| `EXECUTIVE_APPROVED` | `EXECUTIVE_APPROVED` | OK |
| `EXECUTIVE_REJECTED` | `EXECUTIVE_REJECTED` | OK |
| `CUSTOMS_DECLARATION_ISSUED` | `CUSTOMS_ISSUED` | Different name |
| `COMPLETED` | `COMPLETED` | OK |
| `BANK_REJECTED` | `BANK_REJECTED` | Extra (not in canonical) |

> **Backend enum must be reconciled with canonical spec.** Frontend must use canonical values; backend must be updated to match.

### Missing Enum Value
`AUTO_ABSTAIN_TIMEOUT` is defined in `VoteType.php` as a PHP enum case but is NOT in the database `vote` column enum (`APPROVE, REJECT, ABSTAIN` only). A migration is required to add it.

### Missing API Endpoints (vs. docs/06)

| Missing Endpoint | Priority |
|---|---|
| `POST /api/workflow/{id}/claim-support-review/heartbeat` | High — required for claim TTL extension |
| `POST /api/voting/{id}/open` | High — director opens voting session |
| `POST /api/voting/{id}/close` | High — director closes voting + AUTO_ABSTAIN_TIMEOUT |
| `POST /api/workflow/{id}/bank-finalize-rejection` | Medium — explicit "keep rejection" action for bank reviewer |
| `DELETE /api/workflow/{id}/claim-support-review` | Low — docs spec DELETE, implemented as POST support-release |

### Missing Schema Columns (import_requests)

Per docs/03, the following should exist:
- `submitted_by` (FK → users)
- `reviewed_by` (FK → users) — bank internal reviewer
- `rejected_by` (FK → users)
- `resubmitted_by` (FK → users)
- `support_reviewed_by` (FK → users)
- `swift_uploaded_by` (FK → users)
- `voting_opened_by` (FK → users)
- `voting_opened_at` (timestamp)
- `voting_closed_by` (FK → users)
- `voting_closed_at` (timestamp)
- `voting_session_status` (enum nullable)
- `final_decision_at` (timestamp)
- `customs_declaration_id` (FK → customs_declarations)

### Missing Schema Columns (banks)

- `name_ar` and `name_en` (docs spec) vs single `name` (implementation)

### Missing Audit Log Columns

- Explicit `from_status` and `to_status` columns (stored in `metadata` JSON instead)

### Other Gaps

- Support claim TTL is configured in hours (`workflow.support_claim_ttl_hours`), not the 15-minute window the docs specify. Docs spec: 15 min inactivity + 60-second heartbeat. Backend implementation: hours-based, no heartbeat endpoint.
- `BANK_MANAGER` role exists in implemented `UserRole.php` but is NOT in the canonical 7-role spec. It appears in TransitionMap with BANK_REVIEWER permissions.
- Rate limiting on login (5/min per IP) and account lockout (10 failures → 15-min lockout) from AGENTS.md — implementation status unknown.
- `DirectStatusMutationException` protection on `ImportRequest::setAttribute()` — specified in docs/05, implementation status unknown (model does not show this guard in the code read).

---

## 12. Quality & Security Constraints

### Performance Requirements

- Pagination: default 20 items per page on all list endpoints
- No N+1 queries: always eager-load relations (`with(['bank', 'merchant', 'claimedBy'])`)
- Index on: `status`, `bank_id`, `claimed_by`, `claim_expires_at`, `current_owner_role`, `created_at`
- Frontend: lazy-load pages and components; no heavy analytics on dashboard
- Redis caching: dashboard stats, queue counts

### Security Requirements

| Requirement | Implementation |
|---|---|
| Auth | Sanctum HTTP-only cookies (SPA mode) or Bearer token (API clients) |
| Login rate limit | 5 attempts/min per IP — implement via Laravel `RateLimiter` |
| Account lockout | 10 consecutive failures → 15-minute lockout |
| Organization scoping | `scopeForUser()` at query level — MANDATORY |
| Role enforcement | `ImportRequestPolicy` + explicit role checks in controllers |
| Workflow integrity | All transitions via `WorkflowService::transition()` only |
| Vote concurrency | `lockForUpdate()` on vote submission and session closure |
| File validation | PDF MIME type check + max size (configured in `documents.allowed_mime_types`) |
| File storage | Private disk only (`storage/private/`) — never public |
| Audit logging | Every action → `audit_logs`; every transition → `request_stage_history` |
| Failed auth | Log to `audit_logs` with `user_id: NULL` |
| CSRF | Enforced by Sanctum SPA mode |

### Code Quality Rules

- Controllers: thin — receive, validate, authorize, call service, return response
- Services: contain all business logic
- Models: contain scopes, accessors, casts, relationships — no business logic
- Vue components: presentation only — no direct API calls, no business logic
- Composables/stores: API calls, state, business-presentation logic
- TypeScript: strict mode, all API responses typed
- No `any` types
- Zod schemas for all form validation
- All status/role values from typed enums — never raw strings

---

## 13. Design System Reference

### Color Tokens (exact hex values)

| Token Name | HEX | Usage |
|---|---|---|
| App Background | `#f5f5f7` | Page canvas, main background |
| Surface | `#ffffff` | Cards, panels, tables, sidebar |
| Primary Text | `#1d1d1f` | Headlines, main content |
| Secondary Text | `#6e6e73` | Labels, descriptions, table headers |
| Border | `#d2d2d7` | Card borders, table row dividers |
| Primary Action Blue | `#0071e3` | Main buttons, links, active nav item, focus ring, claim button |
| Approval Green | `#34c759` | Approved/success status badges |
| Rejected Red | `#ff3b30` | Rejected/error status, required field asterisk |
| Pending Amber | `#ff9f0a` | Pending/warning status |
| Voting Indigo | `#5856d6` | Voting/review state badges |
| SWIFT Cyan | `#32ade6` | SWIFT-related actions/badges |
| Locked Gray | `#8e8e93` | Locked/read-only states, disabled elements |

### Typography

| Type | Size | Weight | Line Height | Usage |
|---|---|---|---|---|
| Display | 28px | Medium | 36px | Workflow headers |
| Section Title | 20px | Medium | 28px | Card titles, section headings |
| Body | 16px | Regular | 24px | Table data, main content |
| Caption | 13px | Regular | 18px | Labels, secondary info |
| Button | 15px | Medium | 20px | Buttons, action items |

- Arabic: IBM Plex Sans Arabic (all weights)
- English: Inter (all weights)
- Letter spacing: 0.01em Arabic, 0em English
- Font smoothing: antialiased always

### Layout & Spacing

- Base grid: 8px
- Main gutters: 24px
- Max content width: 1280px (dashboard)
- Card radius: 12px
- Card border: 1px solid `#d2d2d7`
- Card shadow: `0 2px 8px rgba(29,29,31,0.04)`
- Dropdown/overlay shadow: `0 4px 16px rgba(29,29,31,0.08)`

### Component Sizing

- Buttons: 44px height, 12px radius
- Inputs: 44px min height, 12px radius, 1px `#d2d2d7` border
- Input focus: 1.5px `#0071e3` border
- Input disabled: `#f5f5f7` background, `#8e8e93` border
- Badges: pill shape, 24px height, icon + label always
- Icons: 24px, monochrome
- Sidebar: 264px fixed width, right side (RTL)
- Table row height: 44px
- Table cell padding: 16px horizontal, 8px vertical

### Status Badge System

| Status | Color | Icon |
|---|---|---|
| Approved | `#34c759` | Checkmark |
| Rejected | `#ff3b30` | Cross |
| Pending | `#ff9f0a` | Clock |
| Voting | `#5856d6` | Ballot |
| SWIFT | `#32ade6` | SWIFT icon |
| Locked | `#8e8e93` | Lock |

### Responsive Breakpoint

- Desktop-first; breakpoint at ≤ 600px
- ≤ 600px: sidebar → top nav bar; cards → full width stacked; tables → key-value pairs
- Minimum touch target: 48px (voting pages — must work on tablets)
- RTL maintained on all breakpoints

### Motion Rules

- Transitions: 120ms fade/slide only
- No bounce, parallax, or background animation
- Focus ring: 2px `#0071e3`

---

## 14. Key Implementation Rules (Non-Negotiable)

### From AGENTS.md

**Never Do:**
1. Do NOT mutate `current_status` directly on `ImportRequest` model — all changes via `WorkflowService::transition()`
2. Do NOT put business logic in controllers, Vue components, or routes
3. Do NOT expose requests outside a user's organization scope
4. Do NOT generate shared admin dashboards — every view is queue-scoped and role-scoped
5. Do NOT use status/role values not in the canonical enums
6. Do NOT modify anything inside `lovable/`
7. Do NOT create `AI-PROTOTYPE-PROMPT.md` in team repos

**Always Do:**
1. Enforce organization-scoped visibility at the database query level (never frontend-only)
2. Log every workflow transition to BOTH `request_stage_history` AND `audit_logs`
3. Include `role` (at time of action) in every audit log entry
4. Wrap customs declaration generation in a single database transaction
5. Use pessimistic locking (`lockForUpdate()`) for vote submission and voting session closure
6. Validate file type as PDF-only for ALL document uploads
7. Return `WORKFLOW_IMMUTABLE_STATE` (HTTP 403) for mutations on terminal states

### From DESIGN.md

1. Color is ONLY for operational meaning — never decoration
2. RTL is the default — never bolt-on
3. Queue-first: dashboards answer "what work matters right now?" not "what are the overall stats?"
4. Every status badge must use both color AND icon — never color-only
5. No glassmorphism, gradients, heavy shadows, or startup SaaS aesthetics
6. No floating buttons (FABs)
7. All actions must remain accessible (no hover-only actions)
8. Locked states: disable all interaction affordances, show clear locked messaging

### Commit Convention

Format: `type(scope): description`  
Examples: `feat(workflow): add support claim heartbeat endpoint`  
Co-author AI commits: `Co-Authored-By: Claude <noreply@anthropic.com>`

### Library Documentation

Before writing implementation code for any library, run:
```bash
npx ctx7@latest library "<LibraryName>" "<your question>"
npx ctx7@latest docs <libraryId> "<your question>"
```
Use for: Laravel 11, Nuxt 4, Vue 4, Tailwind v4, shadcn-vue, Pinia, VeeValidate, Zod, Sanctum, Redis.

### Context Before Modifying Code

Before modifying any service or model, use SocratiCode to:
1. Find the symbol: `mcp__plugin_socraticode_socraticode__codebase_symbol`
2. Check what calls it: `mcp__plugin_socraticode_socraticode__codebase_flow`
3. Assess impact: `mcp__plugin_socraticode_socraticode__codebase_impact`
