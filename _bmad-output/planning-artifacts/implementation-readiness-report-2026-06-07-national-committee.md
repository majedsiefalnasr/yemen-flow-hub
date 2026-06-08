---
stepsCompleted: ["step-01-document-discovery", "step-02-prd-analysis", "step-03-epic-coverage-validation", "step-04-ux-alignment", "step-05-epic-quality-review", "step-06-final-assessment"]
status: 'complete'
readinessStatus: 'READY'
date: '2026-06-07'
scope: 'National Committee Re-Scope (Epics A–F) implementation readiness'
documentsInScope:
  - _bmad-output/planning-artifacts/epics-national-committee.md
  - _bmad-output/planning-artifacts/architecture.md
  - _bmad-output/planning-artifacts/sprint-change-proposal-2026-06-07-national-committee-rebrand-trader-workflow.md
documentsReferenceOnly:
  - _bmad-output/planning-artifacts/epics.md
  - docs/user-view/*.md
documentsExcluded:
  - _bmad-output/planning-artifacts/architecture-email-subsystem-archive.md
  - _bmad-output/planning-artifacts/epics.md (shipped Epics 1–15; additive layer, NOT modified)
  - _bmad-output/planning-artifacts/sprint-change-proposal-2026-06-07-epic-16-security-reliability-hardening.md
---

# Implementation Readiness Assessment Report

**Date:** 2026-06-07
**Project:** Yemen Flow Hub → The National Committee for Regulating & Financing Imports

## Step 1 — Document Inventory

**Scope:** Implementation readiness of the National Committee re-scope (Epics A–F additive layer).

### Documents IN SCOPE

| Type | File | Notes |
|---|---|---|
| Epics & Stories | `epics-national-committee.md` (46K) | The A–F additive layer under review (6 epics, 18 stories). |
| Architecture | `architecture.md` (50K) | National Committee scope, decisions D1–D8. |
| Change source | `sprint-change-proposal-2026-06-07-national-committee-rebrand-trader-workflow.md` | Approved requirement source (no standalone PRD by locked decision). |

### Reference-only (NOT modified, dependency context)

| File | Reason |
|---|---|
| `epics.md` (243K, Epics 1–15) | Shipped base. The re-scope is additive; base epics are referenced via dependency notes, never edited. |
| `docs/user-view/*.md` | UX ground-truth (5 impacted role files reviewed during epic creation). |

### Excluded

| File | Reason |
|---|---|
| `architecture-email-subsystem-archive.md` (37K) | Prior email-subsystem architecture; archived, out of this re-scope. |
| `sprint-change-proposal-2026-06-07-epic-16-security-reliability-hardening.md` | Different change (Epic 16); not part of A–F. |

### Issues

- **PRD:** none found — **expected/by design** (locked decision: no standalone PRD; planning source = proposal + architecture + docs/). Not a blocker.
- **No whole-vs-sharded duplicates.** The multiple `*epic*`/`*architecture*` matches are distinct documents, scoped above — not format duplicates.
- **No missing in-scope documents.**

## Step 2 — Requirements Baseline (PRD-equivalent)

**No standalone PRD by locked decision.** The authoritative requirements baseline is the
Requirements Inventory in `epics-national-committee.md` (full text lives there). Counts:

### Functional Requirements (46 total)

- **Epic A (4):** A1 rebrand all surfaces · A2 sidebar identity · A3 email/notif headers · A4 string-level only.
- **Epic B (7):** B1 global `traders` (tax_number) · B2 `trader_companies` 1:N · B3 `trader_owners` 1:N (≥25% required set) · B4 CRUD (DATA_ENTRY/BANK_REVIEWER/BANK_ADMIN) · B5 tax-number lookup · B6 traders global read / role-gated write · B7 Merchant retained.
- **Epic C (9):** C1 5-tab replaces wizard · C2 lookup→autofill→snapshot · C3 ~20 additive nullable cols · C4 7 enums · C5 Full=100% / Partial ≥5%&<100% · C6 shipping ports/incoterm/dates · C7 docs 5 mandatory + ~9 optional PDF-only · C8 Tab5 reuses timeline · C9 nullable/defaulted (zero migration).
- **Epic D (9):** D1 derived-query ledger (no table) · D2 global ≤100% block · D3 multiple partials ≤100% · D4 invoice-key consistency · D5 named-lock+row-lock+sum-after protocol · D6 composite index · D7 utilization endpoint (aggregate-only) · D8 % UI · D9 all global reads via FinancingLedgerService.
- **Epic E (9, incl. E4a):** E1 reviewer no-reject · E2 support forward-only · E3 exec Approved/Not-Eligible (hide Abstain) · E4 majority floor(n/2)+1 (new only) · E4a no tie-break new model (even split = Not Eligible; legacy keeps it) · E5 "Returned to Data Entry" · E6 SWIFT display merge · E7 via WorkflowService/TransitionMap · E8 SUPPORT_REJECTED cleanup (new-rule) + historical preserved.
- **Epic F (5):** F1 enum `.label()` Not Eligible · F2 frontend/types/notif/report swap · F3 no rename, codes frozen · F4 single label source per layer · F5 banned-synonyms guard.

### Non-Functional Requirements (10 total)

NFR-1 backward-compat (zero migration) · NFR-2 org-scope except ledger · NFR-3 ledger concurrency
correctness (empty-set race) · NFR-4 workflow integrity (atomic, audited) · NFR-5 RBAC unchanged ·
NFR-6 audit immutability · NFR-7 trader snapshot integrity · NFR-8 no new dependency · NFR-9 SOD
preserved · NFR-10 test rigor (full suite for workflow/voting/ledger).

### Additional Requirements (Architecture D1–D8)

D1–D8 + new error codes (`FINANCING_LIMIT_EXCEEDED`, `DUPLICATE_INVOICE_MISMATCH`) + the 4 locked
implementation decisions (DECIMAL(5,2); `trader_snapshot_` prefix; not_eligible_set frees capacity;
`voting_rule_version` column legacy=1/new=2).

### Baseline Completeness Assessment

Requirements baseline is **complete and internally consistent** — every requirement carries a
testable intent, the 3 locked decisions + 4 locked implementation decisions remove prior ambiguity,
and the 12 UX-DRs are each tagged to the shipped role-doc they override. Ready for coverage
traceability.

## Step 3 — Epic Coverage Validation

### Coverage Matrix (FR → Story)

| FR | Story | Status |
|---|---|---|
| A1, A4 | A.1 + A.2 | ✓ Covered |
| A2 | A.2 | ✓ Covered |
| A3 | A.1 | ✓ Covered |
| B1, B2, B3, B7 | B.1 | ✓ Covered |
| B4 | B.2 + B.3 | ✓ Covered |
| B5, B6 | B.2 | ✓ Covered |
| C1 | C.3 | ✓ Covered |
| C2 | C.2 + C.3 | ✓ Covered |
| C3, C4, C9 | C.1 | ✓ Covered |
| C5 | C.2 + C.4 | ✓ Covered |
| C6, C7 | C.4 | ✓ Covered |
| C8 | C.3 | ✓ Covered |
| D1, D5, D6, D9 | D.1 | ✓ Covered |
| D2, D3, D4 | D.2 | ✓ Covered |
| D7, D8 | D.3 | ✓ Covered |
| E1 | E.1 | ✓ Covered |
| E2, E8 | E.2 (+ E.1 for reviewer support_rejected surfaces) | ✓ Covered |
| E3, E4, E4a | E.3 | ✓ Covered |
| E5, E6, E7 | E.4 | ✓ Covered |
| F1, F3 | F.1 | ✓ Covered |
| F2 | F.1 (backend) + F.2 (frontend) | ✓ Covered |
| F4, F5 | F.2 | ✓ Covered |

### Missing Requirements

**NONE.** All 46 FRs trace to ≥1 story with testable acceptance criteria.

### Coverage Statistics

- Total FRs: **46** · FRs covered: **46** · **Coverage: 100%**
- NFRs: 10, each addressed by ≥1 story AC or cross-cutting (audit/test/RBAC) requirement.
- UX-DRs: 12, each tagged to overriding story (DR1–2→C; DR3→D.3; DR4→B.3; DR5→E.1/E.2; DR6→E.2; DR7→E.3; DR8→E.3; DR9→E.4; DR10→E.4; DR11→F.2; DR12→A.2).
- No epic-without-FR or FR-without-epic orphans.

## Step 4 — UX Alignment Assessment (Review Area 5: UI/UX Impact)

### UX Document Status

No standalone `*ux*.md` planning document — **expected**. UX ground-truth for this re-scope is
`docs/user-view/*.md` (5 impacted role specs reviewed during epic creation: data-entry,
bank-reviewer, support-committee, executive-member, committee-director). Each affected screen is
captured as a UX-DR tagged with the shipped role-doc it overrides.

### UX ↔ FR ↔ Architecture Alignment

| Review point | Stories | Architecture support | Status |
|---|---|---|---|
| **Affected role screens** | A.2, B.3, C.3/C.4, D.3, E.1/E.2/E.3/E.4, F.2 | Reuses existing shadcn-vue + Pinia + composable patterns (arch §Frontend); no new component library | ✓ Aligned |
| **Trader lookup/autofill flow** | B.2 (lookup endpoint), B.3 (pages), C.3 (Tab1 lookup→autofill→snapshot) | D3 snapshot at create/submit; `traders` store + `useTraders` mirror existing patterns | ✓ Aligned |
| **5-tab request form** | C.3 (shell + Basic + History), C.4 (Invoice/Shipping/Docs) | `RequestFormTabs.vue` + VeeValidate + Zod; overrides shipped 4-step wizard (dependency-noted) | ✓ Aligned |
| **Workflow timeline changes** | C.3 (Tab5 reuses timeline), E.4 ("Returned to Data Entry") | Display-only; `constants/workflow.ts` single label source | ✓ Aligned |
| **SWIFT stage merge (display)** | E.4 | D8 display-only merge; underlying `WAITING_FOR_SWIFT`+`SWIFT_UPLOADED` + audit unchanged | ✓ Aligned |
| **% utilization UI** | D.3 | Advisory-only bar; backend D2 check authoritative (arch §Frontend boundary) | ✓ Aligned |

### Alignment Issues

**NONE.** Every UX-DR maps to a story AC and is supported by an architecture decision. The advisory
vs. authoritative split (UI % bar advisory; backend ledger authoritative) is explicit, preventing a
UX-vs-backend authority conflict.

### Warnings

- **Per-role-doc terminology/branding sweep (operational, not blocking):** the 5 reviewed role docs
  still describe shipped copy ("مرفوض", "منصة الواردات", 4-step wizard, support approve/reject). These
  are correctly handled as *overrides* in A/E/F stories. The remaining 3 unread role docs
  (bank-admin, swift-officer, cby-admin) + 10 implementation-plans were deferred (low A–F impact);
  F.2's banned-synonyms guard + A.2's single platform-name source will catch any stragglers
  codebase-wide at implementation, so no story gap — flagged for create-story awareness only.

## Step 5 — Epic Quality Review + Deep-Dive (Review Areas 1–4, 6)

### Best-Practices Compliance (per epic)

| Check | A | B | C | D | E | F |
|---|---|---|---|---|---|---|
| Delivers user value (not tech milestone) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Functions independently (no later-epic dep) | ✓ | ✓ | ✓¹ | ✓¹ | ✓ | ✓¹ |
| Stories appropriately sized (single agent) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| No forward (later-story) deps within epic | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| DB tables/columns created only when needed | n/a | ✓ (B.1) | ✓ (C.1) | ✓ (D.1 index) | n/a | n/a |
| Clear Given/When/Then ACs | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| FR traceability maintained | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

¹ Dependency is on an EARLIER epic (B→C→D; E shares the new-rule invariant; F timed last) —
backward dependencies via dependency notes, which is allowed. No epic requires a later epic.

**Brownfield:** correct — no starter-template story; integration via dependency notes to shipped
Epics 1–15; foundation work (B.1, C.1, D.1) lives inside value epics, not a big-upfront epic.

### Review Area 1 — Database Impact ✓

- **New tables (B.1):** `traders` (unique `tax_number`), `trader_companies` (FK `trader_id`), `trader_owners` (FK `trader_id`). ✓
- **New columns (C.1):** ~20 additive on `import_requests` + `trader_id` FK + 5 `trader_snapshot_*` cols + `voting_rule_version`. All nullable/defaulted (FR-C9). ✓
- **Indexes:** unique `traders.tax_number`; composite `(tax_number, invoice_number)` (D.1); FK indexes on trader children. `add_index_invoice_number` already exists — extend, not duplicate. ✓
- **FKs:** `import_requests.trader_id → traders`; `trader_companies.trader_id`, `trader_owners.trader_id`. ✓
- **Zero historical data migration (NFR-1):** ✓ — frozen enums + nullable columns; historical rows valid unchanged.
- **Merchant + Trader coexistence (D3):** ✓ — `merchant_id` (legacy) and `trader_id` (new) both nullable, by request era; `Merchant` model untouched.
- 🟡 **Minor note:** `voting_rule_version` should be added with **default = 1** so existing rows resolve to legacy behavior automatically; new requests explicitly set 2. (A schema default applied to existing rows is standard additive migration, not a business-data migration — does not violate NFR-1.) Capture in C.1.

### Review Area 2 — Workflow Impact (1 MAJOR finding)

- **TransitionMap actions affected:** remove `bank_reject` / `bank_reject_terminal` from reviewer-available (E.1); replace `support_approve` / `support_reject` with `support_forward_to_executive` (E.2); add `return_to_data_entry` (E.1). All retained as enum cases for history. ✓
- **SUPPORT_REJECTED cleanup (E.8/FR-E8):** UI, filters, tabs, counters, dashboards, queues, notifications covered (E.2 AC). Reports: new-rule requests never reach `SUPPORT_REJECTED`, so reports simply stop accumulating new rows; historical rows + labels (via F) still render — **no active report cleanup needed** (confirmed not a gap). ✓
- **Historical preservation:** historical `SUPPORT_REJECTED` requests render correctly + audit preserved (E.2 AC). ✓
- 🟠 **MAJOR — in-flight legacy workflow actions:** The stories gate UI + voting *finalize* by request era, but the **transition-action availability must also be era-gated** (by `voting_rule_version`), not globally stripped. Otherwise a request **in flight at cutover** under v1 (already in `BANK_REVIEW`, `SUPPORT_REVIEW_IN_PROGRESS`, or `SUPPORT_REJECTED`) could lose the old actions it still needs to complete (reviewer reject, support approve/reject, director tie-break). **Recommendation:** E.1/E.2/E.3 must make the era gate apply to `TransitionMap` action availability + policy, so v1 requests keep the legacy action set and v2 requests use the new set; add an explicit cutover note (what happens to requests already in flight at deploy). Addressable in create-story; not a sprint-planning blocker.

### Review Area 3 — Financing Ledger Safety ✓

- **named-lock + row-lock + sum-after-lock protocol (D.1, D5):** ✓ explicit; reuses Story 3.6 MySQL advisory-lock pattern.
- **Empty-set race protection (D.1, NFR-3):** ✓ named lock serializes first-inserts; concurrency test required by AC.
- **No bypass of `FinancingLedgerService` (D9):** ✓ sole cross-bank read path, docblock-documented, aggregate-only return; `DuplicateDetectionService` calls into it rather than re-querying.
- 🟡 **Minor note:** the named lock must release on **all** paths (commit, rollback, exception) — specify `try/finally` or transaction-teardown release + a test that a thrown validation error still frees the lock. Capture in D.1.

### Review Area 4 — Voting Model ✓

- **`voting_rule_version` handling:** column in C.1; `VotingService` gates finalize on it (E.3). ✓
- **No Director tie-break in v2 (E4a):** ✓ even split / sub-majority → NOT-ELIGIBLE; tie-break controls not rendered/invokable for v2.
- **Legacy v1 unchanged:** ✓ v1 uses prior rule incl. tie-break; no retroactive recompute of closed sessions.
- 🟡 **Minor note:** `floor(total_eligible_members/2)+1` — `total_eligible_members` must use the **same active-executive definition** the shipped `VotingService` already applies (inactive execs excluded, per Story 3.4). State this in E.3 so the denominator is unambiguous.

### Review Area 6 — Terminology & Branding ✓

- **Reject/Rejected/Declined/Disapproved/Not Approved → `غير مستوفي للشروط` / Not Eligible:** F.1 (backend `.label()` + templates + report headers) + F.2 (frontend constants/types/UI). ✓
- **Banned-synonyms guard (F5):** ✓ present.
- **Branding `اللجنة الوطنية لتنظيم وتمويل الواردات` / The National Committee for Regulating & Financing Imports:** A.1 (backend) + A.2 (frontend, single platform-name source). ✓
- 🟡 **Minor note:** F.5's banned-synonyms rule needs a concrete **enforcement mechanism** (a grep/CI check or ESLint rule over user-facing strings) rather than manual review, so regressions are caught automatically. Specify the mechanism in F.2.

### Quality Findings Summary

- 🔴 **Critical violations:** NONE.
- 🟠 **Major issues:** 1 — in-flight legacy workflow-action era-gating (Area 2). Addressable in create-story for Epic E; does not block sprint planning.
- 🟡 **Minor concerns:** 4 — voting_rule_version default=1 (C.1); named-lock release-on-all-paths + test (D.1); total_eligible_members definition (E.3); banned-synonyms automated enforcement (F.2). All folded into the named foundation stories.

## Summary and Recommendations

### Overall Readiness Status

**READY** — no critical blockers. 100% FR coverage (46/46), all 10 NFRs and 12 UX-DRs addressed,
architecture decisions D1–D8 + 7 locked decisions consistently reflected, additive-layer integrity
preserved (base `epics.md` untouched). Sprint planning may proceed.

### Critical Issues Requiring Immediate Action

**NONE.**

### Issues to Carry Into create-story (non-blocking)

1. 🟠 **(Epic E) Era-gate transition actions, not just UI/voting.** Make `voting_rule_version` gate
   `TransitionMap` action availability + policy so in-flight legacy (v1) requests keep the old action
   set (reviewer reject, support approve/reject, tie-break) while v2 uses the new set. Add an explicit
   cutover note for requests already in flight at deploy. (E.1/E.2/E.3)
2. 🟡 **(C.1)** Add `voting_rule_version` with default = 1; new requests set 2.
3. 🟡 **(D.1)** Release the named lock on all paths (try/finally / transaction teardown) + a test that
   a validation error still frees it.
4. 🟡 **(E.3)** Define `total_eligible_members` as the existing active-executive set (inactive excluded,
   per Story 3.4).
5. 🟡 **(F.2)** Give the banned-synonyms rule an automated enforcement mechanism (grep/CI or ESLint).

### Recommended Next Steps

1. Proceed to **`bmad-sprint-planning`** to generate the sprint status tracker for Epics A–F.
2. During **`bmad-create-story`** for Epic E, fold in finding #1 (era-gated transition actions + cutover).
3. During create-story for C.1 / D.1 / E.3 / F.2, fold in the matching minor notes (#2–#5).
4. Run the full backend test suite for the workflow-authority (E), voting (E.3), and ledger (D)
   stories per NFR-10.

### Final Note

This assessment reviewed 6 areas (DB, Workflow, Financing Ledger, Voting, UI/UX, Terminology/Branding)
plus standard epic-quality and traceability checks. It found **0 critical, 1 major, 4 minor** issues —
none blocking. The major item is a cutover/era-gating refinement for Epic E, to be handled at
create-story. Artifacts are ready for sprint planning and implementation.

*Assessor: BMad Implementation Readiness (PM) — 2026-06-07.*
