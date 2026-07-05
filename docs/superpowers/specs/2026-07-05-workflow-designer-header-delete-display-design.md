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

1. **Create-request picker (multi-workflow)** — `/workflows/new` opens a picker dialog for published workflow definition + version; on confirm a DRAFT request is created bound to that version and the user is taken to the instance page to fill data.
2. **screen-permissions requests column removed** — stage permissions can now be org-only/org+team (no role required), so a role-keyed "requests" column can no longer represent access correctly; remove it rather than re-scope it.
3. **Header redesign** — replace the flat `PageHeader` + scattered pickers/publish-panel with a single summary card showing definition + version context and clustering all actions (clone, delete version, delete definition).
4. **Delete workflow version + definition** — hard-delete a version not referenced by any `engine_requests` row, and hard-delete a whole definition when none of its versions are referenced. State-agnostic (DRAFT/PUBLISHED/ARCHIVED does not gate delete — only the request-link check does).
5. **Workflow name + version on request** — expose definition name + version number through `EngineRequestResource`, show it as a new column on the requests table, and add it to the request detail header/breadcrumbs.

## Non-goals

- No soft-delete / restore (audit trail covers history).
- No bulk delete.
- No reassigning requests to a different version before delete — if a version/definition has requests, delete is blocked.
- No change to the create/clone/validate/publish/archive flows beyond relocating their buttons into the new summary card.
- No workflow-context display on Data Entry simplified views (Data Entry already gets simplified statuses and does not see internal CBY workflow structure — keep that boundary).

---

## 0. Create-request workflow/version picker + multi-workflow permission scoping

The codebase now supports multiple workflow definitions and versions. Two flows that previously assumed a single workflow must become version-aware.

### 0a. Create-request picker dialog (`/workflows/new` entry)

**Decision (confirmed):** picker dialog opens FIRST → on confirm, backend creates a DRAFT `engine_request` bound to the chosen PUBLISHED version with empty data → redirect to `/workflows/instances/{newId}` → user fills request data on the instance page (its existing wizard/edit mode handles entry). The old standalone multi-field `/workflows/new` form is retired; the page becomes the picker-dialog host (and the queue's "إنشاء طلب" button can open the same dialog).

**What already exists (no new backend needed):**
- `POST /api/v1/engine-requests` (`EngineRequestController::store`) already requires `workflow_version_id` and has `merchant_id` nullable + amount/data optional — it already supports creating a bare DRAFT bound to a version.
- `GET /api/v1/engine-requests/available-workflows` (`availableWorkflows`) already returns the exact picker dataset: PUBLISHED versions, with `definition.{id,code,name}` + `version_id` + `version_number`, filtered to versions whose initial stage the caller can EXECUTE. This is the picker's data source.
- `POST /api/v1/engine-requests/{id}/draft` exists for incremental saves from the instance page.

**Frontend change:**
- New picker dialog component (or inline dialog in `/workflows/new`): two cascading `<Select>`s — definition first, then published version (versions filtered to the chosen definition; only PUBLISHED). Backed by `availableWorkflows` (already pre-filtered to executable initial stages, so the list is the user's actually-usable workflows).
- On confirm → `POST /api/v1/engine-requests { workflow_version_id }` (empty body otherwise) → on 201, `router.replace('/workflows/instances/' + newId)`.
- Cancel → back to `/workflows` queue.
- If `availableWorkflows` returns exactly one entry, the dialog can pre-select it but still requires explicit confirm (do NOT auto-create — confirmed decision is dialog-first always, so the user never accidentally creates a request).

**Risk note:** a bare DRAFT request with no merchant/amount now exists momentarily. The instance page's edit/wizard mode must render gracefully for an empty DRAFT (it already handles DRAFT state for partially-filled requests). Verify during implementation that no required-field guard on the instance page blocks rendering an empty DRAFT; if one does, relax it for DRAFT (required fields are enforced at submit/transition, not at render).

### 0b. screen-permissions matrix — remove the display column; fix the runtime derivation it shares code with

**Decision (confirmed, supersedes an earlier draft of this section):** stage permissions no longer require a role — a row can be org-only, org+team, org+role, or org+team+role (see `feat/workflow-permission-optional-team-role`, commit `13f1c40b`, already landed: `StagePermissionResolver` already treated null `team_id`/`role_id` as wildcards; only the Form Request validation and dialog were tightened to require all three, and that tightening is now reverted — org required, team/role independently optional). Once a permission row can grant access without naming a role at all (org-only, or org+team with no role), **role is no longer sufficient to compute "can this role access requests"** as a single matrix cell. An org-only row grants access to every user in that organization regardless of role; a role-keyed matrix row cannot represent that. The matrix column's premise (one row = one role → derived capability) is broken by design, not just stale — **remove the display column**.

**Important — `derivedRequestsCapabilities()` is not display-only.** Investigation found `PermissionService::derivedRequestsCapabilities(array $roleIds)` has two callers, not one:
1. `RoleScreenPermissionController::matrix()` — the display column being removed.
2. `PermissionService::screenPermissionsForGovernanceRole(int $roleId)` (`PermissionService.php:47`) — a **live, cached, production-facing** method whose docblock states it is "the single source of truth for `requests` screen capability — used by both the screen-permissions matrix display and runtime enforcement." This feeds `screenPermissionsForUser()` → `AuthMeResource` → `GET /api/auth/me`, which the frontend uses to decide what UI (`ScreenGuard`) to render for the logged-in user. This is real request-access gating, not a preview.

Both callers currently share the same broken assumption: `derivedRequestsCapabilities` picks one hardcoded version (`workflow_versions where state=PUBLISHED order by version_number desc limit 1`, ignoring definition), and computes access from `stage_permissions.role_id` only. With multiple published workflow definitions/versions and now-optional role scoping, this under- or over-grants `requests` screen access at login.

**Decision (confirmed):** fix `derivedRequestsCapabilities` properly rather than deferring — it stays load-bearing for `/auth/me`, only the matrix's *display* of it goes away.

**Backend change:**
- `derivedRequestsCapabilities(array $roleIds): array` is rewritten to be correct for multi-version + optional-role rows:
  - Iterate stages across **all PUBLISHED versions**, dropping the current `ORDER BY version_number DESC LIMIT 1` (which silently picks one definition's version and ignores every other definition's published version). This is safe and simple because `WorkflowDesignerService::publish()` already enforces at most one PUBLISHED version per definition at a time (archiving the prior one on publish) — so `WHERE state = PUBLISHED` with no `LIMIT` naturally yields exactly one row per definition, never two versions of the same definition competing. A role/org/team identity derives `requests` access if it matches `stage_permissions` on the stages of ANY of those rows (i.e. any currently-published workflow, not just whichever has the numerically highest version id). Existing test `DerivedRequestsEnforcementTest::test_publishing_new_workflow_version_changes_effective_requests_capability` (archives v1, publishes v2, expects the role's access to now come from v2 only) continues to pass unmodified under this fix, since v1 is ARCHIVED (excluded by the `state = PUBLISHED` filter) by the time v2 is checked — the fix only changes behavior when **multiple different definitions** each have their own published version simultaneously, which is the actual bug this ask is about.
  - Match using the same identity-set semantics as `StagePermissionResolver` (org/team/role/user, null = wildcard, AND within a row, OR across rows) rather than a role-only join — since a role-only computation cannot see org-only rows that would grant every member of that org access regardless of role.
  - **Precise scope of what a role-id-only signature can and cannot resolve**, verified against the schema (`roles.organization_id` is a required, non-null FK — every role belongs to exactly one organization): `derivedRequestsCapabilities(array $roleIds)` CAN still correctly resolve **org-only** and **org+role** `stage_permissions` rows, since a role's organization is always derivable from the role itself (1:1). It CANNOT correctly resolve **org+team** rows (with or without a role), because a role's members are not confined to one team — `roles` and `teams` are independent, possibly many-to-many-via-users groupings within an org, so "does this role have access via an org+team row" has no single answer per role; two different users holding the same role but different team memberships would have genuinely different actual access, yet a role-keyed cache can only store one answer.
  - **Resolution:** extend `derivedRequestsCapabilities` to resolve org-only and org+role rows correctly (fixing the multi-definition bug for the common case), and add an explicit, documented limitation for org+team rows: a role is treated as matching an org+team row only if this method has no way to know differently — i.e. this signature undercounts org+team-scoped access consistently, which is the closest safe default (a false negative in `/auth/me`'s `requests` capability only hides UI the user could technically still reach at the resolver level for actual actions, since `StagePermissionResolver` remains the real enforcement gate on `EngineTransitionService`/queue endpoints — this method only drives what the frontend chrome shows). Document this limitation inline in the method's docblock so a future reader does not mistake it for a bug in the resolver itself.
  - Reuse `StagePermissionResolver`'s row-matching semantics (`rowMatches`) for the org/role matching above rather than re-deriving separate SQL/PHP logic that could drift from the resolver's documented AND/OR/wildcard rules — inject `StagePermissionResolver` into `PermissionService` or extract the pure matching function so both stay in lockstep.
- `RoleScreenPermissionController::matrix()`: remove `'requests' => $derived[$role->id] ?? [...]` from the row mapping and remove its `derivedRequestsCapabilities()` call (this caller goes away entirely).
- `PermissionService::screenPermissionsForGovernanceRole(int $roleId)`: keep its signature and 1-hour `screen_permissions.role.{$roleId}` cache key unchanged — the fix above stays role-id-resolvable (org derived from the role, no per-user context needed) for org-only/org+role rows, so no cache-key or signature change is required. Only the internal query changes (drop the single-latest-published-version assumption; add the org-only/org+role matching).

**Frontend change (`/admin/screen-permissions.vue`):**
- Remove the "الطلبات" column: its header cell, its per-role data cells, the `REQUESTS_KEY` constant if solely used for this column, and the explanatory note ("صلاحيات الطلبات مشتقة من مصمم سير العمل" / "الطلبات مشتقة إلزاميًا من إسنادات المراحل...") since it's no longer accurate as a role-only preview.
- No replacement column, no selectors — the earlier "keep + add selectors" direction is dropped in favor of removal.
- Point admins who want to inspect request-access for a specific org/team/role combination at the workflow designer's "سير العملية التنظيمية" tab instead (already shows org/team/role per stage-permission row); optionally add a one-line hint in the matrix page's remaining subtitle pointing there.

**Backend tests to update/add:**
- `PermissionServiceDerivedRequestsTest.php` (exists, 2 tests, both currently pass and must keep passing) needs new cases: (1) an org-only `stage_permissions` row (no role_id) grants `derivedRequestsCapabilities` access to a role belonging to that org; (2) two different definitions each with their own currently-PUBLISHED version both contribute — a role matching only the second definition's stage still gets correct capabilities (this is the actual multi-definition bug fix, verified directly); (3) a role with no matching row on any published version across multiple definitions still gets `false` for all three capabilities (multi-definition version of the existing "no assignments" test).
- `DerivedRequestsEnforcementTest.php` (exists, 4 tests) must continue passing unmodified — re-run it after the fix as a regression guard, since it already covers cache-busting on publish/archive and the workflow-not-static-grants precedence rule this fix must not disturb.
- No new test is added for the org+team limitation (it's a documented, deliberate scope boundary for this role-id-only method, not a bug to close) — but add one line to the method's docblock stating org+team-scoped rows are not resolvable from a role id alone and are treated as non-matching for this derivation, so a future maintainer doesn't "fix" it into a per-user cache-key change without re-reading this rationale.
- `RoleScreenPermissionController` matrix test (if one exists covering the `requests` key) updated to assert the `requests` key is absent from matrix rows; if no such test exists yet, add one asserting the matrix response's role rows never contain a `requests` key.

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
  - `EngineRequestControllerTest` (or wherever `store` is covered): creating a request with only `workflow_version_id` (no merchant/amount) still returns 201 with a DRAFT request — locks in the ask-0a contract as a regression guard, since nothing currently exercises the "bare create" path explicitly.
  - `PermissionServiceDerivedRequestsTest.php` + `DerivedRequestsEnforcementTest.php` per the ask-0b test list above.
  - `RoleScreenPermissionController` matrix test: `requests` key absent from every role row after the column removal.
- Frontend: Vitest
  - New picker-dialog component test: renders definition/version selects from a mocked `availableWorkflows` response; confirm calls `POST /engine-requests` with only `workflow_version_id`; cancel does not call the API.
  - `useWorkflows` composable test for `deleteVersion`/`deleteDefinition` (mock `del`).
  - `workflows/index.vue` column test asserting the badge renders `تمويل الواردات v2` from a fixture.
  - `admin/screen-permissions.vue` test asserting the requests column/header/note are no longer rendered.
  - Admin workflows page: summary card shows counts; delete-version dialog calls `deleteVersion`; in-use 422 keeps dialog open + toasts.
- Manual `playwright-cli` pass: create a request through the new picker end-to-end (pick workflow+version, land on instance page, confirm empty DRAFT renders); delete the throwaway test definition created earlier in this session; verify a real in-use version's delete is blocked; verify the new column appears on `/workflows`; verify request detail breadcrumb shows workflow name+version; verify `/admin/screen-permissions` no longer shows a requests column.

## Risks

- **Cascade correctness**: confirm `workflow_versions.workflow_definition_id` and all subtree FKs are `cascadeOnDelete` (verified during investigation for versions→stages/transitions; double-check versions→definition FK direction during implementation).
- **Eager-load performance**: adding `workflowVersion.definition` to the list query is one extra join; negligible at this app's scale (~hundreds of requests, single tenant), but confirm via the existing list endpoint's query log during implementation.
- **Optimistic UI**: deletions must not be optimistic — wait for the 204 before removing from the picker, since a race (request created between the UI's last fetch and the delete) would otherwise leave a dangling reference. The composable's `await` + refetch pattern handles this.
