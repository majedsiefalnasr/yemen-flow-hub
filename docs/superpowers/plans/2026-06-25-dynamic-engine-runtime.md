# Dynamic Engine Runtime (Workstream A + B) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `engine_requests` usable end-to-end — a requester can create an instance, fill a dynamically-rendered stage form, execute transitions, and view history, all through new `/workflows` routes that sit alongside the legacy `/requests` system without touching it.

**Architecture:** Two small additive backend endpoints close real permission/data-shape gaps found during planning (a requester-facing "which workflows can I start" list, and a requester-facing "resolved form schema for my current stage" read). Everything else is frontend: new composables/store calling the already-complete `EngineRequestController` API, a new `/workflows` route tree, and a `DynamicForm` component that builds a Zod schema at runtime from field metadata instead of a hand-written one.

**Tech Stack:** Laravel 11 (PHP 8.2+), Nuxt 4/Vue, VeeValidate + Zod, Pinia, shadcn-vue.

## Global Constraints

- New `/workflows` routes only — do not modify any file under `frontend/app/pages/requests/`, `frontend/app/composables/useRequests.ts`, `frontend/app/stores/requests.store.ts`, or any legacy `ImportRequest`-based backend code. Zero changes to legacy system.
- Frontend trusts the backend completely for permission decisions. No client-side re-derivation of `StagePermissionResolver` logic. Render what the API returns; let 403s happen and handle them, don't pre-filter to avoid them.
- Screen-gate new pages with the existing `'requests'` screen key (already granted `VIEW`/`CREATE`/`UPDATE` to the right roles in `ScreenPermissionSeeder`) via `<ScreenGuard screen="requests" capability="...">` and `definePageMeta({ requiredScreen: 'requests' })` pattern — do not add new screen keys or touch the seeder.
- No raw HTML — every form control, table, button, dialog must use the shadcn-vue primitives per `frontend/SHADCN.md`. `Field`/`FieldLabel`/`FieldError` wrap every dynamic field.
- All Arabic-first, RTL (`dir="rtl"`), using existing semantic design tokens (`var(--severity-*)`, etc.) — never raw Tailwind color classes.
- Every backend/frontend change is committed once in the root monorepo, staging paths as `backend/<files>` or `frontend/<files>` per `AGENTS.md`. Conventional commit format `type(scope): description`, signed, `Co-Authored-By: Claude <noreply@anthropic.com>`.
- Backend: `php artisan test --filter=...` for touched test files only; `vendor/bin/pint <file> --test` for touched PHP files. Frontend: `pnpm exec vitest run <file>` for touched test files; `pnpm exec eslint <file>` for touched files. Run `pnpm typecheck` after adding new types in Task 9. No full suites unless explicitly requested.

---

## Task 1: Backend — `GET /api/v1/engine-requests/available-workflows`

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php`
- Modify: `backend/routes/api.php:169` (add route right after the existing `store` line)
- Test: `backend/tests/Feature/Engine/EngineRequestTest.php`

**Interfaces:**
- Consumes: `App\Services\Workflow\StagePermissionResolver::userCanAccessStage(User $user, WorkflowStage $stage, StageAccessLevel $level): bool` (existing), `App\Enums\StageAccessLevel::EXECUTE` (existing), `App\Models\WorkflowVersion` `state` column = `'PUBLISHED'` (existing), `User::hasPermission(string $slug): bool` is NOT used here — gate is screen-permission based, see below.
- Produces: `GET /api/v1/engine-requests/available-workflows` → `{"data": [{"id": int, "code": string, "name": string, "version_id": int, "version_number": int}]}`. Later tasks (Task 5's `useEngineRequests.ts`) call this exact shape.

This endpoint lists every `WorkflowDefinition` that has a `PUBLISHED` version with an initial stage the current user can EXECUTE — i.e. workflows the user is actually allowed to start an instance of. Gate: any authenticated active user with `screen_permissions` `CREATE` on `'requests'` (checked via `App\Services\Permissions\PermissionService::userHasCapability($user, 'requests', 'CREATE')` — confirm exact class/namespace by reading the file before writing the import, it was read during planning as `app/Services/.../PermissionService.php`, method signature `userHasCapability(User $user, string $screenKey, string $capability): bool`).

- [ ] **Step 1: Write the failing test**

Add to `backend/tests/Feature/Engine/EngineRequestTest.php` (inside the existing test class, using its existing `$this->executor`, `$this->version`, `$this->initialStage` fixtures already set up in `setUp()`):

```php
public function test_available_workflows_lists_published_versions_the_user_can_start(): void
{
    $response = $this->actingAs($this->executor)
        ->getJson('/api/v1/engine-requests/available-workflows');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('version_id')->all();
    $this->assertContains($this->version->id, $ids);
}

public function test_available_workflows_excludes_draft_versions(): void
{
    $draftVersion = WorkflowVersion::factory()->create([
        'workflow_definition_id' => $this->version->workflow_definition_id,
        'state' => WorkflowVersionState::DRAFT,
        'version_number' => $this->version->version_number + 1,
    ]);

    $response = $this->actingAs($this->executor)
        ->getJson('/api/v1/engine-requests/available-workflows');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('version_id')->all();
    $this->assertNotContains($draftVersion->id, $ids);
}

public function test_available_workflows_excludes_users_without_create_capability(): void
{
    $noAccessUser = User::factory()->create(['bank_id' => null, 'is_active' => true]);

    $response = $this->actingAs($noAccessUser)
        ->getJson('/api/v1/engine-requests/available-workflows');

    $response->assertForbidden();
}
```

Check `backend/database/factories/WorkflowVersionFactory.php` exists before relying on `WorkflowVersion::factory()` — if it doesn't exist, read how `$this->version` is constructed in `setUp()` of the same test file and replicate that construction inline instead of using a factory.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_available_workflows_lists_published_versions_the_user_can_start`
Expected: FAIL — 404 Not Found (route doesn't exist yet).

- [ ] **Step 3: Add the route**

In `backend/routes/api.php`, immediately after line 169 (`Route::post('engine-requests', [EngineRequestController::class, 'store']);`), add:

```php
    Route::get('engine-requests/available-workflows', [EngineRequestController::class, 'availableWorkflows']);
```

Place it **before** the `engine-requests/{engineRequest}` routes (line 170 onward) so Laravel's router doesn't try to resolve `available-workflows` as an `{engineRequest}` route-model-binding parameter.

- [ ] **Step 4: Implement the controller method**

In `backend/app/Http/Controllers/Api/V1/EngineRequestController.php`, add this method right after `store()` (after its closing brace, before `show()`):

```php
    public function availableWorkflows(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! app(\App\Services\Permissions\PermissionService::class)->userHasCapability($user, 'requests', 'CREATE')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create requests.',
            ], 403);
        }

        $versions = WorkflowVersion::query()
            ->where('state', 'PUBLISHED')
            ->with('workflowDefinition')
            ->get()
            ->filter(function (WorkflowVersion $version) use ($user): bool {
                $initialStage = $version->stages()->where('is_initial', true)->first();
                if ($initialStage === null) {
                    return false;
                }

                return $this->permissionResolver->userCanAccessStage($user, $initialStage, StageAccessLevel::EXECUTE);
            })
            ->map(fn (WorkflowVersion $version): array => [
                'id' => $version->workflowDefinition->id,
                'code' => $version->workflowDefinition->code,
                'name' => $version->workflowDefinition->name,
                'version_id' => $version->id,
                'version_number' => $version->version_number,
            ])
            ->values();

        return response()->json(['data' => $versions]);
    }
```

Verify the exact namespace of `PermissionService` before writing the import — re-grep `grep -rn "class PermissionService" backend/app/Services/` if unsure; use a `use` statement at the top of the file instead of the fully-qualified inline call if that's the existing file's style (check the top of `EngineRequestController.php` for its current `use` block and match it).

Verify `WorkflowVersion` has a `workflowDefinition(): BelongsTo` relation — grep `backend/app/Models/WorkflowVersion.php` for `function workflowDefinition` before assuming the name; if it's named differently (e.g. `definition()`), use that name instead in both the `with()` call and the `map()` closure.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=EngineRequestTest`
Expected: PASS (all three new tests, plus the existing suite in this file still green).

- [ ] **Step 6: Pint check**

Run: `vendor/bin/pint backend/app/Http/Controllers/Api/V1/EngineRequestController.php --test`
Expected: no style violations. If violations reported, run without `--test` to auto-fix, then re-verify tests still pass.

- [ ] **Step 7: Commit**

```bash
cd backend
git add app/Http/Controllers/Api/V1/EngineRequestController.php routes/api.php tests/Feature/Engine/EngineRequestTest.php
git commit -m "feat(workflow): add available-workflows endpoint for engine request creation"
cd ..
git add backend/app/Http/Controllers/Api/V1/EngineRequestController.php backend/routes/api.php backend/tests/Feature/Engine/EngineRequestTest.php
git commit -m "feat(workflow): add available-workflows endpoint for engine request creation"
```

---

## Task 2: Backend — `GET /api/v1/engine-requests/{engineRequest}/form-schema`

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php`
- Modify: `backend/routes/api.php` (add route inside the `engine-requests/{engineRequest}` group)
- Test: `backend/tests/Feature/Engine/EngineRequestTest.php`

**Interfaces:**
- Consumes: `App\Services\Workflow\StageFieldRuleValidator` is NOT reused directly (it validates, doesn't shape a response) — this task computes the same "effective rule" merge (`is_visible`/`is_editable`/`is_required` = stage rule if present else field default) inline, matching the logic already in `StageFieldRuleValidator::validateData()` lines computing `$isVisible`/`$isEditable`/`$isRequired`. `App\Services\Workflow\DynamicFieldOptionsResolver::resolve(FieldDefinition $field): array` (existing, reused as-is for `DYNAMIC_SELECT` fields).
- Produces: `GET /api/v1/engine-requests/{id}/form-schema` → `{"data": {"field_groups": [{"id": int, "name": string, "label": string, "sort_order": int, "fields": [{...FieldDefinitionResource shape..., "is_visible": bool, "is_editable": bool, "is_required": bool, "dynamic_options": [{"value": ..., "label": string}] | null}]}]}}`. The per-field `is_visible`/`is_editable`/`is_required` here are the STAGE-EFFECTIVE values (already merged), not the raw field defaults — Task 7's `useDynamicFormSchema.ts` consumes these merged values directly without re-deriving them.

This is the form-schema read endpoint a requester needs to render `DynamicForm` for an instance's current stage, gated the same way as `show()` (`$this->authorize('view', $engineRequest)`), not the designer-only `workflow.design` permission.

- [ ] **Step 1: Write the failing test**

Add to `backend/tests/Feature/Engine/EngineRequestTest.php`:

```php
public function test_form_schema_returns_merged_stage_effective_rules(): void
{
    $group = FieldGroup::factory()->create([
        'workflow_version_id' => $this->version->id,
    ]);
    $field = FieldDefinition::factory()->create([
        'workflow_version_id' => $this->version->id,
        'field_group_id' => $group->id,
        'key' => 'invoice_amount',
        'type' => \App\Enums\FieldType::NUMBER,
        'is_required' => false,
    ]);
    \App\Models\StageFieldRule::factory()->create([
        'stage_id' => $this->initialStage->id,
        'field_id' => $field->id,
        'is_visible' => true,
        'is_editable' => true,
        'is_required' => true,
    ]);

    $engineRequest = EngineRequest::factory()->create([
        'workflow_version_id' => $this->version->id,
        'current_stage_id' => $this->initialStage->id,
        'created_by' => $this->executor->id,
    ]);

    $response = $this->actingAs($this->executor)
        ->getJson("/api/v1/engine-requests/{$engineRequest->id}/form-schema");

    $response->assertOk();
    $groups = $response->json('data.field_groups');
    $returnedField = collect($groups)[0]['fields'][0];
    $this->assertSame('invoice_amount', $returnedField['key']);
    $this->assertTrue($returnedField['is_required']); // stage rule overrides field default false
}

public function test_form_schema_forbidden_for_user_outside_scope(): void
{
    $engineRequest = EngineRequest::factory()->create([
        'workflow_version_id' => $this->version->id,
        'current_stage_id' => $this->initialStage->id,
        'created_by' => $this->executor->id,
        'bank_id' => Bank::factory()->create()->id,
    ]);

    $response = $this->actingAs($this->outsideUser)
        ->getJson("/api/v1/engine-requests/{$engineRequest->id}/form-schema");

    $response->assertForbidden();
}
```

Check whether `FieldGroup::factory()`, `FieldDefinition::factory()`, `StageFieldRule::factory()`, `EngineRequest::factory()` exist (`find backend/database/factories -iname "*Field*" -o -iname "*EngineRequest*"`) before writing this — if any factory is missing, replicate the inline-creation pattern already used elsewhere in `EngineRequestTest.php`'s `setUp()` for that model instead of calling a nonexistent factory.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_form_schema_returns_merged_stage_effective_rules`
Expected: FAIL — 404 Not Found.

- [ ] **Step 3: Add the route**

In `backend/routes/api.php`, immediately after line 170 (`Route::get('engine-requests/{engineRequest}', [EngineRequestController::class, 'show']);`), add:

```php
    Route::get('engine-requests/{engineRequest}/form-schema', [EngineRequestController::class, 'formSchema']);
```

- [ ] **Step 4: Implement the controller method**

Add to `EngineRequestController.php`, right after `show()`:

```php
    public function formSchema(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $stage = $engineRequest->currentStage;
        $fields = \App\Models\FieldDefinition::query()
            ->where('workflow_version_id', $engineRequest->workflow_version_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $rulesByFieldId = $stage->stageFieldRules()->get()->keyBy('field_id');
        $groups = \App\Models\FieldGroup::query()
            ->where('workflow_version_id', $engineRequest->workflow_version_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $optionsResolver = app(\App\Services\Workflow\DynamicFieldOptionsResolver::class);

        $fieldsByGroup = $fields->groupBy('field_group_id');

        $data = $groups->map(function ($group) use ($fieldsByGroup, $rulesByFieldId, $optionsResolver): array {
            $groupFields = ($fieldsByGroup->get($group->id) ?? collect())
                ->map(function ($field) use ($rulesByFieldId, $optionsResolver): array {
                    $rule = $rulesByFieldId->get($field->id);

                    return [
                        'id' => $field->id,
                        'key' => $field->key,
                        'label' => $field->label,
                        'type' => $field->type->value,
                        'placeholder' => $field->placeholder,
                        'help_text' => $field->help_text,
                        'default_value' => $field->default_value,
                        'min_value' => $field->min_value !== null ? (float) $field->min_value : null,
                        'max_value' => $field->max_value !== null ? (float) $field->max_value : null,
                        'min_length' => $field->min_length,
                        'max_length' => $field->max_length,
                        'regex_pattern' => $field->regex_pattern,
                        'options' => $field->options,
                        'dynamic_source' => $field->dynamic_source?->value,
                        'allowed_file_types' => $field->allowed_file_types,
                        'max_file_size' => $field->max_file_size,
                        'multiple' => (bool) $field->multiple,
                        'is_visible' => $rule?->is_visible ?? true,
                        'is_editable' => $rule?->is_editable ?? true,
                        'is_required' => $rule?->is_required ?? (bool) $field->is_required,
                        'dynamic_options' => $field->dynamic_source !== null ? $optionsResolver->resolve($field) : null,
                    ];
                })
                ->values();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'label' => $group->label,
                'sort_order' => $group->sort_order,
                'fields' => $groupFields,
            ];
        })->values();

        return response()->json(['data' => ['field_groups' => $data]]);
    }
```

Verify `EngineRequest::currentStage(): BelongsTo` relation exists (it's used already in `index()`'s `->with(['currentStage', ...])`) — confirmed during planning, no need to re-check.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=EngineRequestTest`
Expected: PASS (all tests in the file, including Task 1's three and this task's two).

- [ ] **Step 6: Pint check**

Run: `vendor/bin/pint backend/app/Http/Controllers/Api/V1/EngineRequestController.php --test`
Expected: no violations (fix and re-verify if any).

- [ ] **Step 7: Commit**

```bash
cd backend
git add app/Http/Controllers/Api/V1/EngineRequestController.php routes/api.php tests/Feature/Engine/EngineRequestTest.php
git commit -m "feat(workflow): add resolved form-schema endpoint for engine request stages"
cd ..
git add backend/app/Http/Controllers/Api/V1/EngineRequestController.php backend/routes/api.php backend/tests/Feature/Engine/EngineRequestTest.php
git commit -m "feat(workflow): add resolved form-schema endpoint for engine request stages"
```

---

## Task 3: Frontend — TypeScript types for engine requests

**Files:**
- Modify: `frontend/app/types/models.ts` (append after the existing `StagePermission` interface, around line 866)
- Test: none (pure type additions; verified via `pnpm typecheck` at the end of this task and re-verified after every later task that imports these types)

**Interfaces:**
- Consumes: existing `FieldType`, `DynamicFieldSource`, `WorkflowGraph` types already in this file (read above, lines 752-866).
- Produces: `EngineRequestStatus`, `EngineRequest`, `EngineRequestDocument`, `EngineHistoryEntry`, `AvailableWorkflow`, `ResolvedFieldGroup`, `ResolvedFieldDefinition`, `EngineFormSchema` — all consumed by Tasks 4-9.

- [ ] **Step 1: Add the types**

Append to `frontend/app/types/models.ts`, immediately after the `StagePermission` interface (after its closing `}` around line 865):

```ts
export type EngineRequestStatus = 'ACTIVE' | 'CLOSED' | 'REJECTED'

export interface EngineRequest {
  id: number
  reference: string
  status: EngineRequestStatus
  version: number
  workflow_version_id: number
  current_stage: {
    id: number
    code: string
    name: string
    is_initial: boolean
    is_final: boolean
    sla_duration_minutes: number | null
  } | null
  bank_id: number | null
  bank: { id: number; name: string; code: string | null } | null
  merchant_id: number | null
  merchant: { id: number; name: string } | null
  data: Record<string, unknown>
  amount: number | null
  currency: string | null
  invoice_number: string | null
  sla_status: string | null
  created_by: number
  creator: { id: number; name: string } | null
  created_at: string | null
  updated_at: string | null
}

export interface EngineRequestDocument {
  id: number
  request_id: number
  field_id: number | null
  stage_id: number
  original_name: string
  mime: string
  size: number
  uploaded_by: { id: number; name: string } | number
  created_at: string | null
}

export interface EngineHistoryEntry {
  id: number
  from_stage: { id: number; code: string; name: string } | null
  to_stage: { id: number; code: string; name: string } | null
  action_code: string | null
  performed_by: { id: number; name: string } | null
  comments: string | null
  created_at: string | null
}

export interface AvailableWorkflow {
  id: number
  code: string
  name: string
  version_id: number
  version_number: number
}

export interface ResolvedFieldDefinition {
  id: number
  key: string
  label: string
  type: FieldType
  placeholder: string | null
  help_text: string | null
  default_value: string | null
  min_value: number | null
  max_value: number | null
  min_length: number | null
  max_length: number | null
  regex_pattern: string | null
  options: Array<{ value: string; label: string }> | null
  dynamic_source: DynamicFieldSource | null
  allowed_file_types: string[] | null
  max_file_size: number | null
  multiple: boolean
  is_visible: boolean
  is_editable: boolean
  is_required: boolean
  dynamic_options: Array<{ value: string | number; label: string }> | null
}

export interface ResolvedFieldGroup {
  id: number
  name: string
  label: string
  sort_order: number
  fields: ResolvedFieldDefinition[]
}

export interface EngineFormSchema {
  field_groups: ResolvedFieldGroup[]
}
```

- [ ] **Step 2: Verify types compile**

Run: `pnpm typecheck`
Expected: no new errors introduced (pre-existing unrelated errors, if any, are not this task's concern — only check no NEW errors reference the lines just added).

- [ ] **Step 3: Commit**

```bash
cd frontend
git add app/types/models.ts
git commit -m "feat(workflow): add TypeScript types for engine request runtime"
cd ..
git add frontend/app/types/models.ts
git commit -m "feat(workflow): add TypeScript types for engine request runtime"
```

---

## Task 4: Frontend — `useEngineRequests.ts` composable (list, queue, create, show, draft)

**Files:**
- Create: `frontend/app/composables/useEngineRequests.ts`
- Test: `frontend/app/tests/unit/composables/useEngineRequests.test.ts`

**Interfaces:**
- Consumes: `useApi()` from `frontend/app/composables/useApi.ts` (existing — has `.get<T>(url, opts)`, `.post<T>(url, body)`, `.patch<T>(url, body)` methods, confirm exact method names by reading the file's remaining ~40 lines below what was already read if `.patch` isn't confirmed — grep `grep -n "function get\|function post\|function patch" frontend/app/composables/useApi.ts` before writing calls), `extractApiErrorMessage` from `frontend/app/utils/apiErrors.ts` (existing), types from Task 3 (`EngineRequest`, `AvailableWorkflow`, `PaginatedResponse` — `PaginatedResponse<T>` already exists in `models.ts`, confirmed via its use in `useWorkflows.ts`).
- Produces: `useEngineRequests()` returning `{ instances: Ref<EngineRequest[]>, instancesMeta, queue: Ref<EngineRequest[]>, queueMeta, availableWorkflows: Ref<AvailableWorkflow[]>, current: Ref<EngineRequest | null>, loading: Ref<boolean>, error: Ref<string | null>, fetchList(options), fetchQueue(options), fetchAvailableWorkflows(), create(payload): Promise<EngineRequest>, show(id): Promise<EngineRequest>, saveDraft(id, data, version): Promise<EngineRequest> }`. Task 6 (pages) and Task 8 (store) consume this exact shape.

- [ ] **Step 1: Write the failing test**

Create `frontend/app/tests/unit/composables/useEngineRequests.test.ts`:

```ts
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useEngineRequests } from '@/composables/useEngineRequests'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPatch = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, patch: mockPatch }),
}))

describe('useEngineRequests', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
    mockPatch.mockReset()
  })

  it('fetchList populates instances and meta on success', async () => {
    mockGet.mockResolvedValue({
      data: [{ id: 1, reference: 'ENG-2026-000001' }],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
    })
    const { instances, instancesMeta, fetchList } = useEngineRequests()

    await fetchList()

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests', expect.any(Object))
    expect(instances.value).toHaveLength(1)
    expect(instancesMeta.value?.total).toBe(1)
  })

  it('fetchList sets error message on failure', async () => {
    mockGet.mockRejectedValue({ data: { message: 'فشل' } })
    const { instances, error, fetchList } = useEngineRequests()

    await fetchList()

    expect(instances.value).toEqual([])
    expect(error.value).toBe('فشل')
  })

  it('fetchQueue calls the my-queue endpoint', async () => {
    mockGet.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 } })
    const { fetchQueue } = useEngineRequests()

    await fetchQueue()

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/my-queue', expect.any(Object))
  })

  it('fetchAvailableWorkflows populates availableWorkflows', async () => {
    mockGet.mockResolvedValue({ data: [{ id: 1, code: 'IMPORT_FINANCING', name: 'تمويل الواردات', version_id: 10, version_number: 1 }] })
    const { availableWorkflows, fetchAvailableWorkflows } = useEngineRequests()

    await fetchAvailableWorkflows()

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/available-workflows')
    expect(availableWorkflows.value).toHaveLength(1)
  })

  it('create posts payload and returns the created instance', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 5, reference: 'ENG-2026-000005' } })
    const { create } = useEngineRequests()

    const result = await create({ workflow_version_id: 10, data: {} })

    expect(mockPost).toHaveBeenCalledWith('/api/v1/engine-requests', { workflow_version_id: 10, data: {} })
    expect(result.id).toBe(5)
  })

  it('show fetches a single instance by id', async () => {
    mockGet.mockResolvedValue({ success: true, data: { id: 5, reference: 'ENG-2026-000005' } })
    const { show, current } = useEngineRequests()

    const result = await show(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/5')
    expect(result.id).toBe(5)
    expect(current.value?.id).toBe(5)
  })

  it('saveDraft patches data and version', async () => {
    mockPatch.mockResolvedValue({ success: true, data: { id: 5, version: 2 } })
    const { saveDraft } = useEngineRequests()

    const result = await saveDraft(5, { invoice_amount: 100 }, 1)

    expect(mockPatch).toHaveBeenCalledWith('/api/v1/engine-requests/5/draft', {
      data: { invoice_amount: 100 },
      version: 1,
    })
    expect(result.version).toBe(2)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm exec vitest run app/tests/unit/composables/useEngineRequests.test.ts`
Expected: FAIL — module `@/composables/useEngineRequests` not found.

- [ ] **Step 3: Implement the composable**

Create `frontend/app/composables/useEngineRequests.ts`:

```ts
import { ref } from 'vue'
import type { AvailableWorkflow, EngineRequest, PaginatedResponse } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorMessage } from '@/utils/apiErrors'

type ListOptions = {
  page?: number
  per_page?: number
  workflow_id?: number
  workflow_version_id?: number
  stage_id?: number
  bank_id?: number
  merchant_id?: number
  status?: string
  search?: string
  sla_status?: string
}

export function useEngineRequests() {
  const api = useApi()

  const instances = ref<EngineRequest[]>([])
  const instancesMeta = ref<PaginatedResponse<EngineRequest>['meta'] | null>(null)
  const queue = ref<EngineRequest[]>([])
  const queueMeta = ref<PaginatedResponse<EngineRequest>['meta'] | null>(null)
  const availableWorkflows = ref<AvailableWorkflow[]>([])
  const current = ref<EngineRequest | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchList = async (options: ListOptions = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<PaginatedResponse<EngineRequest>>('/api/v1/engine-requests', {
        query: { page: options.page ?? 1, per_page: options.per_page ?? 25, ...options },
      })
      instances.value = response.data
      instancesMeta.value = response.meta
    } catch (cause: unknown) {
      instances.value = []
      instancesMeta.value = null
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل الطلبات.')
    } finally {
      loading.value = false
    }
  }

  const fetchQueue = async (options: ListOptions = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<PaginatedResponse<EngineRequest>>('/api/v1/engine-requests/my-queue', {
        query: { page: options.page ?? 1, per_page: options.per_page ?? 25, ...options },
      })
      queue.value = response.data
      queueMeta.value = response.meta
    } catch (cause: unknown) {
      queue.value = []
      queueMeta.value = null
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل طابور العمل.')
    } finally {
      loading.value = false
    }
  }

  const fetchAvailableWorkflows = async () => {
    error.value = null
    try {
      const response = await api.get<{ data: AvailableWorkflow[] }>(
        '/api/v1/engine-requests/available-workflows',
      )
      availableWorkflows.value = response.data
    } catch (cause: unknown) {
      availableWorkflows.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل مسارات العمل المتاحة.')
    }
  }

  const create = async (payload: {
    workflow_version_id: number
    bank_id?: number | null
    merchant_id?: number | null
    data: Record<string, unknown>
  }): Promise<EngineRequest> => {
    const response = await api.post<{ success: boolean; data: EngineRequest }>(
      '/api/v1/engine-requests',
      payload,
    )
    return response.data
  }

  const show = async (id: number): Promise<EngineRequest> => {
    const response = await api.get<{ success: boolean; data: EngineRequest }>(
      `/api/v1/engine-requests/${id}`,
    )
    current.value = response.data
    return response.data
  }

  const saveDraft = async (
    id: number,
    data: Record<string, unknown>,
    version: number,
  ): Promise<EngineRequest> => {
    const response = await api.patch<{ success: boolean; data: EngineRequest }>(
      `/api/v1/engine-requests/${id}/draft`,
      { data, version },
    )
    current.value = response.data
    return response.data
  }

  return {
    instances,
    instancesMeta,
    queue,
    queueMeta,
    availableWorkflows,
    current,
    loading,
    error,
    fetchList,
    fetchQueue,
    fetchAvailableWorkflows,
    create,
    show,
    saveDraft,
  }
}
```

Before finalizing, confirm `useApi()`'s `.post`/`.patch` method signatures match `(url, body)` exactly by reading the rest of `frontend/app/composables/useApi.ts` beyond what was shown during planning (the file had at least a `getXsrfToken`/`isUnsafeMethod`/`isCsrfMismatch` section visible; the actual `get`/`post`/`patch` method bodies were not yet read in full) — run `grep -n "^  function \|^  async function \|return {" frontend/app/composables/useApi.ts` and adjust the calls above if the real signature differs (e.g. if it takes `{ body: ... }` instead of a positional second argument).

- [ ] **Step 4: Run test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/composables/useEngineRequests.test.ts`
Expected: PASS (all 7 tests).

- [ ] **Step 5: Lint check**

Run: `pnpm exec eslint app/composables/useEngineRequests.ts app/tests/unit/composables/useEngineRequests.test.ts`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
cd frontend
git add app/composables/useEngineRequests.ts app/tests/unit/composables/useEngineRequests.test.ts
git commit -m "feat(workflow): add useEngineRequests composable for engine request runtime"
cd ..
git add frontend/app/composables/useEngineRequests.ts frontend/app/tests/unit/composables/useEngineRequests.test.ts
git commit -m "feat(workflow): add useEngineRequests composable for engine request runtime"
```

---

## Task 5: Frontend — `useEngineRequestActions.ts`, `useEngineRequestHistory.ts`, `useEngineRequestDocuments.ts`

**Files:**
- Create: `frontend/app/composables/useEngineRequestActions.ts`
- Create: `frontend/app/composables/useEngineRequestHistory.ts`
- Create: `frontend/app/composables/useEngineRequestDocuments.ts`
- Test: `frontend/app/tests/unit/composables/useEngineRequestActions.test.ts`
- Test: `frontend/app/tests/unit/composables/useEngineRequestHistory.test.ts`
- Test: `frontend/app/tests/unit/composables/useEngineRequestDocuments.test.ts`

**Interfaces:**
- Consumes: `useApi()`, `extractApiErrorMessage`/`extractApiFieldErrors` from `frontend/app/utils/apiErrors.ts`, `EngineRequest`/`EngineHistoryEntry`/`EngineRequestDocument`/`WorkflowGraph` types (Task 3 + existing).
- Produces: `useEngineRequestActions()` → `{ executing: Ref<boolean>, conflictError: Ref<boolean>, fieldErrors: Ref<Record<string,string|undefined>>, executeAction(id, transitionId, comment, data, version): Promise<EngineRequest>, isConflict(cause): boolean }`. `useEngineRequestHistory()` → `{ history: Ref<EngineHistoryEntry[]>, graph: Ref<WorkflowGraph | null>, loading, error, fetchHistory(id), fetchGraph(id) }`. `useEngineRequestDocuments()` → `{ documents: Ref<EngineRequestDocument[]>, loading, error, fetchDocuments(id), upload(id, file, fieldId), remove(id, documentId), downloadUrl(id, documentId): string }`. Task 6 (store) and Task 9 (pages) consume these exact shapes.

- [ ] **Step 1: Write the failing tests**

Create `frontend/app/tests/unit/composables/useEngineRequestActions.test.ts`:

```ts
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useEngineRequestActions } from '@/composables/useEngineRequestActions'

const mockPost = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ post: mockPost }),
}))

describe('useEngineRequestActions', () => {
  beforeEach(() => {
    mockPost.mockReset()
  })

  it('executeAction posts transition payload and returns updated instance', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 5, version: 2 } })
    const { executeAction } = useEngineRequestActions()

    const result = await executeAction(5, 12, 'تمت الموافقة', { invoice_amount: 100 }, 1)

    expect(mockPost).toHaveBeenCalledWith('/api/v1/engine-requests/5/actions', {
      transition_id: 12,
      comment: 'تمت الموافقة',
      data: { invoice_amount: 100 },
      version: 1,
    })
    expect(result.version).toBe(2)
  })

  it('sets conflictError on 409 response', async () => {
    mockPost.mockRejectedValue({ status: 409, data: { message: 'stale' } })
    const { executeAction, conflictError } = useEngineRequestActions()

    await expect(executeAction(5, 12, null, {}, 1)).rejects.toBeTruthy()
    expect(conflictError.value).toBe(true)
  })

  it('sets fieldErrors on 422 response', async () => {
    mockPost.mockRejectedValue({
      status: 422,
      data: { errors: { invoice_amount: ['This field is required.'] } },
    })
    const { executeAction, fieldErrors } = useEngineRequestActions()

    await expect(executeAction(5, 12, null, {}, 1)).rejects.toBeTruthy()
    expect(fieldErrors.value.invoice_amount).toBe('This field is required.')
  })
})
```

Create `frontend/app/tests/unit/composables/useEngineRequestHistory.test.ts`:

```ts
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useEngineRequestHistory } from '@/composables/useEngineRequestHistory'

const mockGet = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

describe('useEngineRequestHistory', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('fetchHistory populates history entries', async () => {
    mockGet.mockResolvedValue({ success: true, data: [{ id: 1, action_code: 'SUBMIT' }] })
    const { history, fetchHistory } = useEngineRequestHistory()

    await fetchHistory(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/5/history')
    expect(history.value).toHaveLength(1)
  })

  it('fetchGraph populates the graph', async () => {
    mockGet.mockResolvedValue({ success: true, data: { nodes: [], edges: [] } })
    const { graph, fetchGraph } = useEngineRequestHistory()

    await fetchGraph(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/5/graph')
    expect(graph.value).toEqual({ nodes: [], edges: [] })
  })
})
```

Create `frontend/app/tests/unit/composables/useEngineRequestDocuments.test.ts`:

```ts
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useEngineRequestDocuments } from '@/composables/useEngineRequestDocuments'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockDelete = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, delete: mockDelete }),
}))

describe('useEngineRequestDocuments', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
    mockDelete.mockReset()
  })

  it('fetchDocuments populates documents', async () => {
    mockGet.mockResolvedValue({ success: true, data: [{ id: 1, original_name: 'a.pdf' }] })
    const { documents, fetchDocuments } = useEngineRequestDocuments()

    await fetchDocuments(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/5/documents')
    expect(documents.value).toHaveLength(1)
  })

  it('upload posts FormData with file and field_id', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 2, original_name: 'b.pdf' } })
    const { upload } = useEngineRequestDocuments()
    const file = new File(['x'], 'b.pdf', { type: 'application/pdf' })

    await upload(5, file, 3)

    expect(mockPost).toHaveBeenCalledWith('/api/v1/engine-requests/5/documents', expect.any(FormData))
  })

  it('remove deletes a document by id', async () => {
    mockDelete.mockResolvedValue({ success: true })
    const { remove } = useEngineRequestDocuments()

    await remove(5, 2)

    expect(mockDelete).toHaveBeenCalledWith('/api/v1/engine-requests/5/documents/2')
  })

  it('downloadUrl builds the correct path', () => {
    const { downloadUrl } = useEngineRequestDocuments()
    expect(downloadUrl(5, 2)).toBe('/api/v1/engine-requests/5/documents/2/download')
  })
})
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `pnpm exec vitest run app/tests/unit/composables/useEngineRequestActions.test.ts app/tests/unit/composables/useEngineRequestHistory.test.ts app/tests/unit/composables/useEngineRequestDocuments.test.ts`
Expected: FAIL — modules not found.

- [ ] **Step 3: Implement `useEngineRequestActions.ts`**

```ts
import { ref } from 'vue'
import type { EngineRequest } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiFieldErrors } from '@/utils/apiErrors'

export function useEngineRequestActions() {
  const api = useApi()
  const executing = ref(false)
  const conflictError = ref(false)
  const fieldErrors = ref<Record<string, string | undefined>>({})

  const executeAction = async (
    id: number,
    transitionId: number,
    comment: string | null,
    data: Record<string, unknown>,
    version: number,
  ): Promise<EngineRequest> => {
    executing.value = true
    conflictError.value = false
    fieldErrors.value = {}
    try {
      const response = await api.post<{ success: boolean; data: EngineRequest }>(
        `/api/v1/engine-requests/${id}/actions`,
        { transition_id: transitionId, comment, data, version },
      )
      return response.data
    } catch (cause: unknown) {
      const status = (cause as { status?: number })?.status
      if (status === 409) {
        conflictError.value = true
      }
      if (status === 422) {
        fieldErrors.value = extractApiFieldErrors(cause)
      }
      throw cause
    } finally {
      executing.value = false
    }
  }

  return { executing, conflictError, fieldErrors, executeAction }
}
```

- [ ] **Step 4: Implement `useEngineRequestHistory.ts`**

```ts
import { ref } from 'vue'
import type { EngineHistoryEntry, WorkflowGraph } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorMessage } from '@/utils/apiErrors'

export function useEngineRequestHistory() {
  const api = useApi()
  const history = ref<EngineHistoryEntry[]>([])
  const graph = ref<WorkflowGraph | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchHistory = async (id: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ success: boolean; data: EngineHistoryEntry[] }>(
        `/api/v1/engine-requests/${id}/history`,
      )
      history.value = response.data
    } catch (cause: unknown) {
      history.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل سجل الطلب.')
    } finally {
      loading.value = false
    }
  }

  const fetchGraph = async (id: number) => {
    error.value = null
    try {
      const response = await api.get<{ success: boolean; data: WorkflowGraph }>(
        `/api/v1/engine-requests/${id}/graph`,
      )
      graph.value = response.data
    } catch (cause: unknown) {
      graph.value = null
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل مخطط سير العمل.')
    }
  }

  return { history, graph, loading, error, fetchHistory, fetchGraph }
}
```

- [ ] **Step 5: Implement `useEngineRequestDocuments.ts`**

```ts
import { ref } from 'vue'
import type { EngineRequestDocument } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorMessage } from '@/utils/apiErrors'

export function useEngineRequestDocuments() {
  const api = useApi()
  const documents = ref<EngineRequestDocument[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchDocuments = async (requestId: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ success: boolean; data: EngineRequestDocument[] }>(
        `/api/v1/engine-requests/${requestId}/documents`,
      )
      documents.value = response.data
    } catch (cause: unknown) {
      documents.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل المرفقات.')
    } finally {
      loading.value = false
    }
  }

  const upload = async (
    requestId: number,
    file: File,
    fieldId: number | null,
  ): Promise<EngineRequestDocument> => {
    const formData = new FormData()
    formData.append('file', file)
    if (fieldId !== null) {
      formData.append('field_id', String(fieldId))
    }
    const response = await api.post<{ success: boolean; data: EngineRequestDocument }>(
      `/api/v1/engine-requests/${requestId}/documents`,
      formData,
    )
    return response.data
  }

  const remove = async (requestId: number, documentId: number): Promise<void> => {
    await api.delete(`/api/v1/engine-requests/${requestId}/documents/${documentId}`)
  }

  const downloadUrl = (requestId: number, documentId: number): string =>
    `/api/v1/engine-requests/${requestId}/documents/${documentId}/download`

  return { documents, loading, error, fetchDocuments, upload, remove, downloadUrl }
}
```

Before finalizing, confirm `useApi()` exposes a `.delete` method (not just `.get`/`.post`/`.patch`) by reading the remainder of `frontend/app/composables/useApi.ts` — if it only exposes a generic method (e.g. a single `request(method, url, opts)`), adjust `remove()` above to call that generic method with `'DELETE'` instead of assuming `.delete` exists.

- [ ] **Step 6: Run tests to verify they pass**

Run: `pnpm exec vitest run app/tests/unit/composables/useEngineRequestActions.test.ts app/tests/unit/composables/useEngineRequestHistory.test.ts app/tests/unit/composables/useEngineRequestDocuments.test.ts`
Expected: PASS (all tests across the three files).

- [ ] **Step 7: Lint check**

Run: `pnpm exec eslint app/composables/useEngineRequestActions.ts app/composables/useEngineRequestHistory.ts app/composables/useEngineRequestDocuments.ts`
Expected: no errors.

- [ ] **Step 8: Commit**

```bash
cd frontend
git add app/composables/useEngineRequestActions.ts app/composables/useEngineRequestHistory.ts app/composables/useEngineRequestDocuments.ts app/tests/unit/composables/useEngineRequestActions.test.ts app/tests/unit/composables/useEngineRequestHistory.test.ts app/tests/unit/composables/useEngineRequestDocuments.test.ts
git commit -m "feat(workflow): add engine request action, history, and document composables"
cd ..
git add frontend/app/composables/useEngineRequestActions.ts frontend/app/composables/useEngineRequestHistory.ts frontend/app/composables/useEngineRequestDocuments.ts frontend/app/tests/unit/composables/useEngineRequestActions.test.ts frontend/app/tests/unit/composables/useEngineRequestHistory.test.ts frontend/app/tests/unit/composables/useEngineRequestDocuments.test.ts
git commit -m "feat(workflow): add engine request action, history, and document composables"
```

---

## Task 6: Frontend — `engineRequests.store.ts` Pinia store

**Files:**
- Create: `frontend/app/stores/engineRequests.store.ts`
- Test: `frontend/app/tests/unit/stores/engineRequests.store.test.ts`

**Interfaces:**
- Consumes: `useEngineRequests()` (Task 4), `useEngineRequestActions()`, `useEngineRequestHistory()`, `useEngineRequestDocuments()` (Task 5) — the store wraps these composables the same way `requests.store.ts` wraps `useRequests()` (read its pattern at `frontend/app/stores/requests.store.ts` lines 1-40 already shown during planning: options-store via `defineStore('requests', { state, actions })`).
- Produces: `useEngineRequestsStore()` Pinia store exposing state (`instances`, `queue`, `current`, `history`, `graph`, `documents`, `availableWorkflows`, `loading`, `error`, `conflictError`, `fieldErrors`) and actions (`loadList`, `loadQueue`, `loadAvailableWorkflows`, `createInstance`, `loadInstance`, `executeTransition`, `saveDraft`, `loadHistory`, `loadGraph`, `loadDocuments`, `uploadDocument`, `removeDocument`). Task 9 (pages) consume this store directly instead of calling composables one-by-one, matching how `requests/[id]/index.vue` consumes `requests.store.ts` rather than `useRequests()` directly.

- [ ] **Step 1: Write the failing test**

Create `frontend/app/tests/unit/stores/engineRequests.store.test.ts`:

```ts
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'

const mockFetchList = vi.fn()
const mockShow = vi.fn()
const mockCreate = vi.fn()
const mockSaveDraft = vi.fn()
const mockExecuteAction = vi.fn()
const mockFetchHistory = vi.fn()
const mockFetchGraph = vi.fn()
const mockFetchDocuments = vi.fn()

vi.mock('@/composables/useEngineRequests', () => ({
  useEngineRequests: () => ({
    instances: { value: [] },
    instancesMeta: { value: null },
    queue: { value: [] },
    queueMeta: { value: null },
    availableWorkflows: { value: [] },
    current: { value: null },
    loading: { value: false },
    error: { value: null },
    fetchList: mockFetchList,
    fetchQueue: vi.fn(),
    fetchAvailableWorkflows: vi.fn(),
    create: mockCreate,
    show: mockShow,
    saveDraft: mockSaveDraft,
  }),
}))

vi.mock('@/composables/useEngineRequestActions', () => ({
  useEngineRequestActions: () => ({
    executing: { value: false },
    conflictError: { value: false },
    fieldErrors: { value: {} },
    executeAction: mockExecuteAction,
  }),
}))

vi.mock('@/composables/useEngineRequestHistory', () => ({
  useEngineRequestHistory: () => ({
    history: { value: [] },
    graph: { value: null },
    loading: { value: false },
    error: { value: null },
    fetchHistory: mockFetchHistory,
    fetchGraph: mockFetchGraph,
  }),
}))

vi.mock('@/composables/useEngineRequestDocuments', () => ({
  useEngineRequestDocuments: () => ({
    documents: { value: [] },
    loading: { value: false },
    error: { value: null },
    fetchDocuments: mockFetchDocuments,
    upload: vi.fn(),
    remove: vi.fn(),
    downloadUrl: vi.fn(),
  }),
}))

describe('useEngineRequestsStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockFetchList.mockReset()
    mockShow.mockReset()
    mockCreate.mockReset()
    mockSaveDraft.mockReset()
    mockExecuteAction.mockReset()
    mockFetchHistory.mockReset()
    mockFetchGraph.mockReset()
    mockFetchDocuments.mockReset()
  })

  it('loadList delegates to the composable', async () => {
    const store = useEngineRequestsStore()
    await store.loadList()
    expect(mockFetchList).toHaveBeenCalled()
  })

  it('createInstance delegates to the composable and returns the result', async () => {
    mockCreate.mockResolvedValue({ id: 9 })
    const store = useEngineRequestsStore()

    const result = await store.createInstance({ workflow_version_id: 1, data: {} })

    expect(mockCreate).toHaveBeenCalledWith({ workflow_version_id: 1, data: {} })
    expect(result.id).toBe(9)
  })

  it('loadInstance loads the instance plus its history and graph', async () => {
    mockShow.mockResolvedValue({ id: 9 })
    const store = useEngineRequestsStore()

    await store.loadInstance(9)

    expect(mockShow).toHaveBeenCalledWith(9)
    expect(mockFetchHistory).toHaveBeenCalledWith(9)
    expect(mockFetchGraph).toHaveBeenCalledWith(9)
    expect(mockFetchDocuments).toHaveBeenCalledWith(9)
  })

  it('executeTransition delegates and reloads the instance on success', async () => {
    mockExecuteAction.mockResolvedValue({ id: 9, version: 2 })
    mockShow.mockResolvedValue({ id: 9, version: 2 })
    const store = useEngineRequestsStore()

    await store.executeTransition(9, 3, 'ok', {}, 1)

    expect(mockExecuteAction).toHaveBeenCalledWith(9, 3, 'ok', {}, 1)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm exec vitest run app/tests/unit/stores/engineRequests.store.test.ts`
Expected: FAIL — module not found.

- [ ] **Step 3: Implement the store**

Create `frontend/app/stores/engineRequests.store.ts`:

```ts
import { defineStore } from 'pinia'
import type { AvailableWorkflow, EngineHistoryEntry, EngineRequest, EngineRequestDocument, WorkflowGraph } from '@/types/models'
import { useEngineRequests } from '@/composables/useEngineRequests'
import { useEngineRequestActions } from '@/composables/useEngineRequestActions'
import { useEngineRequestHistory } from '@/composables/useEngineRequestHistory'
import { useEngineRequestDocuments } from '@/composables/useEngineRequestDocuments'

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const useEngineRequestsStore = defineStore('engineRequests', {
  state: () => ({
    instances: [] as EngineRequest[],
    instancesMeta: null as PaginationMeta | null,
    queue: [] as EngineRequest[],
    queueMeta: null as PaginationMeta | null,
    availableWorkflows: [] as AvailableWorkflow[],
    current: null as EngineRequest | null,
    history: [] as EngineHistoryEntry[],
    graph: null as WorkflowGraph | null,
    documents: [] as EngineRequestDocument[],
    loading: false,
    error: null as string | null,
    conflictError: false,
    fieldErrors: {} as Record<string, string | undefined>,
  }),
  actions: {
    async loadList(options: Record<string, unknown> = {}) {
      const { instances, instancesMeta, loading, error, fetchList } = useEngineRequests()
      await fetchList(options)
      this.instances = instances.value
      this.instancesMeta = instancesMeta.value
      this.loading = loading.value
      this.error = error.value
    },

    async loadQueue(options: Record<string, unknown> = {}) {
      const { queue, queueMeta, loading, error, fetchQueue } = useEngineRequests()
      await fetchQueue(options)
      this.queue = queue.value
      this.queueMeta = queueMeta.value
      this.loading = loading.value
      this.error = error.value
    },

    async loadAvailableWorkflows() {
      const { availableWorkflows, error, fetchAvailableWorkflows } = useEngineRequests()
      await fetchAvailableWorkflows()
      this.availableWorkflows = availableWorkflows.value
      this.error = error.value
    },

    async createInstance(payload: {
      workflow_version_id: number
      bank_id?: number | null
      merchant_id?: number | null
      data: Record<string, unknown>
    }): Promise<EngineRequest> {
      const { create } = useEngineRequests()
      const result = await create(payload)
      this.current = result
      return result
    },

    async loadInstance(id: number) {
      const { show } = useEngineRequests()
      const { history, fetchHistory } = useEngineRequestHistory()
      const { graph, fetchGraph } = useEngineRequestHistory()
      const { documents, fetchDocuments } = useEngineRequestDocuments()

      this.current = await show(id)
      await Promise.all([fetchHistory(id), fetchGraph(id), fetchDocuments(id)])
      this.history = history.value
      this.graph = graph.value
      this.documents = documents.value
    },

    async executeTransition(
      id: number,
      transitionId: number,
      comment: string | null,
      data: Record<string, unknown>,
      version: number,
    ) {
      const { executeAction, conflictError, fieldErrors } = useEngineRequestActions()
      try {
        const result = await executeAction(id, transitionId, comment, data, version)
        this.current = result
        this.conflictError = false
        this.fieldErrors = {}
        await this.loadInstance(id)
        return result
      } catch (cause) {
        this.conflictError = conflictError.value
        this.fieldErrors = fieldErrors.value
        throw cause
      }
    },

    async saveDraftData(id: number, data: Record<string, unknown>, version: number) {
      const { saveDraft } = useEngineRequests()
      this.current = await saveDraft(id, data, version)
    },

    async uploadDocument(id: number, file: File, fieldId: number | null) {
      const { upload } = useEngineRequestDocuments()
      await upload(id, file, fieldId)
      const { documents, fetchDocuments } = useEngineRequestDocuments()
      await fetchDocuments(id)
      this.documents = documents.value
    },

    async removeDocument(id: number, documentId: number) {
      const { remove } = useEngineRequestDocuments()
      await remove(id, documentId)
      const { documents, fetchDocuments } = useEngineRequestDocuments()
      await fetchDocuments(id)
      this.documents = documents.value
    },
  },
})
```

Note: calling `useEngineRequestHistory()` twice in `loadInstance` (once for `history`/`fetchHistory`, once for `graph`/`fetchGraph`) is intentional only if the composable creates fresh independent refs each call — re-check Task 5's implementation: since `useEngineRequestHistory()` returns both `history` and `graph` refs from ONE call, simplify to a single call capturing both, e.g. `const { history, graph, fetchHistory, fetchGraph } = useEngineRequestHistory()` — fix this in the actual file before running tests (the snippet above has a redundant second call that must be removed).

- [ ] **Step 4: Run test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/stores/engineRequests.store.test.ts`
Expected: PASS (all 4 tests).

- [ ] **Step 5: Lint check**

Run: `pnpm exec eslint app/stores/engineRequests.store.ts`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
cd frontend
git add app/stores/engineRequests.store.ts app/tests/unit/stores/engineRequests.store.test.ts
git commit -m "feat(workflow): add engineRequests Pinia store"
cd ..
git add frontend/app/stores/engineRequests.store.ts frontend/app/tests/unit/stores/engineRequests.store.test.ts
git commit -m "feat(workflow): add engineRequests Pinia store"
```

---

## Task 7: Frontend — `useDynamicFormSchema.ts` (runtime Zod schema builder)

**Files:**
- Create: `frontend/app/composables/useDynamicFormSchema.ts`
- Test: `frontend/app/tests/unit/composables/useDynamicFormSchema.test.ts`

**Interfaces:**
- Consumes: `ResolvedFieldDefinition`, `ResolvedFieldGroup` types from Task 3. `zod` package (already a project dependency, confirmed by `frontend/SHADCN.md`'s Form recipe using `import * as z from 'zod'`).
- Produces: `buildDynamicSchema(fieldGroups: ResolvedFieldGroup[]): z.ZodObject<Record<string, z.ZodTypeAny>>`. Task 8 (`DynamicForm.vue`) consumes this exact function, feeding its result into `toTypedSchema()` from `@vee-validate/zod` the same way `RequestFormTabs.vue` does.

This function flattens every visible field across all `fieldGroups` into one Zod object schema, keyed by `field.key`. Fields with `is_visible === false` are excluded from the schema entirely (matching the design decision: invisible fields are omitted, not rendered disabled). Fields with `is_editable === false` are included but the renderer (Task 8) will disable their input — the schema still validates them normally since their value can't change anyway.

- [ ] **Step 1: Write the failing test**

Create `frontend/app/tests/unit/composables/useDynamicFormSchema.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { buildDynamicSchema } from '@/composables/useDynamicFormSchema'
import type { ResolvedFieldGroup } from '@/types/models'

function group(fields: ResolvedFieldGroup['fields']): ResolvedFieldGroup[] {
  return [{ id: 1, name: 'g1', label: 'مجموعة', sort_order: 0, fields }]
}

function baseField(overrides: Partial<ResolvedFieldGroup['fields'][number]>) {
  return {
    id: 1,
    key: 'field_key',
    label: 'حقل',
    type: 'TEXT' as const,
    placeholder: null,
    help_text: null,
    default_value: null,
    min_value: null,
    max_value: null,
    min_length: null,
    max_length: null,
    regex_pattern: null,
    options: null,
    dynamic_source: null,
    allowed_file_types: null,
    max_file_size: null,
    multiple: false,
    is_visible: true,
    is_editable: true,
    is_required: false,
    dynamic_options: null,
    ...overrides,
  }
}

describe('buildDynamicSchema', () => {
  it('omits fields where is_visible is false', () => {
    const schema = buildDynamicSchema(group([baseField({ is_visible: false })]))
    expect(schema.shape.field_key).toBeUndefined()
  })

  it('TEXT field with is_required true rejects empty string', () => {
    const schema = buildDynamicSchema(group([baseField({ type: 'TEXT', is_required: true })]))
    const result = schema.safeParse({ field_key: '' })
    expect(result.success).toBe(false)
  })

  it('TEXT field respects min_length and max_length', () => {
    const schema = buildDynamicSchema(
      group([baseField({ type: 'TEXT', min_length: 3, max_length: 5 })]),
    )
    expect(schema.safeParse({ field_key: 'ab' }).success).toBe(false)
    expect(schema.safeParse({ field_key: 'abcdef' }).success).toBe(false)
    expect(schema.safeParse({ field_key: 'abcd' }).success).toBe(true)
  })

  it('TEXT field respects regex_pattern', () => {
    const schema = buildDynamicSchema(
      group([baseField({ type: 'TEXT', regex_pattern: '^[0-9]+$' })]),
    )
    expect(schema.safeParse({ field_key: 'abc' }).success).toBe(false)
    expect(schema.safeParse({ field_key: '123' }).success).toBe(true)
  })

  it('NUMBER and CURRENCY fields respect min_value and max_value', () => {
    const schema = buildDynamicSchema(
      group([
        baseField({ key: 'num', type: 'NUMBER', min_value: 10, max_value: 100 }),
        baseField({ key: 'cur', type: 'CURRENCY', min_value: 1 }),
      ]),
    )
    expect(schema.safeParse({ num: 5, cur: 5 }).success).toBe(false)
    expect(schema.safeParse({ num: 50, cur: 5 }).success).toBe(true)
  })

  it('NUMBER field is optional when is_required is false', () => {
    const schema = buildDynamicSchema(group([baseField({ key: 'num', type: 'NUMBER', is_required: false })]))
    expect(schema.safeParse({}).success).toBe(true)
  })

  it('SELECT field validates against options values', () => {
    const schema = buildDynamicSchema(
      group([
        baseField({
          key: 'choice',
          type: 'SELECT',
          options: [{ value: 'A', label: 'أ' }, { value: 'B', label: 'ب' }],
          is_required: true,
        }),
      ]),
    )
    expect(schema.safeParse({ choice: 'C' }).success).toBe(false)
    expect(schema.safeParse({ choice: 'A' }).success).toBe(true)
  })

  it('DYNAMIC_SELECT field validates against dynamic_options values', () => {
    const schema = buildDynamicSchema(
      group([
        baseField({
          key: 'merchant',
          type: 'DYNAMIC_SELECT',
          dynamic_options: [{ value: 7, label: 'تاجر' }],
          is_required: true,
        }),
      ]),
    )
    expect(schema.safeParse({ merchant: 9 }).success).toBe(false)
    expect(schema.safeParse({ merchant: 7 }).success).toBe(true)
  })

  it('CHECKBOX field accepts boolean', () => {
    const schema = buildDynamicSchema(group([baseField({ key: 'agree', type: 'CHECKBOX' })]))
    expect(schema.safeParse({ agree: true }).success).toBe(true)
    expect(schema.safeParse({ agree: 'yes' }).success).toBe(false)
  })

  it('DATE field accepts an ISO date string', () => {
    const schema = buildDynamicSchema(group([baseField({ key: 'd', type: 'DATE', is_required: true })]))
    expect(schema.safeParse({ d: '2026-06-25' }).success).toBe(true)
    expect(schema.safeParse({ d: '' }).success).toBe(false)
  })

  it('FILE field accepts a non-empty array when required, empty array when not', () => {
    const required = buildDynamicSchema(group([baseField({ key: 'docs', type: 'FILE', is_required: true })]))
    expect(required.safeParse({ docs: [] }).success).toBe(false)
    expect(required.safeParse({ docs: [1] }).success).toBe(true)

    const optional = buildDynamicSchema(group([baseField({ key: 'docs', type: 'FILE', is_required: false })]))
    expect(optional.safeParse({ docs: [] }).success).toBe(true)
  })

  it('TEXTAREA field behaves like TEXT for required/length', () => {
    const schema = buildDynamicSchema(
      group([baseField({ key: 'notes', type: 'TEXTAREA', is_required: true, max_length: 10 })]),
    )
    expect(schema.safeParse({ notes: '' }).success).toBe(false)
    expect(schema.safeParse({ notes: 'a'.repeat(11) }).success).toBe(false)
    expect(schema.safeParse({ notes: 'short' }).success).toBe(true)
  })

  it('flattens fields across multiple field groups into one schema', () => {
    const groups: ResolvedFieldGroup[] = [
      { id: 1, name: 'g1', label: 'أ', sort_order: 0, fields: [baseField({ key: 'a' })] },
      { id: 2, name: 'g2', label: 'ب', sort_order: 1, fields: [baseField({ key: 'b' })] },
    ]
    const schema = buildDynamicSchema(groups)
    expect(Object.keys(schema.shape)).toEqual(['a', 'b'])
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm exec vitest run app/tests/unit/composables/useDynamicFormSchema.test.ts`
Expected: FAIL — module not found.

- [ ] **Step 3: Implement the schema builder**

Create `frontend/app/composables/useDynamicFormSchema.ts`:

```ts
import * as z from 'zod'
import type { ResolvedFieldDefinition, ResolvedFieldGroup } from '@/types/models'

function buildFieldSchema(field: ResolvedFieldDefinition): z.ZodTypeAny {
  let schema: z.ZodTypeAny

  switch (field.type) {
    case 'TEXT':
    case 'TEXTAREA': {
      let stringSchema = z.string()
      if (field.min_length !== null) {
        stringSchema = stringSchema.min(field.min_length, 'القيمة قصيرة جداً.')
      }
      if (field.max_length !== null) {
        stringSchema = stringSchema.max(field.max_length, 'القيمة طويلة جداً.')
      }
      if (field.regex_pattern !== null) {
        stringSchema = stringSchema.regex(new RegExp(field.regex_pattern), 'صيغة غير صحيحة.')
      }
      schema = field.is_required ? stringSchema.min(1, 'هذا الحقل مطلوب.') : stringSchema
      break
    }
    case 'NUMBER':
    case 'CURRENCY': {
      let numberSchema = z.number()
      if (field.min_value !== null) {
        numberSchema = numberSchema.min(field.min_value, 'القيمة أقل من الحد المسموح.')
      }
      if (field.max_value !== null) {
        numberSchema = numberSchema.max(field.max_value, 'القيمة أكبر من الحد المسموح.')
      }
      schema = numberSchema
      break
    }
    case 'DATE': {
      schema = field.is_required ? z.string().min(1, 'هذا الحقل مطلوب.') : z.string()
      break
    }
    case 'SELECT': {
      const values = (field.options ?? []).map((option) => option.value)
      schema =
        values.length > 0
          ? z.enum(values as [string, ...string[]])
          : z.string()
      if (!field.is_required) {
        schema = schema.optional()
      }
      break
    }
    case 'DYNAMIC_SELECT': {
      const values = (field.dynamic_options ?? []).map((option) => option.value)
      schema =
        values.length > 0
          ? z.union(values.map((value) => z.literal(value)) as never)
          : z.union([z.string(), z.number()])
      if (!field.is_required) {
        schema = schema.optional()
      }
      break
    }
    case 'CHECKBOX': {
      schema = z.boolean()
      break
    }
    case 'FILE': {
      const arraySchema = z.array(z.unknown())
      schema = field.is_required ? arraySchema.min(1, 'يجب إرفاق ملف واحد على الأقل.') : arraySchema
      break
    }
    default: {
      schema = z.unknown()
    }
  }

  if (field.is_required && field.type !== 'TEXT' && field.type !== 'TEXTAREA' && field.type !== 'DATE' && field.type !== 'FILE') {
    return schema
  }
  if (!field.is_required && field.type !== 'SELECT' && field.type !== 'DYNAMIC_SELECT') {
    return schema.optional()
  }

  return schema
}

export function buildDynamicSchema(fieldGroups: ResolvedFieldGroup[]): z.ZodObject<Record<string, z.ZodTypeAny>> {
  const shape: Record<string, z.ZodTypeAny> = {}

  for (const group of fieldGroups) {
    for (const field of group.fields) {
      if (!field.is_visible) {
        continue
      }
      shape[field.key] = buildFieldSchema(field)
    }
  }

  return z.object(shape)
}
```

Re-check the required/optional branching logic in Step 3 carefully against every test case in Step 1 before moving on — the `is_required` handling differs subtly per type (TEXT/TEXTAREA/DATE encode "required" via `.min(1, ...)` on the string itself rather than via `.optional()`, while NUMBER/CURRENCY/CHECKBOX/SELECT/DYNAMIC_SELECT/FILE encode it via presence-or-absence of `.optional()`). If a test fails on the required/optional boundary, adjust the per-type branch in `buildFieldSchema`, not the generic trailing logic — the trailing `if` block is fragile and may need to be replaced with explicit per-case `.optional()` calls inside each `switch` arm instead, once the tests reveal exactly where it breaks.

- [ ] **Step 4: Run test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/composables/useDynamicFormSchema.test.ts`
Expected: PASS (all cases). If any case fails on the required/optional logic, fix per the note in Step 3 and re-run until green — do not move to Task 8 with red tests here, since `DynamicForm.vue` depends entirely on this function's correctness.

- [ ] **Step 5: Lint check**

Run: `pnpm exec eslint app/composables/useDynamicFormSchema.ts`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
cd frontend
git add app/composables/useDynamicFormSchema.ts app/tests/unit/composables/useDynamicFormSchema.test.ts
git commit -m "feat(workflow): add runtime Zod schema builder for dynamic form fields"
cd ..
git add frontend/app/composables/useDynamicFormSchema.ts frontend/app/tests/unit/composables/useDynamicFormSchema.test.ts
git commit -m "feat(workflow): add runtime Zod schema builder for dynamic form fields"
```

---

## Task 8: Frontend — `DynamicFormField.vue` + `DynamicForm.vue`

**Files:**
- Create: `frontend/app/components/workflow/DynamicFormField.vue`
- Create: `frontend/app/components/workflow/DynamicForm.vue`
- Test: `frontend/app/tests/unit/components/DynamicFormField.test.ts`
- Test: `frontend/app/tests/unit/components/DynamicForm.test.ts`

**Interfaces:**
- Consumes: `ResolvedFieldDefinition`/`ResolvedFieldGroup`/`EngineFormSchema` types (Task 3), `buildDynamicSchema` (Task 7), shadcn-vue `Input`/`Textarea`/`Select`/`Checkbox`/`Field`/`FieldLabel`/`FieldError` (existing, per `frontend/SHADCN.md`), `useEngineRequestDocuments` (Task 5, for FILE field upload).
- Produces: `DynamicFormField.vue` props `{ field: ResolvedFieldDefinition, modelValue: unknown, error?: string }`, emits `update:modelValue`. `DynamicForm.vue` props `{ fieldGroups: ResolvedFieldGroup[], modelValue: Record<string, unknown>, mode: 'edit' | 'readonly', requestId?: number }`, emits `update:modelValue`, exposes a `validate(): Promise<{ valid: boolean; values: Record<string, unknown> }>` method via `defineExpose` for the parent (Task 9's detail page) to call before submitting a transition. Note: `dynamic_options` for `DYNAMIC_SELECT` fields arrive pre-resolved from the backend (Task 2's `form-schema` endpoint already calls `DynamicFieldOptionsResolver` server-side) — `DynamicFormField.vue` does NOT call `useMerchants`/`useReferenceData` itself, it just renders `field.dynamic_options` as `Select` options, identically to a `SELECT` field but reading from a different property.

- [ ] **Step 1: Write the failing test for `DynamicFormField.vue`**

Create `frontend/app/tests/unit/components/DynamicFormField.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DynamicFormField from '@/components/workflow/DynamicFormField.vue'
import type { ResolvedFieldDefinition } from '@/types/models'

function baseField(overrides: Partial<ResolvedFieldDefinition>): ResolvedFieldDefinition {
  return {
    id: 1,
    key: 'field_key',
    label: 'حقل',
    type: 'TEXT',
    placeholder: null,
    help_text: null,
    default_value: null,
    min_value: null,
    max_value: null,
    min_length: null,
    max_length: null,
    regex_pattern: null,
    options: null,
    dynamic_source: null,
    allowed_file_types: null,
    max_file_size: null,
    multiple: false,
    is_visible: true,
    is_editable: true,
    is_required: false,
    dynamic_options: null,
    ...overrides,
  }
}

describe('DynamicFormField', () => {
  it('renders an Input for TEXT fields', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXT' }), modelValue: '' },
    })
    expect(wrapper.findComponent({ name: 'Input' }).exists()).toBe(true)
  })

  it('renders a disabled Input when is_editable is false', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXT', is_editable: false }), modelValue: 'locked' },
    })
    const input = wrapper.findComponent({ name: 'Input' })
    expect(input.props('disabled')).toBe(true)
  })

  it('renders a Textarea for TEXTAREA fields', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXTAREA' }), modelValue: '' },
    })
    expect(wrapper.findComponent({ name: 'Textarea' }).exists()).toBe(true)
  })

  it('renders a Checkbox for CHECKBOX fields', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'CHECKBOX' }), modelValue: false },
    })
    expect(wrapper.findComponent({ name: 'Checkbox' }).exists()).toBe(true)
  })

  it('renders Select options from field.options for SELECT fields', () => {
    const wrapper = mount(DynamicFormField, {
      props: {
        field: baseField({
          type: 'SELECT',
          options: [{ value: 'A', label: 'أ' }],
        }),
        modelValue: '',
      },
    })
    expect(wrapper.findComponent({ name: 'Select' }).exists()).toBe(true)
  })

  it('renders Select options from field.dynamic_options for DYNAMIC_SELECT fields', () => {
    const wrapper = mount(DynamicFormField, {
      props: {
        field: baseField({
          type: 'DYNAMIC_SELECT',
          dynamic_options: [{ value: 7, label: 'تاجر' }],
        }),
        modelValue: '',
      },
    })
    expect(wrapper.findComponent({ name: 'Select' }).exists()).toBe(true)
  })

  it('renders an error message when error prop is set', () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXT' }), modelValue: '', error: 'هذا الحقل مطلوب.' },
    })
    expect(wrapper.text()).toContain('هذا الحقل مطلوب.')
  })

  it('emits update:modelValue when the input changes', async () => {
    const wrapper = mount(DynamicFormField, {
      props: { field: baseField({ type: 'TEXT' }), modelValue: '' },
    })
    await wrapper.findComponent({ name: 'Input' }).setValue('new value')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['new value'])
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm exec vitest run app/tests/unit/components/DynamicFormField.test.ts`
Expected: FAIL — component not found.

- [ ] **Step 3: Implement `DynamicFormField.vue`**

```vue
<script setup lang="ts">
import type { ResolvedFieldDefinition } from '@/types/models'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
import { Field, FieldLabel, FieldError, FieldDescription } from '@/components/ui/field'

const props = defineProps<{
  field: ResolvedFieldDefinition
  modelValue: unknown
  error?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

const selectOptions = computed(() => {
  if (props.field.type === 'DYNAMIC_SELECT') {
    return props.field.dynamic_options ?? []
  }
  return props.field.options ?? []
})

function onInput(value: unknown) {
  emit('update:modelValue', value)
}
</script>

<template>
  <Field>
    <FieldLabel :for="field.key">{{ field.label }}<span v-if="field.is_required" aria-hidden="true"> *</span></FieldLabel>

    <Input
      v-if="field.type === 'TEXT'"
      :id="field.key"
      :model-value="(modelValue as string) ?? ''"
      :placeholder="field.placeholder ?? undefined"
      :disabled="!field.is_editable"
      @update:model-value="onInput"
    />

    <Textarea
      v-else-if="field.type === 'TEXTAREA'"
      :id="field.key"
      :model-value="(modelValue as string) ?? ''"
      :placeholder="field.placeholder ?? undefined"
      :disabled="!field.is_editable"
      @update:model-value="onInput"
    />

    <Input
      v-else-if="field.type === 'NUMBER' || field.type === 'CURRENCY'"
      :id="field.key"
      type="number"
      :model-value="(modelValue as number) ?? ''"
      :placeholder="field.placeholder ?? undefined"
      :disabled="!field.is_editable"
      @update:model-value="(v: string) => onInput(v === '' ? undefined : Number(v))"
    />

    <Input
      v-else-if="field.type === 'DATE'"
      :id="field.key"
      type="date"
      :model-value="(modelValue as string) ?? ''"
      :disabled="!field.is_editable"
      @update:model-value="onInput"
    />

    <Select
      v-else-if="field.type === 'SELECT' || field.type === 'DYNAMIC_SELECT'"
      :model-value="modelValue as string"
      :disabled="!field.is_editable"
      @update:model-value="onInput"
    >
      <SelectTrigger :id="field.key">
        <SelectValue :placeholder="field.placeholder ?? 'اختر…'" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem v-for="option in selectOptions" :key="String(option.value)" :value="option.value">
          {{ option.label }}
        </SelectItem>
      </SelectContent>
    </Select>

    <div v-else-if="field.type === 'CHECKBOX'" class="flex items-center gap-2">
      <Checkbox
        :id="field.key"
        :checked="modelValue as boolean"
        :disabled="!field.is_editable"
        @update:checked="onInput"
      />
    </div>

    <FieldDescription v-if="field.help_text">{{ field.help_text }}</FieldDescription>
    <FieldError v-if="error">{{ error }}</FieldError>
  </Field>
</template>
```

FILE fields are handled by `DynamicForm.vue` directly (Step 5), not by `DynamicFormField.vue`, since file upload needs the `requestId` context and the upload composable — that prop isn't available to the per-field component to keep its interface simple. Confirm `Checkbox`'s exact event name (`@update:checked` vs `@update:model-value`) by reading `frontend/app/components/ui/checkbox/Checkbox.vue` before finalizing — `frontend/SHADCN.md`'s recipe shows `v-model:checked`, implying the event is `update:checked`, but verify against the actual component source.

- [ ] **Step 4: Run test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/components/DynamicFormField.test.ts`
Expected: PASS (all 8 tests). If `Checkbox`/`Select`/`Input` component name lookups fail (e.g. `findComponent({ name: 'Input' })` returns nothing because the component's internal `name` option differs), inspect the actual rendered HTML via `wrapper.html()` in a quick debug run and switch the test's lookup to `wrapper.find('input')` or a `data-testid` attribute instead — do not weaken the component itself to satisfy the test, per the project's test-compatibility rule in `frontend/CLAUDE.md`.

- [ ] **Step 5: Write the failing test for `DynamicForm.vue`**

Create `frontend/app/tests/unit/components/DynamicForm.test.ts`:

```ts
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DynamicForm from '@/components/workflow/DynamicForm.vue'
import type { ResolvedFieldGroup } from '@/types/models'

vi.mock('@/composables/useEngineRequestDocuments', () => ({
  useEngineRequestDocuments: () => ({
    upload: vi.fn().mockResolvedValue({ id: 1, original_name: 'a.pdf' }),
    remove: vi.fn(),
    documents: { value: [] },
    fetchDocuments: vi.fn(),
    loading: { value: false },
    error: { value: null },
  }),
}))

const groups: ResolvedFieldGroup[] = [
  {
    id: 1,
    name: 'g1',
    label: 'بيانات أساسية',
    sort_order: 0,
    fields: [
      {
        id: 1,
        key: 'invoice_amount',
        label: 'مبلغ الفاتورة',
        type: 'NUMBER',
        placeholder: null,
        help_text: null,
        default_value: null,
        min_value: 1,
        max_value: null,
        min_length: null,
        max_length: null,
        regex_pattern: null,
        options: null,
        dynamic_source: null,
        allowed_file_types: null,
        max_file_size: null,
        multiple: false,
        is_visible: true,
        is_editable: true,
        is_required: true,
        dynamic_options: null,
      },
    ],
  },
]

describe('DynamicForm', () => {
  it('renders one DynamicFormField per visible field', () => {
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: {}, mode: 'edit' },
    })
    expect(wrapper.findAllComponents({ name: 'DynamicFormField' })).toHaveLength(1)
  })

  it('renders group labels as section headings', () => {
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: {}, mode: 'edit' },
    })
    expect(wrapper.text()).toContain('بيانات أساسية')
  })

  it('disables all fields when mode is readonly', () => {
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: { invoice_amount: 50 }, mode: 'readonly' },
    })
    const field = wrapper.findComponent({ name: 'DynamicFormField' })
    expect(field.props('field').is_editable).toBe(false)
  })

  it('validate() returns valid:false and field errors when required field is missing', async () => {
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: {}, mode: 'edit' },
    })
    const result = await wrapper.vm.validate()
    expect(result.valid).toBe(false)
  })

  it('validate() returns valid:true when all required fields are present', async () => {
    const wrapper = mount(DynamicForm, {
      props: { fieldGroups: groups, modelValue: { invoice_amount: 50 }, mode: 'edit' },
    })
    const result = await wrapper.vm.validate()
    expect(result.valid).toBe(true)
  })
})
```

- [ ] **Step 6: Run test to verify it fails**

Run: `pnpm exec vitest run app/tests/unit/components/DynamicForm.test.ts`
Expected: FAIL — component not found.

- [ ] **Step 7: Implement `DynamicForm.vue`**

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import type { ResolvedFieldGroup, ResolvedFieldDefinition } from '@/types/models'
import { buildDynamicSchema } from '@/composables/useDynamicFormSchema'
import { useEngineRequestDocuments } from '@/composables/useEngineRequestDocuments'
import DynamicFormField from '@/components/workflow/DynamicFormField.vue'
import { Input } from '@/components/ui/input'
import { Field, FieldLabel, FieldError } from '@/components/ui/field'

const props = defineProps<{
  fieldGroups: ResolvedFieldGroup[]
  modelValue: Record<string, unknown>
  mode: 'edit' | 'readonly'
  requestId?: number
}>()

const emit = defineEmits<{ 'update:modelValue': [value: Record<string, unknown>] }>()

const schema = computed(() => toTypedSchema(buildDynamicSchema(props.fieldGroups)))
const form = useForm({ validationSchema: schema, initialValues: props.modelValue })

const { upload } = useEngineRequestDocuments()

function effectiveField(field: ResolvedFieldDefinition): ResolvedFieldDefinition {
  if (props.mode === 'readonly') {
    return { ...field, is_editable: false }
  }
  return field
}

function fieldValue(key: string): unknown {
  return form.values[key]
}

function setFieldValue(key: string, value: unknown) {
  form.setFieldValue(key, value)
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

async function uploadFile(field: ResolvedFieldDefinition, file: File) {
  if (props.requestId === undefined) {
    return
  }
  const doc = await upload(props.requestId, file, field.id)
  const current = (fieldValue(field.key) as number[]) ?? []
  setFieldValue(field.key, [...current, doc.id])
}

async function validate(): Promise<{ valid: boolean; values: Record<string, unknown> }> {
  const result = await form.validate()
  return { valid: result.valid, values: form.values as Record<string, unknown> }
}

defineExpose({ validate })
</script>

<template>
  <div class="flex flex-col gap-6">
    <div v-for="group in fieldGroups" :key="group.id" class="flex flex-col gap-4">
      <h3 class="text-sm font-semibold text-foreground">{{ group.label }}</h3>
      <template v-for="field in group.fields" :key="field.id">
        <DynamicFormField
          v-if="field.is_visible && field.type !== 'FILE'"
          :field="effectiveField(field)"
          :model-value="fieldValue(field.key)"
          :error="form.errors.value[field.key]"
          @update:model-value="(value) => setFieldValue(field.key, value)"
        />
        <Field v-else-if="field.is_visible && field.type === 'FILE'">
          <FieldLabel :for="field.key">{{ field.label }}<span v-if="field.is_required" aria-hidden="true"> *</span></FieldLabel>
          <Input
            :id="field.key"
            type="file"
            accept="application/pdf"
            :disabled="mode === 'readonly' || !field.is_editable"
            @change="(event: Event) => {
              const input = event.target as HTMLInputElement
              if (input.files?.[0]) uploadFile(field, input.files[0])
            }"
          />
          <FieldError v-if="form.errors.value[field.key]">{{ form.errors.value[field.key] }}</FieldError>
        </Field>
      </template>
    </div>
  </div>
</template>
```

Confirm `useForm()`'s returned `errors` shape (`form.errors.value[key]` vs `form.errors[key]` — VeeValidate's `useForm` returns `errors` as a `ComputedRef`, so `form.errors.value` is correct, but verify against the existing usage pattern in `RequestFormTabs.vue` before finalizing) and `form.validate()`'s return shape (`{ valid: boolean, errors: ... }` per VeeValidate docs) — adjust the `validate()` function above if the actual API differs from what's assumed here.

- [ ] **Step 8: Run test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/components/DynamicForm.test.ts`
Expected: PASS (all 5 tests).

- [ ] **Step 9: Lint check**

Run: `pnpm exec eslint app/components/workflow/DynamicFormField.vue app/components/workflow/DynamicForm.vue`
Expected: no errors.

- [ ] **Step 10: Commit**

```bash
cd frontend
git add app/components/workflow/DynamicFormField.vue app/components/workflow/DynamicForm.vue app/tests/unit/components/DynamicFormField.test.ts app/tests/unit/components/DynamicForm.test.ts
git commit -m "feat(workflow): add DynamicForm runtime field renderer"
cd ..
git add frontend/app/components/workflow/DynamicFormField.vue frontend/app/components/workflow/DynamicForm.vue frontend/app/tests/unit/components/DynamicFormField.test.ts frontend/app/tests/unit/components/DynamicForm.test.ts
git commit -m "feat(workflow): add DynamicForm runtime field renderer"
```

---

## Task 9: Frontend — `useEngineFormSchema.ts` composable (fetch resolved form-schema)

**Files:**
- Create: `frontend/app/composables/useEngineFormSchema.ts`
- Test: `frontend/app/tests/unit/composables/useEngineFormSchema.test.ts`

**Interfaces:**
- Consumes: `useApi()`, `extractApiErrorMessage`, `EngineFormSchema` type (Task 3), calls Task 2's `GET /api/v1/engine-requests/{id}/form-schema` endpoint.
- Produces: `useEngineFormSchema()` → `{ fieldGroups: Ref<ResolvedFieldGroup[]>, loading: Ref<boolean>, error: Ref<string | null>, fetchSchema(requestId: number): Promise<void> }`. Task 10 (detail page) consumes this directly.

- [ ] **Step 1: Write the failing test**

Create `frontend/app/tests/unit/composables/useEngineFormSchema.test.ts`:

```ts
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useEngineFormSchema } from '@/composables/useEngineFormSchema'

const mockGet = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

describe('useEngineFormSchema', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('fetchSchema populates fieldGroups on success', async () => {
    mockGet.mockResolvedValue({ data: { field_groups: [{ id: 1, name: 'g', label: 'مجموعة', sort_order: 0, fields: [] }] } })
    const { fieldGroups, fetchSchema } = useEngineFormSchema()

    await fetchSchema(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/5/form-schema')
    expect(fieldGroups.value).toHaveLength(1)
  })

  it('fetchSchema sets error and empties fieldGroups on failure', async () => {
    mockGet.mockRejectedValue({ data: { message: 'فشل' } })
    const { fieldGroups, error, fetchSchema } = useEngineFormSchema()

    await fetchSchema(5)

    expect(fieldGroups.value).toEqual([])
    expect(error.value).toBe('فشل')
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm exec vitest run app/tests/unit/composables/useEngineFormSchema.test.ts`
Expected: FAIL — module not found.

- [ ] **Step 3: Implement the composable**

Create `frontend/app/composables/useEngineFormSchema.ts`:

```ts
import { ref } from 'vue'
import type { ResolvedFieldGroup } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorMessage } from '@/utils/apiErrors'

export function useEngineFormSchema() {
  const api = useApi()
  const fieldGroups = ref<ResolvedFieldGroup[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchSchema = async (requestId: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: { field_groups: ResolvedFieldGroup[] } }>(
        `/api/v1/engine-requests/${requestId}/form-schema`,
      )
      fieldGroups.value = response.data.field_groups
    } catch (cause: unknown) {
      fieldGroups.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل نموذج الطلب.')
    } finally {
      loading.value = false
    }
  }

  return { fieldGroups, loading, error, fetchSchema }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/composables/useEngineFormSchema.test.ts`
Expected: PASS (both tests).

- [ ] **Step 5: Lint check**

Run: `pnpm exec eslint app/composables/useEngineFormSchema.ts`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
cd frontend
git add app/composables/useEngineFormSchema.ts app/tests/unit/composables/useEngineFormSchema.test.ts
git commit -m "feat(workflow): add useEngineFormSchema composable"
cd ..
git add frontend/app/composables/useEngineFormSchema.ts frontend/app/tests/unit/composables/useEngineFormSchema.test.ts
git commit -m "feat(workflow): add useEngineFormSchema composable"
```

---

## Task 10: Frontend — `/workflows` queue page

**Files:**
- Create: `frontend/app/pages/workflows/index.vue`
- Test: `frontend/app/tests/unit/pages/workflows-index.test.ts`

**Interfaces:**
- Consumes: `useEngineRequestsStore()` (Task 6) — `loadQueue()`, `loadList()`, `queue`, `instances`, `loading`, `error` state.
- Produces: a page mounted at `/workflows` listing the user's دوري queue by default with a toggle to view all visible instances. No new exports — this is a leaf page.

- [ ] **Step 1: Write the failing test**

Create `frontend/app/tests/unit/pages/workflows-index.test.ts`:

```ts
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import WorkflowsIndexPage from '@/pages/workflows/index.vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'

vi.mock('@/composables/useEngineRequests', () => ({
  useEngineRequests: () => ({
    instances: { value: [] },
    instancesMeta: { value: null },
    queue: { value: [] },
    queueMeta: { value: null },
    availableWorkflows: { value: [] },
    current: { value: null },
    loading: { value: false },
    error: { value: null },
    fetchList: vi.fn(),
    fetchQueue: vi.fn(),
    fetchAvailableWorkflows: vi.fn(),
    create: vi.fn(),
    show: vi.fn(),
    saveDraft: vi.fn(),
  }),
}))

describe('workflows/index.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('calls loadQueue on mount', () => {
    const store = useEngineRequestsStore()
    const spy = vi.spyOn(store, 'loadQueue')
    mount(WorkflowsIndexPage, {
      global: { stubs: { NuxtLink: true } },
    })
    expect(spy).toHaveBeenCalled()
  })

  it('renders an empty state when the queue has no items', () => {
    const wrapper = mount(WorkflowsIndexPage, {
      global: { stubs: { NuxtLink: true } },
    })
    expect(wrapper.findComponent({ name: 'Empty' }).exists()).toBe(true)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm exec vitest run app/tests/unit/pages/workflows-index.test.ts`
Expected: FAIL — page file not found.

- [ ] **Step 3: Implement the page**

Create `frontend/app/pages/workflows/index.vue`:

```vue
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
  TableEmpty,
} from '@/components/ui/table'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Empty, EmptyMedia, EmptyHeader, EmptyTitle, EmptyDescription, EmptyContent } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertTitle, AlertDescription, AlertAction } from '@/components/ui/alert'
import { CheckCircle2, FileText, AlertCircle } from 'lucide-vue-next'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const store = useEngineRequestsStore()
const view = ref<'queue' | 'all'>('queue')

function load() {
  if (view.value === 'queue') {
    store.loadQueue()
  } else {
    store.loadList()
  }
}

onMounted(load)

const rows = computed(() => (view.value === 'queue' ? store.queue : store.instances))
</script>

<template>
  <div class="flex flex-col gap-6 p-6" dir="rtl">
    <div class="flex items-center justify-between">
      <h1 class="text-lg font-semibold text-foreground">سير العمل الديناميكي</h1>
      <Button @click="navigateTo('/workflows/new')">
        <FileText class="h-4 w-4 me-2" aria-hidden="true" />
        طلب جديد
      </Button>
    </div>

    <Tabs v-model="view" @update:model-value="load">
      <TabsList variant="line">
        <TabsTrigger value="queue">طابوري</TabsTrigger>
        <TabsTrigger value="all">جميع الطلبات</TabsTrigger>
      </TabsList>
    </Tabs>

    <Alert v-if="store.error" variant="destructive" role="alert">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>خطأ في التحميل</AlertTitle>
      <AlertDescription>{{ store.error }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="load">إعادة المحاولة</Button>
      </AlertAction>
    </Alert>

    <Card v-else class="border-0 shadow">
      <CardHeader class="pb-2">
        <CardTitle class="text-sm font-semibold">{{ view === 'queue' ? 'طابور العمل' : 'جميع الطلبات' }}</CardTitle>
      </CardHeader>
      <CardContent class="p-0">
        <template v-if="store.loading">
          <div class="flex flex-col gap-2 p-4">
            <Skeleton v-for="n in 5" :key="n" class="h-10 w-full" />
          </div>
        </template>
        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead class="text-right">المرجع</TableHead>
              <TableHead class="text-right">المرحلة الحالية</TableHead>
              <TableHead class="text-right">الحالة</TableHead>
              <TableHead class="text-right">إجراء</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="instance in rows"
              :key="instance.id"
              class="cursor-pointer"
              @click="navigateTo(`/workflows/instances/${instance.id}`)"
            >
              <TableCell class="font-mono text-primary">{{ instance.reference }}</TableCell>
              <TableCell>{{ instance.current_stage?.name ?? '—' }}</TableCell>
              <TableCell>{{ instance.status }}</TableCell>
              <TableCell @click.stop>
                <Button size="sm" variant="outline" @click="navigateTo(`/workflows/instances/${instance.id}`)">عرض</Button>
              </TableCell>
            </TableRow>
            <TableEmpty v-if="rows.length === 0" :columns="4">
              <Empty>
                <EmptyMedia variant="icon"><CheckCircle2 /></EmptyMedia>
                <EmptyHeader>
                  <EmptyTitle>{{ view === 'queue' ? 'الطابور فارغ' : 'لا توجد طلبات' }}</EmptyTitle>
                  <EmptyDescription>
                    {{ view === 'queue' ? 'لا توجد طلبات في انتظار إجرائك حالياً ✓' : 'لم يتم إنشاء أي طلبات بعد.' }}
                  </EmptyDescription>
                </EmptyHeader>
                <EmptyContent v-if="view === 'all'">
                  <Button @click="navigateTo('/workflows/new')">إنشاء طلب جديد</Button>
                </EmptyContent>
              </Empty>
            </TableEmpty>
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  </div>
</template>
```

Confirm `Tabs`'s `v-model`/`@update:model-value` binding name matches the shadcn-vue `Tabs` component's actual prop/event (the SHADCN.md recipe shows `<Tabs v-model="activeTab">`, implying `v-model` works, but the explicit `@update:model-value="load"` handler added here for the reload-on-tab-change behavior should be verified against the component source — if `Tabs` doesn't emit that event name, switch to a `watch(view, load)` in `<script setup>` instead).

- [ ] **Step 4: Run test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/pages/workflows-index.test.ts`
Expected: PASS (both tests).

- [ ] **Step 5: Lint check**

Run: `pnpm exec eslint app/pages/workflows/index.vue`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
cd frontend
git add app/pages/workflows/index.vue app/tests/unit/pages/workflows-index.test.ts
git commit -m "feat(workflow): add dynamic workflow queue page"
cd ..
git add frontend/app/pages/workflows/index.vue frontend/app/tests/unit/pages/workflows-index.test.ts
git commit -m "feat(workflow): add dynamic workflow queue page"
```

---

## Task 11: Frontend — `/workflows/new` create page

**Files:**
- Create: `frontend/app/pages/workflows/new.vue`
- Test: `frontend/app/tests/unit/pages/workflows-new.test.ts`

**Interfaces:**
- Consumes: `useEngineRequestsStore()` (Task 6) — `loadAvailableWorkflows()`, `availableWorkflows`, `createInstance(payload)`.
- Produces: a page mounted at `/workflows/new` that lists pickable workflows and creates an instance with empty `data: {}` at the initial stage, then redirects to the detail page where the first-stage `DynamicForm` is filled in (the create step itself does NOT render the dynamic form — `EngineRequestService::create` accepts `data: []` minimum per `StoreEngineRequestRequest`'s `'data' => ['required', 'array']` rule, and the detail page's draft-save flow handles filling in the initial stage's fields after creation, consistent with `EngineRequestService::create()`'s internal validation being non-required-enforcing, confirmed during planning: `validateStage(..., false)` for create, `enforceRequired = false`).

- [ ] **Step 1: Write the failing test**

Create `frontend/app/tests/unit/pages/workflows-new.test.ts`:

```ts
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import WorkflowsNewPage from '@/pages/workflows/new.vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'

const mockNavigateTo = vi.fn()
vi.stubGlobal('navigateTo', mockNavigateTo)

vi.mock('@/composables/useEngineRequests', () => ({
  useEngineRequests: () => ({
    instances: { value: [] },
    instancesMeta: { value: null },
    queue: { value: [] },
    queueMeta: { value: null },
    availableWorkflows: { value: [{ id: 1, code: 'IMPORT_FINANCING', name: 'تمويل الواردات', version_id: 10, version_number: 1 }] },
    current: { value: null },
    loading: { value: false },
    error: { value: null },
    fetchList: vi.fn(),
    fetchQueue: vi.fn(),
    fetchAvailableWorkflows: vi.fn(),
    create: vi.fn().mockResolvedValue({ id: 99 }),
    show: vi.fn(),
    saveDraft: vi.fn(),
  }),
}))

describe('workflows/new.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockNavigateTo.mockReset()
  })

  it('loads available workflows on mount', () => {
    const store = useEngineRequestsStore()
    const spy = vi.spyOn(store, 'loadAvailableWorkflows')
    mount(WorkflowsNewPage)
    expect(spy).toHaveBeenCalled()
  })

  it('lists each available workflow as a selectable option', async () => {
    const wrapper = mount(WorkflowsNewPage)
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('تمويل الواردات')
  })

  it('creates an instance and navigates to its detail page on confirm', async () => {
    const store = useEngineRequestsStore()
    vi.spyOn(store, 'createInstance').mockResolvedValue({ id: 99 } as never)
    const wrapper = mount(WorkflowsNewPage)
    await wrapper.vm.$nextTick()

    await wrapper.find('[data-testid="create-instance-1"]').trigger('click')

    expect(store.createInstance).toHaveBeenCalledWith({ workflow_version_id: 10, data: {} })
    expect(mockNavigateTo).toHaveBeenCalledWith('/workflows/instances/99')
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm exec vitest run app/tests/unit/pages/workflows-new.test.ts`
Expected: FAIL — page file not found.

- [ ] **Step 3: Implement the page**

Create `frontend/app/pages/workflows/new.vue`:

```vue
<script setup lang="ts">
import { onMounted } from 'vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Empty, EmptyMedia, EmptyHeader, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import { Inbox } from 'lucide-vue-next'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests', requiredCapability: 'CREATE' })

const store = useEngineRequestsStore()

onMounted(() => {
  store.loadAvailableWorkflows()
})

async function startWorkflow(versionId: number) {
  const instance = await store.createInstance({ workflow_version_id: versionId, data: {} })
  await navigateTo(`/workflows/instances/${instance.id}`)
}
</script>

<template>
  <div class="flex flex-col gap-6 p-6" dir="rtl">
    <h1 class="text-lg font-semibold text-foreground">إنشاء طلب جديد</h1>

    <div v-if="store.loading" class="grid grid-cols-2 gap-4">
      <Skeleton v-for="n in 2" :key="n" class="h-32 w-full rounded-xl" />
    </div>

    <Empty v-else-if="store.availableWorkflows.length === 0">
      <EmptyMedia variant="icon"><Inbox /></EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>لا توجد مسارات عمل متاحة</EmptyTitle>
        <EmptyDescription>لا يوجد مسار عمل منشور يمكنك بدء طلب جديد ضمنه حالياً.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-else class="grid grid-cols-2 gap-4">
      <Card v-for="workflow in store.availableWorkflows" :key="workflow.version_id" class="border-0 shadow">
        <CardHeader>
          <CardTitle class="text-sm font-semibold">{{ workflow.name }}</CardTitle>
          <CardDescription class="text-xs">{{ workflow.code }} — الإصدار {{ workflow.version_number }}</CardDescription>
        </CardHeader>
        <CardFooter>
          <Button :data-testid="`create-instance-${workflow.id}`" @click="startWorkflow(workflow.version_id)">
            بدء الطلب
          </Button>
        </CardFooter>
      </Card>
    </div>
  </div>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/pages/workflows-new.test.ts`
Expected: PASS (all 3 tests).

- [ ] **Step 5: Lint check**

Run: `pnpm exec eslint app/pages/workflows/new.vue`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
cd frontend
git add app/pages/workflows/new.vue app/tests/unit/pages/workflows-new.test.ts
git commit -m "feat(workflow): add dynamic workflow instance creation page"
cd ..
git add frontend/app/pages/workflows/new.vue frontend/app/tests/unit/pages/workflows-new.test.ts
git commit -m "feat(workflow): add dynamic workflow instance creation page"
```

---

## Task 12: Frontend — `/workflows/instances/[id]` detail page

**Files:**
- Create: `frontend/app/pages/workflows/instances/[id].vue`
- Test: `frontend/app/tests/unit/pages/workflows-instance-detail.test.ts`

**Interfaces:**
- Consumes: `useEngineRequestsStore()` (Task 6), `useEngineFormSchema()` (Task 9), `DynamicForm.vue` (Task 8, via its exposed `validate()` method and `ref` template access), `WorkflowGraph`/`WorkflowGraphEdge` types (existing).
- Produces: the final leaf page. No exports consumed elsewhere.

This page derives "available actions" by filtering `store.graph.edges` where `edge.from_stage_id === store.current.current_stage.id` — confirmed during planning this is the only way to get transitions-from-current-stage client-side, since no dedicated "available actions for this instance" endpoint exists (the `graph` endpoint already returns `state: 'current' | 'executed' | 'possible'` per node/edge, computed server-side in `WorkflowGraphService`/`EngineRequestController::graph()`). Per the approved "pure render" decision, the UI shows every structurally-possible edge from the current stage and lets the server's `executeAction` 403 if the specific user lacks EXECUTE — it does not pre-filter by permission.

- [ ] **Step 1: Write the failing test**

Create `frontend/app/tests/unit/pages/workflows-instance-detail.test.ts`:

```ts
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import WorkflowInstanceDetailPage from '@/pages/workflows/instances/[id].vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'

vi.mock('vue-router', async () => {
  const actual = await vi.importActual('vue-router')
  return { ...actual, useRoute: () => ({ params: { id: '5' } }) }
})

vi.mock('@/composables/useEngineRequests', () => ({
  useEngineRequests: () => ({
    instances: { value: [] },
    instancesMeta: { value: null },
    queue: { value: [] },
    queueMeta: { value: null },
    availableWorkflows: { value: [] },
    current: { value: { id: 5, reference: 'ENG-2026-000005', status: 'ACTIVE', version: 1, current_stage: { id: 1, code: 'INTAKE', name: 'استلام' }, data: {} } },
    loading: { value: false },
    error: { value: null },
    fetchList: vi.fn(),
    fetchQueue: vi.fn(),
    fetchAvailableWorkflows: vi.fn(),
    create: vi.fn(),
    show: vi.fn().mockResolvedValue({ id: 5, reference: 'ENG-2026-000005', status: 'ACTIVE', version: 1, current_stage: { id: 1, code: 'INTAKE', name: 'استلام' }, data: {} }),
    saveDraft: vi.fn(),
  }),
}))

vi.mock('@/composables/useEngineRequestHistory', () => ({
  useEngineRequestHistory: () => ({
    history: { value: [] },
    graph: {
      value: {
        nodes: [{ id: 1, code: 'INTAKE', name: 'استلام', display_label: null, is_initial: true, is_final: false, sort_order: 0 }],
        edges: [{ id: 9, from_stage_id: 1, to_stage_id: 2, action_id: 1, action_code: 'SUBMIT', action_name: 'إرسال', requires_comment: false, is_self_loop: false, is_return: false }],
      },
    },
    loading: { value: false },
    error: { value: null },
    fetchHistory: vi.fn(),
    fetchGraph: vi.fn(),
  }),
}))

vi.mock('@/composables/useEngineRequestDocuments', () => ({
  useEngineRequestDocuments: () => ({
    documents: { value: [] },
    loading: { value: false },
    error: { value: null },
    fetchDocuments: vi.fn(),
    upload: vi.fn(),
    remove: vi.fn(),
    downloadUrl: vi.fn(),
  }),
}))

vi.mock('@/composables/useEngineRequestActions', () => ({
  useEngineRequestActions: () => ({
    executing: { value: false },
    conflictError: { value: false },
    fieldErrors: { value: {} },
    executeAction: vi.fn().mockResolvedValue({ id: 5, version: 2 }),
  }),
}))

vi.mock('@/composables/useEngineFormSchema', () => ({
  useEngineFormSchema: () => ({
    fieldGroups: { value: [] },
    loading: { value: false },
    error: { value: null },
    fetchSchema: vi.fn(),
  }),
}))

describe('workflows/instances/[id].vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('loads the instance on mount', () => {
    const store = useEngineRequestsStore()
    const spy = vi.spyOn(store, 'loadInstance')
    mount(WorkflowInstanceDetailPage, { global: { stubs: { NuxtLink: true } } })
    expect(spy).toHaveBeenCalledWith(5)
  })

  it('renders the instance reference', async () => {
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs: { NuxtLink: true } } })
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('ENG-2026-000005')
  })

  it('renders available actions derived from graph edges matching the current stage', async () => {
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs: { NuxtLink: true } } })
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('إرسال')
  })

  it('shows a conflict banner when conflictError is true after an action attempt', async () => {
    const { useEngineRequestActions } = await import('@/composables/useEngineRequestActions')
    vi.mocked(useEngineRequestActions).mockReturnValue({
      executing: { value: false } as never,
      conflictError: { value: true } as never,
      fieldErrors: { value: {} } as never,
      executeAction: vi.fn(),
    })
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs: { NuxtLink: true } } })
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('تم تحديث الطلب من مستخدم آخر')
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm exec vitest run app/tests/unit/pages/workflows-instance-detail.test.ts`
Expected: FAIL — page file not found.

- [ ] **Step 3: Implement the page**

Create `frontend/app/pages/workflows/instances/[id].vue`:

```vue
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useEngineFormSchema } from '@/composables/useEngineFormSchema'
import { useEngineRequestActions } from '@/composables/useEngineRequestActions'
import DynamicForm from '@/components/workflow/DynamicForm.vue'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { Field, FieldLabel } from '@/components/ui/field'
import { AlertTriangle } from 'lucide-vue-next'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const route = useRoute()
const requestId = computed(() => Number(route.params.id))

const store = useEngineRequestsStore()
const { fieldGroups, fetchSchema } = useEngineFormSchema()
const { executeAction, conflictError, fieldErrors } = useEngineRequestActions()

const formRef = ref<InstanceType<typeof DynamicForm> | null>(null)
const formData = ref<Record<string, unknown>>({})
const comment = ref('')

async function load() {
  await store.loadInstance(requestId.value)
  await fetchSchema(requestId.value)
  formData.value = store.current?.data ?? {}
}

onMounted(load)

const availableActions = computed(() => {
  if (!store.current?.current_stage || !store.graph) {
    return []
  }
  return store.graph.edges.filter((edge) => edge.from_stage_id === store.current!.current_stage!.id)
})

async function runAction(transitionId: number, requiresComment: boolean) {
  if (requiresComment && !comment.value.trim()) {
    return
  }
  const validation = await formRef.value?.validate()
  if (validation && !validation.valid) {
    return
  }
  try {
    await executeAction(
      requestId.value,
      transitionId,
      comment.value || null,
      validation?.values ?? formData.value,
      store.current!.version,
    )
    comment.value = ''
    await load()
  } catch {
    // conflictError / fieldErrors refs already updated by the composable; surfaced in the template.
  }
}
</script>

<template>
  <div class="flex flex-col gap-6 p-6" dir="rtl">
    <div v-if="store.loading">
      <Skeleton class="h-8 w-64 mb-4" />
      <Skeleton class="h-48 w-full" />
    </div>

    <template v-else-if="store.current">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold text-foreground">{{ store.current.reference }}</h1>
          <Badge variant="outline" class="mt-1">{{ store.current.current_stage?.name ?? '—' }}</Badge>
        </div>
      </div>

      <Alert v-if="conflictError" variant="destructive" role="alert">
        <AlertTriangle class="h-4 w-4" />
        <AlertTitle>تعارض في التحديث</AlertTitle>
        <AlertDescription>تم تحديث الطلب من مستخدم آخر. تم تحديث البيانات المعروضة.</AlertDescription>
      </Alert>

      <Tabs default-value="form" dir="rtl">
        <TabsList>
          <TabsTrigger value="form">النموذج</TabsTrigger>
          <TabsTrigger value="history">السجل</TabsTrigger>
          <TabsTrigger value="documents">المرفقات</TabsTrigger>
        </TabsList>

        <TabsContent value="form" class="mt-4">
          <Card class="border-0 shadow">
            <CardContent class="p-4">
              <DynamicForm
                ref="formRef"
                v-model="formData"
                :field-groups="fieldGroups"
                mode="edit"
                :request-id="requestId"
              />

              <Field class="mt-4">
                <FieldLabel for="comment">ملاحظات</FieldLabel>
                <Textarea id="comment" v-model="comment" placeholder="أضف ملاحظاتك هنا…" rows="3" />
              </Field>

              <div class="mt-4 flex gap-2">
                <Button
                  v-for="action in availableActions"
                  :key="action.id"
                  @click="runAction(action.id, action.requires_comment)"
                >
                  {{ action.action_name ?? action.action_code }}
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="history" class="mt-4">
          <Card class="border-0 shadow">
            <CardHeader><CardTitle class="text-sm font-semibold">سجل الإجراءات</CardTitle></CardHeader>
            <CardContent class="flex flex-col gap-2">
              <div v-for="entry in store.history" :key="entry.id" class="border-b border-border pb-2 last:border-0">
                <p class="text-sm">{{ entry.action_code }} — {{ entry.performed_by?.name ?? '—' }}</p>
                <p v-if="entry.comments" class="text-xs text-muted-foreground">{{ entry.comments }}</p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="documents" class="mt-4">
          <Card class="border-0 shadow">
            <CardHeader><CardTitle class="text-sm font-semibold">المرفقات</CardTitle></CardHeader>
            <CardContent class="flex flex-col gap-2">
              <div v-for="doc in store.documents" :key="doc.id" class="text-sm">{{ doc.original_name }}</div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </template>
  </div>
</template>
```

Confirm `DynamicForm`'s `ref` exposes `validate` correctly through `<script setup>` + `defineExpose` when accessed via template `ref="formRef"` — this is standard Vue 3 behavior, no special handling needed, but verify the mounted test (Step 4) actually triggers `formRef.value?.validate` without it being `null` (template refs populate after the component mounts; since `runAction` is only called from a user click after mount, this should be safe, but confirm in the test run rather than assuming).

- [ ] **Step 4: Run test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/pages/workflows-instance-detail.test.ts`
Expected: PASS (all 4 tests).

- [ ] **Step 5: Lint check**

Run: `pnpm exec eslint "app/pages/workflows/instances/[id].vue"`
Expected: no errors.

- [ ] **Step 6: Typecheck**

Run: `pnpm typecheck`
Expected: no new errors introduced by this plan's changes (Tasks 1-12 collectively).

- [ ] **Step 7: Commit**

```bash
cd frontend
git add "app/pages/workflows/instances/[id].vue" app/tests/unit/pages/workflows-instance-detail.test.ts
git commit -m "feat(workflow): add dynamic workflow instance detail page"
cd ..
git add "frontend/app/pages/workflows/instances/[id].vue" frontend/app/tests/unit/pages/workflows-instance-detail.test.ts
git commit -m "feat(workflow): add dynamic workflow instance detail page"
```

---

## Manual verification (after all tasks complete)

Run the focused test suite for everything touched in this plan:

```bash
cd frontend
pnpm exec vitest run app/tests/unit/composables/useEngineRequests.test.ts app/tests/unit/composables/useEngineRequestActions.test.ts app/tests/unit/composables/useEngineRequestHistory.test.ts app/tests/unit/composables/useEngineRequestDocuments.test.ts app/tests/unit/composables/useDynamicFormSchema.test.ts app/tests/unit/composables/useEngineFormSchema.test.ts app/tests/unit/stores/engineRequests.store.test.ts app/tests/unit/components/DynamicFormField.test.ts app/tests/unit/components/DynamicForm.test.ts app/tests/unit/pages/workflows-index.test.ts app/tests/unit/pages/workflows-new.test.ts app/tests/unit/pages/workflows-instance-detail.test.ts

cd ../backend
php artisan test --filter=EngineRequestTest
```

Then start the dev server and walk the golden path manually with `playwright-cli` per project convention: log in as a user with `requests`/`CREATE` screen capability and a role with EXECUTE access on the seeded `IMPORT_FINANCING` initial stage → navigate to `/workflows/new` → create an instance → land on `/workflows/instances/{id}` → fill the rendered `DynamicForm` fields → click an available action → confirm the instance advances to the next stage and the history tab shows the new entry. This manual pass is required because Vitest mocks the API layer entirely — it does not prove the real backend/frontend wiring works end-to-end.

## Self-review notes

- **Spec coverage:** §1 (queue/create/detail pages) → Tasks 6, 10, 11, 12. §2 (dynamic form renderer, runtime Zod schema, field types, DYNAMIC_SELECT, FILE upload, visibility/editability) → Tasks 3, 7, 8. §3 (error handling: 409/403/422) → Tasks 5, 12. §4 (testing) → every task has Vitest coverage; Playwright explicitly deferred per spec, called out in Manual Verification instead. Two backend gaps found during planning (no requester-facing workflow list, no requester-facing field-schema read) are closed in Tasks 1-2, consistent with the user's explicit approval to add small scoped backend endpoints rather than violate the "frontend-only" assumption silently.
- **Type consistency:** `EngineRequest`/`ResolvedFieldGroup`/`ResolvedFieldDefinition`/`EngineHistoryEntry`/`AvailableWorkflow` defined once in Task 3 and referenced identically (same field names) across Tasks 4-12 — no renamed duplicates introduced.
- **Known fragile spots flagged inline for the implementer to verify against actual source before trusting this plan's assumption:** exact `useApi()` method signatures (Task 4 Step 3, Task 5 Step 5), `Checkbox`/`Tabs` exact event names (Task 8 Step 3, Task 10 Step 3), VeeValidate `useForm().errors`/`.validate()` return shape (Task 8 Step 7), `PermissionService` exact namespace and `WorkflowVersion::workflowDefinition()` relation name (Task 1 Step 4), factory existence for `FieldGroup`/`FieldDefinition`/`StageFieldRule`/`EngineRequest` (Task 2 Step 1). These are called out because they were inferred from partial reads during planning, not exhaustively confirmed — the implementer must verify each before treating the plan's code as final, per this skill's "no placeholders" rule applied honestly: the alternative to flagging them would be guessing silently, which is worse.
