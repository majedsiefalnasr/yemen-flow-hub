# Functional / RBAC / Workflow Audit — Consolidated Report (Phase 9)

**Status:** Audit only. No application behavior has been changed. This report
consolidates Phases 1–6 (`00`–`03` checkpoints) into the deliverables required
before any implementation is approved.

**Evidence date:** 2026-07-11 · **Baseline:** `main` post-`6fb84010`

**Scope:** role & permission correctness, workflow designer→runtime fidelity,
UI functional and UX behavior. Distinct from the closed performance audit in
`docs/audit/`. Central question: _does every user see and do exactly what they
are allowed — no more, no less?_

**Method:** static code review (evidence as `file:line`), PHPUnit probe suites
(`backend/tests/Feature/Audit/`), and live browser verification against the
local MySQL stack (Nuxt :3000, Laravel :8000, MySQL :3306, Redis :6379) via
`playwright-cli`. Each finding is labeled with its verification method.

**Authority correction (post-M1):** the metadata-driven Workflow Designer + Engine
are the source of truth. `docs/user-view/` is **deprecated** and not an
authoritative contract; `dynamic-workflow-engine/` is a reference implementation
only; **Executive Voting is out of V1 scope.** The approved canonical V1 workflow
contract is recorded in [`05-m1-workflow-contract.md`](./05-m1-workflow-contract.md).
Findings framed against `docs/user-view/` below are superseded by that contract
where they conflict — see the WF-002 re-scope note in §6.

---

## 1. Executive summary

Once a request is inside a valid organization scope, the core engine controls
are strong: bank isolation, hidden-field suppression, document policy, claims,
optimistic locking, audit history, notification audience scoping, and transition
validation all have direct automated evidence and passed (46 stage/field tests,
plus runtime, document, and governance suites).

The audit nonetheless found **one critical authorization boundary failure, one
critical workflow-configuration failure, and a cluster of high-severity
escalation, parity, and reliability defects.** (Post-M1: WF-002 was re-scoped from
Critical to High — see §6 note — leaving RBAC-004 and WF-003 as the two Criticals.)
The highest-priority authorization theme is that the system has **two disagreeing
sources of truth** for authorization — capability-based vs role-code-based
policies. Early discovery also found conflicting workflow descriptions across the
seeded configuration and deprecated documentation; **M1 resolved this conflict by
establishing the Workflow Designer metadata and runtime engine as authoritative,
with `docs/user-view/` classified as historical and deprecated.** On the workflow
side, the engine faithfully executes the designer; the defects are in the
**seeded designer configuration** (missing SWIFT document rules, FINAL ownership,
validator-bypassing publish, unpopulated `semantic_role`), per the approved
contract in `05-m1-workflow-contract.md`.

| Dimension                                     | Health      | Basis                                                                                                       |
| --------------------------------------------- | ----------- | ----------------------------------------------------------------------------------------------------------- |
| Permission / authorization                    | **At risk** | 1 Critical bypass (RBAC-004); 3 High (RBAC-002 escalation, RBAC-001 retention, H6 env bypass) + RBAC-003    |
| Workflow-engine correctness                   | **Strong**  | Transition chain, locking, fields, claims all verified secure                                               |
| Workflow **configuration** (seeded canonical) | **At risk** | 1 Critical (WF-003) + 2 High (WF-002 re-scoped, WF-001): designer config incomplete vs approved V1 contract |
| UI functional                                 | **At risk** | 2 High (API-UI-001 reliability, STATUS-DRIFT-001 enum drift), 2 Medium blank-denial (UI-RBAC-001/002)       |
| UX                                            | **Fair**    | Denial states, queue/dashboard disagreement, misleading action labels                                       |
| Accessibility                                 | **Good**    | Focus rings, dialog focus-trap/Esc/return-focus all correct                                                 |

### Most urgent fixes (ranked; post-M1–M6)

1. **RBAC-004 (Critical)** — null-`bank_id` users are in-scope for every request; with matching stage metadata this reads and mutates cross-organization requests. Phase A1.
2. **WF-003 (Critical, OPEN)** — SWIFT stage (`FX`) advances with an empty payload because its field rules require no documents; the SWIFT package is not configured in the designer. Blocked on the M1 §5 SWIFT-field confirmation. Phase B3.
3. **RBAC-002 (High)** — admin-only screens are delegable via the write API, enabling role self-escalation to `workflow_designer`. Phase A2.
4. **RBAC-001 (High)** — inactive/demoted admin pivots retain `isSystemAdmin()` privilege across live APIs; active-identity fix. Phase A3.
5. **H6 (High, M2)** — demo-switch + visual-bypass flags gate on a boolean/build flag with no environment cross-check; env hard-stops required. Phase A4.
6. **STATUS-DRIFT-001 (High, M6)** — frontend 22-value status enum (1,119 refs / 42 files) can display incorrect workflow state; full reconciliation to the engine model. Phase D1.
7. **API-UI-001 (High)** — `engine-requests/stats` 500s on MySQL (`ONLY_FULL_GROUP_BY`); frontend retry loop floods the API and burns the rate-limit budget. Phase C1.

_(WF-002 re-scoped Critical→High post-M1 — see §6 note: only FINAL ownership → `committee_director` + reasoned rejects; Executive deciding at EXEC is accepted V1, no voting.)_

---

## 2. Current system behavior

**Authentication.** Sanctum SPA cookie auth; single active role per user
(`User::assertSingleActiveRole`, pivot `user_roles.is_active`). Login rate-limited
5/min/IP, lockout after 10 failures. MFA (authenticator app) is enforced on
first sign-in. A dev-only quick-user-switch control exists, gated by
`demo.*` config.

**Authorization** runs on four independent mechanisms (no route-level authz
middleware; everything is controller/service-level):

1. **Stage permissions** (`stage_permissions`; org/team/role/user columns, NULL = wildcard, AND within a row, OR across rows, EXECUTE ⊃ VIEW) resolved by `StagePermissionResolver`. Sole routing gate for engine view/queue/transition.
2. **Screen permissions** (`screens` + `screen_permissions`) via `PermissionService` with a 1h role-keyed cache; the `requests` capability is _derived_ from stage permissions on published versions.
3. **Data scope** (`DataScope`): NATIONAL_COMMITTEE → system-wide; BANKING_SECTOR → own `bank_id`; anything else → deny-all (`1=0`). Applied at query level.
4. **Role codes** (`RoleCodes`): hard `isSystemAdmin`/`hasRoleCode` checks in User/Bank policies, search, dashboards, claim override, FX, admin settings.

Mechanisms 2 and 4 **disagree on the source of truth** — designer-family
policies are capability-driven while governance policies are role-code-driven.
This split is the root of RBAC-001/002.

**Runtime workflow.** `EngineRequest` pins `workflow_version_id` at creation;
only PUBLISHED versions are creatable; existing requests keep their version after
later publishes. Transition execution (`EngineTransitionService::execute`) is a
single `DB::transaction` with `lockForUpdate`, active re-check, optimistic
`version` check, current-stage origin check, EXECUTE re-check, claim ownership,
required-comment check, field-rule validation (hidden present → reject; read-only
changed → reject; required empty → reject on exit; FILE needs linked document),
then `workflow_history` + `audit_logs`, stage hooks inside the txn, notifications
after commit. This chain is **verified strong**.

**UI consumes metadata** via `form-schema` (visible fields + rules), `graph`
(edges + `execute_stage_ids`), and `EngineRequestResource` (data filtered by
stage visibility, `can_execute` computed per user). Field visibility is
**stage-scoped, not viewer-scoped** (accepted V1). Frontend guards
(`auth.global`, `screen`, `role`) are UX-only by design; the backend re-checks.

---

## 3. Role and permission matrix

Roles (DB code → API enum): `intake`→DATA_ENTRY, `internal_reviewer`→BANK_REVIEWER,
`bank_admin`→BANK_ADMIN, `fx_swift`→SWIFT_OFFICER (Bank side);
`support`→SUPPORT_COMMITTEE, `committee_manager`→EXECUTIVE_MEMBER,
`committee_director`→COMMITTEE_DIRECTOR, `fx_confirm`→(EXECUTIVE_MEMBER),
`system_admin`→CBY_ADMIN (CBY side).

| Role                       | Primary screens (expected)          | Record scope | Verified result                                                                                        | Status                             |
| -------------------------- | ----------------------------------- | ------------ | ------------------------------------------------------------------------------------------------------ | ---------------------------------- |
| Data Entry                 | requests (V/C/U), merchants (V)     | own bank     | nav + `/auth/me` match; create wizard OK                                                               | ✅ (blocked by API-UI-001 on list) |
| Bank Reviewer              | requests (V/U)                      | own bank     | INTERNAL transitions exposed but **defective** (WF-002)                                                | ⚠️                                 |
| Bank Admin                 | reports, requests, users, merchants | own bank     | `/auth/me` ↔ nav ↔ `/admin/roles` 403 all consistent                                                   | ✅                                 |
| SWIFT Officer              | requests, notifications             | own bank     | queue bank-scoped; **WF-003** package bypass; TIIB-35 403 → **blank page (UI-RBAC-002)**               | ❌                                 |
| Support Committee          | requests (V/U), audit/report (V)    | system (CBY) | claim gate OK; transition set incomplete (WF-002)                                                      | ⚠️                                 |
| Executive Member           | requests (vote only, expected)      | system (CBY) | **acts as EXEC+FINAL decision-maker** (WF-002); stats page storm (API-UI-001)                          | ❌                                 |
| Committee Director         | reports, audit, FX-confirm queue    | system (CBY) | nav shows requests but **`/workflows` → 403** (RBAC-005); dashboard vs `/customs` disagree (UI-FX-001) | ❌                                 |
| CBY Admin                  | full admin                          | system       | full nav; designer loads; **but delegable/retained via RBAC-001/002**                                  | ⚠️                                 |
| null-bank / OTHER-org user | none (deny-all lists)               | deny-all     | **reads + transitions any request by ID (RBAC-004)**                                                   | ❌ Critical                        |

**Verified secure controls:** cross-bank request detail denied; hidden fields
absent from detail/list/form-schema; hidden field-linked documents not
downloadable; cross-bank document access denied; bank-admin user listing scoped
to own bank; audit-log own-bank scope; report own-bank vs NC system-wide;
notification recipient ownership; search bank scope + no-org deny; governance
lifecycle guards + optimistic versions + audit logging.

---

## 4. Workflow coverage matrix (seeded `IMPORT_FINANCING` vs contract)

| Stage            | Role (actual metadata)                | Actions (actual)                | Contract expectation                                                          | Gap                                                                                          |
| ---------------- | ------------------------------------- | ------------------------------- | ----------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| CREATE (initial) | intake                                | submit → INTERNAL               | Data Entry submits                                                            | OK                                                                                           |
| INTERNAL         | internal_reviewer                     | APPROVE→SUPPORT, REJECT→CREATE  | Approve / Return / **terminal Reject** as 3 distinct, reasoned decisions      | **REJECT is a mislabeled return; no terminal reject; `requires_comment=false`** (WF-002/001) |
| SUPPORT          | support (claim)                       | APPROVE→EXEC, ADD_NOTES→SUPPORT | Approve / Return to Data Entry / Reject to Reviewer, reasoned                 | **No return/reject path; self-loop unmarked (fails validator)** (WF-002/001)                 |
| FX (SWIFT)       | fx_swift                              | APPROVE→FX_CONFIRM              | Require SWIFT ref + SWIFT PDF + FX-request PDF before exit; no approve action | **Advances on empty payload; no package enforcement/route** (WF-003)                         |
| EXEC             | committee_manager (=EXECUTIVE_MEMBER) | APPROVE, REJECT                 | Each member casts **one vote**; cannot advance/reject directly                | **Member IS the decision-maker** (WF-002)                                                    |
| FX_CONFIRM       | banks VIEW, FX team EXECUTE           | (approve)                       | Director/FX-confirm completion                                                | mixed-audience, all fields read-only (accepted under stage-scoped V1)                        |
| FINAL            | committee_manager                     | APPROVE, REJECT                 | **Director** closes/finalizes; distinct from member                           | **Director unassigned; member finalizes** (WF-002)                                           |

Rejection transitions `INTERNAL→CREATE`, `EXEC→CLOSED_REJECTED`,
`FX_CONFIRM→FX`, `FINAL→FX_CONFIRM` all set `requires_comment=false` and lack
confirmation copy → **five validator errors** on the freshly seeded canonical
version (WF-001). The seeder inserts state `PUBLISHED` directly, bypassing the
publish gate the designer applies to user versions.

**Verified secure engine behavior (config-independent):** valid/ wrong-stage/
unavailable transitions, non-executor denial, required-comment enforcement,
terminal-state rejection, final outcomes (CLOSED/REJECTED/CANCELLED/ABANDONED),
claim lifecycle + TTL + non-holder denial, transaction rollback on hook failure,
history + audit creation, stale-version rejection.

**Open gap:** a true parallel MySQL transition race is untested (optimistic
stale-version tests are the current proxy). Defer to the load phase with the
existing acceptance criterion (20–50 concurrent callers, exactly one success).

---

## 5. Page & feature inventory (functional status)

34 frontend pages. Guards: `auth.global` (login + forced password change),
`screen.ts` (client-side capability check), `role.ts` (mapped role),
`00.visual-bypass.global.ts` (build-flag CBY_ADMIN fabrication — must be off in
prod). Backend API: ~209 routes under `/api`, all authed routes wrapped in
`auth:sanctum` + `active` + `throttle:api-default`.

| Page family                            | Roles             | Functional status | Issues                                     |
| -------------------------------------- | ----------------- | ----------------- | ------------------------------------------ |
| `/login`, `/reset-password`            | public            | OK                | MFA setup blocks fresh accounts (expected) |
| `/`, `/dashboard`                      | all (role-mapped) | OK                | focus/dialog a11y verified good            |
| `/workflows` (list+queue)              | requests-capable  | **Degraded**      | API-UI-001 stats 500 → request storm → 429 |
| `/workflows/instances/[id]`            | requests-capable  | **Degraded**      | UI-RBAC-002 blank denial on 403/404/429    |
| `/workflows/new`                       | Data Entry        | OK                | wizard + chooser render                    |
| `/customs` (FX-confirm)                | Director          | **Inconsistent**  | UI-FX-001 vs dashboard count               |
| `/admin/workflows`                     | designer          | **Blank denial**  | UI-RBAC-001 no `definePageMeta`            |
| `/admin/{roles,orgs,banks,...}`        | admin/screen      | OK                | redirect to explicit 403                   |
| `/reports`, `/audit`, `/notifications` | role-mapped       | OK (scoped)       | —                                          |
| `/staff`, `/bank/users`                | BANK_ADMIN        | OK                | own-bank scoped                            |

**Accessibility (this phase):** focus rings visible on all focusable elements;
command-palette dialog moves focus inside, traps it, closes on Esc and returns
focus to trigger; stat tiles are `role=button tabindex=0`. No a11y defects found
on stable surfaces; the blank-denial pages (UI-RBAC-001/002) are the a11y/UX
concern because they present no landmark heading or recovery action.

---

## 6. Findings table

| ID                               | Sev          | Category                            | Feature / Role                             | Current behavior                                                                                                | Expected                                                                                                                                                             | Evidence                                                                                                                                            | Verification               |
| -------------------------------- | ------------ | ----------------------------------- | ------------------------------------------ | --------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------- |
| **RBAC-004**                     | **Critical** | Authz / IDOR / isolation            | engine-requests / null-bank OTHER-org user | `inScope()` true for any null-`bank_id`; reads + transitions any request                                        | policy enforces same data-scope classification as lists                                                                                                              | `EngineRequestPolicy.php:67-74` vs `DataScope.php:32-36`; probes `test_cf3_*` (detail/subresources/transition all 200)                              | PHPUnit + static           |
| **WF-002** _(re-scoped post-M1)_ | **High**     | Workflow integrity / RBAC / audit   | FINAL stage ownership; reasoned rejects    | `committee_manager` (Executive) owns **both** EXEC and FINAL EXECUTE; rejects lack comments                     | Executive owns EXEC (accepted V1); **`committee_director` owns FINAL**; reasoned rejects                                                                             | `ImportFinancingWorkflowSeeder.php`; live DB `EXEC`/`FINAL` bound team=6/role=6; 6 FINAL `can_execute:true` for member                              | Static + browser + PHPUnit |
| **WF-003**                       | **Critical** | Workflow integrity / evidence       | SWIFT Officer @ FX                         | Transition accepts empty `data`, no package; generic Approve shown                                              | require SWIFT ref + 2 PDFs before exit; no approve action                                                                                                            | seeder `:267-289,324-337`; `EngineSwiftUploadTest:191-207` posts `data:[]` expects 200; `SwiftUploadForm.vue` has no caller                         | Static + browser           |
| **RBAC-002**                     | High         | Priv. escalation                    | screen-permissions write API               | All 8 `ADMIN_ONLY_SCREENS` accepted; chains to self-grant `workflow_designer`                                   | reject admin-only + universal keys server-side                                                                                                                       | `RoleScreenPermissionController.php:71-80,163-177`; `test_cf2_*` (200 then designer 200)                                                            | PHPUnit                    |
| **RBAC-001** _(M3 locked)_       | High         | Priv. retention                     | `isSystemAdmin`/`hasRoleCode` everywhere   | Inactive/demoted admin pivots still grant admin; reached `/admin/settings`, audit, list bypass                  | active-pivot + active-role only; fix 3 helpers centrally — all 58 call sites route through them, no direct historical query; see `07-m3-active-identity-contract.md` | PHPUnit                                                                                                                                             |
| **WF-001**                       | High         | Config / seeder bypass              | canonical published version                | Seeded PUBLISHED bypasses publish gate; validator returns 5 errors; rejects lack comments                       | canonical must pass its own validator; reasoned rejects                                                                                                              | `Phase3WorkflowConfigurationProbeTest`; validator run on fresh seed                                                                                 | PHPUnit                    |
| **RBAC-005**                     | High         | FE/BE parity                        | Director nav                               | Sidebar shows `طلبات التمويل` (count 6) → `/workflows` redirects `/forbidden`                                   | nav derived from actual capability map                                                                                                                               | `/auth/me` (no `requests`) vs rendered sidebar                                                                                                      | Browser                    |
| **UI-FX-001**                    | High         | Functional consistency              | Director dashboard vs `/customs`           | Dashboard 6 ready; `/customs` 0; same session                                                                   | both read the same queue contract                                                                                                                                    | `customs/index.vue:37-42`, `useEngineRequests.ts:58-69`, `DashboardStatsService.php:378-404`                                                        | Browser + static           |
| **API-UI-001**                   | High         | Query / error handling / rate limit | `/workflows` all roles                     | `stats` 500 (MySQL `ONLY_FULL_GROUP_BY`) → retry storm (480+ req) → 429 masks root error                        | valid GROUP BY; single-flight load; stable failure boundary                                                                                                          | live: Data Entry 105 console errors, stats 500×N, then `engine-requests/1` 429; `EngineRequestStatsService`                                         | Browser + static           |
| **UI-RBAC-001**                  | Medium       | Denial UX                           | `/admin/workflows` (Support)               | No `definePageMeta`; mounts, fires forbidden fetch, blank shell                                                 | `screen` middleware redirects to `/forbidden` pre-fetch                                                                                                              | `pages/admin/workflows.vue`; browser 403 + blank snapshot                                                                                           | Browser                    |
| **UI-RBAC-002**                  | Medium       | Denial UX                           | `/workflows/instances/[id]`                | 403/404/429 → blank main, raw console errors, no recovery                                                       | role-appropriate denial/error state, safe navigation                                                                                                                 | `loadInstance()` uncaught; live TIIB-35 403 + instance-1 429 blank                                                                                  | Browser                    |
| **RBAC-003**                     | Medium       | FE parity (fail-open display)       | `/auth/me`                                 | Derives `requests` caps from inactive roles/teams                                                               | same active identity as runtime resolver                                                                                                                             | `PermissionService.php:292-297` vs resolver `:176-184`; `test_cf4_*`                                                                                | PHPUnit                    |
| **H6** _(M2)_                    | High         | Auth bypass / env hardening         | demo switch + visual bypass flags          | Both bypass flags gate on a boolean/build flag only, no environment cross-check                                 | fail closed in production even if flag true; see `06-m2-environment-gates.md`                                                                                        | `AuthController.php:325,360,419`; `00.visual-bypass.global.ts:19`; `config/demo.php`                                                                | Static                     |
| **CF-5**                         | Low          | Consistency                         | immutable version error code               | 403 (global map) vs 409 (controller)                                                                            | one code; AGENTS.md says 409                                                                                                                                         | `bootstrap/app.php` vs `WorkflowVersionController.php:66`                                                                                           | Static                     |
| **CF-6 / F-DOC-1**               | Medium       | Governance / docs drift             | AGENTS.md enums                            | 22-status/8-role legacy enums ≠ 5-status/9-role engine                                                          | reconcile docs with dynamic engine                                                                                                                                   | `AGENTS.md`, `RoleCodes.php`; no `EngineRequestStatus` enum exists                                                                                  | Static                     |
| **STATUS-DRIFT-001** _(M6)_      | High         | Maintainability / correctness       | Frontend workflow-status model             | 22-value `RequestStatus` enum (voting/customs legacy) drives labels/timelines/buckets — can display wrong state | canonical `runtime_status` + `current_stage` + `final_outcome`; derive from API metadata; no parallel FE enum                                                        | `types/enums.ts` (**1,119 refs / 42 files**); `EngineRequestResource:34-77` missing `semantic_role`/`final_outcome`; `09-m6-enum-reconciliation.md` | Static                     |

**WF-002 re-scope note (post-M1).** The M1 designer-first review, built on
`docs/user-view/`, framed WF-002 as "Executive Members wrongly decide instead of
voting." That framing is **withdrawn**: Executive Voting is out of V1 scope, and
Executive Committee deciding directly at `EXEC` is **accepted V1 behavior**. WF-002
is downgraded **Critical → High** and narrowed to two real config defects: (1)
`FINAL` EXECUTE must move from `committee_manager` to `committee_director` (the
two-step decision model in `05-m1-workflow-contract.md`); (2) consequential reject
transitions need `requires_comment=true` + confirmation copy (overlaps WF-001).
WF-003 remains **Critical** and **OPEN** pending the SWIFT-field approval in
`05-m1-workflow-contract.md §5`.

---

## 7. UX improvement plan

**Critical usability blockers**

- **Blank denial pages (UI-RBAC-001/002).** A denied/failed request shows an empty shell — indistinguishable from a broken page; drives reload storms and support escalation. Add a shared denial/error component (landmark heading, reason, safe navigation) and route-level `screen` guards that redirect before the fetch.

**High-impact workflow improvements**

- **Misleading action labels (WF-002).** The Reviewer "رفض/Reject" moves to CREATE (a return) yet records as rejection — misleading operators and corrupting audit narrative. Fix by modeling distinct reasoned Return/Reject stages, then label from metadata.
- **Director queue disagreement (UI-FX-001).** Dashboard promises 6 ready items; `/customs` shows 0. Unify on one queue contract so the count and the actionable list match.
- **Director dead-end nav (RBAC-005).** Offer a link only when the capability exists.

**Form improvements**

- **SWIFT package form (WF-003).** Surface the existing `SwiftUploadForm.vue` behind a real route; keep the transition control disabled with a specific reason until ref + both PDFs are present.

**Feedback & error-state improvements**

- **Request-storm masking (API-UI-001).** Single-flight the queue+stats loads, cancel in-flight on filter change, and keep the surfaced error at the true 500 rather than degrading to 429.

**Accessibility improvements**

- None blocking. Replicate the command-palette dialog pattern (focus-trap/Esc/return-focus) as the standard for all future dialogs; ensure blank-denial replacements expose a landmark heading.

**Optional visual refinements** — none recommended without a usability reason.

---

## 8. Test coverage plan (missing / recommended)

| Area                                 | Level                        | New coverage                                                                                                                                                                                                    |
| ------------------------------------ | ---------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RBAC-004                             | Feature                      | Org-classification matrix (NC / own-bank / other-bank / OTHER / null / admin) × {show, form-schema, history, graph, documents, draft, claim, actions} → expected 200/403/404                                    |
| RBAC-002                             | Feature                      | One negative case per `ADMIN_ONLY_SCREENS` key + explicit self-escalation chain                                                                                                                                 |
| RBAC-001                             | Feature                      | Every privileged helper × {active pivot, inactive pivot, inactive role record, no active role, reassigned, loaded/unloaded relation}; API coverage for settings, audit, list/detail, search, claim override, FX |
| WF-002/003/001                       | Feature + Browser            | Approved V1 workflow-coverage manifest (actor, stage, action meaning, target, reason, terminality, claim/vote); canonical seed must pass validator empty; SWIFT package blocked until complete                  |
| API-UI-001                           | Integration (MySQL) + Vitest | Stats aggregation under `ONLY_FULL_GROUP_BY` per role branch; one failed load → one request batch, stable retry, no auto-loop                                                                                   |
| UI-RBAC-001/002, RBAC-005, UI-FX-001 | Browser + Vitest             | Direct-URL 403/404/500/offline → denial state + one attempt; per-role `/auth/me` ↔ rendered sidebar; dashboard count == queue total                                                                             |
| Concurrency                          | Load/Integration             | Real parallel MySQL transition race: exactly one success, rest stale                                                                                                                                            |

Existing evidence to preserve: 46 stage/field tests, engine runtime suites,
document/governance suites, 178 frontend role-surface tests, 15 screen-permission
tests. The audit probe suites (`Phase2RbacProbeTest`,
`Phase3WorkflowConfigurationProbeTest`) intentionally assert **secure**
expectations and therefore fail today — they become the regression gate once
fixes land.

---

## 9. Implementation roadmap (pending approval — do not start yet)

The M1–M6 decisions are locked, so the roadmap is now a **detailed, per-item
implementation plan** in [`10-implementation-plan.md`](./10-implementation-plan.md)
— each item carries dependencies, affected roles, affected workflow versions,
tests, migration/data-fix needs, rollback strategy, and acceptance criteria.
Summary of phases (post-M1 re-scope applied):

- **Phase A — Security boundary (pre-production, blocking go-live).** A1 RBAC-004 (policy org-scope — **first**), A2 RBAC-002, A3 RBAC-001+RBAC-003 (active-identity), A4 H6/M2 env hard-stops.
- **Phase B — Workflow correctness (new V2).** B1 WF-001 (validator-clean seed), B2 WF-002 re-scoped (FINAL→`committee_director`; **no voting**), B3 WF-003 SWIFT gate _(blocked on M1 §5)_, B4 `semantic_role` population _(blocked on M1 §6)_.
- **Phase C — API/UI reliability.** C1 API-UI-001 (MySQL stats + single-flight), C2 UI-RBAC-001/002 (denial states), C3 UI-FX-001 + RBAC-005 (Director queue/nav).
- **Phase D — Docs/UX + M6 reconciliation.** D1 STATUS-DRIFT-001 (full enum reconciliation + API-contract additions), D2 AGENTS.md, D3 UX (metadata labels, denial component, dialog pattern).
- **Phase E — Regression.** Land Phase-8 suites; flip probe suites green; concurrency race.
- **Phase F — Final verification + gated legacy cleanup.**

**Sequencing rule:** A → B → C → D → E → F. RBAC-004 first (active exploitable
cross-org bypass). Every fix ships with tests written before/alongside it, a
migration+rollback plan for any schema/data change, and preserved audit/transaction
guarantees. No backend authorization weakened for UX; no designer-owned behavior
hard-coded; no legacy removed without dependency evidence.

---

## 10. Missing information required before implementation

Blocking-decision tracker (M1–M6 per the approved decision sequence):

| #     | Decision                                                   | Status                                                                                                                                                                                                                     |
| ----- | ---------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| M1    | Canonical V1 workflow contract (designer-first, no voting) | **Approved** subject to SWIFT field confirmation + semantic-role value validation (`05-m1-workflow-contract.md §5,§6`)                                                                                                     |
| M2 ✅ | Deployment environment gates / auth-bypass hard-stops      | **RESOLVED** — Option A: env hard-stops mandatory; H6 = High (`06-m2-environment-gates.md`)                                                                                                                                |
| M3 ✅ | Demoted-admin / inactive-pivot authorization contract      | **RESOLVED** — Option A: active-pivot + active-role only; helper-level fix (`07-m3-active-identity-contract.md`)                                                                                                           |
| M4 ✅ | Stage-level vs per-action EXECUTE granularity              | **RESOLVED** — Option A: stage-level accepted V1; split-actor scan found no violation (`08-m4-m5-granularity-visibility.md`)                                                                                               |
| M5 ✅ | Field visibility: stage-scoped vs per-viewer               | **RESOLVED** — Option A: stage-scoped accepted V1; hidden-write + read-only enforcement verified server-side; `FX_CONFIRM` mixed audience accepted, no bank-hidden field (`08-m4-m5-granularity-visibility.md`)            |
| M6 ✅ | Docs/enum reconciliation + legacy disposition              | **RESOLVED** — Option B: full enum/presentation reconciliation; new High finding STATUS-DRIFT-001 (1,119 refs/42 files); canonical `runtime_status`/`current_stage`/`final_outcome` model (`09-m6-enum-reconciliation.md`) |

Deferred operational approvals (not blocking-decisions, tracked separately): (a)
permission to inventory/clean stale local data (`DBGWF` invalid published
workflow); (b) approval to create throwaway audit fixtures / run the
parallel-transition load test; (c) the 48-request reset/recreate under V2 per
M1 §9.

---

## Appendix — verification evidence

- Probe suites: `backend/tests/Feature/Audit/Phase2RbacProbeTest.php`, `Phase3WorkflowConfigurationProbeTest.php` (16 secure-expectation failures reproduce RBAC-001–004, WF-001 — intentional, not a green suite). Control: `ScreenPermissionTest` 15 tests / 52 assertions pass.
- Browser (this phase): Data Entry `/workflows` reproduced API-UI-001 (105 console errors, `stats` 500×N, then `engine-requests/1` 429) and UI-RBAC-002 blank detail. Command-palette dialog verified for focus-trap, Esc, return-focus; all focusable elements show visible focus rings.
- Prior browser evidence for RBAC-004/005, WF-002/003, UI-FX-001, UI-RBAC-001/002 recorded in `02-security-workflow-runtime.md` and `03-role-workflow-ui.md`.
- Audit PHP files pass focused Pint; all five audit Markdown files pass Prettier.
