# Admin Workflows Impeccable Remediation Design

Date: 2026-07-15

## Scope

Resolve every P1 and P2 finding from the `/admin/workflows` Impeccable audit in one bounded frontend pass. The work is limited to the workflow designer page, its audited components, and their focused tests.

The backend workflow lifecycle and deletion contracts remain unchanged. The frontend must not infer request usage or add request-count metadata.

## Goals

- Enforce draft-only editing across the workflow designer.
- Remove destructive actions that are impossible from workflow state alone.
- Keep request-linked archived-version deletion under backend authority.
- Reset publish validation state whenever the selected version changes.
- Make the workflow canvas legible, performant, theme-aligned, and keyboard accessible.
- Explain that the Actions Catalog is global and version-independent.
- Preserve the existing app-native, RTL-first visual language.

## Non-Goals

- No backend, API resource, database, policy, or lifecycle changes.
- No request-count or `can_delete` fields added to workflow resources.
- No persisted canvas coordinates.
- No replacement of the normal workflow tabs.
- No unrelated workflow-designer restructuring or decorative redesign.
- No destructive or persistent workflow operations against live data during verification.

## Lifecycle And Deletion Eligibility

Only versions with `state === 'DRAFT'` and `is_editable === true` are editable.

Frontend deletion eligibility uses facts already present in the selected resources:

- A PUBLISHED version never offers Delete version because the backend contract makes deletion impossible.
- A DRAFT version may offer Delete version.
- An ARCHIVED version may offer Delete version because the backend permits deletion when it is request-free. The backend remains the final authority and may reject a request-linked archived version.
- Delete workflow is offered only when every version in the selected definition is DRAFT. Any PUBLISHED or ARCHIVED version makes definition deletion impossible under the backend contract.
- Capability guards continue to apply independently of lifecycle eligibility.

Unavailable destructive actions are hidden rather than disabled because no additional action is available from that state.

## Fields Tab

`WorkflowFieldDesigner` uses one computed editable state based on both `state` and `is_editable`.

For read-only versions:

- Group move-up, move-down, add, delete, field edit, and field reassignment controls are not rendered.
- The instructional copy explains that groups and fields can be reviewed but only changed in a draft version.

For editable drafts, the current ordering and editing instructions remain.

## Publish Panel

`WorkflowPublishPanel` derives draft eligibility reactively from the current `version` prop.

When version ID, state, or editability changes, it resets:

- validation errors and success state;
- validation and publish loading state;
- the publish confirmation dialog;
- any transient result belonging to the previously selected version.

An in-flight validation response must not repopulate state after the selected version changes. The intended success copy remains `النسخة جاهزة للنشر`, and its focused assertion is updated accordingly.

## Workflow Canvas

### Reactivity And Layout

- Register `StageNode` with `markRaw` in a stable, non-reactive node-type registry.
- Reduce excessive vertical spacing and use a responsive canvas height so the seeded nine-stage workflow opens at a legible fit.
- Fit after graph load and after auto-arrange using bounded padding and zoom settings.
- Preserve local drag positions for the current session only.

### Edge Interaction

- Read-only edges are non-selectable, non-focusable, and have no pointer cursor.
- Editable edges receive a wider interaction hit area and an accessible name describing their transition.
- Pointer selection and a keyboard-accessible transition selector feed the same selected-transition state.
- The selected transition is presented in a compact action surface with explicit Edit transition and Delete transition buttons.
- Editing reuses the existing controlled transition dialog and `updateTransition` API.
- Deletion uses the existing guarded transition deletion behavior and a project AlertDialog, not a native browser confirmation.

The normal Transitions tab remains the complete non-canvas fallback.

### Controls And Theme

- Zoom in, zoom out, and fit use the shared shadcn-vue Button primitive.
- Icon buttons keep accessible Arabic names and visible focus styles supplied by the shared primitive.
- Canvas colors use existing semantic CSS variables. The hardcoded `#0066cc` is removed.
- Canvas and workflow-header controls use at least 44px targets on mobile and retain the existing compact desktop sizes at the `md` breakpoint.
- RTL page behavior remains unchanged; Vue Flow retains `dir="ltr"` for spatial graph coordinates.

## Actions Catalog

The catalog remains functionally global and editable independently of the selected workflow version.

Its header is visually distinguished with the existing Card, Badge, and Alert primitives and states:

- the catalog is global and version-independent;
- changes can affect workflows other than the selected version;
- renaming, editing, activating, or deactivating an action may affect every workflow that uses it.

No action API behavior changes.

## Testing

Focused Vitest coverage must prove:

- group ordering controls and editable instructions appear only for editable drafts;
- published version deletion and definitions containing published or archived versions do not expose deletion actions;
- draft and archived version deletion remain available to MANAGE users;
- publish controls update reactively across draft, published, and archived prop changes;
- validation state is cleared on version change and stale validation responses are ignored;
- editable canvas edges expose accessible selection and action controls while read-only edges do not;
- canvas controls remain accessible Button primitives;
- the Actions Catalog explains its global scope.

The relevant focused tests, touched-file ESLint, and touched-file Prettier checks must pass. Typecheck is required only if shared types or cross-module contracts change.

## Browser Verification

Use Playwright CLI as System Admin to verify:

- every workflow tab still renders;
- published and archived versions remain read-only;
- impossible published and definition deletion actions are absent;
- the canvas opens legibly, emits no reactive-component warning, and its controls work;
- read-only edge labels have no pointer affordance;
- desktop and 390px mobile layouts remain contained;
- draft behavior is verified only through safe existing data or non-persistent request mocking. Do not clone, publish, archive, delete, save, or toggle live workflow data.
