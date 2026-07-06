# WP-3 — Designer Validation Pack

**Status:** Draft for review (Phase 6)
**Source of authority:** `2026-07-05-feature-review-notes.md`
**Traceability:** Validator rules — D1-N1, D4-N1, D4-N2+D7-N2, D5-N1, D5-N2, D5-N3, D2-N8, D6-N4, D6-N5, D6-N6, D6-N10. Designer lifecycle — D4-N3, D4-N4, D4-N8, D4-N9, D7-N3, D7-N5, D5-N4, D5-N5.
**Dependencies:** WP-2 (final-outcome rule + status vocabulary) + WP-0 (T-5 partial-update test). WP-1 helpful (C-2 org guard) but not blocking — the classification-dependent rules here key on the `final_outcome` field and reachability math, not classification.
**Enables:** WP-4 (semantic metadata, shares validator infra), WP-9 (lifecycle guards reuse the effective-executor + reachability building blocks).
**Overall risk:** low-medium — additive validation; publish becomes stricter. Migration concern: existing DRAFT versions that would now fail validation must publish-or-fix cleanly; archived/published versions are immutable and unaffected.

## Change classification

All items in this package: **approved functional changes** (publish-time + authoring-time validation rules and lifecycle gates). Two items (D4-N3 delete policy, D4-N4 definition editing) are also governance changes carrying small migration considerations.

**Explicitly out of scope:** semantic-tag/mapping mechanism (WP-4); FX-PDF / financing / dashboard bucket validation that depends on the mapping mechanism (WP-4 builds on these validators); broader server-side enforcement parity (WP-5/WP-7/WP-8); bank/role lifecycle guards (WP-9/WP-10); the two rules already shipped in WP-1 (initial-stage banking gate) and WP-2 (final-stage outcome).

---

## Part A — Publish-time validator rules (`WorkflowVersionValidator::validate()`)

Validator returns `[{code, target, message}]`; empty ⇒ publishable. Re-run server-side at publish (already enforced). All rules below add to the existing set.

### V-1 — Deterministic submit transition from initial stage (D1-N1)

**Rule:** if the initial stage has more than one outgoing transition, exactly one must carry an explicit `is_default_submit` flag; publish blocked otherwise with `INITIAL_SUBMIT_AMBIGUOUS`.
**Inputs:** new `workflow_transitions.is_default_submit` boolean (nullable; default false). Designer UI exposes the flag on initial-stage transitions only; auto-applies it when the initial stage has exactly one transition.
**Edge cases:** single outgoing transition on initial stage — flag optional/irrelevant, allowed. Flag set on a non-initial-stage transition — authoring validation clears/rejects it (field error).
**Why here not WP-4:** this is a plain boolean on transitions, not a semantic mapping; the wizard's first-edge submission (D1-N1 root cause) is fixed by the flag read at runtime, which rides WP-12 (runtime UX). Validator is the publish half.
**Acceptance:** a workflow with two submit-capable initial transitions and no flag fails publish; with the flag set, passes.

### V-2 — Graph reachability (D4-N1)

**Rule:** forward BFS/DFS from the initial stage (ignoring self-loop-only edges):
- Any stage **not reachable** from initial → `STAGE_UNREACHABLE`.
- No **final stage reachable** from initial → `NO_REACHABLE_FINAL`.
- Non-final stage whose only outgoing edges are self-loops → `STAGE_ONLY_SELF_LOOP` (already exists; keep).
**Algorithm:** pure over the in-memory stages+transitions collection the validator already loads; no extra queries.
**Acceptance:** island/isolated-stage workflows blocked; workflows ending only in unreachable finals blocked; valid DAGs pass.

### V-3 — Effective executor per non-final stage (D4-N2 + D7-N2, deepening)

**Current:** `STAGE_NO_EXECUTOR` accepts role/team/org grants without verifying any **active user** matches; only `user_id` grants checked against active users.
**Required:** for every non-final stage, ≥1 **active user** effectively matches at least one EXECUTE permission row — evaluating org/team/role/user set-fields AND within a row, OR across rows, **exactly as `StagePermissionResolver` does** (reuse it as the evaluator so validator and runtime agree; the R4 `StagePermissionAudience` inverse query from WP-R can also serve here for efficiency).
**Authoring feedback (D7-N2):** designer shows "matches N active users" per row (read endpoint: `GET /workflow-stages/{id}/effective-executors`); zero-match rows warn in-editor. Publish blocks zero-executor stages.
**Edge cases:** null-org users excluded (WP-1 C-6) — grant matching null-org user does not satisfy the executor requirement. Inactive users/teams/roles do not count (D8-N1 lands in WP-7 — until then, executor count is computed under current identity rules; the publish rule still catches the "no row at all" and "row matches nobody" cases that matter most).
**Acceptance:** a stage with an EXECUTE row pointing at an empty team/role blocks publish today; non-empty matching orgs pass.

### V-4 — Final stages must have no outgoing transitions (D5-N3)

**Rule:** any final stage with ≥1 outgoing transition → `FINAL_STAGE_HAS_OUTGOING`. Final = lifecycle ended; reopen is a separate future feature.
**Acceptance:** final-with-edge workflow blocked.

### V-5 — Action-kind / outcome consistency (D5-N2)

**Rule:** for transitions whose action `kind` is in a terminal set (`REJECT`, `CLOSE`):
- `REJECT`-kind transitions normally target a final stage with `final_outcome = REJECTED`.
- `CLOSE`/completion-kind transitions target a final stage with `final_outcome = COMPLETED`.
- Mismatch → `ACTION_OUTCOME_MISMATCH`.
- Any `REJECT`/`CLOSE`-kind transition must carry a `confirmation_message` (D2-N4) → else `CONFIRMATION_REQUIRED`.
**Inputs:** action `kind` already exists; `final_outcome` from WP-2.
**Edge cases:** `CUSTOM` destructive transitions require confirmation too (D2-N4) — handled by a generic "destructive requires confirmation" sub-rule: any transition marked `is_destructive` OR using a destructive-kind action requires `confirmation_message`.
**Acceptance:** reject-to-completed-final blocked; destructive transition without message blocked.

### V-6 — Stage status meaningful (D5-N1)

**Rule:** publish blocked if any of: initial stage inactive; any final stage inactive; any transition from/to an inactive stage; any **reachable** inactive non-final stage. Errors: `INITIAL_STAGE_INACTIVE`, `FINAL_STAGE_INACTIVE`, `TRANSITION_USES_INACTIVE_STAGE`, `UNREACHABLE` (already) covers stray inactive islands.
**Note:** stage `status` ACTIVE/INACTIVE must therefore be a real field actively maintained; if WP-R's read of the current `status` field is "stored but unused," this rule is what makes it load-bearing.

### V-7 — Self-transition flagged intentional (D2-N8)

**Rule:** `from_stage_id = to_stage_id` transitions require an explicit `is_self_loop = true` flag at authoring; unflagged self-loops → `UNINTENTIONAL_SELF_LOOP`. (DB has no such column today → add `is_self_loop` boolean, default false; transitions created with `from=to` set it implicitly via the request.)
**Acceptance:** intentional self-transition passes; accidental one fails publish.

### V-8 — Field-rule consistency (D6-N4)

**Rule:** for each (stage, field) rule combination: `required && hidden` → `REQUIRED_HIDDEN_CONFLICT`; `required && !editable && no default && no guaranteed prior value` → `REQUIRED_READONLY_NO_VALUE`. Clear messages naming the field and stage.
**Edge case:** "guaranteed prior value" = the field is filled by an earlier stage's required+editable rule (cross-stage reachability of value). MVP: treat as satisfied if any prior reachable stage requires+edits the field; deeper guarantee is WP-4 territory.

### V-9 — Field-constraint consistency + design-time regex + options shape (D6-N5, D6-N6, D6-N10)

**Rules at field save (request layer) and re-checked at publish:**
- `min_value <= max_value`, `min_length <= max_length`; positive file-size limits. Violations → `FIELD_CONSTRAINT_INVALID`.
- `regex_pattern` syntactically valid (`preg_match` dry-run) + capped length/complexity (e.g. ≤255 chars; reject catastrophic backtracking heuristics where cheap) → else `FIELD_REGEX_INVALID`.
- Static `SELECT` options: array of `{value, label}` with unique non-empty `value` → else `FIELD_OPTIONS_INVALID`.
**Acceptance:** contradictory field rejected at save; bad regex never reaches runtime.

---

## Part B — Designer lifecycle gates

### L-1 — Delete policy for versions/definitions (D4-N3)

**Current:** hard-delete of PUBLISHED versions and of definitions permitted when no requests reference them.
**Required:**
- DRAFT versions: hard-delete allowed when no requests.
- PUBLISHED versions: never hard-deleted; archive-only (return 422 `PUBLISHED_NOT_DELETABLE`), even request-free.
- ARCHIVED versions: retained by default; deletion restricted, audited, only when no requests reference them (no behavior change to the existing guard, just audit — see L-3).
- Definitions: not hard-deletable if any published/archived/request-linked version exists; prefer soft delete. Add `deleted_at` to `workflow_definitions` for soft delete.
**Edge cases:** request-free PUBLISHED definition → archive all versions, then soft-delete the definition.

### L-2 — Limited definition editing (D4-N4)

**Current:** no update endpoint for definitions (code + name frozen after create).
**Required:** `PUT /v1/workflow-definitions/{id}` — `code` immutable (rejected at request layer if changed, mirroring BF-4's pattern); `name` + `description` editable (add `description` column if absent); audited before/after; edits never affect existing requests/versions. Designer UI adds rename/description fields.
**Acceptance:** definition rename persists; code change → 422.

### L-3 — Clone from ARCHIVED (D4-N8)

**Current:** clone allowed only from PUBLISHED.
**Required:** `WorkflowDesignerService::cloneVersion` accepts ARCHIVED source too → new DRAFT with next version number, full validation/publish path; archived source stays archived. Designer UI enables clone on archived rows.
**Acceptance:** archived version clones cleanly; source unchanged.

### L-4 — Archive confirmation for last published (D4-N9)

**Current:** archiving the only PUBLISHED version is allowed silently.
**Required:** endpoint accepts an optional `reason`/`comment` (stored in audit metadata); the API still permits the kill switch (no block), but returns a structured `LAST_PUBLISHED_ARCHIVED` **warning** in the response when the definition will have no startable workflow; frontend `admin/workflows.vue` shows an explicit AlertDialog warning ("new request creation will stop for this workflow definition") before confirming.
**Acceptance:** existing requests continue on pinned versions (unchanged); the action is audited with reason; the UI warns.

### L-5 — Drop user-specific grants (D7-N3)

**Current:** `user_id` accepted on stage permissions.
**Required:** `Store/UpdateStagePermissionRequest` reject `user_id` (prohibited); designer UI removes the user picker. Existing rows with `user_id` are non-breaking (resolver still handles them) but flagged at publish via V-3 if they're the only executor and the user is inactive. A one-time cleanup migration archives `user_id`-bearing rows from published versions (logged) is optional and out of scope here — note as follow-up.
**Acceptance:** new/updated permission rows never carry `user_id`.

### L-6 — Duplicate permission rows (D7-N5)

**Current:** identical `(stage, org, team, role, user, access_level)` rows creatable.
**Required:** unique DB constraint on that tuple (with nulls handled) + request-layer `Rule::unique` → `DUPLICATE_PERMISSION_ROW`.
**Edge cases:** VIEW and EXECUTE rows for the same audience are distinct (access_level in the key).

### L-7 — Return-transition metadata (D5-N4)

**Current:** `WorkflowGraphService::isReturnEdge` is a sort-order heuristic.
**Required:** `workflow_transitions.transition_type` enum (`FORWARD`, `RETURN`, `REJECT`, `CLOSE`, `CUSTOM`) required at authoring; the sort-order heuristic stays as a display fallback only. Default = `FORWARD`; REJECT/CLOSE derive from action kind; designer can mark RETURN.
**Acceptance:** graph edge `is_return` reads the explicit field; a forward-by-sort backward-by-truth edge is correctly labeled.

### L-8 — Display-label determinism (D5-N5)

**Current:** graph node `display_label` = first permission row with a label (arbitrary).
**Required:** deterministic resolution — priority order: (1) stage's own bank-facing label if a per-audience label feature is added later (out of scope; placeholder), (2) explicit designer-set stage label, (3) first-by-id labeled permission row (stable, no longer "arbitrary"). Document the rule.
**Acceptance:** same inputs ⇒ same label; test asserts determinism.

---

## Error cases

All validator errors surface via the existing `WORKFLOW_VALIDATION_FAILED` (422) + named `errors[]` list; lifecycle errors use the governance error envelope (422/409) with the codes above. No 500s.

## Acceptance criteria

1. A workflow exercising every invalid pattern (ambiguity, unreachability, dead executor, final-with-edge, kind/outcome mismatch, inactive-on-path, unflagged self-loop, required+hidden, bad regex/constraints/options) is blocked at publish with a named error; the same workflow fixed passes.
2. Effective-executor authoring endpoint returns accurate counts; zero-match rows warn.
3. Delete policy, definition rename, clone-from-archived, last-published archive warning, drop-user-grants, duplicate-row guard, transition-type, and deterministic label all behave per spec.
4. Existing PUBLISHED/ARCHIVED versions unaffected (immutability held); only DRAFT validation tightens.
5. All WP-0 suites green; WP-2's outcome rule not duplicated.

## Test cases

- **Unit (validator):** one fixture per rule above (invalid + valid); the pure validator is already unit-testable.
- **Feature (publish):** end-to-end publish blocked/approved for representative workflows; lifecycle endpoints (delete policy matrix, definition rename, clone-from-archived, archive-last-published with reason + warning).
- **Authoring:** effective-executor endpoint counts; stage/transition/permission/field request-layer rejections.

## Manual verification steps

1. Designer: build a workflow violating each rule → publish blocked with the named error; fix → publishes.
2. Try deleting a request-free PUBLISHED version → blocked; archive works.
3. Rename a definition; code stays immutable.
4. Clone an archived version → new draft.
5. Archive the only published version → warning shown, existing requests unaffected.

## Rollback considerations

Validator additions are individually revertible (remove the error push). Two schema additions — `workflow_transitions.is_default_submit`, `workflow_transitions.is_self_loop`, `workflow_transitions.transition_type`, `workflow_definitions.description`, `workflow_definitions.deleted_at` — are additive and safe to roll back; backfills for existing rows set sensible defaults (`is_default_submit`/`is_self_loop` false, `transition_type` derived from action kind). L-1's published-not-deletable gate is the one that could strand an admin workflow expecting delete — call out in release notes.

## Open questions

One design flag (decided inline from notes, changeable): **V-8 "guaranteed prior value"** uses reachability-of-a-prior-required-editable-stage as the satisfaction test (MVP). Deeper guarantees (value actually persisted at runtime) belong to WP-4 semantic mapping. Confirm or tighten.
