# Phase F — Voting/Legacy Artifact Inventory (F1) + Related Findings

**Evidence date:** 2026-07-12 · **Status:** Read-only inventory. No deletions
performed yet — classification only, per the approved Phase F gate.

---

## 1. Voting artifacts

| Artifact | Location | Classification | Evidence |
| -------- | -------- | --------------- | -------- |
| `VoteType` enum | `backend/app/Enums/VoteType.php` | **Orphaned** | Zero usages anywhere outside its own file. |
| `request_votes` table | (referenced only in migration filenames) | **Orphaned (table does not physically exist)** | `Schema::hasTable('request_votes')` → false in dev DB. Zero code references to `request_votes`/`RequestVote` anywhere in `app/`. |
| `import_requests` table | (referenced only in migration filenames) | **Orphaned (table does not physically exist)** | `Schema::hasTable('import_requests')` → false. No `ImportRequest` model exists (purged in `a14a7ba1`). |
| `eligible_voter_ids` column | migration `2026_05_27_100000_add_eligible_voter_ids_to_import_requests.php` | **Orphaned (parent table does not exist)** | Zero code references; the column lived on `import_requests`, which no longer exists. |
| Voting migration files (3) | `database/migrations/2026_05_1{3,4}_*`, `2026_05_27_*` | **Migration-only** | Immutable historical DDL record. Do not edit/delete — Laravel migrations are an append-only audit trail of schema history, even for tables later dropped. |
| `VotingSessionStatus` enum (backend) | `backend/app/Enums/VotingSessionStatus.php` | **Orphaned** | Zero usages anywhere outside its own file. Distinct from the frontend `VotingSessionStatus` already removed in Phase D — this is a separate backend-only enum, previously missed. |
| `NotificationType::VOTING_OPENED` | `backend/app/Enums/NotificationType.php:18` | **Runtime-registered, never dispatched** | Has entries in `NotificationRegistry.php` and `TemplateResolver.php` (so the type is "wired" for template resolution) but zero `NotificationType::VOTING_OPENED` dispatch call sites — confirms the Phase D finding that it's allow-listed but dead. |
| `AuditAction::VOTE_CAST` | `backend/app/Enums/AuditAction.php:15` | **Runtime-registered, never dispatched** | Enum case + label exist; zero `AuditAction::VOTE_CAST` dispatch call sites anywhere. Zero `audit_logs` rows with this action in dev DB. |
| `frontend audit.vue` labels: `VOTE_SUBMITTED`, `VOTING_SESSION_OPENED`, `VOTING_SESSION_CLOSED` | `frontend/app/pages/audit.vue:214-218` | **Historical-read compatibility (defensive)** | No corresponding backend enum/dispatch site under these exact names (`VOTE_SUBMITTED` vs. backend's `VOTE_CAST` — a naming mismatch, likely pre-rename). Zero matching `audit_logs` rows found in dev DB. `formatAction()` has a graceful fallback for unmapped codes, so keeping these costs nothing and protects against any environment where such rows do exist. **Recommendation: keep as-is, do not delete** (see F2 below). |
| `database/seeders/NotificationSeeder.php` | Whole file | **Orphaned, and currently broken** | Not called by `DatabaseSeeder` or anywhere else. Imports `App\Models\ImportRequest` (deleted class) and `App\Enums\RequestStatus` (never existed as a backend class) — this file cannot execute successfully today; it predates the `ImportRequest`→`EngineRequest` migration and was never cleaned up. **High-confidence deletion candidate** once dependency-proof (below) is run. |

---

## 2. F2 — Historical audit-event readability (verified, no action needed)

`audit.vue`'s `ACTION_LABELS` map (line ~200-219) already has a graceful
fallback: `formatAction()` returns `ACTION_LABELS[action] ?? action.replace(/_/g, ' ')`.
This means:

- The map is a *display* nicety, not a hard dependency — an unmapped action
  code still renders (as a de-slugified string) rather than breaking.
- The voting-era entries (`VOTE_SUBMITTED`, `VOTING_SESSION_OPENED`,
  `VOTING_SESSION_CLOSED`) already satisfy the user's explicit requirement
  ("preserve a lightweight compatibility label or mapping even after
  runtime voting code is removed") — they cost nothing to keep and require
  no further action right now.
- **Recommendation:** leave this map untouched. When backend voting-model
  removal happens in a later Phase F sub-step, do not remove these 3 map
  entries even though their backend enum cases will be gone — they exist
  precisely for the case where historical rows using these codes exist in
  some environment this session cannot see.

---

## 3. F5 — Legacy customs terminology (`CUSTOMS_DECLARATION_ISSUED`)

| Location | Type | Action |
| -------- | ---- | ------ |
| `database/migrations/2026_06_03_000001_add_fx_confirmation_pending_to_request_status.php:20,37` | Migration-only (immutable DDL) | **Keep** — migrations are append-only history. |
| `database/seeders/NotificationSeeder.php:204` | Part of the orphaned/broken seeder above | Resolved by removing the whole file (see §5). |
| `tests/Feature/CbyAdminDashboardStatsTest.php:296` | Code comment only | **Keep** — a comment explaining historical status-porting logic, not a functional reference. No action needed. |
| `frontend/app/pages/audit.vue:219` (`CUSTOMS_DECLARATION_ISSUED: 'إصدار تأكيد المصارفة الخارجية'`) | Historical-read compatibility | **Keep**, same reasoning as F2 above. |

**Conclusion:** no live/functional `CUSTOMS_DECLARATION_ISSUED` reference exists anywhere. The only actionable item is the dead `NotificationSeeder.php` file.

---

## 4. F6 — Residual executor-specific dashboard stats methods

`App\Services\Dashboard\DashboardStatsService::stats()` still dispatches to
6 per-role private methods that predate the D0 capability-family dashboard
model:

`dataEntryStats()`, `bankReviewerStats()`, `supportCommitteeStats()`,
`swiftOfficerStats()`, `executiveMemberStats()`, `committeeDirectorStats()`
(plus `executiveVotingStats()`, called internally by `executiveMemberStats()`
— voting-flavored data never surfaced anywhere live).

**Classification: Test-only, not Orphaned.**

- **Route is live:** `GET /api/dashboard/stats` → `DashboardController::stats()` → `DashboardStatsService::stats()`. The route dispatch `match` still routes `INTAKE`/`INTERNAL_REVIEWER`/`SUPPORT`/`FX_SWIFT`/`COMMITTEE_MANAGER`/`COMMITTEE_DIRECTOR` role codes to these methods — a direct API call from any of those roles would successfully execute this code today.
- **Zero frontend consumer:** `useDashboard()`'s `fetchStats()` (the only caller of `/api/dashboard/stats`) is only invoked from `BankAdminDashboard.vue` and `CbyAdminDashboard.vue` — the two Analytics-family dashboards. `MyWorkDashboard.vue` (serving all 6 executor roles) uses `useDashboardWorkStore`/`GET /api/dashboard/work` exclusively, confirmed by direct read of its imports.
- **Tested:** `PivotDashboardDispatchTest::test_each_role_gets_a_dashboard_response` exercises all role branches including these 6.

**This does NOT meet the "orphaned, safe to delete" bar** — it's a live,
reachable, tested API surface with a real response, just one the frontend
never calls for these roles anymore. Removing it would be a genuine
API-contract change (removing a working endpoint response for 6 roles),
not a dead-code cleanup — **outside the "no new product behavior" Phase F
charter** unless explicitly re-scoped. **Recommendation: do not remove in
this Phase F pass.** Flag for a future, deliberately-scoped decision (keep
as a defensive fallback API, or formally deprecate with its own approval)
rather than silent deletion now.

---

## 5. Dependency-proof pass for the one confirmed-safe deletion (`NotificationSeeder.php`)

Per the user's explicit safety checklist, verified before any deletion:

- [x] No active route uses it — it's a `Seeder`, not reachable via any HTTP route.
- [x] No current workflow version references it — unrelated to workflow config.
- [x] No queue job, listener, notification, or policy depends on it — `grep -rln "NotificationSeeder"` returns only its own file.
- [x] No migration rollback depends on the class or enum — it's not referenced by any migration's `up()`/`down()`.
- [x] No archived record becomes unreadable — it seeds fake data, doesn't read/interpret existing records.
- [x] No audit event renderer depends on it — unrelated to `audit.vue`'s rendering.
- [x] No seed/fixture required for historical compatibility depends on it — it is itself never invoked by `DatabaseSeeder`, so nothing downstream depends on its output existing.

**All 7 checks pass. Safe to delete** — and per the dependency evidence
above, it is also currently **broken** (references two non-existent
classes), so it cannot be executed successfully in its current state
regardless.

---

## 5a. Task #13 — dead exception handlers in `bootstrap/app.php` (RESOLVED)

Per the user's explicit checklist. This re-investigation corrected an error
in the Phase E checkpoint's original CF-5 conclusion.

**Checklist walkthrough:**

1. **Does Laravel still route any current exception to them?** Verified via
   `class_exists()` and exhaustive `grep` for throw-sites:
   - `WorkflowImmutableStateException` — `class_exists()` returns **false**.
     The class does not exist anywhere in the codebase. Its handler could
     never fire (Laravel's exception matching is based on the actual thrown
     exception's class; a handler type-hinting a nonexistent class simply
     never matches — confirmed no fatal at boot or route-list time).
   - `WorkflowLockedStateException` — file exists (`class_exists()` → true)
     but **zero throw-sites** anywhere in `app/`. Its handler is registered
     but unreachable in practice.
   - `DuplicateVoteException`, `VotingException` — both exist as trivial
     empty subclasses, both with **zero throw-sites** anywhere, sharing one
     dead union-type handler.
   - **`LogicException` (403, `WORKFLOW_IMMUTABLE_STATE`) — genuinely
     reachable**, correcting the Phase E checkpoint's claim. 5 live Eloquent
     `updating` model-event guards throw it: `ReferenceTable`,
     `CustomsDeclaration`, `WorkflowAction`, `WorkflowDefinition`,
     `ReferenceValue` (all "X is immutable once created" guards on direct
     `->update()` calls that dirty an immutable field). **Not removed.**
2. **Do they duplicate centralized API error handling?** No — each handler
   was uniquely shaped (different error codes/status), not a duplicate of
   another live handler.
3. **Do historical or non-API routes depend on them?** No — every handler in
   this file (including the ones removed) is gated on `$request->is('api/*')`;
   a non-API context never receives their custom JSON shape regardless.
4. **Does removing them change status codes or response shapes for anything
   currently working?** No — zero test anywhere asserts on
   `WORKFLOW_LOCKED_STATE`, the `WorkflowImmutableStateException` 403 shape
   (`current_status` field), or any `DuplicateVoteException`/`VotingException`
   response. Confirmed via `grep -rn "WORKFLOW_LOCKED_STATE" tests/` → 0 hits.

**Action taken:** removed `WorkflowImmutableStateException`'s handler + `use`
import (class doesn't exist); removed `WorkflowLockedStateException`'s class
file + handler + `use` import (zero throw-sites); removed
`DuplicateVoteException` and `VotingException`'s class files + shared handler
+ `use` imports (zero throw-sites, voting-era dead code). **Kept** the
`LogicException` 403 handler untouched — it has real, live throw-sites and
removing it would be a genuine behavior change to 5 models' write-guard
error responses, which is outside this cleanup's dependency-proof bar.

**Verification:** `php artisan route:list` — 233 routes, boots clean.
`composer dump-autoload -o` — no errors. Pint clean on `bootstrap/app.php`.
`grep -rln` for all 4 removed class names across `app/` and `database/` —
zero remaining references. Full `tests/Feature/Workflow`,
`tests/Feature/Merchants`, `tests/Feature/Engine` suites re-run — see Phase
F checkpoint for the pass/fail count.

**Task #13: RESOLVED.**

---

## 5b. F8 — `docs/user-view/` archival strategy (PROPOSAL, not executed)

**Current status:** 8 per-role UX spec files (~294 KB total: `bank-admin.md`,
`bank-reviewer.md`, `cby-admin.md`, `committee-director.md`, `data-entry.md`,
`executive-member.md`, `support-committee.md`, `swift-officer.md`), already
marked "deprecated historical material" in `AGENTS.md` since Phase D.

**Genuine finding during this review:** `docs/04-frontend-guide.md` still
had a live "Per-Role UX Authority" section instructing readers to treat
`docs/user-view/{role}.md` as authoritative and to cite it for new UI work
— directly contradicting `AGENTS.md`'s current statement. This is a real
doc-consistency defect (not archival-scope, a correctness fix) and has been
corrected in this pass — see the diff to `docs/04-frontend-guide.md`.

**Removal-gate check (per `09-m6-enum-reconciliation.md` §5, "Removal gates"
— applied here since archival is documentation-only, zero application
dependency by construction):**
- [x] No active API route depends on it — confirmed, these are `.md` files.
- [x] No DB table/persisted data depends on it.
- [x] No queue job/event listener depends on it.
- [x] No current workflow version references it.
- [x] No required audit history becomes unreadable — `grep -rn "docs/user-view" frontend/ backend/ --include="*.ts" --include="*.vue" --include="*.php"` → **zero hits**. No application code references these files at all.
- [x] Replacement documentation exists for the current model — `AGENTS.md`'s "Canonical Request State Model," "Dashboard Architecture," and the frontend `PRODUCT.md`/`DESIGN.md`/`SHADCN.md` trio now cover role/UX decisions.

**12 files reference `docs/user-view/`** (via `grep -rln`): `AGENTS.md`,
`docs/04-frontend-guide.md` (now fixed), plus 10 files under
`docs/audit-functional/` and `docs/superpowers/` — all of these are
**historical audit/decision records** that correctly describe
`docs/user-view/` as deprecated/historical; none require it to exist at its
current path to remain readable (they're prose referencing a path, not a
live include/import).

**Proposed archival strategy (not executed — review only per F8 scope):**

1. **Do not delete.** These files have real historical value as the original
   static-role design intent record, and `05-m1-workflow-contract.md` line
   160 explicitly says "archive/delete only in a separately approved
   cleanup task" — deletion needs its own explicit approval, distinct from
   archival.
2. **Move, don't delete:** `docs/user-view/*.md` → `docs/archive/user-view/*.md`
   (or `docs/_deprecated/user-view/`, whichever matches an existing repo
   convention — none currently exists, so `docs/archive/` is proposed as a
   new, self-explanatory convention for future deprecated-but-retained
   docs).
3. Add a single `docs/archive/user-view/README.md` banner file (new) stating
   in one paragraph: what this was, when it was deprecated (Phase D,
   2026-07-12), and pointing to `AGENTS.md`'s current model — so a reader
   who stumbles onto the archive folder immediately understands its status
   without reading all 8 files.
4. Update the 12 referencing files' relative links from `docs/user-view/` to
   `docs/archive/user-view/` in the same commit as the move (a mechanical
   sed-and-verify pass, not a content rewrite — these files' prose already
   correctly calls it deprecated).
5. **Do not** touch `testing-manual/` — the July 4 audit already confirmed
   by direct content diff that it's a distinct QA-test-sequence document,
   not a duplicate of `docs/user-view/`, and remains presumably still
   current (out of this Phase F's scope to re-verify).

**Recommendation:** this move is low-risk (zero code dependency, well
short of any destructive-action threshold) and could be executed in this
same Phase F pass if you approve it now, or deferred to a dedicated
follow-up — flagging as a decision point rather than executing unilaterally,
since moving files is technically a structural change to the repo layout
that a written archival plan should get explicit sign-off on, per the
spirit of "gated legacy-removal" even though this specific action is safe.

---

## 5c. F9 — Database cleanup dependency evidence

**Conclusion: no schema/data cleanup is required.** Exhaustively re-verified
(2026-07-12, direct `Schema::hasTable()`/`getColumnListing()` calls, not
just `grep`):

- `request_votes` table — does not exist.
- `import_requests` table — does not exist.
- `voting_sessions` table (checked as a plausible name, not previously
  found in migration filenames) — does not exist.
- `engine_requests` table — **zero columns** with `vot` in the name (no
  `eligible_voter_ids`, no vote-related column of any kind).

The 3 voting/import-request-related migration files found in F1 §1
(`2026_05_13_000008_create_request_votes_table.php`,
`2026_05_14_100003_add_voted_at_and_auto_abstain_to_request_votes_table.php`,
`2026_05_27_100000_add_eligible_voter_ids_to_import_requests.php`) describe
tables/columns that were **already dropped** by later migrations not
individually inventoried here (the drop migrations themselves are presumably
part of the same `import_requests`→`engine_requests` architecture migration
that removed the `ImportRequest` model, per commit `a14a7ba1`). Since the
current schema has zero trace of these objects, there is nothing left to
propose a dry-run/backup/rollback plan for — **F9's normal deliverables
(dependency evidence, proposed migrations, row counts, backup plan) do not
apply; the underlying database cleanup already happened in an earlier,
untracked-by-this-audit change.**

**No destructive action needed or proposed for F9.** The 3 historical
migration files themselves stay untouched (immutable schema history, per
the same rule applied to `CUSTOMS_DECLARATION_ISSUED`'s migration in F5).

---

## 6. Summary classification table (F1 deliverable)

| Artifact | Classification |
| -------- | --------------- |
| `VoteType` enum | Orphaned |
| `request_votes` table | Orphaned (does not exist) |
| `import_requests` table | Orphaned (does not exist) |
| `eligible_voter_ids` column | Orphaned (does not exist) |
| Voting migration files | Migration-only (keep, immutable history) |
| Backend `VotingSessionStatus` enum | Orphaned |
| `NotificationType::VOTING_OPENED` | Runtime-registered, never dispatched (keep for now — see §7) |
| `AuditAction::VOTE_CAST` | Runtime-registered, never dispatched (keep for now — see §7) |
| `audit.vue` voting/customs labels | Historical-read compatibility (keep, no action) |
| `NotificationSeeder.php` | Orphaned + broken (safe to delete, dependency-proven) |
| `DashboardStatsService` 6 executor-branch methods | Test-only, live+reachable+tested (do NOT remove this phase) |

---

## 7. Recommendation for this Phase F pass

**Non-destructive actions to take now:**
1. Delete `database/seeders/NotificationSeeder.php` (dependency-proven safe, and currently broken/dead).
2. Leave `VoteType`, `request_votes`/`import_requests` migration files, backend `VotingSessionStatus`, `NotificationType::VOTING_OPENED`, `AuditAction::VOTE_CAST` **in place** for this pass — they are orphaned but their removal is genuinely a "backend voting-model removal" item requiring its own dependency-proof pass per role in the recommended F sequence (item 4), not bundled into this inventory step. Since `VoteType` and `VotingSessionStatus` are pure enum files with zero usages, their removal is low-risk and can follow immediately after this doc is reviewed — recommend as the next concrete Phase F sub-step.
3. Do NOT touch `DashboardStatsService`'s 6 executor-branch methods — live, tested, reachable API surface; removal is a product-contract decision outside this phase's charter.
4. Do NOT touch `audit.vue`'s historical labels or the migration files — both are explicitly protected by the user's safety rules.
