---
stepsCompleted: ["step-01-validate-prerequisites", "step-02-design-epics", "step-03-create-stories", "step-04-final-validation"]
status: 'complete'
completedAt: '2026-06-07'
inputDocuments:
  - _bmad-output/planning-artifacts/sprint-change-proposal-2026-06-07-national-committee-rebrand-trader-workflow.md
  - _bmad-output/planning-artifacts/architecture.md
  - _bmad-output/planning-artifacts/project-context.md
  - docs/01-workflow-and-business-rules.md
  - docs/03-database-and-models.md
  - docs/06-api-reference.md
  - docs/user-view/data-entry.md
  - docs/user-view/bank-reviewer.md
  - docs/user-view/support-committee.md
  - docs/user-view/executive-member.md
  - docs/user-view/committee-director.md
isAdditiveLayer: true
baseEpicsFile: _bmad-output/planning-artifacts/epics.md
---

# National Committee Re-Scope — Epic Breakdown (Additive Layer: Epic 17 series, 17-A…17-F)

## Overview

This document is an **additive re-scope layer** on top of the shipped platform. It decomposes the
approved `sprint-change-proposal-2026-06-07-national-committee-rebrand-trader-workflow.md` and the
architecture decisions D1–D8 into the Epic 17 series (17-A…17-F).

**Layer rules (binding):**

- The base `epics.md` (shipped Epics 1–15) is **NOT modified, rewritten, renumbered, or deprecated**.
- Epics 17-A…17-F are **fully self-contained**. References to existing functionality are made through
  **dependency notes only** (not edits to closed epics).
- Existing shipped functionality remains authoritative **unless explicitly overridden** by the
  approved 2026-06-07 change proposal and architecture decisions D1–D8. Each override is called out
  explicitly in the relevant epic/story.
- The Epic 17 series with letter sub-IDs (17-A…17-F) keeps the re-scope set adjacent to the shipped base (Epics 1–16) while remaining clearly delineated.

**Three LOCKED decisions (do not re-litigate):**

1. Reject → Not Eligible = **label-layer only** (enum cases + DB values frozen; zero data migration).
2. **New Trader tables, keep Merchant** (snapshot trader data into the new request; Merchant retained for historical requests).
3. Voting majority `floor(total_eligible/2)+1` = **new requests only** (no retroactive recompute of closed sessions).

**Canonical "Not Eligible" label:** Arabic `غير مستوفي للشروط` / English `Not Eligible`.

## Requirements Inventory

### Functional Requirements

**Epic 17-A — System Rebrand**

FR-A1: Replace all platform branding (Arabic + English) with "The National Committee for Regulating & Financing Imports" / "اللجنة الوطنية لتنظيم وتمويل الواردات" across every surface: page titles, login screen, sidebar header/subtitle, app header, settings, and document/PDF/report/export headers.
FR-A2: Update the sidebar header subtitle (currently "البنك المركزي اليمني" / platform name "منصة الواردات") to the National Committee identity on every role's sidebar.
FR-A3: Update email and notification template headers/footers to the National Committee identity (display strings only; no template-engine change).
FR-A4: Rebrand is string-level only — no schema, no enum case, no DB value changes.

**Epic 17-B — Trader Management Module**

FR-B1: Provide a global `traders` registry keyed by `tax_number` (globally unique identifier), with trader_name, tax_card_expiry, commercial_registration_number, commercial_registration_expiry.
FR-B2: Support multiple companies per trader (`trader_companies`, 1:N) — company_name.
FR-B3: Support multiple owners/shareholders per trader (`trader_owners`, 1:N) — full_name, ownership_percentage, optional nationality, optional identification_number; owners holding ≥25% are the enforced "required" set.
FR-B4: Provide Trader CRUD (list/create/edit/view) with companies + owners sub-forms, permitted to DATA_ENTRY, BANK_REVIEWER, BANK_ADMIN.
FR-B5: Provide a tax-number lookup endpoint returning trader + companies + owners for request autofill.
FR-B6: Traders are global (NOT org-scoped) for read/lookup; write authority is role-gated via policy.
FR-B7: Retain the existing `Merchant` model unchanged for historical (pre-trader) requests.

**Epic 17-C — 5-Tab Request Form + Invoice/Shipping Data Model**

FR-C1: Replace the existing 4-step request wizard with a 5-tab request screen: Basic / Invoice / Shipping / Documents / Workflow History.
FR-C2: Tab 1 (Basic) performs a tax-number lookup → trader autofill → **snapshot** of trader data into the request row (request-level edits never mutate the trader record).
FR-C3: Add ~20 additive nullable columns to `import_requests` (request_type, coverage_type, currency_source, payment_terms_mode, request_percentage, request_currency, requested_amount, invoice_type, invoice_currency, unit_of_measure, total_invoice_amount, commodity, exporting_company_name, exporting_company_location, country_of_origin, port_of_loading, port_of_arrival, incoterm, final_destination, shipping_date, arrival_date, trader_id + trader snapshot fields).
FR-C4: Add 7 additive backed PHP enums with bilingual labels: RequestType, CoverageType, CurrencySource, PaymentTermsMode, InvoiceType, PortOfArrival, Incoterm.
FR-C5: Tab 2 (Invoice) enforces coverage rules — Full coverage = 100% (readonly); Partial coverage = `≥5% and <100%`.
FR-C6: Tab 3 (Shipping) captures ports + incoterm via the new enums plus shipping/arrival dates and final destination.
FR-C7: Tab 4 (Documents) shows a fixed document set (5 mandatory + ~9 optional), PDF-only, reusing the existing upload + checksum behavior. (Dynamic document-type configuration is deferred.)
FR-C8: Tab 5 (Workflow History) reuses the existing workflow timeline component.
FR-C9: All new columns are nullable/defaulted so historical requests remain valid (zero data migration).

**Epic 17-D — Global Duplicate Prevention & Partial-Financing Ledger**

FR-D1: Compute the global financing total per `(tax_number, invoice_number)` as a **derived query** over `import_requests` (single source of truth; no separate ledger table), excluding Not-Eligible/terminal-rejected rows.
FR-D2: Block a new request when the sum of existing financing % for the invoice key + the new request's % would exceed 100% **across all banks** (global, cross-org).
FR-D3: Allow multiple partial-financing requests on the same invoice key only while the global sum stays ≤100%.
FR-D4: Validate invoice-key consistency on a shared invoice: currency, total, invoice number, and tax number must match across requests sharing the key (`DUPLICATE_INVOICE_MISMATCH`).
FR-D5: Enforce the financing check concurrency-safely: named invoice-key lock (MySQL advisory lock, reusing the Story 3.6 pattern) + row-lock + sum-AFTER-lock + validate ≤100 + transition, all in one transaction (NOT `SUM(...) FOR UPDATE`; the empty-set/phantom-insert race must be closed).
FR-D6: Add a composite index `(tax_number, invoice_number)` on `import_requests`.
FR-D7: Expose a financing-utilization endpoint returning aggregate only (`used_percent`, `remaining_percent`, `blocked`) — never foreign request rows.
FR-D8: Surface a UI % utilization indicator + low-remaining warning + submit-block; advisory only (backend check is authoritative).
FR-D9: All global cross-bank reads go through `FinancingLedgerService` exclusively (the documented org-scope exception).

**Epic 17-E — Workflow Authority Reform**

FR-E1: Internal Reviewer (BANK_REVIEWER) loses terminal/internal reject capability — only approve-continue + return-to-Data-Entry. Remove `bank_reject` / `bank_reject_terminal` from the reviewer's available actions (keep enum cases for history).
FR-E2: Support Committee loses decision authority — replace `support_approve` / `support_reject` with a single `support_forward_to_executive` transition; comments still recorded to audit. UI removes "Support Approval" + "Reject" + "Open Voting" buttons → single "Send to Executive Committee".
FR-E3: Executive voting UI shows only Approved / Not Eligible (hide Abstain; keep AUTO_ABSTAIN_TIMEOUT case for history).
FR-E4: `VotingService` finalization uses majority `floor(total_eligible_members/2)+1` approvals → APPROVED, else NOT-ELIGIBLE — applied to **new requests only** (gated; legacy/closed sessions keep prior behavior). Examples: 6 members → 4 approvals required; 8 → 5; 10 → 6.
FR-E4a: **Director tie-break is REMOVED for the new voting model.** There is no tie resolution: if the required majority is not reached, the outcome is NOT-ELIGIBLE. An even split is NOT-ELIGIBLE (3v3 → Not Eligible; 4v4 → Not Eligible; 5v5 → Not Eligible). The Director tie-break path remains **only** for historical requests still using the legacy voting rule (gated by the same new-rule flag as FR-E4).
FR-E5: Workflow display "Returned for Review" → "Returned to Data Entry" (only on Internal Reviewer return).
FR-E6: Merge "SWIFT Upload" + "SWIFT Uploaded" into one displayed "SWIFT Uploaded" stage (keep granular `WAITING_FOR_SWIFT` + `SWIFT_UPLOADED` statuses + audit entries).
FR-E7: All authority changes are implemented via `WorkflowService::transition()` + `TransitionMap` + policies; no `current_status` direct mutation; no enum case renamed.
FR-E8: **Remove or hide all UI elements, filters, tabs, counters, dashboards, queues, and notifications that depend on `SUPPORT_REJECTED` for new-rule requests** (Support Committee no longer rejects — FR-E2). Affected surfaces include the BANK_REVIEWER `support_rejected` tab, the "رُفض من المساندة" KPI card, the SupportRejectedBanner + its two-choice action, the support-rejection notification, and any dashboard counters keyed on `SUPPORT_REJECTED`. **Historical requests in `SUPPORT_REJECTED` must continue to render correctly and preserve their existing audit trail** (the status case is frozen and retained for history; only new-rule surfaces are removed/hidden).

**Epic 17-F — Terminology & Label Layer**

FR-F1: Swap all user-facing "Reject/Rejected" copy to "غير مستوفي للشروط / Not Eligible" in enum `.label()` (RequestStatus rejection cases, VoteType).
FR-F2: Swap the same terminology in `frontend/app/constants/workflow.ts`, TypeScript types, notification templates, and report/export headers.
FR-F3: No enum case or DB value renamed; reports/exports/APIs continue emitting stored codes — only human-readable labels change.
FR-F4: Each label lives in exactly one source per layer (backend enum `.label()`; frontend `constants/workflow.ts`) — no duplicated label strings across files.
FR-F5: **Terminology validation.** All user-facing surfaces — UX role stories/screens, notifications, reports, exports, and workflow labels — must consistently use Arabic `غير مستوفي للشروط` / English `Not Eligible`. The following alternative labels are **forbidden** anywhere user-facing: "Rejected", "Declined", "Disapproved", "Not Approved" (and Arabic equivalents like "مرفوض"). This applies to all new-rule executive/support/bank outcome copy. (Stored enum codes such as `EXECUTIVE_REJECTED` remain frozen at the data layer — this rule governs DISPLAY strings only.)

### NonFunctional Requirements

NFR-1: **Backward-compatibility invariant.** Frozen enum cases + nullable columns + new-requests-only voting → all historical requests/audit rows render and function unchanged with zero data migration.
NFR-2: **Org-scope preserved everywhere except the financing ledger.** The ledger global read (D) is the single deliberate exception; it must be access-guarded, documented in the service docblock, and return aggregates only (never foreign rows).
NFR-3: **Ledger concurrency correctness.** The global ≤100% check must be correct under concurrent cross-bank submissions, including the empty-set first-insert race (named lock required).
NFR-4: **Workflow integrity.** All transitions atomic, via `WorkflowService::transition()`; immutable `request_stage_history` + `audit_logs`; `role` captured at action time.
NFR-5: **RBAC unchanged.** No new roles; reuse the existing 8-role enum. Authority changes are TransitionMap/policy edits only.
NFR-6: **Audit immutability.** Terminology + SWIFT-merge are display-only; stored action/status codes and audit entries are unchanged.
NFR-7: **Trader snapshot integrity.** Request-linked trader data is a point-in-time snapshot taken at create/submit; editing a trader never mutates an existing request.
NFR-8: **No new dependency.** Every new component is built on the inherited stack (Eloquent, native enums, MySQL locks, shadcn-vue + VeeValidate + Zod). No i18n framework added (labels stay hardcoded bilingual).
NFR-9: **Segregation of duties preserved.** The creator-cannot-review and one-vote-per-member invariants remain enforced at backend policy and are not weakened by the authority reform.
NFR-10: **Test rigor.** Workflow-authority, voting, and ledger stories run the full backend test suite (security/workflow-critical per doctrine); other stories use focused tests.

### Additional Requirements (from Architecture D1–D8)

- D1: Financing ledger = derived query over `import_requests` (no `request_financing_ledger` table).
- D2: Concurrency = named invoice-key lock (MySQL `GET_LOCK`, reuse Story 3.6 advisory-lock pattern) + row-lock + sum-after-lock + validate + transition, one transaction. Explicitly NOT `SUM(...) FOR UPDATE`.
- D3: Trader snapshot copied onto the request row at create/submit; `trader_id` FK + denormalized snapshot columns; `Merchant` retained; `merchant_id` / `trader_id` coexist by era, both nullable.
- D4: Voting majority gated per-request (created-after-feature gate or explicit `voting_rule_version`); legacy/closed sessions never recomputed.
- D5: Authority reform = TransitionMap + Policy edits only; frozen actions retained for history, removed from actor-available set.
- D6: Terminology = single label source per layer (enum `.label()` / `constants/workflow.ts`).
- D7: 7 new enums = additive native backed PHP enums, SCREAMING_SNAKE + bilingual `.label()`.
- D8: SWIFT stage merge = display-only; underlying statuses + audit untouched.
- Error codes: new `FINANCING_LIMIT_EXCEEDED` (422), `DUPLICATE_INVOICE_MISMATCH` (422); reuse `{ success, message, error_code }` envelope.
- **3 deferred gaps to fix in epic-foundation stories:** (1) `request_percentage` storage type (integer vs decimal) + voting-rule gating mechanism; (2) snapshot column naming convention; (3) exact `not_eligible_set` of statuses excluded from the ledger sum.

### UX Design Requirements

> Source: `docs/user-view/{data-entry,bank-reviewer,support-committee,executive-member,committee-director}.md`. These describe the CURRENT shipped UX; Epics 17-A…17-F override the items below per the approved proposal + D1–D8. Existing shipped UX remains authoritative for everything NOT listed here.

UX-DR1: **Data Entry request creation** — replace the 4-step wizard (Request Data / Supplier & Shipment / Documents / Review) with the 5-tab screen (Basic / Invoice / Shipping / Documents / Workflow History). [Override of data-entry.md "New Request"]
UX-DR2: **Trader autofill** — Tab 1 replaces the "Importer / Merchant searchable select" with a tax-number lookup that autofills trader + companies + owners and snapshots them into the request. [Override of data-entry.md Step 1]
UX-DR3: **Hard financing block** — replace the existing soft "duplicate invoice warning" (amber, proceed-anyway) with a hard global ≤100% submit-block + % utilization indicator + low-remaining warning. [Override of data-entry.md Step 2 duplicate-warning]
UX-DR4: **Trader Management pages** — new list/create/edit/view pages with companies + owners sub-forms (DATA_ENTRY, BANK_REVIEWER, BANK_ADMIN); add a sidebar nav entry where role-appropriate. [New, not in shipped role docs]
UX-DR5: **Internal Reviewer no-reject** — remove the "رفض نهائي / terminal reject" destructive action from the BANK_REVIEW ActionsPanel; keep only Approve + Return-to-Data-Entry. Remove the `support_rejected` follow-up queue/banner/tab (Support no longer rejects). [Override of bank-reviewer.md ActionsPanel + SupportRejectedBanner + tabs]
UX-DR6: **Support Committee forward-only** — replace the claim-decision ActionsPanel (Approve / Return / Reject) with a single "إرسال إلى اللجنة التنفيذية / Send to Executive Committee" action + comment capture; remove approve/reject decision buttons and the auto-open-voting messaging tied to support approval. [Override of support-committee.md ActionsPanel + decision modals]
UX-DR7: **Executive voting Approved/Not-Eligible** — VotingPanel shows only Approve / Not Eligible (hide Abstain); update tally pills and member vote labels accordingly. [Override of executive-member.md VotingPanel + tally]
UX-DR8: **Voting majority, NO tie-break (new model)** — finalize uses `floor(total_eligible_members/2)+1`; **remove the Director tie-break and override UI for new-rule requests** (TieBreakBanner, "حسم التعادل" control, tie-break modal). If majority is not reached, outcome is Not Eligible (even split = Not Eligible). The tie-break controls remain rendered **only** for historical legacy-rule requests. The Director VotingPanel for new-rule requests keeps close-session + finalize (majority-derived), minus tie-break. [Override of committee-director.md VotingPanel Director controls — DECIDED: tie-break removed for new model]
UX-DR9: **"Returned to Data Entry" label** — rename "Returned for Review" / return labels to "Returned to Data Entry" on the Internal Reviewer return path across banners, tabs, timeline, and status chips. [Override of return labels across data-entry.md + bank-reviewer.md]
UX-DR10: **SWIFT stage merge (display)** — collapse the two SWIFT timeline nodes into a single "SWIFT Uploaded" stage in WorkflowProgress + constants/workflow.ts; keep audit granularity. [Override of WorkflowProgress in all role docs]
UX-DR11: **Not Eligible terminology** — replace "مرفوض / Rejected" status badges, banners, tabs, KPI labels, and notification copy with "غير مستوفي للشروط / Not Eligible" across all role surfaces. [Override of status labels across all 5 role docs]
UX-DR12: **National Committee rebrand (UI)** — replace platform name "منصة الواردات" + subtitle "البنك المركزي اليمني" in sidebars, login branding, headers, and print letterheads with the National Committee identity. [Override of branding across all role docs]

### FR Coverage Map

FR-A1: Epic 17-A — bilingual rebrand across all surfaces (strings only)
FR-A2: Epic 17-A — sidebar header/subtitle rebrand on every role
FR-A3: Epic 17-A — email/notification template header/footer rebrand
FR-A4: Epic 17-A — rebrand is string-level only, no schema/enum/DB change
FR-B1: Epic 17-B — global `traders` registry keyed by tax_number
FR-B2: Epic 17-B — `trader_companies` (1:N)
FR-B3: Epic 17-B — `trader_owners` (1:N, ownership %, ≥25% required set)
FR-B4: Epic 17-B — Trader CRUD for DATA_ENTRY/BANK_REVIEWER/BANK_ADMIN
FR-B5: Epic 17-B — tax-number lookup endpoint for autofill
FR-B6: Epic 17-B — traders global for read; write role-gated
FR-B7: Epic 17-B — Merchant retained for historical requests
FR-C1: Epic 17-C — 5-tab request screen replaces 4-step wizard
FR-C2: Epic 17-C — Tab 1 tax-number lookup → trader autofill → snapshot
FR-C3: Epic 17-C — ~20 additive nullable `import_requests` columns
FR-C4: Epic 17-C — 7 additive backed PHP enums (bilingual labels)
FR-C5: Epic 17-C — Tab 2 Full=100% readonly / Partial ≥5% & <100%
FR-C6: Epic 17-C — Tab 3 shipping (ports/incoterm/dates/destination)
FR-C7: Epic 17-C — Tab 4 fixed docs (5 mandatory + ~9 optional), PDF-only
FR-C8: Epic 17-C — Tab 5 reuses existing workflow timeline
FR-C9: Epic 17-C — all new columns nullable/defaulted (zero migration)
FR-D1: Epic 17-D — derived-query ledger (no separate table)
FR-D2: Epic 17-D — global ≤100% block across all banks
FR-D3: Epic 17-D — multiple partials allowed while global sum ≤100%
FR-D4: Epic 17-D — invoice-key consistency guard (DUPLICATE_INVOICE_MISMATCH)
FR-D5: Epic 17-D — concurrency-safe submit protocol (named lock + row-lock + sum-after)
FR-D6: Epic 17-D — composite index (tax_number, invoice_number)
FR-D7: Epic 17-D — financing-utilization endpoint (aggregate only)
FR-D8: Epic 17-D — % utilization UI + low-remaining warning + submit-block
FR-D9: Epic 17-D — all global reads via FinancingLedgerService (org-scope exception)
FR-E1: Epic 17-E — Internal Reviewer no-reject (approve-continue + return only)
FR-E2: Epic 17-E — Support Committee comment + forward-to-executive only
FR-E3: Epic 17-E — Executive UI Approved/Not-Eligible (hide Abstain)
FR-E4: Epic 17-E — VotingService majority floor(n/2)+1 (new requests only)
FR-E4a: Epic 17-E — Director tie-break REMOVED for new model (even split = Not Eligible); legacy keeps tie-break
FR-E5: Epic 17-E — "Returned for Review" → "Returned to Data Entry"
FR-E6: Epic 17-E — SWIFT Upload + Uploaded display merge
FR-E7: Epic 17-E — all via WorkflowService::transition()/TransitionMap/policies
FR-E8: Epic 17-E — remove/hide SUPPORT_REJECTED-dependent surfaces (new-rule); historical preserved
FR-F1: Epic 17-F — enum `.label()` → Not Eligible terminology
FR-F2: Epic 17-F — frontend constants/types/notif/report terminology swap
FR-F3: Epic 17-F — no enum/DB rename; APIs/exports emit stored codes
FR-F4: Epic 17-F — single label source per layer
FR-F5: Epic 17-F — terminology validation; banned synonyms (Rejected/Declined/Disapproved/Not Approved)

## Epic List

### Epic 17-A: System Rebrand (National Committee)
Every user sees the platform as "The National Committee for Regulating & Financing Imports /
اللجنة الوطنية لتنظيم وتمويل الواردات" — login, sidebar, header, settings, emails, PDFs, reports,
exports. String-level only; no schema. Lowest risk; ships first.
**FRs covered:** FR-A1, FR-A2, FR-A3, FR-A4
**Dependency notes:** none. Independent of all other re-scope epics.

### Epic 17-B: Trader Management Module
Bank users (Data Entry, Internal Reviewer, Bank Manager) can register and manage global traders
with their companies and owners, and look a trader up by tax number. Establishes the trader data
foundation that the new request form snapshots from.
**FRs covered:** FR-B1, FR-B2, FR-B3, FR-B4, FR-B5, FR-B6, FR-B7
**Dependency notes:** Reuses existing RBAC roles + shadcn-vue patterns (base Epics 1, 1.4, 6.3.x —
referenced, not modified). Must precede Epic 17-C (snapshot needs traders).

### Epic 17-C: Request Form Redesign (5 Tabs) + Invoice/Shipping Data Model
Data Entry creates requests through a 5-tab screen (Basic / Invoice / Shipping / Documents /
Workflow History): tax-number lookup autofills + snapshots a trader, captures the full invoice +
shipping data model, and enforces Full/Partial coverage % rules. Adds the additive columns + 7 new
enums.
**FRs covered:** FR-C1, FR-C2, FR-C3, FR-C4, FR-C5, FR-C6, FR-C7, FR-C8, FR-C9
**Dependency notes:** Depends on Epic 17-B (trader lookup/snapshot). Overrides the shipped 4-step
wizard (base Epic 2.5 / 7.x — referenced, not modified). Reuses existing document-upload behavior
(base Epic 2.2).

### Epic 17-D: Global Duplicate Prevention & Partial-Financing Ledger
A new request is blocked when the global financing % for its (tax number + invoice number) would
exceed 100% across ALL banks; partial financing is allowed only while the global sum stays ≤100%.
Adds the derived-query ledger, the concurrency-safe submit protocol, the % utilization UI, and the
invoice-key consistency guard.
**FRs covered:** FR-D1, FR-D2, FR-D3, FR-D4, FR-D5, FR-D6, FR-D7, FR-D8, FR-D9
**Dependency notes:** Depends on Epic 17-C (`request_percentage` + invoice-key columns). Extends the
shipped `DuplicateDetectionService` (base Epic 5.x — referenced, not modified). Reuses the Story 3.6
MySQL advisory-lock pattern (referenced).

### Epic 17-E: Workflow Authority Reform
The committee's decision flow changes: Internal Reviewer can no longer reject (approve-continue +
return-to-Data-Entry only); the Support Committee comments and forwards to Executive (no decision);
Executive voting becomes Approved / Not Eligible by simple majority `floor(n/2)+1` with no
tie-break (new requests only); "Returned for Review" → "Returned to Data Entry"; the SWIFT
upload/uploaded display merges. All SUPPORT_REJECTED-dependent surfaces are removed for new-rule
requests; historical requests keep their behavior + audit.
**FRs covered:** FR-E1, FR-E2, FR-E3, FR-E4, FR-E4a, FR-E5, FR-E6, FR-E7, FR-E8
**Dependency notes:** Modifies the shipped `TransitionMap`, `VotingService`, policies, and role UIs
(base Epics 2.3, 3.4, 3.5, 8.x — referenced, not modified; changes via `WorkflowService::transition()`).
Voting gating is independent of D but shares the new-requests-only invariant. Full test suite.

### Epic 17-F: Terminology & Label Layer
Every user-facing "Reject/Rejected" surface reads "غير مستوفي للشروط / Not Eligible" — enum labels,
frontend constants, types, notification templates, report/export headers. Banned synonyms
(Rejected/Declined/Disapproved/Not Approved) are prohibited. No enum case or DB value renamed;
APIs/exports still emit stored codes. Finalized after E so workflow labels have settled.
**FRs covered:** FR-F1, FR-F2, FR-F3, FR-F4, FR-F5
**Dependency notes:** Touches enum `.label()` + `constants/workflow.ts` (same files as C/E label
work, but a distinct terminology pass timed last). Display-only; no logic change.

---

## Epic 17-A: System Rebrand (National Committee)

Every surface presents the platform as "The National Committee for Regulating & Financing Imports /
اللجنة الوطنية لتنظيم وتمويل الواردات". String-level only; no schema, enum, or DB change.

### Story 17-A.1: Backend rebrand strings (labels, emails, PDFs, reports, exports)

As a platform owner,
I want all backend-generated text (enum display labels, email/notification template headers and
footers, PDF letterheads, report titles, and export headers) to use the National Committee identity,
So that every document and message the system produces reflects the new institution name.

**Acceptance Criteria:**

**Given** the backend renders any enum `.label()`, email/notification template, generated PDF, report, or export header
**When** the platform name or issuing-institution name is shown
**Then** it reads "The National Committee for Regulating & Financing Imports" / "اللجنة الوطنية لتنظيم وتمويل الواردات"
**And** no enum case, DB value, route, or stored code is changed (display strings only)

**Given** an existing historical request, email, or audit record
**When** it is re-rendered after the rebrand
**Then** it displays the new institution name with no data migration and no change to stored content

### Story 17-A.2: Frontend rebrand strings (login, sidebar, header, settings, print)

As any platform user,
I want the login screen, sidebar header/subtitle, app header, settings, and print letterheads to show
the National Committee identity,
So that the application visibly belongs to the new institution for every role.

**Acceptance Criteria:**

**Given** any role's sidebar
**When** the header and subtitle render (currently "منصة الواردات" / "البنك المركزي اليمني")
**Then** they show the National Committee Arabic + English identity from a single platform-name source

**Given** the login screen, app header, settings surface, and `/requests/[id]/print` letterhead
**When** they render
**Then** each shows the National Committee identity

**Given** the rebrand is applied
**When** the change is reviewed
**Then** it is string-level only — no component logic, route, or token change — and committed to both the frontend team repo and the root monorepo

---

## Epic 17-B: Trader Management Module

Bank users manage global traders with companies and owners, and look traders up by tax number,
establishing the data foundation the new request form snapshots from.

### Story 17-B.1: Trader data model & migrations

As a developer,
I want the `traders`, `trader_companies`, and `trader_owners` tables, models, relationships, factories,
and a dev seeder,
So that trader data can be persisted and exercised by later stories.

**Acceptance Criteria:**

**Given** the migrations run
**When** the schema is inspected
**Then** `traders` exists with a globally unique `tax_number`, `trader_name`, `tax_card_expiry`, `commercial_registration_number`, `commercial_registration_expiry`; `trader_companies` (FK `trader_id`, `company_name`); `trader_owners` (FK `trader_id`, `full_name`, `ownership_percentage`, nullable `nationality`, nullable `identification_number`)

**Given** the `Trader` model
**When** relationships are used
**Then** `Trader hasMany TraderCompany` and `Trader hasMany TraderOwner` resolve, and the existing `Merchant` model is untouched

**Given** the factories and seeder
**When** they run in a dev environment
**Then** sample traders with companies and owners are created without affecting historical requests

### Story 17-B.2: Trader CRUD API + tax-number lookup

As a bank user (Data Entry / Internal Reviewer / Bank Manager),
I want create/read/update/list trader endpoints plus a tax-number lookup,
So that I can manage traders and the request form can autofill from a trader.

**Acceptance Criteria:**

**Given** `TraderService`, `TraderController`, `TraderPolicy`, Form Requests, and Resources
**When** a DATA_ENTRY, BANK_REVIEWER, or BANK_ADMIN user calls the trader endpoints
**Then** they can list/create/update/view traders with their companies and owners (write authority role-gated by policy)

**Given** the tax-number lookup endpoint
**When** called with a `tax_number`
**Then** it returns the trader plus companies and owners for autofill, or a not-found response, and is readable across organizations (traders are global, not org-scoped)

**Given** a user whose role is not permitted trader-write authority
**When** they attempt a create/update
**Then** the policy denies it (403) and the action is not exposed to them

### Story 17-B.3: Trader Management pages (list/create/edit/view)

As a bank user,
I want Trader Management pages with companies and owners sub-forms,
So that I can manage traders through the UI using the existing design system.

**Acceptance Criteria:**

**Given** the trader pages (`/traders` list, new, `[id]` view, `[id]/edit`)
**When** I create or edit a trader
**Then** I can add/remove multiple companies and multiple owners (with ownership %) via shadcn-vue + VeeValidate + Zod sub-forms, validated client-side and server-side

**Given** the `useTraders` composable + `traders` Pinia store + trader types
**When** the pages load trader data
**Then** they mirror the existing `useRequests`/`requests` patterns and a sidebar nav entry appears for role-appropriate users

**Given** an owner with ownership_percentage ≥ 25%
**When** the form validates
**Then** that owner's required fields are enforced as the "required set", and the change is committed to both the frontend team repo and the root monorepo

---

## Epic 17-C: Request Form Redesign (5 Tabs) + Invoice/Shipping Data Model

Data Entry creates requests through a 5-tab screen with trader lookup/snapshot, the full
invoice/shipping data model, and Full/Partial coverage % rules.

### Story 17-C.1: Invoice/shipping data model + 7 enums + ImportRequest foundation

As a developer,
I want the additive `import_requests` columns, the 7 new enums, and the updated `ImportRequest` model,
So that the new request data can be stored without affecting historical requests.

**Acceptance Criteria:**

**Given** the additive migration runs
**When** the schema is inspected
**Then** ~20 new columns exist (request_type, coverage_type, currency_source, payment_terms_mode, request_percentage, request_currency, requested_amount, invoice_type, invoice_currency, unit_of_measure, total_invoice_amount, commodity, exporting_company_name, exporting_company_location, country_of_origin, port_of_loading, port_of_arrival, incoterm, final_destination, shipping_date, arrival_date, trader_id + snapshot fields), all nullable/defaulted

**Given** the locked implementation decisions
**When** this foundation story is implemented
**Then** `request_percentage` is `DECIMAL(5,2)` (e.g. 5.00, 25.50, 100.00) — chosen for fractional-percentage future-proofing — and the trader snapshot columns use explicit `trader_snapshot_`-prefixed names: `trader_snapshot_name`, `trader_snapshot_tax_number`, `trader_snapshot_tax_card_expiry`, `trader_snapshot_commercial_registration_number`, `trader_snapshot_commercial_registration_expiry` (no mixed naming conventions)

**Given** the voting-rule gating decision (used by Epic 17-E)
**When** the migration runs
**Then** an explicit `voting_rule_version` column is added to `import_requests` — legacy requests = version 1, new National Committee requests = version 2 — and NOT a created_at threshold

**Given** the 7 new enums (RequestType, CoverageType, CurrencySource, PaymentTermsMode, InvoiceType, PortOfArrival, Incoterm)
**When** their `.label()` is called
**Then** each returns a bilingual Arabic/English label following existing enum conventions, and `ImportRequest` exposes the new fillable/casts + `trader()` relationship while `merchant_id` remains valid (both nullable, coexist by era)

### Story 17-C.2: Backend request create/update with trader snapshot + coverage rules

As a Data Entry officer,
I want the request create/update API to accept the invoice/shipping fields, snapshot the looked-up
trader, and enforce Full/Partial coverage rules,
So that a new request stores complete, point-in-time data.

**Acceptance Criteria:**

**Given** `StoreImportRequest` validation + the snapshot builder in `TraderService`
**When** a request is created/updated with a `trader_id`
**Then** the trader's data is snapshotted onto the request row at create/submit and later trader edits never mutate this request

**Given** the coverage rules
**When** coverage_type = Full
**Then** request_percentage is 100 (readonly); **and when** coverage_type = Partial **then** request_percentage must be `≥5 and <100`, rejected otherwise

**Given** `ImportRequestResource`
**When** a request is serialized
**Then** it includes the invoice/shipping/snapshot fields and continues to emit stored status codes (labels resolved at display)

### Story 17-C.3: 5-tab form shell + Basic tab (trader lookup) + Workflow History tab

As a Data Entry officer,
I want a 5-tab request screen whose first tab does tax-number lookup/autofill and whose last tab shows
workflow history,
So that I start a request from a trader and can see its lifecycle.

**Acceptance Criteria:**

**Given** `RequestFormTabs.vue` with tabs Basic / Invoice / Shipping / Documents / Workflow History
**When** the form mounts at `/requests/new`
**Then** the 5-tab shell renders using shadcn-vue, replacing the prior 4-step wizard

**Given** Tab 1 (Basic)
**When** I enter a tax number and trigger lookup
**Then** the trader + companies + owners autofill and snapshot into the form state (no live binding after autofill)

**Given** Tab 5 (Workflow History)
**When** it renders for an existing request
**Then** it reuses the existing workflow timeline component

### Story 17-C.4: Invoice, Shipping, and Documents tabs

As a Data Entry officer,
I want the Invoice, Shipping, and Documents tabs,
So that I can capture the full invoice/shipping data and attach required documents.

**Acceptance Criteria:**

**Given** Tab 2 (Invoice)
**When** I select coverage type
**Then** Full locks percentage at 100 (readonly) and Partial requires `≥5% & <100%`, with invoice fields (type, currency, total, number, unit of measure, commodity) captured

**Given** Tab 3 (Shipping)
**When** I fill shipping data
**Then** ports and incoterm use the new enums and shipping/arrival dates + final destination are captured

**Given** Tab 4 (Documents)
**When** I upload documents
**Then** a fixed set of 5 mandatory + ~9 optional slots is shown, PDF-only enforced client- and server-side, reusing the existing upload + checksum behavior

---

## Epic 17-D: Global Duplicate Prevention & Partial-Financing Ledger

A new request is blocked when the global financing % for its (tax number + invoice number) would
exceed 100% across all banks; the ledger is a derived query guarded by `FinancingLedgerService`.

### Story 17-D.1: FinancingLedgerService (derived query + concurrency protocol) + composite index

As a developer,
I want a `FinancingLedgerService` that computes the global financing total via a derived query under a
correct locking protocol, plus the composite index,
So that the ≤100% rule can be enforced correctly and concurrently.

**Acceptance Criteria:**

**Given** `FinancingLedgerService` and the composite index `(tax_number, invoice_number)`
**When** the global financing total for an invoice key is computed
**Then** it sums `request_percentage` from `import_requests` directly (no separate ledger table), excluding the `not_eligible_set` per the locked business rule: **requests that can no longer consume financing capacity (all terminal non-approved outcomes) free their percentage allocation**. The exact `RequestStatus` cases (e.g. `BANK_REJECTED`, `SUPPORT_REJECTED`, `EXECUTIVE_REJECTED`, `DRAFT_REJECTED_INTERNAL`, and any other terminal non-approved status) are mapped against the existing enum in this story and listed explicitly

**Given** a concurrent submission for the same invoice key
**When** the protocol runs
**Then** it acquires a named invoice-key lock (MySQL advisory lock, reusing the Story 3.6 pattern), then row-locks matching rows, sums AFTER locking, validates `existing_sum + new% ≤ 100`, and commits in one transaction — never `SUM(...) FOR UPDATE`

**Given** the empty-set first-insert case (no rows yet for the key)
**When** two banks submit simultaneously
**Then** the named lock serializes them so the combined % cannot exceed 100 (phantom-insert race closed), proven by a concurrency test

**Given** the org-scope exception
**When** the service runs the global query
**Then** it is the only cross-bank read path, documented in the service docblock, and returns aggregate %/violation only — never foreign request rows

### Story 17-D.2: Duplicate prevention + invoice-key guard wired into submit

As the platform,
I want request submission to enforce the global ≤100% limit and invoice-key consistency,
So that over-financing and inconsistent duplicates are blocked.

**Acceptance Criteria:**

**Given** `DuplicateDetectionService` extended to prevention + `FinancingLimitRule` wired into `StoreImportRequest`
**When** a new request would push the global sum over 100%
**Then** submission is blocked with HTTP 422 `FINANCING_LIMIT_EXCEEDED`

**Given** multiple partial-financing requests on one invoice key
**When** the global sum remains ≤100%
**Then** they are allowed

**Given** a new request sharing an invoice key with existing requests
**When** the invoice currency, total, number, or tax number does not match
**Then** submission is blocked with HTTP 422 `DUPLICATE_INVOICE_MISMATCH`, using the existing `{ success, message, error_code }` envelope

### Story 17-D.3: Financing % utilization UI

As a Data Entry officer,
I want a financing utilization indicator with a low-remaining warning and submit-block,
So that I can see remaining global capacity before submitting.

**Acceptance Criteria:**

**Given** the financing-utilization GET endpoint (backend, delegating to `FinancingLedgerService` from Story 17-D.1) plus `useFinancingLedger` + `FinancingUtilizationBar.vue` consuming it
**When** I enter a tax number + invoice number on the request form
**Then** the endpoint returns aggregate only (`used_percent`, `remaining_percent`, `blocked`) and the bar shows used %, remaining %, and a low-remaining warning, using aggregate data only (no foreign rows)

**Given** the global remaining capacity is insufficient for my entered %
**When** I attempt to submit
**Then** the UI blocks submission and explains why, while the backend D2 check remains authoritative (the bar is advisory)

---

## Epic 17-E: Workflow Authority Reform

The committee decision flow changes: Internal Reviewer no-reject, Support forward-only, Executive
majority `floor(n/2)+1` with no tie-break, plus display changes — all new-requests-only, via
`WorkflowService::transition()`, with historical requests preserved.

### Story 17-E.1: Internal Reviewer no-reject

As the platform,
I want the Internal Reviewer to only approve-continue or return-to-Data-Entry (no reject),
So that bank-stage terminal rejection is removed per the new authority model.

**Acceptance Criteria:**

**Given** `TransitionMap` + `ImportRequestPolicy`
**When** a BANK_REVIEWER acts on a request in `BANK_REVIEW`
**Then** only approve-continue and `return_to_data_entry` are available; `bank_reject` and `bank_reject_terminal` are removed from the reviewer's available actions (enum cases retained for history)

**Given** the bank-reviewer request-detail UI
**When** it renders for a new-rule request
**Then** the terminal-reject destructive action is not rendered, and the change routes through `WorkflowService::transition()` with stage-history + audit entries

**Given** a historical request previously terminally rejected
**When** it is viewed
**Then** it still renders correctly with its existing status and audit trail

### Story 17-E.2: Support Committee comment + forward-only

As the platform,
I want the Support Committee to comment and forward to Executive (no approve/reject decision),
So that support loses decision authority per the new model.

**Acceptance Criteria:**

**Given** `TransitionMap` + policy
**When** a SUPPORT_COMMITTEE member finishes review of a new-rule request
**Then** the only decision transition is `support_forward_to_executive`; `support_approve` and `support_reject` are removed from available actions (cases retained for history) and the member's comment is recorded to audit

**Given** the support-committee UI
**When** it renders for a new-rule request
**Then** "Support Approval", "Reject", and "Open Voting" buttons are replaced by a single "إرسال إلى اللجنة التنفيذية / Send to Executive Committee" action with comment capture

**Given** the SUPPORT_REJECTED-dependent surfaces (bank-reviewer `support_rejected` tab, "رُفض من المساندة" KPI, SupportRejectedBanner, support-rejection notification, dependent counters)
**When** the app renders for new-rule requests
**Then** they are removed/hidden, while historical `SUPPORT_REJECTED` requests still render correctly and keep their audit trail

### Story 17-E.3: Executive voting majority floor(n/2)+1, no tie-break

As the platform,
I want executive voting to resolve by simple majority with no tie-break for new requests,
So that decisions follow the new Approved/Not-Eligible rule while legacy requests are unaffected.

**Acceptance Criteria:**

**Given** `VotingService` finalization for a new-rule request
**When** approvals `≥ floor(total_eligible_members/2)+1`
**Then** the outcome is APPROVED; otherwise NOT-ELIGIBLE (6 members → 4 required; 8 → 5; 10 → 6)

**Given** an even split or any sub-majority on a new-rule request (3v3, 4v4, 5v5)
**When** the session is finalized
**Then** the outcome is NOT-ELIGIBLE and the Director tie-break/override controls are not rendered or invokable for new-rule requests

**Given** the locked gating mechanism — the explicit `voting_rule_version` column from Story 17-C.1 (legacy = version 1, new National Committee = version 2; NOT a created_at threshold)
**When** a `voting_rule_version` = 1 (legacy) request is finalized
**Then** it uses the prior rule including Director tie-break, with no retroactive recompute of closed sessions; **and when** `voting_rule_version` = 2 **then** the new `floor(n/2)+1` no-tie-break rule applies

**Given** the executive voting UI
**When** a member votes on a new-rule session
**Then** only Approve / Not Eligible are shown (Abstain hidden; AUTO_ABSTAIN_TIMEOUT case retained for history), and the full backend test suite passes (workflow-critical)

### Story 17-E.4: Workflow display — "Returned to Data Entry" + SWIFT stage merge

As a platform user,
I want the return label to read "Returned to Data Entry" and the SWIFT upload/uploaded stages to show
as one display stage,
So that the workflow display matches the new model without changing stored statuses.

**Acceptance Criteria:**

**Given** the Internal Reviewer return path
**When** the status/label renders (banners, tabs, timeline, status chip)
**Then** it reads "Returned to Data Entry" / "أُعيد إلى مدخل البيانات" instead of "Returned for Review"

**Given** the workflow timeline / `constants/workflow.ts`
**When** the SWIFT stages render
**Then** `WAITING_FOR_SWIFT` and `SWIFT_UPLOADED` collapse into a single displayed "SWIFT Uploaded" stage, while the underlying statuses and granular audit entries are unchanged

**Given** all Epic 17-E changes
**When** reviewed
**Then** no `current_status` is mutated directly and no enum case is renamed (all via `WorkflowService::transition()` / `TransitionMap`)

---

## Epic 17-F: Terminology & Label Layer

Every user-facing "Reject/Rejected" surface reads "غير مستوفي للشروط / Not Eligible", from a single
label source per layer, with banned synonyms prohibited and stored codes frozen.

### Story 17-F.1: Backend Not-Eligible terminology

As a platform owner,
I want backend display labels (enum `.label()`, notification templates, report/export headers) to use
"Not Eligible",
So that all backend-produced text uses the new terminology without changing stored codes.

**Acceptance Criteria:**

**Given** `RequestStatus` rejection cases and `VoteType`
**When** their `.label()` is rendered
**Then** they read "غير مستوفي للشروط" / "Not Eligible" (no case or DB value renamed)

**Given** notification templates and report/export headers
**When** they reference a rejection outcome
**Then** they use the Not-Eligible terminology while reports/exports/APIs continue emitting stored codes

**Given** the backend label sources
**When** reviewed
**Then** each label lives in exactly one place (enum `.label()`), with no duplicated label strings

### Story 17-F.2: Frontend Not-Eligible terminology + banned-synonyms guard

As a platform user,
I want all frontend rejection copy to read "Not Eligible" with banned synonyms prohibited,
So that the UI is terminologically consistent.

**Acceptance Criteria:**

**Given** `frontend/app/constants/workflow.ts` + TypeScript types + UI surfaces (badges, banners, tabs, KPI labels, notification copy)
**When** a rejection outcome renders
**Then** it reads "غير مستوفي للشروط" / "Not Eligible" from the single frontend label source

**Given** the terminology-validation rule
**When** the codebase/UI copy is checked
**Then** the forbidden synonyms — "Rejected", "Declined", "Disapproved", "Not Approved" (and Arabic "مرفوض") — do not appear in any user-facing string

**Given** stored enum codes such as `EXECUTIVE_REJECTED`
**When** the terminology pass is applied
**Then** the data-layer codes remain frozen (display strings only), and the change is committed to both the frontend team repo and the root monorepo
