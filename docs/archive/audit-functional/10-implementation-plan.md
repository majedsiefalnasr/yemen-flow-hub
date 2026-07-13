# Implementation Plan (Phases A–F) — Pending Approval

**Status:** Plan only. **Do not begin implementation until reviewed and approved.**
Derived from the M1–M6 locked decisions (`05`–`09`) and the findings table in
`04-final-report.md`. Evidence date: 2026-07-11.

**Global rules (apply to every item):** audit-first is complete; fixes proceed one
approved item (or tightly-related group) at a time; tests written before/alongside
each fix; the audit probe suites (`Phase2RbacProbeTest`,
`Phase3WorkflowConfigurationProbeTest`) flip from failing to passing as the gate;
no backend authorization weakened for UX; no designer-owned behavior hard-coded;
audit + transaction guarantees preserved; migration + rollback plan for every
schema/data change; re-test all affected roles and workflow paths after each
change.

**Sequencing:** A → B → C → D → E → F. **RBAC-004 is item A1 — the only live
exploitable cross-org bypass.**

---

## Phase A — Security boundary & data isolation (pre-production, blocking go-live)

### A1 — RBAC-004: cross-org request read/execute bypass (Critical)

- **Depends on:** none (first).
- **Roles affected:** null-`bank_id` / OTHER-classification users; all engine-request consumers.
- **Workflow versions:** all (policy-level, version-independent).
- **Change:** `EngineRequestPolicy::inScope()` must apply the same organization-classification data scope as `DataScope` (NATIONAL_COMMITTEE → system-wide; BANKING_SECTOR → own bank; else deny). Stage EXECUTE must not be treated as sufficient without the org scope gate first.
- **Tests (write first):** org-classification matrix (NC / own-bank / other-bank / OTHER / null / system-admin) × {show, form-schema, history, graph, documents, draft, claim, actions} → expected 200/403/404. Flip `Phase2RbacProbeTest::test_cf3_*` to passing.
- **Migration/data:** none.
- **Rollback:** revert the policy method; no data touched.
- **Acceptance:** OTHER/null-bank user gets 403/404 on every request subresource and transition; NC and own-bank scopes unchanged; probe suite green for CF-3.

### A2 — RBAC-002: admin-only screen delegation / self-escalation (High)

- **Depends on:** none.
- **Roles affected:** any role granted screen permissions; system admin.
- **Change:** `RoleScreenPermissionController::update()` must reject `ADMIN_ONLY_SCREENS` and universal keys server-side (not just omit from the matrix UI); enforce the target-role/caller contract server-side.
- **Tests (write first):** one negative case per `ADMIN_ONLY_SCREENS` key + explicit self-escalation chain (grant `screen_permissions:MANAGE` → self-grant `workflow_designer`). Flip `test_cf2_*`.
- **Migration/data:** audit existing `screen_permissions` rows for already-granted admin-only keys; report before removal (no silent deletion).
- **Rollback:** revert validation; existing grants unchanged.
- **Acceptance:** every admin-only key rejected with a clear error; self-escalation chain blocked; matrix UI unchanged.

### A3 — RBAC-001 + RBAC-003: active-identity authorization (High)

- **Depends on:** none. **Contract:** `07-m3-active-identity-contract.md`.
- **Roles affected:** every privileged check (system admin power, audit, claim override, FX, dashboards, `/auth/me` nav).
- **Change:** `isSystemAdmin()` (both branches), `hasRoleCode()`/`hasAnyRoleCode()` require active pivot + active role; remove historical fallback. Align `PermissionService` capability derivation (`/auth/me`) with the same active identity (closes RBAC-003).
- **Tests (write first):** the 10-case matrix in `07-m3 §5` (active/inactive pivot, inactive role record, no-active-role, reassigned, loaded/unloaded, `/auth/me`, direct-API surfaces, history-preserved, reassignment-grants-new-only).
- **Migration/data:** none. **Do not delete historical pivot rows.** Re-grep post-fix to confirm no path bypasses the helpers (verified today: all 58 call sites route through them).
- **Rollback:** revert helper changes; pivots untouched.
- **Acceptance:** demoted admin denied on settings/audit/list/search/claim/FX immediately; `/auth/me` shows only active-role caps; history intact.

### A4 — H6 / M2: environment auth-bypass hard-stops (High)

- **Depends on:** none. **Contract:** `06-m2-environment-gates.md`.
- **Roles affected:** all (auth bypass surface).
- **Change (A-ENV-1 backend):** centralize a guard requiring demo flag **AND** approved environment; production fails closed; reject before listing/switching. Applied to all three demo endpoints (`AuthController.php:325,360,419`). **(A-ENV-2 frontend):** fail the production build/startup when `NUXT_PUBLIC_VISUAL_BYPASS` is set; middleware must not fabricate a user in production; dev-only warning when active in an allowed env. **(A-ENV-3 seeding):** production permanently excluded; staging requires explicit intent; demo data identifiable; reset procedure for non-prod.
- **Tests (write first):** the 7 regression cases in `06-m2 §4` (incl. "production + flag true → still denied" and "production build + visual bypass true → build fails").
- **Migration/data:** none (config + guard code). Deployment-verification checklist (unverified prod values) recorded for ops.
- **Rollback:** revert guard/build check; behavior returns to flag-only (not recommended).
- **Acceptance:** all 7 cases pass; backend rejects unauthenticated API calls even with visual bypass active locally.

**Phase A exit:** RBAC-004/002/001/003 + H6 fixed; probe suites green for those; no cross-org access, no self-escalation, no privilege retention, no environment bypass. **Go-live blocker cleared.**

---

## Phase B — Workflow correctness (designer configuration; new V2)

**Whole-phase note:** delivered as a **new published workflow version (V2)** through
the publish gate. The 48 active V1 requests are handled per M1 §9 (synthetic →
reset/recreate under V2; terminal V1 preserved). No engine code changes the
transition semantics — these are designer-config + seeder corrections.

### B1 — WF-001: canonical seed passes its own validator (High)

- **Depends on:** B-phase V2 scaffold.
- **Change:** route the canonical seed through the publish gate (or assert validator-empty in a test); set `requires_comment=true` + `confirmation_message` on the four reject transitions (`INTERNAL→CREATE`, `EXEC→CLOSED_REJECTED`, `FX_CONFIRM→FX`, `FINAL→FX_CONFIRM`); mark `SUPPORT→SUPPORT` `is_self_loop=true`.
- **Tests:** canonical-seed test asserts `WorkflowVersionValidator` returns zero errors; reject transitions require comments.
- **Migration/data:** V2 seed; V1 untouched.
- **Rollback:** V2 unpublished/archived; V1 remains.
- **Acceptance:** fresh seed validates clean; rejects demand reasons.

### B2 — WF-002 (re-scoped): FINAL ownership → committee_director (High)

- **Depends on:** B1. **Contract:** `05-m1 §2`.
- **Roles affected:** `committee_manager` (Executive), `committee_director` (Director).
- **Change:** in V2, `FINAL` EXECUTE bound to role `committee_director` (org2); `EXEC` keeps `committee_manager`. Document EXEC vs FINAL purpose in designer metadata (`05-m1 §3`).
- **Tests:** Director can execute FINAL; Executive Member cannot execute FINAL; Executive can still execute EXEC; segregation asserted.
- **Migration/data:** V2 stage_permissions; report any V1 request at FINAL (6 today) for reset/recreate.
- **Rollback:** revert V2 permission binding.
- **Acceptance:** two-step model holds; member ≠ finalizer.

### B3 — WF-003: SWIFT document gate at FX (Critical — OPEN pending SWIFT-field approval)

- **Depends on:** B1; **BLOCKED on M1 §5 SWIFT-field confirmation** (which fields; is `fxRequestDocument` mandatory).
- **Roles affected:** SWIFT Officer (`fx_ops`).
- **Change:** add the approved SWIFT fields to the V2 field set; set them `is_required=true` at `FX` in `stage_field_rules`; wire the existing orphaned `SwiftUploadForm.vue` + `UploadSwiftRequest.php` behind a real route; keep the transition control disabled until the package is complete.
- **Tests:** transition rejected until required SWIFT package present; PDF-only + size enforced; wrong bank/role/duplicate/stale rejected; browser test proves disabled control with reason.
- **Migration/data:** V2 field defs + rules; 6 V1 requests at FX reset/recreate.
- **Rollback:** revert field rules; FX reverts to no-gate (not acceptable long-term).
- **Acceptance:** empty-payload FX transition blocked; complete package advances; WF-003 probe green.

### B4 — semantic_role population (designer-driven bucketing)

- **Depends on:** B1; **requires M1 §6 EXEC-semantic decision** (add `EXECUTIVE_REVIEW` enum case vs reuse vs NULL).
- **Change:** populate `semantic_role` on all V2 stages per the final map (`05-m1 §6`); if approved, add the `EXECUTIVE_REVIEW` enum case.
- **Tests:** each stage resolves its semantic bucket; read-model/dashboard use semantic_role not literal codes.
- **Migration/data:** V2 stage metadata; V1 stages remain semantic-NULL (handled by B-phase compatibility adapter, see Phase D).
- **Rollback:** clear semantic_role; literal-code fallback resumes.
- **Acceptance:** queues/dashboards bucket by semantic_role for V2.

**Phase B exit:** V2 published + validator-clean; SWIFT gated; FINAL owned by Director; semantic metadata populated; 48 V1 synthetic requests reset/recreated under V2.

---

## Phase C — API & UI reliability

### C1 — API-UI-001: stats 500 + request storm (High)

- **Depends on:** none.
- **Roles affected:** all `/workflows` users (reproduced live on Data Entry + Executive).
- **Change (backend):** fix `EngineRequestStatsService` GROUP BY for MySQL `ONLY_FULL_GROUP_BY`. **(frontend):** single-flight the queue+stats loads; cancel in-flight on filter change; stable failure boundary; surface the real 500, not a degraded 429.
- **Tests:** MySQL integration test for stats aggregation per role/data-scope branch; frontend test — one failed load = one request batch + stable retry, no auto-loop.
- **Migration/data:** none.
- **Rollback:** revert query + loader changes.
- **Acceptance:** stats return 200 on MySQL for every role; no request storm; error surfaces the true cause.

### C2 — UI-RBAC-001/002: blank denial pages (Medium)

- **Change:** add `definePageMeta` + `screen` middleware to `/admin/workflows` (redirect to `/forbidden` pre-fetch); add a shared denial/error state (landmark heading, reason, safe navigation) to `/workflows/instances/[id]` and catch `loadInstance()` errors (403/404/500/offline).
- **Tests:** browser — direct-URL 403/404/500/offline → denial state + one attempt + safe nav.
- **Acceptance:** no blank shells; one request attempt; recovery action present.

### C3 — UI-FX-001 + RBAC-005: Director queue/nav consistency (High)

- **Depends on:** B2 (Director owns FINAL) — a Director stage assignment fixes the missing `requests` capability.
- **Change:** unify the Director dashboard count and `/customs` on one queue contract; derive nav from the actual capability map so `طلبات التمويل` shows only when usable.
- **Tests:** dashboard ready-count == dedicated queue total for the seeded Director; per-role `/auth/me` ↔ rendered sidebar.
- **Acceptance:** counts match; no dead-end nav.

---

## Phase D — Docs, UX, and M6 enum reconciliation

### D1 — STATUS-DRIFT-001 + CF-6: full enum/presentation reconciliation (High + Medium)

- **Depends on:** M6 contract (`09-m6`); **API-contract prerequisites** (below). Largest single workstream — **1,119 `RequestStatus` refs across 42 frontend files.**
- **API-contract tasks (do first):** `EngineRequestResource` must return `runtime_status`, `current_stage.semantic_role`, and request-level `final_outcome` (currently missing — `:34-77`). Version the contract; add resource tests.
- **Change (10-step roadmap, `09-m6 §9`):** inventory all legacy status deps and classify each (runtime status / current stage / semantic role / final outcome / display-only); introduce focused frontend types + mapping functions (not another oversized enum); derive stage presentation from API metadata (no hard-coded stage list); rebuild dashboards/timelines around runtime_status + stage + semantic_role + history + final_outcome; replace legacy tests/fixtures; verify old pinned V1 requests render; then remove the 22-value `RequestStatus` enum + dead voting/customs presentation code.
- **Backward compatibility:** isolated, clearly-temporary adapter for V1 requests lacking `semantic_role`; never used for new versions; measurable removal criteria; removed once legacy actives/versions resolved.
- **Tests (before removal):** regression coverage for the new model per component; historical-request render tests; adapter tests.
- **Migration/data:** no DB change to requests; V1 requests handled via adapter until reset/recreated (Phase B).
- **Rollback:** per migration slice — each component migrated behind its own change with the old mapping retained until its replacement test is green.
- **Acceptance:** no `RequestStatus` enum; frontend derives state from API; historical requests display correctly; dashboards/timelines use real state model.

### D2 — AGENTS.md + docs reconciliation (CF-6)

- **Change:** update AGENTS.md to five runtime statuses, designer-defined stages, final outcomes as a separate concept, real DB role codes, "Voting not in V1," "COMMITTEE_DIRECTOR does not auto-inherit EXECUTIVE_MEMBER unless configured," the source-of-truth hierarchy, and "frontend must not define an independent canonical status enum." Mark `docs/user-view/` deprecated (header banner). Keep `dynamic-workflow-engine/` as reference.
- **Acceptance:** AGENTS.md matches the live engine + M1 contract; `docs/user-view` banner present; no contributor reads voting/22-status as canonical.

### D3 — UX improvements (from `04-final-report §7`)

- Metadata-driven action labels (replaces mislabeled reject/return); shared denial-state component (overlaps C2); standardize the command-palette dialog pattern (focus-trap/Esc/return-focus) as the dialog reference; ensure blank-denial replacements expose landmark headings.
- **Acceptance:** action labels come from metadata; denial states accessible; dialogs consistent.

---

## Phase E — Automated regression coverage

- Land the Phase-8 test suites (`04-final-report §8`): RBAC org matrix, admin-only-screen negatives, active-identity matrix, workflow-coverage manifest, MySQL stats integration, denial-state browser tests, enum-reconciliation regression, adapter tests.
- Flip the audit probe suites (`Phase2RbacProbeTest`, `Phase3WorkflowConfigurationProbeTest`) to **green** as the standing gate.
- Real parallel MySQL transition race (deferred concurrency gap): 20–50 concurrent callers, exactly one success, rest stale — **requires M6-deferred approval** for throwaway fixtures.
- **Acceptance:** probe suites green; new suites cover every fixed finding; concurrency test passes or is explicitly deferred with criteria.

---

## Phase F — Final verification

- Re-run all role/workflow paths (Data Entry, Bank Reviewer, SWIFT, Support, Executive, Director, Bank Admin, CBY Admin) against V2.
- Full backend (`php artisan test`) + frontend (`pnpm test`) suites for release sign-off; report against the known-red baseline.
- Deployment-verification checklist (M2 §2) executed against prod/staging.
- Legacy-cleanup task (voting stack removal + `docs/user-view` archival) gated on the removal criteria in `09-m6 §5` — separate approval.
- **Acceptance:** every fixed finding verified end-to-end; no regression; go-live checklist complete.

---

## Cross-cutting dependencies & open blockers

| Item                     | Blocked on                                                                                                     |
| ------------------------ | -------------------------------------------------------------------------------------------------------------- |
| B3 (WF-003 SWIFT gate)   | **M1 §5** — confirm SWIFT field keys + whether `fxRequestDocument` is mandatory to leave FX                    |
| B4 (semantic_role)       | **M1 §6** — approve `EXECUTIVE_REVIEW` enum case (vs reuse/NULL) + whether terminal stages need semantic cases |
| D1 (enum reconciliation) | API-contract additions (`runtime_status`, `semantic_role`, `final_outcome`) land first                         |
| E (concurrency test)     | approval for throwaway fixtures / load run                                                                     |
| Phase B data             | approval to reset/recreate the 48 synthetic V1 requests under V2                                               |
| Legacy removal (F)       | dependency-evidence gates in `09-m6 §5`; separate cleanup-task approval                                        |

**Two M1 sub-confirmations remain open** (SWIFT fields, EXEC semantic value); they
block B3/B4 only. Everything in Phase A is unblocked and can start on approval.
