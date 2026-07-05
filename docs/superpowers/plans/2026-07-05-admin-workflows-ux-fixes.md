# Admin Workflows UX Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix data-integrity and UX gaps across the five `/admin/workflows` designer tabs (stages, routing, transitions, fields, actions) per `docs/superpowers/specs/2026-07-05-admin-workflows-ux-design.md`.

**Architecture:** All backend CRUD infrastructure (controllers, services, form requests) already exists for stages/transitions/permissions/fields with optimistic-concurrency (`version` column) and audit logging via `WorkflowDesignerService`. Work is almost entirely: (a) one backend validation rule change (stage-permission required fields), (b) one backend guard addition (stage initial/final mutual exclusion), (c) frontend table/dialog/action additions that call already-existing composable methods or small composable additions that call already-existing backend endpoints.

**Tech Stack:** Laravel 11 / PHPUnit (backend), Nuxt 4 + Vue 4 + TypeScript + Vitest (frontend), shadcn-vue components, VeeValidate + Zod where already used per-file.

## Global Constraints

- Commit format: `type(scope): description`, scope `workflow` for all tasks in this plan (per `AGENTS.md` allowed scopes). End every commit message with `Co-Authored-By: Claude <noreply@anthropic.com>`. Commits stay signed — never `--no-gpg-sign`.
- Backend changes commit from repo root staging `backend/<files>`; frontend changes commit from repo root staging `frontend/<files>`.
- No raw HTML — every table/button/dialog/switch uses shadcn-vue primitives per `frontend/SHADCN.md`.
- RTL rules apply: `border-s-*` not `border-l-*`, action column stays rightmost, icon-only buttons get `aria-label`.
- Every new mutation affordance (edit buttons, new dialogs) wraps in `<ScreenGuard screen="workflow_designer" capability="MANAGE">` exactly like existing create/delete affordances in the same file.
- Toast copy follows the existing "تم تحديث ..." / "تعذّر ..." pattern already used in each file — reuse `extractApiErrorMessage(cause, fallback)`.
- Focused verification only: run the specific Vitest file/PHPUnit filter touched by each task. Do not run full `pnpm test` or full `php artisan test` unless a task says so explicitly.
- No new API endpoints — every task wires to a route/controller method that already exists (confirmed during investigation).

---

## Task 1: Backend — require org+team+role on stage permissions (not one-of-four)

**Files:**
- Modify: `backend/app/Http/Requests/StoreStagePermissionRequest.php`
- Modify: `backend/app/Http/Requests/UpdateStagePermissionRequest.php`
- Modify: `backend/tests/Feature/Workflow/StagePermissionTest.php:56-97` (existing tests `test_add_stage_permission_row` and `test_row_requires_at_least_one_match_field` and `test_role_must_belong_to_organization` need updating for the new required-fields rule)

**Interfaces:**
- Consumes: `App\Enums\StageAccessLevel`, `App\Http\Requests\StagePermissionConsistency::check()` (unchanged, still validates org/team/role coherence when org is set).
- Produces: `StoreStagePermissionRequest::rules()` and `UpdateStagePermissionRequest::rules()` now require `organization_id`, `team_id`, `role_id` (was `nullable`). `user_id` stays `nullable` (unused by the UI dialog, but not removed from the API surface).

- [ ] **Step 1: Read the current UpdateStagePermissionRequest to confirm its shape matches Store**

```bash
cat /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend/app/Http/Requests/UpdateStagePermissionRequest.php
```

Confirm it mirrors `StoreStagePermissionRequest`'s nullable org/team/role rules and its own `after()` "at least one" check (it should, since `StagePermissionConsistency` is shared).

- [ ] **Step 2: Update the failing test expectations first (TDD — adjust existing tests to the new contract)**

Replace `test_row_requires_at_least_one_match_field` in `backend/tests/Feature/Workflow/StagePermissionTest.php` (currently lines 75-83) with a test asserting all three are required:

```php
    public function test_row_requires_organization_team_and_role(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/permissions", [
                'access_level' => 'VIEW',
                'display_label' => 'Everyone',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id', 'team_id', 'role_id']);
    }

    public function test_row_requires_team_even_with_organization_and_role(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/permissions", [
                'organization_id' => $this->org->id,
                'role_id' => $this->role->id,
                'access_level' => 'VIEW',
                'display_label' => 'Missing team',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('team_id');
    }
```

Update `test_add_stage_permission_row` (currently lines 56-73) to include a team, since org+role alone will now fail validation:

```php
    public function test_add_stage_permission_row(): void
    {
        $team = \App\Models\Team::query()->create([
            'organization_id' => $this->org->id,
            'code' => 'reviewers',
            'name' => 'Reviewers Team',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/permissions", [
                'organization_id' => $this->org->id,
                'team_id' => $team->id,
                'role_id' => $this->role->id,
                'access_level' => 'EXECUTE',
                'display_label' => 'مراجعو البنك',
            ])->assertCreated()
            ->assertJsonPath('data.access_level', 'EXECUTE')
            ->assertJsonPath('data.display_label', 'مراجعو البنك');

        $this->assertDatabaseHas('stage_permissions', [
            'id' => $response->json('data.id'),
            'stage_id' => $this->stage->id,
            'team_id' => $team->id,
            'role_id' => $this->role->id,
        ]);
    }
```

Update `test_role_must_belong_to_organization` (currently lines 85-97) to include a valid team so the failure is isolated to the role mismatch:

```php
    public function test_role_must_belong_to_organization(): void
    {
        $otherOrg = Organization::query()->create(['code' => 'OTHER', 'name' => 'Other Org']);
        $team = \App\Models\Team::query()->create([
            'organization_id' => $this->org->id,
            'code' => 'reviewers',
            'name' => 'Reviewers Team',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/permissions", [
                'organization_id' => $otherOrg->id,
                'team_id' => $team->id,
                'role_id' => $this->role->id,
                'access_level' => 'VIEW',
                'display_label' => 'Mismatch',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');
    }
```

Also add a team to `test_update_access_level_and_label` (currently lines 99-115) since the permission is created directly via `stagePermissions()->create()` without a team, and update-validation will now require one to be present when other fields are supplied via the update payload — check whether `UpdateStagePermissionRequest` uses `sometimes` (partial update) or `required` semantics before editing; if `sometimes`, this existing test is unaffected since it doesn't submit `team_id`/`organization_id`/`role_id` in its PUT payload. Leave it unchanged unless Step 4 reveals a validation conflict, in which case add `'team_id' => $team->id` to the `stagePermissions()->create()` call.

- [ ] **Step 3: Run the updated tests to verify they fail against current (nullable) rules**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
php artisan test --filter=StagePermissionTest
```

Expected: `test_row_requires_organization_team_and_role` and `test_row_requires_team_even_with_organization_and_role` FAIL (team_id currently nullable, so no validation error raised for it). `test_add_stage_permission_row` and `test_role_must_belong_to_organization` should still PASS at this point (team_id is accepted as extra, ignored data) — if either fails unexpectedly, stop and investigate before continuing.

- [ ] **Step 4: Update StoreStagePermissionRequest validation rules**

Edit `backend/app/Http/Requests/StoreStagePermissionRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\StageAccessLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStagePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'access_level' => ['required', Rule::enum(StageAccessLevel::class)],
            'display_label' => ['required', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                StagePermissionConsistency::check($validator, $this->all());
            },
        ];
    }
}
```

(Removed the old "at least one of four" manual check since `organization_id`/`team_id`/`role_id` being `required` now makes it redundant; `StagePermissionConsistency::check()` is retained for the org/team/role cross-org mismatch check.)

- [ ] **Step 5: Update UpdateStagePermissionRequest validation rules to match**

Read the file first to confirm its exact current structure, then apply the equivalent change (org/team/role from `sometimes|nullable` to `sometimes|required` — using `sometimes` preserves partial-update semantics for a PUT that only changes `display_label` or `access_level`, but if org/team/role ARE included in the payload, all three must be included together). Use this rule set:

```php
    public function rules(): array
    {
        return [
            'organization_id' => ['sometimes', 'required', 'integer', 'exists:organizations,id'],
            'team_id' => ['sometimes', 'required', 'integer', 'exists:teams,id'],
            'role_id' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'access_level' => ['sometimes', Rule::enum(\App\Enums\StageAccessLevel::class)],
            'display_label' => ['sometimes', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
```

Keep its existing `after()` hook calling `StagePermissionConsistency::check()` unchanged.

- [ ] **Step 6: Run the tests again to verify they pass**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
php artisan test --filter=StagePermissionTest
```

Expected: PASS (all tests in the file).

- [ ] **Step 7: Format touched files and commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
vendor/bin/pint app/Http/Requests/StoreStagePermissionRequest.php app/Http/Requests/UpdateStagePermissionRequest.php --test
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Http/Requests/StoreStagePermissionRequest.php backend/app/Http/Requests/UpdateStagePermissionRequest.php backend/tests/Feature/Workflow/StagePermissionTest.php
git commit -m "$(cat <<'EOF'
fix(workflow): require org, team, and role on stage permission rows

Stage permissions previously accepted any one of organization/team/
role/user, which let admins create rows that couldn't resolve to a
concrete audience. The designer UI only ever offers org+team+role, so
the API now enforces all three together.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Backend — reject a stage marked both initial and final

**Files:**
- Modify: `backend/app/Services/Workflow/WorkflowDesignerService.php:340-394` (`createStage` and `updateStage` methods)
- Test: `backend/tests/Feature/Workflow/WorkflowStageTest.php`

**Interfaces:**
- Consumes: `App\Models\WorkflowStage` (has `is_initial`, `is_final` boolean columns, confirmed in migration `2026_06_23_000003_create_workflow_stages_table.php`), `Illuminate\Validation\ValidationException`.
- Produces: `WorkflowDesignerService::createStage()` and `::updateStage()` now throw `ValidationException` with an `is_final` (or `is_initial`) error key when the resolved stage attributes would set both flags `true` simultaneously. Callers (`WorkflowStageController::store`/`update`) already let `ValidationException` propagate to Laravel's default 422 JSON handler — no controller change needed.

- [ ] **Step 1: Write the failing tests**

Add to `backend/tests/Feature/Workflow/WorkflowStageTest.php` (after `test_setting_a_new_initial_stage_demotes_the_previous_one`, around line 148):

```php
    public function test_stage_cannot_be_both_initial_and_final_on_create(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/stages", [
                'code' => 'weird',
                'name' => 'Weird',
                'is_initial' => true,
                'is_final' => true,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('is_final');
    }

    public function test_stage_cannot_be_both_initial_and_final_on_update(): void
    {
        $stage = $this->draft->stages()->create([
            'code' => 'review',
            'name' => 'Review',
            'is_initial' => true,
        ])->refresh();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$this->draft->id}/stages/{$stage->id}", [
                'is_final' => true,
                'version' => 1,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('is_final');

        $this->assertDatabaseHas('workflow_stages', [
            'id' => $stage->id,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);
    }
```

The second test also asserts the row was NOT mutated (`version` still `1`) — the guard must run before the `DB::transaction()` commits, not after.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
php artisan test --filter=WorkflowStageTest
```

Expected: `test_stage_cannot_be_both_initial_and_final_on_create` and `test_stage_cannot_be_both_initial_and_final_on_update` FAIL (no guard exists yet — both requests currently succeed with 201/200).

- [ ] **Step 3: Add the guard to WorkflowDesignerService**

Read `backend/app/Services/Workflow/WorkflowDesignerService.php` around lines 1-20 to confirm `Illuminate\Validation\ValidationException` needs importing (it is NOT currently imported — confirmed via grep of the import block). Add the import:

```php
use Illuminate\Validation\ValidationException;
```

Add this private helper method near `demoteOtherInitialStages` (around line 580):

```php
    /**
     * A stage cannot be both the workflow's entry point and its terminal point —
     * a request that both starts and ends at the same stage has no transitions
     * to traverse. $resolvedInitial/$resolvedFinal are the values the stage WILL
     * have after the pending create/update is applied.
     */
    private function guardAgainstDualRoleStage(bool $resolvedInitial, bool $resolvedFinal): void
    {
        if ($resolvedInitial && $resolvedFinal) {
            throw ValidationException::withMessages([
                'is_final' => 'A stage cannot be marked as both the initial and final stage.',
            ]);
        }
    }
```

Call it in `createStage()` (around line 346, right after `$stage = $lockedVersion->stages()->create($attributes)->refresh();` but BEFORE the `is_initial` demotion block — actually call it before creation using the raw `$attributes` so an invalid stage is never persisted):

```php
    public function createStage(User $actor, WorkflowVersion $version, array $attributes): WorkflowStage
    {
        return DB::transaction(function () use ($actor, $version, $attributes): WorkflowStage {
            $lockedVersion = WorkflowVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $this->ensureEditable($lockedVersion);

            $this->guardAgainstDualRoleStage(
                (bool) ($attributes['is_initial'] ?? false),
                (bool) ($attributes['is_final'] ?? false),
            );

            $stage = $lockedVersion->stages()->create($attributes)->refresh();

            if ($stage->is_initial) {
                $this->demoteOtherInitialStages($lockedVersion, $stage);
            }

            $this->auditService->log(
                AuditAction::GOVERNANCE_CREATED,
                $actor,
                $stage,
                ['after' => $stage->toArray()],
            );

            return $stage;
        });
    }
```

Call it in `updateStage()` (around line 369, right after the row is locked, using the RESOLVED post-merge values — i.e. attributes not present in `$attributes` fall back to the stage's current value):

```php
    public function updateStage(
        User $actor,
        WorkflowStage $stage,
        array $attributes,
        int $expectedVersion,
    ): WorkflowStage {
        return DB::transaction(function () use ($actor, $stage, $attributes, $expectedVersion): WorkflowStage {
            $locked = WorkflowStage::query()->lockForUpdate()->findOrFail($stage->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);
            $parent = WorkflowVersion::query()->lockForUpdate()->findOrFail($locked->workflow_version_id);
            $this->ensureEditable($parent);

            $this->guardAgainstDualRoleStage(
                (bool) ($attributes['is_initial'] ?? $locked->is_initial),
                (bool) ($attributes['is_final'] ?? $locked->is_final),
            );

            $before = $locked->toArray();
            $locked->update([
                ...$attributes,
                'version' => $locked->version + 1,
            ]);

            if (($attributes['is_initial'] ?? false) === true) {
                $this->demoteOtherInitialStages($parent, $locked);
            }

            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->toArray()],
            );

            return $locked->refresh();
        });
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
php artisan test --filter=WorkflowStageTest
```

Expected: PASS (all tests in the file, including the two new ones and all pre-existing ones — confirm `test_setting_a_new_initial_stage_demotes_the_previous_one` and others still pass since the guard uses fallback-to-current-value logic that shouldn't affect single-flag updates).

- [ ] **Step 5: Format and commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
vendor/bin/pint app/Services/Workflow/WorkflowDesignerService.php --test
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Services/Workflow/WorkflowDesignerService.php backend/tests/Feature/Workflow/WorkflowStageTest.php
git commit -m "$(cat <<'EOF'
fix(workflow): reject a stage marked both initial and final

A request that both starts and ends at the same stage has no
transitions to traverse. Guard runs inside the existing create/update
transaction before the row is persisted, using resolved post-merge
values so a partial update (e.g. only is_final sent) is checked
against the stage's current is_initial value.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Frontend — StagePermissionEditor: add team column, edit action, require all 3 fields

**Files:**
- Modify: `frontend/app/components/workflow/StagePermissionEditor.vue`
- Modify: `frontend/app/composables/useStagePermissions.ts`
- Modify: `frontend/app/tests/unit/components/StagePermissionEditor.test.ts`

**Interfaces:**
- Consumes: `useTeams()` composable (`teams`, `fetchTeams`) — already imported in `StagePermissionEditor.vue` for the dialog's team `<Select>`, just needs a lookup helper added for the table.
- Produces: `useStagePermissions()` gains `updatePermission(permission: StagePermission, payload: Partial<StagePermissionPayload> & { version: number }): Promise<StagePermission>`, calling `PUT /api/v1/workflow-stages/{stageId}/permissions/{id}`.

- [ ] **Step 1: Write the failing test for the edit action and team column**

Add to `frontend/app/tests/unit/components/StagePermissionEditor.test.ts`, first update `mockGet`'s `teams` branch and `ORGS`/`ROLES` fixtures (around lines 23-24, 86) to include a team fixture, then add tests. Full updated test file section:

Replace lines 23-24:
```ts
const ORGS = [{ id: 1, code: 'CBY', name: 'البنك المركزي', is_active: true }]
const ROLES = [{ id: 2, code: 'REVIEWER', name: 'مراجع', organization_id: 1 }]
const TEAMS = [{ id: 3, code: 'REVIEW_TEAM', name: 'فريق المراجعة', organization_id: 1 }]
```

Replace the `makePermission` team_id default (line 31) to use the fixture team:
```ts
function makePermission(overrides: Partial<StagePermission> = {}): StagePermission {
  return {
    id: 1,
    stage_id: 5,
    organization_id: 1,
    team_id: 3,
    role_id: 2,
    user_id: null,
    access_level: 'EXECUTE',
    display_label: 'مراجعو البنك',
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}
```

Replace the `teams` mock branch (line 86):
```ts
    if (url.includes('teams')) return Promise.resolve({ data: TEAMS })
```

Add a `mockPut` alongside `mockPost`/`mockDelete` (lines 9-15):
```ts
const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut, del: mockDelete }),
}))
```

Add new test cases at the end of the `describe` block (before the closing `})` around line 158):
```ts
  it('shows the team label in the table', async () => {
    const wrapper = await mountEditor(['VIEW'])

    expect(wrapper.text()).toContain('فريق المراجعة')
  })

  it('shows an edit affordance for MANAGE users on a DRAFT version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'])

    expect(buttonByLabel(wrapper, 'تعديل الصلاحية')).toBeDefined()
  })

  it('hides edit affordance on a PUBLISHED version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'], 'PUBLISHED')

    expect(buttonByLabel(wrapper, 'تعديل الصلاحية')).toBeUndefined()
  })
```

- [ ] **Step 2: Run the test file to verify the new tests fail**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/components/StagePermissionEditor.test.ts
```

Expected: FAIL — team label not rendered, no button with `aria-label="تعديل الصلاحية"` exists yet.

- [ ] **Step 3: Add `updatePermission` to useStagePermissions composable**

Edit `frontend/app/composables/useStagePermissions.ts`, add after `createPermission` (before `deletePermission`):

```ts
  const updatePermission = async (
    permission: StagePermission,
    payload: Partial<StagePermissionPayload>,
  ) => {
    const response = await api.put<{ data: StagePermission }>(
      `/api/v1/workflow-stages/${permission.stage_id}/permissions/${permission.id}`,
      { ...payload, version: permission.version },
    )
    permissions.value = permissions.value.map((item) =>
      item.id === response.data.id ? response.data : item,
    )
    return response.data
  }
```

Add `updatePermission` to the returned object at the end of the file:

```ts
  return {
    permissions,
    loading,
    error,
    fetchPermissions,
    createPermission,
    updatePermission,
    deletePermission,
  }
```

- [ ] **Step 4: Update StagePermissionEditor.vue — script section**

Edit `frontend/app/components/workflow/StagePermissionEditor.vue`. Add `Pencil` to the lucide import (line 4):

```ts
import { Lock, Pencil, Plus, Trash2, Users } from 'lucide-vue-next'
```

Replace the composable destructure (line 53-54) to include `updatePermission`:

```ts
const { permissions, error, fetchPermissions, createPermission, updatePermission, deletePermission } =
  useStagePermissions()
```

Add a `teamName` lookup helper next to `orgName`/`roleName` (after line 82):

```ts
const teamName = (id: number | null) =>
  id === null ? '—' : (teams.value.find((t) => t.id === id)?.name ?? `#${id}`)
```

Add an `editing` ref next to `deleting` (line 61):

```ts
const editing = ref<StagePermission | null>(null)
const deleting = ref<StagePermission | null>(null)
```

Replace `openCreate` (lines 84-92) and add `openEdit`, then update `submit` (lines 94-112) to branch on `editing.value`:

```ts
function openCreate() {
  editing.value = null
  organizationId.value = ''
  teamId.value = ''
  roleId.value = ''
  accessLevel.value = 'EXECUTE'
  displayLabel.value = ''
  formError.value = null
  dialogOpen.value = true
}

function openEdit(permission: StagePermission) {
  editing.value = permission
  organizationId.value = permission.organization_id ? String(permission.organization_id) : ''
  teamId.value = permission.team_id ? String(permission.team_id) : ''
  roleId.value = permission.role_id ? String(permission.role_id) : ''
  accessLevel.value = permission.access_level
  displayLabel.value = permission.display_label
  formError.value = null
  if (organizationId.value) {
    fetchRoles(Number(organizationId.value))
    fetchTeams(Number(organizationId.value))
  }
  dialogOpen.value = true
}

async function submit() {
  if (!displayLabel.value || !organizationId.value || !teamId.value || !roleId.value) {
    formError.value = 'الجهة والفريق والدور مطلوبة جميعاً، مع إدخال تسمية.'
    return
  }
  const payload = {
    organization_id: Number(organizationId.value),
    team_id: Number(teamId.value),
    role_id: Number(roleId.value),
    access_level: accessLevel.value,
    display_label: displayLabel.value,
  }
  try {
    if (editing.value) {
      await updatePermission(editing.value, payload)
      toast.success('تم تحديث الصلاحية')
    } else {
      await createPermission(props.stage.id, payload)
      toast.success('تمت إضافة الصلاحية')
    }
    dialogOpen.value = false
  } catch (cause) {
    formError.value = extractApiErrorMessage(cause, 'تعذّر حفظ الصلاحية')
  }
}
```

Note the `submit()` validation changed from "at least one of org/team/role" to "all three required" (matches Task 1's backend rule), and the `watch(organizationId, ...)` block (lines 70-77) already clears `teamId`/`roleId` when org changes — keep that as-is, it still applies correctly to edit mode since `openEdit` sets `organizationId.value` directly (not via the watched ref's own reassignment path triggering a clear — confirm this doesn't clear team/role right after `openEdit` populates them; if the watch fires and wipes `teamId`/`roleId` back to `''` immediately after `openEdit` sets them, restructure `openEdit` to set `organizationId.value` first, await a `nextTick()`, then set `teamId`/`roleId`, OR temporarily suppress the watch during programmatic population using a guard flag).

Add a guard flag to prevent the watch from clearing the pre-filled values during `openEdit` (safer than `nextTick` timing):

```ts
const suppressCascadeClear = ref(false)

watch(organizationId, (value) => {
  if (suppressCascadeClear.value) return
  teamId.value = ''
  roleId.value = ''
  if (value) {
    fetchRoles(Number(value))
    fetchTeams(Number(value))
  }
})
```

And update `openEdit` to set the flag around the org assignment:

```ts
function openEdit(permission: StagePermission) {
  editing.value = permission
  suppressCascadeClear.value = true
  organizationId.value = permission.organization_id ? String(permission.organization_id) : ''
  teamId.value = permission.team_id ? String(permission.team_id) : ''
  roleId.value = permission.role_id ? String(permission.role_id) : ''
  accessLevel.value = permission.access_level
  displayLabel.value = permission.display_label
  formError.value = null
  if (organizationId.value) {
    fetchRoles(Number(organizationId.value))
    fetchTeams(Number(organizationId.value))
  }
  dialogOpen.value = true
  nextTick(() => { suppressCascadeClear.value = false })
}
```

Add `nextTick` to the vue import at the top of the file (line 2):

```ts
import { onMounted, nextTick, ref, watch } from 'vue'
```

- [ ] **Step 5: Update StagePermissionEditor.vue — template section**

Add a "الفريق" table header between "الجهة" and "الدور" (around line 162-163):

```vue
            <TableHead class="text-right">التسمية</TableHead>
            <TableHead class="text-right">الجهة</TableHead>
            <TableHead class="text-right">الفريق</TableHead>
            <TableHead class="text-right">الدور</TableHead>
            <TableHead class="text-right">المستوى</TableHead>
            <TableHead class="text-left">إجراء</TableHead>
```

Add the team cell in the row (around line 171-174):

```vue
            <TableCell class="font-medium">{{ permission.display_label }}</TableCell>
            <TableCell class="text-muted-foreground">{{
              orgName(permission.organization_id)
            }}</TableCell>
            <TableCell class="text-muted-foreground">{{ teamName(permission.team_id) }}</TableCell>
            <TableCell class="text-muted-foreground">{{ roleName(permission.role_id) }}</TableCell>
```

Add the edit button beside delete (around line 184-200), inside the action cell's flex container, BEFORE the delete button so tab order is edit-then-delete:

```vue
            <TableCell class="text-left" @click.stop>
              <div class="flex items-center justify-end gap-0.5">
                <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
                  <Tooltip>
                    <TooltipTrigger as-child>
                      <Button
                        size="icon-sm"
                        variant="ghost"
                        aria-label="تعديل الصلاحية"
                        @click="openEdit(permission)"
                      >
                        <Pencil class="h-3.5 w-3.5" />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>تعديل الصلاحية</TooltipContent>
                  </Tooltip>
                </ScreenGuard>
                <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
                  <Tooltip>
                    <TooltipTrigger as-child>
                      <Button
                        size="icon-sm"
                        variant="ghost"
                        aria-label="حذف الصلاحية"
                        @click="deleting = permission"
                      >
                        <Trash2 class="h-3.5 w-3.5 text-[var(--severity-red)]" />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>حذف الصلاحية</TooltipContent>
                  </Tooltip>
                </ScreenGuard>
                <span
                  v-if="!editable"
                  class="inline-flex items-center gap-1 text-xs text-[var(--locked)]"
                >
                  <Lock class="h-3 w-3" />مقفلة
                </span>
              </div>
            </TableCell>
```

Update the dialog title to reflect edit vs create (around line 216-218):

```vue
        <DialogHeader>
          <DialogTitle>{{ editing ? 'تعديل صلاحية مرحلة' : 'إضافة صلاحية مرحلة' }}</DialogTitle>
          <DialogDescription>تُشتق صلاحيات الطلبات والدوري من هذه الصفوف.</DialogDescription>
        </DialogHeader>
```

Update the three field labels to show they're required (around lines 227-267), adding a `*` suffix — Organization, Team, Role labels become "الجهة *", "الفريق *", "الدور *". Remove the "(اختياري)" placeholder wording since they're no longer optional:

```vue
          <div class="flex flex-col gap-1.5">
            <Label>الجهة *</Label>
            <Select v-model="organizationId">
              <SelectTrigger class="w-full"><SelectValue placeholder="اختر الجهة" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="org in organizations" :key="org.id" :value="String(org.id)">
                  {{ org.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label>الفريق *</Label>
            <Select v-model="teamId" :disabled="!organizationId">
              <SelectTrigger class="w-full"><SelectValue placeholder="اختر الفريق" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="team in teams" :key="team.id" :value="String(team.id)">
                  {{ team.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label>الدور *</Label>
            <Select v-model="roleId" :disabled="!organizationId">
              <SelectTrigger class="w-full"><SelectValue placeholder="اختر الدور" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="role in roles" :key="role.id" :value="String(role.id)">
                  {{ role.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
```

- [ ] **Step 6: Run the test file to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/components/StagePermissionEditor.test.ts
```

Expected: PASS (all tests, including the 3 new ones).

- [ ] **Step 7: Lint touched files and commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec eslint app/components/workflow/StagePermissionEditor.vue app/composables/useStagePermissions.ts
pnpm exec prettier app/components/workflow/StagePermissionEditor.vue app/composables/useStagePermissions.ts app/tests/unit/components/StagePermissionEditor.test.ts --check
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/components/workflow/StagePermissionEditor.vue frontend/app/composables/useStagePermissions.ts frontend/app/tests/unit/components/StagePermissionEditor.test.ts
git commit -m "$(cat <<'EOF'
feat(workflow): add team column, edit action, and required fields to stage permissions

Stage permission rows now show the team label alongside org/role, can
be edited (not just deleted), and the dialog requires organization,
team, and role together instead of accepting any one of four fields.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Frontend — WorkflowStageEditor: remove order column, convert start/end to switches with mutual exclusion

**Files:**
- Modify: `frontend/app/components/workflow/WorkflowStageEditor.vue`
- Modify: `frontend/app/tests/unit/components/WorkflowStageEditor.test.ts`

**Interfaces:**
- Consumes: existing `useWorkflowStages()` composable (`updateStage`, `createStage` — unchanged signatures).
- Produces: no new exports; dialog-level behavior change only (client-side mutual exclusion + switches instead of checkboxes for is_initial/is_final).

- [ ] **Step 1: Write the failing test for mutual exclusion and switch rendering**

Add to `frontend/app/tests/unit/components/WorkflowStageEditor.test.ts`, after the existing tests (before the closing `})` of the `describe` block, around line 132):

```ts
  it('does not render the sort-order column', async () => {
    const wrapper = await mountEditor(['VIEW'])

    expect(wrapper.text()).not.toContain('الترتيب')
  })

  it('disables the end-stage switch when start-stage is enabled in the create dialog', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'])
    const addButton = buttonByText(wrapper, 'إضافة مرحلة')
    await addButton?.trigger('click')

    const startSwitch = wrapper.find('#stage-initial')
    await startSwitch.trigger('click')

    const endSwitch = wrapper.find('#stage-final')
    expect(endSwitch.attributes('disabled')).toBeDefined()
  })
```

- [ ] **Step 2: Run the test file to verify the new tests fail**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/components/WorkflowStageEditor.test.ts
```

Expected: FAIL — "الترتيب" column still present; end switch not disabled (still a Checkbox, no mutual-exclusion watch yet).

- [ ] **Step 3: Update WorkflowStageEditor.vue — script section**

Add a `watch` import if not already present (line 2 currently has `onMounted, ref` — add `watch`):

```ts
import { onMounted, ref, watch } from 'vue'
```

Remove the standalone `Checkbox` import (line 31) since both toggles become `Switch` — confirm `Checkbox` isn't used elsewhere in this file (it is not, per the file read earlier: only these two spots use `Checkbox`). Remove:

```ts
import { Checkbox } from '@/components/ui/checkbox'
```

Add mutual-exclusion watches after the `requiresClaim` ref declaration (around line 74):

```ts
const isInitial = ref(false)
const isFinal = ref(false)
const requiresClaim = ref(false)

watch(isInitial, (value) => {
  if (value) isFinal.value = false
})
watch(isFinal, (value) => {
  if (value) isInitial.value = false
})
```

- [ ] **Step 4: Update WorkflowStageEditor.vue — template section**

Remove the "الترتيب" table header (line 192):

```vue
          <TableHeader>
            <TableRow class="bg-muted/50 hover:bg-muted/50">
              <TableHead class="text-right">الرمز</TableHead>
              <TableHead class="text-right">الاسم</TableHead>
              <TableHead class="text-right">النوع</TableHead>
              <TableHead class="text-left">إجراء</TableHead>
            </TableRow>
          </TableHeader>
```

Remove the corresponding sort_order table cell (lines 201-203):

```vue
            <TableRow v-for="stage in stages" :key="stage.id" class="even:bg-muted/30">
              <TableCell class="text-muted-foreground font-mono text-xs">{{
                stage.code
              }}</TableCell>
```

Replace the two `<Checkbox>` toggles with `<Switch>`, wired for mutual exclusion (lines 348-357):

```vue
        <div class="flex items-center gap-6">
          <div class="flex items-center gap-2">
            <Switch id="stage-initial" v-model="isInitial" :disabled="isFinal" />
            <Label for="stage-initial">مرحلة البداية</Label>
          </div>
          <div class="flex items-center gap-2">
            <Switch id="stage-final" v-model="isFinal" :disabled="isInitial" />
            <Label for="stage-final">مرحلة النهاية</Label>
          </div>
        </div>
```

- [ ] **Step 5: Run the test file to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/components/WorkflowStageEditor.test.ts
```

Expected: PASS (all tests, including the 2 new ones — the pre-existing "renders ordered stages with type badges" test checks for `'بداية'` text which is the row-level `<Badge>`, unaffected by the dialog-level Switch change).

- [ ] **Step 6: Lint and commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec eslint app/components/workflow/WorkflowStageEditor.vue
pnpm exec prettier app/components/workflow/WorkflowStageEditor.vue app/tests/unit/components/WorkflowStageEditor.test.ts --check
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/components/workflow/WorkflowStageEditor.vue frontend/app/tests/unit/components/WorkflowStageEditor.test.ts
git commit -m "$(cat <<'EOF'
feat(workflow): make start/end stage mutually exclusive switches

The sort-order column added no information the admin acts on, so it's
removed. Start/end stage are now Switch toggles (matching the
requires-claim toggle style) with client-side mutual exclusion:
enabling one immediately disables the other, since a stage can't both
start and end a workflow.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Frontend — WorkflowTransitionEditor: add edit action

**Files:**
- Modify: `frontend/app/components/workflow/WorkflowTransitionEditor.vue`
- Modify: `frontend/app/tests/unit/components/WorkflowTransitionEditor.test.ts`

**Interfaces:**
- Consumes: existing `useWorkflowTransitions()` composable's `updateTransition(transition, payload)` method (already implemented, confirmed during investigation).
- Produces: no new exports.

- [ ] **Step 1: Read the existing test file to match its exact fixture/mount pattern**

```bash
cat /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend/app/tests/unit/components/WorkflowTransitionEditor.test.ts
```

Use its `makeTransition()`, `mountEditor()`, `buttonByLabel()`, `buttonByText()` helpers (already present per the file structure seen during investigation) as the base for the new test additions below — do not redefine them.

- [ ] **Step 2: Write the failing test for the edit action**

Add to the `describe('WorkflowTransitionEditor', ...)` block:

```ts
  it('shows an edit affordance for MANAGE users on a DRAFT version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'])

    expect(buttonByLabel(wrapper, 'تعديل الانتقال')).toBeDefined()
  })

  it('hides edit affordance on a PUBLISHED version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'], 'PUBLISHED')

    expect(buttonByLabel(wrapper, 'تعديل الانتقال')).toBeUndefined()
  })
```

(Match parameter names/order to whatever `mountEditor`'s actual signature is in the existing file — read Step 1's output before finalizing this step's exact call shape.)

- [ ] **Step 3: Run the test file to verify the new tests fail**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/components/WorkflowTransitionEditor.test.ts
```

Expected: FAIL — no button with `aria-label="تعديل الانتقال"` exists yet.

- [ ] **Step 4: Update WorkflowTransitionEditor.vue — script section**

Add `Pencil` to the lucide import (line 4):

```ts
import { ArrowLeft, GitBranch, Lock, Pencil, Plus, Trash2 } from 'lucide-vue-next'
```

Update the composable destructure (line 68-69) to include `updateTransition`:

```ts
const { transitions, error, fetchTransitions, createTransition, updateTransition, deleteTransition } =
  useWorkflowTransitions()
```

Add an `editing` ref next to `deleting` (line 75):

```ts
const editing = ref<WorkflowTransition | null>(null)
const deleting = ref<WorkflowTransition | null>(null)
```

Replace `openCreate` (lines 91-99) and `submit` (lines 101-116) to support edit mode. `from_stage_id` and `action_id` stay locked on edit (changing either is equivalent to creating a different transition and risks the `unique(from_stage_id, action_id)` constraint) — only `to_stage_id`, `requires_comment`, `confirmation_message` are editable:

```ts
function openCreate() {
  editing.value = null
  fromStageId.value = ''
  actionId.value = ''
  toStageId.value = ''
  requiresComment.value = false
  confirmationMessage.value = ''
  formError.value = null
  dialogOpen.value = true
}

function openEdit(transition: WorkflowTransition) {
  editing.value = transition
  fromStageId.value = String(transition.from_stage_id)
  actionId.value = String(transition.action_id)
  toStageId.value = String(transition.to_stage_id)
  requiresComment.value = transition.requires_comment
  confirmationMessage.value = transition.confirmation_message ?? ''
  formError.value = null
  dialogOpen.value = true
}

async function submit() {
  if (!canSubmit.value) return
  try {
    if (editing.value) {
      await updateTransition(editing.value, {
        to_stage_id: Number(toStageId.value),
        requires_comment: requiresComment.value,
        confirmation_message: confirmationMessage.value || null,
      })
      toast.success('تم تحديث الانتقال')
    } else {
      await createTransition(props.version.id, {
        from_stage_id: Number(fromStageId.value),
        action_id: Number(actionId.value),
        to_stage_id: Number(toStageId.value),
        requires_comment: requiresComment.value,
        confirmation_message: confirmationMessage.value || null,
      })
      toast.success('تمت إضافة الانتقال')
    }
    dialogOpen.value = false
  } catch (cause) {
    formError.value = extractApiErrorMessage(cause, 'تعذّر حفظ الانتقال')
  }
}
```

- [ ] **Step 5: Update WorkflowTransitionEditor.vue — template section**

Add the edit button beside delete in the action cell (around lines 198-214), before delete:

```vue
              <TableCell class="text-left" @click.stop>
                <div class="flex items-center justify-end gap-0.5">
                  <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          aria-label="تعديل الانتقال"
                          @click="openEdit(transition)"
                        >
                          <Pencil class="h-3.5 w-3.5" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>تعديل الانتقال</TooltipContent>
                    </Tooltip>
                  </ScreenGuard>
                  <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          aria-label="حذف الانتقال"
                          @click="deleting = transition"
                        >
                          <Trash2 class="h-3.5 w-3.5 text-[var(--severity-red)]" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>حذف الانتقال</TooltipContent>
                    </Tooltip>
                  </ScreenGuard>
                  <span
                    v-if="!editable"
                    class="inline-flex items-center gap-1 text-xs text-[var(--locked)]"
                  >
                    <Lock class="h-3 w-3" />مقفلة
                  </span>
                </div>
              </TableCell>
```

Update dialog title and lock from/action selects when editing (around lines 232-260):

```vue
      <DialogHeader>
        <DialogTitle>{{ editing ? 'تعديل انتقال' : 'إضافة انتقال' }}</DialogTitle>
        <DialogDescription>اربط مرحلة المصدر بإجراء ومرحلة الوجهة.</DialogDescription>
      </DialogHeader>

      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1.5">
          <Label>من المرحلة</Label>
          <Select v-model="fromStageId" :disabled="editing !== null">
            <SelectTrigger class="w-full"><SelectValue placeholder="اختر المرحلة" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="stage in stages" :key="stage.id" :value="String(stage.id)">
                {{ stage.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="flex flex-col gap-1.5">
          <Label>الإجراء</Label>
          <Select v-model="actionId" :disabled="editing !== null">
            <SelectTrigger class="w-full"><SelectValue placeholder="اختر الإجراء" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="action in actions" :key="action.id" :value="String(action.id)">
                {{ action.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
```

- [ ] **Step 6: Run the test file to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/components/WorkflowTransitionEditor.test.ts
```

Expected: PASS (all tests, including the 2 new ones).

- [ ] **Step 7: Lint and commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec eslint app/components/workflow/WorkflowTransitionEditor.vue
pnpm exec prettier app/components/workflow/WorkflowTransitionEditor.vue app/tests/unit/components/WorkflowTransitionEditor.test.ts --check
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/components/workflow/WorkflowTransitionEditor.vue frontend/app/tests/unit/components/WorkflowTransitionEditor.test.ts
git commit -m "$(cat <<'EOF'
feat(workflow): add edit action to workflow transitions table

From-stage and action stay locked during edit (changing either is
equivalent to a different transition and risks the from+action unique
constraint); to-stage, requires-comment, and confirmation message
remain editable.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Frontend — WorkflowFieldDesigner: enable group select, add field edit action

**Files:**
- Modify: `frontend/app/components/workflow/WorkflowFieldDesigner.vue`
- Modify: `frontend/app/tests/unit/components/WorkflowFieldDesigner.test.ts`

**Interfaces:**
- Consumes: existing `useWorkflowFields()` composable's `updateField(versionId, field, payload)` method (already implemented, confirmed during investigation).
- Produces: no new exports.

- [ ] **Step 1: Read the existing test file to match its exact fixture/mount pattern**

```bash
cat /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend/app/tests/unit/components/WorkflowFieldDesigner.test.ts
```

- [ ] **Step 2: Write the failing tests**

Add to the `describe('WorkflowFieldDesigner', ...)` block (adapt fixture/mount helper names to match Step 1's actual file contents):

```ts
  it('enables the group select in the add-field dialog', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'])
    const addFieldButton = buttonByText(wrapper, 'إضافة حقل')
    await addFieldButton?.trigger('click')

    const groupTrigger = wrapper.find('[aria-label], select, [role="combobox"]')
    // The group Select's trigger button must not carry the disabled attribute.
    const disabledTriggers = wrapper.findAll('button[disabled]')
    const groupSelectDisabled = disabledTriggers.some((btn) =>
      btn.html().includes('اختر المجموعة'),
    )
    expect(groupSelectDisabled).toBe(false)
  })

  it('shows an edit affordance for existing fields', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'])

    expect(buttonByLabel(wrapper, 'تعديل الحقل')).toBeDefined()
  })

  it('hides field edit affordance on a PUBLISHED version', async () => {
    const wrapper = await mountEditor(['VIEW', 'MANAGE'], 'PUBLISHED')

    expect(buttonByLabel(wrapper, 'تعديل الحقل')).toBeUndefined()
  })
```

- [ ] **Step 3: Run the test file to verify the new tests fail**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/components/WorkflowFieldDesigner.test.ts
```

Expected: FAIL — group select still disabled; no edit button exists yet.

- [ ] **Step 4: Update WorkflowFieldDesigner.vue — script section**

Add `Pencil` to the lucide import (line 4):

```ts
import { ChevronDown, ChevronUp, FolderTree, ListChecks, Lock, Pencil, Plus, Trash2 } from 'lucide-vue-next'
```

Add an `editingField` ref next to `deletingField` (line 85):

```ts
const editingField = ref<FieldDefinition | null>(null)
const deletingField = ref<FieldDefinition | null>(null)
```

Update `openFieldDialog` (lines 153-165) to accept an optional field to edit, and add `openEditFieldDialog`:

```ts
function openFieldDialog(groupId: number | null) {
  editingField.value = null
  fieldGroupId.value = groupId
  fieldKey.value = ''
  fieldLabel.value = ''
  fieldType.value = 'TEXT'
  fieldMinValue.value = ''
  fieldMaxValue.value = ''
  fieldDynamicSource.value = ''
  fieldReferenceTableId.value = ''
  fieldRequired.value = false
  formError.value = null
  fieldDialogOpen.value = true
}

function openEditFieldDialog(field: FieldDefinition) {
  editingField.value = field
  fieldGroupId.value = field.field_group_id
  fieldKey.value = field.key
  fieldLabel.value = field.label
  fieldType.value = field.type
  fieldMinValue.value = field.min_value !== null && field.min_value !== undefined ? String(field.min_value) : ''
  fieldMaxValue.value = field.max_value !== null && field.max_value !== undefined ? String(field.max_value) : ''
  fieldDynamicSource.value = field.dynamic_source ?? ''
  fieldReferenceTableId.value = field.reference_table_id ? String(field.reference_table_id) : ''
  fieldRequired.value = field.is_required
  formError.value = null
  fieldDialogOpen.value = true
}
```

Update `submitField` (lines 182-207) to branch on `editingField.value`:

```ts
async function submitField() {
  if (fieldGroupId.value === null || !fieldKey.value || !fieldLabel.value) {
    formError.value = 'المجموعة والرمز والاسم مطلوبة.'
    return
  }
  const payload = {
    field_group_id: fieldGroupId.value,
    key: fieldKey.value,
    label: fieldLabel.value,
    type: fieldType.value,
    min_value: isNumeric.value && fieldMinValue.value ? Number(fieldMinValue.value) : null,
    max_value: isNumeric.value && fieldMaxValue.value ? Number(fieldMaxValue.value) : null,
    dynamic_source: isDynamic.value && fieldDynamicSource.value ? fieldDynamicSource.value : null,
    reference_table_id:
      needsReferenceTable.value && fieldReferenceTableId.value
        ? Number(fieldReferenceTableId.value)
        : null,
    is_required: fieldRequired.value,
  }
  try {
    if (editingField.value) {
      await updateField(props.version.id, editingField.value, payload)
      toast.success('تم تحديث الحقل')
    } else {
      await createField(props.version.id, payload)
      toast.success('تمت إضافة الحقل')
    }
    fieldDialogOpen.value = false
  } catch (cause) {
    formError.value = extractApiErrorMessage(cause, 'تعذّر حفظ الحقل')
  }
}
```

- [ ] **Step 5: Update WorkflowFieldDesigner.vue — template section**

Remove `disabled` from the group `<Select>` in the add/edit field dialog (around lines 545-557):

```vue
          <div class="flex flex-col gap-1.5">
            <Label>المجموعة</Label>
            <Select v-model="fieldGroupId">
              <SelectTrigger class="w-full"
                ><SelectValue placeholder="اختر المجموعة"
              /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="group in groups" :key="group.id" :value="group.id">
                  {{ group.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
```

Add the edit button beside delete in the fields table (around lines 449-477), before the delete button, and update the dialog title. Full replacement of the action cell:

```vue
                <TableCell class="text-left" @click.stop>
                  <div class="flex items-center justify-end gap-0.5">
                    <ScreenGuard
                      v-if="editable && !field.is_system"
                      screen="workflow_designer"
                      capability="MANAGE"
                    >
                      <Tooltip>
                        <TooltipTrigger as-child>
                          <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="تعديل الحقل"
                            @click="openEditFieldDialog(field)"
                          >
                            <Pencil class="h-3.5 w-3.5" />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>تعديل الحقل</TooltipContent>
                      </Tooltip>
                    </ScreenGuard>
                    <ScreenGuard
                      v-if="editable && !field.is_system"
                      screen="workflow_designer"
                      capability="MANAGE"
                    >
                      <Tooltip>
                        <TooltipTrigger as-child>
                          <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="حذف الحقل"
                            @click="deletingField = field"
                          >
                            <Trash2 class="h-3.5 w-3.5 text-[var(--severity-red)]" />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>حذف الحقل</TooltipContent>
                      </Tooltip>
                    </ScreenGuard>
                    <span
                      v-else-if="!editable"
                      class="inline-flex items-center gap-1 text-xs text-[var(--locked)]"
                    >
                      <Lock class="h-3 w-3" />مقفلة
                    </span>
                  </div>
                </TableCell>
```

Update the field dialog title (around lines 515-517):

```vue
        <DialogTitle>{{ editingField ? 'تعديل حقل' : 'إضافة حقل' }}</DialogTitle>
        <DialogDescription>عرّف الحقل وإعداداته حسب النوع.</DialogDescription>
```

Make the field key `<Input>` read-only when editing (around line 522), since `key` likely correlates with stored request data:

```vue
          <div class="flex flex-col gap-1.5">
            <Label>الرمز</Label>
            <Input v-model="fieldKey" placeholder="amount" dir="ltr" :disabled="editingField !== null" />
          </div>
```

- [ ] **Step 6: Run the test file to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/components/WorkflowFieldDesigner.test.ts
```

Expected: PASS (all tests, including the 3 new ones — adjust the first new test's DOM query if the actual shadcn `Select` trigger markup differs; the underlying assertion that matters is "the group select is not force-disabled anymore").

- [ ] **Step 7: Lint and commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec eslint app/components/workflow/WorkflowFieldDesigner.vue
pnpm exec prettier app/components/workflow/WorkflowFieldDesigner.vue app/tests/unit/components/WorkflowFieldDesigner.test.ts --check
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/components/workflow/WorkflowFieldDesigner.vue frontend/app/tests/unit/components/WorkflowFieldDesigner.test.ts
git commit -m "$(cat <<'EOF'
feat(workflow): enable group selection and add edit action for fields

The group select in the add-field dialog was hardcoded disabled even
though the dialog can be opened from a card-level button with no
specific group pre-chosen. Field key stays locked on edit since stored
request data likely references it by key.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Frontend — WorkflowActionsCatalog: remove active column

**Files:**
- Modify: `frontend/app/components/workflow/WorkflowActionsCatalog.vue`
- Modify: `frontend/app/tests/unit/components/WorkflowActionsCatalog.test.ts`

**Interfaces:**
- Consumes: existing `useWorkflowActions()` composable — `setActionActive` stops being called from this component; `updateAction`/`deleteAction` unchanged.
- Produces: no new exports. `is_active` becomes purely informational (shown as a badge, not a toggle) unless investigation of the edit dialog shows a natural place to keep it togglable — see Step 4.

- [ ] **Step 1: Read the existing test file to check whether it asserts on the active Switch**

```bash
cat /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend/app/tests/unit/components/WorkflowActionsCatalog.test.ts
```

If it contains an assertion referencing the "نشط" column header text or a Switch toggle for `is_active`, that assertion must be updated/removed in Step 2 (TDD-adjust the existing test to the new contract, same as Task 1's approach).

- [ ] **Step 2: Update the test file for the new contract**

Remove any existing assertion checking for the "نشط" column header or an active-toggle `Switch` in the table (found via Step 1's read). Add a new assertion confirming the column is gone:

```ts
  it('does not render an active-status column', async () => {
    const wrapper = await mountEditor(['VIEW'])

    expect(wrapper.text()).not.toContain('نشط')
  })
```

(Adapt `mountEditor(...)` call args to match this file's actual helper signature from Step 1.)

- [ ] **Step 3: Run the test file to verify the new/updated test fails**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/components/WorkflowActionsCatalog.test.ts
```

Expected: FAIL — "نشط" column header still present.

- [ ] **Step 4: Update WorkflowActionsCatalog.vue — remove the active column**

Remove the `Switch` import if it becomes unused after this change (check whether the edit dialog needs it — it currently does not use `Switch`, per the file read during investigation, so remove it):

```ts
// Remove this line if no other Switch usage remains in the file:
import { Switch } from '@/components/ui/switch'
```

Remove `setActionActive` and `toggleActive` (no longer called from the template):

```ts
const {
  actions,
  loading,
  error,
  fetchActions,
  createAction,
  updateAction,
  deleteAction,
} = useWorkflowActions()
```

(Remove `setActionActive` from the destructure and delete the `toggleActive` function entirely.)

Remove the "نشط" table header (around line 217):

```vue
          <TableHeader>
            <TableRow class="bg-muted/50 hover:bg-muted/50">
              <TableHead class="text-right">الرمز</TableHead>
              <TableHead class="text-right">الاسم</TableHead>
              <TableHead class="text-right">النوع</TableHead>
              <TableHead class="text-left">إجراء</TableHead>
            </TableRow>
          </TableHeader>
```

Remove the active-toggle table cell (around lines 233-242):

```vue
            <TableRow v-for="action in actions" :key="action.id" class="even:bg-muted/30">
              <TableCell class="text-muted-foreground font-mono text-xs">{{
                action.code
              }}</TableCell>
              <TableCell class="font-medium">{{ action.name }}</TableCell>
              <TableCell>
                <div class="flex flex-wrap items-center gap-1">
                  <Badge variant="secondary">{{ kindLabels[action.kind] }}</Badge>
                  <Badge v-if="action.is_system" variant="outline">نظامي</Badge>
                  <Badge v-if="!action.is_active" variant="outline" class="text-muted-foreground">غير نشط</Badge>
                </div>
              </TableCell>
```

(Folded the active/inactive signal into the existing "النوع" badge group as a small "غير نشط" badge shown only when inactive, rather than dropping the signal entirely — an admin still needs to see at a glance that an action is disabled, just not via a dedicated column+toggle.)

- [ ] **Step 5: Run the test file to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/components/WorkflowActionsCatalog.test.ts
```

Expected: PASS (all tests).

- [ ] **Step 6: Lint and commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec eslint app/components/workflow/WorkflowActionsCatalog.vue
pnpm exec prettier app/components/workflow/WorkflowActionsCatalog.vue app/tests/unit/components/WorkflowActionsCatalog.test.ts --check
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/components/workflow/WorkflowActionsCatalog.vue frontend/app/tests/unit/components/WorkflowActionsCatalog.test.ts
git commit -m "$(cat <<'EOF'
refactor(workflow): remove active-status column from actions catalog

The dedicated نشط column and its inline toggle added a mutation
surface the tab didn't need; edit/delete already cover action
management. Inactive actions still surface via a small badge folded
into the existing type-badge group.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: Manual verification pass across all five tabs

**Files:** none (verification only, no code changes expected)

**Interfaces:**
- Consumes: the running frontend dev server + backend API from Tasks 1-7.
- Produces: nothing — confirms the prior 7 tasks work together end-to-end in a real browser session.

- [ ] **Step 1: Start the dev servers if not already running**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
php artisan serve &
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm dev &
```

- [ ] **Step 2: Log in as CBY_ADMIN and navigate to /admin/workflows**

Using `playwright-cli`, log in as the CBY_ADMIN demo user, navigate to `/admin/workflows`, select a workflow definition with a DRAFT version (create one if none exists via the "إنشاء مسار عمل" button).

- [ ] **Step 3: Verify the Stages tab**

- Confirm "الترتيب" column is gone from the stages table.
- Create a new stage, toggle "مرحلة البداية" ON in the dialog, confirm "مرحلة النهاية" auto-disables.
- Save, reload the page, confirm the stage still shows the "بداية" badge (persistence check for the pre-existing `v-model` fix plus the new mutual-exclusion guard).
- Attempt (via direct API call or by editing a second stage) to set `is_final: true` on the same stage that already has `is_initial: true` — confirm a 422 is returned.

- [ ] **Step 4: Verify the سير العملية (Routing) tab**

- Open a stage's permissions section, confirm the table shows Org, Team, and Role as labels (not IDs or dashes).
- Click "إضافة صلاحية", confirm Org/Team/Role are all required — submitting with only two selected should show a validation error and not call the API.
- Fill in all three plus a label, save, confirm the new row appears with all labels populated.
- Click the new edit (pencil) button on that row, change the access level, save, confirm the update persists after reload.

- [ ] **Step 5: Verify the الانتقالات (Transitions) tab**

- Confirm the table shows real stage/action names (not placeholder data).
- Create a transition, then click its new edit button, change "رسالة التأكيد", save, confirm the change persists after reload.
- Confirm "من المرحلة" and "الإجراء" selects are disabled while editing.

- [ ] **Step 6: Verify the الحقول (Fields) tab**

- Click "إضافة حقل" from the card-level button (not a specific group's row), confirm the "المجموعة" select is now interactive (not grayed out).
- Create a field, then click its new edit button, change the label, save, confirm the change persists.
- Confirm the field's "الرمز" input is disabled while editing.

- [ ] **Step 7: Verify the الإجراءات (Actions) tab**

- Confirm the "نشط" column is gone.
- Confirm an inactive action (if any exist, or toggle one inactive via direct API call first) shows a "غير نشط" badge instead.
- Confirm edit and delete still work for a non-system action.

- [ ] **Step 8: Capture a screenshot of each tab as evidence**

```bash
playwright-cli screenshot --filename=admin-workflows-stages.png
playwright-cli screenshot --filename=admin-workflows-routing.png
playwright-cli screenshot --filename=admin-workflows-transitions.png
playwright-cli screenshot --filename=admin-workflows-fields.png
playwright-cli screenshot --filename=admin-workflows-actions.png
```

- [ ] **Step 9: Report findings**

If any step in this task reveals a regression or unmet requirement, stop and fix it before considering the plan complete — do not report success without having driven every tab in a real browser session per this project's verification rules.

- [ ] **Step 10: Close the browser session**

```bash
playwright-cli close
```

No commit for this task — it's verification-only.
