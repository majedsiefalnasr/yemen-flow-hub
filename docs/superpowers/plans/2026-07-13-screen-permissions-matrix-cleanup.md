# Screen Permissions Matrix Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the screen-permissions catalog truthful and enforceable by renaming the organization analytics capability, adding delegable own-bank staff management, preventing system-dashboard delegation, and removing the dead `/bank/users` route.

**Architecture:** Keep `system_dashboard` as the revocable runtime gate for the fixed `system_admin` dashboard, but remove it from every delegable matrix path. Rename `bank_analytics` to `org_analytics` across the seeded catalog, runtime gates, frontend selection, tests, and live docs. Make `staff:VIEW` the non-system authorization gate for `/staff` and `/api/v1/users`, with database queries and mutations constrained to the actor's own bank; retain `SYSTEM_ADMIN`'s existing system-wide user-management path.

**Tech Stack:** Laravel 11 / PHP 8.2 / PHPUnit, Nuxt 4 / Vue 3.5 / TypeScript / Vitest, MySQL, shadcn-vue, Playwright CLI.

## Global Constraints

- Repository root: `/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code`; `backend/`, `frontend/`, and `docs/` are normal directories in one root Git repository.
- Run `git -c core.fsmonitor=false status --short` before edits. Preserve unrelated dirty files and never stage `graphify-out/`.
- Use `pnpm`; do not migrate JavaScript tooling to Bun.
- Keep `system_dashboard` in the backend screen catalog, the `system_admin` seed grant, `DashboardStatsService`, and dashboard-family tests. It remains a live, revocable capability.
- `system_dashboard` is not delegable: exclude it in `RoleScreenPermissionController::ADMIN_ONLY_SCREENS` so both matrix reads and direct update API calls reject it; retain the frontend exclusion as defense in depth.
- Rename only the screen-permission key `bank_analytics` to `org_analytics`. Do not rename `BankAdminDashboard.vue`, `bankAdminStats()`, `BANK_ADMIN`, `bank_id`, or unrelated bank-domain identifiers.
- Add `staff` as a VIEW-only screen. `staff:VIEW` intentionally grants the complete `/staff` management workflow because that page has no separate read/manage modes.
- Preserve `SYSTEM_ADMIN`'s system-wide `/api/v1/users` access. Every other staff-capability holder must be scoped to their own non-null `bank_id` at both policy and database-query/mutation boundaries.
- A non-system staff manager may manage only roles in `RoleCodes::BANK_ADMIN_MANAGED` and may not create, view, update, deactivate, or recover an account in another bank.
- Do not change `/admin/staff.vue`; it continues to use the `users` screen key.
- Do not generalize `bank_id` to an organization abstraction in this work.
- Do not add a `MANAGE` capability to `staff`.
- Frontend route admission must use `middleware: ['auth', 'screen']`, `requiredScreen: 'staff'`, and `requiredCapability: 'VIEW'`.
- Follow the existing shadcn-vue UI. No raw component substitutions are needed by this change.
- Focused verification only; do not run full backend or frontend suites by default.
- All commits use `type(scope): description`, remain signed, and include `Co-Authored-By: Claude <noreply@anthropic.com>`.

## Planning Corrections From Live-Code Validation

The approved design described a frontend-only `system_dashboard` exclusion. The live update endpoint currently accepts that key, so UI-only hiding would still allow a direct API grant. This plan adds `system_dashboard` to `ADMIN_ONLY_SCREENS` while preserving its catalog row and runtime gate.

The live `UserController::index()` query scopes only actors whose role code is literally `bank_admin`. Once another banking role receives `staff:VIEW`, policy-only authorization would expose an unscoped user list. This plan scopes every authorized non-system staff manager by `bank_id` and validates create/update payloads against the same bank.

The stale `/bank/users` route is documented in `frontend/CLAUDE.md`, not root `AGENTS.md`. Update the file that actually contains the route list.

---

### Task 1: Rename the analytics capability contract to `org_analytics`

**Files:**

- Modify: `backend/database/seeders/ScreenPermissionSeeder.php`
- Modify: `backend/app/Services/Dashboard/DashboardStatsService.php`
- Modify: `backend/tests/Feature/Dashboard/DashboardFamilyCapabilityTest.php`
- Modify: `backend/tests/Feature/Permission/ScreenPermissionTest.php`
- Modify: `backend/tests/Support/AssignsGovernanceIdentity.php`
- Modify: `backend/tests/Feature/DashboardStatsTest.php`
- Modify: `frontend/app/pages/dashboard.vue`
- Modify: `frontend/app/pages/index.vue`
- Modify: `frontend/app/tests/unit/pages/DashboardPage.test.ts`

**Interfaces:**

- Produces: screen key `org_analytics` with Arabic label `تحليلات المنظمة` and default `bank_admin` VIEW grant.
- Consumes: `PermissionService::userHasCapability(User $user, string $screenKey, string $capability): bool` unchanged.
- Preserves: dashboard family values `'system' | 'bank' | 'work'`; only the permission key changes.

- [ ] **Step 1: Rename the focused backend dashboard tests before production code**

In `backend/tests/Feature/Dashboard/DashboardFamilyCapabilityTest.php`, replace the two bank-capability tests with:

```php
public function test_bank_admin_with_capability_gets_org_analytics(): void
{
    $admin = $this->makeUser(UserRole::BANK_ADMIN);

    $this->actingAs($admin)
        ->getJson('/api/dashboard/stats')
        ->assertOk()
        ->assertJsonStructure(['data' => ['total', 'pending', 'approved', 'rejected', 'monthly_requests']]);
}

public function test_revoking_org_analytics_capability_removes_analytics_access(): void
{
    $admin = $this->makeUser(UserRole::BANK_ADMIN);
    $this->revokeCapability('bank_admin', 'org_analytics');

    $data = $this->actingAs($admin)->getJson('/api/dashboard/stats')->assertOk()->json('data');

    $this->assertArrayNotHasKey('monthly_requests', $data);
    $this->assertArrayNotHasKey('total_financed_amount', $data);
}
```

In `backend/tests/Feature/Permission/ScreenPermissionTest.php`, replace the dashboard key at the end of `$expected`:

```php
'system_dashboard', 'org_analytics',
```

- [ ] **Step 2: Rename the pure frontend routing test before production code**

In `frontend/app/tests/unit/pages/DashboardPage.test.ts`, change the bank branch and its tests to:

```ts
if (can("system_dashboard", "VIEW")) return "SystemAdminDashboard";
if (can("org_analytics", "VIEW")) return "BankAdminDashboard";
return "MyWorkDashboard";
```

```ts
it("routes a user with the org_analytics screen + VIEW capability to BankAdminDashboard", () => {
  expect(resolveDashboardFamily({ org_analytics: ["VIEW"] })).toBe(
    "BankAdminDashboard",
  );
});

it("prefers system governance over organization analytics when both are held", () => {
  expect(
    resolveDashboardFamily({
      system_dashboard: ["VIEW"],
      org_analytics: ["VIEW"],
    }),
  ).toBe("SystemAdminDashboard");
});
```

- [ ] **Step 3: Run the renamed tests and confirm the contract is red**

Run:

```bash
cd backend
php artisan test tests/Feature/Dashboard/DashboardFamilyCapabilityTest.php
php artisan test tests/Feature/Permission/ScreenPermissionTest.php
```

Expected: failures show `org_analytics` is not seeded and the bank dashboard branch falls through.

Run:

```bash
cd ../frontend
pnpm exec vitest run app/tests/unit/pages/DashboardPage.test.ts
```

Expected: the pure test passes because its local resolver has already been renamed; production source remains unchanged and is checked in Step 6.

- [ ] **Step 4: Rename the backend catalog and runtime gate**

In `backend/database/seeders/ScreenPermissionSeeder.php`, use:

```php
// Phase D0 dashboard-family capabilities. `system_dashboard` gates the
// platform governance/analytics dashboard + its APIs; `org_analytics`
// gates the organization-scoped analytics dashboard + its APIs. Workflow
// users hold neither and fall through to the operational MyWorkDashboard.
'system_dashboard' => 'لوحة إدارة النظام',
'org_analytics' => 'تحليلات المنظمة',
```

and replace the `bank_admin` grant with:

```php
// Routes Bank Admin to the organization-scoped analytics dashboard (D0).
'org_analytics' => ['VIEW'],
```

In `backend/app/Services/Dashboard/DashboardStatsService.php`, change only the screen key:

```php
$analyticsGate(RoleCodes::BANK_ADMIN, 'org_analytics') => $this->bankAdminStats($user, $scope),
```

Update comment-only references in `backend/tests/Support/AssignsGovernanceIdentity.php` and `backend/tests/Feature/DashboardStatsTest.php` from `bank_analytics` to `org_analytics`.

- [ ] **Step 5: Rename both frontend dashboard selectors**

In both `frontend/app/pages/dashboard.vue` and `frontend/app/pages/index.vue`, use:

```ts
const dashboardFamily = computed<"system" | "bank" | "work">(() => {
  if (can("system_dashboard", "VIEW")) return "system";
  if (can("org_analytics", "VIEW")) return "bank";
  return "work";
});
```

Update the adjacent comment from bank analytics to organization analytics without renaming the `bank` family value or component.

- [ ] **Step 6: Run focused contract verification**

Run:

```bash
cd backend
php artisan test tests/Feature/Dashboard/DashboardFamilyCapabilityTest.php
php artisan test tests/Feature/Permission/ScreenPermissionTest.php
vendor/bin/pint database/seeders/ScreenPermissionSeeder.php app/Services/Dashboard/DashboardStatsService.php tests/Feature/Dashboard/DashboardFamilyCapabilityTest.php tests/Feature/Permission/ScreenPermissionTest.php tests/Support/AssignsGovernanceIdentity.php tests/Feature/DashboardStatsTest.php --test
```

Expected: all focused tests pass and Pint reports no changes required.

Run:

```bash
cd ../frontend
pnpm exec vitest run app/tests/unit/pages/DashboardPage.test.ts
pnpm exec eslint app/pages/dashboard.vue app/pages/index.vue app/tests/unit/pages/DashboardPage.test.ts
pnpm exec prettier app/pages/dashboard.vue app/pages/index.vue app/tests/unit/pages/DashboardPage.test.ts --check
rg -n "bank_analytics" app/pages/dashboard.vue app/pages/index.vue app/tests/unit/pages/DashboardPage.test.ts
```

Expected: Vitest, ESLint, and Prettier pass; `rg` returns no matches.

- [ ] **Step 7: Commit the atomic cross-layer rename**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/database/seeders/ScreenPermissionSeeder.php backend/app/Services/Dashboard/DashboardStatsService.php backend/tests/Feature/Dashboard/DashboardFamilyCapabilityTest.php backend/tests/Feature/Permission/ScreenPermissionTest.php backend/tests/Support/AssignsGovernanceIdentity.php backend/tests/Feature/DashboardStatsTest.php frontend/app/pages/dashboard.vue frontend/app/pages/index.vue frontend/app/tests/unit/pages/DashboardPage.test.ts
git commit -m "$(cat <<'EOF'
refactor(workflow): rename organization analytics capability

Rename bank_analytics to org_analytics across the seeded permission
catalog, backend dashboard gate, frontend family selector, and tests.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Make the matrix catalog truthful and add the `staff` screen

**Files:**

- Modify: `backend/database/seeders/ScreenPermissionSeeder.php`
- Modify: `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php`
- Modify: `backend/tests/Feature/Permission/ScreenPermissionTest.php`
- Modify: `frontend/app/pages/admin/screen-permissions.vue`
- Modify: `frontend/app/tests/unit/pages/screen-permissions.test.ts`

**Interfaces:**

- Produces: `staff` screen with `['VIEW']` in matrix responses and default `bank_admin` VIEW grant.
- Produces: `system_dashboard` remains a Screen row but is absent from matrix responses and rejected by the update endpoint.
- Preserves: `requests` remains non-writable and absent from the grantable frontend columns.

- [ ] **Step 1: Add failing catalog and server-side non-delegation tests**

In `backend/tests/Feature/Permission/ScreenPermissionTest.php`, update the catalog count tests to 17 and include `staff`:

```php
public function test_catalog_has_17_screens(): void
{
    $this->assertSame(17, Screen::count());
}
```

Update the API count assertion too:

```php
public function test_get_screens_returns_all(): void
{
    $this->actingAs($this->admin)
        ->getJson('/api/v1/screens')
        ->assertOk()
        ->assertJsonCount(17, 'data');
}
```

```php
'audit', 'reference_data', 'screen_permissions', 'staff', 'notifications', 'settings',
```

Add:

```php
public function test_bank_admin_receives_staff_view_by_default(): void
{
    $bankAdminRole = Role::query()->where('code', 'bank_admin')->firstOrFail();
    $staffScreen = Screen::query()->where('key', 'staff')->firstOrFail();

    $this->assertDatabaseHas('screen_permissions', [
        'role_id' => $bankAdminRole->id,
        'screen_id' => $staffScreen->id,
        'capability' => 'VIEW',
    ]);
}

public function test_matrix_excludes_system_dashboard_but_includes_view_only_staff(): void
{
    $screens = collect(
        $this->actingAs($this->admin)
            ->getJson('/api/v1/screen-permissions/matrix')
            ->assertOk()
            ->json('data.screens')
    )->keyBy('key');

    $this->assertFalse($screens->has('system_dashboard'));
    $this->assertTrue($screens->has('staff'));
    $this->assertSame(['VIEW'], $screens->get('staff')['capabilities']);
}

public function test_update_rejects_system_dashboard_grant(): void
{
    $this->actingAs($this->admin)
        ->putJson("/api/v1/roles/{$this->intakeRole->id}/screen-permissions", [
            'grants' => ['system_dashboard' => ['VIEW']],
        ])
        ->assertStatus(422);
}
```

- [ ] **Step 2: Add the failing frontend exclusion test**

In `frontend/app/tests/unit/pages/screen-permissions.test.ts`, replace the last test with:

```ts
it("excludes requests and system_dashboard from manual grantable columns", () => {
  expect(source).toContain(
    "NON_GRANTABLE_SCREEN_KEYS = new Set(['requests', 'system_dashboard'])",
  );
  expect(source).toContain("!NON_GRANTABLE_SCREEN_KEYS.has(s.key)");
});
```

- [ ] **Step 3: Run the focused tests and confirm they fail**

Run:

```bash
cd backend
php artisan test tests/Feature/Permission/ScreenPermissionTest.php
```

Expected: failures for count 17, missing `staff`, and `system_dashboard` still being returned/writable.

Run:

```bash
cd ../frontend
pnpm exec vitest run app/tests/unit/pages/screen-permissions.test.ts
```

Expected: failure because the page still declares only `REQUESTS_KEY`.

- [ ] **Step 4: Seed `staff` and its default grant**

In `backend/database/seeders/ScreenPermissionSeeder.php`, add the screen beside `users`:

```php
'users' => 'المستخدمون',
'staff' => 'الموظفون',
'merchants' => 'المستوردون',
```

Add the default grant inside `bank_admin`:

```php
'users' => ['VIEW', 'MANAGE'],
'staff' => ['VIEW'],
'reports' => ['VIEW'],
```

- [ ] **Step 5: Enforce `system_dashboard` as backend non-delegable**

In `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php`, add the key to `ADMIN_ONLY_SCREENS`:

```php
private const ADMIN_ONLY_SCREENS = [
    'workflow_designer',
    'users',
    'teams',
    'roles',
    'screen_permissions',
    'reference_data',
    'organizations',
    'banks',
    'system_dashboard',
];
```

Do not remove `system_dashboard` from the seeder or `DashboardStatsService`; this list controls only matrix delegation.

- [ ] **Step 6: Add the frontend non-grantable key set**

In `frontend/app/pages/admin/screen-permissions.vue`, replace `REQUESTS_KEY` and `manualScreens` with:

```ts
// `requests` is workflow-derived. `system_dashboard` is reserved for the fixed
// system_admin dashboard family. Neither key is manually grantable.
const NON_GRANTABLE_SCREEN_KEYS = new Set(["requests", "system_dashboard"]);
```

```ts
const manualScreens = computed(
  () =>
    matrix.value?.screens.filter(
      (s) => !NON_GRANTABLE_SCREEN_KEYS.has(s.key),
    ) ?? [],
);
```

- [ ] **Step 7: Run focused matrix verification**

Run:

```bash
cd backend
php artisan test tests/Feature/Permission/ScreenPermissionTest.php
vendor/bin/pint database/seeders/ScreenPermissionSeeder.php app/Http/Controllers/Api/V1/RoleScreenPermissionController.php tests/Feature/Permission/ScreenPermissionTest.php --test
```

Expected: all tests pass; the matrix contains `staff` with VIEW only, excludes `system_dashboard`, and rejects a direct grant.

Run:

```bash
cd ../frontend
pnpm exec vitest run app/tests/unit/pages/screen-permissions.test.ts app/tests/unit/composables/useScreenPermissionsAdmin.test.ts
pnpm exec eslint app/pages/admin/screen-permissions.vue app/tests/unit/pages/screen-permissions.test.ts
pnpm exec prettier app/pages/admin/screen-permissions.vue app/tests/unit/pages/screen-permissions.test.ts --check
```

Expected: both Vitest files, ESLint, and Prettier pass.

- [ ] **Step 8: Commit the catalog and non-delegation behavior**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/database/seeders/ScreenPermissionSeeder.php backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php backend/tests/Feature/Permission/ScreenPermissionTest.php frontend/app/pages/admin/screen-permissions.vue frontend/app/tests/unit/pages/screen-permissions.test.ts
git commit -m "$(cat <<'EOF'
feat(settings): add delegable staff screen permission

Add the VIEW-only staff screen, keep the bank administrator default,
and prevent system_dashboard from entering matrix or direct API grants.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Enforce `staff:VIEW` on the users API with own-bank scope

**Files:**

- Modify: `backend/app/Policies/UserPolicy.php`
- Modify: `backend/app/Http/Controllers/Api/V1/UserController.php`
- Modify: `backend/tests/Feature/Engine/EngineBankAdminRbacTest.php`

**Interfaces:**

- Consumes: `PermissionService::userHasCapability($actor, 'staff', 'VIEW')`.
- Produces: non-system staff managers can list/create/manage only `RoleCodes::BANK_ADMIN_MANAGED` users in their own bank.
- Preserves: system administrators remain system-wide and self-recovery/self-deactivation restrictions remain unchanged.

- [ ] **Step 1: Add a test helper that grants `staff:VIEW` to the intake role**

In `backend/tests/Feature/Engine/EngineBankAdminRbacTest.php`, import `Screen`, `ScreenPermission`, and `PermissionService`, then add:

```php
use App\Models\Screen;
use App\Models\ScreenPermission;
use App\Services\Authorization\PermissionService;
```

```php
private function grantStaffViewToIntake(): void
{
    ScreenPermission::query()->create([
        'role_id' => $this->intakeRole->id,
        'screen_id' => Screen::query()->where('key', 'staff')->value('id'),
        'capability' => 'VIEW',
    ]);

    app(PermissionService::class)->clearScreenPermissionCache($this->intakeRole->id);
}
```

- [ ] **Step 2: Add failing delegated-access and data-scope tests**

Add:

```php
public function test_staff_capability_holder_lists_only_own_bank_users(): void
{
    $this->grantStaffViewToIntake();

    $response = $this->actingAs($this->dataEntry)->getJson('/api/v1/users')->assertOk();
    $userIds = collect($response->json('data'))->pluck('id')->all();

    $this->assertContains($this->dataEntry->id, $userIds);
    $this->assertContains($this->bankAdmin->id, $userIds);
    $this->assertNotContains($this->dataEntryB->id, $userIds);
    $this->assertNotContains($this->bankAdminB->id, $userIds);
}

public function test_staff_capability_holder_can_manage_eligible_own_bank_user_only(): void
{
    $this->grantStaffViewToIntake();

    $target = $this->makeUser(
        'eligible-target@rbac.test',
        UserRole::DATA_ENTRY,
        $this->bankA,
        $this->intakeRole,
        $this->entryTeam
    );

    $policy = app(\App\Policies\UserPolicy::class);

    $this->assertTrue($policy->viewAny($this->dataEntry));
    $this->assertTrue($policy->create($this->dataEntry));
    $this->assertTrue($policy->view($this->dataEntry, $target));
    $this->assertTrue($policy->update($this->dataEntry, $target));
    $this->assertTrue($policy->delete($this->dataEntry, $target));
    $this->assertTrue($policy->resetPassword($this->dataEntry, $target));
    $this->assertTrue($policy->resetMfa($this->dataEntry, $target));
    $this->assertTrue($policy->resetPin($this->dataEntry, $target));

    $this->assertFalse($policy->view($this->dataEntry, $this->dataEntryB));
    $this->assertFalse($policy->update($this->dataEntry, $this->dataEntryB));
    $this->assertFalse($policy->delete($this->dataEntry, $this->dataEntryB));
}

public function test_revoking_staff_view_removes_bank_admin_users_api_access(): void
{
    $staffScreenId = Screen::query()->where('key', 'staff')->value('id');
    ScreenPermission::query()
        ->where('role_id', $this->bankAdminRole->id)
        ->where('screen_id', $staffScreenId)
        ->delete();
    app(PermissionService::class)->clearScreenPermissionCache($this->bankAdminRole->id);

    $this->actingAs($this->bankAdmin)->getJson('/api/v1/users')->assertForbidden();
}
```

- [ ] **Step 3: Add failing create-scope tests**

Add a payload helper:

```php
private function createUserPayload(Bank $bank, string $email): array
{
    return [
        'organization_id' => $this->bankOrg->id,
        'team_id' => $this->entryTeam->id,
        'role_id' => $this->intakeRole->id,
        'bank_id' => $bank->id,
        'name' => 'Delegated staff target',
        'email' => $email,
        'password' => 'ValidPassword123!',
        'is_active' => true,
    ];
}
```

Add:

```php
public function test_staff_capability_holder_can_create_only_in_own_bank(): void
{
    $this->grantStaffViewToIntake();

    $this->actingAs($this->dataEntry)
        ->postJson('/api/v1/users', $this->createUserPayload($this->bankA, 'own-bank@rbac.test'))
        ->assertCreated()
        ->assertJsonPath('data.bank_id', $this->bankA->id);

    $this->actingAs($this->dataEntry)
        ->postJson('/api/v1/users', $this->createUserPayload($this->bankB, 'other-bank@rbac.test'))
        ->assertForbidden();
}
```

- [ ] **Step 4: Run the focused test and confirm it fails**

Run:

```bash
cd backend
php artisan test tests/Feature/Engine/EngineBankAdminRbacTest.php
```

Expected: delegated intake access is forbidden before the policy change; after a naive policy-only change, the own-bank list assertion would expose the controller scoping defect.

- [ ] **Step 5: Inject `PermissionService` and make the policy capability-led**

Replace `backend/app/Policies/UserPolicy.php` with:

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\PermissionService;
use App\Support\RoleCodes;

class UserPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || $this->canManageBankStaff($user);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || $this->canManageOwnBankUser($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || $this->canManageBankStaff($user);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || $this->canManageOwnBankUser($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            || $this->canManageOwnBankUser($user, $model);
    }

    public function cbyAdmin(User $user): bool
    {
        return $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN);
    }

    public function resetPassword(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            return true;
        }

        return $this->canManageOwnBankUser($user, $model);
    }

    public function resetMfa(User $user, User $model): bool
    {
        return $user->id !== $model->id
            && ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN) || $this->canManageOwnBankUser($user, $model));
    }

    public function resetPin(User $user, User $model): bool
    {
        return $user->id !== $model->id
            && ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN) || $this->canManageOwnBankUser($user, $model));
    }

    private function canManageBankStaff(User $actor): bool
    {
        return $actor->bank_id !== null
            && $this->permissions->userHasCapability($actor, 'staff', 'VIEW');
    }

    private function canManageOwnBankUser(User $actor, User $target): bool
    {
        return $this->canManageBankStaff($actor)
            && $target->bank_id === $actor->bank_id
            && $target->hasAnyRoleCode(RoleCodes::BANK_ADMIN_MANAGED);
    }
}
```

This intentionally makes the capability revocable for `bank_admin`; do not retain a hardcoded bank-admin bypass.

- [ ] **Step 6: Scope every non-system list query by the actor's bank**

In `UserController::index()`, replace the role-specific `when()` with:

```php
->when(
    ! $actor->hasRoleCode(RoleCodes::SYSTEM_ADMIN),
    fn ($q) => $q->where('bank_id', $actor->bank_id)
)
```

Policy authorization guarantees the non-system actor has `staff:VIEW` and a non-null bank before this query runs. The query itself still enforces the data boundary.

- [ ] **Step 7: Reject cross-bank and ineligible create/update payloads**

At the end of `validateIdentity()`, after banking-sector `bank_id` validation and before `return $data`, add:

```php
$actor = $request->user();
if (! $actor->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
    $targetRoleCode = Role::query()->whereKey($data['role_id'])->value('code');

    abort_unless(
        $actor->bank_id !== null
            && (int) $data['bank_id'] === (int) $actor->bank_id
            && (int) $data['organization_id'] === (int) $actor->organization_id
            && in_array($targetRoleCode, RoleCodes::BANK_ADMIN_MANAGED, true),
        403
    );
}
```

This closes both the newly introduced delegated path and the existing direct-API hole where a bank admin could submit another bank's IDs.

- [ ] **Step 8: Run focused backend verification**

Run:

```bash
cd backend
php artisan test tests/Feature/Engine/EngineBankAdminRbacTest.php
php artisan test tests/Feature/Permission/ScreenPermissionTest.php
vendor/bin/pint app/Policies/UserPolicy.php app/Http/Controllers/Api/V1/UserController.php tests/Feature/Engine/EngineBankAdminRbacTest.php --test
```

Expected: all tests pass; revoking `staff:VIEW` denies the bank administrator, granting it to intake permits own-bank access only, and system-admin behavior remains covered by the existing user-controller tests.

- [ ] **Step 9: Commit capability-led API enforcement**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Policies/UserPolicy.php backend/app/Http/Controllers/Api/V1/UserController.php backend/tests/Feature/Engine/EngineBankAdminRbacTest.php
git commit -m "$(cat <<'EOF'
feat(backend): enforce staff capability on users API

Use staff:VIEW as the revocable non-system user-management gate and
preserve own-bank scope in policies, list queries, and write payloads.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Gate `/staff` with the screen permission

**Files:**

- Modify: `frontend/app/pages/staff.vue`
- Modify: `frontend/app/tests/unit/pages/StaffPage.test.ts`

**Interfaces:**

- Consumes: hydrated `auth.screenPermissions.staff` through `screen.ts` middleware.
- Produces: `/staff` page metadata requiring `staff:VIEW` rather than a hardcoded role.
- Preserves: the page's existing staff-management UI, `UserRole` uses for staff types, and API calls.

- [ ] **Step 1: Add a source-level route contract test**

In `frontend/app/tests/unit/pages/StaffPage.test.ts`, import `readFileSync` and `resolve`, then add before the render tests:

```ts
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
```

```ts
it("uses staff VIEW screen middleware instead of a BANK_ADMIN route guard", () => {
  const source = readFileSync(
    resolve(process.cwd(), "app/pages/staff.vue"),
    "utf8",
  );

  expect(source).toContain("middleware: ['auth', 'screen']");
  expect(source).toContain("requiredScreen: 'staff'");
  expect(source).toContain("requiredCapability: 'VIEW'");
  expect(source).not.toContain("requiredRoles: [UserRole.BANK_ADMIN]");
});
```

- [ ] **Step 2: Run the route contract test and confirm it fails**

Run:

```bash
cd frontend
pnpm exec vitest run app/tests/unit/pages/StaffPage.test.ts -t "uses staff VIEW"
```

Expected: failure because `staff.vue` still uses the role middleware.

- [ ] **Step 3: Replace only the page metadata**

In `frontend/app/pages/staff.vue`, replace `definePageMeta()` with:

```ts
definePageMeta({
  middleware: ["auth", "screen"],
  requiredScreen: "staff",
  requiredCapability: "VIEW",
});
```

Do not remove the `UserRole` import; the page still uses `DATA_ENTRY` and `BANK_REVIEWER` throughout its component logic.

- [ ] **Step 4: Run the full focused page test and static checks**

Run:

```bash
cd frontend
pnpm exec vitest run app/tests/unit/pages/StaffPage.test.ts
pnpm exec eslint app/pages/staff.vue app/tests/unit/pages/StaffPage.test.ts
pnpm exec prettier app/pages/staff.vue app/tests/unit/pages/StaffPage.test.ts --check
```

Expected: the route contract and existing page behavior tests pass; ESLint has zero warnings; Prettier passes.

- [ ] **Step 5: Commit the route gate change**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/pages/staff.vue frontend/app/tests/unit/pages/StaffPage.test.ts
git commit -m "$(cat <<'EOF'
feat(frontend): gate staff page by screen permission

Replace the fixed bank-admin route guard with the VIEW-only staff screen
capability while preserving the page's existing management workflow.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Delete the dead `/bank/users` route and repair its test/docs references

**Files:**

- Delete: `frontend/app/pages/bank/users.vue`
- Modify: `frontend/app/tests/unit/pages/IdentityUsersPages.test.ts`
- Modify: `frontend/CLAUDE.md`

**Interfaces:**

- Preserves: `/admin/staff` using `IdentityUsersPage audience="committee"` and `users` permission.
- Preserves: `/staff` as the organization staff-management route.
- Removes: the unreferenced `/bank/users` page only; do not delete `IdentityUsersPage.vue`.

- [ ] **Step 1: Change the route-existence test before deleting the page**

In `frontend/app/tests/unit/pages/IdentityUsersPages.test.ts`, import `existsSync` and replace the second test with:

```ts
import { existsSync, readFileSync } from "node:fs";
```

```ts
it("keeps the committee route and removes the dead bank wrapper route", () => {
  expect(
    readFileSync(resolve(process.cwd(), "app/pages/admin/staff.vue"), "utf8"),
  ).toContain('audience="committee"');
  expect(existsSync(resolve(process.cwd(), "app/pages/bank/users.vue"))).toBe(
    false,
  );
});
```

- [ ] **Step 2: Run the focused test and confirm it fails**

Run:

```bash
cd frontend
pnpm exec vitest run app/tests/unit/pages/IdentityUsersPages.test.ts
```

Expected: failure because `app/pages/bank/users.vue` still exists.

- [ ] **Step 3: Delete the route and update the authoritative frontend route list**

Delete `frontend/app/pages/bank/users.vue`.

In `frontend/CLAUDE.md`, replace the stale route-list lines:

```text
/admin/banks      ← CBY Admin only
/bank/users       ← Bank Admin only
/staff            ← CBY Admin only
```

with:

```text
/admin/banks      ← CBY Admin only
/admin/staff      ← CBY Admin staff management (`users` screen)
/staff            ← Organization staff management (`staff:VIEW`)
```

- [ ] **Step 4: Prove no live reference to the deleted route remains**

Run:

```bash
cd frontend
pnpm exec vitest run app/tests/unit/pages/IdentityUsersPages.test.ts app/tests/unit/constants/nav-items.test.ts
pnpm exec prettier app/tests/unit/pages/IdentityUsersPages.test.ts CLAUDE.md --check
rg -n "/bank/users|pages/bank/users" app CLAUDE.md --glob '!app/tests/unit/constants/nav-items.test.ts'
```

Expected: both test files and Prettier pass; `rg` returns no matches. The negative assertion text in `nav-items.test.ts` may remain because it explicitly proves the route is absent.

- [ ] **Step 5: Commit dead-route cleanup**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/pages/bank/users.vue frontend/app/tests/unit/pages/IdentityUsersPages.test.ts frontend/CLAUDE.md
git commit -m "$(cat <<'EOF'
refactor(frontend): remove dead bank users route

Delete the unused IdentityUsersPage bank wrapper and document /staff as
the capability-gated organization staff-management surface.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Update live authorization and deployment documentation

**Files:**

- Modify: `docs/architecture/03-permission-model.md`
- Modify: `docs/architecture/04-dashboard-architecture.md`
- Modify: `docs/frontend-guide.md`
- Modify: `docs/engine/extension-guide.md`
- Modify: `docs/testing-guide.md`
- Create: `docs/release-notes/screen-permissions-matrix-cleanup.md`
- Modify: `docs/README.md`

**Interfaces:**

- Documents: `org_analytics`, non-delegable `system_dashboard`, VIEW-only `staff`, own-bank users API scope, and the live-database key migration.
- Preserves: component/class names containing `BankAdmin`; those are explicitly out of scope.

- [ ] **Step 1: Rename only the live documentation key references**

In the five live guides, replace literal capability references as follows:

```text
bank_analytics  -> org_analytics
bank analytics  -> organization analytics
bank-scoped analytics dashboard -> organization-scoped analytics dashboard
```

Keep `BankAdminDashboard.vue`, `bankAdminStats()`, `BANK_ADMIN`, and `bank_id` unchanged.

In `docs/architecture/03-permission-model.md`, add this screen-gate note after the dashboard gate paragraph:

```markdown
`system_dashboard` remains a seeded, revocable runtime capability for
`system_admin`, but it is classified as admin-only by
`RoleScreenPermissionController`: it is absent from the delegable matrix and
rejected by the update endpoint. `staff` is the VIEW-only delegable capability
for `/staff`; non-system holders are constrained to their own bank by
`UserPolicy` and `UserController` query/write boundaries.
```

- [ ] **Step 2: Create exact live-database upgrade instructions**

Create `docs/release-notes/screen-permissions-matrix-cleanup.md` with:

````markdown
# Screen Permissions Matrix Cleanup — Release Notes

**Date:** 2026-07-13

## Summary

- `bank_analytics` is renamed to `org_analytics`; behavior remains restricted
  to the `BANK_ADMIN` dashboard branch.
- `staff:VIEW` gates the organization staff page and own-bank users API.
- `system_dashboard` remains the revocable `SYSTEM_ADMIN` dashboard gate but is
  no longer delegable through the screen-permissions matrix or update API.
- `/bank/users` is removed; `/staff` is the organization staff surface.

## Existing database upgrade

Do not run `ScreenPermissionSeeder` on a customized live database: it deletes
all `screen_permissions` rows before applying defaults. Apply the following
transaction before deploying the renamed application code when the database
contains only the old analytics key:

```sql
START TRANSACTION;

UPDATE screens
SET `key` = 'org_analytics',
    `label` = 'تحليلات المنظمة',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `key` = 'bank_analytics';

INSERT INTO screens (`key`, `label`, `created_at`, `updated_at`)
VALUES ('staff', 'الموظفون', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`),
    `updated_at` = VALUES(`updated_at`);

INSERT IGNORE INTO screen_permissions
    (`role_id`, `screen_id`, `capability`, `created_at`, `updated_at`)
SELECT roles.id, screens.id, 'VIEW', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM roles
JOIN screens ON screens.`key` = 'staff'
WHERE roles.`code` = 'bank_admin';

COMMIT;
```
````

If both analytics keys already exist, merge grants into `org_analytics` and
delete the orphan instead:

```sql
START TRANSACTION;

INSERT IGNORE INTO screen_permissions
    (`role_id`, `screen_id`, `capability`, `created_at`, `updated_at`)
SELECT old_grants.role_id,
       new_screen.id,
       old_grants.capability,
       CURRENT_TIMESTAMP,
       CURRENT_TIMESTAMP
FROM screen_permissions AS old_grants
JOIN screens AS old_screen
  ON old_screen.id = old_grants.screen_id
 AND old_screen.`key` = 'bank_analytics'
JOIN screens AS new_screen
  ON new_screen.`key` = 'org_analytics';

DELETE old_grants
FROM screen_permissions AS old_grants
JOIN screens AS old_screen
  ON old_screen.id = old_grants.screen_id
WHERE old_screen.`key` = 'bank_analytics';

DELETE FROM screens WHERE `key` = 'bank_analytics';

INSERT INTO screens (`key`, `label`, `created_at`, `updated_at`)
VALUES ('staff', 'الموظفون', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`),
    `updated_at` = VALUES(`updated_at`);

INSERT IGNORE INTO screen_permissions
    (`role_id`, `screen_id`, `capability`, `created_at`, `updated_at`)
SELECT roles.id, screens.id, 'VIEW', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM roles
JOIN screens ON screens.`key` = 'staff'
WHERE roles.`code` = 'bank_admin';

COMMIT;
```

After either path, clear application caches:

```bash
php artisan cache:clear
```

Verify:

```sql
SELECT `key`, `label`
FROM screens
WHERE `key` IN ('bank_analytics', 'org_analytics', 'staff')
ORDER BY `key`;
```

Expected rows: `org_analytics` and `staff`; no `bank_analytics` row.

````

- [ ] **Step 3: Add the release note to the documentation index**

Under `## Release notes` in `docs/README.md`, add:

```markdown
- [`release-notes/screen-permissions-matrix-cleanup.md`](release-notes/screen-permissions-matrix-cleanup.md)
  — `org_analytics`, `staff:VIEW`, non-delegable `system_dashboard`, and live-database upgrade steps.
````

- [ ] **Step 4: Verify live docs contain no stale key**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
rg -n "bank_analytics" docs/architecture docs/frontend-guide.md docs/engine/extension-guide.md docs/testing-guide.md docs/release-notes/screen-permissions-matrix-cleanup.md
cd frontend
pnpm exec prettier ../docs/README.md ../docs/architecture/03-permission-model.md ../docs/architecture/04-dashboard-architecture.md ../docs/frontend-guide.md ../docs/engine/extension-guide.md ../docs/testing-guide.md ../docs/release-notes/screen-permissions-matrix-cleanup.md --check
```

Expected: `rg` returns only the intentional old-key migration references inside the release note; Prettier passes.

- [ ] **Step 5: Commit live documentation and upgrade instructions**

```bash
git add docs/README.md docs/architecture/03-permission-model.md docs/architecture/04-dashboard-architecture.md docs/frontend-guide.md docs/engine/extension-guide.md docs/testing-guide.md docs/release-notes/screen-permissions-matrix-cleanup.md
git commit -m "$(cat <<'EOF'
docs(settings): document screen permission cleanup

Align live authorization and dashboard docs with org_analytics, staff
delegation, system-dashboard protection, and safe database upgrade steps.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Run the cross-layer verification gate and browser proof

**Files:**

- No production files expected.
- Local-only generated graph changes under `graphify-out/` must remain unstaged.

**Interfaces:**

- Verifies: seeded catalog, matrix API, users API, both dashboard selectors, `/staff` route admission, and deleted `/bank/users` route.

- [ ] **Step 1: Run the focused backend gate**

```bash
cd backend
php artisan test tests/Feature/Permission/ScreenPermissionTest.php
php artisan test tests/Feature/Dashboard/DashboardFamilyCapabilityTest.php
php artisan test tests/Feature/Engine/EngineBankAdminRbacTest.php
composer format:check
```

Expected: all focused tests and backend formatting pass.

- [ ] **Step 2: Run the focused frontend gate**

```bash
cd ../frontend
pnpm exec vitest run app/tests/unit/pages/screen-permissions.test.ts app/tests/unit/composables/useScreenPermissionsAdmin.test.ts app/tests/unit/pages/DashboardPage.test.ts app/tests/unit/pages/StaffPage.test.ts app/tests/unit/pages/IdentityUsersPages.test.ts app/tests/unit/constants/nav-items.test.ts
pnpm exec eslint app/pages/admin/screen-permissions.vue app/pages/dashboard.vue app/pages/index.vue app/pages/staff.vue app/tests/unit/pages/screen-permissions.test.ts app/tests/unit/pages/DashboardPage.test.ts app/tests/unit/pages/StaffPage.test.ts app/tests/unit/pages/IdentityUsersPages.test.ts
pnpm exec prettier app/pages/admin/screen-permissions.vue app/pages/dashboard.vue app/pages/index.vue app/pages/staff.vue app/tests/unit/pages/screen-permissions.test.ts app/tests/unit/pages/DashboardPage.test.ts app/tests/unit/pages/StaffPage.test.ts app/tests/unit/pages/IdentityUsersPages.test.ts --check
```

Expected: focused Vitest, ESLint with zero warnings, and Prettier pass. Typecheck is not required because no shared TypeScript contract changes.

- [ ] **Step 3: Refresh semantic and graph indexes**

From the repository root, run SocratiCode incremental update and then:

```bash
graphify update .
```

Use SocratiCode `codebase_symbol` on `UserPolicy` and `DashboardStatsService`, then `codebase_impact` on their files to refresh the recorded blast radius. Laravel resolves `UserPolicy` through the service container, so verify its endpoint wiring through the focused `EngineBankAdminRbacTest` rather than assuming a static call edge exists. Do not stage `graphify-out/`.

- [ ] **Step 4: Verify the matrix in a real browser with Playwright CLI**

With the local frontend and backend running, use:

```bash
playwright-cli open
playwright-cli goto http://localhost:3000/login
```

Log in as the seeded system administrator, navigate to `/admin/screen-permissions`, and confirm:

1. No `system_dashboard` column is visible.
2. No `bank_analytics` column is visible.
3. `org_analytics` appears with the Arabic label `تحليلات المنظمة`.
4. `staff` appears with the Arabic label `الموظفون` and one VIEW switch.
5. Toggling `staff` for a banking role persists after refresh.

- [ ] **Step 5: Verify delegated staff route and backend denial boundaries**

Grant `staff:VIEW` to a non-`BANK_ADMIN` banking role through the matrix, log in as a user in that role, and confirm:

1. `/staff` renders instead of redirecting to `/forbidden`.
2. The table contains only users from the actor's bank.
3. Direct navigation to a different bank's user API resource returns 403.
4. Removing `staff:VIEW` redirects `/staff` to `/forbidden` and makes `GET /api/v1/users` return 403.

- [ ] **Step 6: Verify removed route and both dashboard families**

Using Playwright CLI and direct HTTP checks, confirm:

1. `/bank/users` returns Nuxt 404.
2. A bank administrator with `org_analytics:VIEW` gets `BankAdminDashboard`.
3. Revoking `org_analytics:VIEW` removes the analytics family.
4. A system administrator with `system_dashboard:VIEW` still gets `SystemAdminDashboard`.

Close the Playwright session:

```bash
playwright-cli close
```

- [ ] **Step 7: Confirm repository scope before handoff**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git -c core.fsmonitor=false status --short
git --no-pager log --oneline --decorate -7
```

Expected: only local `graphify-out/` changes may remain unstaged; the six task commits are present and signed. Do not commit `graphify-out/`.

---

## Self-Review Checklist

- [ ] `system_dashboard` remains seeded and runtime-revocable, but cannot be delegated through UI or direct API.
- [ ] Every live `bank_analytics` runtime/documentation reference is renamed to `org_analytics`; historical specs/plans remain untouched.
- [ ] `staff` is seeded VIEW-only and granted to `bank_admin` by default.
- [ ] Revoking `staff:VIEW` removes the non-system users API path; granting it to another banking role enables only own-bank eligible staff management.
- [ ] `/staff` uses screen middleware and `/admin/staff` remains unchanged.
- [ ] `/bank/users.vue` and its positive existence assertion are removed.
- [ ] `frontend/CLAUDE.md` documents `/staff` and `/admin/staff` accurately.
- [ ] Existing live databases have an explicit non-destructive upgrade path; production operators are warned not to run the destructive seeder over customized grants.
- [ ] Focused backend/frontend tests, lint, format, graph refresh, and Playwright browser checks are included.
- [ ] No task stages or commits `graphify-out/`.
