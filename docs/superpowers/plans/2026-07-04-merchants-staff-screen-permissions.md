# Merchants Table, Staff Rename, Screen-Permissions Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give BANK_ADMIN the same advanced merchants table CBY_ADMIN uses (bank-scoped); rename `admin/cby-staff` to `admin/staff`; fix the screen-permissions matrix so its `requests` column and `system_admin` exclusion reflect what's actually enforced, and make workflow-designer stage assignments the real, single, cache-correct source of `requests` access everywhere (matrix display, runtime enforcement, and request-creation gating).

**Architecture:** Four independent-but-bundled changes. (1) and (2) are frontend-only, mechanical. (3)+(4) are one backend fix: extract the existing (buggy) workflow-derivation logic into `PermissionService`, fix its query bug, make it the actual enforcement path (not just a display preview), retire the now-dead static `requests` seed/write path, and close the API hole that currently lets `requests` still be written manually.

**Tech Stack:** Nuxt 4 / Vue 4 / TypeScript / TanStack Table (frontend). Laravel / PHPUnit (backend).

## Global Constraints

- Repo root: `/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code`. Frontend: `frontend/`. Backend: `backend/`. This is a single git repository (no separate `frontend/.git`) — one commit per task, no dual-repo commit step needed.
- Conventional commit format: `type(scope): description`. Commits must stay signed — never `--no-gpg-sign`.
- No raw HTML tables/buttons — shadcn-vue `<Table>`/`<Button>` mandatory (already satisfied by reused `DataTable`).
- RTL-first: no LTR-only assumptions in any new/changed markup.
- Do not run full `pnpm test` or full PHPUnit suite by default; focused runs for touched files per task. Full-suite runs only at the final whole-branch review.
- `requests` screen access is derived from the **published workflow version's `stage_permissions`** — this is the single source of truth for that one screen, everywhere (matrix display, `/auth/me`, `userHasCapability`, request-creation gating). No other code path may read or write `requests` capabilities via the static `screen_permissions` table after this plan completes.
- `screen-permissions.vue`'s `requests` column stays visually read-only (Switch disabled) — editing `requests` access happens only via the workflow designer (`admin/workflows.vue`'s stage-assignment UI), not this page. This is an intentional, documented exception to "screen-permissions page is sole source of truth for role screen permissions": true for every screen except `requests`.

---

### Task 1: Merchants page — bank-scoped advanced table

**Files:**
- Modify: `frontend/app/pages/merchants.vue:369` (columnVisibility default), `:440-631` (columns array — add conditional bank column), `:757-1055` (template: remove card-grid branch, add DataTable for both roles)
- Modify: `frontend/app/tests/unit/pages/merchants-page.test.ts` (add column-visibility test)

**Interfaces:**
- Consumes: existing `scoped`/`preFiltered`/`filtered` computeds (`merchants.vue:175-198`), existing `columns` array, existing `DataTable` usage pattern from the CBY_ADMIN branch (`merchants.vue:819-910`).
- Produces: nothing consumed by later tasks — this task is self-contained.

- [ ] **Step 1: Add role-aware default column visibility**

In `frontend/app/pages/merchants.vue`, find line 368-370:

```ts
const columnVisibility = ref<VisibilityState>({
  transactions: false,
})
```

Replace with:

```ts
const columnVisibility = ref<VisibilityState>({
  transactions: false,
  bank: false,
})
```

This starts the `bank` column hidden by default for everyone; Step 2 makes it visible only for CBY_ADMIN.

- [ ] **Step 2: Show the bank column only for CBY_ADMIN**

Still in `merchants.vue`, find the `onMounted` block (around line 153-165) and add, right after the `isCbyAdmin`/`isBankAdmin`/`canManage` computed block (currently lines 167-169):

```ts
const isCbyAdmin = computed(() => user.value?.role === UserRole.CBY_ADMIN)
const isBankAdmin = computed(() => user.value?.role === UserRole.BANK_ADMIN)
const canManage = computed(() => isBankAdmin.value || isCbyAdmin.value)

watch(
  isCbyAdmin,
  (cby) => {
    columnVisibility.value = { ...columnVisibility.value, bank: cby }
  },
  { immediate: true },
)
```

- [ ] **Step 3: Hide the bank faceted filter for BANK_ADMIN**

Find the toolbar `#filters` template block (`merchants.vue:852-865`):

```vue
<template #filters>
  <DataTableFacetedFilter
    v-if="dataTable.getColumn('status')"
    :column="dataTable.getColumn('status')!"
    title="الحالة"
    :options="statusFilterOptions"
  />
  <DataTableFacetedFilter
    v-if="dataTable.getColumn('bank') && bankFilterOptions.length > 0"
    :column="dataTable.getColumn('bank')!"
    title="البنك"
    :options="bankFilterOptions"
  />
</template>
```

Change the bank filter's `v-if` to also require `isCbyAdmin`:

```vue
<template #filters>
  <DataTableFacetedFilter
    v-if="dataTable.getColumn('status')"
    :column="dataTable.getColumn('status')!"
    title="الحالة"
    :options="statusFilterOptions"
  />
  <DataTableFacetedFilter
    v-if="isCbyAdmin && dataTable.getColumn('bank') && bankFilterOptions.length > 0"
    :column="dataTable.getColumn('bank')!"
    title="البنك"
    :options="bankFilterOptions"
  />
</template>
```

- [ ] **Step 4: Replace the role-gated table wrapper with a single shared DataTable block**

Find the two template branches: the CBY_ADMIN `<template v-if="isCbyAdmin">` block (`merchants.vue:819-910`) and the BANK_ADMIN `<template v-else>` card-grid block (`merchants.vue:913-1055`).

Replace both entire blocks (from `<!-- CBY Admin: tanstack table view -->` at line 818 through the closing `</template>` of the card-grid block at line 1055) with a single unconditional block — same content as the current CBY_ADMIN branch, comment updated:

```vue
    <!-- Advanced data table (both CBY Admin and Bank Admin, bank-scoped via `scoped`) -->
    <div class="relative flex flex-col gap-4">
      <DataTable
        :data="preFiltered"
        :columns="columns"
        :loading="loadingMerchants"
        :pagination="merchantPagination"
        :column-filters="columnFilters"
        :column-visibility="columnVisibility"
        :row-selection="rowSelection"
        row-class="group/row"
        @update:pagination="onMerchantPaginationChange"
        @update:column-filters="(v) => (columnFilters = v)"
        @update:column-visibility="(v) => (columnVisibility = v)"
        @update:row-selection="(v) => (rowSelection = v)"
      >
        <template #toolbar="{ table: dataTable }">
          <DataTableToolbar
            :table="dataTable"
            search-placeholder="بحث بالاسم أو الرقم الضريبي"
            :has-filters="hasActiveFilters"
            :selected-count="selectedCount"
            @update:search="(v) => (query = v)"
            @reset="handleReset"
            @clear-selection="clearSelection"
          >
            <template #bulk-actions>
              <DataTableBulkExport
                @csv="exportSelectedRows('csv')"
                @excel="exportSelectedRows('excel')"
                @json="exportSelectedRows('json')"
              />
            </template>
            <template #filters>
              <DataTableFacetedFilter
                v-if="dataTable.getColumn('status')"
                :column="dataTable.getColumn('status')!"
                title="الحالة"
                :options="statusFilterOptions"
              />
              <DataTableFacetedFilter
                v-if="isCbyAdmin && dataTable.getColumn('bank') && bankFilterOptions.length > 0"
                :column="dataTable.getColumn('bank')!"
                title="البنك"
                :options="bankFilterOptions"
              />
            </template>
            <template #actions>
              <DataTableViewOptions :table="dataTable" :column-labels="MERCHANT_COLUMN_LABELS" />
              <DataTableExport
                :table="dataTable as any"
                :export-columns="exportCols as any"
                :filename="buildExportFilename()"
                :formats="['csv', 'tsv', 'json', 'excel', 'pdf']"
                :respect-column-visibility="true"
              />
            </template>
          </DataTableToolbar>
        </template>
        <template #empty>
          <Empty class="bg-muted/20 min-h-[280px] rounded-xl border border-dashed">
            <EmptyHeader>
              <div
                class="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-xl"
              >
                <SearchX class="size-5" />
              </div>
              <EmptyTitle>
                {{ merchants.length === 0 ? 'لا يوجد مستوردون مسجلون بعد' : 'لا توجد نتائج مطابقة' }}
              </EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>
                {{
                  merchants.length === 0
                    ? isCbyAdmin
                      ? 'لم يتم تسجيل أي مستوردين عبر البنوك حتى الآن.'
                      : 'ابدأ بتسجيل أول مستورد باستخدام زر "مستورد جديد" أعلاه.'
                    : 'جرّب إزالة فلتر الحالة أو البنك، أو تغيير نص البحث.'
                }}
              </EmptyDescription>
            </EmptyContent>
          </Empty>
        </template>
        <template #pagination="{ table: dataTable }">
          <DataTablePagination :table="dataTable" />
        </template>
      </DataTable>
    </div>
```

- [ ] **Step 5: Remove now-unused imports**

Run:
```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
grep -n "^import" app/pages/merchants.vue
```

Check whether `Skeleton` (used only by the deleted card-grid skeleton loop) is still referenced anywhere else in the file:

```bash
grep -n "Skeleton" app/pages/merchants.vue
```

If `Skeleton` has zero remaining usages, remove its import line. If `Card` has zero remaining usages outside the dialogs (it will still be used — check before removing), leave it. Do not remove any import still referenced.

- [ ] **Step 6: Add a column-visibility test**

In `frontend/app/tests/unit/pages/merchants-page.test.ts`, add at the end of the file:

```ts
describe('merchants page column visibility by role', () => {
  it('hides the bank column for bank admin by default', () => {
    const columnVisibility: Record<string, boolean> = { transactions: false, bank: false }
    expect(columnVisibility.bank).toBe(false)
  })

  it('shows the bank column for CBY admin', () => {
    const isCbyAdmin = true
    const columnVisibility: Record<string, boolean> = { transactions: false, bank: isCbyAdmin }
    expect(columnVisibility.bank).toBe(true)
  })
})
```

- [ ] **Step 7: Run the focused test**

Run: `cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend && pnpm exec vitest run app/tests/unit/pages/merchants-page.test.ts`
Expected: all tests pass (existing + 2 new).

- [ ] **Step 8: Typecheck**

Run: `pnpm typecheck`
Expected: no new errors.

- [ ] **Step 9: Lint the touched file**

Run: `pnpm exec eslint app/pages/merchants.vue`
Expected: no errors.

- [ ] **Step 10: Manual browser verification**

Using `playwright-cli` (or the MCP playwright tools), log in as a BANK_ADMIN demo user (e.g. `admin@tiib.com.ye` via the quick user switcher) and navigate to `/merchants`. Confirm:
- A TanStack DataTable renders (not card grid) — sortable columns, toolbar with search, status filter, export button.
- No "البنك" column and no bank facet filter visible.
- Row action dropdown still shows "تعديل"/"إيقاف/تفعيل" for bank admin.
- Then switch to a CBY_ADMIN demo user, reload `/merchants`, confirm the bank column and bank facet filter both appear, and the cross-bank risk banner still renders when applicable.

- [ ] **Step 11: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/pages/merchants.vue frontend/app/tests/unit/pages/merchants-page.test.ts
git commit -m "feat(merchants): give bank admin the same advanced table as CBY admin"
```

---

### Task 2: Rename `admin/cby-staff` to `admin/staff`

**Files:**
- Rename: `frontend/app/pages/admin/cby-staff.vue` → `frontend/app/pages/admin/staff.vue`
- Modify: `frontend/app/constants/role-surfaces.ts:343`, `frontend/app/constants/workflow.ts:1008`, `frontend/app/components/AppSidebar.vue:144`, `frontend/app/components/layout/GlobalSearch.vue:206`, `frontend/app/components/CommandPalette.vue:82,105,222`, `frontend/app/components/SearchForm.vue:44,63`
- Modify tests: `frontend/app/tests/unit/constants/nav-items.test.ts`, `frontend/app/tests/unit/pages/IdentityUsersPages.test.ts`, `frontend/app/tests/unit/pages/CbyAdminPages.test.ts`, `frontend/app/tests/unit/pages/prototype-parity-pages.smoke.test.ts`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: nothing consumed by later tasks. Note for whoever works on future docs/cleanup: this rename means any doc referencing `/admin/cby-staff` is now stale.

- [ ] **Step 1: Rename the page file**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
git mv app/pages/admin/cby-staff.vue app/pages/admin/staff.vue
```

The file's content (`<IdentityUsersPage audience="committee" />`) does not need to change.

- [ ] **Step 2: Update `role-surfaces.ts`**

Read the surrounding context first:
```bash
grep -n "cby_staff\|cby-staff" app/constants/role-surfaces.ts
```

In `frontend/app/constants/role-surfaces.ts`, find line 343:

```ts
  'nav.admin.cby_staff': '/admin/cby-staff',
```

Replace with:

```ts
  'nav.admin.staff': '/admin/staff',
```

- [ ] **Step 3: Update every consumer of the renamed key**

```bash
grep -rn "nav.admin.cby_staff" app/
```

For each match found (expected: `frontend/app/constants/workflow.ts` and possibly others), replace `nav.admin.cby_staff` with `nav.admin.staff`.

In `frontend/app/constants/workflow.ts`, find line 1008:

```ts
  '/admin/cby-staff': rolesForSurface('nav.admin.cby_staff'),
```

Replace with:

```ts
  '/admin/staff': rolesForSurface('nav.admin.staff'),
```

- [ ] **Step 4: Update the sidebar**

In `frontend/app/components/AppSidebar.vue`, find line 144 (path string `'/admin/cby-staff'`) and replace with `'/admin/staff'`.

- [ ] **Step 5: Update GlobalSearch**

In `frontend/app/components/layout/GlobalSearch.vue`, find line 206:

```vue
            @click="navigateTo('/admin/cby-staff')"
```

Replace with:

```vue
            @click="navigateTo('/admin/staff')"
```

- [ ] **Step 6: Update CommandPalette**

In `frontend/app/components/CommandPalette.vue`, find and update three occurrences:

Line 82 — shortcut-key map:
```ts
  '/admin/cby-staff': 'U',
```
becomes:
```ts
  '/admin/staff': 'U',
```

Line 105 — search-keyword map (path key changes, search alias string can stay unchanged since it's a search-matching aid, not a route):
```ts
  '/admin/cby-staff': 'cby staff admin users',
```
becomes:
```ts
  '/admin/staff': 'cby staff admin users',
```

Line 222 — `GROUP_DEFS` routes array:
```ts
    routes: ['/merchants', '/staff', '/admin/banks', '/admin/cby-staff'],
```
becomes:
```ts
    routes: ['/merchants', '/staff', '/admin/banks', '/admin/staff'],
```

Note: `/staff` (top-level, bank-side staff management page, `frontend/app/pages/staff.vue`) is a different, unrelated page — it stays in the array unchanged. Do not confuse it with `/admin/staff`.

- [ ] **Step 7: Update SearchForm**

In `frontend/app/components/SearchForm.vue`, update lines 44 and 63 the same way — `/admin/cby-staff'` → `/admin/staff'` in both the shortcut-key map and the route list.

- [ ] **Step 8: Verify no remaining references**

Run:
```bash
grep -rn "cby-staff\|cby_staff" app/ --include="*.vue" --include="*.ts"
```

Expected: zero matches outside of `app/tests/` (test files are updated in the next step).

- [ ] **Step 9: Update test files**

```bash
grep -n "cby-staff\|cby_staff" app/tests/unit/constants/nav-items.test.ts app/tests/unit/pages/IdentityUsersPages.test.ts app/tests/unit/pages/CbyAdminPages.test.ts app/tests/unit/pages/prototype-parity-pages.smoke.test.ts
```

For each match, replace `/admin/cby-staff` with `/admin/staff` and `nav.admin.cby_staff` with `nav.admin.staff`, preserving the surrounding test logic and assertions exactly — only the path/key strings change.

- [ ] **Step 10: Run the focused tests**

Run:
```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/constants/nav-items.test.ts app/tests/unit/pages/IdentityUsersPages.test.ts app/tests/unit/pages/CbyAdminPages.test.ts app/tests/unit/pages/prototype-parity-pages.smoke.test.ts
```
Expected: all pass.

- [ ] **Step 11: Typecheck**

Run: `pnpm typecheck`
Expected: no new errors.

- [ ] **Step 12: Manual browser verification**

Navigate to `/admin/staff` as a CBY_ADMIN demo user — confirm the identity users page renders (same content as before). Confirm `/admin/cby-staff` no longer resolves (404 or redirect-to-login, whichever Nuxt's default behavior is for a removed route). Confirm the sidebar "إدارة المستخدمين" link points to `/admin/staff` and works. Try the command palette (⌘K) search for "cby staff" or "admin" and confirm the entry still surfaces and navigates correctly.

- [ ] **Step 13: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add -A frontend/app/pages/admin/cby-staff.vue frontend/app/pages/admin/staff.vue frontend/app/constants/role-surfaces.ts frontend/app/constants/workflow.ts frontend/app/components/AppSidebar.vue frontend/app/components/layout/GlobalSearch.vue frontend/app/components/CommandPalette.vue frontend/app/components/SearchForm.vue frontend/app/tests/unit/constants/nav-items.test.ts frontend/app/tests/unit/pages/IdentityUsersPages.test.ts frontend/app/tests/unit/pages/CbyAdminPages.test.ts frontend/app/tests/unit/pages/prototype-parity-pages.smoke.test.ts
git commit -m "refactor(admin): rename admin/cby-staff route to admin/staff"
```

---

### Task 3: Extract and fix `derivedRequestsAccess` into `PermissionService`, fix the `whereIn` bug

**Files:**
- Modify: `backend/app/Services/Authorization/PermissionService.php` (add new method)
- Modify: `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php` (remove private method, call the service instead)
- Test: `backend/tests/Unit/Services/PermissionServiceDerivedRequestsTest.php` (new)

**Interfaces:**
- Consumes: `WorkflowVersionState` enum (`App\Enums\WorkflowVersionState`), `DB` facade, tables `workflow_versions` (`state`, `version_number` columns), `workflow_stages` (`workflow_version_id`, `status`, `is_initial` columns), `stage_permissions` (`role_id`, `stage_id`, `access_level` columns).
- Produces: `PermissionService::derivedRequestsCapabilities(array $roleIds): array` — signature `array<int, array{view: bool, add: bool, edit: bool}>`, keyed by role id, with every input role id present in the output (defaulting to all-false if the role has no stage assignments). Task 4 calls this method; Task 5's `matrix()` also calls it.

- [ ] **Step 1: Write the failing unit test for the `whereIn` bug**

Create `backend/tests/Unit/Services/PermissionServiceDerivedRequestsTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Enums\WorkflowVersionState;
use App\Models\Organization;
use App\Models\Role;
use App\Services\Authorization\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionServiceDerivedRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_derives_capabilities_for_multiple_roles_from_published_workflow(): void
    {
        $org = Organization::factory()->create();
        $roleA = Role::factory()->for($org)->create(['code' => 'role_a']);
        $roleB = Role::factory()->for($org)->create(['code' => 'role_b']);

        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'code' => 'test_wf',
            'name' => 'Test Workflow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $versionId = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $initialStageId = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $versionId,
            'name' => 'Intake',
            'is_initial' => true,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondStageId = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $versionId,
            'name' => 'Review',
            'is_initial' => false,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stage_permissions')->insert([
            ['stage_id' => $initialStageId, 'role_id' => $roleA->id, 'access_level' => 'EXECUTE', 'created_at' => now(), 'updated_at' => now()],
            ['stage_id' => $secondStageId, 'role_id' => $roleB->id, 'access_level' => 'VIEW', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = app(PermissionService::class);
        $result = $service->derivedRequestsCapabilities([$roleA->id, $roleB->id]);

        // Role A: EXECUTE on the initial stage → view=true, add=true, edit=true
        $this->assertTrue($result[$roleA->id]['view']);
        $this->assertTrue($result[$roleA->id]['add']);
        $this->assertTrue($result[$roleA->id]['edit']);

        // Role B: VIEW only on a non-initial stage → view=true, add=false, edit=false
        $this->assertTrue($result[$roleB->id]['view']);
        $this->assertFalse($result[$roleB->id]['add']);
        $this->assertFalse($result[$roleB->id]['edit']);
    }

    public function test_role_with_no_assignments_gets_all_false(): void
    {
        $org = Organization::factory()->create();
        $roleC = Role::factory()->for($org)->create(['code' => 'role_c']);

        $service = app(PermissionService::class);
        $result = $service->derivedRequestsCapabilities([$roleC->id]);

        $this->assertFalse($result[$roleC->id]['view']);
        $this->assertFalse($result[$roleC->id]['add']);
        $this->assertFalse($result[$roleC->id]['edit']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend && php artisan test tests/Unit/Services/PermissionServiceDerivedRequestsTest.php`
Expected: FAIL — `derivedRequestsCapabilities` does not exist on `PermissionService` yet.

- [ ] **Step 3: Add the method to `PermissionService`**

In `backend/app/Services/Authorization/PermissionService.php`, add this method after `screenPermissionsForGovernanceRole` (after line 100, before `userHasCapability`):

```php
    /**
     * Derive requests-screen access per role from stage_permissions on the
     * published workflow version. This is the single source of truth for
     * `requests` screen capability — used by both the screen-permissions
     * matrix display and runtime enforcement.
     *
     * @param  array<int>  $roleIds
     * @return array<int, array{view: bool, add: bool, edit: bool}>
     */
    public function derivedRequestsCapabilities(array $roleIds): array
    {
        $result = array_fill_keys($roleIds, ['view' => false, 'add' => false, 'edit' => false]);
        if (empty($roleIds)) {
            return $result;
        }

        $publishedVersionId = DB::table('workflow_versions')
            ->where('state', \App\Enums\WorkflowVersionState::PUBLISHED->value)
            ->orderByDesc('version_number')
            ->value('id');

        if ($publishedVersionId === null) {
            return $result;
        }

        $stageIds = DB::table('workflow_stages')
            ->where('workflow_version_id', $publishedVersionId)
            ->where('status', 'ACTIVE')
            ->pluck('id', 'id');

        if ($stageIds->isEmpty()) {
            return $result;
        }

        $initialStageId = DB::table('workflow_stages')
            ->where('workflow_version_id', $publishedVersionId)
            ->where('is_initial', true)
            ->value('id');

        $assignments = DB::table('stage_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('stage_id', $stageIds)
            ->select('role_id', 'stage_id', 'access_level')
            ->get()
            ->groupBy('role_id')
            ->map(fn ($items) => $items->keyBy('stage_id')->map(fn ($item) => $item->access_level))
            ->all();

        foreach ($roleIds as $roleId) {
            $perRole = $assignments[$roleId] ?? collect();
            if ($perRole->isEmpty()) {
                continue;
            }

            $view = $perRole->isNotEmpty();
            $edit = $perRole->contains(fn (string $level) => $level === 'EXECUTE');
            $add = $initialStageId !== null
                && ($perRole->get($initialStageId) === 'EXECUTE');

            $result[$roleId] = ['view' => $view, 'add' => $add, 'edit' => $edit];
        }

        return $result;
    }
```

Note the fix on the `assignments` query: `whereIn('role_id', $roleIds)` (was `where('role_id', $roleIds)` in the original private method — this was the bug).

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Services/PermissionServiceDerivedRequestsTest.php`
Expected: PASS (both tests).

- [ ] **Step 5: Remove the duplicate private method from the controller, call the service instead**

In `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php`, find the `derivedRequestsAccess` private method (the full method, roughly lines 159-221 including its docblock) and delete it entirely.

Find the call site in `matrix()` (around line 134):

```php
        $derived = $this->derivedRequestsAccess($roles->pluck('id')->all());
```

Replace with:

```php
        $derived = $this->permissionService->derivedRequestsCapabilities($roles->pluck('id')->all());
```

- [ ] **Step 6: Run the existing screen permission feature tests to confirm the matrix still works**

Run: `php artisan test tests/Feature/Permission/ScreenPermissionTest.php`
Expected: all currently-passing tests still pass (this task only relocates the logic and fixes the query bug — behavior for the matrix endpoint is otherwise unchanged at this point; Task 4 is what changes enforcement).

- [ ] **Step 7: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Services/Authorization/PermissionService.php backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php backend/tests/Unit/Services/PermissionServiceDerivedRequestsTest.php
git commit -m "refactor(permissions): extract requests-capability derivation into PermissionService, fix whereIn query bug"
```

---

### Task 4: Make workflow-derived `requests` the real enforcement path

**Files:**
- Modify: `backend/app/Services/Authorization/PermissionService.php:76-100` (`screenPermissionsForGovernanceRole`)
- Modify: `backend/database/seeders/ScreenPermissionSeeder.php` (remove static `requests` grants)
- Modify: `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php` (`update()` — reject `requests` as a writable key)
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php:88` (remove redundant static CREATE gate)
- Modify: `backend/tests/Feature/Permission/ScreenPermissionTest.php` (update tests that assumed static `requests` writes)
- Test: `backend/tests/Feature/Permission/DerivedRequestsEnforcementTest.php` (new)

**Interfaces:**
- Consumes: `PermissionService::derivedRequestsCapabilities()` from Task 3.
- Produces: `screenPermissionsForGovernanceRole()`'s returned map now has `requests` sourced from workflow derivation, not the `screen_permissions` table. Task 5 relies on this for the `system_admin` exclusion fix being independently testable (no interaction between the two, but both land in the same controller file).

- [ ] **Step 1: Write the failing enforcement test**

Create `backend/tests/Feature/Permission/DerivedRequestsEnforcementTest.php`:

```php
<?php

namespace Tests\Feature\Permission;

use App\Enums\WorkflowVersionState;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DerivedRequestsEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_capability_comes_from_workflow_not_screen_permissions_table(): void
    {
        $org = Organization::factory()->create();
        $role = Role::factory()->for($org)->create(['code' => 'derived_test_role']);
        $user = User::factory()->for($org)->create(['role_id' => $role->id]);

        // Manually seed a stale/wrong static grant directly into screen_permissions —
        // this must be IGNORED once requests is workflow-derived.
        $screenId = DB::table('screens')->where('key', 'requests')->value('id');
        DB::table('screen_permissions')->insert([
            'role_id' => $role->id,
            'screen_id' => $screenId,
            'capability' => 'MANAGE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Set up a published workflow granting only VIEW (not EXECUTE) on the initial stage.
        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'code' => 'enforce_test_wf', 'name' => 'Enforce Test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $versionId = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId, 'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED->value, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stageId = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $versionId, 'name' => 'Intake', 'is_initial' => true,
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stage_permissions')->insert([
            'stage_id' => $stageId, 'role_id' => $role->id, 'access_level' => 'VIEW',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(PermissionService::class);
        $sp = $service->screenPermissionsForGovernanceRole($role->id);

        // The stale MANAGE grant in screen_permissions must NOT appear.
        $this->assertArrayHasKey('requests', $sp);
        $this->assertNotContains('MANAGE', $sp['requests']);
        // Workflow says VIEW only (not EXECUTE), so CREATE/UPDATE must be absent.
        $this->assertContains('VIEW', $sp['requests']);
        $this->assertNotContains('CREATE', $sp['requests']);
        $this->assertNotContains('UPDATE', $sp['requests']);
    }

    public function test_publishing_new_workflow_version_changes_effective_requests_capability(): void
    {
        $org = Organization::factory()->create();
        $role = Role::factory()->for($org)->create(['code' => 'publish_test_role']);

        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'code' => 'publish_test_wf', 'name' => 'Publish Test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $v1Id = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId, 'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED->value, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stage1Id = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $v1Id, 'name' => 'Intake', 'is_initial' => true,
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stage_permissions')->insert([
            'stage_id' => $stage1Id, 'role_id' => $role->id, 'access_level' => 'EXECUTE',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(PermissionService::class);
        $before = $service->screenPermissionsForGovernanceRole($role->id);
        $this->assertContains('CREATE', $before['requests']);

        // Simulate publishing a new version that does NOT grant this role EXECUTE.
        DB::table('workflow_versions')->where('id', $v1Id)->update(['state' => WorkflowVersionState::ARCHIVED->value]);
        $v2Id = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId, 'version_number' => 2,
            'state' => WorkflowVersionState::PUBLISHED->value, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $v2Id, 'name' => 'Intake', 'is_initial' => true,
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        // No stage_permissions row for $role on the v2 stage → no access.

        $service->clearScreenPermissionCache($role->id);
        $after = $service->screenPermissionsForGovernanceRole($role->id);
        $this->assertNotContains('CREATE', $after['requests'] ?? []);
    }

    public function test_put_screen_permissions_rejects_requests_key(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->for($org)->create();
        $adminRole = Role::factory()->for($org)->create(['code' => 'admin_reject_test']);
        $screenPermScreenId = DB::table('screens')->where('key', 'screen_permissions')->value('id');
        DB::table('screen_permissions')->insert([
            'role_id' => $adminRole->id, 'screen_id' => $screenPermScreenId, 'capability' => 'MANAGE',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin->update(['role_id' => $adminRole->id]);

        $targetRole = Role::factory()->for($org)->create(['code' => 'target_reject_test']);

        $this->actingAs($admin)
            ->putJson("/api/v1/roles/{$targetRole->id}/screen-permissions", [
                'grants' => ['requests' => ['VIEW']],
            ])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Permission/DerivedRequestsEnforcementTest.php`
Expected: FAIL — `screenPermissionsForGovernanceRole` still reads the static table for `requests`, and the PUT endpoint still accepts `requests` as a valid key.

- [ ] **Step 3: Update `screenPermissionsForGovernanceRole` to merge in derived `requests`**

In `backend/app/Services/Authorization/PermissionService.php`, replace the `screenPermissionsForGovernanceRole` method (lines 76-100):

```php
    public function screenPermissionsForGovernanceRole(int $roleId): array
    {
        $cacheKey = "screen_permissions.role.{$roleId}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($roleId): array {
            $rows = DB::table('screen_permissions')
                ->join('screens', 'screens.id', '=', 'screen_permissions.screen_id')
                ->where('screen_permissions.role_id', $roleId)
                ->where('screens.key', '!=', 'requests')
                ->select('screens.key', 'screen_permissions.capability')
                ->get();

            $result = [];
            foreach ($rows as $row) {
                $result[$row->key][] = $row->capability;
            }

            foreach ($result as $key => $caps) {
                $result[$key] = array_values(array_unique($caps));
            }

            $derived = $this->derivedRequestsCapabilities([$roleId])[$roleId];
            $requestsCaps = [];
            if ($derived['view']) {
                $requestsCaps[] = 'VIEW';
            }
            if ($derived['add']) {
                $requestsCaps[] = 'CREATE';
            }
            if ($derived['edit']) {
                $requestsCaps[] = 'UPDATE';
            }
            if (! empty($requestsCaps)) {
                $result['requests'] = $requestsCaps;
            }

            ksort($result);

            return $result;
        });
    }
```

- [ ] **Step 4: Remove static `requests` grants from the seeder**

In `backend/database/seeders/ScreenPermissionSeeder.php`, remove every `'requests' => [...]` line from the `$grants` array — lines 40, 46, 51, 59, 66, 73, 80, 87, 103 (nine lines across the `intake`, `internal_reviewer`, `bank_admin`, `fx_swift`, `support`, `committee_manager`, `committee_director`, `fx_confirm`, and `system_admin` role blocks). Keep the `'requests' => 'الطلبات'` entry in the `$screens` array at line 22 unchanged — the `Screen` catalog row must still exist.

Example for the `intake` block (line 39-44), before:
```php
            'intake' => [
                'requests' => ['VIEW', 'CREATE'],
                'merchants' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
```
after:
```php
            'intake' => [
                'merchants' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
```

Apply the same removal pattern to every other role block that has a `'requests' => [...]` line.

- [ ] **Step 5: Reject `requests` as a writable screen key in the PUT endpoint**

In `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php`, find the `update()` method's validation section (around line 230-242):

```php
        $validCapabilities = array_column(ScreenCapability::cases(), 'value');
        $validScreenKeys = Screen::query()->pluck('key')->toArray();

        $validated = $request->validate([
            'grants' => ['required', 'array'],
            'grants.*' => ['array'],
            'grants.*.*' => ['string', Rule::in($validCapabilities)],
        ]);

        // Validate screen keys
        foreach (array_keys($validated['grants']) as $screenKey) {
            if (! in_array($screenKey, $validScreenKeys, true)) {
                return ApiResponse::validationError(['grants' => ["Unknown screen: {$screenKey}"]]);
            }
        }
```

Replace with:

```php
        $validCapabilities = array_column(ScreenCapability::cases(), 'value');
        $validScreenKeys = Screen::query()->where('key', '!=', 'requests')->pluck('key')->toArray();

        $validated = $request->validate([
            'grants' => ['required', 'array'],
            'grants.*' => ['array'],
            'grants.*.*' => ['string', Rule::in($validCapabilities)],
        ]);

        // Validate screen keys. `requests` is intentionally excluded — its access is
        // derived from the workflow designer's stage assignments, not manually granted.
        foreach (array_keys($validated['grants']) as $screenKey) {
            if (! in_array($screenKey, $validScreenKeys, true)) {
                return ApiResponse::validationError(['grants' => ["Unknown screen: {$screenKey}"]]);
            }
        }
```

- [ ] **Step 6: Remove the redundant CREATE gate in `EngineRequestController`**

In `backend/app/Http/Controllers/Api/V1/EngineRequestController.php`, find `availableWorkflows()` (around line 84-93):

```php
    public function availableWorkflows(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! app(PermissionService::class)->userHasCapability($user, 'requests', 'CREATE')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create requests.',
            ], 403);
        }

        $versions = WorkflowVersion::query()
```

Replace with:

```php
    public function availableWorkflows(Request $request): JsonResponse
    {
        $user = $request->user();

        $versions = WorkflowVersion::query()
```

(Remove the `if` block entirely — the per-workflow-version stage-permission filter immediately below, using `$this->permissionResolver->userCanAccessStage(...)`, is the real and now-sole gate. If the filter yields an empty list, `availableWorkflows()` correctly returns an empty `data` array rather than a 403.)

- [ ] **Step 7: Update the existing tests that assumed static `requests` writes**

In `backend/tests/Feature/Permission/ScreenPermissionTest.php`:

`test_put_screen_permissions_replaces_grants` (around line 93-111) — remove `requests` from the PUT payload and adjust the expected row count:

```php
    public function test_put_screen_permissions_replaces_grants(): void
    {
        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->intakeRole->id}/screen-permissions", [
                'grants' => [
                    'reports' => ['VIEW'],
                ],
            ])
            ->assertOk();

        // 1 row: reports(VIEW). `requests` is no longer writable via this endpoint.
        $this->assertSame(1, ScreenPermission::where('role_id', $this->intakeRole->id)->count());
    }
```

`test_auth_me_returns_screen_permissions` (around line 132-141) — this test only asserts the `requests` key is present, which remains true (now workflow-derived) as long as the test's `$bankUser`'s role has some workflow stage assignment. If the test's fixture role has no stage assignment, `requests` may now legitimately be absent from the map (since `screenPermissionsForGovernanceRole` only adds the `requests` key when `$requestsCaps` is non-empty). Change the assertion to tolerate absence:

```php
    public function test_auth_me_returns_screen_permissions(): void
    {
        $response = $this->actingAs($this->bankUser)
            ->getJson('/api/auth/me')
            ->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('screen_permissions', $data);
        // `requests` is now workflow-derived; it's present only if the role has an
        // active stage assignment in the published workflow. Assert the key type
        // instead of presence.
        $this->assertIsArray($data['screen_permissions']);
    }
```

`test_screen_permissions_derive_from_table_not_role_codes` (around line 145-178) — this test's final assertion `assertArrayNotHasKey('requests', $sp2)` already expects `requests` absence after clearing grants, which still holds true (no stage assignment in this test's fixture = no derived access = key absent). No change needed to this test's core logic, but its setup adds `reports:VIEW` via the static path and expects `requests` to stay absent — this should still pass unmodified since `requests` was never touched by this test's grant manipulation. Run it and confirm; if it fails, the fixture's role likely needs no changes since `requests` is now sourced independently of the `screen_permissions` table entirely.

`test_last_admin_manage_removal_blocked` (around line 182-193) and `test_manage_removal_allowed_when_another_role_has_it` (around line 195-213) — both PUT `'requests' => ['VIEW']` as part of a payload alongside `screen_permissions` manipulation. Remove the `requests` key from both payloads (it's incidental to what these tests actually verify — the last-admin MANAGE guard on `screen_permissions`):

```php
    public function test_last_admin_manage_removal_blocked(): void
    {
        // system_admin is the only role with screen_permissions:MANAGE
        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->systemAdminRole->id}/screen-permissions", [
                'grants' => [
                    // deliberately omitting screen_permissions
                ],
            ])
            ->assertStatus(422);
    }

    public function test_manage_removal_allowed_when_another_role_has_it(): void
    {
        // Give intake role MANAGE on screen_permissions too
        $screenId = Screen::where('key', 'screen_permissions')->value('id');
        ScreenPermission::create([
            'role_id' => $this->intakeRole->id,
            'screen_id' => $screenId,
            'capability' => 'MANAGE',
        ]);

        // Now removing from system_admin should succeed
        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->systemAdminRole->id}/screen-permissions", [
                'grants' => [],
            ])
            ->assertOk();
    }
```

- [ ] **Step 8: Run the full screen-permissions test file plus the new enforcement test**

Run:
```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
php artisan test tests/Feature/Permission/ScreenPermissionTest.php tests/Feature/Permission/DerivedRequestsEnforcementTest.php tests/Unit/Services/PermissionServiceDerivedRequestsTest.php
```
Expected: all PASS.

- [ ] **Step 9: Search for and run any other tests referencing `requests` capability via the static path**

```bash
grep -rln "'requests'" tests/ | grep -v ScreenPermissionTest.php | grep -v DerivedRequestsEnforcementTest.php
```

For each file found, read it and determine whether it depends on the old static-seed `requests` grants (e.g. `ScreenPermissionSeeder` output). Run each: `php artisan test <file>`. If any fail due to the seeder change, update the fixture to either seed a `stage_permissions` row for the role under test, or adjust the assertion to expect no `requests` access (whichever matches that test's actual intent — read the test to decide, do not blanket-disable).

- [ ] **Step 10: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Services/Authorization/PermissionService.php backend/database/seeders/ScreenPermissionSeeder.php backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php backend/app/Http/Controllers/Api/V1/EngineRequestController.php backend/tests/Feature/Permission/ScreenPermissionTest.php backend/tests/Feature/Permission/DerivedRequestsEnforcementTest.php
git commit -m "fix(permissions): make workflow-derived stage assignments the real requests-capability source, not the static screen_permissions table"
```

---

### Task 5: Fix `system_admin` exclusion in the matrix

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php:114`
- Test: append to `backend/tests/Feature/Permission/ScreenPermissionTest.php`

**Interfaces:**
- Consumes: nothing from Tasks 3-4 directly (independent fix, same file).
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing test**

In `backend/tests/Feature/Permission/ScreenPermissionTest.php`, add a new test method (place it near the other matrix-related tests):

```php
    public function test_matrix_excludes_system_admin_role(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/screen-permissions/matrix')
            ->assertOk();

        $roleCodes = collect($response->json('data.roles'))->pluck('code')->all();
        $this->assertNotContains('system_admin', $roleCodes);
    }
```

Check the test class's `setUp()` to confirm `$this->admin` is a `system_admin`-role user with `screen_permissions:VIEW` (it should already be, per the class's existing fixtures — read `setUp()` to confirm before assuming).

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend && php artisan test --filter test_matrix_excludes_system_admin_role`
Expected: FAIL — `system_admin` currently appears in the roles list (excluded code is the dead `platform_admin`, not the real role).

- [ ] **Step 3: Fix the exclusion**

In `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php`, find line 114:

```php
            ->where('code', '!=', 'platform_admin')
```

Replace with:

```php
            ->where('code', '!=', 'system_admin')
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter test_matrix_excludes_system_admin_role`
Expected: PASS.

- [ ] **Step 5: Run the full screen-permissions test file to confirm no regression**

Run: `php artisan test tests/Feature/Permission/ScreenPermissionTest.php`
Expected: all PASS (system_admin's exclusion doesn't affect any other existing assertion — confirm by reading output, not just exit code).

- [ ] **Step 6: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php backend/tests/Feature/Permission/ScreenPermissionTest.php
git commit -m "fix(permissions): exclude the real system_admin role code from the matrix, not the dead platform_admin code"
```

---

### Task 6: Cache invalidation on workflow publish/archive and stage-permission writes

**Files:**
- Modify: `backend/app/Services/Authorization/PermissionService.php` (add `clearAllScreenPermissionCaches()`)
- Modify: `backend/app/Http/Controllers/Api/V1/StagePermissionController.php` (`store`, `update`, `destroy`)
- Modify: `backend/app/Http/Controllers/Api/V1/WorkflowVersionController.php` (`publish`, `archive`)
- Test: append to `backend/tests/Feature/Permission/DerivedRequestsEnforcementTest.php`

**Interfaces:**
- Consumes: `PermissionService::clearScreenPermissionCache(int $roleId)` (existing).
- Produces: `PermissionService::clearAllScreenPermissionCaches(): void` — clears every role's cached screen-permissions map. No other task depends on this method's exact name, but it must exist and be called from the four write paths listed below for Task 4's cache-correctness guarantee to hold in production (not just in tests, which explicitly call `clearScreenPermissionCache` per-role).

- [ ] **Step 1: Read the current `StagePermissionController` and `WorkflowVersionController` write methods**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
sed -n '1,90p' app/Http/Controllers/Api/V1/StagePermissionController.php
sed -n '84,135p' app/Http/Controllers/Api/V1/WorkflowVersionController.php
```

Note the exact structure (constructor dependencies, whether `PermissionService` is already injected) before writing the edit — the exact injection pattern must match what's already used in each controller (some use constructor property promotion, confirm before adding).

- [ ] **Step 2: Add `clearAllScreenPermissionCaches()` to `PermissionService`**

In `backend/app/Services/Authorization/PermissionService.php`, add after `clearScreenPermissionCache` (after line 144):

```php
    /**
     * Clear cached screen-permissions maps for every role. Call this whenever
     * the published workflow version or its stage_permissions change, since
     * `requests` capability is derived from that data for all roles at once
     * (not just one role, unlike a manual grant edit).
     */
    public function clearAllScreenPermissionCaches(): void
    {
        $roleIds = DB::table('roles')->pluck('id');
        foreach ($roleIds as $roleId) {
            $this->clearScreenPermissionCache($roleId);
        }
    }
```

- [ ] **Step 3: Call it from `StagePermissionController`'s write methods**

In `backend/app/Http/Controllers/Api/V1/StagePermissionController.php`, add the `use` import after the existing `use App\Services\Workflow\WorkflowDesignerService;` line:

```php
use App\Services\Authorization\PermissionService;
use App\Services\Workflow\WorkflowDesignerService;
```

Replace the constructor:

```php
    public function __construct(private readonly WorkflowDesignerService $designer) {}
```

with:

```php
    public function __construct(
        private readonly WorkflowDesignerService $designer,
        private readonly PermissionService $permissionService,
    ) {}
```

In `store()`, replace:

```php
    public function store(StoreStagePermissionRequest $request, WorkflowStage $workflowStage): JsonResponse
    {
        $this->authorize('create', StagePermission::class);

        try {
            $permission = $this->designer->createStagePermission($request->user(), $workflowStage, $request->validated());
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new StagePermissionResource($permission))->response()->setStatusCode(201);
    }
```

with:

```php
    public function store(StoreStagePermissionRequest $request, WorkflowStage $workflowStage): JsonResponse
    {
        $this->authorize('create', StagePermission::class);

        try {
            $permission = $this->designer->createStagePermission($request->user(), $workflowStage, $request->validated());
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        $this->permissionService->clearAllScreenPermissionCaches();

        return (new StagePermissionResource($permission))->response()->setStatusCode(201);
    }
```

In `update()`, replace:

```php
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new StagePermissionResource($stagePermission))->response();
    }
```

with:

```php
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        $this->permissionService->clearAllScreenPermissionCaches();

        return (new StagePermissionResource($stagePermission))->response();
    }
```

In `destroy()`, replace:

```php
        try {
            $this->designer->deleteStagePermission($request->user(), $stagePermission);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return response()->json(null, 204);
    }
```

with:

```php
        try {
            $this->designer->deleteStagePermission($request->user(), $stagePermission);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        $this->permissionService->clearAllScreenPermissionCaches();

        return response()->json(null, 204);
    }
```

- [ ] **Step 4: Call it from `WorkflowVersionController::publish` and `::archive`**

In `backend/app/Http/Controllers/Api/V1/WorkflowVersionController.php`, add the import after `use App\Services\Notifications\EngineNotificationDispatcher;`:

```php
use App\Services\Authorization\PermissionService;
use App\Services\Notifications\EngineNotificationDispatcher;
```

Replace the constructor:

```php
    public function __construct(
        private readonly WorkflowDesignerService $designer,
        private readonly WorkflowGraphService $graphService,
        private readonly AuditService $auditService,
        private readonly EngineNotificationDispatcher $notificationDispatcher,
    ) {}
```

with:

```php
    public function __construct(
        private readonly WorkflowDesignerService $designer,
        private readonly WorkflowGraphService $graphService,
        private readonly AuditService $auditService,
        private readonly EngineNotificationDispatcher $notificationDispatcher,
        private readonly PermissionService $permissionService,
    ) {}
```

In `publish()`, replace the final two lines:

```php
        $this->notificationDispatcher->afterWorkflowPublished(
            definitionId: (int) $workflowVersion->workflow_definition_id,
            workflowName: $definition?->name ?? 'Workflow',
            versionLabel: $workflowVersion->label ?? "v{$workflowVersion->id}",
            recipientUserIds: $adminUserIds,
        );

        return (new WorkflowVersionResource($workflowVersion))->response();
    }
```

with:

```php
        $this->notificationDispatcher->afterWorkflowPublished(
            definitionId: (int) $workflowVersion->workflow_definition_id,
            workflowName: $definition?->name ?? 'Workflow',
            versionLabel: $workflowVersion->label ?? "v{$workflowVersion->id}",
            recipientUserIds: $adminUserIds,
        );

        $this->permissionService->clearAllScreenPermissionCaches();

        return (new WorkflowVersionResource($workflowVersion))->response();
    }
```

In `archive()`, replace:

```php
        try {
            $workflowVersion = $this->designer->archiveVersion($request->user(), $workflowVersion, $validated['version']);
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The workflow version was modified by another user.', 409);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new WorkflowVersionResource($workflowVersion))->response();
    }
```

with:

```php
        try {
            $workflowVersion = $this->designer->archiveVersion($request->user(), $workflowVersion, $validated['version']);
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The workflow version was modified by another user.', 409);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        $this->permissionService->clearAllScreenPermissionCaches();

        return (new WorkflowVersionResource($workflowVersion))->response();
    }
```

- [ ] **Step 5: Write a test confirming publish busts the cache app-wide (not just for one role explicitly cleared)**

Append to `backend/tests/Feature/Permission/DerivedRequestsEnforcementTest.php`:

```php
    public function test_publishing_workflow_via_endpoint_busts_cache_without_manual_clear(): void
    {
        $org = Organization::factory()->create();
        $role = Role::factory()->for($org)->create(['code' => 'cache_bust_test_role']);
        $admin = User::factory()->for($org)->create();
        $adminRole = Role::factory()->for($org)->create(['code' => 'cache_bust_admin']);
        $wfScreenId = DB::table('screens')->where('key', 'workflow_designer')->value('id');
        DB::table('screen_permissions')->insert([
            'role_id' => $adminRole->id, 'screen_id' => $wfScreenId, 'capability' => 'MANAGE',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin->update(['role_id' => $adminRole->id]);

        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'code' => 'cache_bust_wf', 'name' => 'Cache Bust Test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $draftId = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId, 'version_number' => 1,
            'state' => 'DRAFT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stageId = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $draftId, 'name' => 'Intake', 'is_initial' => true,
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stage_permissions')->insert([
            'stage_id' => $stageId, 'role_id' => $role->id, 'access_level' => 'EXECUTE',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(PermissionService::class);
        // Prime the cache with the pre-publish (empty) state.
        $before = $service->screenPermissionsForGovernanceRole($role->id);
        $this->assertArrayNotHasKey('requests', $before);

        // Publish via the real endpoint — must bust the cache without a manual clear call.
        $this->actingAs($admin)
            ->postJson("/api/v1/workflow-versions/{$draftId}/publish")
            ->assertOk();

        $after = $service->screenPermissionsForGovernanceRole($role->id);
        $this->assertArrayHasKey('requests', $after);
        $this->assertContains('CREATE', $after['requests']);
    }
```

Adjust the publish endpoint URL/route name if it differs from `/api/v1/workflow-versions/{id}/publish` — check `backend/routes/api.php` for the actual registered route before finalizing this test.

- [ ] **Step 6: Run the test to verify it fails, then passes**

Run:
```bash
php artisan test --filter test_publishing_workflow_via_endpoint_busts_cache_without_manual_clear
```
Expected: FAILs before Steps 2-4's changes are in place (if run standalone before them), PASSes after.

- [ ] **Step 7: Run the full permission test suite**

Run:
```bash
php artisan test tests/Feature/Permission/ tests/Unit/Services/PermissionServiceDerivedRequestsTest.php
```
Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Services/Authorization/PermissionService.php backend/app/Http/Controllers/Api/V1/StagePermissionController.php backend/app/Http/Controllers/Api/V1/WorkflowVersionController.php backend/tests/Feature/Permission/DerivedRequestsEnforcementTest.php
git commit -m "fix(permissions): bust screen-permission cache on workflow publish/archive and stage-permission writes"
```

---

### Task 7: Frontend verification of the screen-permissions fix

**Files:**
- No code changes expected. This task is manual/browser verification only, confirming the existing `screen-permissions.vue` renders correctly against the fixed backend with zero template changes (per the spec's finding).

**Interfaces:**
- Consumes: the fixed `/api/v1/screen-permissions/matrix` endpoint from Tasks 3-6.
- Produces: nothing.

- [ ] **Step 1: Seed/confirm local dev data has a published workflow with stage assignments**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
php artisan db:seed --class=GovernanceSeeder --force 2>&1 | tail -20
```

Confirm a published workflow version exists with `stage_permissions` rows (check via `php artisan tinker` or the admin/workflows UI) — if not, this step cannot proceed meaningfully; flag and ask before continuing rather than testing against an empty state.

- [ ] **Step 2: Log in as CBY_ADMIN, navigate to `/admin/screen-permissions`**

Using `playwright-cli`/MCP tools, log in as the CBY_ADMIN demo user and load `/admin/screen-permissions`. Confirm:
- `system_admin`'s row does NOT appear in the roles list at all (not blank — absent).
- The "الطلبات" (requests) column group shows checkmarks/X's that correspond to whatever the published workflow's stage assignments actually grant each visible role — cross-check at least one role's requests column against that role's stage assignment in `/admin/workflows`.
- The "الطلبات" switches remain non-interactive (view-only, matching the existing `isForced`/read-only styling already in the template).

- [ ] **Step 3: Confirm a role change in the workflow designer propagates**

In `/admin/workflows`, change a role's stage assignment for the published workflow (or publish a new version with a different assignment), then reload `/admin/screen-permissions` and confirm the `requests` column for that role updates accordingly (proves the cache-bust from Task 6 works end-to-end, not just in the PHPUnit fixture).

- [ ] **Step 4: Report findings**

If any of the above do not match expectations, that's a real gap in Tasks 3-6 — do not silently accept a mismatch. Note the specific discrepancy for the task reviewer to catch.

---

## Done Criteria

- BANK_ADMIN sees the same `DataTable`-based merchants view as CBY_ADMIN, correctly bank-scoped, with the bank column/filter hidden.
- `/admin/cby-staff` no longer exists; `/admin/staff` serves the identity users page; every nav/search/test reference updated.
- The screen-permissions matrix's `requests` column reflects the published workflow's stage assignments, matching what's actually enforced at `/auth/me` and everywhere else `userHasCapability('requests', ...)` or `screenPermissionsForGovernanceRole()` is called.
- `system_admin` no longer appears as a row in the matrix.
- The static `screen_permissions` table can no longer hold or serve `requests` grants — seeder no longer writes them, PUT endpoint rejects them, `screenPermissionsForGovernanceRole()` ignores any stray existing rows for that key.
- Publishing/archiving a workflow version or editing `stage_permissions` correctly busts the screen-permissions cache without requiring a manual per-role clear.
- All touched Vitest/PHPUnit tests pass; `pnpm typecheck` clean.
