# M6 — Enum & Presentation-Model Reconciliation (Approved: Option B)

**Status:** Locked. Full reconciliation — eliminate the legacy status model, do
not preserve it as a documented presentation layer. No code changed yet. Evidence
date: 2026-07-11.

**Severity:** CF-6 / F-DOC-1 stays **Medium** (documentation). The wider frontend
status drift is raised as a **new High** maintainability/correctness finding
(**STATUS-DRIFT-001**) because it spans many live UI components and can display
incorrect workflow state.

---

## 1. Approved source-of-truth hierarchy

1. Database schema + persisted workflow metadata
2. Published Workflow Designer configuration
3. Backend runtime behavior + API contracts
4. Frontend rendering derived from API + workflow metadata
5. Documentation

The frontend must not maintain a parallel workflow-status model that can diverge
from the engine.

## 2. Canonical request-state model (three separate concepts)

- **`runtime_status`** — ACTIVE | CLOSED | REJECTED | CANCELLED | ABANDONED
- **`current_stage`** — from the pinned workflow version: `code`, `name`, `semantic_role`, `is_initial`, `is_final`, designer metadata
- **`final_outcome`** — COMPLETED | REJECTED | CANCELLED | ABANDONED | null (while active)

The frontend must not combine runtime state, workflow stage, and final outcome
into one large static enum.

## 3. Verified drift (three layers)

| Layer                 | State                                                                                                                                                                                                            |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Backend runtime       | **Truth.** Keys on `status` ∈ {ACTIVE, CLOSED, REJECTED, CANCELLED, ABANDONED} + `current_stage_id` (`EngineRequest.php:116,126`). No `EngineRequestStatus` enum; no 22-status vocabulary.                       |
| Frontend presentation | **Drift.** 22-value `RequestStatus` enum (`types/enums.ts`) with voting/customs legacy names. **1,119 references across 42 files.** Drives labels, badges, colors, timelines, dashboard buckets, filters, tests. |
| AGENTS.md             | **Wrong.** Documents 22-status + 8-role enums as "canonical"; still claims `COMMITTEE_DIRECTOR inherits all EXECUTIVE_MEMBER permissions`; references voting. Contradicts backend + M1 contract.                 |

## 4. API-contract gaps (must fix before frontend migration)

`EngineRequestResource` (`:34-77`) currently returns:

- `status` (raw) — **rename/surface as `runtime_status`** per canonical model.
- `current_stage`: `id, code, name, is_initial, is_final, sla_duration_minutes, requires_claim` — **missing `semantic_role`**.
- **No request-level `final_outcome`** — the frontend cannot answer "how did it end" from the resource; `final_outcome` currently lives only on stages.

These become explicit API-contract tasks (see plan A/B-scope in `10-implementation-plan.md`). Do not make the frontend
reconstruct backend state from labels or old enum values.

## 5. Legacy-removal scope (inventory before removal)

Remove only after dependency evidence. Inventory: voting status values, voting
UI/presentation mappings, customs statuses unused by the current workflow,
obsolete labels, dead timeline branches, legacy dashboard categories, old
fixtures/mocks, orphaned voting DTOs/enums/resources/exceptions, deprecated
`docs/user-view/` assumptions, incorrect role-inheritance docs.

Removal gates (all must hold): no active API route depends on it; no DB table /
persisted data depends on it; no queue job / event listener depends on it; no
current workflow version references it; no required audit history becomes
unreadable; replacement tests exist for the current model.

## 6. Backward compatibility (48 pinned V1 requests)

Older requests may lack `semantic_role`. Use a **temporary, isolated, clearly
marked** compatibility adapter that: supports existing V1 records during
migration; is never used for newly published versions; has measurable removal
criteria; is removed once legacy active requests/versions are resolved. The
22-status model must not be the permanent solution.

## 7. AGENTS.md updates required

Five runtime statuses; designer-defined stages; final outcomes as a separate
concept; actual DB role codes; Executive Voting not in V1;
`COMMITTEE_DIRECTOR` does **not** auto-inherit `EXECUTIVE_MEMBER` unless
explicitly configured; the source-of-truth hierarchy; the frontend must not
define an independent canonical workflow-status enum. Mark `docs/user-view/`
deprecated now; archive/delete only via the approved legacy-cleanup task. Keep
`dynamic-workflow-engine/` as reference until separately decided.

## 8. Safety rules

No blind global replacement. Do not hard-code the current nine stages as a
permanent frontend enum. Do not break historical request display. No legacy
removal without dependency evidence. Do not modify the engine merely to match
the old frontend model. Add regression tests before removing old mappings.
Rollback plan per migration slice.

## 9. Roadmap (10 steps, Phase D/E — see implementation plan)

1. Inventory all legacy status dependencies.
2. Finalize new API + frontend state contracts.
3. Add/populate required `semantic_role` metadata.
4. Introduce focused frontend types + adapters.
5. Migrate dashboards, timelines, labels, filters, pages.
6. Replace legacy tests + fixtures.
7. Verify old pinned workflow requests render correctly.
8. Remove the 22-status enum + dead voting/customs presentation code.
9. Reconcile AGENTS.md + other docs.
10. Remove the temporary compatibility adapter when exit criteria are met.
