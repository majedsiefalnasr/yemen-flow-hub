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

**Proceeding to Step 8** (migrate badges/labels/timelines/filters/dashboards/
notifications/tests off the 22-value `RequestStatus` enum — the frontend
migration surface identified in §2 above) per the approved continuous-
execution instruction.
