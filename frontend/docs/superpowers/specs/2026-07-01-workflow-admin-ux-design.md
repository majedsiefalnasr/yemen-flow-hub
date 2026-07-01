# Workflow And Admin UX Alignment Design

Date: 2026-07-01

## Scope

Enhance the UI and UX for these frontend surfaces:

- `/workflows`
- `/workflows/instances/[id]`
- `/admin/workflows`
- `/admin/reference-data`

The pass should align these pages with the existing Yemen Flow Hub application UI: dense operational pages, RTL-safe layout, restrained admin styling, consistent guards, clear status affordances, and familiar table/form controls.

Backend API contracts stay unchanged. Small frontend page-flow and component-structure changes are allowed where they improve usability.

## Project Context Verified

This spec is for the current Yemen Flow Hub Nuxt frontend under:

- `/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend`

The current project files verified for this design are:

- `frontend/app/pages/admin/workflows.vue`
- `frontend/app/pages/admin/reference-data.vue`
- `frontend/app/pages/workflows/index.vue`
- `frontend/app/pages/workflows/instances/[id].vue`
- `frontend/app/components/workflow/WorkflowProcessGraph.vue`
- `frontend/app/components/workflow/WorkflowTransitionEditor.vue`
- `frontend/app/components/workflow/WorkflowStageEditor.vue`
- `frontend/app/components/workflow/StageRoutingEditor.vue`
- `frontend/app/composables/useWorkflowGraph.ts`
- `frontend/app/composables/useWorkflowTransitions.ts`
- `frontend/package.json`

Current project facts:

- The app is Nuxt 4 with Vue 3, shadcn-vue/reka-ui primitives, TanStack Vue Table, Pinia, and lucide-vue-next.
- `/admin/workflows` already has the normal tabbed designer, graph summary, stage editor, transition editor, field designer, publish panel, and action catalog.
- `/admin/reference-data` already uses a master/detail table structure with metric cards and guarded reference table/value actions.
- `/workflows` and `/workflows/instances/[id]` are current workflow-engine pages in this frontend.
- `@vue-flow/core` is not currently installed. The canvas implementation must either add it intentionally or use an in-repo SVG/D3-style fallback.

## Goals

- Make the workflow and reference-data pages feel native to the current app shell.
- Improve scanability for admin and operational users.
- Preserve role, screen, and capability gates.
- Add a workflow canvas view to `/admin/workflows` as a case-study enhancement.
- Keep the normal workflow designer available as the authoritative editing path.
- Enforce the existing workflow editability rule: only draft workflow versions are editable. In this spec, "live" means a published or archived workflow version.

## Non-Goals

- No backend schema changes.
- No persisted workflow canvas coordinates in this pass.
- No full n8n clone or general automation-builder feature set.
- No replacement of existing workflow tabs with the canvas.
- No broad redesign of unrelated request, dashboard, or settings pages.

## Design Principles

- Use existing primitives first: `PageHeader`, `ScreenGuard`, `DataTable`, metric cards, alerts, empty states, dialogs, badges, and tabs.
- Favor dense but organized admin UI over decorative layouts.
- Keep cards for actual tools, tables, dialogs, or repeated items; avoid cards inside cards.
- Use icons for compact actions and labels for destructive or business-critical commands.
- Show disabled controls when their absence would hide workflow rules; explain read-only or permission states.
- Never rely on color alone. Pair operational status colors with readable labels and icons.
- Preserve RTL alignment and responsive behavior across desktop and mobile.

## `/admin/workflows`

### Page Structure

The page becomes a workflow workspace with:

- `PageHeader` with breadcrumbs, title, subtitle, and guarded create action.
- Workflow definition picker.
- Workflow version picker.
- State badge for draft, published/live, or archived.
- Clone action when the selected version supports it.
- A clear editability notice:
  - Draft: editable.
  - Published/live/archived: read-only.

Below the selector area, add a view switch:

- `تفصيلي` for the normal tabbed designer.
- `لوحة` for the visual canvas.

### Normal View

The normal view remains the authoritative workflow editor. It keeps the current functional areas:

- Publish panel.
- Stages.
- Routing and assignments.
- Transitions.
- Fields.
- Actions.

The implementation should align these panels with app patterns:

- Consistent section headers.
- Stable spacing.
- Clear empty states.
- Guarded create, update, and delete buttons.
- Read-only messaging for non-draft versions.

### Canvas View

The canvas is visual-first with safe edits.

Canvas should show:

- Stages as draggable nodes.
- Initial stage badge.
- Final stage badge.
- Claim-required badge.
- SLA indicator when available.
- Transitions as lines from source stage to target stage.
- Edge labels showing action name or action code.
- Comment-required marker on transition labels.
- Return and self-loop indicators.

Canvas behavior:

- Draft versions:
  - Users can drag nodes locally.
  - Users can click a node to inspect/edit the stage through existing stage flows.
  - Users can click an edge to inspect the transition.
  - Users can add transitions through the existing controlled transition dialog.
  - Users can delete transitions only through guarded existing delete behavior.
- Published/live/archived versions:
  - Nodes and edges are inspect-only.
  - Dragging and editing controls are disabled.
  - The read-only state is visible.

Canvas layout:

- Auto-layout from graph data.
- Browser-local node movement for the current session.
- No persisted coordinates until the backend model supports them.

Preferred implementation route:

- Use `@vue-flow/core` if dependency addition is acceptable during implementation.
- It provides draggable nodes, zoom/pan, custom nodes, custom edges, and labeled edges.
- If adding the dependency is blocked, use the existing graph data with a simpler SVG/D3-based canvas fallback.

## `/workflows`

The workflow list should move from its current basic table styling to the app's operational table pattern.

Planned UX:

- Replace the custom heading with `PageHeader`, breadcrumbs, and a guarded primary `طلب جديد` action.
- Keep the queue/all switch as a clear tab or segmented control.
- Add compact metrics where data supports them:
  - My queue.
  - All requests.
  - Waiting for action.
  - Claimed or locked, if available.
- Use the shared table language:
  - Search by reference.
  - Filter by stage and status if available.
  - Clear status badges instead of raw status text.
  - Row click-through to detail.
  - Explicit `عرض` action for accessibility.
- Preserve role and screen access rules.

Empty states:

- Queue: "لا توجد طلبات في انتظار إجرائك حالياً".
- All: "لم يتم إنشاء أي طلبات بعد".

Loading and error states should use the existing skeleton and alert patterns with retry.

## `/workflows/instances/[id]`

The instance detail page keeps the current tabs but improves hierarchy and action clarity.

Planned UX:

- Add `PageHeader` with:
  - Request reference.
  - Current stage badge.
  - Claim state.
  - Primary claim action when available.
- Keep the claim banner prominent when another reviewer holds the claim.
- Keep tabs for:
  - Form.
  - History.
  - Documents.
- Separate workflow actions from the form body:
  - Form fields remain the main content.
  - Comments and transition buttons become a distinct action panel.
- If a stage requires a claim and the current user does not hold it:
  - Actions are disabled, not hidden.
  - A short read-only explanation is shown.
- Conflict errors remain destructive alerts with clear reload/update messaging.

History and documents should use the same card/table visual language as other request detail surfaces.

## `/admin/reference-data`

The reference data page remains a master/detail admin tool but aligns with `/admin/orgs` and `/admin/roles`.

Planned UX:

- Keep `PageHeader`, breadcrumbs, and guarded create actions.
- Add metric cards:
  - Total reference tables.
  - Active reference tables.
  - System reference tables.
  - Selected table value count.
- Use a two-pane layout on desktop:
  - Right pane: reference tables.
  - Main/left pane: values for the selected table.
- Stack panes on mobile.
- Show the selected table summary above values:
  - Label.
  - Key.
  - Active badge.
  - System badge.
  - In-use badge.
- Use shared data-table affordances for both tables and values:
  - Search.
  - Status filter.
  - Column visibility.
  - Pagination.
  - Row actions.
- Preserve destructive protections:
  - System rows cannot be deleted.
  - In-use rows cannot be deleted.
  - Create, update, and delete actions remain capability-gated.
- Use dialogs for create/edit and confirmation dialogs for delete.
- Use clear empty states for:
  - No tables.
  - No selected table.
  - Selected table with no values.

## Data Flow

`/admin/workflows` uses existing workflow data:

- Definitions and versions from `useWorkflows`.
- Stages from `useWorkflowStages`.
- Transitions from `useWorkflowTransitions`.
- Actions from `useWorkflowActions`.
- Graph data from `useWorkflowGraph`.

Canvas state is derived from the graph:

- `WorkflowGraphNode` becomes a canvas node.
- `WorkflowGraphEdge` becomes a canvas edge.
- Edge labels come from `action_name` or `action_code`.
- Node drag positions are local UI state only.

`/workflows` uses the engine request store:

- Queue data for "my queue".
- List data for "all".
- Existing instance detail data for detail view.

`/admin/reference-data` uses existing reference data composable behavior:

- Tables list.
- Selected table values.
- Existing create, update, activate, deactivate, and delete operations.

## Error Handling

- All load failures render an alert with retry.
- Save/delete failures use toast plus form-level messaging where the user must correct input.
- Conflict errors remain visible and specific.
- Read-only workflow versions show disabled controls with explanation.
- Permission-gated actions are hidden or disabled according to the existing `ScreenGuard` pattern.

## Accessibility And Responsiveness

- All icon-only buttons must have labels or accessible names.
- Tables keep explicit row actions in addition to row click behavior.
- Canvas non-spatial controls must be keyboard reachable:
  - View switch.
  - Fit view.
  - Zoom controls.
  - Selected node or edge details.
- Canvas must have a non-canvas fallback: the normal tabbed editor remains available.
- Mobile layouts stack toolbars and panels without text overlap.

## Testing Plan

Unit/component tests:

- `/admin/workflows` view switch between normal and canvas.
- Draft version enables canvas edit affordances.
- Published/live/archived version disables canvas edit affordances.
- Canvas maps graph nodes and edges correctly.
- Edge labels render action name or code.
- `/workflows` queue/all switch and empty states.
- `/workflows/instances/[id]` claim-required disabled action state.
- `/admin/reference-data` table selection, value empty states, and protected delete visibility.

Focused checks:

- `pnpm test -- app/tests/unit/pages/workflows-index.test.ts`
- `pnpm test -- app/tests/unit/pages/workflows-instance-detail.test.ts`
- `pnpm test -- app/tests/unit/pages/WorkflowDesignerPage.test.ts`
- `pnpm test -- app/tests/unit/pages/ReferenceDataPage.test.ts`

Browser verification:

- `/admin/workflows` normal and canvas views.
- `/workflows` queue/all.
- `/workflows/instances/[id]` form/action/history/documents tabs.
- `/admin/reference-data` desktop two-pane and mobile stacked layouts.

## Open Implementation Notes

- If `@vue-flow/core` is added, include its required CSS in the canvas component or app stylesheet.
- If dependency installation is blocked, implement a simpler SVG canvas using existing data and defer direct edge creation.
- Do not persist canvas positions without an explicit backend/API design.
- Keep future enhancement room for direct drag-to-connect, persisted layout, minimap, validation overlays, and richer node configuration.
