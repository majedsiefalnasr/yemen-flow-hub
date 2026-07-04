# Screen Permissions Simplification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Collapse `ScreenCapability` to VIEW/MANAGE/EXPORT, make Merchants/Reports/Audit the only delegable screens (Merchants forcing system_admin to VIEW+EXPORT only), and delete the legacy `permissions`/`role_permissions` slug system so every access check runs through `PermissionService::userHasCapability()` against `screen_permissions` — the table the Screen Permissions admin UI actually edits.

**Architecture:** Backend-only change (no new frontend logic, only a type/label shrink). One enum edit, one data migration, one schema-drop migration, one seeder rewrite, one service method removal + one guard addition, then a mechanical rewire of every policy/controller that currently calls `User::hasPermission()` to instead call `PermissionService::userHasCapability()` with the matching screen key and capability.

**Tech Stack:** Laravel 11, PHPUnit, MySQL migrations.

## Global Constraints

- Backend commit scope: `git add backend/<files>` from repo root, conventional commit `type(scope): description`, scope `backend`.
- All commits signed (no `--no-gpg-sign`).
- Frontend commit (Task 10) uses scope `frontend`.
- Per repo verification ladder: this is a cross-cutting refactor (15 policies + core service), so the final task runs the full backend suite (`php artisan test`) rather than per-file filters — every earlier task still uses the smallest relevant filter.
- No change to `stage_permissions`, `StagePermissionResolver`, or `requests` screen derivation (`derivedRequestsCapabilities()`) — different system, same-sounding name only.
- `ScreenCapability::cases()` already drives `RoleScreenPermissionController::update()`'s validation list — shrinking the enum automatically shrinks validation, no extra controller edit needed for that.

---

### Task 1: Shrink `ScreenCapability` enum and frontend type

**Files:**
- Modify: `backend/app/Enums/ScreenCapability.php`
- Modify: `frontend/app/types/models.ts:131`
- Test: `backend/tests/Feature/Permission/ScreenPermissionTest.php` (existing file, run only — no edits yet)

**Interfaces:**
- Produces: `ScreenCapability` enum with exactly 3 cases (`VIEW`, `MANAGE`, `EXPORT`), consumed by `RoleScreenPermissionController::update()`'s validation and every later task.

- [ ] **Step 1: Edit the backend enum**

```php
<?php

namespace App\Enums;

enum ScreenCapability: string
{
    case VIEW = 'VIEW';
    case MANAGE = 'MANAGE';
    case EXPORT = 'EXPORT';
}
```

- [ ] **Step 2: Edit the frontend type**

In `frontend/app/types/models.ts`, replace line 131:

```ts
export type ScreenCapability = 'VIEW' | 'MANAGE' | 'EXPORT'
```

- [ ] **Step 3: Run existing screen permission tests to see what breaks**

Run: `cd backend && php artisan test tests/Feature/Permission/ScreenPermissionTest.php`
Expected: Some tests may still pass (nothing yet inserts CREATE/UPDATE/DELETE), but this is a checkpoint — do not fix failures yet, later tasks address the seeder and data.

- [ ] **Step 4: Commit**

```bash
git add backend/app/Enums/ScreenCapability.php frontend/app/types/models.ts
git commit -m "$(cat <<'EOF'
refactor(backend): shrink ScreenCapability to VIEW/MANAGE/EXPORT

CREATE/UPDATE/DELETE never toggled independently anywhere in the UI
or policies -- they always traveled together as MANAGE. Collapsing
the enum is step one of unifying screen_permissions as the single
source of truth for access control.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Data migration — rewrite CREATE/UPDATE/DELETE grants to MANAGE

**Files:**
- Create: `backend/database/migrations/2026_07_05_000001_collapse_screen_permission_capabilities.php`
- Test: `backend/tests/Feature/Permission/ScreenPermissionCapabilityMigrationTest.php`

**Interfaces:**
- Consumes: `screen_permissions` table schema (`role_id`, `screen_id`, `capability` string column, unique on `(role_id, screen_id, capability)`) from `backend/database/migrations/2026_06_24_300001_create_screens_and_screen_permissions_tables.php`.
- Produces: every `screen_permissions` row has `capability` in `{VIEW, MANAGE, EXPORT}` after `up()`; no row has `(role_id, merchants_screen_id, 'MANAGE')` for the `system_admin` role.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permission;

use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScreenPermissionCapabilityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_update_delete_rows_collapse_to_manage_without_duplicate_violation(): void
    {
        $this->seed(GovernanceSeeder::class);

        $roleId = DB::table('roles')->where('code', 'bank_admin')->value('id');
        $screenId = DB::table('screens')->insertGetId([
            'key' => 'merchants_test_screen',
            'label' => 'Test Screen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed CREATE, UPDATE, DELETE, and an existing MANAGE row for the same
        // (role, screen) pair -- the migration must dedupe rather than violate
        // the unique(role_id, screen_id, capability) constraint.
        foreach (['CREATE', 'UPDATE', 'DELETE', 'MANAGE'] as $capability) {
            DB::table('screen_permissions')->insert([
                'role_id' => $roleId,
                'screen_id' => $screenId,
                'capability' => $capability,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_05_000001_collapse_screen_permission_capabilities.php', '--realpath' => false]);

        $capabilities = DB::table('screen_permissions')
            ->where('role_id', $roleId)
            ->where('screen_id', $screenId)
            ->pluck('capability')
            ->all();

        $this->assertSame(['MANAGE'], $capabilities);
    }

    public function test_system_admin_manage_on_merchants_is_stripped(): void
    {
        $this->seed(GovernanceSeeder::class);

        $systemAdminRoleId = DB::table('roles')->where('code', 'system_admin')->value('id');
        $merchantsScreenId = DB::table('screens')->insertGetId([
            'key' => 'merchants',
            'label' => 'Merchants',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('screen_permissions')->insert([
            ['role_id' => $systemAdminRoleId, 'screen_id' => $merchantsScreenId, 'capability' => 'VIEW', 'created_at' => now(), 'updated_at' => now()],
            ['role_id' => $systemAdminRoleId, 'screen_id' => $merchantsScreenId, 'capability' => 'MANAGE', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_05_000001_collapse_screen_permission_capabilities.php', '--realpath' => false]);

        $capabilities = DB::table('screen_permissions')
            ->where('role_id', $systemAdminRoleId)
            ->where('screen_id', $merchantsScreenId)
            ->pluck('capability')
            ->all();

        $this->assertSame(['VIEW'], $capabilities);
    }
}
```

Note: `screens.key` does not have to pre-exist as `'merchants'` for the first test — it uses an arbitrary screen key to test the generic rewrite. The second test specifically uses the `merchants` key to test the system_admin carve-out.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && php artisan test tests/Feature/Permission/ScreenPermissionCapabilityMigrationTest.php`
Expected: FAIL — migration file `2026_07_05_000001_collapse_screen_permission_capabilities.php` does not exist yet (the `artisan migrate --path=...` call errors because the path resolves to nothing, or the assertion fails since no rewrite happened).

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacyRows = DB::table('screen_permissions')
                ->whereIn('capability', ['CREATE', 'UPDATE', 'DELETE'])
                ->get(['id', 'role_id', 'screen_id']);

            foreach ($legacyRows as $row) {
                $manageExists = DB::table('screen_permissions')
                    ->where('role_id', $row->role_id)
                    ->where('screen_id', $row->screen_id)
                    ->where('capability', 'MANAGE')
                    ->exists();

                if ($manageExists) {
                    // A MANAGE row already covers this (role, screen) pair --
                    // delete the legacy row instead of rewriting it in place,
                    // since rewriting would violate the unique constraint.
                    DB::table('screen_permissions')->where('id', $row->id)->delete();
                } else {
                    DB::table('screen_permissions')->where('id', $row->id)->update([
                        'capability' => 'MANAGE',
                        'updated_at' => now(),
                    ]);
                    // Mark as claimed so a second legacy row for the same pair
                    // (e.g. both CREATE and UPDATE) gets deleted, not double-updated.
                    DB::table('screen_permissions')->where('id', $row->id)->exists();
                }
            }

            // Re-check for any remaining duplicate MANAGE rows created by the
            // loop above (e.g. CREATE and UPDATE for the same pair both trying
            // to become MANAGE) and delete extras, keeping the lowest id.
            $duplicates = DB::table('screen_permissions')
                ->select('role_id', 'screen_id')
                ->where('capability', 'MANAGE')
                ->groupBy('role_id', 'screen_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $dup) {
                $ids = DB::table('screen_permissions')
                    ->where('role_id', $dup->role_id)
                    ->where('screen_id', $dup->screen_id)
                    ->where('capability', 'MANAGE')
                    ->orderBy('id')
                    ->pluck('id');

                DB::table('screen_permissions')
                    ->whereIn('id', $ids->slice(1))
                    ->delete();
            }

            // system_admin never holds MANAGE on the merchants screen.
            $systemAdminRoleId = DB::table('roles')->where('code', 'system_admin')->value('id');
            $merchantsScreenId = DB::table('screens')->where('key', 'merchants')->value('id');

            if ($systemAdminRoleId !== null && $merchantsScreenId !== null) {
                DB::table('screen_permissions')
                    ->where('role_id', $systemAdminRoleId)
                    ->where('screen_id', $merchantsScreenId)
                    ->where('capability', 'MANAGE')
                    ->delete();
            }
        });
    }

    public function down(): void
    {
        // Lossy forward migration (CREATE/UPDATE/DELETE -> MANAGE is not
        // reversible) -- intentionally no-op, matching the design doc's
        // documented rollback strategy (revert the code deploy, leave data
        // migration applied).
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd backend && php artisan test tests/Feature/Permission/ScreenPermissionCapabilityMigrationTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_07_05_000001_collapse_screen_permission_capabilities.php backend/tests/Feature/Permission/ScreenPermissionCapabilityMigrationTest.php
git commit -m "$(cat <<'EOF'
feat(backend): migrate screen_permissions CREATE/UPDATE/DELETE to MANAGE

Rewrites existing screen_permissions rows to the collapsed 3-capability
model and strips any system_admin MANAGE grant on the merchants screen,
enforcing the carve-out at the data layer in addition to the code-level
guard added in a later task.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Rewrite `ScreenPermissionSeeder` for the 3-capability model

**Files:**
- Modify: `backend/database/seeders/ScreenPermissionSeeder.php`
- Modify: `backend/tests/Feature/Permission/ScreenPermissionTest.php:65-68` (screen count assertion — count is unchanged at 14, no edit actually needed, verify only)

**Interfaces:**
- Consumes: `ScreenCapability` enum from Task 1.
- Produces: seeded `screen_permissions` rows using only VIEW/MANAGE/EXPORT; `system_admin` role has `merchants => ['VIEW', 'EXPORT']` (no MANAGE).

- [ ] **Step 1: Edit the seeder's grants map**

In `backend/database/seeders/ScreenPermissionSeeder.php`, replace the `$grants` array (lines 37-102):

```php
        // Map governance role codes → screen capabilities.
        // Format: role_code => [ screen_key => [capabilities] ]
        $grants = [
            // ── Commercial Banks ──────────────────────────────────
            'intake' => [
                'merchants' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'internal_reviewer' => [
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'bank_admin' => [
                'merchants' => ['VIEW', 'MANAGE'],
                'users' => ['VIEW', 'MANAGE'],
                'reports' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'fx_swift' => [
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],

            // ── National Committee ────────────────────────────────
            'support' => [
                'audit' => ['VIEW'],
                'reports' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'committee_manager' => [
                'reports' => ['VIEW', 'EXPORT'],
                'audit' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'committee_director' => [
                'reports' => ['VIEW', 'EXPORT'],
                'audit' => ['VIEW', 'EXPORT'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'fx_confirm' => [
                'reports' => ['VIEW'],
                'audit' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],

            // ── System Administration ─────────────────────────────
            // system_admin is intentionally restricted on merchants: VIEW +
            // EXPORT only, never MANAGE. Enforced again in code
            // (PermissionService::userHasCapability) so it cannot be
            // bypassed by a future edit to this seeder.
            'system_admin' => [
                'organizations' => ['VIEW', 'MANAGE'],
                'teams' => ['VIEW', 'MANAGE'],
                'roles' => ['VIEW', 'MANAGE'],
                'banks' => ['VIEW', 'MANAGE'],
                'users' => ['VIEW', 'MANAGE'],
                'merchants' => ['VIEW', 'EXPORT'],
                'workflow_designer' => ['VIEW', 'MANAGE'],
                'reports' => ['VIEW', 'EXPORT'],
                'audit' => ['VIEW', 'EXPORT'],
                'reference_data' => ['VIEW', 'MANAGE'],
                'screen_permissions' => ['VIEW', 'MANAGE'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW', 'MANAGE'],
            ],
        ];
```

- [ ] **Step 2: Run the existing screen permission test suite**

Run: `cd backend && php artisan test tests/Feature/Permission/ScreenPermissionTest.php`
Expected: PASS — this suite re-seeds via `GovernanceSeeder`/`ScreenPermissionSeeder` in `setUp()`, so it exercises the rewritten seeder directly. `test_last_admin_manage_removal_blocked` and `test_manage_removal_allowed_when_another_role_has_it` still pass because `screen_permissions => ['VIEW','MANAGE']` for system_admin is unchanged.

- [ ] **Step 3: Commit**

```bash
git add backend/database/seeders/ScreenPermissionSeeder.php
git commit -m "$(cat <<'EOF'
refactor(backend): seed screen_permissions with VIEW/MANAGE/EXPORT only

Matches the collapsed ScreenCapability enum. system_admin's merchants
grant becomes VIEW+EXPORT (no MANAGE) per the product decision that
system_admin can inspect and export merchant data but not create,
edit, or delete merchants -- delegated roles like bank_admin can still
receive full MANAGE.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Update `RoleScreenPermissionController::SCREEN_CAPABILITIES` for all 3 delegable screens

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php:85-89`

**Interfaces:**
- Produces: `matrix()` endpoint's `screens[].capabilities` field returns `['VIEW','MANAGE','EXPORT']` for `merchants`, `reports`, and `audit` (the exact 3 delegable screens).

Note: this file already has a smaller in-progress fix from earlier in this session (`reports`/`audit` EXPORT added). This step finalizes it to match the full 3-screen, 3-capability catalog now that the enum itself is shrunk.

- [ ] **Step 1: Edit the const**

```php
    private const SCREEN_CAPABILITIES = [
        'merchants' => ['VIEW', 'MANAGE', 'EXPORT'],
        'reports' => ['VIEW', 'MANAGE', 'EXPORT'],
        'audit' => ['VIEW', 'MANAGE', 'EXPORT'],
    ];
```

- [ ] **Step 2: Run the screen permission test suite**

Run: `cd backend && php artisan test tests/Feature/Permission/ScreenPermissionTest.php`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php
git commit -m "$(cat <<'EOF'
fix(backend): expose MANAGE and EXPORT columns for all delegable screens

merchants, reports, and audit are the only three delegable screens;
each should offer all three capabilities as toggleable matrix columns
so a role can be granted MANAGE (create/update/delete) or EXPORT
consistently across the delegable set.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Add system_admin merchants carve-out guard in `PermissionService`

**Files:**
- Modify: `backend/app/Services/Authorization/PermissionService.php:197-202` (`userHasCapability`)
- Test: `backend/tests/Feature/Permission/ScreenPermissionTest.php` (add new test method)

**Interfaces:**
- Consumes: `Role` model's `code` column, `screenPermissionsForUser()` (existing method, unchanged signature).
- Produces: `userHasCapability($user, 'merchants', 'MANAGE')` returns `false` for any user whose governance role code is `system_admin`, even if a `screen_permissions` row somehow grants it.

- [ ] **Step 1: Write the failing test**

Add to `backend/tests/Feature/Permission/ScreenPermissionTest.php` (inside the `ScreenPermissionTest` class, after `test_matrix_excludes_system_admin_role`):

```php
    // ── Merchants MANAGE carve-out: system_admin is always denied ────────

    public function test_system_admin_never_has_merchants_manage_even_if_granted(): void
    {
        $merchantsScreenId = Screen::where('key', 'merchants')->value('id');

        // Force-grant MANAGE directly, bypassing the admin UI, to prove the
        // code-level guard holds even when the data says otherwise.
        ScreenPermission::create([
            'role_id' => $this->systemAdminRole->id,
            'screen_id' => $merchantsScreenId,
            'capability' => 'MANAGE',
        ]);

        app(PermissionService::class)->clearScreenPermissionCache($this->systemAdminRole->id);

        $this->assertFalse(
            app(PermissionService::class)->userHasCapability($this->admin, 'merchants', 'MANAGE')
        );
    }

    public function test_system_admin_keeps_merchants_view_and_export(): void
    {
        $this->assertTrue(
            app(PermissionService::class)->userHasCapability($this->admin, 'merchants', 'VIEW')
        );
        $this->assertTrue(
            app(PermissionService::class)->userHasCapability($this->admin, 'merchants', 'EXPORT')
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && php artisan test tests/Feature/Permission/ScreenPermissionTest.php --filter=test_system_admin_never_has_merchants_manage_even_if_granted`
Expected: FAIL — `userHasCapability` currently has no system_admin/merchants special case, so the forced MANAGE grant is honored and the assertion fails.

- [ ] **Step 3: Add the guard**

In `backend/app/Services/Authorization/PermissionService.php`, replace `userHasCapability()` (lines 197-202):

```php
    /**
     * Whether the user holds a specific capability on a specific screen,
     * derived from the data-driven screen_permissions catalog (never role codes).
     *
     * Exception: system_admin never holds MANAGE on the merchants screen,
     * even if a screen_permissions row grants it -- system_admin may
     * inspect and export merchant data but never create/update/delete it.
     * This is enforced here (not just by omission from seed data) so it
     * cannot be bypassed by a future manual grant.
     */
    public function userHasCapability(User $user, string $screenKey, string $capability): bool
    {
        if ($screenKey === 'merchants' && $capability === 'MANAGE' && $user->hasRoleCode('system_admin')) {
            return false;
        }

        $sp = $this->screenPermissionsForUser($user);

        return in_array($capability, $sp[$screenKey] ?? [], true);
    }
```

- [ ] **Step 4: Confirm `hasRoleCode` exists on `User`**

Run: `cd backend && grep -n "function hasRoleCode" app/Models/User.php`
Expected: a match (already used by `UserPolicy`/`BankPolicy` seen during design research — confirms the method exists with this exact name).

- [ ] **Step 5: Run test to verify it passes**

Run: `cd backend && php artisan test tests/Feature/Permission/ScreenPermissionTest.php --filter=test_system_admin`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the full screen permission test file**

Run: `cd backend && php artisan test tests/Feature/Permission/ScreenPermissionTest.php`
Expected: PASS (all tests, original + 2 new)

- [ ] **Step 7: Commit**

```bash
git add backend/app/Services/Authorization/PermissionService.php backend/tests/Feature/Permission/ScreenPermissionTest.php
git commit -m "$(cat <<'EOF'
feat(backend): enforce system_admin merchants MANAGE denial in code

Belt-and-suspenders alongside the seeder/migration: userHasCapability()
now denies MANAGE on the merchants screen for system_admin
unconditionally, so the restriction survives even a future manual
screen_permissions grant.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Rewire `MerchantPolicy` and `FinancingController` to `userHasCapability()`

**Files:**
- Modify: `backend/app/Policies/MerchantPolicy.php`
- Modify: `backend/app/Http/Controllers/Api/FinancingController.php:13`
- Test: `backend/tests/Feature/` — locate and extend existing merchant feature test

**Interfaces:**
- Consumes: `PermissionService::userHasCapability(User $user, string $screenKey, string $capability): bool` (existing method, now with the Task 5 guard).
- Produces: `MerchantPolicy::create/update/delete` gated by `userHasCapability($user, 'merchants', 'MANAGE')` instead of the legacy `$user->hasPermission('merchants.manage')`. `FinancingController::utilization` gated by `userHasCapability($user, 'requests', 'CREATE')` (the derived-capability vocabulary for the `requests` screen, unrelated to the VIEW/MANAGE/EXPORT manual-grant vocabulary — unchanged by this refactor per the design doc's non-goals).

- [ ] **Step 1: Find the existing Merchant feature test**

Run: `cd backend && grep -rl "MerchantController\|MerchantPolicy\|/api/v1/merchants" tests/Feature --include="*.php" -i`

Read whichever file(s) it returns to find the existing pattern for asserting 403/200 on merchant create/update/delete, matching this codebase's existing test conventions (role setup, `actingAs`, etc.) — reuse that setup rather than inventing a new one.

- [ ] **Step 2: Write a failing test proving screen_permissions now controls Merchant access**

Add to the merchant feature test file found in Step 1 (adapt role/user setup to match what that file already does — the shape below assumes a bank_admin-role user without MANAGE granted):

```php
    public function test_merchant_create_denied_when_role_lacks_merchants_manage(): void
    {
        $role = \App\Models\Role::where('code', 'bank_admin')->firstOrFail();
        \App\Models\ScreenPermission::where('role_id', $role->id)
            ->whereHas('screen', fn ($q) => $q->where('key', 'merchants'))
            ->delete();
        app(\App\Services\Authorization\PermissionService::class)->clearScreenPermissionCache($role->id);

        $user = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::BANK_ADMIN]);
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->postJson('/api/v1/merchants', [
                'name' => 'Test Merchant',
                'tax_number' => '999999999',
                'bank_id' => $user->bank_id,
            ])
            ->assertForbidden();
    }

    public function test_merchant_create_allowed_when_role_has_merchants_manage(): void
    {
        $role = \App\Models\Role::where('code', 'bank_admin')->firstOrFail();
        $screenId = \App\Models\Screen::where('key', 'merchants')->value('id');
        \App\Models\ScreenPermission::firstOrCreate([
            'role_id' => $role->id,
            'screen_id' => $screenId,
            'capability' => 'MANAGE',
        ]);
        app(\App\Services\Authorization\PermissionService::class)->clearScreenPermissionCache($role->id);

        $user = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::BANK_ADMIN]);
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->postJson('/api/v1/merchants', [
                'name' => 'Test Merchant 2',
                'tax_number' => '888888888',
                'bank_id' => $user->bank_id,
            ])
            ->assertCreated();
    }
```

Adapt factory calls (`User::factory()`, field names on the merchant payload) to match whatever the existing test file in this suite already uses — check its `store`-endpoint test for the exact required fields before finalizing this step's code.

- [ ] **Step 3: Run test to verify it fails**

Run: `cd backend && php artisan test --filter=test_merchant_create_denied_when_role_lacks_merchants_manage`
Expected: FAIL — today `MerchantPolicy::create()` checks the legacy `merchants.manage` slug, which is unaffected by deleting the `screen_permissions` row, so the request would still succeed (assertForbidden fails).

- [ ] **Step 4: Rewire `MerchantPolicy`**

```php
<?php

namespace App\Policies;

use App\Models\Merchant;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class MerchantPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Merchant $merchant): bool
    {
        return ! $user->isBankUser() || $user->bank_id === $merchant->bank_id;
    }

    public function create(User $user): bool
    {
        return $this->permissionService->userHasCapability($user, 'merchants', 'MANAGE');
    }

    public function update(User $user, Merchant $merchant): bool
    {
        return $this->permissionService->userHasCapability($user, 'merchants', 'MANAGE')
            && (! $user->isBankUser() || $user->bank_id === $merchant->bank_id);
    }

    public function delete(User $user, Merchant $merchant): bool
    {
        return $this->update($user, $merchant);
    }
}
```

- [ ] **Step 5: Rewire `FinancingController`**

In `backend/app/Http/Controllers/Api/FinancingController.php`, replace line 13 and add the constructor:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Services\Authorization\PermissionService;
use App\Services\Workflow\Engine\EngineFinancingLedger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class FinancingController extends Controller
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function utilization(Request $request, EngineFinancingLedger $financingLedgerService)
    {
        abort_unless(
            $request->user() && $this->permissionService->userHasCapability($request->user(), 'requests', 'CREATE'),
            403
        );

        $validated = $request->validate([
            'tax_number' => ['required', 'string', 'max:255'],
            'invoice_number' => ['required', 'string', 'max:255'],
            'exclude_request_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $excludeRequestId = $validated['exclude_request_id'] ?? null;
        $taxNumber = $validated['tax_number'];
        $invoiceNumber = $validated['invoice_number'];

        $usedPercent = $financingLedgerService->usedPercent($taxNumber, $invoiceNumber, $excludeRequestId);
        $remainingPercent = $financingLedgerService->remainingPercent($taxNumber, $invoiceNumber, $excludeRequestId);

        return ApiResponse::success([
            'used_percent' => $usedPercent,
            'remaining_percent' => $remainingPercent,
            'blocked' => $remainingPercent <= 0,
        ], 'Financing utilization retrieved successfully.');
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `cd backend && php artisan test --filter=test_merchant_create`
Expected: PASS (both new tests)

Run: `cd backend && php artisan test tests/Feature/Permission/ScreenPermissionTest.php`
Expected: PASS (no regression)

- [ ] **Step 7: Run any existing FinancingController test**

Run: `cd backend && grep -rl "FinancingController\|financing/utilization\|request.create" tests/Feature --include="*.php" -i`

If a test file is found, run it: `cd backend && php artisan test <path>`
Expected: PASS. If it fails because it relied on the legacy `request.create` permission slug being seeded a specific way, update its setup to grant `requests` screen `CREATE` capability via the derived-capability path instead (i.e. ensure the test role has an `EXECUTE` stage assignment on the initial workflow stage, matching how `derivedRequestsCapabilities()` computes `add`).

- [ ] **Step 8: Commit**

```bash
git add backend/app/Policies/MerchantPolicy.php backend/app/Http/Controllers/Api/FinancingController.php backend/tests/Feature
git commit -m "$(cat <<'EOF'
fix(backend): wire MerchantPolicy and FinancingController to screen_permissions

Both previously checked the legacy permissions/role_permissions slug
system (merchants.manage, request.create), completely disconnected
from the screen_permissions table the admin UI edits. Toggling
Merchants MANAGE in the Screen Permissions page now actually controls
create/update/delete access, closing the gap that motivated this
whole redesign.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Rewire Audit and Reports (`AuditLogPolicy`, `ReportController`, `ReportExportController`)

**Files:**
- Modify: `backend/app/Policies/AuditLogPolicy.php`
- Modify: `backend/app/Http/Controllers/Api/V1/ReportController.php` (14 `Gate::authorize('reports.view')` call sites)
- Modify: `backend/app/Http/Controllers/Api/V1/ReportExportController.php` (4 `Gate::authorize('reports.view')` call sites)
- Test: locate existing Report/Audit feature tests

**Interfaces:**
- Consumes: `PermissionService::userHasCapability()`.
- Produces: `AuditLogPolicy::viewAny/view` gated by `userHasCapability($user, 'audit', 'VIEW')`. Every `reports.view` gate site gated by `userHasCapability($user, 'reports', 'VIEW')`.

- [ ] **Step 1: Find existing Report and Audit feature tests**

Run: `cd backend && grep -rl "ReportController\|ReportExportController\|AuditLogPolicy\|audit-logs" tests/Feature --include="*.php" -i`

Read the returned files to learn the existing role/user setup pattern for these endpoints.

- [ ] **Step 2: Write a failing test for Audit**

Add to the audit test file found in Step 1 (or create `backend/tests/Feature/Permission/AuditScreenPermissionTest.php` if none exists, following the `ScreenPermissionTest.php` setup pattern for seeding `GovernanceSeeder` + `ScreenPermissionSeeder` and constructing a role-attached user):

```php
<?php

namespace Tests\Feature\Permission;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Screen;
use App\Models\ScreenPermission;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditScreenPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_index_denied_without_audit_view_capability(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(ScreenPermissionSeeder::class);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $intakeRole = Role::where('code', 'intake')->firstOrFail();

        $user = User::create([
            'name' => 'Entry',
            'email' => 'entry@test.bank',
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $user->roles()->attach($intakeRole->id);

        // intake role has no audit grant per the seeder.
        $this->actingAs($user)
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_audit_log_index_allowed_with_audit_view_capability(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(ScreenPermissionSeeder::class);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $supportOrg = Organization::where('code', 'national_committee')->first() ?? $bankOrg;
        $supportRole = Role::where('code', 'support')->firstOrFail();

        $user = User::create([
            'name' => 'Support',
            'email' => 'support@test.cby',
            'password' => bcrypt('password'),
            'role' => UserRole::SUPPORT_COMMITTEE,
            'organization_id' => $supportOrg->id,
            'is_active' => true,
        ]);
        $user->roles()->attach($supportRole->id);

        // support role has audit:VIEW per the seeder.
        $this->actingAs($user)
            ->getJson('/api/audit-logs')
            ->assertOk();
    }
}
```

Adjust the organization code lookups (`commercial_banks`, `national_committee`) to match whatever `GovernanceSeeder` actually creates — verify with `grep -n "Organization::" backend/database/seeders/GovernanceSeeder.php` before finalizing, since this plan's author has not re-read that seeder in full.

- [ ] **Step 3: Run test to verify it fails**

Run: `cd backend && php artisan test tests/Feature/Permission/AuditScreenPermissionTest.php`
Expected: Both tests currently pass or fail unpredictably depending on the legacy `permissions` table's seeded state for `audit.view` — the point of this step is to confirm the test harness works before rewiring; if both already pass because the legacy seeder happens to grant the same effective access, that's fine, proceed to Step 4 and re-run after to prove the new path is what's actually being exercised (Step 6 will delete the legacy tables entirely, which is the real proof).

- [ ] **Step 4: Rewire `AuditLogPolicy`**

```php
<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class AuditLogPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return $this->permissionService->userHasCapability($user, 'audit', 'VIEW');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->permissionService->userHasCapability($user, 'audit', 'VIEW');
    }
}
```

- [ ] **Step 5: Rewire `ReportController` and `ReportExportController`**

Run: `cd backend && grep -c "Gate::authorize('reports.view')" app/Http/Controllers/Api/V1/ReportController.php app/Http/Controllers/Api/V1/ReportExportController.php`
Expected: `ReportController.php:14`, `ReportExportController.php:4`

In both files:
1. Add `use App\Services\Authorization\PermissionService;` to the imports.
2. Add a constructor `public function __construct(private readonly PermissionService $permissionService) {}` (if a constructor already exists in either file, add the property/promotion to it instead — check first with `grep -n "__construct" app/Http/Controllers/Api/V1/ReportController.php app/Http/Controllers/Api/V1/ReportExportController.php`).
3. Replace every occurrence of `Gate::authorize('reports.view');` with:
   ```php
   abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);
   ```
   Confirm the enclosing method has a `Request $request` parameter in scope at each call site (`grep -n "public function" app/Http/Controllers/Api/V1/ReportController.php` to list all 14 method signatures first) — if a method's parameter is named differently, use that name instead of `$request`.
4. Remove the now-unused `use Illuminate\Support\Facades\Gate;` import if no other `Gate::` call remains in the file (`grep -n "Gate::" app/Http/Controllers/Api/V1/ReportController.php app/Http/Controllers/Api/V1/ReportExportController.php` to confirm zero remaining matches before removing the import).

- [ ] **Step 6: Run test to verify it passes**

Run: `cd backend && php artisan test tests/Feature/Permission/AuditScreenPermissionTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Run existing Report/Audit tests found in Step 1**

Run: `cd backend && php artisan test <each file found in Step 1>`
Expected: PASS. Fix any failure caused by a test relying on the legacy `reports.view`/`audit.view` slugs being seeded a specific way — update that test's role setup to grant the equivalent `screen_permissions` row instead.

- [ ] **Step 8: Commit**

```bash
git add backend/app/Policies/AuditLogPolicy.php backend/app/Http/Controllers/Api/V1/ReportController.php backend/app/Http/Controllers/Api/V1/ReportExportController.php backend/tests/Feature/Permission/AuditScreenPermissionTest.php
git commit -m "$(cat <<'EOF'
fix(backend): wire AuditLogPolicy and report controllers to screen_permissions

Same disconnect as Merchants: audit.view and reports.view were legacy
permission slugs, unaffected by the Screen Permissions admin UI.
Both screens are read-only today (no create/update/delete routes),
so only the VIEW capability check is live at the enforcement layer.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Rewire the 12 system-admin-only policies (Roles/Teams/Organizations/Workflow-Designer/Reference-Data)

**Files:**
- Modify: `backend/app/Policies/RolePolicy.php`
- Modify: `backend/app/Policies/TeamPolicy.php`
- Modify: `backend/app/Policies/OrganizationPolicy.php`
- Modify: `backend/app/Policies/WorkflowDefinitionPolicy.php`
- Modify: `backend/app/Policies/WorkflowVersionPolicy.php`
- Modify: `backend/app/Policies/WorkflowStagePolicy.php`
- Modify: `backend/app/Policies/WorkflowActionPolicy.php`
- Modify: `backend/app/Policies/WorkflowTransitionPolicy.php`
- Modify: `backend/app/Policies/StagePermissionPolicy.php`
- Modify: `backend/app/Policies/FieldGroupPolicy.php`
- Modify: `backend/app/Policies/FieldDefinitionPolicy.php`
- Modify: `backend/app/Policies/StageFieldRulePolicy.php`
- Modify: `backend/app/Policies/ReferenceValuePolicy.php`
- Modify: `backend/app/Policies/ReferenceTablePolicy.php`
- Modify: `backend/app/Http/Controllers/Api/DocumentTypeController.php` (3 call sites)

**Interfaces:**
- Consumes: `PermissionService::userHasCapability()`.
- Produces: every `viewAny()` in this group gated by `userHasCapability($user, '<screen>', 'MANAGE')` per the mapping table below; `create/update/delete` methods (which all currently just call `$this->viewAny($user)`) are untouched — they already delegate correctly and need no edit once `viewAny()` is fixed.

Mapping (verified during design — every legacy slug in this group maps to exactly one system-admin-only screen):

| Policy | Legacy slug | New screen key |
|---|---|---|
| RolePolicy, TeamPolicy | `roles.manage` | `roles` |
| OrganizationPolicy | `roles.manage` | `roles` |
| WorkflowDefinitionPolicy, WorkflowVersionPolicy, WorkflowStagePolicy, WorkflowActionPolicy, WorkflowTransitionPolicy, StagePermissionPolicy, FieldGroupPolicy, FieldDefinitionPolicy, StageFieldRulePolicy | `workflow.design` | `workflow_designer` |
| ReferenceValuePolicy, ReferenceTablePolicy | `docrules.manage` | `reference_data` |

All checks use capability `MANAGE`.

- [ ] **Step 1: Edit `RolePolicy`**

```php
<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class RolePolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && $this->permissionService->userHasCapability($user, 'roles', 'MANAGE');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }
}
```

- [ ] **Step 2: Edit `TeamPolicy`** (identical shape to RolePolicy, screen `roles`)

Read `backend/app/Policies/TeamPolicy.php` first to get its exact current method signatures (model type is `Team`, not `Role`), then apply the same transformation: constructor-inject `PermissionService`, replace `$user->hasPermission('roles.manage')` in `viewAny()` with `$this->permissionService->userHasCapability($user, 'roles', 'MANAGE')`, leave `create/update/delete` as `$this->viewAny($user)` unchanged.

- [ ] **Step 3: Edit `OrganizationPolicy`** (screen `roles`, but has a `before()` hook — preserve it)

```php
<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class OrganizationPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $this->permissionService->userHasCapability($user, 'roles', 'MANAGE');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->viewAny($user);
    }
}
```

- [ ] **Step 4: Edit the 9 workflow-designer policies** (screen `workflow_designer`)

For each of `WorkflowDefinitionPolicy`, `WorkflowVersionPolicy`, `WorkflowStagePolicy`, `WorkflowActionPolicy`, `WorkflowTransitionPolicy`, `StagePermissionPolicy`, `FieldGroupPolicy`, `FieldDefinitionPolicy`, `StageFieldRulePolicy`:

1. Read the file first (`WorkflowVersionPolicy` and `StageFieldRulePolicy` have extra methods beyond the standard 4 — `publish`/`archive`/`clone` on Version, no `update` on StageFieldRule — preserve every existing method, only change the body of `viewAny()`).
2. Add `use App\Services\Authorization\PermissionService;` import.
3. Add constructor: `public function __construct(private readonly PermissionService $permissionService) {}`.
4. In `viewAny()`, replace `$user->is_active && $user->hasPermission('workflow.design')` with `$user->is_active && $this->permissionService->userHasCapability($user, 'workflow_designer', 'MANAGE')`.
5. Leave every other method body unchanged (they already call `$this->viewAny($user)` or, for `WorkflowVersionPolicy::publish/archive/clone`, whatever their existing logic is — do not touch logic you weren't asked to change).

- [ ] **Step 5: Edit `ReferenceValuePolicy` and `ReferenceTablePolicy`** (screen `reference_data`)

Same transformation: `viewAny()`'s `$user->is_active && $user->hasPermission('docrules.manage')` becomes `$user->is_active && $this->permissionService->userHasCapability($user, 'reference_data', 'MANAGE')`.

- [ ] **Step 6: Edit `DocumentTypeController`**

In `backend/app/Http/Controllers/Api/DocumentTypeController.php`, add the import and constructor, then replace all 3 occurrences:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreDocumentTypeRequest;
use App\Http\Requests\UpdateDocumentTypeRequest;
use App\Http\Resources\DocumentTypeResource;
use App\Models\DocumentType;
use App\Services\Authorization\PermissionService;
use App\Support\ApiResponse;
use OpenApi\Attributes as OA;

class DocumentTypeController extends Controller
{
    public function __construct(private readonly PermissionService $permissionService) {}

    // ... index() unchanged ...

    public function store(StoreDocumentTypeRequest $request)
    {
        if (! $this->permissionService->userHasCapability($request->user(), 'reference_data', 'MANAGE')) {
            return ApiResponse::forbidden();
        }

        $row = DocumentType::query()->create($request->validated());

        return ApiResponse::success(new DocumentTypeResource($row), 'Document type created successfully.', 201);
    }

    public function update(UpdateDocumentTypeRequest $request, DocumentType $documentType)
    {
        if (! $this->permissionService->userHasCapability($request->user(), 'reference_data', 'MANAGE')) {
            return ApiResponse::forbidden();
        }
        $documentType->update($request->validated());

        return ApiResponse::success(new DocumentTypeResource($documentType->refresh()), 'Document type updated successfully.');
    }

    public function destroy(DocumentType $documentType)
    {
        if (! $this->permissionService->userHasCapability(request()->user(), 'reference_data', 'MANAGE')) {
            return ApiResponse::forbidden();
        }
        $documentType->delete();

        return ApiResponse::success((object) [], 'Document type deleted successfully.');
    }
}
```

Keep the existing OpenAPI attribute blocks on each method exactly as they are in the current file — only the body logic changes, shown above without the attributes for brevity; copy them back in from the original file read during Task planning research.

- [ ] **Step 7: Run each policy's existing feature test**

Run: `cd backend && grep -rl "RolePolicy\|TeamPolicy\|OrganizationPolicy\|WorkflowDefinitionPolicy\|WorkflowVersionPolicy\|WorkflowStagePolicy\|WorkflowActionPolicy\|WorkflowTransitionPolicy\|StagePermissionPolicy\|FieldGroupPolicy\|FieldDefinitionPolicy\|StageFieldRulePolicy\|ReferenceValuePolicy\|ReferenceTablePolicy\|DocumentTypeController" tests/Feature --include="*.php" -il`

Run each returned file: `cd backend && php artisan test <path>`
Expected: PASS for tests exercising `system_admin` (still has `MANAGE` on `roles`/`workflow_designer`/`reference_data` per the Task 3 seeder). Any test using a non-system_admin role to exercise these admin-only endpoints should already expect 403 — since these screens are system-admin-only and no other role is ever granted them, this should be a no-op for that class of test. Fix any test that hardcoded a legacy permission slug's seed/mock instead of a role check.

- [ ] **Step 8: Commit**

```bash
git add backend/app/Policies/RolePolicy.php backend/app/Policies/TeamPolicy.php backend/app/Policies/OrganizationPolicy.php backend/app/Policies/WorkflowDefinitionPolicy.php backend/app/Policies/WorkflowVersionPolicy.php backend/app/Policies/WorkflowStagePolicy.php backend/app/Policies/WorkflowActionPolicy.php backend/app/Policies/WorkflowTransitionPolicy.php backend/app/Policies/StagePermissionPolicy.php backend/app/Policies/FieldGroupPolicy.php backend/app/Policies/FieldDefinitionPolicy.php backend/app/Policies/StageFieldRulePolicy.php backend/app/Policies/ReferenceValuePolicy.php backend/app/Policies/ReferenceTablePolicy.php backend/app/Http/Controllers/Api/DocumentTypeController.php
git commit -m "$(cat <<'EOF'
fix(backend): wire remaining system-admin-only policies to screen_permissions

Roles, Teams, Organizations (roles screen), all 9 workflow-designer
policies (workflow_designer screen), and ReferenceValue/ReferenceTable/
DocumentType (reference_data screen) all checked legacy permission
slugs. These screens stay system_admin-exclusive either way, but
routing through userHasCapability() removes the last consumers of the
legacy permissions system ahead of deleting it.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 9: Delete the legacy permission system

**Files:**
- Delete: `backend/app/Models/Permission.php`
- Delete: `backend/database/seeders/PermissionSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php:10-11` (remove the `PermissionSeeder` call)
- Create: `backend/database/migrations/2026_07_05_000002_drop_legacy_permission_tables.php`
- Modify: `backend/app/Providers/AuthServiceProvider.php` (remove `Gate::before` hook)
- Modify: `backend/app/Models/User.php:163-166` (remove `hasPermission()`)
- Modify: `backend/app/Services/Authorization/PermissionService.php` (remove dead methods)

**Interfaces:**
- Produces: no code references `App\Models\Permission`, `permissions` table, `role_permissions` table, `User::hasPermission()`, `PermissionService::userCan()`, `PermissionService::permissionsForRole()`, `PermissionService::legacyScreenPermissionsForUser()`, `PermissionService::rolesForPermission()`, `PermissionService::clearRoleCache()`, or `PermissionService::SCREEN_MAP`.

- [ ] **Step 1: Confirm no remaining call sites**

Run: `cd backend && grep -rn "hasPermission(\|::userCan(\|permissionsForRole(\|legacyScreenPermissionsForUser(\|rolesForPermission(\|clearRoleCache(\|App\\\\Models\\\\Permission\b" app database/seeders --include="*.php"`
Expected: no output except the definitions themselves inside `User.php` and `PermissionService.php` (about to be deleted in this task). If any other call site appears, stop and rewire it before proceeding — it means an earlier task's audit missed a consumer.

- [ ] **Step 2: Write the schema-drop migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }

    public function down(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('group')->index();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role', 'permission_id']);
            $table->index('role');
        });
    }
};
```

- [ ] **Step 3: Delete the `Permission` model**

Run: `rm backend/app/Models/Permission.php`

- [ ] **Step 4: Delete the `PermissionSeeder`**

Run: `rm backend/database/seeders/PermissionSeeder.php`

- [ ] **Step 5: Remove the seeder call from `DatabaseSeeder`**

In `backend/database/seeders/DatabaseSeeder.php`, remove these two lines:

```php
        $this->call([PermissionSeeder::class]);
        $this->command?->info('Seeded permissions matrix.');
```

(They are the first two lines inside `run()`, per the file read during planning — the next line, `$this->call([DocumentTypeSeeder::class]);`, becomes the new first line.)

- [ ] **Step 6: Remove the `Gate::before` hook**

In `backend/app/Providers/AuthServiceProvider.php`, remove:

```php
        Gate::before(function ($user, string $ability) {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return app(PermissionService::class)->userCan($user, $ability);
        });
```

And remove the now-unused imports `use App\Services\Authorization\PermissionService;` (confirm nothing else in this file uses `PermissionService` before removing — per the file read during planning, this hook is its only use).

- [ ] **Step 7: Remove `User::hasPermission()`**

In `backend/app/Models/User.php`, remove:

```php
    public function hasPermission(string $slug): bool
    {
        return app(PermissionService::class)->userCan($this, $slug);
    }
```

Check whether `PermissionService` is still imported/used elsewhere in `User.php` after this removal (`grep -n "PermissionService" app/Models/User.php`) and remove the import if it's now unused.

- [ ] **Step 8: Remove dead methods from `PermissionService`**

In `backend/app/Services/Authorization/PermissionService.php`, remove: `SCREEN_MAP` constant, `userCan()`, `permissionsForRole()`, `rolesForPermission()`, `clearRoleCache()`, `legacyScreenPermissionsForUser()`. Also simplify `screenPermissionsForUser()` since its fallback branch called the now-deleted `legacyScreenPermissionsForUser()`:

```php
    /**
     * Build screen → capabilities map from screen_permissions table (governance roles).
     */
    public function screenPermissionsForUser(User $user): array
    {
        $governanceRole = $user->role();

        if ($governanceRole === null) {
            return [];
        }

        return $this->screenPermissionsForGovernanceRole($governanceRole->id);
    }
```

Remove the now-unused `use App\Models\Permission;` import if present, and `use Illuminate\Support\Collection;` if `permissionsForRole()` was its only consumer (`grep -n "Collection" app/Services/Authorization/PermissionService.php` to check).

- [ ] **Step 9: Run the full backend test suite**

Run: `cd backend && php artisan test`
Expected: PASS (or only pre-existing known-red failures unrelated to this change — per the repo's verification ladder, report the baseline if any unrelated failure exists rather than treating it as a blocker). This is the "broad refactor" case explicitly justifying a full-suite run instead of per-file filters.

- [ ] **Step 10: Commit**

```bash
git add backend/app/Models/Permission.php backend/database/seeders/PermissionSeeder.php backend/database/seeders/DatabaseSeeder.php backend/database/migrations/2026_07_05_000002_drop_legacy_permission_tables.php backend/app/Providers/AuthServiceProvider.php backend/app/Models/User.php backend/app/Services/Authorization/PermissionService.php
git commit -m "$(cat <<'EOF'
refactor(backend): remove legacy permissions/role_permissions system

Every real consumer (15 policies, 2 report controllers, Merchant,
DocumentType, Financing) now checks screen_permissions via
PermissionService::userHasCapability(), so the slug-based permissions
table, its Gate::before() global hook, and the dead PermissionService
methods that only served it are removed. Screen Permissions is now
the single source of truth for non-workflow access control end to end.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 10: Frontend label cleanup

**Files:**
- Modify: `frontend/app/pages/admin/screen-permissions.vue:28-35`

**Interfaces:**
- Produces: `CAP_LABELS` const contains only `VIEW`, `MANAGE`, `EXPORT` keys — matches the shrunk `ScreenCapability` type from Task 1, which already removed the possibility of `CREATE`/`UPDATE`/`DELETE` ever appearing in a `MatrixScreen.capabilities` array.

- [ ] **Step 1: Edit `CAP_LABELS`**

In `frontend/app/pages/admin/screen-permissions.vue`, replace lines 28-35:

```ts
const CAP_LABELS: Record<string, string> = {
  VIEW: 'عرض',
  MANAGE: 'إدارة',
  EXPORT: 'تصدير',
}
```

- [ ] **Step 2: Run frontend typecheck**

Run: `cd frontend && pnpm typecheck`
Expected: PASS — this touches a type-driven constant, matching the verification ladder's rule to run typecheck for changes touching shared interfaces (the `ScreenCapability` type from Task 1 already changed; this step's own edit is just the label map).

- [ ] **Step 3: Run the existing admin screen-permissions Vitest file if one exists**

Run: `cd frontend && find app/tests -iname "*screen-permission*"`

If found, run: `cd frontend && pnpm exec vitest run <path>`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add frontend/app/pages/admin/screen-permissions.vue
git commit -m "$(cat <<'EOF'
refactor(frontend): trim screen permission capability labels to 3

CAP_LABELS no longer needs CREATE/UPDATE/DELETE entries now that the
backend ScreenCapability enum only emits VIEW/MANAGE/EXPORT.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 11: Verify the admin UI end-to-end in a browser

**Files:** none (manual/browser verification only)

**Interfaces:** none — this task exercises the full stack built in Tasks 1-10.

- [ ] **Step 1: Start the backend and frontend dev servers** (or confirm they're already running per this project's `run` skill)

- [ ] **Step 2: Use `playwright-cli` to log in as a system_admin/CBY_ADMIN user and open `/admin/screen-permissions`**

```bash
playwright-cli open
playwright-cli goto http://localhost:3000/login
playwright-cli snapshot
# fill in credentials, submit, then:
playwright-cli goto http://localhost:3000/admin/screen-permissions
playwright-cli snapshot
```

- [ ] **Step 3: Confirm the matrix shows exactly 3 delegable screen column groups** (Merchants, Reports/التقارير, Audit/سجل التدقيق), each with 3 sub-columns (عرض/إدارة/تصدير)

- [ ] **Step 4: Toggle Merchants MANAGE on for a role that doesn't have it (e.g. `intake`), confirm the switch persists after a page reload**

```bash
playwright-cli click <switch-element-id>
playwright-cli goto http://localhost:3000/admin/screen-permissions
playwright-cli snapshot
```

Confirm the toggled switch is still on.

- [ ] **Step 5: Confirm Reports and Audit both show their EXPORT switch already on for `committee_director`** (per the Task 3 seeder), closing the loop on the original bug report that started this whole redesign.

- [ ] **Step 6: Close the browser session**

```bash
playwright-cli close
```

No commit for this task — it's verification only, confirming Tasks 1-10 work together as a system.

---

## Self-Review Notes

- **Spec coverage:** All 5 numbered sections of the design doc map to tasks — capabilities (Task 1), screen categories/matrix (Task 4), merchants special rule (Tasks 3 + 5 + 6), requests unchanged (verified as a non-goal, touched only incidentally in Task 6's `FinancingController` fix which uses the untouched derived-capability vocabulary), legacy removal (Tasks 6-9).
- **Type consistency:** `userHasCapability(User $user, string $screenKey, string $capability): bool` signature (defined pre-existing, unchanged) is used identically across Tasks 5-8. Screen key strings (`merchants`, `reports`, `audit`, `roles`, `workflow_designer`, `reference_data`) match the seeder keys from Task 3 and the existing `Screen` catalog confirmed during design research.
- **Known plan risk:** Tasks 6-8 reference "read the existing test file first" rather than showing exact pre-existing test code, because those files weren't fully read during planning (only grepped for existence). This is intentional — forcing the implementer to verify against the real file rather than trusting a guessed reproduction — but it means those steps carry slightly more judgment than a fully-specified step. Flagged rather than hidden.
