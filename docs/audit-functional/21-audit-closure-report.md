# Yemen Flow Hub — Functional/RBAC/Workflow Audit: Final Closure Report

**Evidence date:** 2026-07-12 · **Baseline:** `main` post-`262ef0b4` (Phase F close) · **Audit scope:** role & permission correctness, workflow designer→runtime fidelity, UI functional/UX behavior, regression-hardening, gated legacy cleanup.

**Phases:** A (security boundary) → B (workflow correctness) → C (API/UI reliability) → D (status-model reconciliation, M6 Option B) → E (regression-hardening) → F (gated legacy cleanup). **All six phases are closed and approved.**

---

## 1. Executive summary

The audit began by finding a critical authorization boundary failure
(RBAC-004: null-`bank_id` users could read/mutate any request across
organizations) and a critical workflow-configuration failure (WF-003: the
SWIFT stage advanced with no document package), plus a cluster of High/Medium
findings spanning authorization, workflow config, API reliability, and a
1,119-reference legacy frontend status-enum drift (STATUS-DRIFT-001).

All 17 findings tracked across the audit's full lifecycle (16 from the
original `04-final-report.md`, plus 1 discovered during Phase E's own
verification pass) are now **fixed and verified**, each with a specific fix
commit, an automated regression test, and — where applicable — live browser
verification. No finding was closed by assertion alone.

Two items remain **deferred cleanup**, explicitly gated behind separate
future approval, not go-live blockers: physical archival of the deprecated
`docs/user-view/` directory, and removal of two dead-but-persisted enum
values (`NotificationType::VOTING_OPENED`, `AuditAction::VOTE_CAST`).

Regression is green: full backend suites (RBAC, workflow, dashboard,
governance, merchants) — **0 failures**. Frontend suite — 8 known-baseline
failures unrelated to this audit's scope (down from 9 after Phase F fixed
one at its root cause), **1014 passing**, zero new regressions introduced
across all six phases.

**Recommendation: application-level go-live readiness is approved**,
subject to the deployment/operational readiness checklist in §12 — this
audit verified the application's logic, not the production environment.

---

## 2. Final architecture

**Backend:** Laravel 11, Sanctum cookie auth, MySQL + Redis. Service-oriented:
`EngineTransitionService` is the sole mutation path for `EngineRequest`
stage/status changes — validates stage permissions
(`StagePermissionResolver`), field rules (`StageFieldRuleValidator`), and
claim ownership inside a `lockForUpdate()`-guarded `DB::transaction`, proven
to correctly serialize concurrent callers (Phase E4, live 20-worker test:
exactly 1 success, 19 `REQUEST_STALE`).

**Frontend:** Nuxt 4, Vue 3, Pinia, shadcn-vue, RTL/Arabic-first. Two
dashboard families selected by capability, not role name: the operational
family (`MyWorkDashboard.vue`, works for any dynamic executor role
automatically) and the analytics/governance family
(`SystemAdminDashboard`/`CbyAdminDashboard.vue`, `BankAdminDashboard.vue`).

**Authorization** runs on independent mechanisms now reconciled to a single
source of truth per surface: stage permissions (`stage_permissions`,
NULL-wildcard, EXECUTE ⊃ VIEW) for engine routing; screen permissions
(`screens`/`screen_permissions`) for admin/governance surfaces; `DataScope`
for query-level org/bank isolation; active-pivot-only role resolution
(fixed RBAC-001/003's inactive-pivot leak).

---

## 3. Final finding tally

**17 findings tracked. 17 fixed and verified. 0 open.**

| Category  | Count                                                                         |
| --------- | ----------------------------------------------------------------------------- |
| Critical  | 2 (RBAC-004, WF-003)                                                          |
| High      | 9 (RBAC-001/002/005, WF-001/002, UI-FX-001, API-UI-001, H6, STATUS-DRIFT-001) |
| Medium    | 5 (UI-RBAC-001/002, RBAC-003, CF-6/F-DOC-1, E8-001)                           |
| Low       | 1 (CF-5)                                                                      |
| **Total** | **17**                                                                        |

Every finding's terminal status, in the vocabulary required for this
closure report:

| ID                          | Status                                                                                                                                                                                                            |
| --------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RBAC-004                    | **Fixed and verified**                                                                                                                                                                                            |
| WF-003                      | **Fixed and verified**                                                                                                                                                                                            |
| RBAC-002                    | **Fixed and verified**                                                                                                                                                                                            |
| RBAC-001                    | **Fixed and verified**                                                                                                                                                                                            |
| WF-001                      | **Fixed and verified**                                                                                                                                                                                            |
| WF-002                      | **Fixed and verified** (re-scoped Critical→High pre-Phase-A per the M1 designer-first review; narrowed scope fixed, see §6)                                                                                       |
| RBAC-005                    | **Fixed and verified**                                                                                                                                                                                            |
| UI-FX-001                   | **Fixed and verified**                                                                                                                                                                                            |
| API-UI-001                  | **Fixed and verified**                                                                                                                                                                                            |
| UI-RBAC-001                 | **Fixed and verified**                                                                                                                                                                                            |
| UI-RBAC-002                 | **Fixed and verified** (page-load path fixed Phase C; action-submit path — the same finding class — extended and fixed Phase E8)                                                                                  |
| RBAC-003                    | **Fixed and verified**                                                                                                                                                                                            |
| H6                          | **Fixed and verified**                                                                                                                                                                                            |
| CF-5                        | **Fixed and verified** (Phase F: confirmed the live code path is single/consistent at 409; 3 genuinely dead exception classes removed; 1 handler with real narrow reachability correctly retained, not a finding) |
| CF-6 / F-DOC-1              | **Fixed and verified**                                                                                                                                                                                            |
| STATUS-DRIFT-001            | **Fixed and verified**                                                                                                                                                                                            |
| E8-001 (discovered Phase E) | **Fixed and verified**                                                                                                                                                                                            |

Full commit/test/verification evidence per finding:
[`17-phase-e-traceability-matrix.md`](./17-phase-e-traceability-matrix.md).

No finding ID was renumbered, merged, or silently dropped. The one prior
count inconsistency (a Phase E checkpoint draft that undercounted at 15,
missing WF-002's standalone row) was reconciled and documented in
commit `bfc43060` before Phase F began.

---

## 4. Findings-to-commit traceability

See [`17-phase-e-traceability-matrix.md`](./17-phase-e-traceability-matrix.md)
for the complete per-finding table (fix commit hash + message, automated
test name, live verification method). Representative examples:

- RBAC-004 → `c8be6b1f` → `Phase2RbacProbeTest` (cf3 cases) → live null-bank/OTHER-org denial check
- WF-003 → `ef99c8b5` → `SwiftPackageGateV2Test` → live SWIFT upload flow (`swift-fx-gate-422.png`)
- STATUS-DRIFT-001 → `99b5de7f`/`8b9388df`/`1451185d` + full Phase D Steps 8–12 → 24+ frontend test files → live check across all 8 roles
- E8-001 → this session's Phase E8 commit → 5 new tests in `workflows-instance-detail.test.ts` → live 404 ErrorState check

---

## 5. Security verification

- **RBAC-004 (Critical, fixed):** engine-request detail/execute now scoped
  by org classification — cross-org read/mutation confirmed denied.
- **RBAC-001/003 (fixed):** privileged helpers (`isSystemAdmin`,
  `hasRoleCode`) now authorize only the active pivot on an active role;
  inactive/demoted admin privilege-retention closed.
- **RBAC-002 (fixed):** admin-only and universal screen-permission grants
  rejected server-side; self-escalation chain to `workflow_designer` closed.
- **H6 (fixed):** demo-switch and visual-bypass flags now fail closed via
  environment cross-check, not just a boolean/build flag.
- **Concurrency (Phase E4):** `EngineTransitionService::execute()`'s
  `lockForUpdate()` proven under real parallel MySQL contention (forked OS
  processes, not simulated) — exactly one success at 5 and 20 concurrent
  workers, zero lost updates, zero duplicate transitions.
- **Claim races:** share the identical `lockForUpdate()` primitive as
  transitions, proven by the same mechanism.
- **HTTP error surface (403/404/409/422/429/500):** all six codes verified
  to produce user-visible, role-appropriate responses — no blank shells
  (UI-RBAC-001/002), no silently-swallowed action failures (E8-001, fixed
  this audit).

---

## 6. Workflow V2 contract

`IMPORT_FINANCING` V2 (built via the real Workflow Designer lifecycle:
clone → correct DRAFT → validate → publish → archive V1, commit `ef99c8b5`)
is the current PUBLISHED version. Corrections applied and verified:

- **B1 (WF-001):** all 4 consequential reject transitions require a comment
  - confirmation message; the SUPPORT self-loop is explicitly marked
    intentional. `Phase3WorkflowConfigurationProbeTest` (fixed at its own
    stale-seed bug in Phase E1) confirms 0 validator errors on the live
    PUBLISHED version.
- **B2 (WF-002):** FINAL stage EXECUTE moved from `committee_manager` to
  `committee_director`; EXEC keeps `committee_manager` (Executive Committee
  decides directly — accepted V1 behavior, Executive Voting is out of V1
  scope by design, not a defect).
- **B3 (WF-003):** SWIFT package (`swift_reference`, `swift_file`,
  `fx_request_file`) required and enforced before FX→FX_CONFIRM; visible
  read-only downstream. Live-verified via the SWIFT Officer upload flow.
- **B4 (semantic_role):** populated on all 7 operational stages of V2
  through the designer lifecycle (`updateStage`), same mechanism a human
  designer user would use.

**48 synthetic ACTIVE requests were recreated under V2**; 8 terminal
(COMPLETED/REJECTED) V1 requests preserved as historical record, unaffected
by the correction.

**Semantic-role compatibility fallback**
(`SemanticRegistry::stageCodeAliases()`,
`EngineRequestReadModel::bucket()`/`SemanticResolver::stageForRole()`)
remains **active by design** — none of its 4 documented removal criteria are
met yet (not every ACTIVE-reachable version has full `semantic_role`
coverage; no regression test proves the code-only path dead). This is an
**accepted limitation**, not an open finding — it has explicit, measurable
exit criteria and is intentionally not removed prematurely.

---

## 7. Dashboard architecture

Two families, selected by capability, never by role name — confirmed
unchanged and correct through Phase D, E, and F:

- **Operational family** (`MyWorkDashboard.vue`): every workflow-executor
  role, including a brand-new dynamically-created role, gets a working
  actionable queue with **zero frontend code changes** — proven live in
  Phase E6 (created and deleted a test role through the live admin UI) and
  backed by the automated `UserActionableRequestQueryTest`.
- **Analytics/governance family** (`SystemAdminDashboard`/
  `CbyAdminDashboard.vue`, `BankAdminDashboard.vue`): gated on
  `system_dashboard`/`bank_analytics` screen capabilities independently on
  both frontend routing and backend `DashboardStatsService`.
- **Shared actionable-work invariant:** dashboard preview, `/my-queue`, and
  the nav badge all resolve through one contract
  (`UserActionableRequestQuery`) and stay equal **by record ID**.
- **Known, deliberately retained residual:** `DashboardStatsService`'s 6
  legacy per-role stats branches (`dataEntryStats` etc.) remain live and
  tested but have zero frontend consumer since the D0 migration — not
  removed, since doing so is a product-contract decision outside this
  audit's "no new product behavior" scope. **Accepted limitation.**

---

## 8. Canonical state model

Request state is four independent concepts, confirmed with zero
`RequestStatus`-style enum remaining anywhere in the frontend
(STATUS-DRIFT-001, fully resolved):

- **`runtime_status`** — `ACTIVE | CLOSED | REJECTED | CANCELLED | ABANDONED`
- **`current_stage`** — designer-defined, includes `semantic_role`
- **`current_stage.semantic_role`** — 8-case `StageSemanticRole` enum, `EXECUTIVE_REVIEW` current name
- **`final_outcome`** — `COMPLETED | REJECTED | CANCELLED | ABANDONED | null`, lives on the terminal stage

All 5 `runtime_status` values verified with backend transition-semantics
tests (`OutcomeSemanticsTest`, 8/8) and a frontend badge-map test added
this audit (Phase E7, `BankAdminDashboard.test.ts`). Live browser
verification confirmed for ACTIVE/CLOSED/REJECTED; CANCELLED/ABANDONED
live-browser checks are an **accepted limitation** — no fixture data exists
in this dev environment, and none was fabricated to force a screenshot
(per this session's explicit no-mock-data discipline).

---

## 9. Regression evidence

- **Backend, full re-run post-Phase-F:** `Dashboard`, `Engine`, `Workflow`,
  `Merchants`, `Permission`, `Audit`, `Governance` suites — **0 failures**,
  2406 assertions.
- **Frontend, full re-run post-Phase-F:** 8 failed files / 15 failed tests /
  1014 passed / 8 skipped — improved from the Phase D/E baseline of 9
  files/18 tests (Phase F fixed `ReferenceDataPage.test.ts` at its actual
  root cause, not by weakening assertions).
- **Concurrency:** live `perf:load-scenario --transition-concurrency={5,20}`
  against real MySQL — both PASS.
- **Backend Pint:** 50 files with pre-existing debt (unchanged,
  `BASELINE-FMT-001`), zero new debt from any phase's touched files.
- **Frontend ESLint/Prettier:** clean on every touched file across all
  phases.
- **App boot:** 233 routes, `composer dump-autoload -o` clean.

---

## 10. Known baseline debt

Pre-existing, unrelated to this audit's scope — stable IDs assigned so
future validation reports can distinguish **known baseline debt** from a
**new regression**:

| ID                                                                  | Status                                                |
| ------------------------------------------------------------------- | ----------------------------------------------------- |
| BASELINE-FE-001 (8 pre-existing frontend test files)                | **Known baseline debt**                               |
| BASELINE-FE-002 (missing `extractApiErrorMessage` import)           | **Fixed and verified** (this audit, Phase E9)         |
| BASELINE-FE-003 (`ReferenceDataPage.test.ts` fixture bugs)          | **Fixed and verified** (this audit, Phase F task #14) |
| BASELINE-FE-004 (`auth.store.test.ts` role-getter bug, 7 tests)     | **Known baseline debt**                               |
| BASELINE-BE-001 (PHP/PDO SSL deprecation noise)                     | **Known baseline debt** (cosmetic, 0 failures caused) |
| BASELINE-FMT-001 (repo-wide Pint/ESLint debt outside touched files) | **Known baseline debt**                               |
| BASELINE-BE-002 (11 pre-existing frontend TS errors)                | **Known baseline debt**                               |

Full detail: [`17-phase-e-traceability-matrix.md`](./17-phase-e-traceability-matrix.md#baseline-finding-registry-stable-ids).

---

## 11. Deferred cleanup

Both items below are explicitly **not** go-live blockers — legacy-cleanup
work correctly gated behind their own future approval, per this audit's
evidence-driven discipline (nothing was removed on the assumption that it
"looked legacy").

### `docs/user-view/` physical archival — **deferred cleanup**

8 per-role UX spec files, already marked deprecated historical material in
`AGENTS.md` since Phase D. A concrete archival proposal exists
(`19-phase-f-inventory.md` §5b: move to `docs/archive/user-view/`, add a
deprecation-banner README, fix 12 referencing links) but was **not
executed**, per this closure's explicit gate:

- [ ] No repository links depend on their current paths (12 referencing
      files currently do — the move must update them atomically)
- [ ] No external documentation process references them (not verified this
      audit — outside repo scope)
- [ ] Archival destination and redirect strategy agreed
- [ ] Move performed in a dedicated documentation commit

### Persisted enum values — **deferred cleanup**

`NotificationType::VOTING_OPENED` and `AuditAction::VOTE_CAST` are
registered (in `NotificationRegistry`/`TemplateResolver`) but never
dispatched by any live code path. Confirmed **not removed** pending:

- [ ] Whether either value exists in database rows — checked in this dev
      DB (0 rows for both), **not checked against any other environment**
- [ ] Whether historical audit logs or notifications still render them —
      `audit.vue`'s label map already has a graceful fallback for both,
      independent of whether the enum case itself is removed
- [ ] Whether archived seed data or fixtures contain them — not audited
      this pass
- [ ] Whether API consumers expect those values — not audited this pass
- [ ] Whether a compatibility mapping is required if removed — the
      frontend label map already provides one; the backend enum case
      itself is the remaining open question

**Backend voting-model artifacts already removed this audit** (Phase F,
dependency-proven safe): `VoteType` enum, `VotingSessionStatus` enum
(backend-only copy), 3 zero-throw-site exception classes
(`WorkflowLockedStateException`, `DuplicateVoteException`,
`VotingException`), and the dead `NotificationSeeder.php`. These are
distinct from the 2 persisted-enum-value items above, which specifically
require database/historical-data evidence before removal — a different
risk class, correctly not bundled together.

---

## 12. Application readiness decision

**Application-level go-live readiness: APPROVED.**

This audit verified application logic — authorization, workflow
correctness, API/UI reliability, state-model consistency, and regression
safety. It did **not** verify the production deployment environment or
infrastructure. Production go-live requires the separate checklist below,
verified against the actual target environment before deployment.

---

## 13. Production deployment checklist

Verify each item against the actual production environment/infrastructure
— none of these were checked by this audit, which ran against local
dev/MySQL:

1. [ ] `APP_ENV=production`
2. [ ] `APP_DEBUG=false`
3. [ ] `APP_DEMO_ROLE_SWITCH=false`
4. [ ] `NUXT_PUBLIC_VISUAL_BYPASS` unset or `false`
5. [ ] Production demo seeding hard-blocked (confirm `demo.seed_demo_data`/`demo.allowed_seed_environments` config excludes `production`)
6. [ ] Database backup and restore tested
7. [ ] Deployment rollback documented and tested
8. [ ] Queue workers and Horizon configuration verified
9. [ ] Redis persistence and failure behavior understood
10. [ ] Scheduler and cron execution verified
11. [ ] Storage and private-document permissions verified
12. [ ] Logging, alerting, and slow-query monitoring enabled
13. [ ] Production secrets and encryption keys managed securely
14. [ ] Rate limiting and trusted-proxy configuration verified
15. [ ] HTTPS, cookies, Sanctum domains, CORS, and session settings correct
16. [ ] Database migrations tested on a production-like snapshot
17. [ ] Health checks and post-deployment smoke tests prepared
18. [ ] Production workflow version and permissions confirmed (i.e., V2 is PUBLISHED, V1 ARCHIVED, in the production DB — not just this dev DB)
19. [ ] System-admin recovery path tested
20. [ ] No local or synthetic data included unintentionally (the 48 synthetic V2-recreated requests and 8 terminal V1 requests in this dev DB must NOT ship to production)

---

## 14. Rollback and incident-response checklist

- [ ] Deployment rollback procedure documented and rehearsed (item 7 above — cross-referenced, not duplicated verification)
- [ ] Database rollback/point-in-time-restore procedure tested independent of application rollback
- [ ] Workflow version rollback path understood: if V2 has a defect discovered post-launch, is re-archiving V2 and re-publishing V1 (or a corrected V3) a safe, tested operation? Not exercised by this audit — the Designer's clone→correct→publish→archive lifecycle was proven safe for _forward_ moves (`PublishImportFinancingV2CommandTest`), not for an emergency reversion.
- [ ] Incident escalation path defined for: authorization bypass discovered in production, workflow transition stuck/corrupted mid-flight, claim-lock contention beyond tested concurrency levels (audit proved correctness at 5–20 concurrent workers, not production-scale load)
- [ ] Audit-log/`workflow_history` integrity check procedure exists for post-incident forensics
- [ ] Contact/ownership for each of the 3 externally-facing systems (backend API, frontend Nuxt app, MySQL/Redis infra) documented for on-call response

---

## Appendix — full document trail

| Phase   | Checkpoint doc                                                  |
| ------- | --------------------------------------------------------------- |
| A–C     | `docs/audit-functional/10-implementation-plan.md`, `11`–`13`    |
| D       | `15-phase-d0-checkpoint.md`, `16-phase-d-step1-inventory.md`    |
| E       | `17-phase-e-traceability-matrix.md`, `18-phase-e-checkpoint.md` |
| F       | `19-phase-f-inventory.md`, `20-phase-f-checkpoint.md`           |
| Closure | this document                                                   |

**Audit closed. All 17 findings fixed and verified. 2 items deferred cleanup, explicitly gated. Application-level go-live: approved, subject to the production deployment checklist above.**
