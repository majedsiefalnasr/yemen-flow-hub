# Phase E Checkpoint — Regression-Hardening & Go-Live Verification

**Evidence date:** 2026-07-12 · **Baseline:** `main` post-`5212d91d` (Phase D
close, approved) · **Scope:** regression-hardening and system verification
only — no new product behavior, per the approved Phase E charter.

**Status: Phase E complete. Awaiting review before Phase F.**

---

## 1. Scope executed (E1–E9)

| # | Item | Result |
| - | ---- | ------ |
| E1 | Convert audit probe suites into permanent regression coverage | Both `Phase2RbacProbeTest` and `Phase3WorkflowConfigurationProbeTest` reclassified from "audit artifact" to permanent regression gate; a stale-seed bug in the latter (seeded via `DatabaseSeeder` alone, which only ever creates the pre-fix V1 config — never ran `workflow:publish-import-financing-v2`) was found and fixed. Both suites now 100% green. |
| E2 | Full RBAC matrix after all A–D changes | `tests/Feature/Permission`, `tests/Feature/Audit`, `EngineGovernanceRbacTest`, `EngineBankAdminRbacTest`, `StagePermissionResolverTest`, `PermissionServiceDerivedRequestsTest`, `StagePermissionTest`, `StagePermissionConsistencyTest`, `tests/Feature/Governance` — 0 failures, 516 assertions. |
| E3 | Full workflow-path coverage across V2 | `tests/Feature/Workflow`, `ImportFinancingWorkflowParityTest`, `WorkflowStageRequiresClaimTest`, `tests/Feature/EngineRequest` — 0 failures, 774 assertions. |
| E4 | Concurrency tests (duplicate transitions, optimistic locking, claim races, repeated submissions) | Closed the audit's explicitly-flagged open gap ("a true parallel MySQL transition race is untested"). Added `--transition-concurrency=N` to `PerfLoadScenarioCommand` (real `pcntl_fork` + real MySQL, mirrors the existing `--concurrency` reference-allocator harness). Ran live at 5 and 20 workers: **exactly 1 success, rest `REQUEST_STALE`, zero unexpected outcomes** both times — proves `EngineTransitionService::execute()`'s `lockForUpdate()` correctly serializes. Claim races share the identical `lockForUpdate()` primitive (already sequential-tested); repeated form submissions are the same race class. |
| E5 | All 3 dashboard families live | System Admin (`CbyAdminDashboard.vue`), Bank Admin (`BankAdminDashboard.vue`), Director's `MyWorkDashboard` all verified via `playwright-cli` — correct routing, zero voting UI, zero console errors, real merchant names rendering (Step 8 fix holds). |
| E6 | Browser flows for all roles + 1 new dynamic role | All 8 canonical roles logged in and verified clean (zero console errors): Data Entry, Bank Reviewer, Bank Admin, SWIFT Officer, Support Committee, Executive Member, Committee Director, CBY Admin. A brand-new role (`phase_e6_dynamic_role`) was created live through the designer/roles-admin UI, confirmed correctly listed, then deleted via the `AlertDialog` confirmation flow — zero frontend code changes required, matching the D0 architecture's defining claim (backed by the existing automated `05650774` test). |
| E7 | Terminal-state presentation (active/completed/rejected/cancelled/abandoned) | Backend: `OutcomeSemanticsTest` — all 4 terminal outcomes + ACTIVE fully covered, 8/8 passing (transition-into-outcome, publish-validation, abandon guard matrix, my-queue exclusion, reports/summary counts). Frontend: `RUNTIME_STATUS_BADGE` map (all 5 statuses) had zero test coverage — added 4 new tests to `BankAdminDashboard.test.ts` confirming CANCELLED/ABANDONED both resolve to the locked/immutable token. Live browser verification for ACTIVE/CLOSED/REJECTED confirmed (earlier this session); CANCELLED/ABANDONED live-browser check blocked by absent dev-DB fixture data — **honestly reported, not fabricated** (no mock-data workaround used). |
| E8 | API/UI handling for 403/404/409/422/429/500 | Found and fixed a real defect: `runAction()` in the request-detail page silently swallowed every transition-submit error except `CLAIM_NOT_HELD` — 409/422/429/500 produced **zero user feedback** (button just stopped spinning). Added specific Arabic-copy handling per error code (`REQUEST_STALE`→reload+toast, `TRANSITION_NOT_AVAILABLE`→toast+reload, `STAGE_FIELDS_INVALID`→backend message, 429→rate-limit copy, unknown/500→generic fallback). 5 new tests, 24/24 passing on the file. Live-confirmed 404 and 403 page-load paths render proper `ErrorState`, not blank shells (UI-RBAC-001/002 holds). |
| E9 | Findings traceability matrix | [`17-phase-e-traceability-matrix.md`](./17-phase-e-traceability-matrix.md) — all 16 original findings (verified row count against `04-final-report.md` §6, including WF-002) mapped to fix commit + automated test + verification + status (all ✅ RESOLVED), plus 1 new finding (E8-001) discovered and resolved in this same phase — 17 findings total across the audit's lifecycle, 17 resolved. Plus a 7-entry baseline-failure registry with stable IDs (`BASELINE-FE-001..004`, `BASELINE-BE-001..002`, `BASELINE-FMT-001`). |

---

## 2. New defects found and fixed during Phase E (not pre-existing findings)

1. **`Phase3WorkflowConfigurationProbeTest` stale-seed bug** — test infrastructure bug, not a product defect. Fixed by seeding V2 via `workflow:publish-import-financing-v2 --publish` (the pattern every other V2-dependent test already used).
2. **E8-001 — silent transition-error swallowing** — real product defect (Medium). A user clicking an action button that fails for any reason except a lost claim saw no error message at all. Fixed with specific per-error-code Arabic toasts + auto-reload on 409/422.
3. **`BASELINE-FE-002` — missing `extractApiErrorMessage` import** in `useReferenceData.ts:87` — real product defect (would throw in production on any reference-data-values load error, not just in tests). One-line import fix.

All three are covered by new or existing automated tests; none required scope expansion beyond "regression-hardening and verification," consistent with the Phase E charter.

---

## 3. Test and build results

- **Backend:** `Phase2RbacProbeTest` + `Phase3WorkflowConfigurationProbeTest` — 19/19 passing. RBAC/Permission/Governance matrix — 0 failures, 516 assertions. Workflow-path suite — 0 failures, 774 assertions. Dashboard+Engine (final full re-run) — 0 failures, 1238 assertions, 318 deprecation notices (BASELINE-BE-001, cosmetic PDO/SSL noise only). `OutcomeSemanticsTest` — 8/8. Pint on all touched files — clean.
- **Frontend:** `workflows-instance-detail.test.ts` — 24/24 (19 original + 5 new). `BankAdminDashboard.test.ts` — 20/20 (16 original + 4 new). ESLint/Prettier on all touched files — clean. Full suite re-run: **9 failed files / 18 failed tests / 1011 passed / 8 skipped** — exact match to the established baseline (`BASELINE-FE-001` + `BASELINE-FE-004`, 9 files / 18 tests combined), confirming zero new regressions; passed-test count rose from 1002→1011 (+9, all new tests added this phase).
- **Concurrency:** live `perf:load-scenario --transition-concurrency=5` and `=20` against real MySQL — both PASS, zero residue after cleanup.
- **Live browser (`playwright-cli`):** all 8 roles, all 3 dashboard families, 404/403 error states, dynamic-role creation/deletion — all clean, zero unexpected console errors (one isolated batch of 429s was this session's own rapid-navigation testing pace against the 120 req/min per-user limit, confirmed via a clean reload, not a defect).

---

## 4. Baseline failures (explicitly not Phase A–E regressions)

Full registry with stable IDs in [`17-phase-e-traceability-matrix.md`](./17-phase-e-traceability-matrix.md#baseline-finding-registry-stable-ids):

- `BASELINE-FE-001` — 8 pre-existing frontend test files, unrelated to this work.
- `BASELINE-FE-002` — **RESOLVED this session** (was the `extractApiErrorMessage` import bug).
- `BASELINE-FE-003` — newly *exposed* (not newly caused) by resolving FE-002: 3 `ReferenceDataPage.test.ts` tests fail for a distinct, deeper reason (row rendering under MANAGE capability). Tracked as task #14 for future root-cause; not fixed in this phase since it's outside Phase E's regression-hardening scope and needs its own investigation.
- `BASELINE-FE-004` — `auth.store.test.ts` `isCbyUser`/`isBankUser` getter bug, 7 tests, unrelated to this work.
- `BASELINE-BE-001` — PHP/PDO SSL-constant deprecation noise, cosmetic only, 0 failures caused.
- `BASELINE-FMT-001` — repo-wide Pint/ESLint debt outside any file touched by Phase A–E.
- `BASELINE-BE-002` — 11 pre-existing frontend TS errors, none in Phase D/E-touched files.

---

## 5. Net code change (Phase E only)

| File | Change |
| ---- | ------ |
| `backend/tests/Feature/Audit/Phase3WorkflowConfigurationProbeTest.php` | +1 helper method, 3 call-site fixes (stale-seed bug) |
| `backend/tests/Feature/Audit/Phase2RbacProbeTest.php` | Docblock reclassification only |
| `backend/app/Console/Commands/PerfLoadScenarioCommand.php` | +~180 lines: `--transition-concurrency` scenario (new concurrency proof harness) |
| `frontend/app/pages/workflows/instances/[id].vue` | +~15 lines: per-error-code toast handling in `runAction()` (E8-001 fix) |
| `frontend/app/tests/unit/pages/workflows-instance-detail.test.ts` | +~95 lines: 5 new tests for the E8-001 fix |
| `frontend/app/tests/unit/components/BankAdminDashboard.test.ts` | +~35 lines: 4 new tests for the runtime_status badge map (E7) |
| `frontend/app/composables/useReferenceData.ts` | +1 line: missing import fix (BASELINE-FE-002) |
| `docs/audit-functional/17-phase-e-traceability-matrix.md` | New file: findings traceability matrix (16 original + 1 new = 17 total) + baseline registry |
| `docs/audit-functional/18-phase-e-checkpoint.md` | This checkpoint |

No production behavior changed except the two defect fixes (E8-001, BASELINE-FE-002) and the new opt-in concurrency-proof CLI scenario (never runs in normal request handling). No new product features. No files deleted.

---

## 6. Remaining Phase F work (gated, not started)

Per the approved Phase D/E scope, Phase F is legacy cleanup only, gated on
its own independent exit criteria:

- Backend voting-model removal (`VoteType` enum, vote-related columns/tables/routes/jobs/seed data) — no active route/UI depends on it (confirmed in Phase D).
- Unused `voting_opened` notification support (allow-listed but never dispatched).
- Remaining `CUSTOMS_DECLARATION_ISSUED` references in backend migrations/seeder/test comments (historical audit-label reading must be preserved — explicit requirement from the Phase D acceptance message).
- Dead unreachable exception handlers in `bootstrap/app.php` (`WorkflowImmutableStateException`, `WorkflowLockedStateException` — classes that don't exist anywhere, found during this phase's CF-5 investigation; tracked as task #13).
- `docs/user-view/` deprecation (already non-authoritative per Phase D's AGENTS.md rewrite; physical removal/archival is Phase F).
- Semantic-role compatibility fallback removal — **blocked**, none of its 4 documented exit criteria met yet.

Also carried forward, not Phase F (separate, smaller, whenever convenient):

- Task #14 — root-cause `BASELINE-FE-003`'s 3 newly-exposed `ReferenceDataPage.test.ts` failures.

Before any Phase F destructive/legacy-removal work: verify no active route uses the targeted artifact, no current workflow version references it, no queue/listener uses it, no historical audit record becomes unreadable, and no migration rollback or archived data depends on it — per the explicit Phase D acceptance constraints.

---

**Phase E is complete. Do not begin Phase F until this checkpoint is reviewed.**
