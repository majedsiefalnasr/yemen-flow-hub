# Admin Workflows Page — UX & Data-Integrity Fixes

**Date:** 2026-07-05
**Scope:** `frontend/app/pages/admin/workflows.vue` and its five designer tabs (stages, routing, transitions, fields, actions). No new pages, no new tabs, no new backend endpoints — all required backend routes/services already exist.

## Context

`/admin/workflows` is the workflow designer used by `CBY_ADMIN` to build and edit `WorkflowVersion` definitions (stages, org/team/role routing, transitions, dynamic form fields, action catalog) before publishing. Investigation found:

- Most backend CRUD (`WorkflowStageController`, `WorkflowTransitionController`, `StagePermissionController`, `FieldGroupController`, `FieldDefinitionController`) already supports full update semantics with optimistic-concurrency (`version` column) and audit logging via `WorkflowDesignerService`.
- Most gaps are frontend-only: missing edit actions, missing table columns, one disabled form field, and one required-field validation gap.
- One backend gap: no guard preventing a single stage from having `is_initial = true` and `is_final = true` simultaneously.
- The historic "start/end stage doesn't save" symptom was root-caused to a `v-model:checked` vs `v-model` binding bug on reka-ui `Checkbox`/`Switch` components, already fixed in commit `454d7fab` (prior to this session). This spec re-verifies that fix and adds the missing mutual-exclusivity rule on top of it.

## Goals

1. Stages tab: drop the meaningless sort-order column, surface `requires_claim`, convert start/end checkboxes to mutually-exclusive switches, and add the missing mutual-exclusivity guard (backend + frontend).
2. Routing tab (`سير العملية`): stage-permission table shows human labels (org, team, role) instead of raw IDs/dashes, gains an edit action, and its dialog requires org + team + role (all three, not one-of-four).
3. Transitions tab: add an edit action (table already reads real API data; no data-integrity bug found).
4. Fields tab: enable the group selector in the add-field dialog (currently force-disabled), add an edit action for existing fields.
5. Actions tab: remove the "نشط" (active) column (edit/delete already work; is_active toggle stays reachable via edit, not a dedicated column).
6. General UX/UI copy pass consistent with `frontend/DESIGN.md` / `SHADCN.md` tokens and RTL rules — no new visual system, just consistency and clarity fixes identified while touching each tab.

## Non-goals

- No changes to `WorkflowCanvas.vue` (the alternate graph view) — out of scope unless it shares a component touched here.
- No publish-flow validation changes (e.g. blocking publish when a stage lacks permissions) — user confirmed the "every stage needs org+team+role" requirement is about the **stage-permission dialog's own required fields**, not a stage-level coverage gate.
- No new API endpoints. Every fix uses existing routes/services.

---

## Tab 1 — Stages (`WorkflowStageEditor.vue`)

### Table changes

- Remove the "الترتيب" (sort order) column entirely — confirmed cosmetic-only, no reordering UI depends on it being visible.
- `requires_claim` already has a badge in the "النوع" (type) column (`Badge variant="secondary"` labeled "مطالبة") — this satisfies "add يتطلب مطالبة column" without a dedicated column; keep as a badge, not a new column, to avoid table sprawl. If a literal separate column is wanted instead, note this as a call-out during implementation review.

### Start/End toggles

- Replace the two `<Checkbox>` (مرحلة البداية / مرحلة النهاية) with `<Switch>` for visual consistency with the existing `يتطلب مطالبة` switch — all three become togglers as asked.
- Mutual exclusivity (confirmed: auto-clear behavior):
  - Client: a `watch` on `isInitial`/`isFinal` in the dialog — turning one ON immediately sets the other to `false` and disables it (`:disabled="isFinal"` / `:disabled="isInitial"`).
  - Server: `WorkflowDesignerService::createStage()`/`updateStage()` gains a guard — if the resolved attributes would leave `is_initial === true && is_final === true`, throw a `ValidationException` (422) before persisting. This is defense-in-depth since another concurrent admin session or a future API consumer could bypass the client guard.

### Verified non-bug

- `is_initial`/`is_final` persistence: migration, model casts, `WorkflowStageResource`, and `WorkflowDesignerService::createStage/updateStage` all correctly read/write these columns. The `v-model` binding fix (commit `454d7fab`) already resolved the save/retrieve symptom. This spec re-verifies live via `playwright-cli` during implementation, rather than re-fixing something already fixed.

---

## Tab 2 — Routing / سير العملية (`StageRoutingEditor.vue` + `StagePermissionEditor.vue`)

### Table changes (`StagePermissionEditor.vue`)

- Org column already resolves to a label (`orgName()`) — keep.
- Role column already resolves to a label (`roleName()`) — keep.
- Add a **Team** column resolving to a label the same way (`teamName()` helper, mirroring `orgName`/`roleName`), since `team_id` exists on the model/type but isn't shown.
- Add an **edit** action (pencil icon) beside delete, opening the same dialog pre-filled, calling a new `updateStagePermission` composable method (backend `StagePermissionController::update` + `useStagePermissions` gains `updatePermission`).

### Dialog changes (`StagePermissionEditor.vue` add/edit dialog)

- Organization, Team, and Role become **required** fields (remove the "at least one of four" rule for this UI flow):
  - Client: Zod/inline validation requires all three selected before submit is enab't; keep the existing team/role cascading-disable-until-org-chosen UX.
  - Server: `StoreStagePermissionRequest`/`UpdateStagePermissionRequest` rules change `organization_id`, `team_id`, `role_id` from `nullable` to `required` (drop the `after()` "at least one of four" check for these three, `user_id` stays optional/unused by this UI).

### Copy pass

- `StageRoutingEditor.vue` intro paragraph already states the org/team/role → visibility/execution rationale clearly (matches the note the user quoted); no change needed there.

---

## Tab 3 — Transitions (`WorkflowTransitionEditor.vue`)

- Table already renders real API data (`fetchTransitions` from `useWorkflowTransitions`, backed by `WorkflowTransitionController::index`) — no data-integrity fix needed here.
- Add an **edit** action (pencil icon) beside delete:
  - Opens the same dialog pre-filled with `from_stage_id`, `action_id`, `to_stage_id`, `requires_comment`, `confirmation_message`.
  - Calls the already-existing `updateTransition` composable method (backend `WorkflowTransitionController::update` already implemented).
  - `from_stage_id`/`action_id` likely should stay read-only on edit (changing them is equivalent to a different transition and could collide with the `unique(from_stage_id, action_id)` constraint) — only `to_stage_id`, `requires_comment`, `confirmation_message` editable. Flag this as an implementation-time judgment call, defaulting to "lock from/action, allow changing to-stage + comment settings" unless review says otherwise.

---

## Tab 4 — Fields (`WorkflowFieldDesigner.vue`)

- **Bug:** the "المجموعة" (group) `<Select>` in the add-field dialog is hardcoded `disabled` (line ~547) even though `openFieldDialog(groupId)` already sets `fieldGroupId` correctly from the row-level "add field" button. Fix: remove the `disabled` attribute so the group is changeable at creation time (useful when there's more than one group and the admin opened the dialog from the card-level "add field" button rather than a specific group).
- Add an **edit** action (pencil icon) beside delete for each field row:
  - Opens a dialog pre-filled with the field's current values (key stays read-only — likely referenced by stored field data — label/type/required/min/max/dynamic-source editable).
  - Calls the already-existing `updateField` composable method (backend `FieldDefinitionController::update` already implemented).

---

## Tab 5 — Actions (`WorkflowActionsCatalog.vue`)

- Remove the "نشط" (active) table column and its inline `<Switch>` toggle. Edit dialog remains the way to change an action's active state if needed — confirmed edit/delete already function correctly (`updateAction`, `deleteAction` both wired to real endpoints).
- If removing inline-toggle access to `is_active` regresses a needed quick-toggle workflow, that can be re-added inside the edit dialog as a `Switch` field instead of a table column — implementation should check whether `is_active` needs to remain edit-doable at all, or whether it's fully redundant now that the column is gone (i.e., confirm whether `is_active` should move into the edit dialog, since removing the column removes the only current way to toggle it back after an edit-locks-it scenario).

---

## Cross-cutting UX/UI pass

- All new edit dialogs follow the existing pattern in each file (VeeValidate + Zod where a schema already exists, otherwise the existing plain-ref + manual-validation pattern already used in `StagePermissionEditor`/`WorkflowTransitionEditor`/`WorkflowFieldDesigner` — stay consistent per-file rather than introducing a new pattern mid-file).
- All new/changed interactive elements keep RTL rules (`border-s-*`, icon-only buttons get `aria-label`, action column stays rightmost).
- Toast copy for new edit actions follows existing "تم تحديث ..." success pattern already used elsewhere (e.g. stage editor's "تم تحديث المرحلة").
- `ScreenGuard` `capability="MANAGE"` wraps every new edit affordance exactly like existing create/delete affordances in the same file.

## Testing / Verification

- Focused Vitest per touched component where existing tests exist (`WorkflowTransitionEditor.test.ts` already exists — extend for the edit action).
- Backend: focused PHPUnit filters for `WorkflowDesignerServiceTest`/`StagePermissionController`-related tests if they exist; add a new test for the is_initial+is_final mutual-exclusion guard.
- Manual `playwright-cli` pass through all 5 tabs on a DRAFT version: create/edit/delete stage (verify start/end mutual exclusion persists after reload), create/edit/delete stage permission (verify all-3-required validation, verify team label shows), create/edit/delete transition, create/edit field (verify group selectable), verify actions tab has no نشط column and edit/delete still work.
