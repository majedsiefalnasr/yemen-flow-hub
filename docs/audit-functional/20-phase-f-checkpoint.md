# Phase F Checkpoint — Legacy Removal & Final Cleanup

**Evidence date:** 2026-07-12 · **Baseline:** `main` post-`bfc43060` (Phase E
finding-count reconciliation) · **Scope:** gated, non-destructive legacy
cleanup only, per the approved Phase F charter. No new product behavior.

**Status: Phase F non-destructive work complete.**

---

## 1. Legacy artifacts removed

| Artifact                                                    | Reason                                                                                                                                                                                                                   | Verification                                                            |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------- |
| `backend/database/seeders/NotificationSeeder.php`           | Orphaned (zero callers) and broken (imports the deleted `ImportRequest` model and a `RequestStatus` class that never existed as a backend class)                                                                         | 7/7 dependency-proof checklist passed (§5 of `19-phase-f-inventory.md`) |
| `backend/app/Enums/VoteType.php`                            | Orphaned — zero usages anywhere outside its own file                                                                                                                                                                     | `grep -rln` across `app/`, `database/`                                  |
| `backend/app/Enums/VotingSessionStatus.php`                 | Orphaned — a second, backend-only copy distinct from the frontend enum already removed in Phase D; zero usages                                                                                                           | Same                                                                    |
| `backend/app/Exceptions/WorkflowLockedStateException.php`   | Class exists but zero throw-sites anywhere; its `bootstrap/app.php` handler was unreachable in practice                                                                                                                  | `class_exists()` true, throw-site grep zero hits, zero test coverage    |
| `backend/app/Exceptions/DuplicateVoteException.php`         | Zero throw-sites; shared a dead union-type handler with `VotingException`                                                                                                                                                | Same                                                                    |
| `backend/app/Exceptions/VotingException.php`                | Zero throw-sites                                                                                                                                                                                                         | Same                                                                    |
| 3 dead `bootstrap/app.php` handler blocks + 4 `use` imports | Handlers for `WorkflowImmutableStateException` (class doesn't exist at all — `class_exists()` false), `WorkflowLockedStateException`, and the shared `DuplicateVoteException\|VotingException` handler — all unreachable | Task #13's full checklist (§5a of `19-phase-f-inventory.md`)            |

**Net removal:** 6 files deleted, 1 file edited (`bootstrap/app.php`, −22 lines net).

---

## 2. Legacy artifacts retained, and why

| Artifact                                                                                                                                                                                          | Why retained                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `LogicException` 403 handler in `bootstrap/app.php` (`WORKFLOW_IMMUTABLE_STATE`)                                                                                                                  | **Live and reachable** — 5 model `updating` guards (`ReferenceTable`, `CustomsDeclaration`, `WorkflowAction`, `WorkflowDefinition`, `ReferenceValue`) throw it on a direct `->update()` that dirties an immutable field. Task #13's own checklist corrected an earlier (Phase E) misclassification of this handler as dead — see §5a.                                                                                                                                                  |
| 3 voting-era migration files (`create_request_votes_table`, `add_voted_at_and_auto_abstain...`, `add_eligible_voter_ids...`)                                                                      | Immutable schema history — Laravel migrations are an append-only audit trail even for tables/columns later dropped. The tables/columns they describe no longer physically exist (confirmed via `Schema::hasTable()`), so there is nothing left to migrate away from (see F9, §5c).                                                                                                                                                                                                     |
| `CUSTOMS_DECLARATION_ISSUED` migration references (2 lines)                                                                                                                                       | Same — immutable schema history.                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `audit.vue`'s `VOTE_SUBMITTED`/`VOTING_SESSION_OPENED`/`VOTING_SESSION_CLOSED`/`CUSTOMS_DECLARATION_ISSUED` label-map entries                                                                     | Explicit historical-compatibility requirement (F2) — `formatAction()` already has a graceful unmapped-code fallback, so these cost nothing to keep and protect any environment where such historical `audit_logs` rows exist, even though none were found in this dev DB.                                                                                                                                                                                                              |
| `NotificationType::VOTING_OPENED`, `AuditAction::VOTE_CAST` (backend enums)                                                                                                                       | Registered in `NotificationRegistry`/`TemplateResolver` but never dispatched — genuinely dead in practice, but **not removed this pass**: removing a `NotificationType`/`AuditAction` case is a schema-adjacent decision (these values may be persisted as strings in historical rows) that deserves its own dependency-proof pass distinct from the exception-handler cleanup, consistent with "do not combine unrelated cleanups." Flagged for a future, dedicated Phase F sub-step. |
| `DashboardStatsService`'s 6 executor-branch stats methods (`dataEntryStats`, `bankReviewerStats`, `supportCommitteeStats`, `swiftOfficerStats`, `executiveMemberStats`, `committeeDirectorStats`) | **Live, routed, and tested** (`GET /api/dashboard/stats`, exercised by `PivotDashboardDispatchTest`) — the frontend no longer calls this path for these 6 roles (`MyWorkDashboard.vue` uses `/api/dashboard/work` instead), but the API contract itself still works if called directly. Removing a working API response is a product-contract decision outside "no new product behavior" — explicitly not touched (F6, §4 of `19-phase-f-inventory.md`).                               |
| `docs/user-view/*.md` (8 files)                                                                                                                                                                   | Explicitly gated — `05-m1-workflow-contract.md` requires deletion/archival to be a separately approved task. A concrete archival proposal (move to `docs/archive/user-view/`, add a deprecation-banner README, fix 12 referencing links) was written but **not executed** — a review-only deliverable per F8's scope (§5b).                                                                                                                                                            |
| `docs/user-view/`'s underlying `.md` files themselves                                                                                                                                             | Same — see above. Their referencing docs (`AGENTS.md`, 10 audit-functional/superpowers docs) already correctly describe them as deprecated; only `docs/04-frontend-guide.md` had a stale contradiction, which **was** fixed (see §3 below — a correctness fix, not an archival action).                                                                                                                                                                                                |

---

## 3. Documentation correctness fix (found during F8)

`docs/04-frontend-guide.md`'s "Per-Role UX Authority" section still instructed
readers to treat `docs/user-view/{role}.md` as authoritative and to cite it
for new UI work — directly contradicting `AGENTS.md`'s Phase D statement
that `docs/user-view/` is deprecated historical material. This is a genuine
doc-consistency defect, fixed in this pass (not an archival decision — the
file's own claim about current authority was simply wrong). The "Design
Consistency Requirement" section's spec-citation line was updated to match.

---

## 4. Schema/data changes proposed or executed

**None.** F9's investigation (§5c of `19-phase-f-inventory.md`) found that
all voting-related tables/columns (`request_votes`, `import_requests`,
`eligible_voter_ids`) already do not physically exist in the current schema
— confirmed via direct `Schema::hasTable()`/`getColumnListing()` calls, not
inference from grep. There is no dependency evidence, migration, dry-run
query, row count, or rollback plan to produce, because there is nothing left
to clean up at the database level. The underlying schema cleanup already
happened in an earlier change not tracked by this audit (likely alongside
the `ImportRequest`→`EngineRequest` architecture migration, commit
`a14a7ba1`).

---

## 5. Historical compatibility evidence

- `audit.vue`'s voting/customs label-map entries retained with a graceful
  fallback for any unmapped code (§2 above) — verified via direct read of
  `formatAction()`.
- Zero historical `audit_logs` rows found matching any voting action code in
  this dev DB (`VOTING_SESSION_OPENED`, `VOTING_SESSION_CLOSED`, `VOTE_CAST`,
  `VOTE_SUBMITTED` all return 0 rows) — the retained label-map entries are a
  defensive measure for environments this session cannot observe, not a
  requirement proven necessary here.
- The 3 voting-era + 2 customs-era migration files were left completely
  untouched (not even reformatted) — confirmed via `git status` showing no
  diff against them.

---

## 6. Resolution of carried-forward tasks

### Task #13 — dead exception handlers in `bootstrap/app.php`

**RESOLVED.** Rigorous re-investigation (corrected the Phase E checkpoint's
earlier, less careful conclusion) found:

- `WorkflowImmutableStateException` — class genuinely does not exist
  (`class_exists()` → false). Handler removed.
- `WorkflowLockedStateException` — class exists, zero throw-sites. Class +
  handler removed.
- `DuplicateVoteException`, `VotingException` — zero throw-sites each,
  shared one dead handler. Both classes + the shared handler removed.
- `LogicException` 403 handler — genuinely live (5 real model guards throw
  it). **Not removed** — this was the one case where deeper investigation
  reversed the earlier "dead code" assumption.

All 4 of the user's checklist items verified: (1) confirmed via
`class_exists()`/exhaustive throw-site grep which exceptions are actually
routed; (2) no handler duplicated another's shape; (3) every handler in the
file gates on `$request->is('api/*')`, so non-API routes were never
affected by any of them; (4) zero test anywhere asserts on the removed
handlers' response shapes, confirmed removal changes nothing observable.

### Task #14 — `ReferenceDataPage.test.ts` fixture failures

**RESOLVED — root cause was a stale test fixture, not a production defect.**
Two independent, compounding bugs, both found and fixed:

1. `reference-data.vue`'s `onMounted` auto-selects the first table and
   fetches its values (added in commit `7a053d9b`, after the test file was
   last updated) — this consumed a `mockGet` response the test's own
   explicit-click assertion needed, silently starving it.
2. `beforeEach`'s `vi.clearAllMocks()` only clears mock call _history_, not
   queued `mockResolvedValueOnce` implementations (confirmed against
   current Vitest docs) — so a leftover queued response from one test could
   leak into the next test's queue across the file's run order.

Fixed by queuing the auto-select's values response in `mountPage()` itself,
and switching to `vi.resetAllMocks()`. No assertion was weakened — every
original expectation in all 7 tests is unchanged; the file now passes 7/7
consistently (verified 3 repeated runs).

---

## 7. Full test/build results (post Phase F)

- **Backend Pint:** 50 files with pre-existing debt (`BASELINE-FMT-001`,
  unchanged), zero new debt. All Phase F-touched files (`bootstrap/app.php`,
  the 2 probe suites, `PerfLoadScenarioCommand.php`) Pint-clean.
- **Backend regression** (`Dashboard`, `Engine`, `Workflow`, `Merchants`,
  `Permission`, `Audit`, `Governance`): **0 failures**, 2406 assertions, 653
  deprecation notices (`BASELINE-BE-001`, cosmetic PDO/SSL noise only).
- **Frontend full suite:** 8 failed files / 15 failed tests / 1014 passed —
  **improved** from Phase E's baseline (9 files / 18 tests / 1011 passed):
  `ReferenceDataPage.test.ts` is now fully green (was 3 of its 7 tests
  failing), dropping the known-baseline count by exactly 1 file / 3 tests.
  Remaining 8 files match `BASELINE-FE-001` + `BASELINE-FE-004` exactly —
  confirmed zero new regressions.
- **Frontend ESLint/Prettier** on all Phase F/E-touched files: clean.
- **App boot:** `php artisan route:list` — 233 routes (unchanged count),
  `composer dump-autoload -o` — no errors.

---

## 8. Final traceability matrix

See [`17-phase-e-traceability-matrix.md`](./17-phase-e-traceability-matrix.md)
— updated in this phase to correct CF-5's resolution evidence (the
`bootstrap/app.php` cleanup performed here). All 17 findings (16 original +
E8-001) remain ✅ RESOLVED. No finding regressed.

Baseline registry (same doc) updated: `BASELINE-FE-002` was already
RESOLVED in Phase E; `BASELINE-FE-003` remains 🟡 KNOWN BASELINE (task #14
above resolved the underlying bug, but the baseline registry entry itself
documents history — see note below).

**Correction to the baseline registry:** `BASELINE-FE-003`
(`ReferenceDataPage.test.ts`'s 3 newly-exposed failures) is now **RESOLVED**
as of this Phase F pass (task #14). The frontend baseline moves from 9
files/18 tests to **8 files / 15 tests / 1014 passed** — confirmed by the
live re-run in §7, not just arithmetic. Future validation reports should
use this as the current known frontend baseline.

---

## 9. Remaining known debt

| ID                                                          | Status                 | Notes                                                                                                         |
| ----------------------------------------------------------- | ---------------------- | ------------------------------------------------------------------------------------------------------------- |
| `BASELINE-FE-001`                                           | Unchanged, known       | 8 pre-existing failing files, unrelated to any audit-phase work                                               |
| `BASELINE-FE-004`                                           | Unchanged, known       | `auth.store.test.ts` `isCbyUser`/`isBankUser` getter bug, 7 tests                                             |
| `BASELINE-BE-001`                                           | Unchanged, cosmetic    | PHP/PDO SSL-constant deprecation noise                                                                        |
| `BASELINE-FMT-001`                                          | Unchanged, known       | Repo-wide Pint/ESLint debt outside touched files                                                              |
| `BASELINE-BE-002`                                           | Unchanged, known       | 11 pre-existing frontend TS errors                                                                            |
| `NotificationType::VOTING_OPENED`, `AuditAction::VOTE_CAST` | Deferred               | Dead in practice, deserves its own dependency-proof pass before removal (enum-value persistence risk)         |
| `DashboardStatsService`'s 6 executor-branch methods         | Deferred, deliberate   | Live/tested/unreachable-from-frontend; removal is a product-contract decision, out of "no new behavior" scope |
| `docs/user-view/*.md` archival                              | Proposed, not executed | Concrete plan written (§5b of `19-phase-f-inventory.md`); awaiting go-ahead                                   |

---

## 10. Go-live blockers

**None identified.** Every backend and frontend regression suite passes at
or above the established baseline. No new product behavior was introduced.
No destructive schema/data change was proposed or made. The 3 genuinely
dead exception-handler removals and 3 orphaned-file deletions are pure
dead-code cleanup with zero observable behavior change, verified by full
regression re-runs before and after.

## 11. Go-live recommendation

**Recommend proceeding.** Phases A through F (M6 Option B's full arc — RBAC
fixes, workflow-config fixes, API/UI reliability, status-model
reconciliation, regression-hardening, and gated legacy cleanup) are
complete, verified, and documented with a full traceability trail. Two
small non-blocking items remain deliberately deferred (`docs/user-view`
physical archival, the 2 enum values' removal) — both are documented,
low-risk, and explicitly gated behind their own future approval, consistent
with "gated legacy-removal phase" rather than a go-live blocker.

---

**Phase F non-destructive work is complete. No further action is proposed
without explicit approval per the user's stated gates (database migration,
historical-compatibility removal, or archived-record-affecting changes).**
