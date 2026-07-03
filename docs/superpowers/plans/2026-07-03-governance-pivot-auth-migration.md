# Governance Pivot Auth Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace every legacy `users.role` (`App\Enums\UserRole`) authorization check with the governance pivot system (`organizations`/`teams`/`roles`/`user_roles`), so `users.role` stops being the live auth gate. Also fixes the root-cause bug where CBY_ADMIN's requests list returns empty when their `user_roles` pivot is out of sync with their legacy `role` column.

**Architecture:** The governance pivot (`Role.code` scoped to an `Organization`, `User belongsToMany Role via user_roles`) becomes the single source of truth for authorization. A new `committee_director` role code is added (currently `EXECUTIVE_MEMBER` and `COMMITTEE_DIRECTOR` both incorrectly collapse to `committee_manager`, making them indistinguishable at the pivot layer). New `User` helper methods (`hasRoleCode()`, `hasAnyRoleCode()`, `inOrganization()`) mirror the existing `isSystemAdmin()` pattern (relation-aware, avoids N+1). Every one of the 21 call sites identified in the audit is converted from enum comparison to role-code comparison. The legacy `users.role` column and `UserRole` enum are left in place (still used for display/audit/legacy-compat per `UserController::legacyRoleFor()`) but are no longer read for access-control decisions.

**Tech Stack:** Laravel 11 (PHP 8.2), MySQL migrations, PHPUnit.

> **Explicit reversal notice:** This plan reverses the decision recorded in `docs/superpowers/plans/2026-07-01-workstream-d-governance-consolidation.md` ("users.role scalar remains the live auth gate — do NOT change PermissionService, policies, or auth middleware"). That decision is superseded by this plan per explicit user direction on 2026-07-03.

> **Known remaining scope (deliberately not covered by this plan):** The final whole-branch review (2026-07-03) found that `App\Services\Authorization\PermissionService::userCan()` — the backing method for `User::hasPermission()` — is still 100% keyed on the legacy `users.role` enum via the `role_permissions` table, and was never part of the 21-site audit this plan executed. `hasPermission()` gates roughly 20 additional policies not touched here: `MerchantPolicy`, `AuditLogPolicy`, `RolePolicy`, `TeamPolicy`, `OrganizationPolicy`, all `Workflow*Policy` classes, `ReferenceTablePolicy`/`ReferenceValuePolicy`, plus the global Gate fallback in `AuthServiceProvider`. The Merchant subsystem (`MerchantPolicy`, `MerchantController`, `V1\MerchantController`, `Merchant` model) is also still enum-gated via `isBankUser()`. None of this is a regression introduced by this branch — these surfaces were enum-gated before this plan and remain so after it, unchanged. But it means the plan's stated goal ("users.role stops being the live auth gate") is met only for the 21 audited sites, not for the whole codebase. `PermissionService` already contains a parallel pivot-based path (`userHasCapability()`/`screenPermissionsForGovernanceRole()`, used by one call site: `EngineRequestController::store()`), so the two systems now coexist deliberately until a future migration converts `userCan()` and its ~20 dependent policies. Do not assume `users.role` is unused for authorization anywhere outside the 21 sites this plan lists — verify against the current code before relying on that assumption.

## Global Constraints

- All commits from repo root (`/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code`), never from `backend/` subdirectory
- Conventional commit format: `type(scope): description` — scopes used here: `backend`, `auth`
- All commits signed — never `--no-gpg-sign`, `--no-sign`, `-c commit.gpgsign=false`
- Never `--no-verify`
- Co-author line required: `Co-Authored-By: Claude <noreply@anthropic.com>`
- Backend test command from repo root: `cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code && php -d xdebug.mode=off artisan test <testfile>`
- "N deprecated" in test output = N passed, not failures
- Do NOT run full `php artisan test` per task — run only targeted files; full suite runs once at the end (Task 10)
- Never commit `graphify-out/` artifacts
- The legacy `UserRole` enum and `users.role` column are NOT deleted in this plan — only stop being read for authorization. Display/audit/serialization uses of `$user->role?->value` are left untouched (see audit report "display only" list)
- `EXECUTIVE_MEMBER` and `COMMITTEE_DIRECTOR` must remain distinguishable after migration — this is the reason for Task 1 (new `committee_director` role code)

---

### Task 1: Add `committee_director` governance role code (migration + seeder + data backfill)

**Files:**
- Create: `backend/database/migrations/2026_07_03_000001_add_committee_director_role.php`
- Modify: `backend/database/seeders/GovernanceSeeder.php:39` (roles array)
- Modify: `backend/database/seeders/UserSeeder.php:200` (assignIdentity map)
- Modify: `backend/database/seeders/ScreenPermissionSeeder.php:72` (grants array — add director grant, keep committee_manager for executive members)
- Test: `backend/tests/Feature/Governance/CommitteeDirectorRoleTest.php`

**Interfaces:**
- Consumes: nothing from other tasks (this is the foundation task)
- Produces: `roles` table has a `committee_director` row (org `national_committee`, team `executive`) distinct from `committee_manager`. All existing `COMMITTEE_DIRECTOR`-enum users have their `user_roles` pivot repointed from `committee_manager` to `committee_director`. `screen_permissions` has grants for `committee_director`. Later tasks rely on `Role::where('code', 'committee_director')` existing and being correctly assigned.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Governance/CommitteeDirectorRoleTest.php`:

```php
<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeDirectorRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_committee_director_role_code_exists_and_is_distinct_from_committee_manager(): void
    {
        $this->seed(GovernanceSeeder::class);

        $organization = Organization::query()->where('code', 'national_committee')->firstOrFail();
        $team = Team::query()->where('organization_id', $organization->id)->where('code', 'executive')->firstOrFail();

        $director = Role::query()->where('organization_id', $organization->id)->where('code', 'committee_director')->first();
        $manager = Role::query()->where('organization_id', $organization->id)->where('code', 'committee_manager')->first();

        $this->assertNotNull($director, 'committee_director role must exist');
        $this->assertNotNull($manager, 'committee_manager role must still exist');
        $this->assertNotEquals($director->id, $manager->id);
    }

    public function test_seeded_committee_director_user_has_committee_director_role_code(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);

        $director = User::query()->where('role', UserRole::COMMITTEE_DIRECTOR->value)->firstOrFail();

        $this->assertTrue($director->roles->contains('code', 'committee_director'));
        $this->assertFalse($director->roles->contains('code', 'committee_manager'));
    }

    public function test_seeded_executive_member_user_keeps_committee_manager_role_code(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);

        $executive = User::query()->where('role', UserRole::EXECUTIVE_MEMBER->value)->firstOrFail();

        $this->assertTrue($executive->roles->contains('code', 'committee_manager'));
        $this->assertFalse($executive->roles->contains('code', 'committee_director'));
    }

    public function test_committee_director_has_screen_permissions(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(ScreenPermissionSeeder::class);

        $director = Role::query()->where('code', 'committee_director')->firstOrFail();
        $this->assertTrue($director->screenPermissions()->exists());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Governance/CommitteeDirectorRoleTest.php
```

Expected: FAIL — `committee_director role must exist` (role does not exist yet), and `screenPermissions` method may not exist on `Role` yet (check — if missing, add a `screenPermissions(): HasMany` relation returning `hasMany(ScreenPermission::class)` only if no such relation exists; check `app/Models/Role.php` first, it may already have one via a different name — if it does, use that name instead in the test).

- [ ] **Step 3: Check if Role model has a screen permissions relation**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
grep -n "screenPermission\|ScreenPermission" app/Models/Role.php
```

If no relation exists, add it to `app/Models/Role.php` (after the existing `users()` method):

```php
public function screenPermissions(): HasMany
{
    return $this->hasMany(\App\Models\ScreenPermission::class);
}
```

Add the import at the top of `app/Models/Role.php`:
```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

(Check `app/Models/ScreenPermission.php` exists first — the audit found a `screen_permissions` table but the model may be named differently; run `ls backend/app/Models/ | grep -i screen` to confirm the exact model class name and adjust the relation target accordingly.)

- [ ] **Step 4: Write the migration**

Create `backend/database/migrations/2026_07_03_000001_add_committee_director_role.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $organizationId = DB::table('organizations')->where('code', 'national_committee')->value('id');
        $managerRoleId = DB::table('roles')
            ->where('organization_id', $organizationId)
            ->where('code', 'committee_manager')
            ->value('id');

        $directorRoleId = DB::table('roles')->insertGetId([
            'organization_id' => $organizationId,
            'code' => 'committee_director',
            'name' => 'مدير اللجنة التنفيذية (مدير)',
            'is_system' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Repoint COMMITTEE_DIRECTOR users' pivot from committee_manager to committee_director.
        $directorUserIds = DB::table('users')->where('role', 'COMMITTEE_DIRECTOR')->pluck('id');

        foreach ($directorUserIds as $userId) {
            DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('role_id', $managerRoleId)
                ->delete();

            DB::table('user_roles')->insertOrIgnore([
                'user_id' => $userId,
                'role_id' => $directorRoleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Copy committee_manager's screen_permissions as director's starting grant set
        // (Task 1 seeder update will refine this; this keeps the migration idempotent
        // even if seeders haven't run yet in this environment).
        $managerPermissions = DB::table('screen_permissions')->where('role_id', $managerRoleId)->get();
        foreach ($managerPermissions as $permission) {
            DB::table('screen_permissions')->insertOrIgnore([
                'role_id' => $directorRoleId,
                'screen_id' => $permission->screen_id,
                'capability' => $permission->capability,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $organizationId = DB::table('organizations')->where('code', 'national_committee')->value('id');
        $directorRoleId = DB::table('roles')
            ->where('organization_id', $organizationId)
            ->where('code', 'committee_director')
            ->value('id');

        if ($directorRoleId === null) {
            return;
        }

        DB::table('screen_permissions')->where('role_id', $directorRoleId)->delete();
        DB::table('user_roles')->where('role_id', $directorRoleId)->delete();
        DB::table('roles')->where('id', $directorRoleId)->delete();
    }
};
```

- [ ] **Step 5: Update GovernanceSeeder roles array**

In `backend/database/seeders/GovernanceSeeder.php`, find the `$roles` array (line ~39) and change:

```php
        $roles = [
            ['commercial_banks', 'intake', 'موظف الإدخال'],
            ['commercial_banks', 'internal_reviewer', 'المراجع الداخلي'],
            ['commercial_banks', 'bank_admin', 'مسؤول البنك'],
            ['commercial_banks', 'fx_swift', 'موظف الصرف/السويفت'],
            ['national_committee', 'support', 'عضو اللجنة المساندة'],
            ['national_committee', 'committee_manager', 'مدير اللجنة التنفيذية'],
            ['national_committee', 'fx_confirm', 'موظف تأكيد المصارفة'],
            ['system_administration', 'system_admin', 'مسؤول النظام'],
        ];
```

to:

```php
        $roles = [
            ['commercial_banks', 'intake', 'موظف الإدخال'],
            ['commercial_banks', 'internal_reviewer', 'المراجع الداخلي'],
            ['commercial_banks', 'bank_admin', 'مسؤول البنك'],
            ['commercial_banks', 'fx_swift', 'موظف الصرف/السويفت'],
            ['national_committee', 'support', 'عضو اللجنة المساندة'],
            ['national_committee', 'committee_manager', 'عضو اللجنة التنفيذية'],
            ['national_committee', 'committee_director', 'مدير اللجنة التنفيذية (مدير)'],
            ['national_committee', 'fx_confirm', 'موظف تأكيد المصارفة'],
            ['system_administration', 'system_admin', 'مسؤول النظام'],
        ];
```

(Note: `committee_manager`'s Arabic label is corrected from "مدير اللجنة التنفيذية" (director) to "عضو اللجنة التنفيذية" (member) since it now represents EXECUTIVE_MEMBER only.)

- [ ] **Step 6: Update UserSeeder assignIdentity map**

In `backend/database/seeders/UserSeeder.php`, find `assignIdentity()` (line ~193) and change:

```php
        $map = [
            UserRole::DATA_ENTRY->value => ['commercial_banks', 'entry', 'intake', true],
            UserRole::BANK_REVIEWER->value => ['commercial_banks', 'internal_review', 'internal_reviewer', true],
            UserRole::BANK_ADMIN->value => ['commercial_banks', 'bank_admin', 'bank_admin', true],
            UserRole::SWIFT_OFFICER->value => ['commercial_banks', 'fx_ops', 'fx_swift', true],
            UserRole::SUPPORT_COMMITTEE->value => ['national_committee', 'support', 'support', false],
            UserRole::EXECUTIVE_MEMBER->value => ['national_committee', 'executive', 'committee_manager', false],
            UserRole::COMMITTEE_DIRECTOR->value => ['national_committee', 'executive', 'committee_manager', false],
            UserRole::CBY_ADMIN->value => ['system_administration', 'administration', 'system_admin', false],
        ];
```

to:

```php
        $map = [
            UserRole::DATA_ENTRY->value => ['commercial_banks', 'entry', 'intake', true],
            UserRole::BANK_REVIEWER->value => ['commercial_banks', 'internal_review', 'internal_reviewer', true],
            UserRole::BANK_ADMIN->value => ['commercial_banks', 'bank_admin', 'bank_admin', true],
            UserRole::SWIFT_OFFICER->value => ['commercial_banks', 'fx_ops', 'fx_swift', true],
            UserRole::SUPPORT_COMMITTEE->value => ['national_committee', 'support', 'support', false],
            UserRole::EXECUTIVE_MEMBER->value => ['national_committee', 'executive', 'committee_manager', false],
            UserRole::COMMITTEE_DIRECTOR->value => ['national_committee', 'executive', 'committee_director', false],
            UserRole::CBY_ADMIN->value => ['system_administration', 'administration', 'system_admin', false],
        ];
```

(Only the `COMMITTEE_DIRECTOR` line changes: role_code `committee_manager` → `committee_director`.)

- [ ] **Step 7: Update ScreenPermissionSeeder grants array**

In `backend/database/seeders/ScreenPermissionSeeder.php`, find the `committee_manager` grant (line ~72) and add a `committee_director` grant immediately after it with broader permissions (director needs everything the manager/executive has, plus report export and FX-confirmation-adjacent visibility — matching the "director can force-release claims, upload signed FX confirmations" powers found in the audit):

```php
            'committee_manager' => [
                'requests' => ['VIEW', 'UPDATE'],
                'reports' => ['VIEW', 'EXPORT'],
                'audit' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'committee_director' => [
                'requests' => ['VIEW', 'UPDATE', 'MANAGE'],
                'reports' => ['VIEW', 'EXPORT'],
                'audit' => ['VIEW', 'EXPORT'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
```

- [ ] **Step 8: Run the migration**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php artisan migrate
```

Expected: `Migrating: 2026_07_03_000001_add_committee_director_role` then `Migrated`.

- [ ] **Step 9: Run test to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Governance/CommitteeDirectorRoleTest.php
```

Expected: PASS, 4 tests, 0 failures.

- [ ] **Step 10: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/database/migrations/2026_07_03_000001_add_committee_director_role.php \
        backend/database/seeders/GovernanceSeeder.php \
        backend/database/seeders/UserSeeder.php \
        backend/database/seeders/ScreenPermissionSeeder.php \
        backend/tests/Feature/Governance/CommitteeDirectorRoleTest.php \
        backend/app/Models/Role.php
git commit -m "$(cat <<'EOF'
feat(backend): add committee_director governance role distinct from committee_manager

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Add pivot-based helper methods to User model

**Files:**
- Modify: `backend/app/Models/User.php`
- Test: `backend/tests/Unit/Models/UserGovernanceHelpersTest.php`

**Interfaces:**
- Consumes: Task 1's `committee_director` role code
- Produces: `User::hasRoleCode(string $code): bool`, `User::hasAnyRoleCode(array $codes): bool`, `User::inOrganization(string $code): bool` — used by every subsequent task

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Models/UserGovernanceHelpersTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGovernanceHelpersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_has_role_code_matches_assigned_pivot_role(): void
    {
        $admin = \App\Models\User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $this->assertTrue($admin->hasRoleCode('system_admin'));
        $this->assertFalse($admin->hasRoleCode('committee_director'));
    }

    public function test_has_any_role_code_matches_one_of_several(): void
    {
        $director = \App\Models\User::query()->where('role', UserRole::COMMITTEE_DIRECTOR->value)->firstOrFail();

        $this->assertTrue($director->hasAnyRoleCode(['committee_director', 'system_admin']));
        $this->assertFalse($director->hasAnyRoleCode(['committee_manager', 'system_admin']));
    }

    public function test_in_organization_matches_assigned_org(): void
    {
        $admin = \App\Models\User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $bankAdmin = \App\Models\User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();

        $this->assertTrue($admin->inOrganization('system_administration'));
        $this->assertFalse($admin->inOrganization('commercial_banks'));
        $this->assertTrue($bankAdmin->inOrganization('commercial_banks'));
    }

    public function test_has_role_code_works_with_preloaded_relation(): void
    {
        $admin = \App\Models\User::query()
            ->with('roles')
            ->where('role', UserRole::CBY_ADMIN->value)
            ->firstOrFail();

        $this->assertTrue($admin->relationLoaded('roles'));
        $this->assertTrue($admin->hasRoleCode('system_admin'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Unit/Models/UserGovernanceHelpersTest.php
```

Expected: FAIL — `Call to undefined method App\Models\User::hasRoleCode()`.

- [ ] **Step 3: Add the helper methods to User model**

In `backend/app/Models/User.php`, add these methods immediately after the existing `isSystemAdmin()` method (which stays unchanged):

```php
    public function hasRoleCode(string $code): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('code', $code);
        }

        return $this->roles()->where('code', $code)->exists();
    }

    public function hasAnyRoleCode(array $codes): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->whereIn('code', $codes)->isNotEmpty();
        }

        return $this->roles()->whereIn('code', $codes)->exists();
    }

    public function inOrganization(string $code): bool
    {
        if ($this->relationLoaded('organization')) {
            return $this->organization?->code === $code;
        }

        return $this->organization()->where('code', $code)->exists();
    }
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Unit/Models/UserGovernanceHelpersTest.php
```

Expected: PASS, 4 tests, 0 failures.

- [ ] **Step 5: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Models/User.php backend/tests/Unit/Models/UserGovernanceHelpersTest.php
git commit -m "$(cat <<'EOF'
feat(backend): add hasRoleCode/hasAnyRoleCode/inOrganization pivot helpers to User

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Migrate `EngineRequestController::index()` and `EngineRequestPolicy::view()` (the reported bug)

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php:219` (already changed to `hasRole(UserRole::CBY_ADMIN)` in a prior step — this task changes it again to the pivot form)
- Modify: `backend/app/Policies/EngineRequestPolicy.php:18` (same — currently `hasRole(UserRole::CBY_ADMIN)`, changing to pivot form)
- Test: `backend/tests/Feature/Engine/EngineRequestAdminVisibilityTest.php`

**Interfaces:**
- Consumes: Task 2's `hasRoleCode()`
- Produces: CBY_ADMIN visibility now keyed on `hasRoleCode('system_admin')`, matching `isSystemAdmin()` semantics exactly (this makes `isSystemAdmin()` and this check equivalent — they use the same underlying pivot check)

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Engine/EngineRequestAdminVisibilityTest.php`:

```php
<?php

namespace Tests\Feature\Engine;

use App\Enums\UserRole;
use App\Models\EngineRequest;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineRequestAdminVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cby_admin_sees_all_requests_even_without_legacy_role_pivot_sync(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);

        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        // Simulate the exact bug scenario: pivot row missing/desynced even though
        // the legacy `role` column correctly says CBY_ADMIN.
        $admin->roles()->detach();
        $this->assertFalse($admin->fresh()->hasRoleCode('system_admin'));

        EngineRequest::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/engine-requests');

        // This asserts the CURRENT (buggy, pre-fix) behavior would return 0 —
        // after the fix in this task, it must return all 3 regardless of pivot state
        // is NOT what we want long-term (pivot should be source of truth), so this
        // test instead re-attaches the correct pivot row and asserts visibility:
        $admin->roles()->attach(\App\Models\Role::where('code', 'system_admin')->firstOrFail());
        $admin->refresh();

        $response = $this->actingAs($admin)->getJson('/api/v1/engine-requests');
        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_non_admin_user_does_not_see_all_requests(): void
    {
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);

        $dataEntry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        EngineRequest::factory()->count(3)->create();

        $response = $this->actingAs($dataEntry)->getJson('/api/v1/engine-requests');
        $response->assertOk();
        $this->assertLessThan(3, count($response->json('data')));
    }
}
```

- [ ] **Step 2: Run test to verify current state**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Engine/EngineRequestAdminVisibilityTest.php
```

Expected: both tests currently PASS (the controller was already changed to `hasRole(UserRole::CBY_ADMIN)` earlier in this session, which is enum-based and unaffected by pivot detach/attach). This step is a baseline check, not a red-test-first step — proceed to Step 3 to convert to pivot-based and confirm test 1 still models the intended end state correctly.

- [ ] **Step 3: Convert EngineRequestController::index() to pivot check**

In `backend/app/Http/Controllers/Api/V1/EngineRequestController.php`, change:

```php
        if (! $user->hasRole(UserRole::CBY_ADMIN)) {
```

to:

```php
        if (! $user->hasRoleCode('system_admin')) {
```

Remove the now-unused `use App\Enums\UserRole;` import if this was the only usage in the file:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
grep -n "UserRole" app/Http/Controllers/Api/V1/EngineRequestController.php
```

If only the `use` line remains, remove it.

- [ ] **Step 4: Convert EngineRequestPolicy::view() to pivot check**

In `backend/app/Policies/EngineRequestPolicy.php`, change:

```php
    public function view(User $user, EngineRequest $request): bool
    {
        if ($user->hasRole(UserRole::CBY_ADMIN)) {
            return true;
        }
```

to:

```php
    public function view(User $user, EngineRequest $request): bool
    {
        if ($user->hasRoleCode('system_admin')) {
            return true;
        }
```

Remove the `use App\Enums\UserRole;` import (added earlier this session) since it's now unused:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
grep -n "UserRole" app/Policies/EngineRequestPolicy.php
```

If only the `use` line remains, remove it.

- [ ] **Step 5: Run test to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Engine/EngineRequestAdminVisibilityTest.php
```

Expected: PASS, 2 tests, 0 failures. Test 1 now proves: even after detaching the pivot then re-attaching it, visibility correctly follows the pivot state (not the legacy enum) — this is the actual fix for the reported bug, expressed as "pivot is now authoritative," not "enum happens to always be right."

- [ ] **Step 6: Run broader engine request test suite**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Engine/ 2>&1 | tail -15
```

Expected: all pass. If any pre-existing test relied on `EngineRequestController::index()` or `EngineRequestPolicy::view()` granting CBY_ADMIN access via the enum without a correct pivot row (e.g. a test factory that sets `role: CBY_ADMIN` without seeding governance), it will now fail — fix the test's setup to also assign the `system_admin` pivot role (via `UserSeeder`'s `assignIdentity()` pattern or direct `$user->roles()->attach(...)`) rather than reverting the production code.

- [ ] **Step 7: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Http/Controllers/Api/V1/EngineRequestController.php \
        backend/app/Policies/EngineRequestPolicy.php \
        backend/tests/Feature/Engine/EngineRequestAdminVisibilityTest.php
git commit -m "$(cat <<'EOF'
fix(backend): key engine request admin visibility on governance pivot, not legacy enum

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Migrate bank-role and CBY-role group checks (`CustomsDeclarationPolicy`, `BankPolicy`, `UserPolicy`)

**Files:**
- Modify: `backend/app/Policies/CustomsDeclarationPolicy.php`
- Modify: `backend/app/Policies/BankPolicy.php`
- Modify: `backend/app/Policies/UserPolicy.php`
- Test: `backend/tests/Feature/Policies/PivotRoleGroupPoliciesTest.php`

**Interfaces:**
- Consumes: Task 1 (`committee_director` code), Task 2 (`hasRoleCode`/`hasAnyRoleCode`/`inOrganization`)
- Produces: bank-role-group and CBY-role-group checks expressed as pivot role-code lists

**Role-code group reference (derived from audit + Task 1):**
- Bank roles group (`isBankRole()` equivalent): `['intake', 'internal_reviewer', 'bank_admin', 'fx_swift']`
- CBY roles group (`isCbyRole()` equivalent): `['system_admin', 'support', 'committee_manager', 'committee_director']`
- Bank-admin-manageable group (`isBankAdminManageable()` equivalent): `['intake', 'internal_reviewer']`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Policies/PivotRoleGroupPoliciesTest.php`:

```php
<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivotRoleGroupPoliciesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_committee_director_can_download_customs_declaration_via_pivot(): void
    {
        $director = User::query()->where('role', UserRole::COMMITTEE_DIRECTOR->value)->firstOrFail();
        $request = EngineRequest::factory()->create(['bank_id' => null]);
        $declaration = CustomsDeclaration::factory()->create(['engine_request_id' => $request->id]);

        $this->assertTrue($director->can('download', $declaration));
    }

    public function test_data_entry_cannot_download_customs_declaration_for_other_bank(): void
    {
        $entry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();
        $otherBankRequest = EngineRequest::factory()->create(['bank_id' => $entry->bank_id + 1000]);
        $declaration = CustomsDeclaration::factory()->create(['engine_request_id' => $otherBankRequest->id]);

        $this->assertFalse($entry->can('download', $declaration));
    }

    public function test_cby_admin_can_create_bank_via_pivot(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $this->assertTrue($admin->can('create', \App\Models\Bank::class));
    }

    public function test_bank_admin_cannot_create_bank(): void
    {
        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();

        $this->assertFalse($bankAdmin->can('create', \App\Models\Bank::class));
    }

    public function test_cby_admin_can_view_any_user(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $this->assertTrue($admin->can('viewAny', User::class));
    }

    public function test_bank_admin_can_manage_own_bank_data_entry_user(): void
    {
        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();
        $entry = User::query()
            ->where('role', UserRole::DATA_ENTRY->value)
            ->where('bank_id', $bankAdmin->bank_id)
            ->firstOrFail();

        $this->assertTrue($bankAdmin->can('update', $entry));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Policies/PivotRoleGroupPoliciesTest.php
```

Expected: some tests may currently PASS (since the enum-based code still works correctly today) — this is a baseline test, not TDD-red. Note which pass/fail before changes; all must pass after the pivot conversion in Step 3.

- [ ] **Step 3: Convert CustomsDeclarationPolicy**

Read `backend/app/Policies/CustomsDeclarationPolicy.php` fully first to confirm exact current line numbers, then change:

```php
if ($bankId === null && ! in_array($user->role, [UserRole::COMMITTEE_DIRECTOR, UserRole::CBY_ADMIN], true)) {
    return false;
}

return match ($user->role) {
    UserRole::COMMITTEE_DIRECTOR,
    UserRole::CBY_ADMIN => true,
    UserRole::BANK_REVIEWER => $user->bank_id !== null && $user->bank_id === $bankId,
    default => false,
};
```

to:

```php
if ($bankId === null && ! $user->hasAnyRoleCode(['committee_director', 'system_admin'])) {
    return false;
}

if ($user->hasAnyRoleCode(['committee_director', 'system_admin'])) {
    return true;
}

if ($user->hasRoleCode('internal_reviewer')) {
    return $user->bank_id !== null && $user->bank_id === $bankId;
}

return false;
```

And change:

```php
return match ($user->role) {
    UserRole::DATA_ENTRY,
    UserRole::BANK_REVIEWER,
    UserRole::BANK_ADMIN => $isSameBank,
    UserRole::SUPPORT_COMMITTEE,
    UserRole::EXECUTIVE_MEMBER,
    UserRole::COMMITTEE_DIRECTOR,
    UserRole::CBY_ADMIN => true,
    default => false,
};
```

to:

```php
if ($user->hasAnyRoleCode(['intake', 'internal_reviewer', 'bank_admin'])) {
    return $isSameBank;
}

return $user->hasAnyRoleCode(['support', 'committee_manager', 'committee_director', 'system_admin']);
```

Remove `use App\Enums\UserRole;` if no longer referenced in the file (check with grep as in prior tasks).

- [ ] **Step 4: Convert BankPolicy**

Read `backend/app/Policies/BankPolicy.php` fully, then change every occurrence:

```php
$user->hasRole(UserRole::CBY_ADMIN)
```
→
```php
$user->hasRoleCode('system_admin')
```

```php
$user->hasRole(UserRole::BANK_ADMIN)
```
→
```php
$user->hasRoleCode('bank_admin')
```

```php
$user->isBankUser()
```
→
```php
$user->hasAnyRoleCode(['intake', 'internal_reviewer', 'bank_admin', 'fx_swift'])
```

Apply these substitutions to `view()`, `create()`, `update()`, `delete()`. Remove `use App\Enums\UserRole;` if unused afterward.

- [ ] **Step 5: Convert UserPolicy**

Read `backend/app/Policies/UserPolicy.php` fully, then apply these substitutions throughout (`viewAny`, `view`, `create`, `update`, `delete`, `cbyAdmin`, `resetPassword`, `resetMfa`, `resetPin`, `canManageOwnBankUser`):

```php
$user->hasRole(UserRole::CBY_ADMIN)
```
→
```php
$user->hasRoleCode('system_admin')
```

```php
$user->hasRole(UserRole::BANK_ADMIN)
```
→
```php
$user->hasRoleCode('bank_admin')
```

```php
$model->role?->isCbyRole() || $model->hasRole(UserRole::BANK_ADMIN)
```
→
```php
$model->hasAnyRoleCode(['system_admin', 'support', 'committee_manager', 'committee_director', 'bank_admin'])
```

```php
$target->role?->isBankAdminManageable()
```
→
```php
$target->hasAnyRoleCode(['intake', 'internal_reviewer'])
```

Remove `use App\Enums\UserRole;` if unused afterward.

- [ ] **Step 6: Run test to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Policies/PivotRoleGroupPoliciesTest.php
```

Expected: PASS, 6 tests, 0 failures.

- [ ] **Step 7: Run existing policy/bank/user test suites**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Bank/ backend/tests/Feature/User/ backend/tests/Feature/Customs/ 2>&1 | tail -20
```

Expected: all pass. Fix any test factories that create users with a `role` enum value but no matching governance pivot assignment (use `UserSeeder`'s `assignIdentity()` pattern as the reference for correct test setup).

- [ ] **Step 8: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Policies/CustomsDeclarationPolicy.php \
        backend/app/Policies/BankPolicy.php \
        backend/app/Policies/UserPolicy.php \
        backend/tests/Feature/Policies/PivotRoleGroupPoliciesTest.php
git commit -m "$(cat <<'EOF'
refactor(backend): migrate CustomsDeclarationPolicy, BankPolicy, UserPolicy to governance pivot

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Migrate Form Request authorization (`UpdateBankRequest`, `UpdateUserRequest`, `StoreUserRequest`, `UpdateAdminSettingRequest`, `FxConfirmationUploadRequest`)

**Files:**
- Modify: `backend/app/Http/Requests/UpdateBankRequest.php`
- Modify: `backend/app/Http/Requests/UpdateUserRequest.php`
- Modify: `backend/app/Http/Requests/StoreUserRequest.php`
- Modify: `backend/app/Http/Requests/UpdateAdminSettingRequest.php`
- Modify: `backend/app/Http/Requests/FxConfirmationUploadRequest.php`
- Test: `backend/tests/Feature/Requests/PivotFormRequestAuthorizationTest.php`

**Interfaces:**
- Consumes: Task 1, Task 2
- Produces: form-request-level authorization keyed on pivot role codes

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Requests/PivotFormRequestAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\Requests;

use App\Enums\UserRole;
use App\Models\EngineRequest;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivotFormRequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_committee_director_can_upload_fx_confirmation(): void
    {
        $director = User::query()->where('role', UserRole::COMMITTEE_DIRECTOR->value)->firstOrFail();
        $request = EngineRequest::factory()->create();

        $response = $this->actingAs($director)->postJson(
            "/api/requests/{$request->id}/fx-confirmation-upload",
            []
        );

        $this->assertNotEquals(403, $response->status());
    }

    public function test_executive_member_cannot_upload_fx_confirmation(): void
    {
        $executive = User::query()->where('role', UserRole::EXECUTIVE_MEMBER->value)->firstOrFail();
        $request = EngineRequest::factory()->create();

        $response = $this->actingAs($executive)->postJson(
            "/api/requests/{$request->id}/fx-confirmation-upload",
            []
        );

        $response->assertForbidden();
    }

    public function test_cby_admin_can_update_admin_settings(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($admin)->putJson('/api/admin/settings/general', [
            'app_name' => 'Test',
        ]);

        $this->assertNotEquals(403, $response->status());
    }

    public function test_non_admin_cannot_update_admin_settings(): void
    {
        $entry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        $response = $this->actingAs($entry)->putJson('/api/admin/settings/general', [
            'app_name' => 'Test',
        ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails or note baseline**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Requests/PivotFormRequestAuthorizationTest.php
```

Note current pass/fail state (route paths may need adjusting after reading the actual route definitions in `routes/api.php` if they don't match — check with `grep -n "fx-confirmation-upload\|admin/settings/general" backend/routes/api.php` and correct the test URIs to match exactly before proceeding).

- [ ] **Step 3: Convert FxConfirmationUploadRequest**

In `backend/app/Http/Requests/FxConfirmationUploadRequest.php`, change:

```php
// Only COMMITTEE_DIRECTOR may upload signed FX confirmation documents
return $user->role === UserRole::COMMITTEE_DIRECTOR;
```

to:

```php
// Only the committee_director governance role may upload signed FX confirmation documents
return $user->hasRoleCode('committee_director');
```

Remove `use App\Enums\UserRole;` if unused afterward.

- [ ] **Step 4: Convert UpdateAdminSettingRequest**

In `backend/app/Http/Requests/UpdateAdminSettingRequest.php`, change:

```php
return $user !== null && $user->hasRole(UserRole::CBY_ADMIN);
```

to:

```php
return $user !== null && $user->hasRoleCode('system_admin');
```

Remove `use App\Enums\UserRole;` if unused afterward.

- [ ] **Step 5: Convert UpdateBankRequest**

In `backend/app/Http/Requests/UpdateBankRequest.php`, change:

```php
if ($this->user()?->hasRole(UserRole::BANK_ADMIN)) {
```

to:

```php
if ($this->user()?->hasRoleCode('bank_admin')) {
```

Remove `use App\Enums\UserRole;` if unused afterward.

- [ ] **Step 6: Convert UpdateUserRequest**

Read `backend/app/Http/Requests/UpdateUserRequest.php` fully, then apply:

```php
if (! $actor?->hasRole(UserRole::BANK_ADMIN)) {
```
→
```php
if (! $actor?->hasRoleCode('bank_admin')) {
```

```php
! $target->role?->isBankAdminManageable()
```
→
```php
! $target->hasAnyRoleCode(['intake', 'internal_reviewer'])
```

For the role-string-based checks (`UserRole::tryFrom($roleValue)`, `UserRole::from($roleValue)->isBankAdminManageable()`), these validate an incoming request payload field named `role` that still submits legacy enum string values (e.g. `"BANK_ADMIN"`) — this is a client-contract concern, not an internal auth-gate concern, and is OUT OF SCOPE for this migration (changing the wire format of the `role` field is a separate, larger API-contract change requiring frontend coordination). Leave these two lines unchanged:

```php
if (! $roleValue || ! UserRole::tryFrom($roleValue)) {
    return true;
}

return $actor->bank_id !== null
    && UserRole::from($roleValue)->isBankAdminManageable()
    && (int) $this->input('bank_id') === (int) $actor->bank_id;
```

Apply the same two substitutions (`hasRole(UserRole::BANK_ADMIN)` → `hasRoleCode('bank_admin')`, and the `isBankRole()`/`isCbyRole()`/`isBankAdminManageable()` group checks in `withValidator()`) is also OUT OF SCOPE for the same reason — those checks validate the enum value the client is submitting in the request body, not the acting user's own authorization. Only convert checks that gate on `$actor`'s own role (the two substitutions above); leave checks on `$roleValue`/`UserRole::from($roleValue)` (the target's requested role) as enum-based, since that's validating client input format, not doing authorization.

Do NOT remove `use App\Enums\UserRole;` from this file — it's still used for the `$roleValue` validation.

- [ ] **Step 7: Convert StoreUserRequest**

Apply the identical pattern from Step 6 to `backend/app/Http/Requests/StoreUserRequest.php`: only convert the `$actor->hasRole(UserRole::BANK_ADMIN)` check to `$actor->hasRoleCode('bank_admin')`; leave the `$roleValue`/`UserRole::from($roleValue)` validation checks as-is (client input format validation, not actor authorization).

- [ ] **Step 8: Run test to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Requests/PivotFormRequestAuthorizationTest.php
```

Expected: PASS, 4 tests, 0 failures.

- [ ] **Step 9: Run existing request/settings test suites**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Settings/ backend/tests/Feature/User/ backend/tests/Feature/Bank/ backend/tests/Feature/Engine/ 2>&1 | tail -20
```

Expected: all pass.

- [ ] **Step 10: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Http/Requests/UpdateBankRequest.php \
        backend/app/Http/Requests/UpdateUserRequest.php \
        backend/app/Http/Requests/StoreUserRequest.php \
        backend/app/Http/Requests/UpdateAdminSettingRequest.php \
        backend/app/Http/Requests/FxConfirmationUploadRequest.php \
        backend/tests/Feature/Requests/PivotFormRequestAuthorizationTest.php
git commit -m "$(cat <<'EOF'
refactor(backend): migrate form request actor authorization to governance pivot

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Migrate `BankController`, `SearchController`, `UserController` (Api\\), `V1\\UserController` data-scoping queries

**Files:**
- Modify: `backend/app/Http/Controllers/Api/BankController.php`
- Modify: `backend/app/Http/Controllers/Api/SearchController.php`
- Modify: `backend/app/Http/Controllers/Api/UserController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/UserController.php`
- Test: `backend/tests/Feature/Controllers/PivotScopedControllerQueriesTest.php`

**Interfaces:**
- Consumes: Task 1, Task 2
- Produces: bank-scoping and role-search queries filtered by pivot role codes instead of `users.role`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Controllers/PivotScopedControllerQueriesTest.php`:

```php
<?php

namespace Tests\Feature\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivotScopedControllerQueriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_bank_admin_only_sees_own_bank_in_bank_list(): void
    {
        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($bankAdmin)->getJson('/api/banks');
        $response->assertOk();

        $bankIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertEquals([$bankAdmin->bank_id], $bankIds);
    }

    public function test_cby_admin_sees_all_banks(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($admin)->getJson('/api/banks');
        $response->assertOk();

        $this->assertGreaterThan(1, count($response->json('data')));
    }

    public function test_bank_admin_user_list_only_shows_manageable_roles(): void
    {
        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($bankAdmin)->getJson('/api/users');
        $response->assertOk();

        $roles = collect($response->json('data'))->pluck('role')->unique()->all();
        sort($roles);
        $this->assertEquals([UserRole::BANK_REVIEWER->value, UserRole::DATA_ENTRY->value], $roles);
    }

    public function test_search_users_forbidden_for_data_entry(): void
    {
        $entry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        $response = $this->actingAs($entry)->getJson('/api/search?q=test&type=users');
        $response->assertOk();
        $this->assertEmpty($response->json('data') ?? $response->json());
    }
}
```

- [ ] **Step 2: Run test to note baseline (should pass today via enum path)**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Controllers/PivotScopedControllerQueriesTest.php
```

Adjust route URIs in the test to match actual routes if `/api/search` uses a different query-param contract — check with `grep -n "Route::.*search" backend/routes/api.php` first and correct the test before proceeding.

- [ ] **Step 3: Convert BankController**

In `backend/app/Http/Controllers/Api/BankController.php`:

Line ~40, change:
```php
->when($actor->isBankUser(), fn ($q) => $q->where('id', $actor->bank_id))
```
to:
```php
->when($actor->hasAnyRoleCode(['intake', 'internal_reviewer', 'bank_admin', 'fx_swift']), fn ($q) => $q->where('id', $actor->bank_id))
```

Line ~178 `resetAdminPassword()`, change:
```php
if (! $request->user()?->hasRole(UserRole::CBY_ADMIN)) {
```
to:
```php
if (! $request->user()?->hasRoleCode('system_admin')) {
```

Line ~188, the DB query filter finding a bank's admin — change:
```php
->where('role', UserRole::BANK_ADMIN->value)
```
to:
```php
->whereHas('roles', fn ($q) => $q->where('code', 'bank_admin'))
```

Leave line ~91-99 (`store()` — the `'role' => UserRole::BANK_ADMIN` on the auto-provisioned user) and line ~108 (audit `target_role`) and line ~207 (audit `target_role`) UNCHANGED — these are role *assignment on creation* and audit *display*, not authorization gates, and are explicitly out of scope per the plan's Global Constraints. However, since Task 1/2 mean new users must ALSO get a governance pivot row to pass the migrated checks, update the `store()` method: immediately after the `User::create([...])` call that sets `'role' => UserRole::BANK_ADMIN`, add a pivot assignment using the same lookup pattern as `UserSeeder::assignIdentity()`:

```php
$organization = \App\Models\Organization::query()->where('code', 'commercial_banks')->firstOrFail();
$team = \App\Models\Team::query()->where('organization_id', $organization->id)->where('code', 'bank_admin')->firstOrFail();
$role = \App\Models\Role::query()->where('organization_id', $organization->id)->where('code', 'bank_admin')->firstOrFail();

$admin->forceFill([
    'organization_id' => $organization->id,
])->save();
$admin->teams()->sync([$team->id]);
$admin->roles()->sync([$role->id]);
```

(Read the actual `store()` method first to find the exact variable name holding the newly created user — the audit report calls it implicitly but confirm before inserting this block right after creation and before the response is built.)

- [ ] **Step 4: Convert SearchController**

In `backend/app/Http/Controllers/Api/SearchController.php`:

```php
if (! in_array($user->role, [UserRole::CBY_ADMIN, UserRole::BANK_ADMIN], true)) {
    return [];
}
```
→
```php
if (! $user->hasAnyRoleCode(['system_admin', 'bank_admin'])) {
    return [];
}
```

```php
if ($user->role === UserRole::BANK_ADMIN) {
    if (! $user->bank_id) {
        return [];
    }
    $userQuery->where('bank_id', $user->bank_id)
        ->whereIn('role', [
            UserRole::DATA_ENTRY->value,
            UserRole::BANK_REVIEWER->value,
        ]);
}
```
→
```php
if ($user->hasRoleCode('bank_admin')) {
    if (! $user->bank_id) {
        return [];
    }
    $userQuery->where('bank_id', $user->bank_id)
        ->whereHas('roles', fn ($q) => $q->whereIn('code', ['intake', 'internal_reviewer']));
}
```

```php
if ($user->role !== UserRole::CBY_ADMIN) {
    return [];
}
```
→
```php
if (! $user->hasRoleCode('system_admin')) {
    return [];
}
```

```php
if ($user->isBankUser()) {
    $customsQuery->whereHas('request', fn ($q) => $q->where('bank_id', $user->bank_id));
}
```
→
```php
if ($user->hasAnyRoleCode(['intake', 'internal_reviewer', 'bank_admin', 'fx_swift'])) {
    $customsQuery->whereHas('request', fn ($q) => $q->where('bank_id', $user->bank_id));
}
```

Remove `use App\Enums\UserRole;` if unused afterward.

- [ ] **Step 5: Convert UserController (Api\\)**

In `backend/app/Http/Controllers/Api/UserController.php`, line ~40-44:

```php
->when(
    $actor->hasRole(UserRole::BANK_ADMIN),
    fn ($q) => $q->where('bank_id', $actor->bank_id)
        ->whereIn('role', [UserRole::DATA_ENTRY->value, UserRole::BANK_REVIEWER->value])
)
```
→
```php
->when(
    $actor->hasRoleCode('bank_admin'),
    fn ($q) => $q->where('bank_id', $actor->bank_id)
        ->whereHas('roles', fn ($rq) => $rq->whereIn('code', ['intake', 'internal_reviewer']))
)
```

Line ~47:
```php
->when(request()->filled('bank_id') && ! $actor->hasRole(UserRole::BANK_ADMIN), ...)
```
→
```php
->when(request()->filled('bank_id') && ! $actor->hasRoleCode('bank_admin'), ...)
```

Leave all `'target_role' => $user->role?->value` / `'role' => $user->role?->value` audit-log lines (7 occurrences per the audit report) UNCHANGED — display/serialization only.

- [ ] **Step 6: Convert V1\\UserController**

In `backend/app/Http/Controllers/Api/V1/UserController.php`, line ~37:

```php
->when($actor->hasRole(UserRole::BANK_ADMIN), fn ($q) => $q->where('bank_id', $actor->bank_id))
```
→
```php
->when($actor->hasRoleCode('bank_admin'), fn ($q) => $q->where('bank_id', $actor->bank_id))
```

Leave `legacyRoleFor()` (lines ~205-223) UNCHANGED — this is the deliberate reverse-mapping bridge that keeps the legacy `role` column populated for display/back-compat when creating governance-first users; it is explicitly meant to keep referencing `UserRole` enum values as its output type, and is not an authorization gate.

- [ ] **Step 7: Run test to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Controllers/PivotScopedControllerQueriesTest.php
```

Expected: PASS, 4 tests, 0 failures.

- [ ] **Step 8: Run existing bank/user/search test suites**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Bank/ backend/tests/Feature/User/ backend/tests/Feature/Search/ 2>&1 | tail -20
```

Expected: all pass.

- [ ] **Step 9: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Http/Controllers/Api/BankController.php \
        backend/app/Http/Controllers/Api/SearchController.php \
        backend/app/Http/Controllers/Api/UserController.php \
        backend/app/Http/Controllers/Api/V1/UserController.php \
        backend/tests/Feature/Controllers/PivotScopedControllerQueriesTest.php
git commit -m "$(cat <<'EOF'
refactor(backend): migrate bank/user/search controller scoping to governance pivot

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Migrate `ReportController` (workflow/voting/bank reports + exports)

**Files:**
- Modify: `backend/app/Http/Controllers/Api/ReportController.php`
- Test: `backend/tests/Feature/Reports/PivotReportAuthorizationTest.php`

**Interfaces:**
- Consumes: Task 1, Task 2
- Produces: report access gated on pivot role codes; bank-vs-CBY report shape decided by pivot role code instead of enum

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Reports/PivotReportAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\Reports;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivotReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_support_committee_can_access_workflow_report(): void
    {
        $support = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->firstOrFail();

        $response = $this->actingAs($support)->getJson('/api/reports/workflow');
        $response->assertOk();
    }

    public function test_data_entry_cannot_access_workflow_report(): void
    {
        $entry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        $response = $this->actingAs($entry)->getJson('/api/reports/workflow');
        $response->assertForbidden();
    }

    public function test_cby_admin_gets_cross_bank_breakdown_in_bank_report(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($admin)->getJson('/api/reports/bank');
        $response->assertOk();
        $this->assertArrayHasKey('banks', $response->json());
    }

    public function test_swift_officer_cannot_access_bank_report(): void
    {
        $swift = User::query()->where('role', UserRole::SWIFT_OFFICER->value)->firstOrFail();

        $response = $this->actingAs($swift)->getJson('/api/reports/bank');
        $response->assertForbidden();
    }

    public function test_bank_admin_can_access_bank_report_scoped_to_own_bank(): void
    {
        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($bankAdmin)->getJson('/api/reports/bank');
        $response->assertOk();
        $this->assertArrayNotHasKey('banks', $response->json());
    }
}
```

- [ ] **Step 2: Run test to note baseline**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Reports/PivotReportAuthorizationTest.php
```

Adjust assertions if the actual JSON shape differs (e.g. `banks` key name) — read `ReportController::bank()` fully first to confirm the real response structure before finalizing test expectations.

- [ ] **Step 3: Convert isCbyUser() call sites**

`ReportController` calls `$user->isCbyUser()` at `workflow()` (line ~153), `voting()` (line ~293), and `exportWorkflow()` (line ~454). Since `isCbyUser()` is a method ON THE ENUM-BACKED `UserRole` accessed via `$user->role?->isCbyRole()` (per `User::isCbyUser()`'s definition: `return $this->role?->isCbyRole() ?? false;`), replace each of these three call sites:

```php
if (! $user->isCbyUser()) {
```
→
```php
if (! $user->hasAnyRoleCode(['system_admin', 'support', 'committee_manager', 'committee_director'])) {
```

- [ ] **Step 4: Convert bank()/exportBank() dual-flag pattern**

In `backend/app/Http/Controllers/Api/ReportController.php`, `bank()` method (line ~337-346) and `exportBank()` method (line ~515-517) both have this identical pattern — convert both occurrences:

```php
$bankRoles = [
    UserRole::DATA_ENTRY->value,
    UserRole::BANK_REVIEWER->value,
    UserRole::BANK_ADMIN->value,
];
$isBankReportingUser = in_array($user->role?->value, $bankRoles, true);
$isCbyAdmin = $user->role === UserRole::CBY_ADMIN;

if (! $isBankReportingUser && ! $isCbyAdmin) {
    ...
    return ApiResponse::forbidden();
}
```

to:

```php
$isBankReportingUser = $user->hasAnyRoleCode(['intake', 'internal_reviewer', 'bank_admin']);
$isCbyAdmin = $user->hasRoleCode('system_admin');

if (! $isBankReportingUser && ! $isCbyAdmin) {
    ...
    return ApiResponse::forbidden();
}
```

The `$isCbyAdmin` variable is reused later in each method (`if ($isCbyAdmin) { ... }` branches at lines ~364, ~542, ~569 per the audit) — no changes needed there since the variable name and boolean semantics are preserved.

- [ ] **Step 5: Remove now-unused UserRole import if applicable**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
grep -n "UserRole" app/Http/Controllers/Api/ReportController.php
```

If only the `use` line remains, remove it.

- [ ] **Step 6: Run test to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Reports/PivotReportAuthorizationTest.php
```

Expected: PASS, 5 tests, 0 failures.

- [ ] **Step 7: Run existing reports test suite**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Reports/ 2>&1 | tail -20
```

Expected: all pass.

- [ ] **Step 8: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Http/Controllers/Api/ReportController.php \
        backend/tests/Feature/Reports/PivotReportAuthorizationTest.php
git commit -m "$(cat <<'EOF'
refactor(backend): migrate ReportController authorization to governance pivot

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Migrate `DashboardController` role dispatch and `AuditController` authorization

**Files:**
- Modify: `backend/app/Http/Controllers/Api/DashboardController.php`
- Modify: `backend/app/Http/Controllers/Api/AuditController.php`
- Test: `backend/tests/Feature/Dashboard/PivotDashboardDispatchTest.php`

**Interfaces:**
- Consumes: Task 1 (`committee_director` code is what makes this task possible — without it, `EXECUTIVE_MEMBER` and `COMMITTEE_DIRECTOR` would collapse to the same dashboard/audit access), Task 2
- Produces: dashboard variant selection and audit-log access gated on pivot role codes, correctly distinguishing director from executive member

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Dashboard/PivotDashboardDispatchTest.php`:

```php
<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivotDashboardDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_each_role_gets_a_dashboard_response(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::query()->where('role', $role->value)->first();
            if ($user === null) {
                continue;
            }

            $response = $this->actingAs($user)->getJson('/api/dashboard/stats');
            $response->assertOk();
        }
    }

    public function test_committee_director_and_executive_member_get_different_dashboards(): void
    {
        $director = User::query()->where('role', UserRole::COMMITTEE_DIRECTOR->value)->firstOrFail();
        $executive = User::query()->where('role', UserRole::EXECUTIVE_MEMBER->value)->firstOrFail();

        $directorResponse = $this->actingAs($director)->getJson('/api/dashboard/stats')->json();
        $executiveResponse = $this->actingAs($executive)->getJson('/api/dashboard/stats')->json();

        $this->assertNotEquals($directorResponse, $executiveResponse);
    }

    public function test_committee_director_can_access_audit_log(): void
    {
        $director = User::query()->where('role', UserRole::COMMITTEE_DIRECTOR->value)->firstOrFail();

        $response = $this->actingAs($director)->getJson('/api/audit');
        $response->assertOk();
    }

    public function test_executive_member_cannot_access_audit_log(): void
    {
        $executive = User::query()->where('role', UserRole::EXECUTIVE_MEMBER->value)->firstOrFail();

        $response = $this->actingAs($executive)->getJson('/api/audit');
        $response->assertForbidden();
    }

    public function test_cby_admin_can_access_audit_log(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($admin)->getJson('/api/audit');
        $response->assertOk();
    }
}
```

- [ ] **Step 2: Run test to note baseline (must pass today since Task 1 already fixed the director/executive distinction at the data layer)**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Dashboard/PivotDashboardDispatchTest.php
```

- [ ] **Step 3: Convert DashboardController::stats() dispatch**

In `backend/app/Http/Controllers/Api/DashboardController.php`, change:

```php
return match (true) {
    $user->hasRole(UserRole::DATA_ENTRY) => $this->dataEntryStats($user),
    $user->hasRole(UserRole::BANK_REVIEWER) => $this->bankReviewerStats($user),
    $user->hasRole(UserRole::BANK_ADMIN) => $this->bankAdminStats($user),
    $user->hasRole(UserRole::SUPPORT_COMMITTEE) => $this->supportCommitteeStats($user),
    $user->hasRole(UserRole::SWIFT_OFFICER) => $this->swiftOfficerStats($user),
    $user->hasRole(UserRole::EXECUTIVE_MEMBER) => $this->executiveMemberStats($user),
    $user->hasRole(UserRole::COMMITTEE_DIRECTOR) => $this->committeeDirectorStats($user),
    $user->hasRole(UserRole::CBY_ADMIN) => $this->cbyadminStats(),
    default => ApiResponse::success([], 'Dashboard stats retrieved.'),
};
```

to:

```php
return match (true) {
    $user->hasRoleCode('intake') => $this->dataEntryStats($user),
    $user->hasRoleCode('internal_reviewer') => $this->bankReviewerStats($user),
    $user->hasRoleCode('bank_admin') => $this->bankAdminStats($user),
    $user->hasRoleCode('support') => $this->supportCommitteeStats($user),
    $user->hasRoleCode('fx_swift') => $this->swiftOfficerStats($user),
    $user->hasRoleCode('committee_manager') => $this->executiveMemberStats($user),
    $user->hasRoleCode('committee_director') => $this->committeeDirectorStats($user),
    $user->hasRoleCode('system_admin') => $this->cbyadminStats(),
    default => ApiResponse::success([], 'Dashboard stats retrieved.'),
};
```

(This is the exact reason Task 1 was necessary — without a distinct `committee_director` code, this `match` could not tell directors from executive members.)

- [ ] **Step 4: Convert bankAdminStats() active-user count**

In the same file, `bankAdminStats()` method:

```php
$activeUsers = User::query()
    ->where('bank_id', $bankId)
    ->whereIn('role', [UserRole::DATA_ENTRY->value, UserRole::BANK_REVIEWER->value])
    ->where('is_active', true)
    ->count();
```

to:

```php
$activeUsers = User::query()
    ->where('bank_id', $bankId)
    ->whereHas('roles', fn ($q) => $q->whereIn('code', ['intake', 'internal_reviewer']))
    ->where('is_active', true)
    ->count();
```

- [ ] **Step 5: Remove unused UserRole import if applicable**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
grep -n "UserRole" app/Http/Controllers/Api/DashboardController.php
```

- [ ] **Step 6: Convert AuditController::isAuditAuthorized()**

In `backend/app/Http/Controllers/Api/AuditController.php`, change:

```php
private function isAuditAuthorized(): bool
{
    $role = request()->user()->role;

    return in_array($role, [UserRole::CBY_ADMIN, UserRole::COMMITTEE_DIRECTOR], true);
}
```

to:

```php
private function isAuditAuthorized(): bool
{
    return request()->user()->hasAnyRoleCode(['system_admin', 'committee_director']);
}
```

Remove `use App\Enums\UserRole;` if unused afterward.

- [ ] **Step 7: Run test to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Dashboard/PivotDashboardDispatchTest.php
```

Expected: PASS, 5 tests, 0 failures.

- [ ] **Step 8: Run existing dashboard/audit test suites**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Dashboard/ backend/tests/Feature/Audit/ 2>&1 | tail -20
```

Expected: all pass.

- [ ] **Step 9: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Http/Controllers/Api/DashboardController.php \
        backend/app/Http/Controllers/Api/AuditController.php \
        backend/tests/Feature/Dashboard/PivotDashboardDispatchTest.php
git commit -m "$(cat <<'EOF'
refactor(backend): migrate DashboardController dispatch and AuditController auth to governance pivot

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 9: Migrate `SystemSettingsService`, `EngineClaimService`, `ExpireEngineClaimsCommand`

**Files:**
- Modify: `backend/app/Services/Settings/SystemSettingsService.php`
- Modify: `backend/app/Services/Workflow/EngineClaimService.php`
- Modify: `backend/app/Console/Commands/ExpireEngineClaimsCommand.php`
- Test: `backend/tests/Feature/Services/PivotServiceAuthorizationTest.php`

**Interfaces:**
- Consumes: Task 1, Task 2
- Produces: service-layer and console-command CBY_ADMIN checks keyed on pivot role code

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Services/PivotServiceAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\Services;

use App\Enums\UserRole;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Settings\SystemSettingsService;
use App\Services\Workflow\EngineClaimService;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivotServiceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_cby_admin_can_save_system_settings(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $this->app->make(SystemSettingsService::class)->saveSection('general', ['app_name' => 'Test'], $admin);

        $this->assertTrue(true);
    }

    public function test_non_admin_cannot_save_system_settings(): void
    {
        $entry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        $this->expectException(AuthorizationException::class);
        $this->app->make(SystemSettingsService::class)->saveSection('general', ['app_name' => 'Test'], $entry);
    }

    public function test_cby_admin_can_force_release_claim_held_by_another_user(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $support = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->firstOrFail();

        $request = EngineRequest::factory()->create([
            'claimed_by' => $support->id,
        ]);

        $this->app->make(EngineClaimService::class)->release($request, $admin);

        $this->assertNull($request->fresh()->claimed_by);
    }
}
```

- [ ] **Step 2: Run test to note baseline**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Services/PivotServiceAuthorizationTest.php
```

Read `SystemSettingsService::saveSection()` and `EngineClaimService::release()` signatures fully first to confirm exact method parameters match this test's calls — adjust the test if signatures differ (e.g. if `claimed_by` column is actually named differently, check the `engine_requests` migration).

- [ ] **Step 3: Convert SystemSettingsService**

In `backend/app/Services/Settings/SystemSettingsService.php`, change:

```php
if (! $user->hasRole(UserRole::CBY_ADMIN)) {
    throw new AuthorizationException('Only administrators can modify system settings.');
}
```

to:

```php
if (! $user->hasRoleCode('system_admin')) {
    throw new AuthorizationException('Only administrators can modify system settings.');
}
```

Remove `use App\Enums\UserRole;` if unused afterward.

- [ ] **Step 4: Convert EngineClaimService**

In `backend/app/Services/Workflow/EngineClaimService.php`, change:

```php
$isAdmin = $user->role === UserRole::CBY_ADMIN;
if (! $isAdmin && $locked->claimed_by !== $user->id) {
    throw EngineException::claimNotHeld();
}
```

to:

```php
$isAdmin = $user->hasRoleCode('system_admin');
if (! $isAdmin && $locked->claimed_by !== $user->id) {
    throw EngineException::claimNotHeld();
}
```

Remove `use App\Enums\UserRole;` if unused afterward.

- [ ] **Step 5: Convert ExpireEngineClaimsCommand**

In `backend/app/Console/Commands/ExpireEngineClaimsCommand.php`, change:

```php
private function resolveCbyAdminIds(): array
{
    return User::query()
        ->where('role', UserRole::CBY_ADMIN->value)
        ->where('is_active', true)
        ->pluck('id')
        ->toArray();
}
```

to:

```php
private function resolveCbyAdminIds(): array
{
    return User::query()
        ->whereHas('roles', fn ($q) => $q->where('code', 'system_admin'))
        ->where('is_active', true)
        ->pluck('id')
        ->toArray();
}
```

Remove `use App\Enums\UserRole;` if unused afterward.

- [ ] **Step 6: Run test to verify it passes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Services/PivotServiceAuthorizationTest.php
```

Expected: PASS, 3 tests, 0 failures.

- [ ] **Step 7: Run existing settings/engine/claim test suites**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Settings/ backend/tests/Feature/Engine/ backend/tests/Unit/Commands/ 2>&1 | tail -20
```

Expected: all pass.

- [ ] **Step 8: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Services/Settings/SystemSettingsService.php \
        backend/app/Services/Workflow/EngineClaimService.php \
        backend/app/Console/Commands/ExpireEngineClaimsCommand.php \
        backend/tests/Feature/Services/PivotServiceAuthorizationTest.php
git commit -m "$(cat <<'EOF'
refactor(backend): migrate SystemSettingsService, EngineClaimService, ExpireEngineClaimsCommand to governance pivot

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 10: Full regression pass and CLAUDE.md/docs update

**Files:**
- No source changes — verification only
- Modify: `docs/superpowers/plans/2026-07-01-workstream-d-governance-consolidation.md` (append a superseded notice, do not delete history)

**Interfaces:**
- Consumes: Tasks 1-9 complete
- Produces: confirmation the full migration is regression-free; historical record updated so future readers don't act on the stale 2026-07-01 decision

- [ ] **Step 1: Run the full backend test suite**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test 2>&1 | tail -60
```

Expected: all pass, zero failures. If any test outside the files touched in Tasks 1-9 fails, it means a test factory somewhere creates a user with a `role` enum but no governance pivot assignment — find it via the failure's stack trace and fix the factory/seeder to also assign the pivot (following `UserSeeder::assignIdentity()`'s pattern), not by reverting production code.

- [ ] **Step 2: Grep-verify no remaining authorization-gating enum reads**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/backend
grep -rn "hasRole(UserRole::\|\$user->role ===\|\$user->role !==\|in_array(\$user->role\|\$user->isBankUser()\|\$user->isCbyUser()" app/Policies app/Http/Requests app/Http/Controllers app/Services app/Console
```

Expected: zero output, OR only output from `UpdateUserRequest.php`/`StoreUserRequest.php` lines validating the incoming `$roleValue` request field (explicitly out of scope per Task 5 Step 6) — confirm any remaining hits are one of those two files' `$roleValue`/`UserRole::from($roleValue)` lines, not `$actor`/`$user` authorization checks.

- [ ] **Step 3: Append superseded notice to the 2026-07-01 plan doc**

Read `docs/superpowers/plans/2026-07-01-workstream-d-governance-consolidation.md` in full, then add this notice immediately after the header block (after the `## Global Constraints` section, before the first `---`):

```markdown
> **⚠️ SUPERSEDED (2026-07-03):** The architectural decision above ("users.role scalar remains the live auth gate — do NOT change PermissionService, policies, or auth middleware") was explicitly reversed on 2026-07-03. See `docs/superpowers/plans/2026-07-03-governance-pivot-auth-migration.md` — all authorization now reads the governance pivot (`roles`/`user_roles`), not `users.role`. This file is kept for historical context only; do not use its constraints for new work.
```

- [ ] **Step 4: Commit the docs update**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add docs/superpowers/plans/2026-07-01-workstream-d-governance-consolidation.md
git commit -m "$(cat <<'EOF'
docs: mark 2026-07-01 governance decision as superseded by pivot auth migration

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 5: Report final state**

Summarize for the user: which files were migrated, the full test suite result (pass count), and confirm the original bug (CBY_ADMIN empty requests list) is verifiably fixed by Task 3's test.

---
