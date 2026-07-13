# Phase D Steps 1–7 — Inventory, State Contract, Rename, Semantic Rollout

Evidence date: 2026-07-12. Covers Step 1 (inventory), Steps 2-3 (API contract),
Step 4 (EXECUTIVE_VOTE→EXECUTIVE_REVIEW rename), Step 5 (semantic-role/outcome
separation, confirmed already satisfied), Step 6 (semantic_role rollout onto
V2), and Step 7 (compatibility-fallback formalization) below. Supersedes the
M6 doc's dependency count with the current, post-D0 figure and reconciles the
gap.

---

## 0. Reconciling the M6 count

M6 (`09-m6-enum-reconciliation.md` §3) recorded **"1,119 references across 42
files"**. Current grep: **927 `RequestStatus.` references across 31 files**.

The gap is real shrinkage, not a counting-method difference: D0.6 deleted six
bespoke per-role dashboards and eleven of their tests (`DataEntryDashboard`,
`BankReviewerDashboard`, `SupportCommitteeDashboard`, `SwiftOfficerDashboard`,
`ExecutiveDashboard`, `CommitteeDirectorDashboard` + `.vue`/`.test.ts` pairs),
all of which were built directly on `RequestStatus`. Those consumers are gone.
The 31-file, 927-reference figure is the **current, authoritative** baseline
for Phase D.

---

## 1. The structural finding: two parallel request models

`frontend/app/types/models.ts` defines two unrelated request shapes:

- **`ImportRequest`** (line 219) — the pre-engine legacy model. `status:
  RequestStatus`, plus voting-era fields (`voting_rule_version`,
  `voting_opened_by`, `voting_session_status`, `is_tie`, `ready_to_close`)
  that describe a feature **out of V1**.
- **`EngineRequest`** (line 935) — the real dynamic-engine model. `status:
  EngineRequestStatus` (the 5-value runtime status), driven by
  `current_stage_id` / workflow metadata, no voting fields.

`RequestStatus` is exclusively an `ImportRequest`-family concern. Every one of
the 31 consumer files traces back to `ImportRequest`, not `EngineRequest`.

---

## 2. Consumer categories

### 2a. Dead (zero live mount point — safe to delete outright)

| File | Why dead |
| ---- | -------- |
| `components/workflow/WorkflowProgress.vue` | No importer anywhere outside its own test. Its only plausible consumers were the six deleted per-role dashboards. |
| `components/workflow/WorkflowTimeline.vue` | Same — no live importer. Consumes `RequestStageHistory.to_status: RequestStatus` (line 621 of `models.ts`), a field shape nothing else uses. |

These can be removed in Step 9/10 with no replacement needed — not "migrate,"
just delete once a grep confirms no new consumer appeared.

### 2b. Live, load-bearing (must migrate, not delete)

| File | Role | Feed |
| ---- | ---- | ---- |
| `composables/useDashboard.ts` | Calls `GET /api/dashboard/stats` (the legacy endpoint already flagged for residual cleanup in `15-phase-d0-checkpoint.md` §6). Returns `ImportRequest[]` in `recent_requests`/`review_queue`/etc. | `BankAdminDashboard.vue`, `CbyAdminDashboard.vue` (= SystemAdminDashboard), `bank-admin-helpers.ts`, `cby-admin-helpers.ts`, `dashboard.store.ts`. |
| `components/dashboard/BankAdminDashboard.vue` | Retained analytics-family dashboard (permanent per the D0 architecture). Its `bankRecentColumns` table renders `StatusBadge` and `getRequestProgress` off `BankRecentRow.status: RequestStatus`, sourced from `useDashboard.ts`. | Live-mounted, `bank_analytics`-gated. |
| `components/dashboard/CbyAdminDashboard.vue` | Same pattern for the System Admin family — needs direct confirmation of which fields it reads (not yet fully traced; likely the same `ImportRequest[]` shape via `useDashboard.ts`). | Live-mounted, `system_dashboard`-gated. |
| `components/shared/StatusBadge.vue` | Pure presentational: `getBusinessStatus(status: RequestStatus, role)` → label/color/icon. Only remaining live importer is `BankAdminDashboard.vue`. | — |
| `constants/workflow.ts` | The center of gravity: `STATUS_COLORS`, `STATUS_ICONS`, `STATUS_LABELS`, `DATA_ENTRY_STATUS_LABELS`, `STATUS_PROGRESS`, `ROLE_BUCKETS`, `ROLE_FILTER_STATUSES`, `ROLE_ATTENTION_STATUSES`, `getBusinessStatus()`, `getStatusProgress()`. Every `[UserRole.X]` bucket keys off `RequestStatus` values, including `EXECUTIVE_VOTING_OPEN/CLOSED`, `my_vote`, `ready_to_close`, `is_tie` — voting-era shape, out of V1. | Consumed by `StatusBadge.vue`, `WorkflowProgress.vue` (dead), `WorkflowTimeline.vue` (dead), `AuditTimeline.vue` (label lookup only — see 2c). |
| `utils/requestProgress.ts` | `getRequestProgress(status: RequestStatus): number` — a second, independent progress-percent table (distinct from `constants/workflow.ts`'s `STATUS_PROGRESS`). Used by `BankAdminDashboard.vue`. | Live. |
| `components/workflow/SwiftUploadForm.vue` | Imports `ImportRequest` as a prop type only (`request: ImportRequest`). No `RequestStatus` value-level usage found inside the component body. | Needs the prop type swapped to `EngineRequest`, not a behavior migration. |
| `types/models.ts` | Defines `RequestStatus`-typed fields on `ImportRequest`, `RequestStageHistory` (`status: string | null` at line 572 is already loose; `from_status`/`to_status: string | null` at 621-622 — NOT typed `RequestStatus`, already status-agnostic), `ROLE_FILTER_STATUSES` etc. | Type-only. |
| `types/enums.ts` | The `RequestStatus` enum definition itself (22 values, confirmed exact match to M6/AGENTS.md). | Definition. |

### 2c. Live but not actually coupled to the enum values (lower-risk)

| File | Why lower-risk |
| ---- | -------------- |
| `components/workflow/AuditTimeline.vue` | Imports `RequestStageHistory` (whose `from_status`/`to_status` are already `string \| null`, not `RequestStatus`-typed) and looks up display labels via `STATUS_LABELS[raw] ?? raw` — a safe fallback for unknown strings. This component **already degrades gracefully** for non-enum status strings; it needs a `constants/workflow.ts` that still exports `STATUS_LABELS` keyed usefully, but won't break if the enum itself changes shape. |

### 2d. Test / fixture files (18 files — migrate alongside their subject, not independently)

`tests/unit/components/{AuditTimeline,BankAdminDashboard,ClaimBanners,
CorrectionBanner,LockedBanner,SwiftUploadPage,VotingPanel,
VotingRequestDetailPage,WorkflowProgress,WorkflowTimelineSwiftMerge}.test.ts`,
`tests/unit/constants/{workflow-buckets,workflow-status}.test.ts`,
`tests/unit/fixtures/request-data.ts`, `tests/unit/pages/
RequestDetailClaimLogic.test.ts`, `tests/unit/pages/requests/{bank-admin,
bank-reviewer,cby-admin,committee-director,data-entry,executive-member,
support-committee,swift-officer}-requests.test.ts`, `tests/unit/pages/
story-12-3-role-gates.test.ts`, `tests/unit/types/enums.test.ts`.

Two of these (`VotingPanel.test.ts`, `VotingRequestDetailPage.test.ts`) are
explicitly voting-UI tests — candidates for removal in Step 9 once their
subject components are confirmed dead (not yet verified; out of this
inventory's scope, flagged for Step 8/9).

---

## 3. Backend `EXECUTIVE_VOTE` inventory (for Step 4's rename)

Four live files, one historical migration (must not be touched — it is a
backfill record of what already ran, not live logic):

| File | Usage | Rename impact |
| ---- | ----- | -------------- |
| `app/Enums/StageSemanticRole.php:11` | Case definition `EXECUTIVE_VOTE = 'EXECUTIVE_VOTE'`. | Rename the case + value. |
| `app/Support/EngineRequestReadModel.php:28,37` | `dashboardRoles()`-style bucket list; `'executive_queue' => ['roles' => [EXECUTIVE_VOTE], 'codes' => ['EXEC']]`. | Mechanical — same bucket, new case name. `'EXEC'` code fallback unaffected. |
| `app/Services/Workflow/SemanticRegistry.php:43,92` | `stageCodeAliases()['EXEC'] => EXECUTIVE_VOTE`; `dashboardRoles()` list. | Mechanical. |
| `app/Services/Workflow/Effects/CustomsFxPdfEffect.php:122` | `firstEnteredAt($request, StageSemanticRole::EXECUTIVE_VOTE)` to resolve `executive_decided_at` for the FX PDF snapshot. | Mechanical — argument only, no logic change. |
| `database/migrations/2026_07_06_000007_wp4_backfill_import_financing_semantics.php:48` | **Correction (post-execution finding):** this is not a frozen historical record — its `up()` calls `StageSemanticRole::EXECUTIVE_VOTE->value` as a live PHP enum-case reference, re-evaluated on every `php artisan migrate` (fresh clone, CI, `RefreshDatabase` test runs). It is conditionally idempotent (checks `IMPORT_FINANCING` exists, matches on `code = 'EXEC'`) but its *class body* still needs to compile against the current enum. | **Must be updated, not left alone.** Confirmed by execution: renaming the enum case without updating this file breaks `php artisan migrate` on any fresh database (undefined enum case — fatal error), which every CI run and `RefreshDatabase` test performs. Fixed alongside the Step 4 rename in the same commit. |

No other backend file references the case. Live-DB check (2026-07-12, local
dev): `WorkflowStage::whereNotNull('semantic_role')->count()` returned **0** —
no persisted row currently holds `'EXECUTIVE_VOTE'` in this environment, so no
row-level data backfill was needed here. Any environment where this migration
already ran against real data (and wasn't reseeded since) would need a
one-time `UPDATE workflow_stages SET semantic_role = 'EXECUTIVE_REVIEW' WHERE
semantic_role = 'EXECUTIVE_VOTE'` before deploying the renamed enum — flagged
for release-checklist attention, not executed here since no such row exists
locally.

**Correction applied:** the rename kept the case name and backing string
identical (`EXECUTIVE_REVIEW = 'EXECUTIVE_REVIEW'`), avoiding a
`case EXECUTIVE_REVIEW = 'EXECUTIVE_VOTE'` name/value mismatch — consistent
with M6's goal of eliminating exactly that class of drift.

---

## 4. `StageSemanticRole::FINANCE_RESERVE` status

Confirmed present in the enum (8 cases total: INITIAL_ENTRY, BANK_REVIEW,
SUPPORT_REVIEW, SWIFT, EXECUTIVE_VOTE, FINANCE_RESERVE, FX_CONFIRMATION,
FINAL) but **absent from `SemanticRegistry::dashboardRoles()`** (7 entries,
skips FINANCE_RESERVE) and absent from `stageCodeAliases()`. It is not
dead-code — `WorkflowEffectCode::FINANCING_RESERVE`'s required-tags list
(`SemanticRegistry::requiredTagsForEffect()`) is a distinct effect-code
concept, not the same as the stage semantic role. `FINANCE_RESERVE` as a
`StageSemanticRole` case appears reserved for a stage type not present in the
current `IMPORT_FINANCING` V2 workflow (which has 7 stages: CREATE, INTERNAL,
SUPPORT, EXEC, FX, FX_CONFIRM, FINAL — no dedicated finance-reserve stage).
Leave untouched in Phase D; it is neither a rename target nor a dependency
blocker.

---

## 5. What Step 1 concludes

- **31 files**, not 42 — six dashboards + eleven tests already removed by D0.
- **2 files are dead** (`WorkflowProgress.vue`, `WorkflowTimeline.vue`) — no
  migration needed, direct deletion candidates for Step 9/10.
- **1 file needs no real migration**, only graceful degradation confirmation
  (`AuditTimeline.vue` — already string-typed on the fields that matter).
- **The real migration surface** is `constants/workflow.ts` (voting-era
  `ROLE_BUCKETS` logic for `EXECUTIVE_MEMBER`/`COMMITTEE_DIRECTOR`, must lose
  the `EXECUTIVE_VOTING_OPEN/CLOSED`, `my_vote`, `ready_to_close`, `is_tie`
  branches per the "voting is out of V1" constraint), `StatusBadge.vue`,
  `utils/requestProgress.ts`, `types/models.ts` (`ImportRequest` →
  `EngineRequest`-shaped fields), and the two files that actually mount
  `RequestStatus`-driven UI in production: `BankAdminDashboard.vue` and (to be
  confirmed) `CbyAdminDashboard.vue`, both fed by the legacy
  `useDashboard.ts` → `GET /api/dashboard/stats` path.
- This **directly overlaps** the D0 checkpoint's deferred residual-stats
  cleanup (`15-phase-d0-checkpoint.md` §6): `useDashboard.ts` and
  `/dashboard/stats` are simultaneously (a) the D0 follow-up's target and (b)
  Phase D's `RequestStatus` migration surface for the two retained analytics
  dashboards. The two follow-ups should land together for these two files —
  migrating `BankAdminDashboard`/`CbyAdminDashboard` off `RequestStatus`
  naturally means migrating them off `ImportRequest`/`useDashboard.ts` onto an
  `EngineRequest`-shaped analytics contract in the same pass, rather than
  doing the enum swap now and the endpoint swap later.
- **Backend `EXECUTIVE_VOTE` rename** (Step 4) is a clean 4-file mechanical
  change plus one data-backfill step; the historical migration file is
  correctly out of scope.

---

## 6. Steps 2-3 — Canonical API-contract fields (commit `860b31c9`)

`EngineRequestResource::toArray()` gained three fields, all reachable from the
already-loaded, already-cast `currentStage` relation — no schema change, no
new query:

- **`runtime_status`** — alias of the existing `status` (same value, explicit
  name matching the M6 contract).
- **`current_stage.semantic_role`** — `$this->currentStage->semantic_role?->value`,
  nullable by design.
- **`final_outcome`** (request-level key) — `$this->currentStage->final_outcome?->value`,
  gated `when($this->currentStage->is_final)`; absent (not null) while active,
  since M6's model treats "not yet decided" as no-key rather than a false
  signal.

Verified live: an ACTIVE SUPPORT-stage request returns `runtime_status=ACTIVE`,
no `final_outcome` key; a `CLOSED_COMPLETED`-stage request returns
`final_outcome=COMPLETED`; a `CLOSED_REJECTED`-stage request returns
`final_outcome=REJECTED`.

## 7. Step 4 — EXECUTIVE_VOTE → EXECUTIVE_REVIEW rename (commit `283cd76c`)

Renamed both the case and its backing string together
(`EXECUTIVE_REVIEW = 'EXECUTIVE_REVIEW'`) across all 5 live references (4
non-test + 1 test fixture found via a fuller sweep than Step 1's original
grep) plus the WP-4 backfill migration, which — contrary to Step 1's initial
"do not touch, it's historical" guidance — turned out to be **live PHP that
re-evaluates the enum case on every fresh `migrate` run**, not a frozen
record; confirmed by execution that leaving it unrenamed breaks `migrate` on
a clean database. That guidance is corrected in §5 above. No live-DB row held
`semantic_role='EXECUTIVE_VOTE'` in this environment, so no row backfill was
needed here.

## 8. Step 5 — Active-stage roles vs terminal outcomes (confirmed, no change)

`StageSemanticRole` (8 cases: INITIAL_ENTRY, BANK_REVIEW, SUPPORT_REVIEW,
SWIFT, EXECUTIVE_REVIEW, FINANCE_RESERVE, FX_CONFIRMATION, FINAL) has no
terminal-outcome case. `FinalOutcome` (COMPLETED, REJECTED, CANCELLED,
ABANDONED) is a fully separate enum on the same `workflow_stages` table,
non-overlapping in vocabulary. Confirmed live: `CLOSED_COMPLETED`/
`CLOSED_REJECTED` have `semantic_role=NULL` and a set `final_outcome`; the 7
operational stages have a set `semantic_role` and `final_outcome=NULL`. The
two concepts never collide on the same stage.

## 9. Step 6 — semantic_role populated on V2 (commit `bfb2a7f1`)

Added a B4 step to `workflow:publish-import-financing-v2`: after B1-B3,
`applySemanticMetadata()` assigns `semantic_role` to the 7 operational stages
via `WorkflowDesignerService::updateStage()` — the same designer-lifecycle
path (`StoreWorkflowStageRequest`/`UpdateWorkflowStageRequest` already expose
this field to a human designer user) rather than a raw column write. The two
`CLOSED_*` terminal stages are intentionally left unset, per §8.

Root cause of why V2 previously had every stage's `semantic_role = NULL`,
despite the WP-4 backfill migration existing: `WorkflowDesignerService::cloneVersion()`
creates fresh `WorkflowStage` rows via `deepCopyVersionConfig()`, entirely
outside migration reach — a migration only updates pre-existing rows at the
moment it runs, not rows created later by application code. V2's stages
simply didn't exist yet when the backfill migration ran.

Live-verified after republishing: all 7 operational stages now carry the
correct `semantic_role`; validation still passes (0 errors); the D0
actionable-work/dashboard parity suite (`DashboardWorkApiTest`,
`DashboardFamilyCapabilityTest`, `UserActionableRequestQueryTest` — 21 tests,
139 assertions) and the resource-contract test are unaffected, confirming
`EngineRequestReadModel::bucket()`'s dual-path (`semantic_role IN (...) OR
code IN (...)`) resolves correctly now that `semantic_role` is populated, not
only in the previously-tested null case.

## 10. Step 7 — Compatibility-fallback formalization

No code change — the fallback already exists and is now proven correct in
**both** states (populated, from Step 6's live V2; and null, from the
dedicated compatibility test added alongside it). Formalizing its contract
per M6 §6's requirement ("temporary, isolated, clearly marked... with
measurable removal criteria"):

**What it is:** `EngineRequestReadModel::bucket()`
(`backend/app/Support/EngineRequestReadModel.php:62-89`) and
`SemanticResolver::stageForRole()`
(`backend/app/Services/Workflow/SemanticResolver.php:77-105`) both resolve a
stage by `semantic_role` first, falling back to a hardcoded stage-`code`
match (`SemanticRegistry::stageCodeAliases()`) when `semantic_role` is unset.

**Why it's isolated and clearly marked:** both call sites carry an explicit
docblock stating the preference order; the fallback is not a silent default —
`SemanticResolver::publishWarnings()` emits `SEMANTIC_DASHBOARD_ROLE_GAP` for
any dashboard-relevant role with zero matching stages, though this warning
path is not currently wired into `PublishImportFinancingV2Command` (it calls
`validate()`, not `warnings()`) — a gap worth closing before Step 8, not
required to unblock it.

**Removal criteria (all must hold, per M6 §6):**

1. Every workflow version reachable by an ACTIVE `EngineRequest` has
   `semantic_role` set on every stage the request could occupy. V2 now
   satisfies this for `IMPORT_FINANCING` (Step 6); any other workflow
   definition or a hand-built DRAFT version in the designer would not yet.
2. `SemanticRegistry::stageCodeAliases()` and `dashboardRoles()` have no
   remaining consumers relying on the `codes` half of the fallback (verify via
   the same grep pattern used in this inventory before removal).
3. A regression test exists proving the code-only fallback path is dead (no
   test currently exercises "stage has semantic_role=null AND is reached by
   an ACTIVE request" in production data — only in this session's synthetic
   compatibility test).
4. No archived/historical workflow version still holds ACTIVE requests that
   depend on the fallback resolving their stage.

**Not yet met** — do not remove. This satisfies Phase D's safety rule ("do
not remove compatibility fallbacks until their exit criteria are satisfied")
by making the criteria explicit and checkable rather than leaving them
implicit.

---

## 11. Step 8 — Frontend migration off `RequestStatus` (in progress)

**Live production defect found and fixed.** `BankAdminDashboard.vue`'s
recent-requests table was typed as `ImportRequest[]` but the backend has
never returned that shape — `useDashboard.ts`'s `bankAdminStats`/
`cbyadminStats` `recent_requests` field is `EngineRequestReadModel::resourceCollection()`
(flat `merchant_name`, no nested `merchant.name`/`supplier_name`). The
merchant column has been silently rendering "غير متاح" for every row in
production. Fixed: `useDashboard.ts` retyped to a new `DashboardQueueItem`
interface matching the real payload; `BankAdminDashboard.vue`'s table now
reads `merchant_name` directly and replaced the `StatusBadge`/
`getRequestProgress` (RequestStatus-only) cell with a runtime-status-colored
`stage_name` badge — live-verified via `playwright-cli` (Bank Admin dashboard
now shows real merchant names and correct Arabic stage labels, e.g.
"المراجعة المساندة", "مغلق — مرفوض", "عمليات الصرف").

**Removed as confirmed-dead** (zero live mount points/consumers, verified by
grep before each removal): `WorkflowProgress.vue` + test, `WorkflowTimeline.vue`
+ test, `StatusBadge.vue` (orphaned once `BankAdminDashboard.vue` stopped using
it), `utils/requestProgress.ts` (same), `SwiftUploadForm.vue`'s unused
`request: ImportRequest` prop, `AuditTimeline.vue`'s consumer status (component
itself has zero mount points — noted, not yet deleted), `types/models.ts`'s
`ImportRequest` interface (0 live consumers after the above) and its
now-dead-only `VotingDetail` interface (voting is out of V1), the dead
per-role `useDashboard.ts` interfaces (`DataEntryDashboardStats`,
`BankReviewerDashboardStats`, `SupportCommitteeDashboardStats`,
`SwiftOfficerDashboardStats`, `ExecutiveDashboardStats`,
`CommitteeDirectorDashboardStats`, `VotingQueueItem`, `DirectorQueueItem` — all
0 live consumers, remnants of the D0.6-deleted dashboards), 4 test files for
already-nonexistent pages/components (`SwiftUploadPage.test.ts`,
`VotingPanel.test.ts` — `VotingPanel.vue` does not exist —
`VotingRequestDetailPage.test.ts`, `RequestDetailClaimLogic.test.ts` — the
`[id]/index.vue` page it claimed to mirror is a pre-migration route that no
longer exists; the real `/workflows/instances/[id].vue` does not use any of
the tested logic), and the now-fully-orphaned `tests/unit/fixtures/request-data.ts`.
`dashboard.store.ts` simplified from a 7-branch queue-normalization block down
to the one still-relevant `recent_requests` branch.

**Verification:** typecheck error count returned to the exact pre-change
baseline (19 — confirmed via `git stash`/`stash pop` A-B comparison; every
count above 19 was a Step-8 regression, all fixed). All touched Vitest suites
green (`BankAdminDashboard.test.ts` 16, `ClaimBanners.test.ts` 11,
`dashboard.store.test.ts` 7 — rewritten to match the real 2-type
`DashboardStats` union). ESLint/Prettier clean on all touched files. Live
`playwright-cli` verification on `/dashboard` as Bank Admin (`admin@ybrd.com.ye`):
correct merchant names and stage labels, 0 new console errors (pre-existing
429 rate-limit noise from earlier session activity, unrelated).

**Major finding — the remaining `RequestStatus` surface is almost entirely
dead, not migratable.** After the above cleanup, exactly one production file
still executes `RequestStatus`-driven logic in a way reachable from a live
page: none. `constants/workflow.ts`'s entire enormous export surface
(`STATUS_COLORS`, `STATUS_ICONS`, `STATUS_LABELS`, `DATA_ENTRY_STATUS_LABELS`,
`STATUS_PROGRESS`, `ROLE_BUCKETS` — including the voting-era
`EXECUTIVE_VOTING_OPEN`/`my_vote`/`ready_to_close`/`is_tie` branches that are
explicitly out of V1 — `ROLE_FILTER_STATUSES`, `ROLE_ATTENTION_STATUSES`,
`CBY_BANK_FILTER_ROLES`, `getBusinessStatus()`, `getStatusProgress()`) now has
**zero live non-test consumers**. Its last production consumer,
`AuditTimeline.vue`, itself has zero mount points (grep-confirmed). The 8
`tests/unit/pages/requests/{role}-requests.test.ts` files exist solely to
unit-test `ROLE_BUCKETS`'s data shape for a `/requests` role-tab page family
that `frontend/CLAUDE.md` confirms no longer exists ("The legacy `/requests`...
routes no longer exist").

## 12. Step 9 — Dead RequestStatus surface removal (dependency-proof pass + execution)

**Dependency-proof pass — all 7 items confirmed clean before deletion:**

1. **No registered route resolves to `/requests`.** Route enumeration
   (`find frontend/app/pages -name "*.vue"`) and `constants/workflow.ts`'s own
   `PROTECTED_ROUTES`/`ROUTE_ROLE_MAP` list 33 live pages — none under
   `requests/`. `frontend/CLAUDE.md` independently confirms the family was
   retired.
2. **No dynamic import/lazy registration/plugin/story/helper mounts
   `AuditTimeline.vue`.** Grep for the component name outside its own file
   and tests: zero hits.
3. **No production file imports the dead surface.** `RequestStatus`: 5
   production files, all either the definition (`enums.ts`), a downstream
   type alias with 0 live readers (`models.ts`'s `PaginatedResponse.status_totals`,
   confirmed separately), or comment-only mentions (`useDashboard.ts`,
   `BankAdminDashboard.vue`). `ROLE_BUCKETS`: 1 file (its own definition in
   `constants/workflow.ts`) before removal. `STATUS_LABELS`/
   `getBusinessStatus`: 1 real consumer, `AuditTimeline.vue` — itself
   confirmed dead by item 2. Voting status mappings
   (`EXECUTIVE_VOTING_OPEN/CLOSED`, `VotingSessionStatus`): 0 production
   consumers outside the enum definitions.
4. **No backend API contract returns the 22-value vocabulary as
   authoritative.** No `RequestStatus` class/enum exists in
   `backend/app/**/*.php` (the one substring match was `EngineRequestStatus`,
   the unrelated 5-value runtime-status class). `EngineRequestResource`
   returns `status`/`runtime_status` (5-value), `current_stage.semantic_role`,
   `final_outcome` — never the 22-value vocabulary.
5. **No notification/export/report/history view depends on these
   constants.** `useReports.ts`, `useTableExport.ts`, `reports.store.ts`,
   `DataTableBulkExport.vue`, `DataTableExport.vue`, and everything under
   `pages/reports/` — grep-checked individually, zero imports from
   `constants/workflow`'s dead surface.
6. **The 8 role-request tests reference only the removed page family or
   dead bucket logic.** Verified per-file: every one of the 281 total `it()`
   blocks imports `RequestStatus` + `ROLE_BUCKETS`/`CBY_BANK_FILTER_ROLES`
   directly, asserts on that data shape, and never mounts a component or
   imports a live page.
7. **Equivalent current behavior mapped.** See table below.

**One real stop-and-check triggered, resolved without migration.**
`story-12-3-role-gates.test.ts`'s "FX confirmation tab" / "SWIFT two-pill
states" describe blocks share topic names with the live
`EngineFxConfirmationPanel.vue`. Verified: the test file defines all its
logic (`directorBanner`, `fxFlowState`, `swiftPills`, `swiftUploadAccess`,
etc.) inline, never importing from any real component; grepped
`EngineFxConfirmationPanel.vue` for those exact function/type names — zero
matches. The live component already has its own dedicated coverage
(`EngineFxConfirmationPanel.test.ts`, `useEngineFxConfirmation.test.ts`).
Coincidental topic overlap, not shared implementation — cleared for removal.

**Old test → current coverage mapping:**

| Removed | Covered unreachable feature, or replacement |
| ------- | -------------------------------------------- |
| `AuditTimeline.test.ts` | Unreachable — component had 0 mount points. Live history rendering is the request-detail "السجل" tab (stage-transition list from `workflow_history`), unaffected, live-verified below. |
| `CorrectionBanner.test.ts` | Unreachable — `CorrectionBanner.vue` had 0 external consumers. |
| `LockedBanner.test.ts` | Unreachable — `LockedBanner.vue` had 0 external consumers. |
| `story-12-3-role-gates.test.ts` | Unreachable pre-engine prototype logic (self-contained, never wired to a real component). FX-tab concern has live equivalent coverage: `EngineFxConfirmationPanel.test.ts` + `useEngineFxConfirmation.test.ts`. |
| `workflow-buckets.test.ts`, `workflow-status.test.ts` | Unreachable — tested `ROLE_BUCKETS`/status-label data shape directly, no component/page consumer. |
| `{role}-requests.test.ts` ×8 (bank-admin, bank-reviewer, cby-admin, committee-director, data-entry, executive-member, support-committee, swift-officer) | Unreachable — tested `ROLE_BUCKETS` for a `/requests` page family with no route. Current per-role actionable-work behavior is covered by `UserActionableRequestQueryTest`, `DashboardWorkApiTest`, the `/my-queue` parity assertions inside both, `MyWorkDashboard.test.ts`, and `useNavBadges.test.ts` (nav badge parity) — the dynamic, capability-driven replacement for the static per-role bucket model these tests exercised. |
| `WorkflowProgress.test.ts`, `WorkflowTimelineSwiftMerge.test.ts` (removed in the Step 8 commit) | Unreachable — components had 0 mount points. |

**Files removed (Step 9, this commit):** `components/workflow/AuditTimeline.vue`,
`components/banners/CorrectionBanner.vue`, `components/banners/LockedBanner.vue`,
their 3 test files, `tests/unit/pages/story-12-3-role-gates.test.ts`,
`tests/unit/constants/{workflow-buckets,workflow-status}.test.ts`, and the 8
`tests/unit/pages/requests/{role}-requests.test.ts` files — 17 files removed. From
`constants/workflow.ts` (1037 → 341 lines): `STATUS_COLORS`, `STATUS_ICONS`,
`BusinessStatus`, `getBusinessStatus()`, `DATA_ENTRY_REPRESENTATIVE_STATUS`,
`ROLE_FILTER_STATUSES`, `DATA_ENTRY_STATUS_LABELS`, `STATUS_LABELS`,
`SWIFT_DISPLAY_GROUP`, `STATUS_PROGRESS`, `getStatusProgress()`, `StageBucket`,
`ROLE_BUCKETS` (including every `EXECUTIVE_VOTING_OPEN/CLOSED`, `my_vote`,
`ready_to_close`, `is_tie` voting-era branch), `ROLE_ATTENTION_STATUSES`,
`CBY_BANK_FILTER_ROLES`. The `RequestStatus` import itself is removed from
`constants/workflow.ts`.

**Kept, per explicit instruction:** `RequestStatus`/`VotingSessionStatus`/
`VoteType` enum definitions in `types/enums.ts` (full removal is Step 10,
separately gated on "all consumers migrated" — the definition itself still
has 2 non-dead references: its own test and `PaginatedResponse.status_totals`,
which has 0 live readers but wasn't in this turn's approved deletion scope);
`types/enums.test.ts`'s `RequestStatus` block (tests the still-present enum
shape); all `UserRole`-only exports in `constants/workflow.ts` (`ROLE_LABELS`,
`ROLE_QUEUE_TITLES`, `BANK_ROLES`, `CBY_ROLES`, `CBY_OPERATIONAL_ROLES`,
`NAV_ITEMS`, `PROTECTED_ROUTES`, `ROUTE_ROLE_MAP`, etc.) and the live
`NOT_ELIGIBLE_*` labels (7 of 11 have live consumers, decoupled from
`RequestStatus`); `runtime_status`, `current_stage`, `semantic_role`,
`final_outcome`, current workflow-history rendering, designer-driven stage
labels, and the active `semantic_role`-or-`code` compatibility fallback —
none touched.

**Verification (all against the approved checklist):**

- Typecheck: **19 errors before, 19 after** — exact baseline maintained
  (confirmed via `git stash`/`stash pop` A-B comparison both before and after
  this pass).
- Frontend suites (`pages`, `components`, `constants`, `types` — 61 test
  files, 698 tests before removal): 8 files / 11 tests / 5 unhandled-rejection
  errors failing, **all confirmed pre-existing** via the same stash A-B
  comparison (`CommandPalette.test.ts`, `WorkflowPublishPanel.test.ts`,
  `GovernanceRolesPage.test.ts`, `LoginPage.test.ts`, `OrganizationsPage.test.ts`,
  `ReferenceDataPage.test.ts`, `TeamsPage.test.ts`, `notifications.page.test.ts`
  — none touch `RequestStatus`/`ROLE_BUCKETS`/any removed file; the
  `ReferenceDataPage.test.ts` errors are an unrelated `extractApiErrorMessage`
  reference bug). Every test that does touch the removed surface passes or
  was itself removed as part of this pass.
- Backend: `tests/Feature/Dashboard/` 27/27 green (200 assertions);
  `tests/Feature/Engine/` 291/291 green (1038 assertions).
- ESLint/Prettier: clean on `constants/workflow.ts`.
- Production build (`pnpm run build`): succeeds, no dead-import errors.
- Route enumeration: 33 pages, no `/requests` family, confirmed independently
  of the dependency-proof pass's item 1.
- Final repo-wide search: `ROLE_BUCKETS` — 0 hits anywhere. Deleted component
  names (`AuditTimeline`, `CorrectionBanner`, `LockedBanner`,
  `WorkflowProgress`, `WorkflowTimeline`, `StatusBadge`) — 0 hits anywhere.
  `EXECUTIVE_VOTING_OPEN/CLOSED` — 0 hits outside the still-present enum
  definition. `RequestStatus` — 5 production files, all confirmed dead-weight
  (definition, 1 unused type field, 2 comments) in item 3 above.
- Live `playwright-cli` verification: System Admin
  (`admin@cby.gov.ye`) → System Admin dashboard renders ("مؤشرات أداء النظام"),
  0 console errors. Bank Admin (`admin@ybrd.com.ye`) → merchant column still
  shows real names post-cleanup (unaffected by this pass, re-confirmed).
  Director (`director@cby.gov.ye`) and SWIFT Officer (`swift@ybrd.com.ye`) →
  both route to MyWorkDashboard ("طابور مهامي"). Request-detail history
  ("السجل" tab, request A016 as System Admin) → renders the full stage-
  transition list (إنشاء الطلب → المراجعة الداخلية → المراجعة المساندة →
  القرار التنفيذي → مغلق — مرفوض) with actor names, timestamps, and action
  codes, entirely independent of the deleted `AuditTimeline.vue`. Zero
  non-rate-limit console errors across all navigations.

No runtime consumer appeared during the dependency-proof pass or the
post-deletion verification. Nothing required migration instead of deletion.
