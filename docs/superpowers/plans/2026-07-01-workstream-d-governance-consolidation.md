# Workstream D — Governance Consolidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Delete the dead Trader subsystem (backend + frontend + DB tables) and remove two stale legacy artefacts from the codebase (`User::votes()` relation referencing deleted `RequestVote` model, and `ImportRequest` type annotations in `useDashboard.ts` / `ExecutiveDashboard.vue` that should reference `EngineRequest`).

**Architecture:** The engine uses `merchants` (not traders) as its canonical counterpart entity. The `traders`/`trader_companies`/`trader_owners` tables, all associated backend classes, all frontend trader pages/components/composables/store, and all frontend type references to Trader are dead weight with no engine consumer — safe to delete entirely. The role model is intentionally dual (`users.role` scalar drives authz, `user_roles` pivot feeds governance/engine Role display) — no collapse needed, just remove the stale `votes()` HasMany on User that references the already-deleted `RequestVote` model.

**Tech Stack:** Laravel 11 (PHP 8.2), Nuxt 4 / Vue 3 / TypeScript, MySQL migrations for DB drops, PHPUnit + Vitest for tests.

## Global Constraints

- All commits from repo root (`/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code`), never from `backend/` or `frontend/` subdirectory
- Conventional commit format: `type(scope): description` — allowed scopes: `auth`, `backend`, `docs`, `frontend`, `repo`, `settings`, `testing`, `ui`, `workflow`
- All commits signed — never `--no-gpg-sign`, `--no-sign`, `-c commit.gpgsign=false`
- Never `--no-verify`
- Co-author line required: `Co-Authored-By: Claude <noreply@anthropic.com>`
- Run tests with `php -d xdebug.mode=off artisan test <file>` to suppress deprecation noise; "N deprecated" in output = N passed, not failures
- Backend test command from repo root: `cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code && php -d xdebug.mode=off artisan test <testfile>`
- Frontend test command from repo root: `cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend && pnpm exec vitest run <testfile>`
- Do NOT run full `php artisan test` or `pnpm test` — run only targeted files per task
- `pnpm` only for JS tooling — no Bun
- Never commit `graphify-out/` artifacts
- `users.role` scalar remains the live auth gate — do NOT change PermissionService, policies, or auth middleware

---

### Task 1: Delete Trader Backend (controllers, services, models, policies, resources, form requests, routes, event provider bindings)

**Files:**
- Delete: `backend/app/Http/Controllers/Api/TraderController.php`
- Delete: `backend/app/Services/TraderService.php`
- Delete: `backend/app/Models/Trader.php`
- Delete: `backend/app/Models/TraderCompany.php`
- Delete: `backend/app/Models/TraderOwner.php`
- Delete: `backend/app/Policies/TraderPolicy.php`
- Delete: `backend/app/Http/Resources/TraderResource.php`
- Delete: `backend/app/Http/Requests/StoreTraderRequest.php`
- Delete: `backend/app/Http/Requests/UpdateTraderRequest.php`
- Modify: `backend/app/Providers/AuthServiceProvider.php` — remove Trader policy registration
- Modify: `backend/routes/api.php` — remove trader routes and use statement
- Modify: `backend/app/Models/User.php` — remove `votes()` HasMany (references deleted `RequestVote` model) and its `use App\Models\RequestVote` import and `use Illuminate\Database\Eloquent\Relations\HasMany` if no other HasMany remains (check first)
- Test: `backend/tests/Feature/Trader/` (delete entire directory if it exists)

**Interfaces:**
- Consumes: nothing from other tasks
- Produces: trader tables still exist in DB (dropped in Task 2); backend classes gone after this task

- [ ] **Step 1: Verify no engine code references trader classes**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
grep -rn "TraderController\|TraderService\|TraderPolicy\|TraderResource\|StoreTraderRequest\|UpdateTraderRequest\|App\\\\Models\\\\Trader[^O]" backend/app/ --include="*.php" | grep -v "TraderController\|TraderService\|TraderPolicy\|TraderResource\|StoreTraderRequest\|UpdateTraderRequest\|Models/Trader\|Models/TraderCompany\|Models/TraderOwner\|Policies/TraderPolicy\|Resources/TraderResource\|Requests/StoreTrader\|Requests/UpdateTrader\|Services/TraderService"
```

Expected: zero output (no engine or shared code imports trader classes)

- [ ] **Step 2: Check if HasMany is used elsewhere in User model**

```bash
grep -n "HasMany\|hasMany" backend/app/Models/User.php
```

Expected output shows these HasMany uses: `auditLogs()`, `loginHistory()` — and `votes()`. The `use Illuminate\Database\Eloquent\Relations\HasMany` import must be KEPT (still used by `auditLogs` and `loginHistory`).

- [ ] **Step 3: Check for trader test directory**

```bash
ls backend/tests/Feature/Trader/ 2>/dev/null || echo "NO_TRADER_TEST_DIR"
ls backend/tests/Unit/Trader/ 2>/dev/null || echo "NO_TRADER_UNIT_DIR"
```

Note output — delete any trader test files found.

- [ ] **Step 4: Delete trader backend files**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
rm backend/app/Http/Controllers/Api/TraderController.php
rm backend/app/Services/TraderService.php
rm backend/app/Models/Trader.php
rm backend/app/Models/TraderCompany.php
rm backend/app/Models/TraderOwner.php
rm backend/app/Policies/TraderPolicy.php
rm backend/app/Http/Resources/TraderResource.php
rm backend/app/Http/Requests/StoreTraderRequest.php
rm backend/app/Http/Requests/UpdateTraderRequest.php
```

If trader test directories exist (found in Step 3):
```bash
rm -rf backend/tests/Feature/Trader backend/tests/Unit/Trader
```

- [ ] **Step 5: Remove Trader policy from AuthServiceProvider**

Open `backend/app/Providers/AuthServiceProvider.php`. Remove these two lines:
```php
use App\Models\Trader;           // ← remove
use App\Policies\TraderPolicy;   // ← remove
```
And remove from the `$policies` array (or `Gate::policy()` calls):
```php
Gate::policy(Trader::class, TraderPolicy::class);  // ← remove
```

Verify no other Trader references remain in the file:
```bash
grep -n "Trader" backend/app/Providers/AuthServiceProvider.php
```
Expected: zero output.

- [ ] **Step 6: Remove trader routes from api.php**

Open `backend/routes/api.php`. Remove:
```php
use App\Http\Controllers\Api\TraderController;   // line ~17 — remove this use
```
And remove the trader route block (lines ~248–254):
```php
    Route::get('traders/lookup', [TraderController::class, 'lookup']);
    Route::get('traders', [TraderController::class, 'index']);
    Route::post('traders', [TraderController::class, 'store']);
    Route::get('traders/{trader}', [TraderController::class, 'show']);
    Route::put('traders/{trader}', [TraderController::class, 'update']);
    Route::patch('traders/{trader}', [TraderController::class, 'update']);
    Route::delete('traders/{trader}', [TraderController::class, 'destroy']);
```

Verify:
```bash
grep -n "trader\|Trader" backend/routes/api.php
```
Expected: zero output.

- [ ] **Step 7: Remove stale votes() from User model**

Open `backend/app/Models/User.php`. Remove these lines:
```php
    public function votes(): HasMany
    {
        return $this->hasMany(RequestVote::class);
    }
```
And remove the import at the top:
```php
use App\Models\RequestVote;   // ← remove this line
```

Keep `use Illuminate\Database\Eloquent\Relations\HasMany` — still used by `auditLogs()` and `loginHistory()`.

Verify:
```bash
grep -n "RequestVote\|votes()" backend/app/Models/User.php
```
Expected: zero output.

- [ ] **Step 8: Run format check on touched files**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
vendor/bin/pint backend/app/Providers/AuthServiceProvider.php backend/routes/api.php backend/app/Models/User.php --test
```

If pint reports changes needed, run without `--test` to apply:
```bash
vendor/bin/pint backend/app/Providers/AuthServiceProvider.php backend/routes/api.php backend/app/Models/User.php
```

- [ ] **Step 9: Run targeted backend tests**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Auth/ backend/tests/Feature/User/ backend/tests/Feature/Bank/ 2>&1 | tail -5
```

Expected: all pass, zero failures. (These cover the User model and auth paths we touched.)

Also run:
```bash
php -d xdebug.mode=off artisan test backend/tests/Feature/Engine/ 2>&1 | tail -5
```

Expected: all pass.

- [ ] **Step 10: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/app/Providers/AuthServiceProvider.php \
        backend/routes/api.php \
        backend/app/Models/User.php
git add -u backend/app/Http/Controllers/Api/TraderController.php \
          backend/app/Services/TraderService.php \
          backend/app/Models/Trader.php \
          backend/app/Models/TraderCompany.php \
          backend/app/Models/TraderOwner.php \
          backend/app/Policies/TraderPolicy.php \
          backend/app/Http/Resources/TraderResource.php \
          backend/app/Http/Requests/StoreTraderRequest.php \
          backend/app/Http/Requests/UpdateTraderRequest.php
git commit -m "$(cat <<'EOF'
refactor(backend): delete trader subsystem, remove stale RequestVote relation

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Drop Trader DB Tables (new drop migration)

**Files:**
- Create: `backend/database/migrations/2026_07_01_000001_drop_trader_tables.php`
- No model changes (models already deleted in Task 1)

**Interfaces:**
- Consumes: Task 1 complete (models gone, safe to drop tables)
- Produces: `traders`, `trader_companies`, `trader_owners` tables dropped from schema

- [ ] **Step 1: Verify no FK constraints point at trader tables from engine tables**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
grep -rn "trader" backend/database/migrations/ | grep -v "create_trader\|drop_trader" | grep -i "foreign\|references"
```

Expected: zero output (no engine migration references traders as FK target).

- [ ] **Step 2: Write the drop migration**

Create `backend/database/migrations/2026_07_01_000001_drop_trader_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('trader_owners');
        Schema::dropIfExists('trader_companies');
        Schema::dropIfExists('traders');
    }

    public function down(): void
    {
        Schema::create('traders', function ($table) {
            $table->id();
            $table->string('tax_number')->unique();
            $table->string('trader_name')->nullable();
            $table->date('tax_card_expiry')->nullable();
            $table->string('commercial_registration_number')->nullable();
            $table->date('commercial_registration_expiry')->nullable();
            $table->timestamps();
        });

        Schema::create('trader_companies', function ($table) {
            $table->id();
            $table->foreignId('trader_id')->constrained('traders')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('trader_owners', function ($table) {
            $table->id();
            $table->foreignId('trader_id')->constrained('traders')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php artisan migrate
```

Expected: `Migrating: 2026_07_01_000001_drop_trader_tables` then `Migrated`.

- [ ] **Step 4: Verify tables are gone**

```bash
php artisan tinker --execute="echo implode(',', array_filter(array_map(fn(\$t) => in_array(\$t, ['traders','trader_companies','trader_owners']) ? \$t : null, \Illuminate\Support\Facades\Schema::getTableListing())));"
```

Expected: empty output (no trader tables remain).

- [ ] **Step 5: Run engine tests to confirm nothing broke**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
php -d xdebug.mode=off artisan test backend/tests/Feature/Engine/ 2>&1 | tail -5
```

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add backend/database/migrations/2026_07_01_000001_drop_trader_tables.php
git commit -m "$(cat <<'EOF'
refactor(backend): drop trader_owners, trader_companies, traders tables

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Delete Trader Frontend (pages, components, composable, store, type file, shared file cleanup, sidebar/palette entries)

**Files:**
- Delete: `frontend/app/pages/traders/index.vue`
- Delete: `frontend/app/pages/traders/new.vue`
- Delete: `frontend/app/pages/traders/[id]/index.vue`
- Delete: `frontend/app/pages/traders/[id]/edit.vue`
- Delete: `frontend/app/components/trader/TraderForm.vue`
- Delete: `frontend/app/components/trader/TraderCompaniesField.vue`
- Delete: `frontend/app/components/trader/TraderOwnersField.vue`
- Delete: `frontend/app/composables/useTraders.ts`
- Delete: `frontend/app/stores/traders.ts`
- Delete: `frontend/app/types/trader.ts`
- Delete: `frontend/app/tests/unit/composables/useTraders.test.ts`
- Delete: `frontend/app/tests/unit/stores/traders.store.test.ts`
- Delete: `frontend/app/tests/unit/types/trader.test.ts`
- Delete: `frontend/app/tests/unit/components/TraderForm.test.ts`
- Delete: `frontend/app/tests/unit/components/TraderCompaniesField.test.ts`
- Delete: `frontend/app/tests/unit/components/TraderOwnersField.test.ts`
- Delete: `frontend/app/tests/unit/pages/traders-index.test.ts`
- Delete: `frontend/app/tests/unit/pages/traders-new.test.ts`
- Delete: `frontend/app/tests/unit/pages/traders-id-index.test.ts`
- Delete: `frontend/app/tests/unit/pages/traders-id-edit.test.ts`
- Delete: `frontend/app/tests/unit/components/AppSidebar.traders.test.ts`
- Modify: `frontend/app/components/AppSidebar.vue` — remove `/traders` from nav array
- Modify: `frontend/app/components/CommandPalette.vue` — remove `/traders` entries
- Modify: `frontend/app/constants/workflow.ts` — remove trader import and TRADER_MANAGEMENT_ROLES usage
- Modify: `frontend/app/types/models.ts` — remove `ImportRequest` interface (lines 208–314), `RequestFormData` interface (lines 368–416), `TraderLookupResult` interface (lines 417–421), and the `import type { Trader, TraderCompany, TraderOwner }` line at the top

**Interfaces:**
- Consumes: Task 1 + 2 complete
- Produces: `/traders` route gone; no trader types anywhere in frontend

- [ ] **Step 1: Verify no non-trader frontend file imports from trader files**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
grep -rn "useTraders\|useTradersStore\|TraderForm\|TraderCompaniesField\|TraderOwnersField\|from.*trader\b\|from.*traders\b\|types/trader" frontend/app/ --include="*.ts" --include="*.vue" | grep -v "frontend/app/pages/traders\|frontend/app/components/trader\|frontend/app/composables/useTraders\|frontend/app/stores/traders\|frontend/app/types/trader\|frontend/app/tests"
```

Expected: only `AppSidebar.vue`, `CommandPalette.vue`, `constants/workflow.ts` (all being edited), and `types/models.ts` (being edited). If anything else appears, note it and clean it up before deleting files.

- [ ] **Step 2: Delete trader frontend files**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code

# Pages
rm -rf frontend/app/pages/traders

# Components
rm -rf frontend/app/components/trader

# Composable, store, type
rm frontend/app/composables/useTraders.ts
rm frontend/app/stores/traders.ts
rm frontend/app/types/trader.ts

# Tests
rm frontend/app/tests/unit/composables/useTraders.test.ts
rm frontend/app/tests/unit/stores/traders.store.test.ts
rm frontend/app/tests/unit/types/trader.test.ts
rm frontend/app/tests/unit/components/TraderForm.test.ts
rm frontend/app/tests/unit/components/TraderCompaniesField.test.ts
rm frontend/app/tests/unit/components/TraderOwnersField.test.ts
rm frontend/app/tests/unit/pages/traders-index.test.ts
rm frontend/app/tests/unit/pages/traders-new.test.ts
rm frontend/app/tests/unit/pages/traders-id-index.test.ts
rm frontend/app/tests/unit/pages/traders-id-edit.test.ts
rm frontend/app/tests/unit/components/AppSidebar.traders.test.ts
```

- [ ] **Step 3: Remove /traders from AppSidebar.vue**

Open `frontend/app/components/AppSidebar.vue`. Find the nav array entry `'/traders'` and remove it (and its trailing comma if needed). It appears around line 144.

After editing, verify:
```bash
grep -n "trader\|Trader" frontend/app/components/AppSidebar.vue
```
Expected: zero output.

- [ ] **Step 4: Remove /traders from CommandPalette.vue**

Open `frontend/app/components/CommandPalette.vue`. Remove all lines containing `/traders` or `traders`. There are three: the keyboard shortcut map entry `'/traders': 'G'`, the search keywords entry `'/traders': 'traders tax owners companies'`, and the routes array entry `'/traders'` inside the admin/management group.

After editing, verify:
```bash
grep -n "trader\|Trader" frontend/app/components/CommandPalette.vue
```
Expected: zero output.

- [ ] **Step 5: Remove trader from constants/workflow.ts**

Open `frontend/app/constants/workflow.ts`. Remove:
```ts
import { TRADER_MANAGEMENT_ROLES } from '../types/trader'   // ← remove
```
And the route entry that references `/traders` (around line 301):
```ts
    route: '/traders',   // ← remove entire object containing this
```

After editing, verify:
```bash
grep -n "trader\|Trader" frontend/app/constants/workflow.ts
```
Expected: zero output.

- [ ] **Step 6: Remove legacy types from models.ts**

Open `frontend/app/types/models.ts`. Make these surgical removals:

**Line ~14** — remove the Trader import:
```ts
import type { Trader, TraderCompany, TraderOwner } from './trader'
```

**Lines 208–314** — remove the entire `ImportRequest` interface (from `export interface ImportRequest {` through its closing `}`).

**Lines 368–416** — remove the entire `RequestFormData` interface (from `export interface RequestFormData {` through its closing `}`).

**Lines 417–421** — remove the `TraderLookupResult` interface:
```ts
export interface TraderLookupResult {
  trader: Trader
  companies: TraderCompany[]
  owners: TraderOwner[]
}
```

After editing, verify:
```bash
grep -n "trader\|Trader\|ImportRequest\|RequestFormData\|TraderLookupResult" frontend/app/types/models.ts
```
Expected: zero output.

- [ ] **Step 7: Fix useDashboard.ts — replace ImportRequest with EngineRequest**

Open `frontend/app/composables/useDashboard.ts`. The dashboard API now returns engine requests in all queue arrays. Replace the import and all type annotations:

Change line 1:
```ts
// Before:
import type { ApiResponse, ImportRequest } from '../types/models'
// After:
import type { ApiResponse, EngineRequest } from '../types/models'
```

Replace every occurrence of `ImportRequest` with `EngineRequest` throughout the file. This affects:
- `DataEntryDashboardStats`: `draft_requests`, `returned_requests`, `recent_requests`
- `BankReviewerDashboardStats`: `review_queue`, `downstream_queue`
- `BankAdminDashboardStats`: `recent_requests`
- `SupportCommitteeDashboardStats`: `support_queue`
- `SwiftOfficerDashboardStats`: `swift_queue`
- `VotingQueueItem extends ImportRequest` → `VotingQueueItem extends EngineRequest`
- `ExecutiveDashboardStats`: `customs_declaration_pending`, `fx_confirmation_queue`, `voting_lifecycle_queue`
- `CbyAdminDashboardStats`: `recent_requests`

After editing, verify:
```bash
grep -n "ImportRequest" frontend/app/composables/useDashboard.ts
```
Expected: zero output.

- [ ] **Step 8: Fix ExecutiveDashboard.vue — replace ImportRequest with EngineRequest**

Open `frontend/app/components/dashboard/ExecutiveDashboard.vue`. Change:
```ts
// Before:
import type { ImportRequest } from '../../types/models'
// After:
import type { EngineRequest } from '../../types/models'
```

Then replace every use of `ImportRequest` in that file with `EngineRequest`.

After editing, verify:
```bash
grep -n "ImportRequest" frontend/app/components/dashboard/ExecutiveDashboard.vue
```
Expected: zero output.

- [ ] **Step 9: Run typecheck**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm typecheck 2>&1 | tail -20
```

Expected: zero errors. If errors appear, they will be in files that imported from `types/trader` or used `ImportRequest` — fix them before continuing.

- [ ] **Step 10: Run ESLint on touched files**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec eslint app/components/AppSidebar.vue app/components/CommandPalette.vue app/constants/workflow.ts app/types/models.ts app/composables/useDashboard.ts app/components/dashboard/ExecutiveDashboard.vue 2>&1 | tail -20
```

Expected: zero warnings, zero errors.

- [ ] **Step 11: Run targeted Vitest on dashboard and sidebar**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm exec vitest run app/tests/unit/composables/useDashboard.test.ts app/tests/unit/components/AppSidebar.test.ts 2>&1 | tail -10
```

If those test files don't exist, run:
```bash
pnpm exec vitest run app/tests/unit/components/ 2>&1 | tail -10
```

Expected: all pass, zero failures.

- [ ] **Step 12: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add -u frontend/app/pages/traders/ \
           frontend/app/pages/traders/index.vue \
           frontend/app/pages/traders/new.vue \
           "frontend/app/pages/traders/[id]/index.vue" \
           "frontend/app/pages/traders/[id]/edit.vue" \
           frontend/app/components/trader/TraderForm.vue \
           frontend/app/components/trader/TraderCompaniesField.vue \
           frontend/app/components/trader/TraderOwnersField.vue \
           frontend/app/composables/useTraders.ts \
           frontend/app/stores/traders.ts \
           frontend/app/types/trader.ts
git add -u frontend/app/tests/unit/composables/useTraders.test.ts \
           frontend/app/tests/unit/stores/traders.store.test.ts \
           frontend/app/tests/unit/types/trader.test.ts \
           frontend/app/tests/unit/components/TraderForm.test.ts \
           frontend/app/tests/unit/components/TraderCompaniesField.test.ts \
           frontend/app/tests/unit/components/TraderOwnersField.test.ts \
           frontend/app/tests/unit/pages/traders-index.test.ts \
           frontend/app/tests/unit/pages/traders-new.test.ts \
           frontend/app/tests/unit/pages/traders-id-index.test.ts \
           frontend/app/tests/unit/pages/traders-id-edit.test.ts \
           frontend/app/tests/unit/components/AppSidebar.traders.test.ts
git add frontend/app/components/AppSidebar.vue \
        frontend/app/components/CommandPalette.vue \
        frontend/app/constants/workflow.ts \
        frontend/app/types/models.ts \
        frontend/app/composables/useDashboard.ts \
        frontend/app/components/dashboard/ExecutiveDashboard.vue
git commit -m "$(cat <<'EOF'
refactor(frontend): delete trader subsystem, migrate dashboard types to EngineRequest

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```
