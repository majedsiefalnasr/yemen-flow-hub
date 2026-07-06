# WP-5 — Claims: Stage-Scoped + Server-Side Parity

**Status:** Draft for review (Phase 6)
**Source of authority:** `2026-07-05-feature-review-notes.md`
**Traceability:** D9-N1 (release on transition), D9-N2 (draft enforcement), D9-N3 (claim-loss UX), D9-N4 (cache mirror), D9-N5 (heartbeat safety), D9-N6 (live-state note), D10-N1 (document claim gating), D3-N6 (queue claim badge). R10 claim-session composable extraction rides here.
**Dependencies:** WP-0 (T-1 claim lifecycle races — the characterization oracle; two of its cases **flip** here) + WP-R (R2 claim controller split). WP-12 owns the runtime UI parity for confirmation/comment feedback.
**Enables:** WP-12 (claim-loss UI is referenced by runtime UX).
**Overall risk:** medium — core engine + frontend claim flows. Controlled by T-1: every behavior change flips a pinned case explicitly.

## Change classification

| Item | Kind |
|------|------|
| CL-1 release claim on transition | Approved functional (D9-N1) — explicit touch of `execute()` |
| CL-2 draft claim enforcement | Approved functional (D9-N2) |
| CL-3 document upload/delete claim enforcement | Approved functional (D10-N1) |
| CL-4 heartbeat safety | Approved functional (D9-N5) |
| CL-5 claim-loss UX | Approved functional (D9-N3) |
| CL-6 queue claim badge | Approved functional (D3-N6) |
| CL-7 cache mirror removal | Migration/cleanup (D9-N4) |
| CL-8 live claim-state note | Future enhancement (D9-N6) — documented, not built |

**Explicitly out of scope:** server-side lists/KPI redesign (WP-12/D3-N1); confirmation dialogs / comment feedback (WP-12); broader two-layer visibility (WP-7); notification audience rework (WP-7). Frontend R10 composable extraction is bundled here because CL-5 needs it.

---

## CL-1 — Release claim on transition (D9-N1)

**Current:** `EngineTransitionService::execute()` never clears claim fields after a successful transition; the holder's heartbeat keeps extending the claim into the next stage → next-stage executor lockout.
**Required:** on successful transition (status move complete, before hooks fire is fine — claim release is post-mutate), if the request was claimed and `fromStage.requires_claim`, **release the claim inside the same transaction**, audit `CLAIM_RELEASED` with reason `stage_changed`, actor = the transition performer. Next stage starts unclaimed. Heartbeat must stop client-side (CL-5 / R10).
**Do-not-touch acknowledgment:** minimal, spec-authorized edit inside `execute()` — adds a release step after the existing mutate, before the hook/notification block. Guard sequence, locking, hooks, audit flow otherwise untouched. The release reuses `EngineClaimService::releaseExpired`-style clearing or a new `releaseForStageChange` method (no holder-identity check — system-initiated within the actor's transaction).
**Edge cases:** transition on a non-`requires_claim` from-stage → no-op (nothing to release). Abandon (WP-2 O-4) already clears claim via its own path — consistent.
**T-1 pin flips:** case 9 (claim carry-over) now asserts claim cleared after transition.

## CL-2 — Draft claim enforcement (D9-N2)

**Current:** `EngineTransitionService::saveDraft()` checks active/version/permission but **not** claim — wizard claim gating is frontend-only (commit e30dc286).
**Required:** in `saveDraft()`, after the permission check, if `currentStage.requires_claim` → require `$request->claimed_by === $user->id && $request->isClaimed()` else 403 `CLAIM_NOT_HELD` (same guard family as `execute()`).
**T-1 pin flips:** case 10 (non-holder draft save currently succeeds) now asserts 403.
**Edge cases:** a draft whose claim expired between page-load and save → 403 → CL-5 UX surfaces claim-loss. Non-`requires_claim` stages unaffected.

## CL-3 — Document upload/delete claim enforcement (D10-N1)

**Current:** `EngineRequestDocumentController::uploadDocument` / `deleteDocument` check `execute` policy but not claim.
**Required:** on `requires_claim` stages, upload + delete require the caller to hold the claim (same `CLAIM_NOT_HELD` 403). One shared guard helper (e.g. `ensureClaimHeld($request, $user)` on the controller or a small action) used by CL-2 and CL-3 — DRY.
**Acceptance:** non-holder document write on a claim stage → 403; holder → succeeds.

## CL-4 — Heartbeat safety (D9-N5)

**Current:** `EngineClaimService::heartbeat()` has no row lock, no active check, no expiry/stage re-check; race with sweeper can leave `claimed_by = null` with dangling `claim_expires_at`; extends claims on requests that have moved stages (feeds CL-1's pre-fix symptom).
**Required:**
- Row lock (`lockForUpdate`) inside a transaction.
- Verify: request still `isActive()`; `claimed_by === $user->id`; `claim_expires_at` not in the past (claim not already swept/expired); **request has not moved stages since claim** (compare stored `current_stage_id` / a claim-scoped token — see CL-1's stage-scoping; simplest: store `claim_stage_id` on the claim, reject heartbeat if `current_stage_id !== claim_stage_id`).
- Any failure → 403 `CLAIM_NOT_HELD` (do not extend).
- Success → extend TTL, mirror cache (until CL-7 removes it).
**T-1 additions:** heartbeat-after-sweep → 403; heartbeat-after-stage-change → 403; concurrent heartbeat race → exactly one extends.

## CL-5 — Claim-loss UX (D9-N3) + R10 composable

**Current:** `useEngineClaim` calls `void heartbeat()` — rejection unobserved; documented claim-loss redirect+notification (AGENTS/frontend CLAUDE.md) unimplemented.
**Required:**
- `useEngineClaim` becomes a proper claim-session composable (R10 extraction target): heartbeat/claim/release failures populate a reactive `claimLost` state with the error code; heartbeat stops on loss.
- On `CLAIM_NOT_HELD` from **heartbeat**, **draft save**, or **transition/action**: stop heartbeat, show a clear toast/banner ("claim lost or expired"), switch the page to read-only (disable save + action buttons, hide the claim button), and offer "return to queue" or "reclaim" (when the user may reclaim). Applies across `[id].vue` view page and `EngineRequestWizard`.
- Backend remains the authority (CL-2/CL-3); frontend is recovery UX.
**Acceptance:** a user whose claim is swept mid-edit gets a clear read-only state + path forward, never a silent failed save.

## CL-6 — Queue claim badge (D3-N6)

**Current:** `myQueue` returns claimable/executable requests with no claim-state distinction; users open detail to discover another user holds the claim.
**Required:** `myQueue` response carries `claimed_by_user` (name) + `is_claimed_by_other` per row (already partly in the resource); frontend `workflows/index.vue` queue view: requests claimed by another user either hidden from "My Queue" or shown with a «مطالب بواسطة مستخدم آخر» badge + disabled action state. Supervisor "all" view keeps claimed rows for oversight (per D3-N6).
**Decision point (flagged):** hide-from-queue vs badge-and-disable — recommend **badge-and-disable** (preserves visibility of who's working on what; hiding can feel like work disappeared). Confirm.

## CL-7 — Cache mirror removal (D9-N4)

**Current:** `engine_claim:{id}` cache key written by claim/heartbeat/release/releaseExpired — zero readers anywhere (write-only).
**Required:** remove all `Cache::put/forget('engine_claim:...')` calls from `EngineClaimService`; DB row is the sole source of truth. Update `AGENTS.md` / `backend/CLAUDE.md` text describing the mirror as load-bearing (it isn't).
**Risk:** minimal — no reader exists. **Rollback:** trivial.
**Acceptance:** grep finds no `engine_claim:` key usage; all claim behavior unchanged (T-1 green).

## CL-8 — Live claim-state note (D9-N6)

**Documented, not built.** Other viewers eventually see claim changes without reload (polling/refresh/real-time). Backend enforcement is already correct; this is UX. Park as future enhancement, referenced from WP-12 runtime UX.

---

## Business rules (consolidated)

1. Claims are scoped to the request's current stage; reset on every stage entry; never carry across handoffs.
2. Claim ownership is enforced server-side on drafts, documents, and transitions — frontend gating is UX only.
3. Heartbeat extends only a valid, current-stage, unexpired claim held by the caller.
4. Claim loss is surfaced clearly and switches the surface to read-only with a recovery path.
5. DB row is the single claim source of truth; no cache mirror.

## Error cases

| Case | Response |
|------|----------|
| Draft save / document write / transition on `requires_claim` stage by non-holder | 403 `CLAIM_NOT_HELD` |
| Heartbeat on expired / swept / stage-changed / wrong-holder claim | 403 `CLAIM_NOT_HELD` |
| Heartbeat on inactive request | 403 `REQUEST_CLOSED` |

## Acceptance criteria

1. T-1 cases 9 and 10 flip green (claim cleared after transition; non-holder draft save 403) — explicit test-first evidence.
2. Document upload/delete on a claim stage by non-holder → 403; by holder → succeeds.
3. Heartbeat safety matrix green (swept / stage-changed / wrong-holder / concurrent).
4. Claim-loss UX: heartbeat/draft/transition `CLAIM_NOT_HELD` → read-only + recovery path on view page and wizard.
5. Queue rows distinguish claimed-by-other; badge + disabled action (or hidden — per CL-6 decision).
6. `engine_claim:` cache key gone; docs updated; all T-1 cases green.
7. WP-2 abandon path still clears claim (no regression).

## Test cases

- **Feature (engine):** transition clears claim + audit reason `stage_changed`; draft/document/transition claim matrix; heartbeat safety matrix; abandon-then-claim-consistency.
- **Unit (`EngineClaimService`):** release-for-stage-change; heartbeat guard logic; cache-mirror removal (no Cache calls).
- **Frontend unit:** `useEngineClaim` claim-loss state transitions; queue badge rendering; wizard read-only-on-loss.
- **Regression:** full claim → heartbeat → transition flow unchanged for a legitimate holder (T-1 happy paths).

## Manual verification steps

1. Two users: A claims, B opens queue → B sees «claimed by A» badge + disabled actions; B cannot save draft / upload doc / transition (403).
2. A transitions the request → claim released; B (next-stage executor) can claim fresh; A's heartbeat stopped.
3. A's claim swept (wait 15 min or force sweep) → A's next heartbeat/save → read-only state + "return to queue".
4. Heartbeat after a stage change → 403, no extension.

## Rollback considerations

CL-1/CL-2/CL-3/CL-4 are independent commits reverting individually. CL-7 (cache removal) reverts trivially. CL-5/CL-6 are frontend-only. Once CL-1 ships, any client still heartbeating post-transition gets 403 — benign (CL-5 handles it; clients without CL-5 just see failed heartbeats, no data impact).

## Open questions

1. **CL-6 queue policy:** hide claimed-by-other from "My Queue" or badge-and-disable? Recommend badge-and-disable.
2. **Claim-stage scoping field:** confirm adding `claim_stage_id` (or comparing `current_stage_id` at heartbeat) is acceptable — the cleanest way to make heartbeat stage-aware per CL-4. Minor additive column or reuse `current_stage_id` snapshot.
