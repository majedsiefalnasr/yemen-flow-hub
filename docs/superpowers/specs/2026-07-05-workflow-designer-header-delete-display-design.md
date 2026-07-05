# Workflow Designer Header, Delete, and Request Workflow Display — Design

**Date:** 2026-07-05
**Scope:** `/admin/workflows` designer header redesign, hard-delete of workflow definitions + versions (request-link gated), and surfacing workflow name + version number on the requests list table and request detail page. Backend (Laravel) + frontend (Nuxt/Vue).

## Context

The workflow designer (`/admin/workflows`) currently has no way to remove a workflow definition or an individual version once created — only archive (versions) and clone exist. Stray test/duplicate definitions accumulate. Separately, the requests list (`/workflows`) and request detail page show no workflow context (only `workflow_version_id` is exposed by `EngineRequestResource`), so an admin/operator cannot tell which workflow a request belongs to from the queue.

Investigation found:
- `engine_requests.workflow_version_id` FK → `workflow_versions`. The "in use" check is `EngineRequest::where('workflow_version_id', $v)->exists()`.
- No `DELETE` routes or controller `destroy` methods exist for `WorkflowDefinition` or `WorkflowVersion`.
- Existing migrations already use `cascadeOnDelete()` on `workflow_versions` → stages/transitions/permissions/field-groups/field-rules, so deleting a version or definition cascades its whole subtree cleanly.
- `WorkflowVersionPolicy` and `WorkflowDefinitionPolicy` exist, gating all actions on the `workflow_designer` MANAGE capability (no `delete()` method yet on either).
- `EngineRequestResource` exposes `workflow_version_id` only; `EngineRequest` model has a `workflowVersion()` BelongsTo but no chained `definition` relation loaded by list/detail endpoints.
- `EngineRequestController::index` does not currently eager-load `workflowVersion`.
- Requests table is TanStack `ColumnDef<EngineRequest>[]` in `frontend/app/pages/workflows/index.vue:226`, rendered via a `<DataTable>` component; the detail page is `frontend/app/pages/workflows/instances/[id].vue`.

## Goals

1. **Header redesign** — replace the flat `PageHeader` + scattered pickers/publish-panel with a single summary card showing definition + version context and clustering all actions (clone, delete version, delete definition).
2. **Delete workflow version + definition** — hard-delete a version not referenced by any `engine_requests` row, and hard-delete a whole definition when none of its versions are referenced. State-agnostic (DRAFT/PUBLISHED/ARCHIVED does not gate delete — only the request-link check does).
3. **Workflow name + version on request** — expose definition name + version number through `EngineRequestResource`, show it as a new column on the requests table, and add it to the request detail header/breadcrumbs.

## Non-goals

- No soft-delete / restore (audit trail covers history).
- No bulk delete.
- No reassigning requests to a different version before delete — if a version/definition has requests, delete is blocked.
- No change to the create/clone/validate/publish/archive flows beyond relocating their buttons into the new summary card.
- No workflow-context display on Data Entry simplified views (Data Entry already gets simplified statuses and does not see internal CBY workflow structure — keep that boundary).

---

## 1. Header redesign (`frontend/app/pages/admin/workflows.vue`)

Replace the current top section (PageHeader + flat picker strip + publish panel as three separate blocks) with:

- **Summary card** (`<Card>`): definition name + code as title, version picker (`<Select>`) + state badge inline on the title row. Stat line below: `N مراحل · N انتقالات · N حقول`. Metadata line: created date, published date or "غير منشورة". Actions cluster (right side / inline-end): clone (existing, PUBLISHED-only), **delete version** (new, `ScreenGuard MANAGE`), **delete definition** (new, via `<DropdownMenu>` ⋯ → `AlertDialog`, `ScreenGuard MANAGE`).

**Counts source (backend change):** `WorkflowVersionResource` currently exposes no counts. The definitions-list endpoint (`WorkflowDefinitionController::index`, which nests versions) must eager-load `withCount(['stages', 'transitions'])` on each version and the resource must surface `stages_count` / `transitions_count`. Field count is not a direct relation on the version (fields belong to field-groups which belong to the version) — expose it as a computed `fields_count` via `withCount` on a `fields()` hasManyThrough relation added to `WorkflowVersion` (version → field_groups → field_definitions), or fall back to omitting the field count from the stat line if the hasManyThrough proves awkward. Decision during implementation: prefer the hasManyThrough so all three counts show; if it complicates the model, drop to two counts (`N مراحل · N انتقالات`) rather than fudge the third.
- **Read-only banner** ("نسخة للعرض فقط") stays below the card, unchanged.
- **Validate + publish panel** (`WorkflowPublishPanel`) stays as its own row below, unchanged behavior — it just moves visually under the new card.

The existing version/definition pickers fold into the summary card's title row rather than living in a separate strip.

UX writing (Arabic, formal MSA, per `PRODUCT.md` brand tone):
- Delete version button: `حذف النسخة`
- Delete definition menu item: `حذف مسار العمل`
- Delete-version confirm: `سيتم حذف النسخة «v2» نهائياً.` / action `تأكيد الحذف`
- Delete-definition confirm: `سيتم حذف مسار العمل «تمويل الواردات» وكل نسخه نهائياً.` / action `تأكيد الحذف`
- In-use error toast: server message verbatim (e.g. `لا يمكن حذف نسخة مرتبطة بطلبات.`).

### State after delete

- **Version deleted**: if other versions of the same definition exist, auto-select the newest remaining; otherwise fall through to definition-selection logic.
- **Definition deleted**: auto-select the first remaining definition's newest version; if no definitions remain, show the existing empty state.

## 2. Delete workflow version + definition (backend)

### Routes (`backend/routes/api.php`)

```php
Route::delete('workflow-versions/{workflowVersion}', [WorkflowVersionController::class, 'destroy']);
Route::delete('workflow-definitions/{workflowDefinition}', [WorkflowDefinitionController::class, 'destroy']);
```

### Controllers

`WorkflowVersionController::destroy(Request $request, WorkflowVersion $workflowVersion): JsonResponse`
- `$this->authorize('delete', $workflowVersion);`
- Try `$this->designer->deleteVersion($request->user(), $workflowVersion);`
- Catch `WorkflowDesignProtectionException` → `422` with `error.code = WORKFLOW_VERSION_IN_USE`.
- Return `204` on success.

`WorkflowDefinitionController::destroy(Request $request, WorkflowDefinition $workflowDefinition): JsonResponse`
- `$this->authorize('delete', $workflowDefinition);`
- Try `$this->designer->deleteDefinition($request->user(), $workflowDefinition);`
- Catch `WorkflowDesignProtectionException` → `422` with `error.code = WORKFLOW_DEFINITION_IN_USE`.
- Return `204` on success.

Both reuse the controller's existing private `error()` helper shape (`{error: {code, message, fields, request_id}}`) for consistency with the rest of the designer controllers.

### Service (`backend/app/Services/Workflow/WorkflowDesignerService.php`)

```php
public function deleteVersion(User $actor, WorkflowVersion $version): void
{
    DB::transaction(function () use ($actor, $version): void {
        $locked = WorkflowVersion::query()->lockForUpdate()->findOrFail($version->getKey());
        if (EngineRequest::query()->where('workflow_version_id', $locked->getKey())->exists()) {
            throw WorkflowDesignProtectionException::versionInUse();
        }
        $this->auditService->log(AuditAction::GOVERNANCE_DELETED, $actor, $locked, ['before' => $locked->toArray()]);
        $locked->delete();
    });
}

public function deleteDefinition(User $actor, WorkflowDefinition $definition): void
{
    DB::transaction(function () use ($actor, $definition): void {
        $locked = WorkflowDefinition::query()->lockForUpdate()->findOrFail($definition->getKey());
        $versionIds = $locked->versions()->pluck('id');
        if (EngineRequest::query()->whereIn('workflow_version_id', $versionIds)->exists()) {
            throw WorkflowDesignProtectionException::definitionInUse();
        }
        $this->auditService->log(AuditAction::GOVERNANCE_DELETED, $actor, $locked, ['before' => $locked->toArray()]);
        $locked->delete(); // cascades versions → stages/transitions/permissions/fields
    });
}
```

`EngineRequest` must be imported in the service. `WorkflowDesignProtectionException` gains two factory methods (`versionInUse()`, `definitionInUse()`) emitting the new error codes and Arabic messages; both map to HTTP 422 (the existing `WorkflowDesignProtectionException` is already surfaced as 422 by the stage controller, so the controller catch + `error()` call aligns).

### Policies

- `WorkflowVersionPolicy::delete(User $user, WorkflowVersion $version): bool` → `return $this->viewAny($user);` (MANAGE capability; state-agnostic — the request-link check lives in the service, not the policy).
- `WorkflowDefinitionPolicy::delete(User $user, WorkflowDefinition $definition): bool` → `return $this->viewAny($user);`.

### Why state-agnostic is safe here

A PUBLISHED version with zero requests is dead weight (nothing runs against it). Deleting it removes the row; the cascade handles subtree cleanup. The request-link check is the real invariant — a version with active or historical requests is the audit-sensitive case and is blocked. No FK from `engine_requests` to versions is left dangling because the check prevents the delete.

## 3. Workflow name + version on request

### Backend — `EngineRequestResource`

Add (alongside the existing `workflow_version_id`):

```php
'workflow_version' => $this->whenLoaded('workflowVersion', fn () => [
    'id' => $this->workflowVersion->id,
    'version_number' => (int) $this->workflowVersion->version_number,
    'state' => $this->workflowVersion->state->value,
    'definition' => $this->whenLoaded('workflowVersion.definition', fn () => [
        'id' => $this->workflowVersion->definition->id,
        'name' => $this->workflowVersion->definition->name,
        'code' => $this->workflowVersion->definition->code,
    ]),
]),
```

### Backend — eager loading

- `EngineRequestController::index` (list): add `'workflowVersion.definition'` to the list query's `with()`.
- `EngineRequestController::show` (detail): ensure `'workflowVersion.definition'` is loaded (it currently loads `workflowVersion`; add `.definition`).
- Any other endpoint returning `EngineRequestResource` for a list/detail context that should show workflow context gets the same eager-load — confirm during implementation by grepping `EngineRequestResource::collection` and `new EngineRequestResource` call sites.

### Frontend — type

`frontend/app/types/models.ts`, `EngineRequest` interface: add optional

```ts
workflow_version?: {
  id: number
  version_number: number
  state: WorkflowVersion['state']
  definition?: { id: number; name: string; code: string }
}
```

### Frontend — requests table (`frontend/app/pages/workflows/index.vue`)

New TanStack column after `reference` (or after `stage`, implementation judgment — recommended right after `reference` so the workflow identity is prominent):

```ts
{
  id: 'workflow',
  header: ({ column }) => h(DataTableColumnHeader as any, { column, title: 'مسار العمل' }),
  accessorFn: (row) => row.workflow_version?.definition?.name ?? '—',
  filterFn: (row, _id, value: string[]) =>
    value.includes(row.original.workflow_version?.definition?.name ?? '—'),
  cell: ({ row }) => {
    const v = row.original.workflow_version
    if (!v?.definition) return h('span', { class: 'text-muted-foreground text-sm' }, '—')
    return h(Badge, { variant: 'outline', class: 'font-normal' }, () => `${v.definition!.name} v${v.version_number}`)
  },
},
```

Add to `exportCols` so it appears in CSV exports.

### Frontend — request detail (`frontend/app/pages/workflows/instances/[id].vue`)

Add workflow name + version to the page header context — either in breadcrumbs (e.g. `طلبات التمويل / تمويل الواردات v2`) or in the `EngineRequestSummary`/quick-info area. Breadcrumbs are preferred (low-risk, matches existing pattern). Read from `store.current.workflow_version`.

## 4. Frontend delete composables + UI wiring

- `useWorkflows` composable gains:
  ```ts
  deleteVersion: (version) => api.del(`/api/v1/workflow-versions/${version.id}`)
  deleteDefinition: (definition) => api.del(`/api/v1/workflow-definitions/${definition.id}`)
  ```
  After a successful delete, refetch definitions (`fetchDefinitions()`) so the picker refreshes; the existing `watch(definitions, ...)` auto-selection logic handles picking a new selection.
- Summary card delete-version button → `AlertDialog` confirm → `deleteVersion` → on success toast + refetch; on `WORKFLOW_VERSION_IN_USE` (422) toast the server message and keep the dialog open.
- Summary card ⋯ menu → "حذف مسار العمل" → `AlertDialog` confirm → `deleteDefinition` → same success/error handling with `WORKFLOW_DEFINITION_IN_USE`.
- Both affordances wrapped in `<ScreenGuard screen="workflow_designer" capability="MANAGE">`.

## Testing / Verification

- Backend: focused PHPUnit
  - `WorkflowVersionTest::test_delete_version_with_no_requests` → 204, row gone.
  - `WorkflowVersionTest::test_delete_version_with_requests_is_rejected` → 422 `WORKFLOW_VERSION_IN_USE`, row remains.
  - `WorkflowVersionTest::test_non_admin_cannot_delete_version` → 403.
  - New `WorkflowDefinitionTest` (or extend existing): delete definition with no requests across any version → 204, definition + versions + stages gone; delete with a request on any version → 422 `WORKFLOW_DEFINITION_IN_USE`; non-admin → 403; audit log entry created.
- Frontend: Vitest
  - `useWorkflows` composable test for `deleteVersion`/`deleteDefinition` (mock `del`).
  - `workflows/index.vue` column test asserting the badge renders `تمويل الواردات v2` from a fixture.
  - Admin workflows page: summary card shows counts; delete-version dialog calls `deleteVersion`; in-use 422 keeps dialog open + toasts.
- Manual `playwright-cli` pass: delete the throwaway test definition created earlier in this session; verify a real in-use version's delete is blocked; verify the new column appears on `/workflows`; verify request detail breadcrumb shows workflow name+version.

## Risks

- **Cascade correctness**: confirm `workflow_versions.workflow_definition_id` and all subtree FKs are `cascadeOnDelete` (verified during investigation for versions→stages/transitions; double-check versions→definition FK direction during implementation).
- **Eager-load performance**: adding `workflowVersion.definition` to the list query is one extra join; negligible at this app's scale (~hundreds of requests, single tenant), but confirm via the existing list endpoint's query log during implementation.
- **Optimistic UI**: deletions must not be optimistic — wait for the 204 before removing from the picker, since a race (request created between the UI's last fetch and the delete) would otherwise leave a dangling reference. The composable's `await` + refetch pattern handles this.
