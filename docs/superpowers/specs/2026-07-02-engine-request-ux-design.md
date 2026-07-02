# Engine Request UX Rebuild — Design

Date: 2026-07-02
Surface: `frontend/app/pages/workflows/` (new, instances/[id]), plus supporting engine components.

## Problem

The dynamic workflow-engine request pages (`workflows/new`, `workflows/instances/[id]`)
are thin and disjointed compared with the removed legacy `requests/` pages. Today:

- `new.vue` picks a workflow and immediately creates an **empty** instance — no data
  is collected up front.
- `instances/[id].vue` is a single-column three-tab page (form / bare history / bare
  documents) with no summary strip, no visual workflow steps, and no dedicated actions
  area. Edit has no distinct affordance.

The removed `requests/` pages had a richer, well-liked UX: a summary strip, a
two-column layout (tabbed main + sticky actions rail), a step/progress indicator, and
a multi-step create wizard. We want that UX for the engine pages, but driven by the
**dynamic engine data** (stages, transitions, resolved field schema) rather than the
legacy hardcoded `RequestStatus` pipeline.

The legacy step/timeline components (`WorkflowProgress`, `WorkflowTimeline`,
`AuditTimeline`) are all coupled to the `RequestStatus` enum and `RequestStageHistory`,
so they cannot be reused as-is.

## Goals

1. `workflows/new`: a multi-step **wizard** that collects request inputs (grouped by the
   workflow's dynamic field-groups) before the request enters the pipeline.
2. `workflows/instances/[id]`: a two-column detail page — summary strip on top, tabbed
   main area (inputs, documents, timeline), sticky **actions rail** sidebar — with a
   dynamic **stage stepper** built from the engine stage graph.
3. Edit: editing a draft happens on the instance page (draft-stage inline edit + save),
   not a separate broken route.
4. Fix the four reported breakages: create-collects-no-inputs, form/actions, edit,
   bare documents/history.

## Non-goals

- No changes to the legacy `requests/` routes (already deleted) or the workflow
  **designer** pages (`admin/workflows`).
- No new backend endpoints. Everything uses the existing engine API.
- No voting / FX-confirmation / SWIFT special panels in this pass beyond what the
  dynamic actions already expose. (Those can be layered later; see Future.)

## Data contract (existing engine API)

- `GET engine-requests/available-workflows` → `AvailableWorkflow[]` (`{ id, code, name, version_id, version_number }`).
- `POST engine-requests` `{ workflow_version_id, bank_id?, merchant_id?, data }` → creates instance.
- `GET engine-requests/{id}/form-schema` → `{ field_groups: ResolvedFieldGroup[] }` (resolved, stage-aware; **requires an instance**).
- `PATCH engine-requests/{id}/draft` `{ data, version }` → save draft data.
- `POST engine-requests/{id}/actions` `{ transition_id, comment?, data, version }` → execute a transition.
- `GET engine-requests/{id}/graph` → `WorkflowGraph` (`nodes: WorkflowGraphNode[]`, `edges: WorkflowGraphEdge[]`).
- `GET engine-requests/{id}/history` → `EngineHistoryEntry[]`.
- `GET/POST/DELETE engine-requests/{id}/documents` (+ `/download`).
- `POST/DELETE engine-requests/{id}/claim` (+ heartbeat).

Store methods already present: `loadAvailableWorkflows`, `createInstance`, `loadInstance`
(loads current + history + graph + documents), `saveDraftData`, `executeTransition`,
`uploadDocument`, `removeDocument`.

**Key constraint:** the resolved `form-schema` and documents are per-instance. So the
wizard creates the instance first, then collects data against the resolved schema.

## Approach (chosen)

**Create-then-guide.** `workflows/new` stays the workflow picker, but on selection it
creates the draft instance and routes to the instance page in **wizard mode**. The
instance page is the single surface for create → edit → view → act, rendered by role
and stage:

- **Wizard mode** (`?mode=wizard`, draft stage, creator): a stepped flow over the
  resolved field-groups (one step per group) with Save-draft and a final Submit
  (the initial-stage transition). This satisfies "multi-step wizard collects inputs".
- **View/act mode** (default): the two-column detail with tabs + actions rail.

This reuses the resolved, stage-aware schema and keeps documents working (instance
exists), and unifies edit with view. It avoids a second, drift-prone version-level
schema path.

Rejected alternatives:
- *Pre-create wizard* over version-level `field-groups`: would duplicate schema
  resolution client-side and can't attach documents until after create.
- *Keep current create-empty-then-fill flat form*: fails the "wizard" and "steps" goals.

## Components

New, engine-native (no `RequestStatus` coupling):

1. **`EngineStageStepper.vue`** — dynamic stepper from `WorkflowGraph`.
   - Props: `graph: WorkflowGraph`, `currentStageId: number | null`, `history: EngineHistoryEntry[]`, `compact?: boolean`.
   - Derives the ordered stage path from `nodes` sorted by `sort_order` (initial →
     final), marks visited (present in history `to_stage`), current, and upcoming.
     Return/self-loop edges do not add duplicate steps. Built on the existing shadcn
     `Stepper` primitives.
   - Purpose: the "workflow steps" the user asked for, correct for any workflow.

2. **`EngineTimeline.vue`** — vertical timeline from `EngineHistoryEntry[]`.
   - Props: `entries: EngineHistoryEntry[]`.
   - Each entry: from→to stage, action name, actor, timestamp (formatted ar-EG),
     optional comment. Newest last, current highlighted.

3. **`EngineRequestSummary.vue`** — the top summary strip.
   - Props: `request: EngineRequest`.
   - Label/value items: reference, current stage, status, bank, merchant, amount,
     created date, claimed-by. Mirrors the old `request-summary-strip`.

4. **`EngineActionsRail.vue`** — the sticky sidebar action panel.
   - Props: `request`, `availableActions` (edges from current stage), `canAct`,
     `claimRequiredButNotHeld`, `busy`.
   - Emits: `run(transitionId, requiresComment)`, `claim`, `release`.
   - Contains the comment field and the transition buttons; also surfaces claim state.
     Extracted so the instance page template stays small.

5. **`EngineRequestWizard.vue`** — the stepped create flow.
   - Props: `requestId`, `fieldGroups`, `version`, initial `data`.
   - One step per field-group using `DynamicForm` (filtered to that group), a
     `Save draft` action per step (`PATCH /draft`), Back/Next, and a final Submit that
     runs the initial-stage transition. Built on the `Stepper` primitives.
   - Emits `submitted` on success (page then switches to view mode).

Reused as-is: `DynamicForm` (per-group render via a filtered `fieldGroups`),
`ClaimBanner`, shadcn `Stepper`, `DataTable`-free (detail page is not a table).

## Page layouts

### `workflows/new.vue`
Unchanged responsibility (workflow picker) but polished: `PageHeader`, loading
skeletons, empty state, cards per available workflow. On "بدء الطلب": `createInstance`
→ `navigateTo(/workflows/instances/{id}?mode=wizard)`. Gated to creator roles
(`DATA_ENTRY`); others see a "not permitted" state.

### `workflows/instances/[id].vue`
```
PageHeader (reference + breadcrumbs + claim button)
EngineStageStepper                      ← dynamic steps
EngineRequestSummary                    ← summary strip
conflict / claim banners

if wizard mode (draft + creator):
  EngineRequestWizard                   ← stepped inputs, save draft, submit
else:
  grid lg:grid-cols-[1fr_320px]
    main:
      Tabs: البيانات (DynamicForm by group) | المرفقات (documents) | السجل (EngineTimeline)
    aside (sticky):
      EngineActionsRail                  ← actions + comment + claim
```
Role/stage logic (kept from current page, moved into small computeds): `stageRequiresClaim`,
`isHeldByMe`, `heldByOther`, `canAct`, `availableActions` (graph edges from current stage).

### Edit
No separate route needed. A draft instance opens in wizard/edit affordance; a
non-draft instance shows the form tab read-only unless a stage action allows edits.
The old `[id]/edit.vue` concept is folded into the instance page. (If a
`/workflows/instances/[id]/edit` route is desired later it can redirect to
`?mode=wizard`; not built now.)

## Documents & history (fix "bare/broken")

- **Documents tab:** list with name, uploader, date, download link (`downloadUrl`),
  delete (when permitted), and an upload control (`uploadDocument`). Empty state.
- **History tab:** `EngineTimeline` over `store.history`.

## Error handling

- Optimistic-concurrency conflict (409) already handled by
  `useEngineRequestActions` (`conflictError`); surface the existing alert, reload on
  conflict.
- Load failure → inline `Alert` with retry (mirrors the list page).
- Wizard save-draft failure → toast, stay on step.
- Action validation: run `DynamicForm.validate()` before submit; block on invalid.

## Testing

- Unit: `EngineStageStepper` ordering (initial→final, visited/current/upcoming,
  self-loop/return not duplicated) from a sample graph+history.
- Unit: `EngineTimeline` renders entries sorted, current highlighted.
- Component: wizard advances steps, calls `saveDraft` per step, `submitted` on final
  transition.
- Existing engine composable tests remain green.

## Future (out of scope)

Voting panel, FX-confirmation card, SWIFT upload surfaced as first-class rail panels
for the relevant stages; duplicate-invoice widget; print/customs views. These were in
the legacy page and can be re-added incrementally once the core rebuild lands.
