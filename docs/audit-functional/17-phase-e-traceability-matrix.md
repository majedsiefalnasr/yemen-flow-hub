# Phase E — 17-Finding Traceability Matrix

**Evidence date:** 2026-07-12 · **Baseline:** `main` post-`5212d91d` (Phase D close)

Maps every finding from [`04-final-report.md`](./04-final-report.md) §6 to its
fix commit, automated test, manual verification, and current status. Built as
part of Phase E item 9 (regression-hardening and system-verification phase).

**Status legend:** ✅ RESOLVED (fix landed, verified) · 🟡 DEFERRED (explicitly
gated to a later phase, tracked) · ⚪ N/A (accepted V1 behavior, not a defect)

---

| ID                  | Sev      | Fix commit(s)                                                                                                     | Automated test                                                                                                                                                     | Manual/browser verification                                                                                                    | Status                                                                    |
| ------------------- | -------- | ------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| **RBAC-004**        | Critical | `c8be6b1f` fix(auth): scope engine-request detail/execute by org classification                                   | `Phase2RbacProbeTest` (cf3 org-classification cases) — green post-fix                                                                                             | Live: null-bank/OTHER-org user denied detail access (Phase A verification)                                                     | ✅ RESOLVED                                                                |
| **WF-003**          | Critical | `ef99c8b5` feat(workflow): build corrected IMPORT_FINANCING V2 via designer lifecycle (WF-001/002/003)             | `SwiftPackageGateV2Test` — SWIFT package (ref + 2 PDFs) blocks FX→FX_CONFIRM until present                                                                       | Live: SWIFT Officer upload flow, `swift-fx-gate-422.png` evidence screenshot                                                    | ✅ RESOLVED                                                                |
| **RBAC-002**        | High     | `3e0c5a43` fix(auth): reject admin-only and universal screen grants in update API                                 | `Phase2RbacProbeTest` (cf2 admin-only-screen cases) — green post-fix                                                                                              | —                                                                                                                                 | ✅ RESOLVED                                                                |
| **RBAC-001**        | High     | `2978881f` fix(auth): authorize only the active pivot on an active role                                            | `Phase2RbacProbeTest` (cf1 inactive-pivot cases) — green post-fix                                                                                                 | —                                                                                                                                 | ✅ RESOLVED                                                                |
| **WF-001**          | High     | `ef99c8b5` (same V2 build — B1: reasoned rejects + explicit self-loop)                                             | `Phase3WorkflowConfigurationProbeTest` (3/3, fixed to seed V2 in Phase E1 — was stale-config false-positive, not a live regression) · `PublishImportFinancingV2CommandTest` | —                                                                                                                                 | ✅ RESOLVED                                                                |
| **RBAC-005**        | High     | `39e74922` fix(workflow): align Director dashboard with its FINAL queue (UI-FX-001/RBAC-005)                      | —                                                                                                                                                                  | Live (Phase E6): Director's `/workflows` nav link resolves without a 403 redirect                                              | ✅ RESOLVED                                                                |
| **UI-FX-001**       | High     | `39e74922` (same commit — Director dashboard/`/customs` queue unification)                                        | —                                                                                                                                                                  | Live (Phase E5): Director dashboard "0" matches `/customs` "طلبات جاهزة للإصدار (0)" — counts agree                             | ✅ RESOLVED                                                                |
| **API-UI-001**      | High     | `18552127` docs(workflow): record Phase C checkpoint (API-UI-001, UI-RBAC, Director parity) + underlying MySQL/single-flight fix | —                                                                                                                                                                  | Live: no retry-storm observed in this session's `/customs`, `/workflows` navigation (isolated 429s were this session's own rapid testing pace, confirmed via clean reload) | ✅ RESOLVED                                                                |
| **UI-RBAC-001**     | Medium   | `a59d675b` fix(frontend): route-guard designer and error-state instance load (UI-RBAC-001/002)                    | `workflows-instance-detail.test.ts` (403/404/429 ErrorState render tests)                                                                                         | Live (Phase E8): `/workflows/instances/999999` renders proper 404 ErrorState, not a blank shell                                | ✅ RESOLVED                                                                |
| **UI-RBAC-002**     | Medium   | `a59d675b` (same commit)                                                                                           | `workflows-instance-detail.test.ts` (403/404/429 ErrorState render tests, +5 new tests added Phase E8 for 409/422/429/500 on the action-submit path)              | Live (Phase E8): 404 confirmed; action-submit error branches (previously silently swallowed except CLAIM_NOT_HELD) fixed and tested this phase | ✅ RESOLVED (extended in Phase E8 — see finding below)                    |
| **RBAC-003**        | Medium   | `2978881f` (same commit as RBAC-001 — shared active-identity helper fix)                                           | `Phase2RbacProbeTest` (cf4 `/auth/me` active-roles-only case) — green post-fix                                                                                    | —                                                                                                                                 | ✅ RESOLVED                                                                |
| **H6**              | High     | `715a4377` fix(auth): fail closed on demo switch and visual bypass in production                                  | `Phase2RbacProbeTest` (demo-switch-forbidden-when-flag-off case) — green post-fix · `DemoRouteEnvironmentGateTest`                                               | —                                                                                                                                 | ✅ RESOLVED                                                                |
| **CF-5**            | Low      | Not a live defect — see investigation note below                                                                   | 10+ tests (`StageFieldRuleTest`, `WorkflowTransitionTest`, `WorkflowPublishTest`, `WorkflowVersionLifecycleTest` ×2, `FieldDefinitionTest`, `WorkflowStageTest` ×2, `StagePermissionTest` ×2) all assert `WORKFLOW_IMMUTABLE_STATE` → 409, all passing | Phase E9 investigation: the reachable exception path (`WorkflowVersionImmutableException`) returns 409, matching `AGENTS.md`. Two `bootstrap/app.php` handlers registered for `403` reference exception classes (`WorkflowImmutableStateException`, `WorkflowLockedStateException`) that **do not exist as files anywhere in the codebase** — permanently unreachable dead code, not a live inconsistency. Tracked as task #13 for Phase F cleanup (remove dead handlers). | ✅ RESOLVED (live behavior is single/consistent; dead code noted for cleanup) |
| **CF-6 / F-DOC-1**  | Medium   | `9e4dfe06`, `a0c7a44d`...`5212d91d` (Phase D's full AGENTS.md rewrite, esp. the D Step 11 doc-reconciliation commits) | —                                                                                                                                                                  | Phase E9: `grep -n "22-status\|8-role" AGENTS.md` → 0 hits; canonical 4-concept state model + 8-role enum both current           | ✅ RESOLVED                                                                |
| **STATUS-DRIFT-001**| High     | `99b5de7f`, `8b9388df`, `1451185d`, plus the full Phase D Steps 8-12 sequence (RequestStatus enum + all 1,119 refs removed) | 24+ frontend test files updated/added across Phase D; `types/enums.test.ts` confirms no `RequestStatus` export exists                                            | Live: verified across System Admin, Bank Admin, Director, SWIFT Officer, Data Entry, Bank Reviewer, Support Committee, Executive Member (Phase E6) — all read `runtime_status`/`current_stage`/`semantic_role`/`final_outcome`, zero references to the deleted enum | ✅ RESOLVED                                                                |

**WF-002** (re-scoped Critical→High post-M1, see `04-final-report.md` §6 note)
is folded into the same `ef99c8b5` V2 build (B2: FINAL EXECUTE moved from
`committee_manager` to `committee_director`) — not a separate row above
because the final report's finding table treats it as a footnoted variant of
the WF-001 row group; its two concrete defects (FINAL ownership, reasoned
rejects) are both covered by the same commit and the same
`PublishImportFinancingV2CommandTest`/`OutcomeSemanticsTest` suites. Verified
live: Director-only FINAL-stage EXECUTE confirmed via the role/permission
matrix in Phase E2's full RBAC run.

---

## New finding surfaced during Phase E (not in the original 17)

**E8-001 (Medium, RESOLVED same session).** `runAction()` in
`frontend/app/pages/workflows/instances/[id].vue` silently swallowed every
transition-submit error except `CLAIM_NOT_HELD` — a 409 (`REQUEST_STALE`), 422
(`TRANSITION_NOT_AVAILABLE` / `STAGE_FIELDS_INVALID`), 429, or 500 on the
action-submit path produced zero user feedback; the button simply stopped
spinning. Distinct from UI-RBAC-002 (which covered the page-*load* path only).
Fixed in this session: dedicated Arabic toast per error code, with an
automatic reload on 409/422 so the user sees current state. 5 new tests added
to `workflows-instance-detail.test.ts` (24/24 passing). No dedicated finding
ID existed for this in the original audit; assigned `E8-001` for future
reference.

---

## Summary

| Status                                  | Count |
| ---------------------------------------- | ----- |
| ✅ RESOLVED                              | 16    |
| 🟡 DEFERRED (Phase F, tracked, non-blocking) | 0 (CF-5's dead-code cleanup is tracked but not a live defect) |
| New findings surfaced + resolved in Phase E | 1 (E8-001) |

All 16 tracked findings from the original audit are resolved with commit +
test + verification evidence. The compatibility fallback
(`SemanticRegistry::stageCodeAliases()`) remains intentionally active per its
documented exit criteria (see `AGENTS.md` — not itself a finding, a tracked
temporary mechanism). Backend voting-model artifacts (`VoteType` enum,
`voting_opened` notification type, dead `bootstrap/app.php` exception
handlers) are explicitly Phase F scope, not Phase E blockers.

---

## Baseline finding registry (stable IDs)

Pre-existing failures/debt not caused by any Phase A–E change, so future
validation reports can classify **New regression** / **Known baseline
failure** / **Resolved baseline failure** without re-litigating whether each
one is "ours." IDs are stable — reuse them, don't renumber.

| ID                | Description                                                                                                                                          | Location                                                                                                                                                                                                     | Status as of this report                                                                                                                                    |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **BASELINE-FE-001** | Frontend Vitest baseline: 8 pre-existing failing test files unrelated to Phase D/E work — `WorkflowPublishPanel.test.ts`, `GovernanceRolesPage.test.ts`, `LoginPage.test.ts`, `OrganizationsPage.test.ts`, `TeamsPage.test.ts`, `notifications.page.test.ts` (×2 tests), plus `CommandPalette.test.ts`'s unrelated navigation-labels test | Various `frontend/app/tests/unit/**`                                                                                                                                                                        | 🟡 KNOWN BASELINE — unchanged this session, not investigated further (out of Phase E scope)                                                                    |
| **BASELINE-FE-002** | `extractApiErrorMessage is not defined` — missing import in `useReferenceData.ts:87`, threw whenever `fetchReferenceValues` hit an error path (masked 3 tests in `ReferenceDataPage.test.ts` behind a `ReferenceError` instead of their real assertions) | `frontend/app/composables/useReferenceData.ts:87` (now `:88` post-fix)                                                                                                                                       | ✅ **RESOLVED this session** — one-line import fix added, confirmed via reproduction before/after                                                              |
| **BASELINE-FE-003** | With BASELINE-FE-002's masking crash removed, `ReferenceDataPage.test.ts` still fails 3/7 tests for a distinct, deeper reason: the MANAGE-capability row-action menu trigger doesn't render, and `tbody tr` doesn't render at all in 2 of the 3 (table shows its empty state instead of the `TABLE` fixture row) | `frontend/app/tests/unit/pages/ReferenceDataPage.test.ts` (tests: `exposes a row action menu trigger...`, `loads values when a table row is selected`, `shows a selected table summary...`)                | 🟡 KNOWN BASELINE, newly exposed — tracked as task #14, not yet root-caused; was previously invisible because BASELINE-FE-002 crashed these tests before reaching these assertions |
| **BASELINE-FE-004** | Auth store role-classification bug: `isCbyUser`/`isBankUser` getters return incorrect values after `store.login()` in 7 test cases                                                                                                                    | `frontend/app/tests/unit/stores/auth.store.test.ts` (7 tests, e.g. `CBY_ADMIN is a CBY user`)                                                                                                               | 🟡 KNOWN BASELINE — unchanged this session, not investigated further (out of Phase E scope)                                                                    |
| **BASELINE-BE-001** | PHP/PDO SSL-constant deprecation noise: `Constant PDO::MYSQL_ATTR_SSL_CA is deprecated` fires on every test touching a DB connection — a PHP/PDO version-mismatch warning, not an application defect. Appears as "N deprecated" in every backend PHPUnit run this session (e.g. 318 in the Dashboard+Engine suite, 204 in the workflow-path suite) | Backend-wide — PHP/PDO driver version vs. Laravel's MySQL connection config                                                                                                                                 | 🟡 KNOWN BASELINE — cosmetic only (0 test failures caused), unchanged this session                                                                             |
| **BASELINE-FMT-001** | Repository-wide formatting/lint debt outside any file touched by Phase A–E: a long-standing backend Pint backlog across files never edited in this audit, plus 2 pre-existing frontend ESLint errors (`reports.store.ts` unused import, `story-12-3-gates.spec.ts` e2e parsing error — the latter's source file, `story-12-3-role-gates.test.ts`, was itself deleted in Phase D Step 9; the `.spec.ts` e2e counterpart may be stale and worth a Phase F check) | Repo-wide, various untouched files                                                                                                                                                                          | 🟡 KNOWN BASELINE — unchanged this session, confirmed via `git log --oneline -3 -- <file>` per-file baseline checks in Phase D                                 |
| **BASELINE-BE-002** | Frontend typecheck baseline: 11 pre-existing TS errors, none in files touched by any Phase D/E commit — largely `WorkflowStage`/`FieldDefinition`/`NotificationType` optional-vs-required property mismatches in designer test fixtures (`StageFieldRuleMatrix.test.ts`, `StagePermissionEditor.test.ts`, `WorkflowFieldDesigner.test.ts`, `useWorkflowFields.test.ts`, `useWorkflowStages.test.ts`, `useWorkflowTransitions.test.ts`, `notificationNavigation.test.ts`) plus 3 non-test files (`notifications.vue`, `reports/index.vue`, `workflowNavigation.ts`) | Various `frontend/app/**` — see full list via `pnpm typecheck`                                                                                                                                             | 🟡 KNOWN BASELINE — confirmed unchanged post Phase E8 fix (re-ran `pnpm typecheck`, zero new errors in `useReferenceData.ts`/`apiErrors.ts`/`[id].vue`)         |

**How to use this table:** when a future test/typecheck/lint run shows a
failure, check whether its ID appears here. If yes → **known baseline
failure**, don't treat as a task blocker (though BASELINE-FE-003 is actively
tracked as task #14 for eventual root-cause). If no → **new regression**,
investigate before proceeding. When a baseline ID's underlying issue is
fixed, mark it ✅ RESOLVED in this table (see BASELINE-FE-002 above) rather
than deleting the row — the row is the audit trail.
