# WP-14 — Legacy Cleanup Wave Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate every live frontend consumer to V1/canonical endpoints, then remove legacy routes, dead modules, and dropped-table references — producing a grep-clean codebase with one API namespace direction.

**Architecture:** Strict D23-N13 sequencing: build missing V1 replacements → migrate consumers + tests → grep-verify zero legacy traffic → delete legacy routes/controllers → stage R9 envelope on rewritten endpoints → full regression + release notes. Never delete a legacy endpoint before its consumer is migrated and verified.

**Tech Stack:** PHP 8.2+, Laravel 11, Sanctum, MySQL. Nuxt 4 + Vue + TypeScript frontend.

## Investigation findings (the starting state)

| Area | V1 (keep) | Legacy (remove after migration) | Frontend consumer |
|---|---|---|---|
| Users | `GET/POST/PUT /api/v1/users`, `reset-password`, `reset-mfa`, `reset-pin` | `Route::apiResource('users', UserController)` + recovery routes at `/api/users/*` | `useUsers.ts` → `staff.vue`, `AccountRecoveryDialog.vue` |
| Banks | `GET/POST/PUT /api/v1/banks`, activate/deactivate/destroy | `Route::apiResource('banks', BankController)` + `admin/reset-password` | `useBanks.ts` → `admin/banks.vue`, `merchants.vue` |
| Audit logs | `GET /api/v1/audit-logs`, export, show | `GET /api/audit`, `/api/audit/stats`, `/duplicates`, `/risk-indicators` | `useAudit.ts` legacy fns → `audit.vue` widgets + legacy table |
| Report presets | **missing** — must promote | `GET/POST/DELETE /api/report-presets` | `useReports.ts` |
| Notifications | `GET /api/v1/notifications/*` | duplicate `NotificationController` at `/api/notifications` | `useNotifications.ts` already V1 ✅ |
| Reports | `GET /api/v1/reports/*` + exports | `ReportController` workflow/voting/bank routes | no live frontend consumer found |
| Demo switch | — | `auth/demo-users`, `switch-demo-user`, `switch-demo-role` (config-gated today) | `auth.store.ts` |
| Dead modules | — | `MerchantController` (no route), `document-types`, legacy notifications | `useDocumentTypes.ts` (dead) |

**Already shipped (verify grep, do not re-implement):**
- `engine_claim:` cache mirror — WP-5 CL-7; grep expects zero hits
- WP-11 placebo settings + email blob — WP-14 only purges any stragglers
- `useNotifications` on `/api/v1/notifications` — purge legacy controller only

**WP-10 RM-3 gate (`users.role` column drop):** RM-3 was **deferred** per wave plan. Task 13 is **conditional** — run only when `grep -r "users\.role\|->role\b" backend/app` shows no live readers outside audit snapshots and `User::legacyRole()` fallback. If RM-3 not complete, skip Task 13 and record deferral in progress ledger.

## Global Constraints

- **D23-N13 sequence is mandatory:** migrate consumers BEFORE deleting routes. No half-removed modules.
- **Migration-first, removal-second:** every live screen must work on V1 before legacy route deletion.
- **Demo routes absent from production:** routes not registered when `APP_ENV` ∉ `{local, staging}` — not a disabled 404 handler.
- **Demo audit:** every demo switch in allowed envs logs actor, target, timestamp, IP, environment.
- **`.env.example`:** `APP_DEMO_ROLE_SWITCH=false` stays.
- **Report presets:** user-scoped saved filters only; never bypass data scope (WP-7).
- **API envelope (R9):** rich shape `{ error: { code, message, fields }, request_id }` on endpoints rewritten in this wave; `extractApiErrorMessage`/`extractApiErrorCode` tolerate both shapes during transition.
- **Signed commits only.** Never `--no-gpg-sign`/`--no-verify`. Conventional commits with scope `backend`, `frontend`, `docs`, or `repo`. Co-Author trailer mandatory.
- **TDD:** failing test first for every behavior change.
- **Verification ladder:** focused tests per task. Full suites only at Task 14 gate.
- **Known-red baseline:** report unrelated failures; do not chase.

---

## File Structure

**Backend — create:**
- `backend/app/Http/Controllers/Api/V1/ReportPresetController.php` — user-scoped presets (promoted from legacy)
- `backend/tests/Feature/V1/ReportPresetTest.php`
- `backend/tests/Feature/Legacy/LegacyRouteAbsentTest.php` — post-purge 404 assertions
- `backend/tests/Feature/Auth/DemoRouteEnvironmentGateTest.php`
- `backend/database/migrations/2026_07_07_200001_drop_users_role_column.php` — **Task 13 only, gated**
- `docs/release-notes/2026-07-wp14-legacy-cleanup.md`

**Backend — modify:**
- `backend/routes/api.php` — register V1 presets; env-gate demo routes; remove legacy routes (Tasks 10–11)
- `backend/app/Http/Controllers/Api/AuthController.php` — demo env gate + audit logging
- `backend/config/demo.php` — add `allowed_environments` list
- `backend/app/Services/Workflow/WorkflowDesignerService.php` — fix `stageIsBound()` (L-5)
- `backend/app/Services/Workflow/FieldDesignerService.php` — fix `fieldIsUsed()` (L-5)

**Backend — delete (Tasks 10–11, after consumer migration verified):**
- `backend/app/Http/Controllers/Api/UserController.php` (partial legacy)
- `backend/app/Http/Controllers/Api/BankController.php`
- `backend/app/Http/Controllers/Api/AuditController.php`
- `backend/app/Http/Controllers/Api/NotificationController.php`
- `backend/app/Http/Controllers/Api/ReportController.php`
- `backend/app/Http/Controllers/Api/ReportPresetsController.php`
- `backend/app/Http/Controllers/Api/MerchantController.php`
- `backend/app/Http/Controllers/Api/DocumentTypeController.php`
- `backend/app/Models/DocumentType.php` (if exists)
- Related legacy feature tests under `backend/tests/Feature/` pointing at removed routes

**Frontend — modify:**
- `frontend/app/composables/useUsers.ts` — `/api/v1/users`
- `frontend/app/composables/useBanks.ts` — `/api/v1/banks`
- `frontend/app/composables/useAudit.ts` — remove legacy paths; engine paths stay
- `frontend/app/composables/useReports.ts` — `/api/v1/report-presets`
- `frontend/app/pages/audit.vue` — drop or rebuild widget panels (Task 2 flag)
- `frontend/app/stores/auth.store.ts` — demo calls guarded by env flag
- `frontend/app/utils/apiErrors.ts` — envelope tolerance (Task 7)
- `frontend/app/plugins/00.visual-bypass-api.client.ts` — remove legacy stub paths
- `frontend/app/tests/unit/composables/useUsers.test.ts`
- `frontend/app/tests/unit/composables/useBanks.test.ts`
- `frontend/app/tests/unit/composables/useAudit.test.ts`
- `frontend/app/tests/unit/composables/useReports.test.ts`

**Frontend — delete (Task 11):**
- `frontend/app/composables/useDocumentTypes.ts`
- `frontend/app/tests/unit/composables/useDocumentTypes.test.ts`

**Ledger:**
- `.superpowers/sdd/progress.md`

---

## Task 1: V1 report presets (L-1 / D23-N11)

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/ReportPresetController.php`
- Modify: `backend/routes/api.php` (inside `v1` group, after reports block ~line 227)
- Test: `backend/tests/Feature/V1/ReportPresetTest.php`

**Interfaces:**
- Consumes: `User.user_preferences` JSON column (same storage as legacy `ReportPresetsController`)
- Produces: `GET /api/v1/report-presets`, `POST /api/v1/report-presets`, `DELETE /api/v1/report-presets/{id}` returning `{ data: ReportPreset[] }` with rich envelope on errors

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\V1;

use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPresetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, UserSeeder::class]);
    }

    public function test_user_can_list_own_report_presets(): void
    {
        $user = User::factory()->create([
            'user_preferences' => [
                'report_presets' => [
                    ['id' => 'p1', 'name' => 'Q1', 'filter' => ['from' => '2026-01-01'], 'createdAt' => '2026-01-01'],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/report-presets')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'p1');
    }

    public function test_user_can_save_and_delete_preset(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/report-presets', [
                'id' => 'abc',
                'name' => 'My filter',
                'filter' => ['bank' => 1],
                'createdAt' => '2026-07-07',
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', 'abc');

        $this->actingAs($user)
            ->deleteJson('/api/v1/report-presets/abc')
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
cd backend && php artisan test tests/Feature/V1/ReportPresetTest.php
```
Expected: FAIL — route not defined or controller missing.

- [ ] **Step 3: Implement V1 controller** (copy logic from `ReportPresetsController.php`; namespace `Api\V1`; use governance `ApiResponse` rich envelope)

```php
Route::get('report-presets', [ReportPresetController::class, 'index']);
Route::post('report-presets', [ReportPresetController::class, 'store']);
Route::delete('report-presets/{id}', [ReportPresetController::class, 'destroy']);
```

- [ ] **Step 4: Run test — verify pass**

```bash
cd backend && php artisan test tests/Feature/V1/ReportPresetTest.php
```
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/Api/V1/ReportPresetController.php backend/routes/api.php backend/tests/Feature/V1/ReportPresetTest.php
git commit -m "feat(backend): add V1 report presets endpoints"
```

---

## Task 2: Audit legacy widgets — optional drop (L-1 / D23-N4) ⚠️ PRODUCT FLAG

**Default path: DROP stale panels.** Rebuild only if product confirms operators use them.

**Files:**
- Modify: `backend/config/features.php` (create if missing) OR `backend/config/demo.php` sibling `config/features.php`
- Modify: `frontend/app/pages/audit.vue`
- Modify: `frontend/app/composables/useAudit.ts`
- Test: `frontend/app/tests/unit/pages/audit.test.ts`

**Interfaces:**
- Produces: `config('features.audit_legacy_widgets')` — env `AUDIT_LEGACY_WIDGETS`, default `false`
- When `false`: remove `fetchAuditStats`, `fetchDuplicates`, `fetchRiskIndicators` calls and MetricGrid/InsightsTabsCard widget sections from `audit.vue`; keep engine audit log table on `/api/v1/audit-logs`
- When `true`: wire widgets to existing V1 compliance endpoints:
  - duplicates → `GET /api/v1/compliance/duplicate-invoices`
  - expired docs count → `GET /api/v1/compliance/expired-documents` (summary meta)
  - SLA breaches → `GET /api/v1/compliance/sla-breaches` (risk indicators substitute)

- [ ] **Step 1: Write failing test (default = widgets hidden)**

```typescript
it('does not render legacy audit widget panels when AUDIT_LEGACY_WIDGETS is false', async () => {
  vi.stubGlobal('useRuntimeConfig', () => ({
    public: { auditLegacyWidgets: false },
  }))
  const wrapper = await mountAuditPage()
  expect(wrapper.text()).not.toContain('مؤشرات المخاطر')
  expect(wrapper.text()).not.toContain('فواتير مكررة')
})
```

- [ ] **Step 2: Run test — verify fail**

```bash
cd frontend && pnpm exec vitest run app/tests/unit/pages/audit.test.ts -t "legacy audit widget"
```
Expected: FAIL — widgets still rendered.

- [ ] **Step 3: Implement flag + remove default panels**

Add to `nuxt.config.ts` `runtimeConfig.public.auditLegacyWidgets` reading env. Wrap widget `onMounted` fetches in `if (config.public.auditLegacyWidgets)`.

- [ ] **Step 4: Run test — verify pass**

```bash
cd frontend && pnpm exec vitest run app/tests/unit/pages/audit.test.ts
```
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/config/features.php frontend/nuxt.config.ts frontend/app/pages/audit.vue frontend/app/composables/useAudit.ts frontend/app/tests/unit/pages/audit.test.ts
git commit -m "feat(frontend): gate audit legacy widgets behind product flag (default off)"
```

**Note:** If product later sets `AUDIT_LEGACY_WIDGETS=true`, add a follow-up sub-task to map compliance responses to widget shape — do not block Tasks 3–14 on that decision.

---

## Task 3: Migrate `useUsers` to V1 (L-2 / D23-N3, D23-N12)

**Files:**
- Modify: `frontend/app/composables/useUsers.ts` (all `/api/users` → `/api/v1/users`)
- Modify: `frontend/app/tests/unit/composables/useUsers.test.ts`
- Modify: `frontend/app/tests/unit/components/security/AccountRecoveryDialog.test.ts`
- Modify: `backend/tests/Feature/Admin/AccountRecoveryAdminResetTest.php` (legacy `/api/users` paths → `/api/v1/users`)
- Modify: `frontend/tests/e2e/account-recovery.spec.ts` (mock paths)
- Modify: `frontend/app/plugins/00.visual-bypass-api.client.ts`

**Interfaces:**
- Consumes: V1 `UserController` (`reset-pin` already at `/api/v1/users/{user}/reset-pin`)
- Produces: `useUsers()` methods calling only `/api/v1/users*`

- [ ] **Step 1: Update composable tests first (TDD)**

Change every `expect(mockGet).toHaveBeenCalledWith('/api/users` to `'/api/v1/users`.

- [ ] **Step 2: Run tests — verify fail**

```bash
cd frontend && pnpm exec vitest run app/tests/unit/composables/useUsers.test.ts
```
Expected: FAIL — composable still calls legacy paths.

- [ ] **Step 3: Migrate `useUsers.ts`**

Replace all path prefixes:

```typescript
const V1 = '/api/v1/users'
// fetchUsers: `${V1}?${query}` or V1
// resetUserPin: post(`${V1}/${id}/reset-pin`, {})
```

- [ ] **Step 4: Update backend AccountRecovery tests**

```php
$this->actingAs($cbyAdmin)->postJson("/api/v1/users/{$target->id}/reset-password", [
```

- [ ] **Step 5: Run focused tests — verify pass**

```bash
cd frontend && pnpm exec vitest run app/tests/unit/composables/useUsers.test.ts app/tests/unit/components/security/AccountRecoveryDialog.test.ts
cd backend && php artisan test tests/Feature/Admin/AccountRecoveryAdminResetTest.php
```
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add frontend/app/composables/useUsers.ts frontend/app/tests/unit/composables/useUsers.test.ts frontend/app/tests/unit/components/security/AccountRecoveryDialog.test.ts backend/tests/Feature/Admin/AccountRecoveryAdminResetTest.php frontend/app/plugins/00.visual-bypass-api.client.ts
git commit -m "refactor(frontend): migrate useUsers consumers to V1 endpoints"
```

---

## Task 4: Migrate `useBanks` to V1 (L-2 / D23-N3)

**Files:**
- Modify: `frontend/app/composables/useBanks.ts`
- Modify: `frontend/app/tests/unit/composables/useBanks.test.ts`
- Modify: `frontend/app/pages/admin/banks.vue` (only if response shape differs)
- Modify: `frontend/app/pages/merchants.vue`
- Modify: `frontend/app/plugins/00.visual-bypass-api.client.ts`

**Interfaces:**
- Consumes: V1 `BankController` index/store/update
- Produces: all `useBanks()` CRUD on `/api/v1/banks`
- **Dead method:** `resetBankAdminPassword` — not used by any page. Remove from composable + tests OR promote to V1 if needed later. Do not block migration.

- [ ] **Step 1: Update `useBanks.test.ts` paths to `/api/v1/banks`**

- [ ] **Step 2: Run test — verify fail**

```bash
cd frontend && pnpm exec vitest run app/tests/unit/composables/useBanks.test.ts
```

- [ ] **Step 3: Migrate composable**

```typescript
const response = await get<ApiResponse<PaginatedResponse<Bank>>>('/api/v1/banks?per_page=200')
// createBank: post('/api/v1/banks', payload)
// updateBank: put(`/api/v1/banks/${id}`, payload)
```

Remove `resetBankAdminPassword` export (unused; legacy-only endpoint).

- [ ] **Step 4: Run tests + lint**

```bash
cd frontend && pnpm exec vitest run app/tests/unit/composables/useBanks.test.ts app/tests/unit/pages/CbyAdminPages.test.ts
cd frontend && pnpm exec eslint app/composables/useBanks.ts
```
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor(frontend): migrate useBanks to V1 bank endpoints"
```

---

## Task 5: Migrate `useAudit` legacy list + `audit.vue` (L-2 / D23-N3)

**Files:**
- Modify: `frontend/app/composables/useAudit.ts`
- Modify: `frontend/app/pages/audit.vue`
- Modify: `frontend/app/tests/unit/composables/useAudit.test.ts`
- Modify: `frontend/app/tests/unit/pages/audit.test.ts`

**Interfaces:**
- Replace `fetchAuditLogs` implementation to call `fetchEngineAuditLogs` shape OR delegate directly to `/api/v1/audit-logs`
- Remove exports `fetchAuditStats`, `fetchDuplicates`, `fetchRiskIndicators` when Task 2 flag is off
- `audit.vue` primary table uses V1 engine audit logs (already partially wired)

- [ ] **Step 1: Write failing test**

```typescript
it('calls GET /api/v1/audit-logs for the main audit table', async () => {
  const { fetchAuditLogs } = useAudit()
  await fetchAuditLogs({ page: 1 })
  expect(mockGet).toHaveBeenCalledWith('/api/v1/audit-logs?page=1')
})
```

- [ ] **Step 2: Run test — verify fail**

```bash
cd frontend && pnpm exec vitest run app/tests/unit/composables/useAudit.test.ts -t "v1/audit-logs"
```

- [ ] **Step 3: Implement migration** — point `fetchAuditLogs` at V1; delete legacy `/api/audit*` functions if widgets dropped.

- [ ] **Step 4: Update `audit.vue` loadAuditLogs** to use migrated composable; remove dead widget state when flag off.

- [ ] **Step 5: Run tests — verify pass**

```bash
cd frontend && pnpm exec vitest run app/tests/unit/composables/useAudit.test.ts app/tests/unit/pages/audit.test.ts
```

- [ ] **Step 6: Commit**

```bash
git commit -m "refactor(frontend): migrate audit page to V1 audit-logs"
```

---

## Task 6: Migrate `useReports` report-presets to V1 (L-2 / D23-N11)

**Files:**
- Modify: `frontend/app/composables/useReports.ts` (~lines 250–269)
- Modify: `frontend/app/tests/unit/composables/useReports.test.ts`

- [ ] **Step 1: Update tests to expect `/api/v1/report-presets`**

- [ ] **Step 2: Run test — verify fail**

```bash
cd frontend && pnpm exec vitest run app/tests/unit/composables/useReports.test.ts -t "preset"
```

- [ ] **Step 3: Change composable paths**

```typescript
const response = await get<ApiResponse<ReportPreset[]>>('/api/v1/report-presets')
await post<ApiResponse<ReportPreset[]>>('/api/v1/report-presets', preset)
await del(`/api/v1/report-presets/${id}`)
```

- [ ] **Step 4: Run test — verify pass**

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor(frontend): migrate report presets to V1"
```

---

## Task 7: API envelope tolerance on migrated endpoints (L-6 / R9)

**Files:**
- Modify: `frontend/app/utils/apiErrors.ts`
- Create: `frontend/app/tests/unit/utils/apiErrors.test.ts`
- Modify: V1 controllers touched in Tasks 1–3 backend tests to assert rich envelope on 422

**Interfaces:**
- Produces: `extractApiErrorMessage` reads `data.error.message` then `data.message`
- Produces: `extractApiErrorCode` reads `data.error.code` then `data.error_code`
- Produces: optional `extractRequestId(err)` → `data.request_id`

- [ ] **Step 1: Write failing tests for both envelope shapes**

```typescript
it('extracts message from rich envelope', () => {
  const err = { data: { error: { code: 'VALIDATION_FAILED', message: 'خطأ' } } }
  expect(extractApiErrorMessage(err, 'fallback')).toBe('خطأ')
})

it('extracts message from legacy envelope', () => {
  const err = { data: { message: 'قديم', error_code: 'OLD' } }
  expect(extractApiErrorMessage(err, 'fallback')).toBe('قديم')
  expect(extractApiErrorCode(err)).toBe('OLD')
})
```

- [ ] **Step 2: Run test — verify fail (if legacy path missing)**

```bash
cd frontend && pnpm exec vitest run app/tests/unit/utils/apiErrors.test.ts
```

- [ ] **Step 3: Implement tolerance** (file already partially supports rich envelope — extend coverage + add `request_id` helper)

- [ ] **Step 4: Adopt rich envelope in `ReportPresetController` validation errors** (use existing `ApiResponse::error()` pattern from governance controllers)

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor(frontend): unify API error extraction for R9 envelope migration"
```

---

## Task 8: Demo route production gate (L-3 / D23-N2)

**Files:**
- Modify: `backend/config/demo.php`
- Modify: `backend/routes/api.php` — wrap demo routes in env check
- Modify: `backend/app/Http/Controllers/Api/AuthController.php` — audit log on switch
- Create: `backend/tests/Feature/Auth/DemoRouteEnvironmentGateTest.php`
- Modify: `frontend/app/stores/auth.store.ts` — skip demo UI calls when `runtimeConfig.public.demoEnabled === false`
- Modify: `backend/.env.example`

**Interfaces:**
- Produces: `config('demo.allowed_environments')` = `['local', 'staging']`
- Produces: demo routes registered **only** when `in_array(app()->environment(), allowed, true) && config('demo.allow_role_switch')`
- Produces: `AuditLog` entry `DEMO_USER_SWITCH` with actor, target_user_id, ip, environment

- [ ] **Step 1: Write failing test**

```php
public function test_demo_routes_not_registered_in_production_environment(): void
{
    $this->app->detectEnvironment(fn () => 'production');
    config(['demo.allow_role_switch' => true]);

    $this->getJson('/api/auth/demo-users')->assertNotFound();
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
cd backend && php artisan test tests/Feature/Auth/DemoRouteEnvironmentGateTest.php
```

- [ ] **Step 3: Implement route registration guard**

```php
// routes/api.php — only register inside:
if (app()->environment(config('demo.allowed_environments')) && config('demo.allow_role_switch')) {
    Route::get('demo-users', ...);
    Route::post('switch-demo-user', ...);
    Route::post('switch-demo-role', ...)->middleware(['auth:sanctum', 'active']);
}
```

Move `switch-demo-role` out of always-on auth group.

- [ ] **Step 4: Add audit logging in `switchDemoUser` / `switchDemoRole`**

- [ ] **Step 5: Run tests — verify pass**

```bash
cd backend && php artisan test tests/Feature/Auth/DemoRouteEnvironmentGateTest.php tests/Feature/Auth/DemoUserSwitchTest.php
```

- [ ] **Step 6: Commit**

```bash
git commit -m "fix(auth): gate demo switch routes to local/staging only"
```

---

## Task 9: Zero-legacy-consumer grep gate (D23-N13 step 5)

**Files:**
- Create: `scripts/verify-no-legacy-api-consumers.sh`
- Modify: `.superpowers/sdd/progress.md`

- [ ] **Step 1: Add verification script**

```bash
#!/usr/bin/env bash
set -euo pipefail
LEGACY_PATTERNS=(
  "'/api/users"
  '"/api/users'
  "'/api/banks"
  '"/api/banks'
  "'/api/audit"
  '"/api/audit'
  "'/api/report-presets"
  '"/api/report-presets'
  "'/api/notifications"
  '"/api/notifications'
)
for pat in "${LEGACY_PATTERNS[@]}"; do
  if rg -n "$pat" frontend/app frontend/tests --glob '!**/*.md'; then
    echo "FAIL: legacy consumer found for pattern $pat"
    exit 1
  fi
done
echo "OK: no legacy API consumers in frontend/app"
```

- [ ] **Step 2: Run script — must pass before Task 10**

```bash
bash scripts/verify-no-legacy-api-consumers.sh
```
Expected: OK

- [ ] **Step 3: Record pass in progress ledger**

- [ ] **Step 4: Commit**

```bash
git add scripts/verify-no-legacy-api-consumers.sh
git commit -m "chore(repo): add legacy API consumer grep gate for WP-14"
```

---

## Task 10: Purge legacy route batch — users, banks, audit, presets, notifications (L-4 / D23-N5)

**Prerequisite:** Task 9 grep gate green.

**Files:**
- Modify: `backend/routes/api.php` — remove lines 265–308 legacy block (users, banks, document-types, audit*, notifications duplicate, report-presets, legacy reports)
- Delete: legacy controllers listed in File Structure
- Create: `backend/tests/Feature/Legacy/LegacyRouteAbsentTest.php`
- Delete: orphaned tests that only hit removed routes

- [ ] **Step 1: Write failing test (routes still exist → will pass prematurely; run AFTER deletion)**

```php
public function test_legacy_users_route_returns_404(): void
{
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/users')->assertNotFound();
}
```

- [ ] **Step 2: Remove routes + delete controllers**

- [ ] **Step 3: Run route list spot-check**

```bash
cd backend && php artisan route:list --path=api/users
cd backend && php artisan route:list --path=api/audit
```
Expected: only `api/v1/*` entries remain.

- [ ] **Step 4: Run tests**

```bash
cd backend && php artisan test tests/Feature/Legacy/LegacyRouteAbsentTest.php
```

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor(backend): remove legacy users banks audit presets notification routes"
```

---

## Task 11: Dead module purge — reports, merchants, document-types, stale constants (L-4/L-5)

**Files:**
- Delete: `ReportController`, legacy `MerchantController`, `DocumentTypeController`, model, migrations references in tests only
- Delete: `frontend/app/composables/useDocumentTypes.ts` + test
- Modify: `frontend/app/constants/workflow.ts` — remove stale simplified-status / route-role maps (D3-N4, D11-N6/N7) per grep
- Modify: notification URL generators still pointing at `/requests/{id}` (D19-N1)
- Verify: `rg 'engine_claim:' backend/` returns zero hits

- [ ] **Step 1: Grep inventory before delete**

```bash
rg -l "ReportController|DocumentType|useDocumentTypes|MerchantController" backend frontend
```

- [ ] **Step 2: Delete dead files + fix imports**

- [ ] **Step 3: Rewrite `stageIsBound` / `fieldIsUsed`**

In `WorkflowDesignerService::stageIsBound` and `FieldDesignerService::fieldIsUsed`, replace `import_requests` / `requests` table checks with `engine_requests` workflow-version scoped queries (mirror WP-0 BF-1 fix pattern).

- [ ] **Step 4: Add backend unit test**

```php
public function test_stage_is_bound_checks_engine_requests_not_import_requests(): void
{
    // stage with bound engine_request → true; no wrong-table exception
}
```

- [ ] **Step 5: Run focused tests**

```bash
cd backend && php artisan test --filter=StageIsBound
cd frontend && pnpm exec vitest run app/tests/unit/pages/prototype-parity-pages.smoke.test.ts
```

- [ ] **Step 6: Commit**

```bash
git commit -m "refactor(backend): purge dead modules and fix dropped-table designer guards"
```

---

## Task 12: Stale reference sweep — ImportRequest fixtures, notification URLs, committee_director gates (L-4/L-5)

**Files:**
- Modify: frontend test fixtures using `ImportRequest` / `makeImportRequest` where they block cleanup understanding (keep only if testing legacy display aliases)
- Modify: `backend/app/Services/Notifications/*` — engine request deep links
- Grep: `committee_director` stale gates (WP-10 RM-5 should have resolved; remove dead branches)

- [ ] **Step 1: Grep sweep**

```bash
rg "ImportRequest|import_requests|/requests/" frontend/app backend/app --glob '!**/migrations/**'
```

- [ ] **Step 2: Fix each live (non-migration, non-archive) hit**

- [ ] **Step 3: Run WP-0 characterization suites if touched**

```bash
cd backend && php artisan test tests/Feature/Engine/EngineDemoSeederTest.php
```

- [ ] **Step 4: Commit**

```bash
git commit -m "chore(repo): sweep stale ImportRequest and notification URL references"
```

---

## Task 13: Conditional `users.role` column drop (L-4 / D23-N7) — WP-10 RM-3 GATE ⚠️

**Run only when ALL true:**
1. WP-10 RM-1 complete — no authorization reads `users.role`
2. `rg "legacyRoleFor|getAttributes\(\)\['role'\]" backend/app` shows only `User::legacyRole()` fallback
3. Product/sign-off for irreversible drop

**If gate fails:** mark Task 13 `deferred` in progress ledger; skip migration; do not block Task 14.

**Files:**
- Create: `backend/database/migrations/2026_07_07_200001_drop_users_role_column.php`
- Modify: `backend/app/Models/User.php` — remove `role` from fillable/casts; delete `legacyRole()` fallback
- Modify: `backend/app/Enums/UserRole.php` — delete if zero references
- Test: `backend/tests/Feature/Governance/UsersRoleColumnAbsentTest.php`

- [ ] **Step 1: Pre-flight grep**

```bash
rg "\->role\b|users\.role|'role'" backend/app --glob '!**/User.php'
```

- [ ] **Step 2: Write failing schema test**

```php
public function test_users_table_has_no_role_column(): void
{
    $this->assertFalse(Schema::hasColumn('users', 'role'));
}
```

- [ ] **Step 3: Migration + model cleanup** (only if pre-flight clean)

- [ ] **Step 4: Run governance + demo tests**

```bash
cd backend && php artisan test tests/Feature/Governance tests/Feature/Auth/DemoUserSwitchTest.php
```

- [ ] **Step 5: Commit or record deferral**

```bash
git commit -m "refactor(backend): drop users.role column after pivot migration (WP-10 RM-3)"
```

---

## Task 14: Full regression, docs, release notes (L-7)

**Files:**
- Create: `docs/release-notes/2026-07-wp14-legacy-cleanup.md`
- Modify: `docs/06-api-reference.md` — remove deleted endpoints; document V1 presets
- Modify: `.superpowers/sdd/progress.md` — mark all tasks complete

- [ ] **Step 1: Run full verification (terminal package — full suites justified)**

```bash
cd backend && composer format:check && php artisan test
cd frontend && pnpm lint && pnpm format:check && pnpm typecheck && pnpm test
bash scripts/verify-no-legacy-api-consumers.sh
```

- [ ] **Step 2: Grep purge verification**

```bash
rg "api/audit[^-]|api/users|api/banks|report-presets|NotificationController|DocumentTypeController" backend frontend --glob '!docs/**' --glob '!**/*.md'
```
Expected: zero hits outside release notes / historical audit snapshots.

- [ ] **Step 3: Manual verification checklist**

1. `staff`, `admin/banks`, `merchants`, `audit`, `reports` screens work on V1
2. Production-equivalent env (`APP_ENV=production`): demo routes 404
3. Full engine flow — no 404/500 from stale references
4. Migrated endpoints return rich error envelope on validation failure

- [ ] **Step 4: Write release notes** listing every removed endpoint/module + envelope change

- [ ] **Step 5: Spec coverage self-review**

| Spec item | Task |
|---|---|
| L-1 V1 replacements | 1, 2 (optional) |
| L-2 consumer migration | 3–6 |
| L-3 demo production removal | 8 |
| L-4 dead-code purge | 10–12 |
| L-5 dropped-table refs | 11–12 |
| L-6 API envelope | 7 |
| L-7 regression + release notes | 14 |
| D23-N13 sequence | 1→6→9→10→14 |

- [ ] **Step 6: Commit**

```bash
git add docs/release-notes/2026-07-wp14-legacy-cleanup.md docs/06-api-reference.md .superpowers/sdd/progress.md
git commit -m "docs(repo): wp14 legacy cleanup release notes and api reference"
```

---

## Self-Review

**Spec coverage:** All L-1–L-7 items mapped. D23-N13 enforced via Tasks 1–6 (build+migrate), Task 9 (verify), Tasks 10–11 (delete). RM-3 gated in Task 13.

**Placeholder scan:** No TBD steps. Optional Task 2 has explicit default (drop widgets).

**Type consistency:** V1 path prefix `/api/v1/` used consistently. `useAudit` engine methods unchanged.

**Sequencing risk:** Task 10 blocked on Task 9 script. Task 13 independent deferral.

---

## Execution Handoff

**Plan complete and saved to `.claude/worktrees/wp14-legacy-cleanup/docs/superpowers/plans/2026-07-07-wp14-legacy-cleanup-wave.md`.**

**Two execution options:**

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks
2. **Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
