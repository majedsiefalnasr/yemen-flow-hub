# WP-2 — Outcome Semantics

**Status:** Draft for review (Phase 6)
**Source of authority:** `2026-07-05-feature-review-notes.md`
**Traceability:** D2-N1 (final outcomes + REJECTED), D1-N2 (deliberate draft creation + abandon flow), D22-N4 (capacity eligibility), D18-N5 / D21-N6 (report + dashboard outcome alignment)
**Dependencies:** WP-0 green. Independent of WP-1.
**Enables:** WP-3 (validator pack builds on the outcome rule), WP-4 (metadata buckets), true rejected/cancelled reporting everywhere.
**Overall risk:** medium — expands the runtime status vocabulary and touches `EngineTransitionService::execute` (do-not-touch list: this spec **is** the explicit approved authority for that change).

## Change classification

| Item | Kind |
|------|------|
| O-1 final-outcome field + designer + backfill | Approved functional + migration (D2-N1) |
| O-2 runtime status derivation | Approved functional (D2-N1) — explicit approved touch of `execute()` |
| O-3 deliberate draft creation | Approved functional (D1-N2) |
| O-4 abandon flow | Approved functional (D1-N2) |
| O-5 capacity eligibility | Approved functional (D22-N4) — constant change only, locking protocol untouched |
| O-6 reports/dashboards/read-model alignment | Approved functional (D18-N5, D21-N6) |

**Explicitly out of scope:** metadata dashboard buckets (WP-4 / D21-N1); status-filter whitelist validation (WP-12 / D18-N6); claim-enforcement changes on drafts (WP-5 — O-4 applies *current* draft rules and notes the WP-5 alignment); invoice normalization (WP-7 / R5s2).

---

## O-1 — Final-stage outcome field

**Current:** `workflow_stages.is_final` boolean only; engine writes `CLOSED` for every final stage; `REJECTED` queried by ~8 surfaces but never written.
**Required:**
- `App\Enums\FinalOutcome` backed string enum: `COMPLETED`, `REJECTED`, `CANCELLED`, `ABANDONED`. (All four now — D22-N4 requires the freeing statuses; designer UI initially surfaces COMPLETED/REJECTED/CANCELLED; ABANDONED is reachable via O-4 and selectable for explicitly-modeled abandon stages.)
- `workflow_stages.final_outcome` nullable enum column; semantically **required when `is_final = true`**.
- Designer stage editor (`WorkflowStageEditor` + stage requests): outcome select shown/required when final; non-final stages must have it null (validation both directions). Clone copies it (extend `deepCopyVersionConfig`).
- **Publish rule (ships here, not WP-3 — same pattern as WP-1 C-5; coordination note for WP-3):** `FINAL_STAGE_NO_OUTCOME` — publish blocked while any final stage lacks an outcome. Never derive outcome from stage name/code (explicit D2-N1 constraint).
- **Backfill migration for existing versions (incl. PUBLISHED — migration-level, PR-reviewed, logged like WP-1 C-1):** mapping proposal: final stages entered via `REJECT`-kind actions → `REJECTED`; all remaining final stages → `COMPLETED`; ambiguous rows resolved manually in the reviewed mapping list. Published-version immutability governs *designer* edits; a reviewed data migration is the sanctioned path.

## O-2 — Runtime status derivation

**Current:** `EngineTransitionService::execute()` line ~80: `$newStatus = $transition->toStage->is_final ? 'CLOSED' : 'ACTIVE';`
**Required:**
- Final stage → status from `final_outcome`: COMPLETED→`CLOSED`, REJECTED→`REJECTED`, CANCELLED→`CANCELLED`, ABANDONED→`ABANDONED`. Non-final → `ACTIVE`, unchanged.
- **Defensive fallback:** final stage with null outcome at runtime (theoretically impossible post-backfill+publish-rule) → `CLOSED` (current behavior) + high-severity log — never a hard failure of the transition.
- Runtime status vocabulary becomes: `ACTIVE, CLOSED, REJECTED, CANCELLED, ABANDONED`. `isActive()` unchanged; every non-ACTIVE status is terminal — `REQUEST_CLOSED` 403 and the `execute` policy already key on `isActive()`, so terminal protection extends automatically.
- `EngineRequest::isClosed()` updated to include the new terminal statuses (single terminal-status list constant shared with O-5/O-6 consumers).
- **Do-not-touch acknowledgment:** this is a minimal, spec-authorized edit of the status assignment inside `execute()`; the guard sequence, locking, hooks, and audit flow are untouched.

## O-3 — Deliberate draft creation

**Current:** `/workflows/new` auto-creates an empty draft on mount when exactly one published workflow exists; picker cancel leaves an orphan.
**Required:**
- No request creation on mount, ever. Single-workflow case renders the same picker with one card; the instance is created only on the explicit «بدء الطلب» click. Multi-workflow behavior unchanged. Cancel → navigate away, nothing created.
- Wizard behavior after creation unchanged.
**Impacted frontend tests:** any pinning of the auto-start path updated in the same commit (behavior change is the approved point).

## O-4 — Abandon flow (drafts)

**Current:** no abandon path; empty ACTIVE drafts live forever, hold financing capacity, pollute queues.
**Required:**
- **Service:** `EngineTransitionService::abandonDraft(EngineRequest, int $version, User $actor)` — same guard family as `saveDraft`: row lock, `isActive()` else `REQUEST_CLOSED`, optimistic `version` else `REQUEST_STALE`, **current stage must be `is_initial`** else 422 `ABANDON_NOT_AVAILABLE`, actor holds EXECUTE on the stage, *current* draft claim rules apply (WP-5 will tighten claim enforcement; alignment note). Effect: `status = 'ABANDONED'`, `version+1`, claim fields cleared, `workflow_history` entry (`action_code: 'ABANDON'`, from=current stage, to=null), audit `REQUEST_ABANDONED` (new `AuditAction`), no notification fan-out.
- **Endpoint:** `POST /v1/engine-requests/{id}/abandon` (body: `version`). 200 + resource.
- **UI:** «إلغاء المسودة» destructive action in the wizard (AlertDialog confirm, per product rule) and offered from the unsaved-changes leave dialog. Post-abandon → navigate to `/workflows` with toast.
- **Scope rule:** abandon = initial-stage drafts only (the D1-N2 problem). Cancelling mid-workflow is *modeled by designers* as ordinary transitions into CANCELLED-outcome final stages — no special engine op (O-1/O-2 make that expressible).
- Terminal behavior: abandoned requests excluded from `myQueue` (`active()` scope), visible in "all" under a status filter, immutable (`REQUEST_CLOSED` on any mutation).

## O-5 — Financing capacity eligibility

**Current:** `EngineFinancingLedger::NOT_ELIGIBLE_STATUSES = ['REJECTED']` (never matched until now).
**Required:** `['REJECTED', 'CANCELLED', 'ABANDONED']`, sourced from the shared terminal-status constant (one eligibility truth for ledger + reports per D22-N4). Consuming statuses stay: ACTIVE (incl. drafts — interim behavior documented per D22-N4; drafts now at least abandonable) + CLOSED. **Locking protocol untouched** (do-not-touch list; constant-only change under this spec's authority).

## O-6 — Reports / dashboards / read-model alignment

**Current:** rejected counts across V1 reports, legacy reports, dashboards, compliance read as `status='REJECTED'` — correct definitions, permanently-zero data. New statuses would be invisible to filters.
**Required (counting correctness only — no new report features, no payload redesign):**
- Engine list `ALLOWED_STATUSES`: + `CANCELLED`, `ABANDONED` (index filter accepts them).
- V1 `ReportController::summary`: `rejected` now real; add additive `cancelled` and `abandoned` counts (additive fields only).
- Trend/`requestsOverTime` and other rejected-series: unchanged code, now-correct data.
- `EngineRequestReadModel` STATUS_BUCKETS: `rejected` bucket unchanged; terminal-status handling uses the shared constant where buckets test "closed-ness".
- Frontend `workflows/index.vue`: status labels + filter options + badge classes for `CANCELLED` (ملغي) and `ABANDONED` (متروك) — locked-gray token family for both.
- **Dashboard payloads unchanged in shape** — T-4 snapshots stay identical on existing fixtures (fixtures contain no rejected/cancelled data). Deeper dashboard outcome displays ride WP-4/WP-12.

---

## Business rules (consolidated)

1. Every final stage declares its outcome; publish enforces it; runtime never infers outcome from names/codes.
2. Status vocabulary: `ACTIVE / CLOSED / REJECTED / CANCELLED / ABANDONED`; one shared terminal-status + capacity-eligibility source.
3. Drafts are created deliberately and abandonable deliberately; abandonment is initial-stage-only, audited, history-logged, capacity-freeing.
4. Mid-workflow cancellation is workflow-designed (CANCELLED-outcome final stages), not an engine special case.

## Error cases

| Case | Response |
|------|----------|
| Publish with outcome-less final stage | 422 `WORKFLOW_VALIDATION_FAILED` + `FINAL_STAGE_NO_OUTCOME` |
| Outcome set on non-final stage / missing on final (authoring) | 422 field error |
| Abandon on non-initial stage | 422 `ABANDON_NOT_AVAILABLE` |
| Abandon on terminal request | 403 `REQUEST_CLOSED` |
| Abandon stale version | 409 `REQUEST_STALE` |
| Abandon without EXECUTE | 403 `STAGE_EXECUTION_FORBIDDEN` |

## Acceptance criteria

1. Outcome derivation matrix green: transition into each of the four outcomes yields the mapped status; non-final → ACTIVE; null-outcome fallback logs + CLOSED.
2. Publish blocked on outcome-less final stages; backfill migration leaves zero such stages in non-archived versions; mapping logged + PR-reviewed.
3. `/workflows/new` creates nothing until explicit start (frontend test updated); cancel creates nothing.
4. Abandon endpoint passes its guard matrix; abandoned request frees financing capacity (ledger test) and disappears from `myQueue`.
5. Reports/summary count all five statuses correctly; engine list filter accepts the new statuses; T-4 dashboard snapshots unchanged.
6. All WP-0 suites green; no T-1 claim pins flip (abandon clears claims via its own path, asserted separately).

## Test cases

- **Unit:** FinalOutcome→status map incl. fallback; terminal-status constant consumers (isClosed, ledger eligibility).
- **Feature:** publish rule; stage authoring validation (both directions); abandon guard matrix + history/audit rows + claim clearing; ledger capacity freed after abandon/reject; summary counts per status; list filter acceptance.
- **Migration:** seeded final stages (plain FINAL, reject-action-entered) → expected outcomes.
- **Frontend unit:** picker no-auto-create; abandon confirm dialog wiring; new status labels.
- **Regression:** full create→submit→…→final flow per outcome type.

## Manual verification steps

1. Designer: mark a stage final → outcome select required; publish without it → blocked with named error.
2. Run a request into a REJECTED-outcome final stage → list shows مرفوض; summary rejected count increments.
3. `/workflows/new` → nothing in DB until «بدء الطلب»; cancel → nothing.
4. Start a draft, abandon from the wizard → confirm dialog → request terminal, gone from طابوري, financing utilization for its invoice drops.
5. Attempt any action on the abandoned request → `REQUEST_CLOSED`.

## Rollback considerations

O-1 column is additive. O-2..O-6 revert independently, **but**: once any request carries a new status (`REJECTED/CANCELLED/ABANDONED`), reverting O-2/O-6 strands unknown statuses in old code paths — rollback window effectively closes at first real outcome write. Mitigation: ship O-1+O-2+O-6 in one release; O-3/O-4 UI can trail. Document in release notes.

## Open questions

None. Two derivations to note in review (decided inline from approved notes, flag if you want them changed): (a) abandon is initial-stage-only, with mid-workflow cancellation left to designer-modeled CANCELLED stages; (b) `ABANDONED` is included in the designer enum but primarily written by the abandon flow.
