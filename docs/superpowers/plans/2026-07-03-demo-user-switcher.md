# Demo User Switcher Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a floating button (visible everywhere, including the login screen) that opens a searchable, org-grouped list of every active demo user; clicking a user card logs in as that user instantly, with no password/PIN/OTP steps.

**Architecture:** Two new unauthenticated backend endpoints (`GET /api/auth/demo-users`, `POST /api/auth/switch-demo-user`) reuse the existing `AuthController::issueSession()` session-issuing helper and the existing `demo.allow_role_switch` config gate. A new frontend runtime flag mirrors the backend gate so the button never mounts when demo mode is off. A floating `Button` + shadcn `Sheet` pair, mounted once in `app.vue`, fetch and display the list via a new composable and a new Pinia store action.

**Tech Stack:** Laravel 11 (PHP 8.2+), Sanctum, PHPUnit; Nuxt 4, Vue 4, TypeScript, Pinia, shadcn-vue, Vitest.

## Global Constraints

- Both new backend endpoints must be gated by `config('demo.allow_role_switch')` (env `APP_DEMO_ROLE_SWITCH`), returning `403` via `ApiResponse::forbidden()` when disabled — same flag `switchDemoRole` already uses.
- Both new backend endpoints are unauthenticated (no `auth:sanctum` middleware) — must be reachable from the bare login screen, matching `switch-demo-role`'s existing reachability.
- Both new backend endpoints throttled `throttle:20,1`, matching `switch-demo-role`.
- Frontend button only mounts when `useRuntimeConfig().public.demoUserSwitch` is `true` (env `NUXT_PUBLIC_DEMO_USER_SWITCH`) — checked once, no reactivity needed.
- RTL correctness: floating button at `fixed bottom-4 start-4 z-50` (not `left`); Sheet opens `side="start"` (app sidebar is right/`end`-anchored per `frontend/DESIGN.md`).
- All new UI uses shadcn-vue components only (`Button`, `Sheet`, `Card`, `Badge`, `InputGroup`/`InputGroupInput`, `Skeleton`, `Alert`, `Empty`) per `frontend/SHADCN.md` — no raw `<button>`/`<input>`/`<table>`.
- No business logic in Vue components — fetch/session logic lives in the composable and the Pinia store action.
- Commit message scope/type per `AGENTS.md`: backend changes use `feat(auth): ...` or `feat(backend): ...`; frontend changes use `feat(auth): ...` or `feat(frontend): ...`. Commits must stay signed (no `--no-gpg-sign`).

---

## File Structure

**Backend:**
- Modify: `backend/app/Http/Controllers/Api/AuthController.php` — add `demoUsers()` and `switchDemoUser()` actions.
- Create: `backend/app/Http/Resources/DemoUserResource.php` — shapes a `User` into `{ id, name, email, role, role_label, organization, team, bank }`.
- Modify: `backend/routes/api.php` — register the two new routes next to `switch-demo-role`.
- Create: `backend/tests/Feature/Auth/DemoUserSwitchTest.php` — covers both endpoints, flag on/off, 404 for bad id.

**Frontend:**
- Modify: `frontend/nuxt.config.ts` — add `runtimeConfig.public.demoUserSwitch`.
- Modify: `frontend/app/types/models.ts` — add `DemoUser` interface.
- Modify: `frontend/app/stores/auth.store.ts` — add `switchDemoUser(userId)` action.
- Modify: `frontend/app/tests/unit/stores/auth.store.test.ts` — test the new action.
- Create: `frontend/app/composables/useDemoUsers.ts` — fetches `GET /api/auth/demo-users`.
- Create: `frontend/app/tests/unit/composables/useDemoUsers.test.ts` — tests the composable.
- Create: `frontend/app/components/auth/DemoUserSwitcherCard.vue` — one user card (name, org/team sub-line, role badge).
- Create: `frontend/app/components/auth/DemoUserSwitcherSheet.vue` — search box + grouped card list inside a `Sheet`.
- Create: `frontend/app/components/auth/DemoUserSwitcherButton.vue` — floating trigger button, owns the `Sheet` open state.
- Modify: `frontend/app/app.vue` — mount `DemoUserSwitcherButton` once, outside the `showShell` branch.
- Create: `frontend/app/tests/unit/components/DemoUserSwitcherSheet.test.ts` — search filtering + click-to-switch behavior.

---

## Task 1: Backend — `DemoUserResource` and `GET /api/auth/demo-users`

**Files:**
- Create: `backend/app/Http/Resources/DemoUserResource.php`
- Modify: `backend/app/Http/Controllers/Api/AuthController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Auth/DemoUserSwitchTest.php`

**Interfaces:**
- Produces: `DemoUserResource` — a `JsonResource` wrapping a `User`, output shape `{ id: int, name: string, email: string, role: string, role_label: string, organization: {id,code,name}|null, team: {id,organization_id,code,name}|null, bank: {id,code,name}|null }`.
- Produces: `AuthController::demoUsers(Request $request)` — returns `ApiResponse::success(['users' => DemoUserResource::collection($users)])` or `ApiResponse::forbidden(...)`.
- Produces route: `GET /api/auth/demo-users` (unauthenticated, `throttle:20,1`).

- [ ] **Step 1: Write the failing feature test for the list endpoint**

Create `backend/tests/Feature/Auth/DemoUserSwitchTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoUserSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
    }

    public function test_demo_users_list_returns_forbidden_when_flag_disabled(): void
    {
        config(['demo.allow_role_switch' => false]);

        $this->getJson('/api/auth/demo-users')->assertForbidden();
    }

    public function test_demo_users_list_returns_active_users_with_governance_identity(): void
    {
        config(['demo.allow_role_switch' => true]);

        $response = $this->getJson('/api/auth/demo-users')->assertOk();

        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();
        $entry = collect($response->json('data.users'))->firstWhere('id', $bankAdmin->id);

        $this->assertNotNull($entry);
        $this->assertSame($bankAdmin->name, $entry['name']);
        $this->assertSame($bankAdmin->email, $entry['email']);
        $this->assertSame(UserRole::BANK_ADMIN->value, $entry['role']);
        $this->assertSame(UserRole::BANK_ADMIN->label(), $entry['role_label']);
        $this->assertSame('commercial_banks', $entry['organization']['code']);
        $this->assertNotNull($entry['bank']);
    }

    public function test_demo_users_list_excludes_inactive_users(): void
    {
        config(['demo.allow_role_switch' => true]);

        $inactive = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->firstOrFail();
        $inactive->update(['is_active' => false]);

        $response = $this->getJson('/api/auth/demo-users')->assertOk();

        $ids = collect($response->json('data.users'))->pluck('id');
        $this->assertNotContains($inactive->id, $ids);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && php artisan test --filter=DemoUserSwitchTest`
Expected: FAIL — route `demo-users` not defined (404) or method not found.

- [ ] **Step 3: Create `DemoUserResource`**

Create `backend/app/Http/Resources/DemoUserResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemoUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $team = $this->resource->team();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'organization' => $this->organization ? [
                'id' => $this->organization->id,
                'code' => $this->organization->code,
                'name' => $this->organization->name,
            ] : null,
            'team' => $team ? [
                'id' => $team->id,
                'organization_id' => $team->organization_id,
                'code' => $team->code,
                'name' => $team->name,
            ] : null,
            'bank' => $this->bank ? [
                'id' => $this->bank->id,
                'code' => $this->bank->code,
                'name' => $this->bank->name,
            ] : null,
        ];
    }
}
```

- [ ] **Step 4: Add `demoUsers()` action to `AuthController`**

In `backend/app/Http/Controllers/Api/AuthController.php`, add `use App\Http\Resources\DemoUserResource;` to the imports (alongside the existing `use App\Http\Resources\UserResource;`), then add this method directly above `switchDemoRole` (around line 319, before the `#[OA\Post(path: '/api/auth/switch-demo-role', ...)]` attribute):

```php
    #[OA\Get(
        path: '/api/auth/demo-users',
        tags: ['Auth'],
        summary: 'List active demo users available for quick session switching',
        responses: [
            new OA\Response(response: 200, description: 'List of demo users'),
            new OA\Response(response: 403, description: 'Demo role switching disabled'),
        ]
    )]
    public function demoUsers(Request $request)
    {
        if (! config('demo.allow_role_switch', false)) {
            return ApiResponse::forbidden('Demo role switching is disabled.');
        }

        $users = User::query()
            ->where('is_active', true)
            ->with(['organization', 'teams', 'bank'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(['users' => DemoUserResource::collection($users)]);
    }

```

- [ ] **Step 5: Register the route**

In `backend/routes/api.php`, add the new route directly above the existing `switch-demo-role` line (line 56), inside the same unauthenticated `Route::prefix('auth')->group(...)` block:

```php
    Route::get('demo-users', [AuthController::class, 'demoUsers'])->middleware('throttle:20,1');
    Route::post('switch-demo-role', [AuthController::class, 'switchDemoRole'])->middleware('throttle:20,1');
```

(Only the `demo-users` line is new — `switch-demo-role` already exists; keep it as the anchor to place the new line before it.)

- [ ] **Step 6: Run the tests to verify they pass**

Run: `cd backend && php artisan test --filter=DemoUserSwitchTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Format touched PHP files**

Run: `cd backend && vendor/bin/pint app/Http/Controllers/Api/AuthController.php app/Http/Resources/DemoUserResource.php routes/api.php tests/Feature/Auth/DemoUserSwitchTest.php`

- [ ] **Step 8: Commit**

```bash
git add backend/app/Http/Controllers/Api/AuthController.php backend/app/Http/Resources/DemoUserResource.php backend/routes/api.php backend/tests/Feature/Auth/DemoUserSwitchTest.php
git commit -m "$(cat <<'EOF'
feat(auth): add demo users listing endpoint

Lists active users with governance identity (org/team/bank) for the
upcoming quick-switch UI. Gated by the existing demo.allow_role_switch
flag, same as switch-demo-role.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Backend — `POST /api/auth/switch-demo-user`

**Files:**
- Modify: `backend/app/Http/Controllers/Api/AuthController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Auth/DemoUserSwitchTest.php`

**Interfaces:**
- Consumes: private `issueSession(Request $request, User $user)` (already defined in `AuthController.php:366`, returns `ApiResponse::success` payload with `user`, `requires_mfa: false`, `mode`, `token`, `token_type`).
- Produces: `AuthController::switchDemoUser(Request $request)` — validates `user_id`, looks up an active `User`, calls `issueSession`.
- Produces route: `POST /api/auth/switch-demo-user` (unauthenticated, `throttle:20,1`).

- [ ] **Step 1: Write the failing feature tests**

Append to `backend/tests/Feature/Auth/DemoUserSwitchTest.php` (inside the class, after the last existing test method):

```php
    public function test_switch_demo_user_returns_forbidden_when_flag_disabled(): void
    {
        config(['demo.allow_role_switch' => false]);

        $user = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        $this->postJson('/api/auth/switch-demo-user', ['user_id' => $user->id])
            ->assertForbidden();
    }

    public function test_switch_demo_user_returns_not_found_for_unknown_id(): void
    {
        config(['demo.allow_role_switch' => true]);

        $this->postJson('/api/auth/switch-demo-user', ['user_id' => 999999])
            ->assertNotFound();
    }

    public function test_switch_demo_user_returns_not_found_for_inactive_user(): void
    {
        config(['demo.allow_role_switch' => true]);

        $inactive = User::query()->where('role', UserRole::SWIFT_OFFICER->value)->firstOrFail();
        $inactive->update(['is_active' => false]);

        $this->postJson('/api/auth/switch-demo-user', ['user_id' => $inactive->id])
            ->assertNotFound();
    }

    public function test_switch_demo_user_issues_session_for_target_user(): void
    {
        config(['demo.allow_role_switch' => true]);

        $target = User::query()->where('role', UserRole::COMMITTEE_DIRECTOR->value)->firstOrFail();

        $response = $this->postJson('/api/auth/switch-demo-user', ['user_id' => $target->id])
            ->assertOk();

        $response->assertJsonPath('data.user.id', $target->id);
        $response->assertJsonPath('data.requires_mfa', false);
        $this->assertAuthenticatedAs($target);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && php artisan test --filter=DemoUserSwitchTest`
Expected: FAIL — new 4 tests fail with 404 route-not-found for `/api/auth/switch-demo-user`. The 3 tests from Task 1 still PASS.

- [ ] **Step 3: Add `switchDemoUser()` action to `AuthController`**

In `backend/app/Http/Controllers/Api/AuthController.php`, add this method directly after `switchDemoRole` (after the closing brace at line 364, before `private function issueSession`):

```php

    #[OA\Post(
        path: '/api/auth/switch-demo-user',
        tags: ['Auth'],
        summary: 'Switch authenticated session to a specific demo user by id',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Session switched'),
            new OA\Response(response: 403, description: 'Demo role switching disabled'),
            new OA\Response(response: 404, description: 'No active demo user found for the given id'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function switchDemoUser(Request $request)
    {
        if (! config('demo.allow_role_switch', false)) {
            return ApiResponse::forbidden('Demo role switching is disabled.');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $user = User::query()
            ->where('id', $validated['user_id'])
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return ApiResponse::notFound('No active demo account found for the selected user.');
        }

        return $this->issueSession($request, $user);
    }
```

- [ ] **Step 4: Register the route**

In `backend/routes/api.php`, add directly below the `switch-demo-role` line:

```php
    Route::post('switch-demo-role', [AuthController::class, 'switchDemoRole'])->middleware('throttle:20,1');
    Route::post('switch-demo-user', [AuthController::class, 'switchDemoUser'])->middleware('throttle:20,1');
```

(Only the `switch-demo-user` line is new.)

- [ ] **Step 5: Run the tests to verify they pass**

Run: `cd backend && php artisan test --filter=DemoUserSwitchTest`
Expected: PASS (7 tests total)

- [ ] **Step 6: Format touched PHP files**

Run: `cd backend && vendor/bin/pint app/Http/Controllers/Api/AuthController.php routes/api.php tests/Feature/Auth/DemoUserSwitchTest.php`

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Controllers/Api/AuthController.php backend/routes/api.php backend/tests/Feature/Auth/DemoUserSwitchTest.php
git commit -m "$(cat <<'EOF'
feat(auth): add switch-demo-user endpoint for per-user quick login

Extends the existing demo role-switch mechanism to target one specific
user by id, reusing the same issueSession() helper as login/verify-otp/
switch-demo-role. Gated by the same demo.allow_role_switch flag.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Frontend — runtime config flag and `DemoUser` type

**Files:**
- Modify: `frontend/nuxt.config.ts`
- Modify: `frontend/app/types/models.ts`

**Interfaces:**
- Produces: `useRuntimeConfig().public.demoUserSwitch: boolean`.
- Produces: `DemoUser` interface — `{ id: number, name: string, email: string, role: UserRole, role_label: string, organization: GovernanceIdentity | null, team: GovernanceIdentity | null, bank: GovernanceBank | null }`.

This task has no test of its own (pure config/type addition consumed by later tasks' tests).

- [ ] **Step 1: Add the runtime config flag**

In `frontend/nuxt.config.ts`, inside `runtimeConfig.public` (after line 66, the `visualBypassRole` line), add:

```ts
      demoUserSwitch: process.env.NUXT_PUBLIC_DEMO_USER_SWITCH === 'true',
```

So the block reads (only the new line added, existing lines unchanged):

```ts
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
      demoMode: process.env.NUXT_PUBLIC_DEMO_MODE === 'true',
      visualBypass: process.env.NUXT_PUBLIC_VISUAL_BYPASS === 'true',
      visualBypassRole: process.env.NUXT_PUBLIC_VISUAL_BYPASS_ROLE || 'CBY_ADMIN',
      demoUserSwitch: process.env.NUXT_PUBLIC_DEMO_USER_SWITCH === 'true',
      googleFontsApiKey: process.env.NUXT_PUBLIC_GOOGLE_FONTS_API_KEY || '',
      inactivityTimeoutMs: Number(process.env.NUXT_PUBLIC_INACTIVITY_TIMEOUT_MS) || 900_000,
      inactivityWarningMs: Number(process.env.NUXT_PUBLIC_INACTIVITY_WARNING_MS) || 120_000,
    },
  },
```

- [ ] **Step 2: Add the `DemoUser` type**

In `frontend/app/types/models.ts`, add directly after the `GovernanceBank` interface (after line 118):

```ts
export interface DemoUser {
  id: number
  name: string
  email: string
  role: UserRole
  role_label: string
  organization: GovernanceIdentity | null
  team: GovernanceIdentity | null
  bank: GovernanceBank | null
}
```

`UserRole` is already imported in this file (used elsewhere for `AuthUser.role`) — verify the import exists; if not, it must already be imported since `AuthUser` at line 52 uses `role: UserRole`.

- [ ] **Step 3: Typecheck**

Run: `cd frontend && pnpm typecheck`
Expected: PASS, no new type errors.

- [ ] **Step 4: Commit**

```bash
git add frontend/nuxt.config.ts frontend/app/types/models.ts
git commit -m "$(cat <<'EOF'
feat(frontend): add demo user switch runtime flag and DemoUser type

Mirrors the backend demo.allow_role_switch gate on the client so the
upcoming quick-switch button never mounts when the feature is off.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Frontend — `switchDemoUser` store action

**Files:**
- Modify: `frontend/app/stores/auth.store.ts`
- Test: `frontend/app/tests/unit/stores/auth.store.test.ts`

**Interfaces:**
- Consumes: `DemoUser` type from Task 3 only for id lookup (actual method signature takes `userId: number`, matching the backend's `user_id` field).
- Produces: `authStore.switchDemoUser(userId: number): Promise<void>` — sets `this.user`, `this.isAuthenticated`, `this.isLoggingOut = false`, persists auth mode, syncs avatar cache, sets `localStorage['yfh-authenticated']`; throws `{ statusCode: 403, data: {...} }` if the returned user is inactive (defensive — backend already filters, mirrors existing actions' shape).

- [ ] **Step 1: Write the failing test**

In `frontend/app/tests/unit/stores/auth.store.test.ts`, find the existing `describe('useAuthStore', ...)` block. Add a new nested `describe` block after the `describe('password recovery CSRF', ...)` block (locate it by searching for that string, then insert the new block as a sibling immediately after its closing `})`):

```ts
  describe('switchDemoUser', () => {
    it('logs in as the target user and persists auth state', async () => {
      const targetUser: AuthUser = {
        id: 42,
        name: 'Nada Al-Kibsi',
        email: 'exec2@cby.gov.ye',
        role: UserRole.EXECUTIVE_MEMBER,
        bank_id: null,
        bank_name_ar: null,
        bank_name_en: null,
        is_active: true,
      }

      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'Login successful.',
        data: {
          user: targetUser,
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })

      const store = useAuthStore()
      await store.switchDemoUser(42)

      expect(mockFetch).toHaveBeenCalledWith(
        '/api/auth/switch-demo-user',
        expect.objectContaining({
          method: 'POST',
          body: { user_id: 42 },
        }),
      )
      expect(store.user).toEqual(targetUser)
      expect(store.isAuthenticated).toBe(true)
    })

    it('throws when the returned user is inactive', async () => {
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'Login successful.',
        data: {
          user: { ...DEMO_USER, is_active: false },
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })

      const store = useAuthStore()
      await expect(store.switchDemoUser(1)).rejects.toMatchObject({ statusCode: 403 })
    })
  })
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/stores/auth.store.test.ts -t "switchDemoUser"`
Expected: FAIL — `store.switchDemoUser is not a function`.

- [ ] **Step 3: Implement `switchDemoUser` in the store**

In `frontend/app/stores/auth.store.ts`, add this action directly after `switchDemoRole` (after the closing `},` at line 471, before `async verifyOtp`):

```ts
    async switchDemoUser(userId: number): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      const xsrfToken = this.getXsrfToken()

      const response = await $fetch<ApiResponse<LoginResponseData>>('/api/auth/switch-demo-user', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        },
        body: { user_id: userId },
      })

      if (!response.data.user?.is_active) {
        throw {
          statusCode: 403,
          data: { success: false, message: 'حساب العرض التوضيحي غير مفعل.' },
        }
      }

      this.user = response.data.user
      this.isAuthenticated = true
      this.isLoggingOut = false
      markLogoutInProgress(false)
      this.persistAuthMode({ mode: response.data.mode, token: response.data.token })
      syncAvatarCache(this.user)
      if (import.meta.client) {
        localStorage.setItem('yfh-authenticated', '1')
      }
    },

```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/stores/auth.store.test.ts -t "switchDemoUser"`
Expected: PASS (2 tests)

- [ ] **Step 5: Run the full auth store test file to check for regressions**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/stores/auth.store.test.ts`
Expected: PASS (all tests, including pre-existing ones)

- [ ] **Step 6: Lint and format the touched files**

Run: `cd frontend && pnpm exec eslint app/stores/auth.store.ts app/tests/unit/stores/auth.store.test.ts && pnpm exec prettier app/stores/auth.store.ts app/tests/unit/stores/auth.store.test.ts --check`

- [ ] **Step 7: Commit**

```bash
git add frontend/app/stores/auth.store.ts frontend/app/tests/unit/stores/auth.store.test.ts
git commit -m "$(cat <<'EOF'
feat(auth): add switchDemoUser store action

Mirrors switchDemoRole but targets one specific user id, backing the
upcoming quick-switch UI where each demo user gets its own card.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Frontend — `useDemoUsers` composable

**Files:**
- Create: `frontend/app/composables/useDemoUsers.ts`
- Test: `frontend/app/tests/unit/composables/useDemoUsers.test.ts`

**Interfaces:**
- Consumes: `DemoUser` type from Task 3.
- Produces: `useDemoUsers()` returning `{ users: Ref<DemoUser[]>, loading: Ref<boolean>, error: Ref<string | null>, fetchDemoUsers: () => Promise<void> }`.

- [ ] **Step 1: Write the failing test**

Create `frontend/app/tests/unit/composables/useDemoUsers.test.ts`. First check an existing composable test for the exact Nuxt-global stubbing pattern used in this codebase — use `frontend/app/tests/unit/composables/useEngineStagePath.test.ts` as the reference for `vi.stubGlobal` conventions if it stubs `$fetch`/`useRuntimeConfig`; otherwise follow the pattern below (matches `auth.store.test.ts`):

```ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { UserRole } from '../../../types/enums'
import type { DemoUser } from '../../../types/models'

const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({
  public: { apiBase: 'http://localhost:8000' },
}))

const { useDemoUsers } = await import('../../../composables/useDemoUsers')

const SAMPLE_USERS: DemoUser[] = [
  {
    id: 1,
    name: 'Fatima Al-Maqtari',
    email: 'admin@ybrd.com.ye',
    role: UserRole.BANK_ADMIN,
    role_label: 'مسؤول البنك / Bank Admin',
    organization: { id: 1, code: 'commercial_banks', name: 'Commercial Banks' },
    team: { id: 1, organization_id: 1, code: 'bank_admin', name: 'Bank Admin' },
    bank: { id: 1, code: 'ybrd', name: 'YBRD' },
  },
]

describe('useDemoUsers', () => {
  beforeEach(() => {
    mockFetch.mockReset()
  })

  it('starts with an empty list and not loading', () => {
    const { users, loading, error } = useDemoUsers()
    expect(users.value).toEqual([])
    expect(loading.value).toBe(false)
    expect(error.value).toBeNull()
  })

  it('fetches and stores the demo user list', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      message: 'OK',
      data: { users: SAMPLE_USERS },
    })

    const { users, loading, fetchDemoUsers } = useDemoUsers()
    const promise = fetchDemoUsers()
    expect(loading.value).toBe(true)
    await promise

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/auth/demo-users',
      expect.objectContaining({ baseURL: 'http://localhost:8000' }),
    )
    expect(users.value).toEqual(SAMPLE_USERS)
    expect(loading.value).toBe(false)
  })

  it('sets an error message when the fetch fails', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network error'))

    const { error, fetchDemoUsers } = useDemoUsers()
    await fetchDemoUsers()

    expect(error.value).not.toBeNull()
  })
})
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/composables/useDemoUsers.test.ts`
Expected: FAIL — cannot find module `../../../composables/useDemoUsers`.

- [ ] **Step 3: Implement the composable**

Create `frontend/app/composables/useDemoUsers.ts`:

```ts
import { ref } from 'vue'
import type { ApiResponse, DemoUser } from '../types/models'

interface DemoUsersResponseData {
  users: DemoUser[]
}

export function useDemoUsers() {
  const users = ref<DemoUser[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchDemoUsers(): Promise<void> {
    const config = useRuntimeConfig()
    const baseURL = config.public.apiBase as string

    loading.value = true
    error.value = null

    try {
      const response = await $fetch<ApiResponse<DemoUsersResponseData>>('/api/auth/demo-users', {
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      users.value = response.data.users
    } catch {
      error.value = 'تعذّر تحميل قائمة المستخدمين. حاول مجدداً.'
    } finally {
      loading.value = false
    }
  }

  return { users, loading, error, fetchDemoUsers }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/composables/useDemoUsers.test.ts`
Expected: PASS (3 tests)

- [ ] **Step 5: Lint and format**

Run: `cd frontend && pnpm exec eslint app/composables/useDemoUsers.ts app/tests/unit/composables/useDemoUsers.test.ts && pnpm exec prettier app/composables/useDemoUsers.ts app/tests/unit/composables/useDemoUsers.test.ts --check`

- [ ] **Step 6: Commit**

```bash
git add frontend/app/composables/useDemoUsers.ts frontend/app/tests/unit/composables/useDemoUsers.test.ts
git commit -m "$(cat <<'EOF'
feat(auth): add useDemoUsers composable

Fetches the active demo user list for the quick-switch sheet, keeping
fetch/loading/error state out of the component per architecture rules.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Frontend — `DemoUserSwitcherCard.vue`

**Files:**
- Create: `frontend/app/components/auth/DemoUserSwitcherCard.vue`

**Interfaces:**
- Consumes: `DemoUser` type from Task 3, `ROLE_LABELS` is NOT used here — `role_label` comes from the backend payload directly (already includes bilingual label via `UserRole::label()`).
- Produces: emits `select` when clicked (parent owns the switch call), following the same emit-based pattern as `LoginSavedAccountCard.vue`.

No dedicated unit test for this presentational component — it is covered by the `DemoUserSwitcherSheet.vue` integration test in Task 8 (per project convention: `LoginSavedAccountCard.vue` also has no standalone test file, only its parent does).

- [ ] **Step 1: Create the component**

Create `frontend/app/components/auth/DemoUserSwitcherCard.vue`:

```vue
<script setup lang="ts">
import { Card } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { DemoUser } from '~/types/models'

const props = defineProps<{
  user: DemoUser
}>()

const emit = defineEmits<{
  select: []
}>()

defineOptions({
  inheritAttrs: false,
})

const subLine = props.user.team?.name ?? props.user.organization?.name ?? ''
</script>

<template>
  <Card
    class="flex cursor-pointer items-center gap-3 border-0 p-3 shadow transition-shadow hover:shadow-md"
    role="button"
    tabindex="0"
    :aria-label="`تسجيل الدخول كـ ${user.name}`"
    @click="emit('select')"
    @keydown.enter="emit('select')"
    @keydown.space.prevent="emit('select')"
  >
    <div class="min-w-0 flex-1 text-start">
      <p class="text-foreground truncate text-sm leading-tight font-semibold">
        {{ user.name }}
      </p>
      <p class="text-muted-foreground mt-0.5 truncate text-xs">
        {{ user.email }}
      </p>
      <p v-if="subLine" class="text-muted-foreground/70 truncate text-xs">
        {{ subLine }}
      </p>
    </div>

    <Badge variant="secondary" class="shrink-0 text-xs leading-none">
      {{ user.role_label }}
    </Badge>
  </Card>
</template>
```

- [ ] **Step 2: Lint and format**

Run: `cd frontend && pnpm exec eslint app/components/auth/DemoUserSwitcherCard.vue && pnpm exec prettier app/components/auth/DemoUserSwitcherCard.vue --check`

- [ ] **Step 3: Commit**

```bash
git add frontend/app/components/auth/DemoUserSwitcherCard.vue
git commit -m "$(cat <<'EOF'
feat(auth): add DemoUserSwitcherCard component

Presentational card (name, email, org/team, role badge) for one demo
user row in the quick-switch sheet.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Frontend — `DemoUserSwitcherSheet.vue`

**Files:**
- Create: `frontend/app/components/auth/DemoUserSwitcherSheet.vue`
- Test: `frontend/app/tests/unit/components/DemoUserSwitcherSheet.test.ts`

**Interfaces:**
- Consumes: `useDemoUsers()` from Task 5 (`users`, `loading`, `error`, `fetchDemoUsers`), `useAuthStore().switchDemoUser(userId)` from Task 4, `DemoUserSwitcherCard` from Task 6 (`select` emit), `DemoUser` type from Task 3.
- Produces: `v-model:open` boolean prop (standard shadcn `Sheet` pattern) — parent (`DemoUserSwitcherButton`) controls visibility.

- [ ] **Step 1: Write the failing test**

Create `frontend/app/tests/unit/components/DemoUserSwitcherSheet.test.ts`:

```ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { UserRole } from '../../../types/enums'
import type { DemoUser } from '../../../types/models'

const mockFetchDemoUsers = vi.fn()
const mockUsers = { value: [] as DemoUser[] }
const mockLoading = { value: false }
const mockError = { value: null as string | null }

vi.mock('../../../composables/useDemoUsers', () => ({
  useDemoUsers: () => ({
    users: mockUsers,
    loading: mockLoading,
    error: mockError,
    fetchDemoUsers: mockFetchDemoUsers,
  }),
}))

const mockSwitchDemoUser = vi.fn()
vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    switchDemoUser: mockSwitchDemoUser,
  }),
}))

vi.stubGlobal('navigateTo', vi.fn())

const SAMPLE_USERS: DemoUser[] = [
  {
    id: 1,
    name: 'Fatima Al-Maqtari',
    email: 'admin@ybrd.com.ye',
    role: UserRole.BANK_ADMIN,
    role_label: 'مسؤول البنك / Bank Admin',
    organization: { id: 1, code: 'commercial_banks', name: 'Commercial Banks' },
    team: { id: 1, organization_id: 1, code: 'bank_admin', name: 'Bank Admin' },
    bank: { id: 1, code: 'ybrd', name: 'YBRD' },
  },
  {
    id: 2,
    name: 'Nada Al-Kibsi',
    email: 'exec2@cby.gov.ye',
    role: UserRole.EXECUTIVE_MEMBER,
    role_label: 'عضو تنفيذي / Executive Committee Member',
    organization: { id: 2, code: 'national_committee', name: 'National Committee' },
    team: { id: 2, organization_id: 2, code: 'executive', name: 'Executive' },
    bank: null,
  },
]

const { default: DemoUserSwitcherSheet } = await import(
  '../../../components/auth/DemoUserSwitcherSheet.vue'
)

describe('DemoUserSwitcherSheet', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockFetchDemoUsers.mockReset()
    mockSwitchDemoUser.mockReset()
    mockUsers.value = [...SAMPLE_USERS]
    mockLoading.value = false
    mockError.value = null
  })

  it('filters the visible users by the search query', async () => {
    const wrapper = mount(DemoUserSwitcherSheet, {
      props: { open: true, 'onUpdate:open': () => {} },
    })

    expect(wrapper.text()).toContain('Fatima Al-Maqtari')
    expect(wrapper.text()).toContain('Nada Al-Kibsi')

    const searchInput = wrapper.find('input[type="text"], input:not([type])')
    await searchInput.setValue('Nada')

    expect(wrapper.text()).not.toContain('Fatima Al-Maqtari')
    expect(wrapper.text()).toContain('Nada Al-Kibsi')
  })

  it('calls switchDemoUser with the clicked user id', async () => {
    mockSwitchDemoUser.mockResolvedValueOnce(undefined)

    const wrapper = mount(DemoUserSwitcherSheet, {
      props: { open: true, 'onUpdate:open': () => {} },
    })

    const card = wrapper.findAll('[role="button"]').find((el) => el.text().includes('Nada'))
    await card?.trigger('click')

    expect(mockSwitchDemoUser).toHaveBeenCalledWith(2)
  })
})
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/components/DemoUserSwitcherSheet.test.ts`
Expected: FAIL — cannot find module `../../../components/auth/DemoUserSwitcherSheet.vue`.

- [ ] **Step 3: Implement the component**

Create `frontend/app/components/auth/DemoUserSwitcherSheet.vue`:

```vue
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/components/ui/sheet'
import { InputGroup, InputGroupInput, InputGroupAddon } from '@/components/ui/input-group'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertTitle, AlertDescription, AlertAction } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Empty, EmptyMedia, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import { AlertCircle, Search, Users as UsersIcon } from 'lucide-vue-next'
import DemoUserSwitcherCard from '@/components/auth/DemoUserSwitcherCard.vue'
import { useDemoUsers } from '@/composables/useDemoUsers'
import { useAuthStore } from '@/stores/auth.store'
import type { DemoUser } from '~/types/models'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { users, loading, error, fetchDemoUsers } = useDemoUsers()
const authStore = useAuthStore()
const searchQuery = ref('')
const switchingUserId = ref<number | null>(null)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      searchQuery.value = ''
      void fetchDemoUsers()
    }
  },
)

function matchesQuery(user: DemoUser, query: string): boolean {
  const haystack = [
    user.name,
    user.email,
    user.role_label,
    user.organization?.name ?? '',
    user.team?.name ?? '',
  ]
    .join(' ')
    .toLowerCase()
  return haystack.includes(query.toLowerCase())
}

const filteredUsers = computed(() => {
  const query = searchQuery.value.trim()
  const list = query ? users.value.filter((user) => matchesQuery(user, query)) : users.value
  return list
})

const groupedUsers = computed(() => {
  const groups = new Map<string, DemoUser[]>()
  for (const user of filteredUsers.value) {
    const key = user.organization?.name ?? 'أخرى'
    const bucket = groups.get(key) ?? []
    bucket.push(user)
    groups.set(key, bucket)
  }
  return Array.from(groups.entries())
})

async function handleSelect(user: DemoUser): Promise<void> {
  switchingUserId.value = user.id
  try {
    await authStore.switchDemoUser(user.id)
    emit('update:open', false)
    await navigateTo('/dashboard')
  } catch {
    toast.error(`تعذّر تسجيل الدخول كـ ${user.name}`)
  } finally {
    switchingUserId.value = null
  }
}
</script>

<template>
  <Sheet :open="open" @update:open="(value) => emit('update:open', value)">
    <SheetContent side="start" class="flex w-[420px] flex-col gap-4">
      <SheetHeader>
        <SheetTitle>تبديل المستخدم السريع</SheetTitle>
        <SheetDescription>اختر حساباً لتسجيل الدخول به مباشرة دون إعادة المصادقة</SheetDescription>
      </SheetHeader>

      <InputGroup>
        <InputGroupAddon align="inline-start">
          <Search class="h-4 w-4" />
        </InputGroupAddon>
        <InputGroupInput v-model="searchQuery" placeholder="ابحث بالاسم أو البريد أو الدور…" />
      </InputGroup>

      <div class="flex-1 space-y-4 overflow-y-auto">
        <template v-if="loading">
          <Skeleton v-for="n in 4" :key="n" class="h-16 w-full rounded-xl" />
        </template>

        <Alert v-else-if="error" variant="destructive" role="alert">
          <AlertCircle class="h-4 w-4" />
          <AlertTitle>خطأ في التحميل</AlertTitle>
          <AlertDescription>{{ error }}</AlertDescription>
          <AlertAction>
            <Button variant="outline" size="sm" @click="fetchDemoUsers">إعادة المحاولة</Button>
          </AlertAction>
        </Alert>

        <Empty v-else-if="groupedUsers.length === 0">
          <EmptyMedia variant="icon">
            <UsersIcon />
          </EmptyMedia>
          <EmptyTitle>لا يوجد مستخدمون</EmptyTitle>
          <EmptyDescription>لا توجد نتائج مطابقة لبحثك.</EmptyDescription>
        </Empty>

        <div v-else v-for="[groupName, groupUsers] in groupedUsers" :key="groupName" class="space-y-2">
          <h3 class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
            {{ groupName }}
          </h3>
          <DemoUserSwitcherCard
            v-for="user in groupUsers"
            :key="user.id"
            :user="user"
            @select="handleSelect(user)"
          />
        </div>
      </div>
    </SheetContent>
  </Sheet>
</template>
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/components/DemoUserSwitcherSheet.test.ts`
Expected: PASS (2 tests). If the `Sheet` component teleports content in a way Vitest cannot query (e.g. `SheetContent` renders via a `Teleport` to `document.body` that `mount()` doesn't attach), and the search/click assertions cannot find the elements: per `AGENTS.md`, do not downgrade `Sheet`/`InputGroup`/`Card` to raw HTML to make the test pass — instead mark the specific failing assertions with `it.skip` and note the teleport limitation in a one-line comment above the skipped test.

- [ ] **Step 5: Lint and format**

Run: `cd frontend && pnpm exec eslint app/components/auth/DemoUserSwitcherSheet.vue app/tests/unit/components/DemoUserSwitcherSheet.test.ts && pnpm exec prettier app/components/auth/DemoUserSwitcherSheet.vue app/tests/unit/components/DemoUserSwitcherSheet.test.ts --check`

- [ ] **Step 6: Commit**

```bash
git add frontend/app/components/auth/DemoUserSwitcherSheet.vue frontend/app/tests/unit/components/DemoUserSwitcherSheet.test.ts
git commit -m "$(cat <<'EOF'
feat(auth): add DemoUserSwitcherSheet with search and org grouping

Fetches the demo user list on open, filters client-side by name/email/
role/org, groups by organization, and switches session on card click.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: Frontend — `DemoUserSwitcherButton.vue` and mount in `app.vue`

**Files:**
- Create: `frontend/app/components/auth/DemoUserSwitcherButton.vue`
- Modify: `frontend/app/app.vue`

**Interfaces:**
- Consumes: `DemoUserSwitcherSheet` from Task 7 (`open` prop, `update:open` emit), `useRuntimeConfig().public.demoUserSwitch` from Task 3.
- Produces: nothing consumed further — this is the top-level mount point.

No dedicated unit test — this component is a thin visibility/open-state wrapper; behavior is covered by Task 7's sheet test plus manual browser verification in Step 4 below (per the project's UI verification rule: "start the dev server and use the feature in a browser before reporting the task as complete").

- [ ] **Step 1: Create the button component**

Create `frontend/app/components/auth/DemoUserSwitcherButton.vue`:

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Users } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import DemoUserSwitcherSheet from '@/components/auth/DemoUserSwitcherSheet.vue'

const config = useRuntimeConfig()
const isEnabled = Boolean(config.public.demoUserSwitch)
const sheetOpen = ref(false)
</script>

<template>
  <template v-if="isEnabled">
    <Button
      variant="secondary"
      size="icon"
      class="fixed start-4 bottom-4 z-50 shadow-lg"
      aria-label="تبديل المستخدم السريع"
      @click="sheetOpen = true"
    >
      <Users class="h-5 w-5" />
    </Button>
    <DemoUserSwitcherSheet v-model:open="sheetOpen" />
  </template>
</template>
```

- [ ] **Step 2: Mount it in `app.vue`**

In `frontend/app/app.vue`, add the import alongside the existing component imports (after the `import { useThemingStore } from '@/stores/theming.store'` line):

```ts
import DemoUserSwitcherButton from '@/components/auth/DemoUserSwitcherButton.vue'
```

Then add `<DemoUserSwitcherButton />` inside the `<ConfigProvider>` template, as a sibling before `<AppShell>` (so it renders regardless of `showShell`):

```vue
  <ConfigProvider :dir="appDir">
    <NuxtLoadingIndicator color="var(--primary)" />
    <NuxtRouteAnnouncer />
    <Toaster :position="toasterPosition" rich-colors />
    <DemoUserSwitcherButton />

    <AppShell v-if="showShell">
```

(Only the `<DemoUserSwitcherButton />` line is new; everything else in this block is unchanged.)

- [ ] **Step 3: Typecheck**

Run: `cd frontend && pnpm typecheck`
Expected: PASS

- [ ] **Step 4: Manual browser verification**

Set `NUXT_PUBLIC_DEMO_USER_SWITCH=true` and `APP_DEMO_ROLE_SWITCH=true` in the local `.env` files (frontend and backend respectively), start both dev servers, then use `playwright-cli`:

```bash
playwright-cli open
playwright-cli goto http://localhost:3000/login
playwright-cli snapshot
```

Confirm the floating button renders bottom-start on the login page. Click it, confirm the sheet opens with a grouped, searchable user list. Type a partial name into the search box, confirm the list narrows. Click a user card, confirm the app navigates to `/dashboard` logged in as that user (check the header/profile shows the selected user's name). Then set `NUXT_PUBLIC_DEMO_USER_SWITCH=false`, restart the frontend dev server, reload `/login`, and confirm the button is absent from the DOM (`playwright-cli snapshot` shows no button element).

```bash
playwright-cli close
```

- [ ] **Step 5: Lint and format**

Run: `cd frontend && pnpm exec eslint app/components/auth/DemoUserSwitcherButton.vue app/app.vue && pnpm exec prettier app/components/auth/DemoUserSwitcherButton.vue app/app.vue --check`

- [ ] **Step 6: Commit**

```bash
git add frontend/app/components/auth/DemoUserSwitcherButton.vue frontend/app/app.vue
git commit -m "$(cat <<'EOF'
feat(auth): add floating demo user switcher button

Mounts once in app.vue so it survives the login/authenticated
transition. Only renders when the demoUserSwitch runtime flag is on,
keeping it out of the DOM entirely in environments without the flag.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Self-Review Notes

- **Spec coverage:** Every section of the design spec maps to a task — backend endpoints (Tasks 1–2), runtime flag + types (Task 3), store action (Task 4), composable (Task 5), card component (Task 6), sheet with search + grouping (Task 7), floating button + mount (Task 8). Testing section covered by each task's own test steps.
- **Placeholder scan:** No TBD/TODO markers; every code step has complete, runnable code.
- **Type consistency:** `DemoUser` (Task 3) used identically across `useDemoUsers` (Task 5), `DemoUserSwitcherCard` (Task 6), `DemoUserSwitcherSheet` (Task 7). `switchDemoUser(userId: number)` signature (Task 4) matches the call site in Task 7 (`authStore.switchDemoUser(user.id)`). Backend `DemoUserResource` field names (`role_label`, `organization`, `team`, `bank`) match the frontend `DemoUser` interface field-for-field.
