---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
lastStep: 8
status: 'complete'
completedAt: '2026-06-07'
inputDocuments:
  - _bmad-output/planning-artifacts/sprint-change-proposal-2026-06-07-national-committee-rebrand-trader-workflow.md
  - _bmad-output/planning-artifacts/project-context.md
  - docs/01-workflow-and-business-rules.md
  - docs/02-system-architecture.md
  - docs/03-database-and-models.md
  - docs/05-backend-guide.md
  - docs/06-api-reference.md
workflowType: 'architecture'
project_name: 'Yemen Flow Hub → National Committee for Regulating & Financing Imports'
user_name: 'MAJED'
date: '2026-06-07'
scope: 'National Committee Re-Scope (Rebrand + Trader Module + 5-Tab Request Form + Invoice/Shipping Model + Global Financing Ledger + Workflow Authority Reform + Terminology Layer) — Epics A–F'
---

# Architecture Decision Document — National Committee Re-Scope (Epics A–F)

_Brownfield architecture for the re-scope from Yemen Flow Hub (CBY FX/customs tool) to **The National Committee for Regulating & Financing Imports** (اللجنة الوطنية لتنظيم وتمويل الواردات). Implements `sprint-change-proposal-2026-06-07-national-committee-rebrand-trader-workflow.md`. The prior email/notification subsystem architecture is archived at `architecture-email-subsystem-archive.md`._

_This document builds collaboratively through step-by-step discovery. Sections are appended as we work through each architectural decision together._

## Scope

Brownfield re-scope of Yemen Flow Hub → The National Committee for Regulating & Financing
Imports. Implements the 2026-06-07 sprint-change-proposal across six sequenced epics:
A Rebrand · B Trader Module · C 5-Tab Form + Invoice/Shipping Model · D Global Financing
Ledger · E Workflow Authority Reform · F Terminology Label Layer. Three LOCKED decisions
(supplied via correct-course) are treated as approved constraints and are NOT re-litigated:
1. Reject→Not Eligible = label-layer ONLY (enum cases + DB values frozen, zero migration).
2. New Trader tables, keep Merchant (snapshot trader data into new requests).
3. Voting majority floor(n/2)+1 = NEW REQUESTS ONLY (no retroactive recompute).

**Canonical "Not Eligible" label:** Arabic `غير مستوفي للشروط` / English `Not Eligible`. This
display string replaces "Reject/Rejected" user-facing copy ONLY; enum cases (`EXECUTIVE_REJECTED`,
`SUPPORT_REJECTED`, `BANK_REJECTED`) and stored DB values are frozen.

## Project Context Analysis

### Requirements Overview

**Brownfield note:** This re-scope layers onto a shipped 16-epic platform. No standalone PRD
exists; requirements source = this sprint-change-proposal + docs/ + epics.md. Closed epics are
NOT edited; a new epic set (A–F) is layered on top. All changes preserve existing shipped
behavior for historical rows.

**Functional Requirements (derived from proposal §4 Epics A–F):**

| Epic | FR | Architectural implication |
|---|---|---|
| A | Full bilingual rebrand across every surface (title, login, sidebar, header, emails, notifications, PDFs, reports, exports, settings, docs) | String-level only; no schema. Labels are hardcoded bilingual strings (NO i18n framework exists). |
| B | Trader Management module: global `traders` (tax_number = global unique identifier), `trader_companies` (1:N), `trader_owners` (1:N, ownership %) | New global tables + `TraderService` + controllers + policies + CRUD UI. CRUD perms: DATA_ENTRY, BANK_REVIEWER, BANK_ADMIN. |
| C | 5-tab request screen (Basic/Invoice/Shipping/Documents/Workflow History) + ~20 additive `import_requests` columns + 7 new additive enums | Additive migration (all nullable/defaulted). Tab1 tax-number lookup → trader autofill → SNAPSHOT into request row (request edits never mutate trader). Full=100% readonly; Partial ≥5% & <100%. |
| D | Global cross-bank duplicate prevention + partial-financing ledger: Σ financing % per (tax_number, invoice_number) ≤ 100% across ALL banks | Net-new `FinancingLedgerService`; extend `DuplicateDetectionService` detection→prevention. Concurrency-safe (lock/transactional). Composite index. Intentionally bypasses org-scope (must be access-guarded + documented). |
| E | Workflow authority reform: Internal Reviewer no-reject (approve-continue + RETURN_TO_DATA_ENTRY only); Support Committee comment+forward only (no decision); Executive voting Approved/Not-Eligible with floor(n/2)+1; "Returned for Review"→"Returned to Data Entry"; merge SWIFT Upload+Uploaded display | TransitionMap edits (strip `support_approve`/`support_reject`→`support_forward_to_executive`; remove `bank_reject`/`bank_reject_terminal` from reviewer actions, KEEP cases for history). VotingService majority = real logic change. All via WorkflowService::transition(). |
| F | Terminology: Reject/Rejected → "غير مستوفي للشروط / Not Eligible" everywhere user-facing | Display strings only in enum `.label()`, `frontend/app/constants/workflow.ts`, types, notification templates, report/export headers. NO enum case/DB value renamed; APIs still emit stored codes. |

**Non-Functional Requirements:**

| NFR | Driver |
|---|---|
| Backward-compat invariant: frozen enum cases + nullable columns → historical rows render/function unchanged, zero migration | Audit immutability + de-risking the MAJOR re-scope |
| Voting rule = new requests only; closed sessions never recomputed | Regulatory finality of past decisions |
| Org-scope preserved EVERYWHERE except financing-ledger duplicate check (intentional, documented, access-guarded) | The one deliberate org-scope exception in the platform |
| Concurrency-safe global % ledger under cross-bank races (lock or transactional check) | Ledger correctness is the highest-risk surface |
| All transitions via `WorkflowService::transition()`; immutable stage_history + audit_logs | Existing workflow doctrine, non-negotiable |
| RBAC unchanged — no new roles; reuse existing 8-role enum | Constraint: authority changes are transition/policy edits, not role edits |
| Audit logs immutable; only display labels change, stored action/status codes unchanged | Compliance: terminology is presentation-layer |
| Trader snapshot: request-level data is point-in-time; trader edits never mutate historical requests | Data integrity for issued requests |
| Reuse existing design system / shadcn-vue (no new component library) | UI consistency + YAGNI |

**Scale & Complexity:**

- Primary domain: **full-stack** (Laravel 11 backend-heavy: enums/migrations/services/transition-map/voting; Nuxt 4 frontend: Trader pages + 5-tab form + % utilization UI).
- Complexity level: **HIGH (enterprise / compliance-driven)** — concentrated in (a) global financing-% ledger correctness under cross-bank concurrency, (b) workflow-authority removal touching TransitionMap + extensive tests, (c) voting algorithm change.
- New architectural components: **~6 core** — `TraderService`, `FinancingLedgerService`, Trader table set (traders/companies/owners), ledger (query or table), modified VotingService/TransitionMap/DuplicateDetectionService, 7 new enums, 5-tab request component.

### Technical Constraints & Dependencies

- **MUST reuse:** `WorkflowService::transition()` for all state changes; existing `RequestStatus` (22 cases) + `VoteType` (4 cases) enums FROZEN; existing 8-role RBAC; `audit_logs` + `request_stage_history` spine; existing shadcn-vue design system; `Merchant` model (retained for historical requests).
- **MUST NOT:** rename any enum case or DB status/vote value; migrate historical data; add new roles; introduce an i18n framework (labels stay hardcoded bilingual); break org-scope anywhere except the documented ledger exception; recompute closed voting sessions.
- **No i18n framework** (no vue-i18n / no locale json) → rebrand + terminology = string edits in enum `.label()` + `frontend/app/constants/workflow.ts` + types + notif templates + report headers.
- **Existing partial infra:** `add_index_invoice_number` + `import_request_reference_sequences` migrations already exist (partial ledger infra present).
- **Ground-truth deltas (verified in code):** `VotingService` today = `approve>reject` + director tiebreak, NO quorum/majority → real change. `DuplicateDetectionService` = detection only, no prevention/no % ledger → net-new engine. `ImportRequest` flat → ~20 additive columns. `TransitionMap` has `support_approve`/`support_reject` (→ strip to forward-only) + `bank_reject`/`bank_reject_terminal` (→ remove from reviewer, keep cases).

### Cross-Cutting Concerns Identified

1. **Backward-compatibility** — spans every epic: frozen enums (F), nullable columns (C), new-requests-only voting (E), retained Merchant (B). The de-risking spine of the whole re-scope.
2. **Org-scope vs. the global-ledger exception** — the financing ledger (D) is the single intentional org-scope break; must be access-guarded + explicitly documented so it is never mistaken for a leak.
3. **Trader snapshot boundary** — trader data is global/mutable, but request-linked trader data is a point-in-time snapshot (B+C); governs the form, the migration, and data integrity.
4. **Concurrency** — ledger % validation (D) + voting closure (E) both need lock/transactional guarantees under cross-bank / multi-actor races.
5. **Label layer as presentation-only** — terminology (A+F) changes display strings only; stored codes, APIs, audit entries, exports-by-code unchanged. Governs enum `.label()` + frontend constants + notif templates.
6. **WorkflowService as the sole transition chokepoint** — authority reform (E) is implemented purely as TransitionMap + policy + voting-rule edits routed through `WorkflowService::transition()`; agents never mutate `current_status` directly.

## Starter Template Evaluation

### Verdict: NOT APPLICABLE — brownfield re-scope

No starter template applies. Epics A–F are built inside the existing Laravel 11 backend
(`backend/`) and Nuxt 4 frontend (`frontend/`). The stack is locked by the shipped 16-epic
platform; there is no greenfield scaffold to initialize and no init story. Reframed as:
inherited foundation vs. NEW components this re-scope introduces.

### Technology Foundation (inherited, not chosen)

| Layer | Tech | Status |
|---|---|---|
| Runtime | PHP 8.2+, Laravel 11 | Inherited |
| Auth | Laravel Sanctum | Inherited |
| DB | MySQL | Inherited |
| Cache / queue / TTL | Redis | Inherited |
| Workflow engine | `WorkflowService` + `TransitionMap` (state machine) | Inherited, EXTEND |
| Voting engine | `VotingService` | Inherited, MODIFY (majority rule) |
| Duplicate engine | `DuplicateDetectionService` | Inherited, EXTEND (detection→prevention) |
| Trader (legacy) | `Merchant` model (bank-scoped, flat) | Inherited, RETAINED for history |
| PDF | barryvdh/laravel-dompdf | Inherited |
| Frontend | Nuxt 4 / Vue 4 / TypeScript / Tailwind v4 / shadcn-vue | Inherited |
| State / forms | Pinia, VueUse, VeeValidate, Zod | Inherited |
| i18n | NONE (hardcoded bilingual strings in enums + frontend constants) | Inherited constraint |

### NEW Components This Re-Scope Introduces (no new framework)

| Component | Built on | Epic |
|---|---|---|
| `traders` / `trader_companies` / `trader_owners` tables + models | Eloquent + migrations | B |
| `TraderService` + controllers + policies | native Laravel services | B |
| 7 additive enums (RequestType, CoverageType, CurrencySource, PaymentTermsMode, InvoiceType, PortOfArrival, Incoterm) | native PHP enums | C |
| ~20 additive `import_requests` columns + trader snapshot fields | additive migration (nullable) | C |
| `FinancingLedgerService` (global cross-bank % validation, org-scope exception) | native service + DB lock/transaction | D |
| `DuplicateDetectionService` prevention extension + composite index | extend existing service | D |
| `VotingService` majority `floor(n/2)+1` (new requests only) | modify existing service | E |
| `TransitionMap` authority edits + `support_forward_to_executive` action | modify existing map | E |
| 5-tab request form component + Trader CRUD pages + % utilization UI | shadcn-vue (existing) | C, B, D |
| Bilingual label updates (enum `.label()`, `frontend/app/constants/workflow.ts`) | string edits | A, F |

### Library / Dependency Decision

**Zero new dependency.** Every NEW component is built on the inherited stack:
- Trader module = Eloquent + native services (mirrors existing `Merchant`/`Bank` patterns).
- New enums = native PHP backed enums (mirrors existing `RequestStatus`/`VoteType`).
- Financing ledger = native MySQL transactional/lock primitives (mirrors existing voting-closure
  pessimistic locking) — no external lock library.
- 5-tab form + Trader pages = existing shadcn-vue + VeeValidate + Zod (mirrors existing RequestForm).
- Rebrand/terminology = string edits; NO i18n framework added (locked constraint).

**Rationale:** A government compliance platform favors a minimal supply chain. The inherited stack
already provides every primitive these epics need (KISS + YAGNI). Adding a library would expand the
audit surface for capabilities the existing patterns already cover.

**Note:** No project-initialization story exists (brownfield). The first implementation story is the
Epic A rebrand (lowest risk, string-level), per the proposal's A→B→C→D→E→F sequencing.

## Core Architectural Decisions

### Decision Priority Analysis

**Critical (block implementation):**
- D1 — Financing ledger = derived query over `import_requests`, NOT a separate table.
- D2 — Ledger concurrency = named invoice-key lock + row-lock + sum-AFTER-lock inside the submit transaction (NOT `SUM(...) FOR UPDATE`).
- D3 — Trader snapshot = trader fields copied onto the request row at create/submit (`trader_id` FK + denormalized snapshot columns).
- D4 — Voting majority `floor(total_eligible/2)+1` gated per-request (new requests only); legacy/closed sessions keep prior behavior.

**Important (shape architecture):**
- D5 — Authority reform = TransitionMap + Policy edits only (enum cases frozen).
- D6 — Terminology = single label source per layer (enum `.label()` backend; `frontend/app/constants/workflow.ts` frontend).
- D7 — 7 new enums = additive native PHP backed enums, SCREAMING_SNAKE + bilingual `.label()`.
- D8 — SWIFT stage merge = display-only (statuses + audit unchanged).

**Deferred:**
- Promoting derived ledger to a materialized/cached table (revisit only if query cost proves real at volume).
- Dynamic document-type configuration (Tab4 ships a fixed 5-mandatory + ~9-optional set now; proposal flags "future-dynamic").

### Data Architecture

- **D1 — Financing ledger (derived query):** the global cross-bank financing total is computed
  on demand from `import_requests` rows — there is NO `request_financing_ledger` table, no sync, no
  reconciliation. `import_requests` is the single source of truth. A composite index on
  `(tax_number, invoice_number)` makes the aggregate cheap. Rows in Not-Eligible / terminal-rejected
  states are excluded from the sum (they free capacity).
- **D2 — Ledger concurrency (correct locking protocol):** `SELECT SUM(...) FOR UPDATE` is NOT used —
  an aggregate result row is not the matched request rows, so it does not lock them, and it cannot
  lock rows that do not yet exist (the empty-set / phantom-insert race). The required protocol is:
  1. Begin DB transaction.
  2. Acquire a **named lock for the invoice key** `(tax_number, invoice_number)` — a MySQL advisory
     lock (`GET_LOCK("financing:{tax_number}:{invoice_number}", timeout)`) or a dedicated
     invoice-key sentinel lock row. This is mandatory because `FOR UPDATE` cannot lock rows that do
     not exist yet; the named lock serializes concurrent first-inserts for the same key across banks.
     (Reuse the existing MySQL advisory-lock pattern already used for external FX confirmation
     issuance in Story 3.6 — do not invent a new locking mechanism.)
  3. `SELECT ... FROM import_requests WHERE tax_number=? AND invoice_number=? AND current_status
     NOT IN (<not_eligible_set>) FOR UPDATE;` — lock the actual matched rows.
  4. Sum `request_percentage` over the locked rows (application code, or a second aggregate AFTER the
     rows are locked).
  5. Validate `existing_sum + new_request_percentage <= 100`; on violation throw a domain error
     (HTTP 422 `FINANCING_LIMIT_EXCEEDED`) and roll back.
  6. Insert / submit the new request via `WorkflowService::transition()` in the SAME transaction.
  7. Commit (releases row locks; release the named lock).
  This serializes both the "rows already exist" case (step 3 row-lock) and the "no rows yet" case
  (step 2 named lock), closing the phantom-insert race that a bare `FOR UPDATE` leaves open.
- **D3 — Trader snapshot:** new requests carry `trader_id` (FK, lineage) PLUS denormalized snapshot
  columns copied from the trader at create/submit (trader name, tax_number, registration number +
  expiry, etc.). Editing a trader later never mutates an existing request. `Merchant` is retained
  untouched for historical (pre-trader) requests; `merchant_id` and `trader_id` are mutually-present
  by request era, both nullable.
- **Additive migration (Epic C):** ~20 nullable/defaulted columns on `import_requests` (request_type,
  coverage_type, currency_source, payment_terms_mode, request_percentage, request_currency,
  requested_amount, invoice_type, invoice_currency, unit_of_measure, total_invoice_amount, commodity,
  exporting_company_name, exporting_company_location, country_of_origin, port_of_loading,
  port_of_arrival, incoterm, final_destination, shipping_date, arrival_date, trader_id + snapshot
  fields). All nullable → historical rows stay valid, zero data migration.
- **New tables (Epic B):** `traders` (tax_number unique global identifier, trader_name,
  tax_card_expiry, commercial_registration_number, commercial_registration_expiry),
  `trader_companies` (trader_id FK, company_name), `trader_owners` (trader_id FK, full_name,
  ownership_percentage, nationality?, identification_number?).
- **Indexes:** composite `(tax_number, invoice_number)` on `import_requests` (D1/D2); unique
  `traders.tax_number`; FK indexes on trader_companies/owners. `add_index_invoice_number` already
  exists — extend, don't duplicate.

### Authentication & Security

- **Org-scope exception (the single deliberate break):** the D1 ledger query is GLOBAL — it sums
  across ALL banks, intentionally bypassing `scopeForUser()`. This is the only place the platform
  reads cross-org data. It MUST be: (a) reachable only through `FinancingLedgerService` (never an
  ad-hoc query), (b) used only for the duplicate/% validation + the UI % indicator, (c) NEVER expose
  another bank's request details — it returns aggregate % and a boolean/violation, not foreign rows,
  (d) explicitly documented as an intentional exception in the service docblock + this architecture.
- **RBAC unchanged:** no new roles. Trader CRUD permitted to DATA_ENTRY, BANK_REVIEWER, BANK_ADMIN
  via new policies on the existing role enum. Authority reform (D5) is enforced in policies +
  TransitionMap, never in the UI alone.
- **Audit immutability:** terminology (D6) and SWIFT merge (D8) are display-only; stored
  `audit_logs` / `request_stage_history` action + status codes are unchanged.

### API & Communication Patterns

- **D2 — Submit path (concurrency):** request submission runs the full named-lock + row-lock +
  sum-after-lock + validate + transition protocol (see Data Architecture D2) in one DB transaction.
  Mirrors the existing pessimistic-lock + advisory-lock patterns already in the codebase.
- **D4 — Voting majority gating:** `VotingService` finalization computes APPROVED when
  approve-votes `≥ floor(total_eligible/2)+1`, else NOT-ELIGIBLE — applied only to requests flagged
  as new-rule (created after the feature lands; gate via created_at threshold or an explicit
  `voting_rule_version` attribute). Closed sessions are never recomputed. Director tiebreak/legacy
  path remains for old-rule requests.
- **Error envelope:** reuse the existing `{ success, message, error_code }` wrapper; new codes
  `FINANCING_LIMIT_EXCEEDED`, `DUPLICATE_INVOICE_MISMATCH` (currency/total/number/tax mismatch on a
  shared invoice key). No new envelope shape.
- **Forwarding action (D5):** new transition action `support_forward_to_executive` replaces the
  Support Committee's decision actions; comment still recorded to audit. New `RETURN_TO_DATA_ENTRY`
  is the only non-forward action available to the Internal Reviewer besides approve-continue.

### Frontend Architecture

- **5-tab request form (D, Epic C):** one parent component, five tab panels
  (Basic / Invoice / Shipping / Documents / Workflow History) built on existing shadcn-vue +
  VeeValidate + Zod (mirror existing `RequestForm`). Tab1 tax-number lookup → `TraderService`
  autofill → snapshot into form state. Full coverage = 100% readonly; Partial = `≥5% & <100%`.
  Tab5 reuses the existing workflow timeline component.
- **% utilization UI (D, Epic D):** the financing indicator + low-remaining warning + submit-block
  read the GLOBAL remaining % via the ledger endpoint (aggregate only, no foreign rows). Advisory in
  the UI; the D2 named-lock + row-lock check at submit is the authority.
- **Trader CRUD pages (Epic B):** list/create/edit/view with companies + owners sub-forms; existing
  table/modal/dialog patterns, no new component library.
- **Terminology (D6) + SWIFT merge (D8):** all label changes flow through
  `frontend/app/constants/workflow.ts` (single frontend label source). SWIFT merge collapses the two
  SWIFT timeline nodes into one display node; underlying statuses untouched.
- **State management:** extend existing Pinia stores (`requests`, plus a new `traders` store);
  reuse existing composable patterns (`useRequests` → `useTraders`).

### Infrastructure & Deployment

- No infra change. Same MySQL + Redis + Laravel/Nuxt deployment. The D1 ledger is a query against
  the existing primary DB; the D2 protocol uses native InnoDB row locks + MySQL named/advisory locks
  (already used in the codebase) — no new service, no new queue, no new cache topology. Redis stays
  as-is (no ledger cache in Phase 1 — Deferred).
- Migrations are additive and reversible; deploy order follows epic sequence A→B→C→D→E→F.

### Decision Impact Analysis

**Implementation sequence (matches proposal A→F):**
1. Epic A — rebrand strings (enum `.label()` + `frontend/app/constants/workflow.ts`). Lowest risk.
2. Epic B — Trader tables + `TraderService` + policies + CRUD pages (D3 foundation; precedes C).
3. Epic C — additive `import_requests` migration + 7 enums (D7) + 5-tab form + trader snapshot (D3).
4. Epic D — composite index + `FinancingLedgerService` (D1) + `DuplicateDetectionService` prevention
   + D2 submit-lock protocol + % UI. (Gates submission, so after C.)
5. Epic E — TransitionMap + Policy authority edits (D5) + `VotingService` majority (D4) + SWIFT
   display merge (D8). Full test suite (workflow-critical).
6. Epic F — finalize terminology labels (D6) after workflow labels settle.

**Cross-component dependencies:**
- D3 snapshot (C) depends on the Trader tables (B) existing — B precedes C.
- D1/D2 ledger (D) depends on `request_percentage` + duplicate-key columns from C — C precedes D.
- D4 voting gating (E) is independent of D but shares the "new-requests-only" backward-compat
  invariant with the locked decisions.
- D6 terminology (F) finalizes after E because workflow labels ("Returned to Data Entry",
  forward-only) settle in E.

## Implementation Patterns & Consistency Rules

### Critical Conflict Points Identified

8 areas where AI agents could diverge on this re-scope. Naming is inherited from the existing
codebase and restated here to bind agents. Verified conventions: backed PHP enums with
SCREAMING_SNAKE cases + bilingual `.label()`; snake_case tables/columns; `{Domain}Service` classes
in `App\Services\`; migrations `YYYY_MM_DD_NNNNNN_verb_noun`; frontend constants in
`frontend/app/constants/`; `useX` composables + Pinia stores; response wrapper
`{ success, message, data }` / error `{ success, message, error_code }`.

### Naming Patterns

**New enums (Epic C, D7):** backed PHP enums, SCREAMING_SNAKE cases, bilingual `.label()` —
matching `RequestStatus` / `VoteType`. Files `app/Enums/{PascalCase}.php`:
`RequestType`, `CoverageType`, `CurrencySource`, `PaymentTermsMode`, `InvoiceType`,
`PortOfArrival`, `Incoterm`. NOT `requestType` / kebab. Each case carries `label(): string`
returning `"عربي / English"` form consistent with existing enums.

**DB tables/columns:** snake_case plural tables — `traders`, `trader_companies`, `trader_owners`.
Columns snake_case — `tax_number`, `commercial_registration_expiry`, `ownership_percentage`,
`request_percentage`, `port_of_arrival`. FK = `{singular}_id` — `trader_id`. Snapshot columns
prefixed for clarity where they shadow trader fields (e.g. `trader_snapshot_name` or documented
inline) — pick ONE convention in the Epic C foundation story and reuse.

**Migrations:** `YYYY_MM_DD_NNNNNN_verb_noun.php` — `..._create_traders_table.php`,
`..._add_invoice_shipping_fields_to_import_requests_table.php`,
`..._add_composite_index_tax_invoice_to_import_requests_table.php`.

**Services:** `{Domain}Service` in `App\Services\` — `TraderService`, `FinancingLedgerService`.
Modify existing in place — `VotingService`, `DuplicateDetectionService`, `WorkflowService`,
`TransitionMap`.

**Transition actions:** snake_case verb phrases matching existing TransitionMap keys —
`support_forward_to_executive`, `return_to_data_entry`. Do NOT invent camelCase action keys.
Frozen-but-retired actions (`bank_reject`, `bank_reject_terminal`, `support_approve`,
`support_reject`) stay defined for history; just removed from the actor's available-action set.

**Frontend:** components PascalCase `.vue` (`TraderForm.vue`, `RequestFormTabs.vue`); composables
`useTraders.ts`; Pinia store `traders` (`useTradersStore`); types in `app/types/`; labels in
`frontend/app/constants/workflow.ts`. Mirror existing `useRequests` / `requests` store shapes.

### Structure Patterns

```
backend/app/
  Enums/{RequestType,CoverageType,CurrencySource,PaymentTermsMode,InvoiceType,PortOfArrival,Incoterm}.php
  Services/
    TraderService.php
    FinancingLedgerService.php          (global; org-scope exception documented in docblock)
    VotingService.php                   (modify: floor(n/2)+1 gating)
    DuplicateDetectionService.php       (modify: detection → prevention)
    Workflow/{WorkflowService,TransitionMap}.php   (modify: authority reform)
  Models/{Trader,TraderCompany,TraderOwner}.php
  Policies/TraderPolicy.php
  Http/Controllers/Api/TraderController.php
  Http/Requests/{StoreTraderRequest,SubmitImportRequest...}.php   (FinancingLimit rule here)
frontend/app/
  components/trader/{TraderForm,TraderCompaniesField,TraderOwnersField}.vue
  components/request/RequestFormTabs.vue (+ tab panels)
  composables/useTraders.ts
  stores/traders.ts
  types/trader.ts
  constants/workflow.ts                 (modify: Not-Eligible labels, Returned-to-Data-Entry, SWIFT merge)
```

Tests: `backend/tests/Feature/{Trader,Financing,Workflow,Voting}/` + `Unit/` mirror existing split.

### Format Patterns

**Bilingual label form:** `"غير مستوفي للشروط / Not Eligible"` style — Arabic first, ` / `
separator, English second — matching existing enum `.label()` output. Single source per layer
(D6): backend enum `.label()`, frontend `constants/workflow.ts`. Never duplicate a label string
across files.

**Coverage/% rules:** `request_percentage` stored as integer or decimal — pick in Epic C
foundation; Full coverage = `100` (readonly in UI), Partial = `>= 5 && < 100`. Validation lives in
a Form Request rule + `FinancingLedgerService`, not scattered in the controller.

**API envelope:** reuse `{ success, message, data }` / `{ success, message, error_code }`. New
error codes: `FINANCING_LIMIT_EXCEEDED` (422), `DUPLICATE_INVOICE_MISMATCH` (422). APIs/exports
emit STORED status/vote codes; labels resolve at display only (D6).

### Communication Patterns

**All state transitions via `WorkflowService::transition()`** — agents NEVER mutate
`current_status` directly and NEVER add a transition outside `TransitionMap`. The new
`support_forward_to_executive` + `return_to_data_entry` actions are registered in `TransitionMap`
with role guards in policy; comments recorded to `audit_logs` + `request_stage_history`.

**Ledger access ONLY via `FinancingLedgerService`** — the global cross-bank sum is never queried
ad-hoc from a controller, model scope, or another service. The service is the single guarded
chokepoint for the org-scope exception, returns aggregate % / violation only (never foreign rows).

**Voting gating** — the new-rule check lives in `VotingService` only; agents do not branch on
voting rule version anywhere else.

### Process Patterns

**Concurrency (D2):** any new-request submission that touches the financing key goes through the
named-lock → row-lock → sum-after-lock → validate → transition protocol inside ONE transaction.
Agents reuse the existing MySQL advisory-lock helper (Story 3.6 FX-confirmation pattern); they do
NOT write a bare `SUM(...) FOR UPDATE` and do NOT skip the named lock for the empty-set case.

**Trader snapshot:** snapshot copy happens at create/submit in `TraderService` /
the submit handler — once. Agents never re-read the live trader to populate an existing request's
display; the request's snapshot columns are authoritative for that request.

**Backward-compat guard:** every additive column is nullable/defaulted; every label change is
display-only; voting rule is gated to new requests. Agents NEVER write a data migration that
backfills or rewrites historical rows, and NEVER rename an enum case or DB value.

**Validation timing:** Zod (frontend) for UX + Form Request (backend) for authority — both. The
financing-% and duplicate-invoice checks are authoritative on the BACKEND (Form Request rule +
`FinancingLedgerService`); the frontend % indicator is advisory only.

### Enforcement Guidelines

**All AI agents MUST:**
- Route every transition through `WorkflowService::transition()` + `TransitionMap`.
- Access the global financing sum ONLY through `FinancingLedgerService`.
- Use the named-lock + row-lock + sum-after-lock protocol for submit (never `SUM(...) FOR UPDATE`).
- Add new enums as backed PHP enums with bilingual `.label()`; keep frozen cases intact.
- Put labels in exactly one place per layer (enum `.label()` / `constants/workflow.ts`).
- Keep additive columns nullable; never migrate/rewrite historical rows.
- Reuse shadcn-vue + existing composable/store/Form-Request patterns.

**Anti-patterns (forbidden):**
- Mutating `current_status` directly or adding an out-of-map transition.
- `SUM(request_percentage) ... FOR UPDATE` as the ledger guard (locks nothing useful).
- Skipping the named lock → losing the empty-set / phantom-insert race.
- A separate `request_financing_ledger` table duplicating the requests source of truth.
- Renaming `EXECUTIVE_REJECTED` / `SUPPORT_REJECTED` / `BANK_REJECTED` (or any frozen case).
- Duplicating a label string across files; emitting labels (not codes) from APIs/exports.
- Re-reading a live trader to render a historical request (snapshot drift).
- Recomputing closed voting sessions under the new majority rule.
- Querying cross-bank request rows anywhere except the guarded ledger aggregate.

## Project Structure & Boundaries

### Complete Re-Scope Tree (✚ new, ✎ modify, · exists)

```
backend/
├── app/
│   ├── Enums/
│   │   ├── RequestStatus.php                       ✎  (F) .label() → "Not Eligible"; cases FROZEN
│   │   ├── VoteType.php                            ✎  (F) hide ABSTAIN in UI; cases FROZEN
│   │   ├── RequestType.php                         ✚  (C) backed enum + bilingual label()
│   │   ├── CoverageType.php                        ✚  (C) FULL | PARTIAL
│   │   ├── CurrencySource.php                      ✚  (C)
│   │   ├── PaymentTermsMode.php                    ✚  (C)
│   │   ├── InvoiceType.php                         ✚  (C)
│   │   ├── PortOfArrival.php                       ✚  (C)
│   │   └── Incoterm.php                            ✚  (C)
│   ├── Models/
│   │   ├── ImportRequest.php                       ✎  (C) + invoice/shipping/snapshot fillable + trader() rel
│   │   ├── Merchant.php                            ·  retained for historical requests
│   │   ├── Trader.php                              ✚  (B) hasMany companies/owners; tax_number unique
│   │   ├── TraderCompany.php                       ✚  (B)
│   │   └── TraderOwner.php                         ✚  (B)
│   ├── Services/
│   │   ├── TraderService.php                       ✚  (B) CRUD + snapshot builder
│   │   ├── FinancingLedgerService.php              ✚  (D) GLOBAL aggregate (org-scope exception)
│   │   ├── DuplicateDetectionService.php           ✎  (D) detection → prevention + invoice-match guard
│   │   ├── VotingService.php                       ✎  (E) floor(n/2)+1 gated to new requests
│   │   └── Workflow/
│   │       ├── WorkflowService.php                 ·  sole transition chokepoint (reuse)
│   │       └── TransitionMap.php                   ✎  (E) +support_forward_to_executive, +return_to_data_entry;
│   │                                                      strip support_approve/reject + bank_reject from actors
│   ├── Policies/
│   │   ├── TraderPolicy.php                        ✚  (B) DATA_ENTRY/BANK_REVIEWER/BANK_ADMIN
│   │   └── ImportRequestPolicy.php                 ✎  (E) reviewer no-reject, support forward-only
│   ├── Rules/
│   │   └── FinancingLimitRule.php                  ✚  (D) ≤100% global check (delegates to ledger service)
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── TraderController.php                ✚  (B) index/show/store/update + tax-number lookup
│   │   │   └── ImportRequestController.php         ✎  (C/D) 5-tab payload + submit through D2 protocol
│   │   ├── Requests/
│   │   │   ├── StoreTraderRequest.php              ✚  (B)
│   │   │   ├── UpdateTraderRequest.php             ✚  (B)
│   │   │   └── StoreImportRequest.php              ✎  (C/D) invoice/shipping/% + FinancingLimitRule
│   │   └── Resources/
│   │       ├── TraderResource.php                  ✚  (B)
│   │       └── ImportRequestResource.php           ✎  (C) + invoice/shipping/snapshot; emits STORED codes
├── database/
│   ├── migrations/
│   │   ├── ..._create_traders_table.php                                ✚ (B) unique tax_number
│   │   ├── ..._create_trader_companies_table.php                       ✚ (B)
│   │   ├── ..._create_trader_owners_table.php                          ✚ (B)
│   │   ├── ..._add_invoice_shipping_fields_to_import_requests_table.php ✚ (C) ~20 nullable cols + trader_id + snapshot
│   │   └── ..._add_composite_index_tax_invoice_to_import_requests_table.php ✚ (D)
│   ├── factories/{TraderFactory,TraderCompanyFactory,TraderOwnerFactory}.php ✚ (B)
│   └── seeders/TraderSeeder.php                    ✚  (B) dev sample traders
└── tests/
    ├── Feature/
    │   ├── Trader/TraderCrudTest.php               ✚  (B) global CRUD + RBAC
    │   ├── Financing/FinancingLedgerTest.php       ✚  (D) ≤100% global, cross-bank, empty-set race, concurrency
    │   ├── Request/FiveTabRequestTest.php          ✚  (C) snapshot, % rules, additive fields
    │   ├── Workflow/AuthorityReformTest.php        ✚  (E) reviewer no-reject, support forward-only, returns
    │   └── Voting/MajorityRuleTest.php             ✚  (E) floor(n/2)+1 new-only, legacy untouched
    └── Unit/
        ├── Enums/NewEnumsLabelTest.php             ✚  (C/F) bilingual labels incl. غير مستوفي للشروط
        └── Services/TraderSnapshotTest.php         ✚  (C) snapshot immutability

frontend/
├── app/
│   ├── constants/
│   │   └── workflow.ts                             ✎  (A/F) Not-Eligible label, Returned-to-Data-Entry, SWIFT merge
│   ├── types/
│   │   ├── trader.ts                               ✚  (B) Trader, Company, Owner
│   │   └── request.ts                              ✎  (C) + invoice/shipping/snapshot fields, new enums
│   ├── composables/
│   │   ├── useTraders.ts                           ✚  (B) CRUD + tax-number lookup
│   │   ├── useRequests.ts                          ✎  (C) 5-tab payload + financing % fetch
│   │   └── useFinancingLedger.ts                   ✚  (D) advisory % indicator (aggregate only)
│   ├── stores/
│   │   └── traders.ts                              ✚  (B) Pinia
│   ├── components/
│   │   ├── trader/
│   │   │   ├── TraderForm.vue                      ✚  (B) shadcn-vue + VeeValidate + Zod
│   │   │   ├── TraderCompaniesField.vue            ✚  (B) 1:N sub-form
│   │   │   └── TraderOwnersField.vue               ✚  (B) 1:N + ownership %
│   │   └── request/
│   │       ├── RequestFormTabs.vue                 ✚  (C) parent 5-tab shell
│   │       ├── tabs/BasicInfoTab.vue               ✚  (C) tax-number lookup → autofill
│   │       ├── tabs/InvoiceTab.vue                 ✚  (C) Full/Partial % rules
│   │       ├── tabs/ShippingTab.vue                ✚  (C) ports/incoterm enums
│   │       ├── tabs/DocumentsTab.vue               ✚  (C) 5 mandatory + ~9 optional
│   │       ├── tabs/WorkflowHistoryTab.vue         ✎  (C) reuse existing timeline
│   │       └── FinancingUtilizationBar.vue         ✚  (D) % used + low-remaining warning + submit-block
│   ├── pages/
│   │   ├── traders/index.vue                       ✚  (B) list
│   │   ├── traders/new.vue                         ✚  (B)
│   │   ├── traders/[id]/index.vue                  ✚  (B) view
│   │   ├── traders/[id]/edit.vue                   ✚  (B)
│   │   └── requests/new.vue                        ✎  (C) mount RequestFormTabs
│   └── tests/unit/                                 ✚/✎  Trader*, RequestFormTabs, FinancingUtilizationBar, label tests
└── (branding assets/strings)                       ✎  (A) title, login, sidebar, header → National Committee
```

### Architectural Boundaries

**API boundaries:**
- Trader (auth:sanctum + TraderPolicy): `GET/POST /api/traders`, `GET/PUT /api/traders/{id}`,
  `GET /api/traders/lookup?tax_number=` (autofill). Org-scope NOT applied to trader records
  (traders are global), but write authority is role-gated.
- Financing ledger: `GET /api/requests/financing-utilization?tax_number=&invoice_number=` →
  returns `{ used_percent, remaining_percent, blocked }` ONLY. Never returns foreign request rows.
  This is the single public surface of the org-scope exception.
- Request submit: `POST /api/requests` / submit transition runs the D2 protocol server-side; the
  FinancingLimitRule + ledger service are the authority. No client can bypass.
- Workflow actions: existing endpoints; `support_forward_to_executive` + `return_to_data_entry`
  added to the transition surface; `bank_reject*` / `support_*` decision actions removed from the
  actor-available set (cases retained for history).

**Component boundaries (frontend):**
- `RequestFormTabs.vue` owns tab state + submit; tab panels are dumb field groups.
- `FinancingUtilizationBar.vue` is advisory display only; it never gates submit by itself — the
  backend D2 check is authoritative; the bar mirrors it for UX.
- `traders` store is the single client cache for trader data; the request form snapshots into its
  own form state at lookup time (never live-binds to the trader store after autofill).

**Service boundaries (backend):**
- `FinancingLedgerService` — the ONLY place the global cross-bank sum is computed; owns the
  named-lock + row-lock + sum-after-lock protocol; returns aggregate/violation, never foreign rows.
- `TraderService` — trader CRUD + builds the request snapshot payload; does not touch workflow.
- `VotingService` — owns the new-rule gating; nothing else branches on voting rule version.
- `TransitionMap` / `WorkflowService` — sole authority for which actions each role may take.
- `DuplicateDetectionService` — invoice-key match validation (currency/total/number/tax); calls
  into `FinancingLedgerService` for the % portion, does not duplicate the global query.

**Data boundaries:**
- `import_requests` — single source of truth for financing %; snapshot columns are authoritative
  per-request; `merchant_id` (legacy) and `trader_id` (new) coexist, both nullable.
- `traders` / `trader_companies` / `trader_owners` — global, mutable, NOT org-scoped.
- Frozen enum cases — DB values never change; only `.label()` output changes.

### Requirements → Structure Mapping

| Epic | Lives in |
|---|---|
| A Rebrand | `frontend` branding strings + `constants/workflow.ts` + enum `.label()` + email/PDF/report headers |
| B Trader Module | `Models/Trader*`, `TraderService`, `TraderPolicy`, `TraderController`, migrations, `components/trader/*`, `pages/traders/*`, `useTraders`, `stores/traders` |
| C 5-Tab Form + Model | 7 `Enums/*`, `..._add_invoice_shipping_fields_*` migration, `ImportRequest`/`StoreImportRequest`/`ImportRequestResource` edits, `components/request/RequestFormTabs` + tabs, `types/request` |
| D Financing Ledger | `FinancingLedgerService`, `FinancingLimitRule`, `DuplicateDetectionService` edit, composite-index migration, `FinancingUtilizationBar`, `useFinancingLedger`, financing-utilization endpoint |
| E Workflow Authority | `TransitionMap`, `ImportRequestPolicy`, `VotingService` edits; `support_forward_to_executive` + `return_to_data_entry`; SWIFT display merge in `constants/workflow.ts` |
| F Terminology | enum `.label()`, `constants/workflow.ts`, notification templates, report/export headers (labels only, codes unchanged) |

### Integration Points

- **Internal:** request submit → `FinancingLimitRule` → `FinancingLedgerService` (D2 protocol) →
  `WorkflowService::transition()`. Trader lookup → `TraderService` → snapshot into request.
  Support forward / reviewer return → `TransitionMap` via `WorkflowService`. Voting close →
  `VotingService` (gated majority).
- **External:** none new. Same MySQL + Redis. Advisory lock via MySQL `GET_LOCK` (existing pattern).
- **Data flow:** trader (global) → snapshot → request (org-scoped) → workflow transitions
  (audited) → voting (gated) → completion. Financing % flows: request rows → ledger aggregate
  (global, guarded) → submit guard + advisory UI bar.

## Architecture Validation Results

### Coherence Validation ✅

**Decision compatibility:** D1–D8 are mutually consistent. The one real tension — D1 derived-query
ledger vs. D2 concurrency under the empty-set case — is resolved by the named-lock + row-lock +
sum-after-lock protocol (named lock covers the phantom-insert race a bare row-lock cannot). D3
snapshot + retained `Merchant` coexist via two nullable FKs (`merchant_id` legacy / `trader_id`
new), with no contradiction: a request is identified by era, never both live-bound. D4 voting
gating + the locked "new-requests-only" rule + D6 label-only terminology together preserve the
backward-compat invariant with zero data migration.

**Pattern consistency:** All naming is inherited from verified codebase conventions (backed PHP
enums + bilingual `.label()`, snake_case tables/migrations, `{Domain}Service`, `useX` composables,
`{success,message,data}` envelope). New surfaces (traders, 7 enums, ledger service, transition
actions, labels) restate those conventions rather than introducing new ones. The single-source-
per-layer label rule (D6) enforces F uniformly.

**Structure alignment:** The structure tree maps every decision to a concrete file. The
`FinancingLedgerService` sole-writer boundary enforces the org-scope exception (D1/D2). The
`WorkflowService`/`TransitionMap` chokepoint enforces authority reform (D5). The advisory-only
`FinancingUtilizationBar` + authoritative backend check keeps UI and authority correctly separated.

### Requirements Coverage Validation ✅

**Epic coverage:** A–F each map to concrete files (step-6 mapping table). No orphan epic.

| Epic | Architecturally supported by |
|---|---|
| A Rebrand | enum `.label()` + `constants/workflow.ts` + branding strings + headers (D6) |
| B Trader Module | Trader tables/models/service/policy/controller + CRUD UI (D3 foundation) |
| C 5-Tab Form + Model | 7 additive enums + nullable migration + 5-tab component + snapshot (D3/D7) |
| D Financing Ledger | derived-query ledger + D2 lock protocol + prevention + composite index + % UI (D1/D2) |
| E Workflow Authority | TransitionMap + Policy + VotingService edits + SWIFT display merge (D4/D5/D8) |
| F Terminology | single-source labels, codes frozen (D6) |

**NFR coverage:**

| NFR | Covered by |
|---|---|
| Backward-compat (zero migration) | Frozen cases (D6) + nullable columns (D3/C) + new-only voting (D4) + retained Merchant |
| Org-scope preserved except ledger | `FinancingLedgerService` guarded chokepoint, aggregate-only return (D1) |
| Ledger concurrency correctness | D2 named-lock + row-lock + sum-after-lock; empty-set race closed |
| All transitions via WorkflowService | D5 + Communication Patterns enforcement |
| RBAC unchanged | TraderPolicy on existing roles; no new role |
| Audit immutability | D6/D8 display-only; stored codes unchanged |
| Trader snapshot integrity | D3 snapshot-at-submit, never live re-read |
| No new dependency / minimal supply chain | Step-3 zero-dependency decision |

### Implementation Readiness Validation ✅

**Decision completeness:** All critical decisions (D1–D4) documented with the exact locking
protocol and gating mechanism; no version ambiguity (zero new deps). Patterns enforceable via
explicit MUST / forbidden lists.

**Structure completeness:** Full ✚/✎/· tree across both repos; every NEW + MODIFIED file located;
boundaries + epic mapping explicit.

**Pattern completeness:** 8 conflict points addressed; concurrency, snapshot, backward-compat, and
validation-timing process patterns specified.

### Gap Analysis Results

**Critical gaps:** NONE.

**Important gaps (capture in stories, not blocking):**
1. `request_percentage` storage type (integer vs decimal) + `voting_rule_version` gating mechanism
   (created_at threshold vs explicit column) MUST be fixed in the Epic C / Epic E foundation
   stories. Decided in principle; the concrete choice lands in the first story of each epic.
2. Snapshot column naming convention (prefix vs documented inline) MUST be fixed in the Epic C
   foundation story and reused across all snapshot fields.
3. The exact "not_eligible_set" excluded from the ledger sum (which terminal/rejected statuses free
   capacity) MUST be enumerated in the Epic D story against the frozen `RequestStatus` cases.

**Nice-to-have (Phase 2 / deferred):** materialized/cached ledger table, dynamic document-type
configuration, Redis advisory % cache.

### Validation Issues Addressed

- Bare `SUM(...) FOR UPDATE` ledger guard → replaced with named-lock + row-lock + sum-after-lock
  (D2), reusing the existing Story 3.6 MySQL advisory-lock pattern; empty-set phantom-insert race
  explicitly closed.
- Arabic "Not Eligible" label corrected to `غير مستوفي للشروط` (the garbled string in the source
  proposal/memory is NOT used).
- Snapshot vs retained-Merchant coexistence → resolved via two nullable FKs by request era.

### Architecture Completeness Checklist

**Requirements Analysis**
- [x] Project context thoroughly analyzed
- [x] Scale and complexity assessed
- [x] Technical constraints identified
- [x] Cross-cutting concerns mapped

**Architectural Decisions**
- [x] Critical decisions documented with versions (zero new deps; inherited stack pinned by platform)
- [x] Technology stack fully specified
- [x] Integration patterns defined
- [x] Performance considerations addressed (composite index, derived query, lock scope)

**Implementation Patterns**
- [x] Naming conventions established
- [x] Structure patterns defined
- [x] Communication patterns specified
- [x] Process patterns documented

**Project Structure**
- [x] Complete directory structure defined
- [x] Component boundaries established
- [x] Integration points mapped
- [x] Requirements to structure mapping complete

### Architecture Readiness Assessment

**Overall Status:** READY FOR IMPLEMENTATION (16/16 checklist items confirmed; no critical gaps; 3
important gaps deferred to epic-foundation stories as documented).

**Confidence Level:** HIGH — brownfield re-scope, decisions grounded in verified existing code,
all epics + NFRs mapped to concrete components, the one high-risk surface (global ledger
concurrency) has an explicit correct protocol reusing a proven in-repo pattern.

**Key Strengths:**
- Single source of truth for financing % (derived query) — no ledger-sync class of bug.
- The org-scope exception is structurally confined to one guarded service returning aggregates only.
- Zero data migration + frozen enums = full backward-compat and audit immutability.
- Authority reform is pure TransitionMap/Policy edits through the existing workflow chokepoint.
- Zero new dependency (government compliance-friendly supply chain).

**Areas for Future Enhancement:** materialized/cached ledger, dynamic document types, Redis %
cache, deprecation of `DRAFT_REJECTED_INTERNAL` after the migration window.

### Implementation Handoff

**AI Agent Guidelines:**
- Follow D1–D8 exactly; never substitute a bare `SUM(...) FOR UPDATE` for the D2 protocol.
- Access the global financing sum ONLY through `FinancingLedgerService`.
- Route all transitions through `WorkflowService::transition()` + `TransitionMap`.
- Keep enum cases + DB values frozen; change only `.label()` / `constants/workflow.ts`.
- Keep additive columns nullable; never migrate/rewrite historical rows; never recompute closed
  voting sessions.
- Use `غير مستوفي للشروط / Not Eligible` as the canonical bilingual label.

**First Implementation Priority — Epic A (Rebrand, brownfield, string-level):**
- Update enum `.label()` + `frontend/app/constants/workflow.ts` + branding strings (title, login,
  sidebar, header) + email/PDF/report/export headers to "The National Committee for Regulating &
  Financing Imports / اللجنة الوطنية لتنظيم وتمويل الواردات". Then B→C→D→E→F per the sequence.
