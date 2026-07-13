# Phase B Checkpoint + V1 Request Classification (Non-Destructive)

Evidence date: 2026-07-11. Phase B (B1–B3) is complete; B4 deferred to Phase D.
No V1 request was reset, recreated, migrated, or deleted. This report is a
non-destructive dry-run classification; the actual reset/recreation of the 48 V1
requests **requires separate explicit approval**.

---

## 1. Phase B outcome

Delivered as a **new published workflow version V2** through the real designer
lifecycle (clone → correct DRAFT → validate → publish → archive V1). No seeder or
raw-DB path bypassed publication validation.

| Task | Finding           | Result                                                                                                                                                                                             |
| ---- | ----------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| B1   | WF-001 (High)     | Four reject transitions require comment + confirmation message; Support self-loop marked. V2 passes `WorkflowVersionValidator` with **0 errors**.                                                  |
| B2   | WF-002 (High)     | FINAL EXECUTE owned by `committee_director`; EXEC keeps `committee_manager` (two-step model).                                                                                                      |
| B3   | WF-003 (Critical) | SWIFT package (`swift_reference`, `swift_file`, `fx_request_file`) required at FX; the engine blocks `FX→FX_CONFIRM` until the reference and both linked PDFs exist. Visible read-only downstream. |
| B4   | semantic_role     | **Deferred to Phase D** — cross-layer type-model dependency; V2 publishes clean via the stage-code fallback.                                                                                       |

**Mechanism / safety:** `workflow:publish-import-financing-v2` — designer-driven,
dry-run by default, `--publish` to persist, production hard-blocked, idempotent,
transactional (V1 untouched on any failure).

**Verification:** Phase B suite 14 tests / 645 assertions pass; engine+workflow
regression 1628 assertions, 0 failures; Pint clean. The WF-003 runtime gate is
proven (transition blocked without the package, passes with it).

**Local dev DB state (post Option-A cleanup):** IMPORT_FINANCING **v2 (id=39)
PUBLISHED** with all corrections; **v1 (id=1) ARCHIVED**; the two debug requests
and their 4 documents deleted by exact ID; 0 `ENG-DBG-*` and 0 orphans remain; the
48 V1 requests unchanged; publication audit trail preserved. Full incident +
cleanup record in `11-phase-b-v2-construction.md`.

---

## 2. V1 request classification (live DB, read-only)

**Total pinned to V1: 56** (48 ACTIVE + 4 CLOSED + 4 REJECTED).

### Count by stage / status

| Status    | Stage            | Count  |
| --------- | ---------------- | ------ |
| ACTIVE    | CREATE           | 4      |
| ACTIVE    | INTERNAL         | 12     |
| ACTIVE    | SUPPORT          | 8      |
| ACTIVE    | EXEC             | 6      |
| ACTIVE    | FX               | 6      |
| ACTIVE    | FX_CONFIRM       | 6      |
| ACTIVE    | FINAL            | 6      |
| CLOSED    | CLOSED_COMPLETED | 4      |
| REJECTED  | CLOSED_REJECTED  | 4      |
| **Total** |                  | **56** |

- **Active (non-terminal): 48** — the requests exposed to the corrected-workflow decision.
- **Terminal: 8** (4 COMPLETED + 4 REJECTED) — already finished on V1.

### Synthetic vs. UAT classification

**Verdict: all 56 are synthetic/demo seed data. No organic UAT records found.**

| Signal                    | Value                                              | Interpretation                   |
| ------------------------- | -------------------------------------------------- | -------------------------------- |
| `created_at`              | all 56 at `2026-05-01 09:00:00` (1 distinct value) | Machine-seeded in a single batch |
| Reference prefixes        | `ENG-2026-YBRD` (28) + `ENG-2026-TIIB` (28)        | Deterministic seeder output      |
| Distinct banks / creators | 2 / 2                                              | Fixture spread, not real usage   |
| Distinct merchants        | 8                                                  | Fixture merchant set             |
| Source                    | `EngineRequest*Seeder` catalog                     | No user-entered data path        |

### Footprint (what exists on these records)

- **58** `engine_request_documents` linked to V1 requests.
- **248** `workflow_history` rows (rich per-transition history).
- Audit-log entries exist for their seeded transitions.

Footprint is non-trivial: a reset must be deliberate and clean, even though the
data is synthetic.

---

## 3. Proposed action per category (for a FUTURE approved data step)

| Category                            | Count | Proposed action                     | Rationale                                                                                                                                                      |
| ----------------------------------- | ----- | ----------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ACTIVE on V1 (all stages)           | 48    | **Reset/recreate under V2**         | Synthetic; exposed to the corrected workflow (esp. 12 at FX/FINAL under WF-002/003). Recreating under V2 gives a coherent demo aligned to the corrected model. |
| Terminal on V1 (COMPLETED/REJECTED) | 8     | **Preserve as-is on V1 (archived)** | Already finished; useful as historical/demo examples of completed and rejected flows. No exposure to the active-workflow defects.                              |

**Recommended default:** recreate the 48 active requests under V2 from the seeder
catalog (fresh, V2-pinned, demo-appropriate distribution across the corrected
stages), and keep the 8 terminal V1 records for history. This avoids a risky
in-place stage-mapping migration of synthetic data.

**Alternative (only if any record is later asserted to have UAT value):** a
controlled per-record migration with an explicit V1→V2 stage map, dry run,
history+audit preservation, rollback, and separate approval. No such record has
been identified.

---

## 4. Related documents & history impact

- **Documents (58):** for recreated-under-V2 requests, the linked seed documents are re-created by the seeder catalog; the originals belong to the deleted synthetic requests. For preserved terminal V1 requests, their documents stay intact.
- **Workflow history (248) & audit logs:** history/audit for recreated requests is regenerated by the recreation path; the V1 requests' history is removed with them. Terminal V1 requests retain their full history and audit trail. **The workflow _publication_ audit trail (V2 publish / V1 archive) is independent and preserved regardless.**

---

## 5. Rollback / restoration approach (for the future data step)

- **Pre-step snapshot:** capture the V1 request ids, references, stages, pinned version, and dependent-record counts (documents, history) before any reset — a restore manifest.
- **Recreation is idempotent from the seeder catalog**, so re-running the seed restores the synthetic demo set if a reset is reverted.
- **Terminal V1 records are never touched**, so they need no rollback.
- **Any in-place migration path (alternative) must ship its own dry run + inverse stage-map + transactional rollback** before approval.

---

## 6. What still requires explicit approval

- The actual **reset/recreation of the 48 active V1 requests** under V2 (destructive to synthetic V1 data).
- Deletion of the pre-existing stale local artifacts noted earlier (`DBGWF`, `CLAIM_WF_*` versions) is out of Phase B scope and not proposed here.

**Phase B stops here for review.** No V1 request data has been altered.

---

## 7. Data step EXECUTED (2026-07-11, approved)

The approved recreation ran successfully. The 48 active synthetic V1 requests were
recreated under the published V2 (id=39); the 8 terminal V1 records were preserved.

**7-step controlled sequence performed:**

1. **Snapshot** — restore manifest (56 request rows, references, stages, pinned version, 58 documents, 248 history) captured to a scratchpad file **outside** any cleanup path before touching the DB.
2. **Dry-run** — `workflow:recreate-active-under-v2` (no flag) confirmed env=local, V1=1, published V2=39, active-to-recreate=48, terminal-to-preserve=8; mutated nothing.
3. **Counts confirmed** — 48 active + 8 terminal, all synthetic (`ENG-2026-YBRD`/`ENG-2026-TIIB` prefixes; single seed timestamp), V2 published.
4. **Safety review** — FK cascade footprint mapped from migrations + live DB: `engine_request_documents`/`workflow_history` `cascadeOnDelete` (also deleted explicitly, redundant-not-harmful); `audit_logs.workflow_instance_id` `nullOnDelete` (audit rows preserved, 0 linked to the active set anyway); `customs_declarations.engine_request_id` `cascadeOnDelete` (0 linked on the active set); `request_votes` targets the legacy `import_requests` table (empty, voting out of V1); `engine_notifications` is polymorphic (no FK, no block). No blocking FK, no unexpected cascade.
5. **Execute** — `--execute`. The **first** attempt failed closed on the anchor invariant validator (`workflow version must be 1, got 2`) and the transaction **rolled back cleanly** (state fully restored: 48 active + 8 terminal, 58 docs, 248 hist), proving atomicity. Root cause: `EngineRequestAnchorInvariantValidator` hard-pinned the expected version to 1. Fix: parameterize `validate($request, int $expectedVersionNumber = 1)` (default preserves the seeder's V1 contract) and have the builder forward its bound `workflowVersion->version_number`. Re-run then completed.
6. **Command verify** — all 6 built-in checks green: active-on-V1=0, terminal-on-V1=8, active-on-V2=48, requests-at-V2-FINAL=6, orphan-docs=0, orphan-history=0.
7. **Independent verify + regression** — V2 active stage spread matches the original V1 distribution exactly (CREATE 4 / INTERNAL 12 / SUPPORT 8 / EXEC 6 / FX 6 / FX_CONFIRM 6 / FINAL 6 = 48); footprint conserved (V2: 50 docs + 196 hist; V1 terminal retains 8 docs + 52 hist; totals 58/248 match snapshot); 0 global orphans. Regression: 19 seeder-invariant + anchor-history tests green, 11 Phase B command tests green; Pint clean.

**Committed code:** the version-parameterized `EngineRequestAnchorInvariantValidator`
and the builder's forwarding call (real seeder-path improvements, default behavior
unchanged). The recreation command itself remains **local-only / uncommitted** per
the approved data-step contract (execution tooling stays local; only outcomes are
documented). The pre-step snapshot is retained locally as the restore manifest.
