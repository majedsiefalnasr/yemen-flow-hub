# Sprint Change Proposal — National Committee Rebrand, Trader Module & Workflow Reform

- **Date:** 2026-06-07
- **Author:** MAJED (via BMad Correct-Course)
- **Project:** Yemen Flow Hub → **The National Committee for Regulating & Financing Imports** (اللجنة الوطنية لتنظيم وتمويل الواردات)
- **Scope classification:** **MAJOR** — fundamental replan (PM + Architect), new epic set required
- **Change mode:** Incremental review, batch-built proposal

---

## Section 1 — Issue Summary

A formal stakeholder change request re-scopes the platform from an internal CBY FX/customs workflow tool into the **National Committee for Regulating & Financing Imports** platform. It introduces:

1. Full system rebrand (Arabic + English) across every surface.
2. A new **Trader Management** module (global traders, multiple companies, owners/shareholders with ownership %).
3. A **5-tab Request screen redesign** (Basic / Invoice / Shipping / Documents / Workflow History).
4. A large new **Invoice + Shipping data model** (request type, coverage type, currency source, payment terms %, ports, incoterms, exporter info).
5. A **global cross-bank duplicate-prevention + partial-financing ledger** (sum of financing % per Tax Number + Invoice Number must never exceed 100% across ALL banks).
6. **Workflow authority changes**: Internal Reviewer cannot reject; Supporting Committee loses decision authority (comment + forward only); Executive voting reduced to Approved / Not Eligible with strict simple majority `floor(n/2)+1`.
7. **Terminology**: "Reject/Rejected" → "Not Eligible" everywhere user-facing.
8. **Workflow display**: "Returned for Review" → "Returned to Data Entry"; merge SWIFT Upload + SWIFT Uploaded display into one stage.

**Trigger type:** New stakeholder requirement (strategic re-scope), not a defect.
**Discovery:** Formal written change request.

### Ground-truth audit (what the code actually is today)

| Area | Current state (verified in code) | Implication |
|------|----------------------------------|-------------|
| Status enum | `RequestStatus` = 22 cases incl. `EXECUTIVE_REJECTED`, `SUPPORT_REJECTED`, `BANK_REJECTED` | "Not Eligible" = label-layer only; cases frozen |
| Vote enum | `VoteType` = APPROVE / REJECT / ABSTAIN / AUTO_ABSTAIN_TIMEOUT | Hide ABSTAIN from UI; keep cases for history |
| Voting algo | `VotingService` uses `approve > reject` + director tiebreak, **no quorum / no `floor(n/2)+1`** | New majority rule = real logic change |
| Trader | `Merchant` model = bank-scoped, flat, single owner, no companies/expiry | New global Trader tables; keep Merchant for history |
| Duplicate | `DuplicateDetectionService` = detection only (lists dupes), **no prevention, no % ledger** | Net-new financing-percentage engine |
| i18n | **No vue-i18n / no locale json** — labels are hardcoded bilingual strings in enums + `frontend/app/constants/workflow.ts` | Rename = string edits, not config flip |
| Request schema | `ImportRequest` flat; missing ~20 invoice/shipping/snapshot fields | Large additive migration |
| Indexes | `add_index_invoice_number` + `import_request_reference_sequences` exist | Partial infra already present |
| TransitionMap | `support_approve` / `support_reject` give Support Committee decision power; `bank_reject` gives Internal Reviewer terminal reject | Both contradict new authority rules |

---

## Section 2 — Impact Analysis

### Epic impact
Existing epics (1–16, completed) assume CBY branding, Support Committee decision authority, and 3-way voting. This change **partially invalidates** the Support-Committee, Voting, Merchant, and Request-form epics. Closed epics are **not edited**; a **new epic set** is layered on top.

### Architecture impact
- **New tables:** `traders`, `trader_companies`, `trader_owners`, `request_financing_ledger` (or a derived query), plus document-type expansion.
- **`import_requests`:** ~20 additive columns (request_type, coverage_type, currency_source, payment_terms_mode, request_percentage, request_currency, requested_amount, invoice_type, invoice_currency, unit_of_measure, total_invoice_amount, commodity, exporting_company_name, exporting_company_location, country_of_origin, port_of_loading, port_of_arrival, incoterm, final_destination, shipping_date, arrival_date, trader_id, + trader snapshot fields).
- **New enums (additive, no rename of existing):** RequestType, CoverageType, CurrencySource, PaymentTermsMode, InvoiceType, PortOfArrival, Incoterm.
- **Services:** new `TraderService`, `FinancingLedgerService` (global % validation, cross-bank, bypasses org-scope intentionally); modify `VotingService` (majority rule), `TransitionMap` (authority), `DuplicateDetectionService` (add prevention).
- **Backward-compat invariant:** org-scope visibility preserved everywhere EXCEPT the financing-ledger duplicate check, which is intentionally global and must be explicitly documented + access-guarded.

### UI/UX impact
- Request page → 5-tab component; Tax Number lookup → trader autofill → request snapshot.
- New Trader Management pages (list/create/edit/view) with companies + owners sub-forms.
- Financing % utilization indicator + low-remaining warning + submit-block on % violation.
- Global string swaps: Reject→Not Eligible, Returned for Review→Returned to Data Entry, SWIFT stage merge.
- Existing design system / shadcn-vue reused (no new component library).

### Backward-compatibility assessment
- **Enums:** frozen cases → historical rows render with new labels automatically, no migration. ✅
- **Voting:** new rule applies to **new requests only**; closed sessions untouched. ✅
- **Merchant:** retained; existing requests keep `merchant_id`; new requests use `trader_id` + snapshot. ✅
- **Audit logs:** immutable; only display labels change, stored action/status codes unchanged. ✅
- **New columns:** all nullable / defaulted so historical requests stay valid. ✅

---

## Section 3 — Recommended Approach

**Direct Adjustment + new epic set (no rollback).** Nothing shipped is reverted. Three locked decisions de-risk the change:

1. **Reject → Not Eligible = label-layer only.** Keep enum case names + DB values; change only display strings + new UI copy. Zero data migration, full audit immutability.
2. **New Trader tables, keep Merchant.** Global module; new requests snapshot trader data into the request row; historical Merchant-linked requests unaffected.
3. **Voting majority `floor(n/2)+1` for new requests only.** No retroactive recompute of closed sessions.

**Effort:** Large (multi-epic). **Risk:** Medium — concentrated in (a) global financing-% ledger correctness under concurrency across banks, (b) Support Committee authority removal touching TransitionMap + tests, (c) voting algorithm change. **Timeline:** new sprint, sequenced epics below.

---

## Section 4 — Detailed Change Proposals (proposed new epics)

> These are **proposed epics for `bmad-create-epics-and-stories`**, not direct edits to closed epics.

### EPIC A — System Rebrand (National Committee)
Title, login, sidebar, header, emails, notifications, PDFs, reports, exports, settings, docs → "The National Committee for Regulating & Financing Imports" / "اللجنة الوطنية لتنظيم وتمويل الواردات". String-level; no schema. Low risk, do first.

### EPIC B — Trader Management Module
`traders` (tax_number unique global PK identifier, trader_name, tax_card_expiry, commercial_registration_number, commercial_registration_expiry), `trader_companies` (FK trader, company_name), `trader_owners` (FK trader, full_name, ownership_percentage, nationality?, identification_number?; only 25%+ enforced as "required" set). CRUD permissions: DATA_ENTRY, BANK_REVIEWER (Internal Reviewer), BANK_ADMIN (Bank Manager). New `TraderService`, controllers, policies, frontend pages.

### EPIC C — Request Form Redesign (5 tabs) + Invoice/Shipping Data Model
Additive `import_requests` columns + new enums (RequestType, CoverageType, CurrencySource, PaymentTermsMode, InvoiceType, PortOfArrival, Incoterm). Tab1 Tax Number lookup → trader autofill → **snapshot** (request-level edits don't mutate trader). Tab2 invoice fields + Full/Partial % rules (Full=100% readonly; Partial ≥5% & <100%). Tab3 shipping (ports/incoterm enums). Tab4 dynamic documents (5 mandatory + ~9 optional, future-dynamic). Tab5 workflow history (reuse existing timeline).

### EPIC D — Global Duplicate Prevention & Partial-Financing Ledger
Extend `DuplicateDetectionService` → prevention. Validation: duplicate key (tax_number + invoice_number); reject new request if existing financing reaches 100%; allow multiples only when partial and **global sum across all banks ≤ 100%**; invoice currency / total / number / tax number must match. Composite index (tax_number, invoice_number). Concurrency-safe (lock or transactional check) since cross-bank. Form Request rule + service guard. UI % utilization + low-remaining warning + submit-block.

### EPIC E — Workflow Authority Reform
- **Internal Reviewer (BANK_REVIEWER):** remove terminal/internal reject capability → only approve-continue + `RETURN_TO_DATA_ENTRY`. Remove `bank_reject` / `bank_reject_terminal` from reviewer-available actions (keep enum/case for history).
- **Supporting Committee:** remove `support_approve` / `support_reject` decision authority → new `support_forward_to_executive` transition; UI removes "Support Approval" + "Open Voting" buttons → single "Send to Executive Committee". Comments still recorded to audit.
- **Executive voting:** UI shows only Approved / Not Eligible (hide Abstain). `VotingService` majority = `floor(total_eligible/2)+1` approvals → APPROVED else NOT-ELIGIBLE. New requests only.
- **Display:** "Returned for Review" → "Returned to Data Entry" (only on Internal Reviewer return); merge SWIFT Upload + SWIFT Uploaded display into one "SWIFT Uploaded" stage (keep granular audit entries).
- All changes via `WorkflowService::transition()`; TransitionMap + policies + tests updated.

### EPIC F — Terminology & Label Layer
Display-string swaps: Reject/Rejected → "غير مؤهل / Not Eligible" in enum `.label()`, `frontend/app/constants/workflow.ts`, types, notification templates, report/export headers. **No enum case or DB value renamed.** Reports/exports/APIs continue emitting stored codes; only human-readable labels change.

---

## Section 5 — Implementation Handoff

**Scope = MAJOR.** Route to **PM + Architect** for replan before any dev.

Recommended BMad sequence (fresh context per skill):
1. `bmad-edit-prd` (or create PRD section) — fold business rules: trader snapshot, partial-financing %, voting majority, authority changes.
2. `bmad-create-architecture` — schema/ERD, new enums, financing-ledger design + concurrency, global-scope exception documentation. (Deliverables #2, #3, #5, #6.)
3. `bmad-create-epics-and-stories` — Epics A–F into stories with the locked decisions baked in.
4. `bmad-check-implementation-readiness`.
5. `bmad-sprint-planning`.
6. Per story: `bmad-create-story` → `bmad-dev-story` → `bmad-code-review`. Run focused tests per change; full suite for the workflow-authority + voting + ledger stories (security/workflow-critical per doctrine).

**Sequencing:** A (rebrand) → B (trader) → C (form/data model) → D (ledger) → E (workflow) → F (terminology). B precedes C (snapshot needs trader). D precedes/with E (% rules gate submission). F can ride alongside A but finalize after E (workflow labels settle).

### Success criteria
- All historical requests/audit rows render + function unchanged (backward-compat verified).
- New requests enforce global ≤100% financing across banks.
- Internal Reviewer has no reject path; Support Committee has no decision path; voting uses `floor(n/2)+1`.
- No enum case renamed; no DB status/vote value migrated.
- Rebrand visible on every listed surface.

---

## Locked Decisions (from this session)
1. Reject→Not Eligible = **label-layer only** (cases + DB values frozen).
2. **New Trader tables, keep Merchant** (snapshot into request).
3. Voting `floor(n/2)+1` = **new requests only**, no retroactive recompute.
