# Workflow Designer Enhancements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add hard-delete for workflow versions/definitions (request-link gated), redesign the designer header into a summary card, surface workflow name+version on requests, convert the create-request page into a picker dialog, and remove the now-unrepresentable "requests" column from `/admin/screen-permissions` while fixing the runtime capability derivation it shared code with.

**Architecture:** Backend: two new `destroy()` controller actions delegating to two new `WorkflowDesignerService` methods (transaction + `lockForUpdate` + in-use guard, mirroring the existing `deleteStage`/`deleteTransition` pattern), a rewritten `derivedRequestsCapabilities()` that iterates all currently-published versions instead of one hardcoded latest, and an `EngineRequestResource` addition with matching eager-loads. Frontend: a new summary-card header replacing the flat picker strip in `admin/workflows.vue`, delete composables + `AlertDialog` wiring, a new TanStack column on the requests table, a `<Dialog>`-based picker on `/workflows/new` (converting its existing working card-grid, not rebuilding it), and removal of the requests column + `RequestsAccess` type from the screen-permissions matrix page.

**Tech Stack:** Laravel 11 (PHP 8.2+), PHPUnit, Nuxt 4/Vue 4/TypeScript, Vitest, shadcn-vue, TanStack Vue Table, Pinia.

## Global Constraints

- All commits signed; never `--no-gpg-sign`/`--no-sign`/`-c commit.gpgsign=false`.
- Commit message format `type(scope): description`; scope from `{auth, backend, docs, frontend, repo, settings, testing, ui, workflow}`.
- Co-author line: `Co-Authored-By: Claude <noreply@anthropic.com>`.
- No raw HTML `<button>`/`<table>`/`<select>` in Vue templates — shadcn-vue components only (`frontend/SHADCN.md`). Exception: `admin/screen-permissions.vue` already uses a raw `<table>`; do not "fix" that as part of this work, only remove the requests-column markup within it.
- Destructive confirmations use `<AlertDialog>`, never `window.confirm()` or `<Dialog>`.
- Never mutate `current_status`/stage fields directly on `EngineRequest` — not touched by this plan, but the new delete methods must not bypass `WorkflowDesignerService`'s existing transaction/locking conventions.
- Arabic UI copy, formal MSA, matching the exact strings specified per task below.
- Backend mutating service methods: `DB::transaction()` + `lockForUpdate()`, consistent with every existing method in `WorkflowDesignerService`.
- Frontend typecheck (`pnpm typecheck`) required for any task touching `types/models.ts`, composables, or store contracts (Tasks 5, 7, 9).
- Focused verification only per task (smallest relevant test/file); no full suite runs unless a task says otherwise.

---

### Task 1: `WorkflowDesignProtectionException` delete-error factory methods

**Files:**
- Modify: `backend/app/Exceptions/WorkflowDesignProtectionException.php`
- Test: `backend/tests/Unit/Exceptions/WorkflowDesignProtectionExceptionTest.php` (new)

**Interfaces:**
- Produces: `WorkflowDesignProtectionException::versionInUse(): self` and `WorkflowDesignProtectionException::definitionInUse(): self`, both extending the existing `public function __construct(public readonly string $errorCode, string $message)` constructor. Error codes: `WORKFLOW_VERSION_IN_USE`, `WORKFLOW_DEFINITION_IN_USE`.

Current file content (read in full):

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class WorkflowDesignProtectionException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
```

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\WorkflowDesignProtectionException;
use PHPUnit\Framework\TestCase;

class WorkflowDesignProtectionExceptionTest extends TestCase
{
    public function test_version_in_use_factory_sets_code_and_message(): void
    {
        $exception = WorkflowDesignProtectionException::versionInUse();

        $this->assertSame('WORKFLOW_VERSION_IN_USE', $exception->errorCode);
        $this->assertSame('لا يمكن حذف نسخة مرتبطة بطلبات.', $exception->getMessage());
    }

    public function test_definition_in_use_factory_sets_code_and_message(): void
    {
        $exception = WorkflowDesignProtectionException::definitionInUse();

        $this->assertSame('WORKFLOW_DEFINITION_IN_USE', $exception->errorCode);
        $this->assertSame('لا يمكن حذف مسار عمل مرتبط بطلبات.', $exception->getMessage());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && php artisan test tests/Unit/Exceptions/WorkflowDesignProtectionExceptionTest.php`
Expected: FAIL with "Call to undefined method App\Exceptions\WorkflowDesignProtectionException::versionInUse()"

- [ ] **Step 3: Write the minimal implementation**

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class WorkflowDesignProtectionException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function versionInUse(): self
    {
        return new self('WORKFLOW_VERSION_IN_USE', 'لا يمكن حذف نسخة مرتبطة بطلبات.');
    }

    public static function definitionInUse(): self
    {
        return new self('WORKFLOW_DEFINITION_IN_USE', 'لا يمكن حذف مسار عمل مرتبط بطلبات.');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd backend && php artisan test tests/Unit/Exceptions/WorkflowDesignProtectionExceptionTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add backend/app/Exceptions/WorkflowDesignProtectionException.php backend/tests/Unit/Exceptions/WorkflowDesignProtectionExceptionTest.php
git commit -m "feat(workflow): add delete-in-use exception factories"
```

---

### Task 2: `WorkflowDesignerService::deleteVersion()` + `deleteDefinition()`

**Files:**
- Modify: `backend/app/Services/Workflow/WorkflowDesignerService.php`
- Test: `backend/tests/Feature/Workflow/WorkflowVersionDeleteTest.php` (new)
- Test: `backend/tests/Feature/Workflow/WorkflowDefinitionDeleteTest.php` (new)

**Interfaces:**
- Consumes: `WorkflowDesignProtectionException::versionInUse()` / `::definitionInUse()` (Task 1), `App\Models\EngineRequest`, `App\Models\WorkflowVersion`, `App\Models\WorkflowDefinition`, `App\Enums\AuditAction::GOVERNANCE_DELETED`, `$this->auditService->log(AuditAction $action, User $actor, Model $subject, array $context): void` (existing signature, used identically to other delete methods in this file).
- Produces: `WorkflowDesignerService::deleteVersion(User $actor, WorkflowVersion $version): void` and `WorkflowDesignerService::deleteDefinition(User $actor, WorkflowDefinition $definition): void`. Both throw `WorkflowDesignProtectionException` when in use; both are state-agnostic (no `ensureEditable()` call — DRAFT/PUBLISHED/ARCHIVED can all be deleted, only the request-link check gates).

This service class does not yet import `EngineRequest`. Check the top of the file first:

- [ ] **Step 1: Confirm current imports and add `EngineRequest`**

Read `backend/app/Services/Workflow/WorkflowDesignerService.php` lines 1-30 to find the exact `use` block, then add:

```php
use App\Models\EngineRequest;
```

alongside the existing `use App\Models\WorkflowDefinition;` and `use App\Models\WorkflowVersion;` lines (if not already imported — grep confirmed it is not).

- [ ] **Step 2: Write the failing tests (version delete)**

```php
<?php

namespace Tests\Feature\Workflow;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowVersionDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    private WorkflowDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->nonAdmin = User::query()->where('role', '!=', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->definition = WorkflowDefinition::query()->create(['code' => 'flow-del', 'name' => 'Flow Delete']);
    }

    public function test_delete_version_with_no_requests(): void
    {
        $version = $this->definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$version->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workflow_versions', ['id' => $version->id]);
    }

    public function test_delete_version_with_requests_is_rejected(): void
    {
        $version = $this->definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
        ]);
        $stage = $version->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        EngineRequest::query()->create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'REF-'.uniqid(),
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$version->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_VERSION_IN_USE');

        $this->assertDatabaseHas('workflow_versions', ['id' => $version->id]);
    }

    public function test_non_admin_cannot_delete_version(): void
    {
        $version = $this->definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ]);

        $this->actingAs($this->nonAdmin)
            ->deleteJson("/api/v1/workflow-versions/{$version->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('workflow_versions', ['id' => $version->id]);
    }

    public function test_delete_published_version_with_no_requests_is_allowed(): void
    {
        $version = $this->definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$version->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workflow_versions', ['id' => $version->id]);
    }
}
```

- [ ] **Step 3: Write the failing tests (definition delete)**

```php
<?php

namespace Tests\Feature\Workflow;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowDefinitionDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->nonAdmin = User::query()->where('role', '!=', UserRole::CBY_ADMIN->value)->firstOrFail();
    }

    public function test_delete_definition_with_no_requests_across_any_version(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'def-del', 'name' => 'Definition Delete']);
        $v1 = $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::ARCHIVED]);
        $v1->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        $definition->versions()->create(['version_number' => 2, 'state' => WorkflowVersionState::DRAFT]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-definitions/{$definition->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workflow_definitions', ['id' => $definition->id]);
        $this->assertDatabaseMissing('workflow_versions', ['workflow_definition_id' => $definition->id]);
        $this->assertDatabaseMissing('workflow_stages', ['code' => 'intake']);
    }

    public function test_delete_definition_with_a_request_on_any_version_is_rejected(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'def-del-2', 'name' => 'Definition Delete 2']);
        $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::ARCHIVED]);
        $v2 = $definition->versions()->create(['version_number' => 2, 'state' => WorkflowVersionState::PUBLISHED]);
        $stage = $v2->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        EngineRequest::query()->create([
            'workflow_version_id' => $v2->id,
            'current_stage_id' => $stage->id,
            'reference' => 'REF-'.uniqid(),
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-definitions/{$definition->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_DEFINITION_IN_USE');

        $this->assertDatabaseHas('workflow_definitions', ['id' => $definition->id]);
    }

    public function test_non_admin_cannot_delete_definition(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'def-del-3', 'name' => 'Definition Delete 3']);

        $this->actingAs($this->nonAdmin)
            ->deleteJson("/api/v1/workflow-definitions/{$definition->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('workflow_definitions', ['id' => $definition->id]);
    }
}
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `cd backend && php artisan test tests/Feature/Workflow/WorkflowVersionDeleteTest.php tests/Feature/Workflow/WorkflowDefinitionDeleteTest.php`
Expected: FAIL — routes `DELETE /api/v1/workflow-versions/{id}` and `DELETE /api/v1/workflow-definitions/{id}` do not exist yet (404s), or controller methods missing.

- [ ] **Step 5: Add the service methods**

Add to `backend/app/Services/Workflow/WorkflowDesignerService.php`, placed near the other `delete*` methods (after `deleteStagePermission`, around line 583):

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
        $locked->delete();
    });
}
```

- [ ] **Step 6: Add routes**

In `backend/routes/api.php`, add near the other `workflow-versions`/`workflow-definitions` routes:

```php
Route::delete('workflow-versions/{workflowVersion}', [WorkflowVersionController::class, 'destroy']);
Route::delete('workflow-definitions/{workflowDefinition}', [WorkflowDefinitionController::class, 'destroy']);
```

- [ ] **Step 7: Add controller actions**

In `backend/app/Http/Controllers/Api/V1/WorkflowVersionController.php`, add (using the existing private `error()` helper already in this controller):

```php
public function destroy(Request $request, WorkflowVersion $workflowVersion): JsonResponse
{
    $this->authorize('delete', $workflowVersion);

    try {
        $this->designer->deleteVersion($request->user(), $workflowVersion);
    } catch (WorkflowDesignProtectionException $e) {
        return $this->error($e->errorCode, $e->getMessage(), 422);
    }

    return response()->json(null, 204);
}
```

In `backend/app/Http/Controllers/Api/V1/WorkflowDefinitionController.php`, add:

```php
public function destroy(Request $request, WorkflowDefinition $workflowDefinition): JsonResponse
{
    $this->authorize('delete', $workflowDefinition);

    try {
        $this->designer->deleteDefinition($request->user(), $workflowDefinition);
    } catch (WorkflowDesignProtectionException $e) {
        return $this->error($e->errorCode, $e->getMessage(), 422);
    }

    return response()->json(null, 204);
}
```

Confirm both controllers import `WorkflowDesignProtectionException` and `Illuminate\Http\JsonResponse` — add the `use` statements if missing (grep the top of each file first; `WorkflowVersionController` likely already imports the exception since it's used elsewhere in that file for `update`/`publish`; `WorkflowDefinitionController` currently has no delete-related logic so check it needs the import added).

- [ ] **Step 8: Add policy methods**

In `backend/app/Policies/WorkflowVersionPolicy.php`, add:

```php
public function delete(User $user, WorkflowVersion $version): bool
{
    return $this->viewAny($user);
}
```

In `backend/app/Policies/WorkflowDefinitionPolicy.php`, add:

```php
public function delete(User $user, WorkflowDefinition $definition): bool
{
    return $this->viewAny($user);
}
```

(Read each policy file first to confirm the exact existing `viewAny()` signature and MANAGE-capability check it delegates to, and match that pattern exactly rather than assuming.)

- [ ] **Step 9: Run tests to verify they pass**

Run: `cd backend && php artisan test tests/Feature/Workflow/WorkflowVersionDeleteTest.php tests/Feature/Workflow/WorkflowDefinitionDeleteTest.php`
Expected: PASS (4 tests in each file)

- [ ] **Step 10: Format touched PHP files**

Run: `cd backend && vendor/bin/pint app/Services/Workflow/WorkflowDesignerService.php app/Http/Controllers/Api/V1/WorkflowVersionController.php app/Http/Controllers/Api/V1/WorkflowDefinitionController.php app/Policies/WorkflowVersionPolicy.php app/Policies/WorkflowDefinitionPolicy.php --test`

- [ ] **Step 11: Commit**

```bash
git add backend/app/Services/Workflow/WorkflowDesignerService.php backend/app/Http/Controllers/Api/V1/WorkflowVersionController.php backend/app/Http/Controllers/Api/V1/WorkflowDefinitionController.php backend/app/Policies/WorkflowVersionPolicy.php backend/app/Policies/WorkflowDefinitionPolicy.php backend/routes/api.php backend/tests/Feature/Workflow/WorkflowVersionDeleteTest.php backend/tests/Feature/Workflow/WorkflowDefinitionDeleteTest.php
git commit -m "feat(workflow): add hard-delete for workflow versions and definitions"
```

---

### Task 3: `WorkflowVersionResource` stage/transition/field counts

**Files:**
- Modify: `backend/app/Models/WorkflowVersion.php`
- Modify: `backend/app/Http/Resources/WorkflowVersionResource.php`
- Modify: `backend/app/Http/Controllers/Api/V1/WorkflowDefinitionController.php`
- Test: `backend/tests/Feature/Workflow/WorkflowDefinitionIndexCountsTest.php` (new)

**Interfaces:**
- Consumes: `WorkflowVersion::stages()`, `::transitions()` (existing `HasMany` relations), a new `WorkflowVersion::fields(): HasManyThrough`.
- Produces: `WorkflowVersionResource` output gains `stages_count`, `transitions_count`, `fields_count` (integers) when the version model has those counts loaded via `withCount`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Workflow;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowDefinitionIndexCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_exposes_stage_transition_and_field_counts_per_version(): void
    {
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'counts', 'name' => 'Counts Flow']);
        $version = $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::DRAFT]);
        $stageA = $version->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        $stageB = $version->stages()->create(['code' => 'review', 'name' => 'Review']);
        $version->transitions()->create([
            'from_stage_id' => $stageA->id,
            'to_stage_id' => $stageB->id,
            'code' => 'submit',
            'label' => 'Submit',
        ]);
        $group = $version->fieldGroups()->create(['code' => 'basics', 'name' => 'Basics', 'order' => 1]);
        $group->fieldDefinitions()->create([
            'code' => 'amount',
            'label' => 'Amount',
            'type' => 'number',
            'order' => 1,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/workflow-definitions')->assertOk();

        $versionPayload = collect($response->json('data'))
            ->firstWhere('id', $definition->id)['versions'][0];

        $this->assertSame(2, $versionPayload['stages_count']);
        $this->assertSame(1, $versionPayload['transitions_count']);
        $this->assertSame(1, $versionPayload['fields_count']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && php artisan test tests/Feature/Workflow/WorkflowDefinitionIndexCountsTest.php`
Expected: FAIL — `stages_count` key missing/null in the response payload.

- [ ] **Step 3: Add the `fields()` relation to `WorkflowVersion`**

Read `backend/app/Models/WorkflowVersion.php` in full first to find its existing `stages()`/`transitions()`/`fieldGroups()`/`fieldDefinitions()` relation code and the exact `use` block, then add (matching the style of the other `HasMany` relation methods already in the file):

```php
public function fields(): HasManyThrough
{
    return $this->hasManyThrough(
        FieldDefinition::class,
        FieldGroup::class,
        'workflow_version_id',
        'field_group_id',
    );
}
```

Add `use Illuminate\Database\Eloquent\Relations\HasManyThrough;` and `use App\Models\FieldDefinition;` (confirm `FieldGroup` is already imported for the existing `fieldGroups()` relation; reuse it).

- [ ] **Step 4: Add `withCount` to the definitions index query**

In `backend/app/Http/Controllers/Api/V1/WorkflowDefinitionController.php`, find the `index()` method's query (read the file in full first — it's 72 lines) and add `withCount(['stages', 'transitions', 'fields'])` to the nested `versions` eager-load, e.g.:

```php
WorkflowDefinition::query()
    ->with(['versions' => fn ($query) => $query->withCount(['stages', 'transitions', 'fields'])])
    ->get();
```

Adjust to match the exact existing query builder chain in `index()` — do not restructure unrelated parts of the method.

- [ ] **Step 5: Expose counts in the resource**

Read `backend/app/Http/Resources/WorkflowVersionResource.php` in full, then add to its `toArray()` array (conditionally, since counts are only present when eager-loaded):

```php
'stages_count' => $this->whenCounted('stages'),
'transitions_count' => $this->whenCounted('transitions'),
'fields_count' => $this->whenCounted('fields'),
```

- [ ] **Step 6: Run test to verify it passes**

Run: `cd backend && php artisan test tests/Feature/Workflow/WorkflowDefinitionIndexCountsTest.php`
Expected: PASS

- [ ] **Step 7: Format touched PHP files**

Run: `cd backend && vendor/bin/pint app/Models/WorkflowVersion.php app/Http/Resources/WorkflowVersionResource.php app/Http/Controllers/Api/V1/WorkflowDefinitionController.php --test`

- [ ] **Step 8: Commit**

```bash
git add backend/app/Models/WorkflowVersion.php backend/app/Http/Resources/WorkflowVersionResource.php backend/app/Http/Controllers/Api/V1/WorkflowDefinitionController.php backend/tests/Feature/Workflow/WorkflowDefinitionIndexCountsTest.php
git commit -m "feat(workflow): expose stage, transition, and field counts per version"
```

---

### Task 4: Redesign designer header into a summary card (`admin/workflows.vue`)

**Files:**
- Modify: `frontend/app/pages/admin/workflows.vue`
- Test: `frontend/app/tests/unit/pages/admin-workflows.test.ts` (extend existing, or create if none exists — check first)

**Interfaces:**
- Consumes: `stages_count`/`transitions_count`/`fields_count` from Task 3's `WorkflowVersionResource` (available on the currently-selected version object already in this page's store/composable state — read the current `admin/workflows.vue` in full first to find the exact variable name, e.g. `selectedVersion.value.stages_count`).
- Produces: no new composable exports from this task alone (delete wiring is Task 6); this task only restructures the template/markup of the header section.

- [ ] **Step 1: Read the current file in full**

Read `frontend/app/pages/admin/workflows.vue` completely to capture: the exact existing `PageHeader` usage, the picker strip's `<Select>` markup for definition/version, the `WorkflowPublishPanel` usage, and the exact reactive refs/computed names for `selectedDefinition`/`selectedVersion`/`definitions` (do not guess these — this step is a precondition for steps 2+, which must reuse the exact names found here).

- [ ] **Step 2: Replace the header block with a summary Card**

Using the exact ref/computed names found in Step 1, replace the flat `PageHeader` + picker strip with (adapt variable names to match Step 1's findings exactly — this snippet uses placeholder names `selectedDefinition`/`selectedVersion`/`definitions` that MUST be swapped for whatever the file actually calls them):

```vue
<Card class="border-0 shadow" aria-labelledby="designer-summary-heading">
  <CardHeader class="pb-2">
    <div class="flex items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div>
          <CardTitle id="designer-summary-heading" class="text-base font-semibold">
            {{ selectedDefinition?.name }}
            <span class="text-muted-foreground font-normal text-sm">({{ selectedDefinition?.code }})</span>
          </CardTitle>
          <CardDescription class="text-xs">
            {{ selectedVersion?.created_at ? formatDate(selectedVersion.created_at) : '—' }}
            ·
            {{ selectedVersion?.published_at ? formatDate(selectedVersion.published_at) : 'غير منشورة' }}
          </CardDescription>
        </div>
        <Select v-model="selectedVersionId">
          <SelectTrigger class="w-40">
            <SelectValue placeholder="اختر النسخة" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="version in selectedDefinition?.versions ?? []" :key="version.id" :value="String(version.id)">
              v{{ version.version_number }}
            </SelectItem>
          </SelectContent>
        </Select>
        <Badge :variant="selectedVersion?.state === 'PUBLISHED' ? 'default' : 'outline'">
          {{ selectedVersion?.state }}
        </Badge>
      </div>
      <div class="flex items-center gap-2">
        <Button v-if="selectedVersion?.state === 'PUBLISHED'" variant="outline" size="sm" @click="cloneVersion">
          استنساخ
        </Button>
        <ScreenGuard screen="workflow_designer" capability="MANAGE">
          <Button variant="outline" size="sm" @click="openDeleteVersionDialog">حذف النسخة</Button>
        </ScreenGuard>
        <ScreenGuard screen="workflow_designer" capability="MANAGE">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="ghost" size="icon" aria-label="إجراءات إضافية">
                <MoreHorizontal class="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem class="text-destructive" @click="openDeleteDefinitionDialog">
                حذف مسار العمل
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </ScreenGuard>
      </div>
    </div>
    <div class="text-muted-foreground mt-2 text-xs">
      {{ selectedVersion?.stages_count ?? 0 }} مراحل ·
      {{ selectedVersion?.transitions_count ?? 0 }} انتقالات ·
      {{ selectedVersion?.fields_count ?? 0 }} حقول
    </div>
  </CardHeader>
</Card>
```

Add imports for `Card, CardHeader, CardTitle, CardDescription` from `@/components/ui/card`, `Badge` from `@/components/ui/badge`, `DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem` from `@/components/ui/dropdown-menu`, and `MoreHorizontal` from `lucide-vue-next` if not already imported — check the existing import block first since `Select`/`Button`/`ScreenGuard` are almost certainly already imported.

`openDeleteVersionDialog`/`openDeleteDefinitionDialog` are stubs in this task (just open local `ref(false)` dialog state — no API call yet); Task 6 wires the actual delete composables and `AlertDialog` bodies. Add:

```ts
const deleteVersionDialogOpen = ref(false)
const deleteDefinitionDialogOpen = ref(false)

function openDeleteVersionDialog() {
  deleteVersionDialogOpen.value = true
}

function openDeleteDefinitionDialog() {
  deleteDefinitionDialogOpen.value = true
}
```

- [ ] **Step 3: Keep the read-only banner and publish panel below, unchanged**

Confirm (do not modify) that the existing read-only banner markup and `<WorkflowPublishPanel>` usage remain directly below the new Card, in the same relative order as before.

- [ ] **Step 4: Run the page's existing test file**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/pages/admin-workflows.test.ts` (or whatever the actual existing test path is — locate it first with `find app/tests -iname '*workflow*'` if the guessed path doesn't exist)
Expected: existing tests referencing the removed `PageHeader`/picker-strip markup may fail — update their selectors to match the new Card structure (e.g. query by `designer-summary-heading` id or the visible version-select trigger text) rather than deleting coverage.

- [ ] **Step 5: Lint and format the touched file**

Run: `cd frontend && pnpm exec eslint app/pages/admin/workflows.vue && pnpm exec prettier app/pages/admin/workflows.vue --check`

- [ ] **Step 6: Commit**

```bash
git add frontend/app/pages/admin/workflows.vue frontend/app/tests/unit/pages/admin-workflows.test.ts
git commit -m "feat(workflow): redesign designer header as a summary card"
```

---

### Task 5: `EngineRequestResource` + eager-loading + frontend type for workflow name/version

**Files:**
- Modify: `backend/app/Http/Resources/EngineRequestResource.php`
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php`
- Modify: `frontend/app/types/models.ts`
- Test: `backend/tests/Feature/EngineRequest/EngineRequestWorkflowVersionResourceTest.php` (new)

**Interfaces:**
- Produces (backend): `EngineRequestResource` output gains `workflow_version: {id, version_number, state, definition: {id, name, code}} | null`.
- Produces (frontend type): `EngineRequest.workflow_version?: {id: number; version_number: number; state: WorkflowVersion['state']; definition?: {id: number; name: string; code: string}}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EngineRequest;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineRequestWorkflowVersionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_includes_workflow_version_and_definition_name(): void
    {
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'wv-flow', 'name' => 'تمويل الواردات']);
        $version = $definition->versions()->create(['version_number' => 3, 'state' => WorkflowVersionState::PUBLISHED]);
        $stage = $version->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        $engineRequest = \App\Models\EngineRequest::query()->create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'REF-'.uniqid(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/engine-requests/{$engineRequest->id}")
            ->assertOk();

        $response->assertJsonPath('data.workflow_version.version_number', 3)
            ->assertJsonPath('data.workflow_version.definition.name', 'تمويل الواردات');
    }

    public function test_index_includes_workflow_version_for_each_row(): void
    {
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'wv-flow-2', 'name' => 'Flow Two']);
        $version = $definition->versions()->create(['version_number' => 1, 'state' => WorkflowVersionState::PUBLISHED]);
        $stage = $version->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        \App\Models\EngineRequest::query()->create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'REF-'.uniqid(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/engine-requests')->assertOk();

        $row = collect($response->json('data'))->first();
        $this->assertSame('Flow Two', $row['workflow_version']['definition']['name']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && php artisan test tests/Feature/EngineRequest/EngineRequestWorkflowVersionResourceTest.php`
Expected: FAIL — `data.workflow_version` missing/null in the response.

- [ ] **Step 3: Add the field to `EngineRequestResource`**

Read `backend/app/Http/Resources/EngineRequestResource.php` in full first to find its exact `toArray()` array and existing `workflow_version_id` line, then add directly after it:

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

- [ ] **Step 4: Add eager-loading in the controller**

In `backend/app/Http/Controllers/Api/V1/EngineRequestController.php`:
- `index()`: add `'workflowVersion.definition'` to the existing `->with(['currentStage', 'bank', 'merchant', 'creator'])` array (making it `->with(['currentStage', 'bank', 'merchant', 'creator', 'workflowVersion.definition'])`).
- `myQueue()`: apply the same addition to its `.with([...])` call.
- `show()`: change `$engineRequest->load(['currentStage', 'creator', 'bank', 'merchant', 'claimedBy'])` to `$engineRequest->load(['currentStage', 'creator', 'bank', 'merchant', 'claimedBy', 'workflowVersion.definition'])`.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd backend && php artisan test tests/Feature/EngineRequest/EngineRequestWorkflowVersionResourceTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Format touched PHP files**

Run: `cd backend && vendor/bin/pint app/Http/Resources/EngineRequestResource.php app/Http/Controllers/Api/V1/EngineRequestController.php --test`

- [ ] **Step 7: Update the frontend type**

In `frontend/app/types/models.ts`, find the `EngineRequest` interface (currently has `workflow_version_id: number` and no `workflow_version` field — confirm by reading the interface first) and add:

```ts
workflow_version?: {
  id: number
  version_number: number
  state: WorkflowVersion['state']
  definition?: {
    id: number
    name: string
    code: string
  }
}
```

Confirm `WorkflowVersion['state']` resolves correctly — check whether `WorkflowVersion` is already an exported interface/type in this same file with a `state` field (it is, per the existing `AvailableWorkflow`/workflow designer types); if the type name differs from `WorkflowVersion`, use the exact name found.

- [ ] **Step 8: Typecheck**

Run: `cd frontend && pnpm typecheck`
Expected: no new type errors introduced by the `EngineRequest` interface change.

- [ ] **Step 9: Commit**

```bash
git add backend/app/Http/Resources/EngineRequestResource.php backend/app/Http/Controllers/Api/V1/EngineRequestController.php backend/tests/Feature/EngineRequest/EngineRequestWorkflowVersionResourceTest.php frontend/app/types/models.ts
git commit -m "feat(workflow): expose workflow name and version on engine requests"
```

---

### Task 6: Frontend delete composables + summary card `AlertDialog` wiring

**Files:**
- Modify: `frontend/app/composables/useWorkflows.ts` (confirm exact filename first — could be `useWorkflow.ts` per `frontend/CLAUDE.md`'s file listing; grep to find it)
- Modify: `frontend/app/pages/admin/workflows.vue`
- Test: `frontend/app/tests/unit/composables/useWorkflows.test.ts` (extend existing or create — check first)

**Interfaces:**
- Consumes: Task 2's `DELETE /api/v1/workflow-versions/{id}` and `DELETE /api/v1/workflow-definitions/{id}` endpoints; Task 4's `deleteVersionDialogOpen`/`deleteDefinitionDialogOpen` refs and `openDeleteVersionDialog`/`openDeleteDefinitionDialog` functions in `admin/workflows.vue`.
- Produces: `deleteVersion(version: {id: number}): Promise<void>` and `deleteDefinition(definition: {id: number}): Promise<void>` added to the workflows composable, both calling the existing API client's `.delete()` method (confirm the exact method name on the client used elsewhere in this composable — likely `api.del` or `$fetch` with `method: 'DELETE'`; match the existing pattern for other mutating calls in this same file rather than introducing a new call style).

- [ ] **Step 1: Locate and read the composable in full**

Run `find frontend/app/composables -iname '*workflow*'` to find the exact file, then read it completely to capture the existing API-call style (e.g. `const { $api } = useNuxtApp()`, or a dedicated `useApi()` wrapper) and the existing `fetchDefinitions()`/similar refetch function name.

- [ ] **Step 2: Write the failing composable test**

Adapt to the exact mocking pattern already used in this composable's existing test file (read it first for the mock setup); the shape of the new test cases:

```ts
describe('deleteVersion', () => {
  it('calls DELETE on the version endpoint', async () => {
    const { deleteVersion } = useWorkflows()
    const deleteSpy = vi.spyOn(/* the mocked api client */, 'delete')

    await deleteVersion({ id: 42 })

    expect(deleteSpy).toHaveBeenCalledWith('/api/v1/workflow-versions/42')
  })
})

describe('deleteDefinition', () => {
  it('calls DELETE on the definition endpoint', async () => {
    const { deleteDefinition } = useWorkflows()
    const deleteSpy = vi.spyOn(/* the mocked api client */, 'delete')

    await deleteDefinition({ id: 7 })

    expect(deleteSpy).toHaveBeenCalledWith('/api/v1/workflow-definitions/7')
  })
})
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd frontend && pnpm exec vitest run <path-to-composable-test>`
Expected: FAIL — `deleteVersion`/`deleteDefinition` not exported from the composable.

- [ ] **Step 4: Implement the composable functions**

Add to the composable file, matching the exact API-call idiom found in Step 1 (placeholder below uses a generic `api.delete` call — replace with the file's real client call):

```ts
function deleteVersion(version: { id: number }): Promise<void> {
  return api.delete(`/api/v1/workflow-versions/${version.id}`)
}

function deleteDefinition(definition: { id: number }): Promise<void> {
  return api.delete(`/api/v1/workflow-definitions/${definition.id}`)
}
```

Add both to the composable's returned object.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd frontend && pnpm exec vitest run <path-to-composable-test>`
Expected: PASS

- [ ] **Step 6: Wire the `AlertDialog`s in `admin/workflows.vue`**

Replace the Task 4 stub dialogs with functioning ones. Add to the template (near the summary Card from Task 4):

```vue
<AlertDialog v-model:open="deleteVersionDialogOpen">
  <AlertDialogContent>
    <AlertDialogHeader>
      <AlertDialogTitle>حذف النسخة</AlertDialogTitle>
      <AlertDialogDescription>
        سيتم حذف النسخة «v{{ selectedVersion?.version_number }}» نهائياً.
      </AlertDialogDescription>
    </AlertDialogHeader>
    <AlertDialogFooter>
      <AlertDialogCancel>إلغاء</AlertDialogCancel>
      <AlertDialogAction :disabled="deletingVersion" @click="confirmDeleteVersion">
        تأكيد الحذف
      </AlertDialogAction>
    </AlertDialogFooter>
  </AlertDialogContent>
</AlertDialog>

<AlertDialog v-model:open="deleteDefinitionDialogOpen">
  <AlertDialogContent>
    <AlertDialogHeader>
      <AlertDialogTitle>حذف مسار العمل</AlertDialogTitle>
      <AlertDialogDescription>
        سيتم حذف مسار العمل «{{ selectedDefinition?.name }}» وكل نسخه نهائياً.
      </AlertDialogDescription>
    </AlertDialogHeader>
    <AlertDialogFooter>
      <AlertDialogCancel>إلغاء</AlertDialogCancel>
      <AlertDialogAction :disabled="deletingDefinition" @click="confirmDeleteDefinition">
        تأكيد الحذف
      </AlertDialogAction>
    </AlertDialogFooter>
  </AlertDialogContent>
</AlertDialog>
```

Add imports for `AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogCancel, AlertDialogAction` from `@/components/ui/alert-dialog`, and `toast` from `vue-sonner`.

Add script logic (using the exact composable import/name found in Step 1, and the exact refetch function name, e.g. `fetchDefinitions`):

```ts
const { deleteVersion, deleteDefinition, fetchDefinitions } = useWorkflows()
const deletingVersion = ref(false)
const deletingDefinition = ref(false)

async function confirmDeleteVersion() {
  if (!selectedVersion.value) return
  deletingVersion.value = true
  try {
    await deleteVersion(selectedVersion.value)
    deleteVersionDialogOpen.value = false
    toast.success('تم حذف النسخة بنجاح')
    await fetchDefinitions()
  } catch (error: any) {
    toast.error(error?.data?.error?.message ?? 'تعذر حذف النسخة')
  } finally {
    deletingVersion.value = false
  }
}

async function confirmDeleteDefinition() {
  if (!selectedDefinition.value) return
  deletingDefinition.value = true
  try {
    await deleteDefinition(selectedDefinition.value)
    deleteDefinitionDialogOpen.value = false
    toast.success('تم حذف مسار العمل بنجاح')
    await fetchDefinitions()
  } catch (error: any) {
    toast.error(error?.data?.error?.message ?? 'تعذر حذف مسار العمل')
  } finally {
    deletingDefinition.value = false
  }
}
```

Confirm the existing `watch(definitions, ...)` auto-selection logic (referenced in the spec) already handles re-selecting a valid definition/version after `fetchDefinitions()` resolves with a shorter list — do not duplicate that logic here.

- [ ] **Step 7: Lint, format, typecheck**

Run: `cd frontend && pnpm exec eslint app/pages/admin/workflows.vue app/composables/<file-from-step-1> && pnpm exec prettier app/pages/admin/workflows.vue --check && pnpm typecheck`

- [ ] **Step 8: Commit**

```bash
git add frontend/app/pages/admin/workflows.vue frontend/app/composables/
git commit -m "feat(workflow): wire delete version and delete definition dialogs"
```

---

### Task 7: New "مسار العمل" column on the requests table + detail breadcrumb

**Files:**
- Modify: `frontend/app/pages/workflows/index.vue`
- Modify: `frontend/app/pages/workflows/instances/[id].vue`
- Test: `frontend/app/tests/unit/pages/workflows-index.test.ts` (extend existing — check first)

**Interfaces:**
- Consumes: Task 5's `EngineRequest.workflow_version` frontend type.
- Produces: no new exports; this is a leaf UI task.

- [ ] **Step 1: Add the column definition**

In `frontend/app/pages/workflows/index.vue`, insert into the `columns: ColumnDef<EngineRequest>[]` array, directly after the `reference` column (confirmed at lines 226-265 during investigation):

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

Confirm `Badge` is already imported in this file (it's used by the existing `bank` column per investigation) — no new import needed.

- [ ] **Step 2: Add the column to CSV export**

In the same file's `exportCols` array (confirmed starting around line 354), add an entry matching the existing object shape:

```ts
{
  key: 'workflow',
  label: 'مسار العمل',
  format: (_v, row) => {
    const v = row.workflow_version
    return v?.definition ? `${v.definition.name} v${v.version_number}` : '—'
  },
},
```

- [ ] **Step 3: Write/extend the column test**

Locate the existing test file for this page (`find frontend/app/tests -iname '*workflows-index*' -o -iname '*workflows.index*'`), read its existing fixture/mount pattern, and add a case:

```ts
it('renders the workflow name and version badge', () => {
  const row = buildRequestFixture({
    workflow_version: {
      id: 1,
      version_number: 2,
      state: 'PUBLISHED',
      definition: { id: 1, name: 'تمويل الواردات', code: 'import-financing' },
    },
  })
  // mount/render using this file's existing helper, then assert the badge text
  expect(wrapper.text()).toContain('تمويل الواردات v2')
})
```

Adapt `buildRequestFixture`/`wrapper` to whatever fixture/mount helpers the existing test file actually uses — do not invent a new fixture builder if one already exists in this file or a shared test-utils module.

- [ ] **Step 4: Run the test**

Run: `cd frontend && pnpm exec vitest run <path-to-workflows-index-test>`
Expected: PASS

- [ ] **Step 5: Add workflow name+version to the request detail breadcrumb**

Read `frontend/app/pages/workflows/instances/[id].vue` in full to find its breadcrumb component usage and the store's current-request accessor (e.g. `store.current`), then add the workflow context as a breadcrumb segment or adjacent label, e.g.:

```vue
<Breadcrumb>
  <BreadcrumbList>
    <BreadcrumbItem>
      <BreadcrumbLink href="/workflows">طلبات التمويل</BreadcrumbLink>
    </BreadcrumbItem>
    <BreadcrumbSeparator />
    <BreadcrumbItem v-if="store.current?.workflow_version?.definition">
      <BreadcrumbPage>
        {{ store.current.workflow_version.definition.name }} v{{ store.current.workflow_version.version_number }}
      </BreadcrumbPage>
    </BreadcrumbItem>
  </BreadcrumbList>
</Breadcrumb>
```

Match this to whatever breadcrumb component/pattern the file already uses (shadcn `Breadcrumb` sub-components, or a simpler existing header pattern) — read the file first rather than assuming `Breadcrumb` exists in this codebase; if no breadcrumb pattern exists yet, add the workflow name+version as a small `text-muted-foreground text-sm` line directly under the page's existing title element instead.

- [ ] **Step 6: Lint and format**

Run: `cd frontend && pnpm exec eslint app/pages/workflows/index.vue app/pages/workflows/instances/[id].vue && pnpm exec prettier app/pages/workflows/index.vue app/pages/workflows/instances/[id].vue --check`

- [ ] **Step 7: Commit**

```bash
git add frontend/app/pages/workflows/index.vue frontend/app/pages/workflows/instances/[id].vue frontend/app/tests/unit/pages/
git commit -m "feat(workflow): show workflow name and version on requests list and detail"
```

---

### Task 8: Convert `/workflows/new` card-grid into a `<Dialog>` picker

**Files:**
- Modify: `frontend/app/pages/workflows/new.vue`
- Test: `frontend/app/tests/unit/pages/workflows-new.test.ts` (extend existing or create — check first)

**Note on scope:** `/workflows/new.vue` already implements the full data flow correctly — `store.loadAvailableWorkflows()`, single-option auto-selection guard, and `startWorkflow(versionId)` → `store.createInstance({ workflow_version_id: versionId, data: {} })` → `navigateTo('/workflows/instances/' + instance.id + '?mode=wizard')`. This task is a restyle only: wrap the existing card-grid selection UI in a `<Dialog>` so it reads as a picker overlay rather than a full-page grid, matching the spec's "dialog opens first" framing. No composable, store, or backend change is needed for this task — all of that already exists and works.

**Interfaces:**
- Consumes: existing `store.availableWorkflows`, `store.loadAvailableWorkflows()`, `store.createInstance()` (all already implemented in `frontend/app/stores/engineRequests.store.ts`).
- Produces: no new exports.

- [ ] **Step 1: Read the current file in full**

Read `frontend/app/pages/workflows/new.vue` completely (confirmed ~90 lines) to capture the exact existing `canCreate`, `autoStarting`, `onMounted`, `startWorkflow` code and the current template's card-grid `v-for` block, so the dialog wrapper preserves every existing behavior (auto-start-if-one-option guard, `canCreate` role gate, cancel-to-queue navigation).

- [ ] **Step 2: Wrap the grid in a `Dialog` open by default**

Restructure the template so the existing `<Card>`-grid content renders inside `<DialogContent>`, with the dialog open as soon as the page mounts (since this page's entire purpose is the picker) and `@update:open="false"` navigating back to `/workflows`:

```vue
<template>
  <Dialog :open="true" @update:open="onDialogOpenChange">
    <DialogContent class="max-w-2xl">
      <DialogHeader>
        <DialogTitle>اختر مسار العمل</DialogTitle>
        <DialogDescription>حدد مسار العمل والنسخة المنشورة لبدء طلب جديد</DialogDescription>
      </DialogHeader>

      <!-- existing v-if(!canCreate)/v-else-if(autoStarting)/v-else card-grid content moves here unchanged -->

      <DialogFooter>
        <DialogClose as-child>
          <Button variant="outline" @click="onCancel">إلغاء</Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
```

Add script logic:

```ts
function onDialogOpenChange(open: boolean) {
  if (!open) onCancel()
}

async function onCancel() {
  await navigateTo('/workflows')
}
```

Add imports for `Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose` from `@/components/ui/dialog` (the existing `Card`/`Button` imports and `startWorkflow`/`canCreate`/`autoStarting` logic stay exactly as found in Step 1 — only the wrapping template structure changes).

- [ ] **Step 3: Verify the existing test file still passes, updating selectors if needed**

Run: `cd frontend && pnpm exec vitest run <path-to-workflows-new-test>` (locate via `find frontend/app/tests -iname '*workflows-new*' -o -iname '*workflows.new*'`; if none exists, this step becomes "confirm no regression via manual read" and Step 4 below creates fresh coverage instead).

- [ ] **Step 4: Add a dialog-specific test case (if creating fresh coverage)**

```ts
it('renders the picker inside a dialog and cancel navigates back to the queue', async () => {
  // mount with a mocked store exposing availableWorkflows with 2+ entries
  // assert DialogContent is present with title "اختر مسار العمل"
  // trigger the cancel button, assert navigateTo('/workflows') was called
})
```

Adapt to this codebase's existing store-mocking pattern for this page (check how `store.availableWorkflows` is currently mocked in any sibling test, e.g. the workflows index test from Task 7, and reuse the same Pinia-testing setup).

- [ ] **Step 5: Run the test**

Run: `cd frontend && pnpm exec vitest run <path-to-workflows-new-test>`
Expected: PASS

- [ ] **Step 6: Lint and format**

Run: `cd frontend && pnpm exec eslint app/pages/workflows/new.vue && pnpm exec prettier app/pages/workflows/new.vue --check`

- [ ] **Step 7: Commit**

```bash
git add frontend/app/pages/workflows/new.vue frontend/app/tests/unit/pages/
git commit -m "feat(workflow): present the create-request workflow picker as a dialog"
```

---

### Task 9: Fix `derivedRequestsCapabilities()` for multi-definition + optional-role rows

**Files:**
- Modify: `backend/app/Services/Authorization/PermissionService.php`
- Test: `backend/tests/Unit/Services/PermissionServiceDerivedRequestsTest.php` (extend existing)
- Test: `backend/tests/Feature/Permission/DerivedRequestsEnforcementTest.php` (run unmodified as regression guard)

**Interfaces:**
- Consumes: `App\Services\Workflow\StagePermissionResolver` — inject it into `PermissionService`'s constructor (or extract its row-matching logic into a reusable method both classes can call; prefer injecting the existing service since it is already registered in the container and has no circular dependency on `PermissionService`).
- Produces: `derivedRequestsCapabilities(array $roleIds): array` keeps its exact existing signature and return shape (`[roleId => ['view' => bool, 'add' => bool, 'edit' => bool]]`); only its internal query/matching logic changes. `screenPermissionsForGovernanceRole(int $roleId)`'s signature and cache key (`screen_permissions.role.{$roleId}`) are unchanged.

- [ ] **Step 1: Read the current method in full**

Read `backend/app/Services/Authorization/PermissionService.php` lines 1-140 (already read in full earlier this session) to reconfirm the exact current `derivedRequestsCapabilities` body — specifically the `DB::table('workflow_versions')->where('state', PUBLISHED)->orderByDesc('version_number')->value('id')` line and the subsequent `stage_permissions` join keyed only on `role_id` — since this task replaces that body precisely.

- [ ] **Step 2: Write the failing test — org-only row grants access**

Add to `backend/tests/Unit/Services/PermissionServiceDerivedRequestsTest.php` (read the existing file first to match its exact `DB::table(...)->insertGetId(...)` raw-insert fixture style, then add a new test method following that same style):

```php
public function test_org_only_stage_permission_row_grants_requests_access_to_roles_in_that_org(): void
{
    $orgId = DB::table('organizations')->insertGetId(['code' => 'ORG1', 'name' => 'Org One', 'created_at' => now(), 'updated_at' => now()]);
    $roleId = DB::table('roles')->insertGetId([
        'organization_id' => $orgId,
        'code' => 'reviewer',
        'name' => 'Reviewer',
        'is_system' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $definitionId = DB::table('workflow_definitions')->insertGetId(['code' => 'org-only', 'name' => 'Org Only Flow', 'created_at' => now(), 'updated_at' => now()]);
    $versionId = DB::table('workflow_versions')->insertGetId([
        'workflow_definition_id' => $definitionId,
        'version_number' => 1,
        'state' => 'PUBLISHED',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $stageId = DB::table('workflow_stages')->insertGetId([
        'workflow_version_id' => $versionId,
        'code' => 'intake',
        'name' => 'Intake',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('stage_permissions')->insert([
        'stage_id' => $stageId,
        'organization_id' => $orgId,
        'team_id' => null,
        'role_id' => null,
        'access_level' => 'VIEW',
        'display_label' => 'Org-wide',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(PermissionService::class);
    $result = $service->derivedRequestsCapabilities([$roleId]);

    $this->assertTrue($result[$roleId]['view']);
}
```

- [ ] **Step 3: Write the failing test — multi-definition, each with its own published version**

```php
public function test_role_matching_only_second_definitions_stage_still_gets_capabilities(): void
{
    $orgId = DB::table('organizations')->insertGetId(['code' => 'ORG2', 'name' => 'Org Two', 'created_at' => now(), 'updated_at' => now()]);
    $roleId = DB::table('roles')->insertGetId([
        'organization_id' => $orgId,
        'code' => 'reviewer-2',
        'name' => 'Reviewer Two',
        'is_system' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $definitionAId = DB::table('workflow_definitions')->insertGetId(['code' => 'def-a', 'name' => 'Definition A', 'created_at' => now(), 'updated_at' => now()]);
    $versionAId = DB::table('workflow_versions')->insertGetId([
        'workflow_definition_id' => $definitionAId,
        'version_number' => 1,
        'state' => 'PUBLISHED',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('workflow_stages')->insertGetId([
        'workflow_version_id' => $versionAId,
        'code' => 'stage-a',
        'name' => 'Stage A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $definitionBId = DB::table('workflow_definitions')->insertGetId(['code' => 'def-b', 'name' => 'Definition B', 'created_at' => now(), 'updated_at' => now()]);
    $versionBId = DB::table('workflow_versions')->insertGetId([
        'workflow_definition_id' => $definitionBId,
        'version_number' => 1,
        'state' => 'PUBLISHED',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $stageBId = DB::table('workflow_stages')->insertGetId([
        'workflow_version_id' => $versionBId,
        'code' => 'stage-b',
        'name' => 'Stage B',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('stage_permissions')->insert([
        'stage_id' => $stageBId,
        'organization_id' => $orgId,
        'team_id' => null,
        'role_id' => $roleId,
        'access_level' => 'EXECUTE',
        'display_label' => 'B reviewers',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(PermissionService::class);
    $result = $service->derivedRequestsCapabilities([$roleId]);

    $this->assertTrue($result[$roleId]['add']);
}
```

(This is the actual bug fix under test: before the fix, the hardcoded "one latest published version across all definitions" query could easily resolve to Definition A's version — which the role has no stage permission on — silently zeroing out access that should come from Definition B.)

- [ ] **Step 4: Write the failing test — no matching row across multiple definitions returns false**

```php
public function test_role_with_no_matching_row_across_multiple_definitions_gets_all_false(): void
{
    $orgId = DB::table('organizations')->insertGetId(['code' => 'ORG3', 'name' => 'Org Three', 'created_at' => now(), 'updated_at' => now()]);
    $roleId = DB::table('roles')->insertGetId([
        'organization_id' => $orgId,
        'code' => 'no-access',
        'name' => 'No Access',
        'is_system' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach (['def-c', 'def-d'] as $code) {
        $definitionId = DB::table('workflow_definitions')->insertGetId(['code' => $code, 'name' => $code, 'created_at' => now(), 'updated_at' => now()]);
        $versionId = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $versionId,
            'code' => $code.'-stage',
            'name' => $code.' Stage',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $service = app(PermissionService::class);
    $result = $service->derivedRequestsCapabilities([$roleId]);

    $this->assertFalse($result[$roleId]['view']);
    $this->assertFalse($result[$roleId]['add']);
    $this->assertFalse($result[$roleId]['edit']);
}
```

- [ ] **Step 5: Run the new tests to verify they fail (or pass by accident for the wrong reason)**

Run: `cd backend && php artisan test tests/Unit/Services/PermissionServiceDerivedRequestsTest.php`
Expected: the multi-definition test (Step 3) fails intermittently/deterministically depending on insertion order under the current single-latest-version query; the org-only test (Step 2) fails because the current join requires `role_id` to match directly and cannot see a null-`role_id` row as a wildcard.

- [ ] **Step 6: Rewrite `derivedRequestsCapabilities`**

Read `StagePermissionResolver::rowMatches()` first (already read in full earlier this session — its exact signature and null-wildcard/AND/OR semantics) to match its behavior exactly rather than re-deriving it. Replace the method body in `PermissionService.php`:

```php
public function derivedRequestsCapabilities(array $roleIds): array
{
    $results = array_fill_keys($roleIds, ['view' => false, 'add' => false, 'edit' => false]);

    $roles = Role::query()->whereIn('id', $roleIds)->get(['id', 'organization_id']);

    $publishedVersionIds = DB::table('workflow_versions')
        ->where('state', WorkflowVersionState::PUBLISHED->value)
        ->pluck('id');

    if ($publishedVersionIds->isEmpty()) {
        return $results;
    }

    $stageIds = DB::table('workflow_stages')
        ->whereIn('workflow_version_id', $publishedVersionIds)
        ->pluck('id');

    if ($stageIds->isEmpty()) {
        return $results;
    }

    $permissionRows = DB::table('stage_permissions')
        ->whereIn('stage_id', $stageIds)
        ->get(['organization_id', 'team_id', 'role_id', 'access_level']);

    foreach ($roles as $role) {
        foreach ($permissionRows as $row) {
            // team_id-scoped rows are not resolvable from a role id alone: a role's
            // members are not confined to one team, so this method treats org+team
            // rows as non-matching here by design (see StagePermissionResolver for
            // the real per-user enforcement, which does resolve team membership).
            if ($row->team_id !== null) {
                continue;
            }

            $organizationMatches = $row->organization_id === null || $row->organization_id === $role->organization_id;
            $roleMatches = $row->role_id === null || $row->role_id === $role->id;

            if (! $organizationMatches || ! $roleMatches) {
                continue;
            }

            $results[$role->id]['view'] = true;

            if (in_array($row->access_level, ['EXECUTE'], true)) {
                $results[$role->id]['add'] = true;
                $results[$role->id]['edit'] = true;
            }
        }
    }

    return $results;
}
```

Confirm the exact `access_level` enum values and how the existing (pre-fix) code mapped them to `view`/`add`/`edit` before finalizing this mapping — read the method's current body from Step 1 again if the mapping above doesn't match what was already there, and preserve the existing VIEW/EXECUTE → capability mapping exactly rather than inventing a new one.

Add `use App\Models\Role;` and `use App\Enums\WorkflowVersionState;` imports if not already present in this file.

Add a docblock directly above the method:

```php
/**
 * Derives the synthetic `requests` screen capability for each role from
 * currently-published workflow stage permissions. Because at most one
 * WorkflowVersion per WorkflowDefinition can be PUBLISHED at a time
 * (WorkflowDesignerService::publishVersion() auto-archives the prior one),
 * this iterates every published version across every definition rather
 * than picking a single "latest" one.
 *
 * Limitation: stage_permissions rows scoped to an organization+team (with
 * or without a role) are NOT resolvable from a role id alone, since a
 * role's members are not confined to one team. Such rows are treated as
 * non-matching here. This only affects what the frontend chrome shows via
 * /auth/me — StagePermissionResolver remains the real enforcement gate on
 * every actual transition/queue endpoint.
 */
```

- [ ] **Step 7: Run the new tests to verify they pass**

Run: `cd backend && php artisan test tests/Unit/Services/PermissionServiceDerivedRequestsTest.php`
Expected: PASS (5 tests: the 2 pre-existing + 3 new)

- [ ] **Step 8: Run the enforcement regression suite unmodified**

Run: `cd backend && php artisan test tests/Feature/Permission/DerivedRequestsEnforcementTest.php`
Expected: PASS (4 tests, unmodified) — confirms `test_publishing_new_workflow_version_changes_effective_requests_capability` still passes since ARCHIVED versions are excluded by the `state = PUBLISHED` filter exactly as before.

- [ ] **Step 9: Format the touched PHP file**

Run: `cd backend && vendor/bin/pint app/Services/Authorization/PermissionService.php --test`

- [ ] **Step 10: Commit**

```bash
git add backend/app/Services/Authorization/PermissionService.php backend/tests/Unit/Services/PermissionServiceDerivedRequestsTest.php
git commit -m "fix(workflow): derive requests capability across all published workflow definitions"
```

---

### Task 10: Remove the requests column from `admin/screen-permissions.vue` + matrix endpoint

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php`
- Modify: `frontend/app/pages/admin/screen-permissions.vue`
- Modify: `frontend/app/composables/useScreenPermissionsAdmin.ts`
- Test: `backend/tests/Feature/Permission/RoleScreenPermissionMatrixTest.php` (extend existing if present, else create)
- Test: `frontend/app/tests/unit/pages/screen-permissions.test.ts` (extend existing — check first)

**Interfaces:**
- Consumes: nothing new.
- Produces: `RoleScreenPermissionController::matrix()` response rows no longer contain a `requests` key. `MatrixRoleRow` (frontend) drops its `requests: RequestsAccess` field; `RequestsAccess` interface is deleted entirely.

- [ ] **Step 1: Write the failing backend test**

Find or create `backend/tests/Feature/Permission/RoleScreenPermissionMatrixTest.php` (grep first: `find backend/tests -iname '*ScreenPermissionMatrix*' -o -iname '*RoleScreenPermission*'`). If a matrix test file already exists, add a case to it rather than creating a duplicate:

```php
public function test_matrix_response_never_includes_a_requests_key(): void
{
    $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
    $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

    $response = $this->actingAs($admin)->getJson('/api/v1/role-screen-permissions/matrix')->assertOk();

    foreach ($response->json('data.roles') as $role) {
        $this->assertArrayNotHasKey('requests', $role);
    }
}
```

(Confirm the exact matrix response envelope shape — `data.roles` vs `data` directly — by reading `RoleScreenPermissionController::matrix()` in full again before finalizing this assertion path.)

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && php artisan test tests/Feature/Permission/RoleScreenPermissionMatrixTest.php`
Expected: FAIL — `requests` key present in each role row.

- [ ] **Step 3: Remove the `requests` key from the matrix response**

In `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php`, in `matrix()` (confirmed at lines 97-156): delete the `$derived = $this->permissionService->derivedRequestsCapabilities(...)` call (line 135) and the `'requests' => $derived[$role->id] ?? [...]` entry from the row-mapping array (line 144). Leave every other field in the row mapping unchanged.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd backend && php artisan test tests/Feature/Permission/RoleScreenPermissionMatrixTest.php`
Expected: PASS

- [ ] **Step 5: Format the touched PHP file**

Run: `cd backend && vendor/bin/pint app/Http/Controllers/Api/V1/RoleScreenPermissionController.php --test`

- [ ] **Step 6: Remove the frontend type**

In `frontend/app/composables/useScreenPermissionsAdmin.ts`, delete the `RequestsAccess` interface entirely and remove the `requests: RequestsAccess` field from `MatrixRoleRow`.

- [ ] **Step 7: Remove the frontend column markup**

In `frontend/app/pages/admin/screen-permissions.vue` (339 lines, already read in full): remove the `REQUESTS_KEY` const (line 21) and `REQUEST_CAPS` const (lines 22-26) if `REQUESTS_KEY` has no other use in the file (confirm via grep within the file first — `manualScreens`'s filter at lines 38-40 uses it to exclude `requests` from the manual-screen list, but since `requests` is no longer surfaced in matrix rows at all, confirm whether that filter is still needed against the actual `matrix.value?.screens` payload shape before deleting it; if `screens` never included `requests` as a `Screen` row in the first place — confirmed true per earlier investigation, since `requests` was purely synthetic and excluded from `Screen::query()` — then this filter was already a no-op and can be safely removed alongside the two consts).
- Remove the grouped `<th colspan=REQUEST_CAPS.length>` header block (lines 232-244, including the "مشتقة من المصمم" badge).
- Remove the per-capability sub-header `<th>` row entries for `REQUEST_CAPS` (lines 258-266).
- Remove the per-role `<td>` block using `role.requests[c.cap]` (lines 297-312).
- Update the `PageHeader` subtitle (line 165) to remove "صلاحيات الطلبات مشتقة من مصمم سير العمل"; replace with a subtitle that doesn't reference request-derived capabilities at all (e.g. just "صلاحيات شاشات النظام حسب الدور").
- Update the explanatory Card text (lines 180-183) to remove the "صلاحيات شاشة الطلبات مشتقة إلزاميًا من إسنادات المراحل..." paragraph; replace with a one-line pointer per the spec: "لعرض صلاحيات الطلبات لمؤسسة أو فريق أو دور معيّن، راجع تبويب «سير العملية التنظيمية» في مصمم مسارات العمل."

- [ ] **Step 8: Update/extend the frontend page test**

Locate the existing test file (`find frontend/app/tests -iname '*screen-permissions*'`), read it in full, and:
- Remove any assertion that expects a "الطلبات" column header or `REQUEST_CAPS` sub-headers to render.
- Add: `expect(wrapper.text()).not.toContain('الطلبات')` (or the more specific column-header text used previously) to lock in the removal as a regression guard.

- [ ] **Step 9: Run the frontend test**

Run: `cd frontend && pnpm exec vitest run <path-to-screen-permissions-test>`
Expected: PASS

- [ ] **Step 10: Lint, format, typecheck**

Run: `cd frontend && pnpm exec eslint app/pages/admin/screen-permissions.vue app/composables/useScreenPermissionsAdmin.ts && pnpm exec prettier app/pages/admin/screen-permissions.vue app/composables/useScreenPermissionsAdmin.ts --check && pnpm typecheck`

- [ ] **Step 11: Commit**

```bash
git add backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php backend/tests/Feature/Permission/ frontend/app/pages/admin/screen-permissions.vue frontend/app/composables/useScreenPermissionsAdmin.ts frontend/app/tests/unit/pages/
git commit -m "refactor(workflow): remove role-only requests column from screen-permissions matrix"
```

---

### Task 11: Manual `playwright-cli` verification pass

**Files:** none (verification only)

**Interfaces:** none

- [ ] **Step 1: Start the dev servers**

Run backend (`cd backend && php artisan serve`) and frontend (`cd frontend && pnpm dev`) if not already running.

- [ ] **Step 2: Verify create-request picker end-to-end**

```bash
playwright-cli open
playwright-cli goto http://localhost:3000/workflows/new
playwright-cli snapshot
```

Confirm the picker renders as a `<Dialog>` titled "اختر مسار العمل", pick a workflow+version, confirm it lands on `/workflows/instances/{id}` with an empty DRAFT rendering without error.

- [ ] **Step 3: Verify delete flows**

```bash
playwright-cli goto http://localhost:3000/admin/workflows
playwright-cli snapshot
```

Create a throwaway DRAFT definition+version via the existing "إنشاء" flow if one isn't already present, delete it via the new summary-card menu, confirm success toast. Then attempt to delete a version/definition known to have linked requests and confirm the in-use error toast appears and the dialog stays open.

- [ ] **Step 4: Verify the new requests-table column**

```bash
playwright-cli goto http://localhost:3000/workflows
playwright-cli snapshot
```

Confirm the "مسار العمل" column renders with `<definition name> v<version>` badges.

- [ ] **Step 5: Verify screen-permissions page no longer shows the requests column**

```bash
playwright-cli goto http://localhost:3000/admin/screen-permissions
playwright-cli snapshot
```

Confirm no "الطلبات" column/header is present.

- [ ] **Step 6: Close the browser session**

```bash
playwright-cli close
```

No commit for this task — it is a verification pass only. Report results to the user.
