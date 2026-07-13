# Phase C Checkpoint — API/UI Reliability + Director Parity

Evidence date: 2026-07-11. Phase C (C1–C3) complete. The V1→V2 data recreation
was executed first (mandatory precondition), then all Phase C work landed and was
verified against the recreated V2 dataset in the running app.

---

## 0. V1→V2 data recreation (precondition) — DONE

The 48 active synthetic V1 requests were recreated under the published V2
(id=39); the 8 terminal V1 records were preserved. Full record in
`12-phase-b-checkpoint.md §7`. Post-state: 0 active on V1, 8 terminal on V1, 48
active on V2 (6 at FINAL = Director queue), 0 orphans; V2 active stage spread
matches the original V1 distribution exactly.

Required a one-line, default-preserving change to
`EngineRequestAnchorInvariantValidator::validate($req, $expectedVersionNumber = 1)`
so the tested scenario builder could target V2 without weakening the seeder's V1
invariant (commit `3318a9db`).

---

## 1. C1 — API-UI-001 (stats storm) — DONE

**Backend (commit `2d399706`):** the `engine-requests/stats` endpoint 500'd on
MySQL from two SQLite/MySQL parity defects, which drove the client into a retry
storm and eventual 429:

1. The `by_status` grouped pass carried `withStageEntry()`'s accumulated
   projection while grouping only by status → MySQL 1055 under
   `ONLY_FULL_GROUP_BY`. Fixed by resetting the select list with `select()`.
2. The SLA "nearing" window used `MAX(1, CAST(x AS INTEGER))`, SQLite-only → MySQL
   1064. Moved to the driver-branched `EngineRequest::nearingWindowSql()`
   (`GREATEST` / `CAST AS SIGNED`).

Engine-independent regressions added (the suite runs on SQLite, which ignores
both strict behaviours). Verified 200 with correct buckets on the live MySQL dev
DB.

**Frontend (commit `352b7727`):** the store's stats load now has single-flight
per scope, a terminal-error circuit (a failing params signature is not
re-fired until it changes or the user retries), 429 detection with a dedicated
message, and error surfacing into a non-gating page Alert. The page uses
`Promise.allSettled` and a retry that clears the circuit, so a stats failure
never rejects the list load or hides the table.

## 2. C2 — UI-RBAC-001/002 (denial + error states) — DONE

Commit `a59d675b`.

- **UI-RBAC-001:** `/admin/workflows` gained route-level
  `definePageMeta({ middleware: ['auth','screen'], requiredScreen: 'workflow_designer' })`
  so an unpermitted user is redirected to `/forbidden` before the designer shell
  mounts, instead of seeing a blank shell (every panel withheld by `ScreenGuard`).
- **UI-RBAC-002:** the workflow instance page catches a failed `loadInstance` and
  renders the shared `ErrorState` with the right HTTP code (403/404/429/500) and a
  retry, instead of a blank shell. Two instance-page tests added.

## 3. C3 — UI-FX-001 + RBAC-005 (Director parity) — DONE

Commit `39e74922`.

**UI-FX-001:** under V2 the Director executes the **FINAL** stage, but the
dashboard headlined the `fx_confirmation_pending` bucket — the **FX_CONFIRM**
stage owned by the national FX team, a **disjoint** record set (both 6, zero
overlap). Fixed by:

- a new `director_final_queue` read-model bucket (FINAL stage);
- rewriting `committeeDirectorStats()` to headline the FINAL queue
  (`final_pending` + `final_pending_queue`) with finalized approved/rejected
  counters; backward-compatible keys now mirror the FINAL queue;
- a dedicated `CommitteeDirectorDashboard.vue` (FINAL-queue KPIs + table, no
  executive-voting UI, which is out of V1 scope), routed for `COMMITTEE_DIRECTOR`
  instead of the shared `ExecutiveDashboard`.

**RBAC-005:** the Director's `requests` capability is **derived** from a
`stage_permissions` EXECUTE row, so it exists only once the Director owns an
executable stage. On the corrected V2 the Director owns FINAL, so the visible
`/workflows` nav link is no longer a dead-end. Added a regression asserting the
seeded Director's `/auth/me` grants `requests` on the published V2 — combining the
real capability map with the offered navigation, as the audit required. **This
also documents that RBAC-005 is resolved by the V2 workflow, not V1** (the base
`ImportFinancingWorkflowSeeder` still assigns FINAL to `committee_manager`; the
`committee_director` ownership is the V2 correction).

---

## 4. Live browser verification against the recreated V2 dataset

Signed in through the local demo-switch and drove the running app
(`playwright-cli`).

| Check | Result |
| ----- | ------ |
| Director dashboard (V2) | Renders `CommitteeDirectorDashboard`; banner "6 طلبات بانتظار الاعتماد النهائي"; KPIs 6 / 4 / 4; FINAL queue lists ENG-2026-{YBRD,TIIB}-A011/A012/A013. **No voting UI.** |
| `/customs` (Director) | "طلبات جاهزة للإصدار (6)" — the **same six** records as the dashboard. UI-FX-001 disjoint-queue defect resolved. |
| `/workflows` (Director) | URL stays `/workflows` (no `/forbidden` redirect); page + data table render; nav link functional. RBAC-005 dead-end resolved. |
| API-UI-001 stability | A clean single page load produces **0 console errors / 0 429s**. Rapid multi-page manual navigation briefly hit the shared 5/min limiter and produced only **4 bounded errors** (2 endpoints × 2), handled gracefully — not the unbounded storm the audit documented. |
| SWIFT UI vs V2 field rules (WF-003) | As `swift@ybrd.com.ye`, Approve on an FX-stage request returned **HTTP 422** and the request did **not** advance past FX — the mandatory SWIFT package (reference + 2 PDFs) gate enforced at runtime, no generic-Approve bypass. |

Screenshots captured: `director-dashboard-v2.png`, `director-workflows-v2.png`,
`swift-fx-gate-422.png` (local evidence, not committed).

---

## 5. Verification summary

- Backend focused suites green: stats (5), Director dashboard (4), snapshot (1),
  Director role incl. RBAC-005 parity (5); Pint clean.
- Frontend focused suites green: engine-requests store (9), instance detail (19),
  dashboard page routing (9), dashboard store (10), Director dashboard (6);
  ESLint zero-warning; touched-file typecheck clean (unrelated
  semantic_tag/semantic_role baseline errors remain, out of scope).
- Live V2 browser verification complete for the Director queue/nav, `/customs`
  parity, stats stability, and the SWIFT field-rule gate.

**Phase C is complete and verified against the recreated V2 dataset.** No pending
Phase C item remains blocked.
