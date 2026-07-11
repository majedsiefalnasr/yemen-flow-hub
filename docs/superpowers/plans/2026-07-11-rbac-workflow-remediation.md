# RBAC + Workflow Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the M1–M6 approved decisions — close the security-boundary, authorization, workflow-configuration, UI-reliability, and status-model-drift findings from the functional/RBAC audit.

**Architecture:** Backend Laravel 11 (Sanctum, MySQL, service-oriented; authorization in policies + `PermissionService` + `StagePermissionResolver` + `DataScope`). Frontend Nuxt 4 / Vue 3 (Pinia, shadcn-vue). The dynamic workflow engine (designer metadata → runtime) is authoritative; fixes correct designer configuration (a new published V2), authorization boundaries, and the frontend status model — never the engine's transition semantics.

**Tech Stack:** PHP 8.2 / Laravel 11 / PHPUnit / Pint · Nuxt 4 / Vue 3 / Vitest / ESLint / Prettier · pnpm.

## Global Constraints

- Source-of-truth order (M6): DB schema + persisted metadata → published designer config → backend runtime + API contracts → frontend → docs. Frontend must not hold a parallel canonical status model.
- **Executive Voting is OUT of V1** (M1). Do not reintroduce voting stages/models/UI. Voting code is legacy/orphaned; remove only via the gated legacy-cleanup task with dependency evidence.
- Stage-level EXECUTE is the accepted permission model (M4); no per-action permission axis. Field visibility is stage-scoped, not viewer-scoped (M5).
- Authorization = active pivot **AND** active role only (M3). Never delete historical `user_roles` pivot rows.
- Canonical request-state model (M6): `runtime_status` ∈ {ACTIVE, CLOSED, REJECTED, CANCELLED, ABANDONED}; `current_stage` (designer metadata incl. `semantic_role`); `final_outcome` ∈ {COMPLETED, REJECTED, CANCELLED, ABANDONED, null}.
- Every fix: tests written before/alongside; audit probe suites (`Phase2RbacProbeTest`, `Phase3WorkflowConfigurationProbeTest`) become green as their defect is fixed — **never weaken their secure expectations**. Preserve audit + transaction guarantees. No backend authz weakened for UX. Migration + rollback plan for every schema/data change.
- Commits: Conventional Commits, signed, co-authored `Co-Authored-By: Claude <noreply@anthropic.com>`. Allowed scopes: auth, backend, docs, frontend, repo, settings, testing, ui, workflow. Never `--no-verify`.
- Verification ladder: focused test/filter for touched behavior first; Pint on touched PHP; Vitest file/filter + ESLint/Prettier on touched FE; typecheck only for type/composable/store/contract changes. Full suites at phase checkpoints only.
- **Checkpoint after each full phase (A, B, C, D, E, F), not after each task.** Pause mid-phase only for: (1) business decision, (2) new evidence invalidating an approved decision, (3) data migration / backward-incompatible change needing approval, (4) a security issue changing priority.

**Reference specs:** `docs/audit-functional/05-m1`…`10-implementation-plan.md`.

---

## Phase A — Security boundary & authorization (pre-production, blocking go-live)

### Task A1: RBAC-004 — cross-org request scope ✅ DONE

Completed in the prior session. `EngineRequestPolicy::inScope()` now uses `DataScope::forUser()` classification. CF-3 probes green; engine suite 0 failures; Pint clean. Committed as part of Phase A.

- [x] Done — see report in conversation history.

---

### Task A2: RBAC-002 — reject admin-only / universal screen delegation

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php:157-178` (`update()`)
- Test: `backend/tests/Feature/Audit/Phase2RbacProbeTest.php` (existing CF-2 probes become green — do not modify their expectations)

**Interfaces:**
- Consumes: `ADMIN_ONLY_SCREENS` and `UNIVERSAL_SCREENS` class constants (already defined `:66,:71`).
- Produces: `update()` rejects any grant key in `ADMIN_ONLY_SCREENS ∪ UNIVERSAL_SCREENS ∪ {requests}` with a validation error before writing.

- [ ] **Step 1: Run the CF-2 probes to confirm red baseline**

Run: `cd backend && php artisan test tests/Feature/Audit/Phase2RbacProbeTest.php --filter=test_cf2`
Expected: 3 fail (admin-only key accepted → 200; self-escalation chain succeeds).

- [ ] **Step 2: Tighten the writable-screen-key set in `update()`**

In `RoleScreenPermissionController::update()`, replace the `$validScreenKeys` line (`:164`):

```php
// Writable screens exclude: `requests` (designer-derived), UNIVERSAL_SCREENS
// (always-on, not customizable), and ADMIN_ONLY_SCREENS (system-admin-only,
// not delegable). Rejecting these server-side closes the RBAC-002
// delegation/self-escalation path — the matrix UI hiding them is not enough.
$nonWritable = array_merge(
    ['requests'],
    self::UNIVERSAL_SCREENS,
    self::ADMIN_ONLY_SCREENS,
);
$validScreenKeys = Screen::query()
    ->whereNotIn('key', $nonWritable)
    ->pluck('key')
    ->toArray();
```

The existing loop (`:174-178`) already returns a validation error for any key not in `$validScreenKeys`, so admin-only/universal keys now produce `Unknown screen: {key}`.

- [ ] **Step 3: Run CF-2 probes to verify green**

Run: `cd backend && php artisan test tests/Feature/Audit/Phase2RbacProbeTest.php --filter=test_cf2`
Expected: PASS — every `ADMIN_ONLY_SCREENS` key rejected; self-escalation chain blocked.

- [ ] **Step 4: Run the screen-permission control suite (no regression to legitimate grants)**

Run: `cd backend && php artisan test tests/Feature/Permission/ScreenPermissionTest.php`
Expected: PASS (15 tests) — merchants/reports/audit grants still writable.

- [ ] **Step 5: Pint + commit**

Run: `cd backend && vendor/bin/pint app/Http/Controllers/Api/V1/RoleScreenPermissionController.php`
```bash
git add backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php
git commit -m "fix(auth): reject admin-only and universal screen grants in update API (RBAC-002)"
```

---

### Task A3: RBAC-001 + RBAC-003 — active-identity authorization

**Files:**
- Modify: `backend/app/Models/User.php:222-249` (`isSystemAdmin`, `hasRoleCode`, `hasAnyRoleCode`)
- Modify: `backend/app/Services/Authorization/PermissionService.php` (capability derivation used by `/auth/me`)
- Test: `backend/tests/Feature/Audit/Phase2RbacProbeTest.php` (CF-1, CF-4 probes become green)

**Interfaces:**
- Consumes: `roles()` relationship with pivot `is_active`; `role()` (active-role accessor); `RoleCodes::SYSTEM_ADMIN`.
- Produces: `isSystemAdmin()`, `hasRoleCode()`, `hasAnyRoleCode()` all return true only for the single active pivot on an active role record; `/auth/me` capabilities derive from the active identity only.

- [ ] **Step 1: Run CF-1 + CF-4 probes to confirm red baseline**

Run: `cd backend && php artisan test tests/Feature/Audit/Phase2RbacProbeTest.php --filter="test_cf1|test_cf4"`
Expected: fail (inactive/demoted admin still admin; `/auth/me` shows requests caps from inactive role).

- [ ] **Step 2: Read the active-role accessor to reuse it**

Run: `cd backend && grep -nE "function role\(|activeRole|wherePivot\('is_active'|roles\.is_active" app/Models/User.php`
Confirm the name of the active-role accessor (`role()`) and how it filters pivot + role `is_active`. Reuse it; do not add a parallel query.

- [ ] **Step 3: Fix `isSystemAdmin()` — active pivot + active role, both branches identical**

Replace `isSystemAdmin()` (`:222-229`):

```php
public function isSystemAdmin(): bool
{
    return $this->hasRoleCode(RoleCodes::SYSTEM_ADMIN);
}
```

(Delegating to the corrected `hasRoleCode()` guarantees both helpers use the same active-identity rule and both execution paths behave identically. If `hasRoleCode` cannot cover the unloaded-relation case efficiently, keep a dedicated implementation but it MUST filter `user_roles.is_active = true` AND `roles.is_active = true` in both the loaded and query branches.)

- [ ] **Step 4: Fix `hasRoleCode()` / `hasAnyRoleCode()` — remove the historical fallback**

Replace `hasRoleCode()` (`:231-244`) so it evaluates the active role only, with no fallback to all historical roles:

```php
public function hasRoleCode(string $code): bool
{
    return $this->activeRoleCodes()->contains($code);
}

public function hasAnyRoleCode(array $codes): bool
{
    return $this->activeRoleCodes()->intersect($codes)->isNotEmpty();
}

/**
 * Codes of the user's single active role (active pivot AND active role
 * record). Empty when the user has no active role — historical inactive
 * pivots never contribute (M3 / RBAC-001).
 */
private function activeRoleCodes(): \Illuminate\Support\Collection
{
    return $this->roles()
        ->wherePivot('is_active', true)
        ->where('roles.is_active', true)
        ->pluck('code');
}
```

(If the loaded-relation path must be honored without a query, filter the loaded collection by the pivot's `is_active` and the role's `is_active` — never return historical codes. Match whatever the existing `role()` accessor already does.)

- [ ] **Step 5: Align `/auth/me` capability derivation with the active identity**

Run: `cd backend && grep -nE "derivedRequestsCapabilitiesForUser|teams\(\)|roles\(\)|is_active" app/Services/Authorization/PermissionService.php | head`
In the requests-capability derivation, add the same `wherePivot('is_active', true)` on teams/roles and `where('...is_active', true)` on the role/team records that `StagePermissionResolver` uses, so the overlay matches runtime. (Mirror the resolver's identity filters exactly.)

- [ ] **Step 6: Run CF-1 + CF-4 probes to verify green**

Run: `cd backend && php artisan test tests/Feature/Audit/Phase2RbacProbeTest.php --filter="test_cf1|test_cf4"`
Expected: PASS — demoted admin denied; `/auth/me` shows no requests caps for inactive-only role.

- [ ] **Step 7: Re-grep for direct historical-role queries (no bypass introduced)**

Run: `cd backend && grep -rnE "->roles\(\)->|->roles->|wherePivot\('is_active'|whereHas\('roles'" app/ | grep -viE "User.php|PermissionService.php"`
Expected: no new call site queries historical roles outside the helpers.

- [ ] **Step 8: Run governance + auth suites (legitimate admin access preserved)**

Run: `cd backend && php artisan test tests/Feature/Auth tests/Feature/Permission`
Expected: PASS — active system admin still reaches admin surfaces; single-active-role invariant intact.

- [ ] **Step 9: Pint + commit**

```bash
cd backend && vendor/bin/pint app/Models/User.php app/Services/Authorization/PermissionService.php
git add backend/app/Models/User.php backend/app/Services/Authorization/PermissionService.php
git commit -m "fix(auth): authorize only active pivot on active role; align /auth/me (RBAC-001, RBAC-003)"
```

---

### Task A4: H6 / M2 — environment auth-bypass hard-stops

**Files:**
- Create: `backend/app/Support/DemoEnvironment.php` (centralized gate)
- Modify: `backend/app/Http/Controllers/Api/AuthController.php:325,360,419` (three demo endpoints)
- Modify: `backend/config/demo.php` (keep `allowed_environments`; ensure prod excluded)
- Modify: `frontend/app/middleware/00.visual-bypass.global.ts` (production hard-stop)
- Modify: `frontend/nuxt.config.ts` (build/startup guard)
- Test: `backend/tests/Feature/Audit/Phase2RbacProbeTest.php` (existing `test_demo_switch_*`) + a new `DemoEnvironmentGateTest`

**Interfaces:**
- Produces: `DemoEnvironment::switchingAllowed(): bool` = `config('demo.allow_role_switch') === true` **AND** `app()->environment(config('demo.allowed_environments'))` **AND** `! app()->isProduction()`. Used by all demo endpoints.

- [ ] **Step 1: Write the failing gate test**

Create `backend/tests/Feature/Audit/DemoEnvironmentGateTest.php`:

```php
<?php

namespace Tests\Feature\Audit;

use App\Support\DemoEnvironment;
use Tests\TestCase;

class DemoEnvironmentGateTest extends TestCase
{
    public function test_production_denies_even_when_flag_true(): void
    {
        $this->app['env'] = 'production';
        config(['demo.allow_role_switch' => true, 'demo.allowed_environments' => ['local','staging','testing']]);
        $this->assertFalse(DemoEnvironment::switchingAllowed());
    }

    public function test_approved_env_allows_only_when_flag_true(): void
    {
        $this->app['env'] = 'local';
        config(['demo.allow_role_switch' => false, 'demo.allowed_environments' => ['local','staging','testing']]);
        $this->assertFalse(DemoEnvironment::switchingAllowed());

        config(['demo.allow_role_switch' => true]);
        $this->assertTrue(DemoEnvironment::switchingAllowed());
    }
}
```

- [ ] **Step 2: Run it — verify fail (class missing)**

Run: `cd backend && php artisan test tests/Feature/Audit/DemoEnvironmentGateTest.php`
Expected: FAIL — `DemoEnvironment` not found.

- [ ] **Step 3: Create the centralized gate**

Create `backend/app/Support/DemoEnvironment.php`:

```php
<?php

namespace App\Support;

class DemoEnvironment
{
    /**
     * Demo identity switching requires BOTH the feature flag AND an explicitly
     * approved non-production environment. Production always fails closed, even
     * if APP_DEMO_ROLE_SWITCH=true (H6 / M2).
     */
    public static function switchingAllowed(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        if (! (bool) config('demo.allow_role_switch', false)) {
            return false;
        }

        return app()->environment((array) config('demo.allowed_environments', []));
    }
}
```

- [ ] **Step 4: Run gate test — verify pass**

Run: `cd backend && php artisan test tests/Feature/Audit/DemoEnvironmentGateTest.php`
Expected: PASS.

- [ ] **Step 5: Route all three demo endpoints through the gate**

In `AuthController.php`, replace each `if (! config('demo.allow_role_switch', false))` guard (`:325,:360,:419`) with:

```php
if (! \App\Support\DemoEnvironment::switchingAllowed()) {
    return ApiResponse::forbidden('Demo role switching is disabled.');
}
```

- [ ] **Step 6: Run the demo-switch probes — verify green**

Run: `cd backend && php artisan test tests/Feature/Audit/Phase2RbacProbeTest.php --filter="test_demo"`
Expected: PASS (switch denied when flag off; would also deny in production).

- [ ] **Step 7: Frontend — block visual bypass in production (middleware)**

In `frontend/app/middleware/00.visual-bypass.global.ts`, add a production guard at the top of the middleware body, before fabricating a user:

```ts
export default defineNuxtRouteMiddleware(() => {
  const config = useRuntimeConfig()
  if (!config.public.visualBypass) return
  // Defense in depth: never fabricate an identity in a production runtime,
  // even if the build flag leaked through (H6 / M2).
  if (import.meta.env.PROD && !import.meta.dev) return

  const auth = useAuthStore()
  if (auth.user && auth.isAuthenticated) return
  // ... existing fabrication ...
})
```

- [ ] **Step 8: Frontend — fail the production build when the flag is set (nuxt.config)**

In `frontend/nuxt.config.ts`, before the `runtimeConfig` block, add:

```ts
if (process.env.NODE_ENV === 'production' && process.env.NUXT_PUBLIC_VISUAL_BYPASS === 'true') {
  throw new Error(
    'NUXT_PUBLIC_VISUAL_BYPASS must not be enabled in a production build (H6 / M2).',
  )
}
```

- [ ] **Step 9: Verify demo config excludes production**

Confirm `backend/config/demo.php` `allowed_environments` = `['local','staging','testing']` (production absent). No change needed if already so; document the seeding gate note in the M2 doc.

- [ ] **Step 10: Frontend lint/format touched files**

Run: `cd frontend && pnpm exec eslint app/middleware/00.visual-bypass.global.ts && pnpm exec prettier app/middleware/00.visual-bypass.global.ts nuxt.config.ts --check`
Expected: no errors.

- [ ] **Step 11: Pint + commit**

```bash
cd backend && vendor/bin/pint app/Support/DemoEnvironment.php app/Http/Controllers/Api/AuthController.php
git add backend/app/Support/DemoEnvironment.php backend/app/Http/Controllers/Api/AuthController.php backend/tests/Feature/Audit/DemoEnvironmentGateTest.php frontend/app/middleware/00.visual-bypass.global.ts frontend/nuxt.config.ts
git commit -m "fix(auth): fail closed on demo switch + visual bypass in production (H6)"
```

---

### Phase A checkpoint

- [ ] Run the full audit probe suite: `cd backend && php artisan test tests/Feature/Audit/Phase2RbacProbeTest.php` — CF-1/2/3/4 + demo probes all green; only the PDO deprecation notice remains.
- [ ] Run `cd backend && php artisan test tests/Feature/Auth tests/Feature/Permission tests/Feature/Engine` — 0 failures.
- [ ] Report files changed, root causes, security impact, rollback, acceptance evidence for A1–A4. **Pause for review before Phase B.**

---

## Phase B — Workflow correctness (new published V2)

**Phase note:** delivered as workflow version **V2** through the publish gate. The 48 synthetic V1 requests are reset/recreated under V2 (M1 §9) — this is a **data change requiring approval at the Phase B checkpoint** before any destructive reset. Terminal V1 records preserved. B3 and B4 use the M1-confirmed SWIFT fields (§5) and semantic map (§6).

### Task B1: WF-001 — canonical seed passes its own validator

**Files:**
- Modify: `backend/database/seeders/ImportFinancingWorkflowSeeder.php` (V2 version block: reject transitions `requires_comment=true` + `confirmation_message`; `SUPPORT→SUPPORT` `is_self_loop=true`)
- Test: `backend/tests/Feature/Audit/Phase3WorkflowConfigurationProbeTest.php` (becomes green)

- [ ] **Step 1:** Run `cd backend && php artisan test tests/Feature/Audit/Phase3WorkflowConfigurationProbeTest.php` — confirm red (5 validator errors).
- [ ] **Step 2:** In the seeder's transition rows, add `requires_comment => true` and a `confirmation_message` (AR) to the four reject transitions: `INTERNAL→CREATE`, `EXEC→CLOSED_REJECTED`, `FX_CONFIRM→FX`, `FINAL→FX_CONFIRM`.
- [ ] **Step 3:** Mark the `SUPPORT→SUPPORT` (ADD_NOTES) transition `is_self_loop => true`.
- [ ] **Step 4:** Add a seed test asserting `WorkflowVersionValidator` returns zero errors on the freshly seeded canonical version.
- [ ] **Step 5:** Run `cd backend && php artisan test tests/Feature/Audit/Phase3WorkflowConfigurationProbeTest.php` — verify green.
- [ ] **Step 6:** Pint + commit `fix(workflow): canonical seed passes validation; reasoned rejects + marked self-loop (WF-001)`.

### Task B2: WF-002 — FINAL ownership → committee_director

**Files:**
- Modify: `backend/database/seeders/ImportFinancingWorkflowSeeder.php` (`seedStagePermissions`: `FINAL` EXECUTE role → `committee_director`; `CLOSED_*` VIEW likewise as appropriate)
- Test: new `backend/tests/Feature/Engine/FinalStageOwnershipTest.php`

- [ ] **Step 1:** Write failing test: Director (`committee_director`) can execute `FINAL`; Executive Member (`committee_manager`) cannot execute `FINAL`; Executive Member can still execute `EXEC`.
- [ ] **Step 2:** Run — verify fail.
- [ ] **Step 3:** In `seedStagePermissions`, change the `FINAL` row's `roleCode` from `committee_manager` to `committee_director`. Keep `EXEC` as `committee_manager`.
- [ ] **Step 4:** Run — verify pass. Document EXEC vs FINAL purpose (M1 §3) in the seeder doc-block.
- [ ] **Step 5:** Pint + commit `fix(workflow): FINAL stage owned by committee_director; EXEC by committee_manager (WF-002)`.

### Task B3: WF-003 — SWIFT document gate at FX

**Files:**
- Modify: `backend/database/seeders/ImportFinancingWorkflowSeeder.php` (add `swift_reference`, `swift_file`, `fx_request_file` field defs; FX stage_field_rules `is_required=true` for all three; visible+read-only at FX_CONFIRM/FINAL)
- Modify: `backend/app/Http/Requests/UploadSwiftRequest.php` (drop legacy single-`file` mode; require the three-part package)
- Add route + controller wiring for the SWIFT upload surface (reuse `SwiftUploadForm.vue`)
- Migrate: `backend/tests/Feature/Engine/EngineSwiftUploadTest.php` (from `['file'=>…]` to the three-part package)
- Test: new package-gate assertions

**Interfaces:** canonical keys `swift_reference` (TEXT), `swift_file` (FILE/PDF/10MB), `fx_request_file` (FILE/PDF/10MB) — all required to leave FX (M1 §5).

- [ ] **Step 1:** Write failing integration test against the published workflow: transition out of FX is rejected until `swift_reference` + `swift_file` + `fx_request_file` all present and valid; PDF-only + size enforced.
- [ ] **Step 2:** Run — verify fail (currently advances on empty payload).
- [ ] **Step 3:** Add the three field definitions to the seeder field set (canonical snake_case keys) and set them `is_required=true` in the `FX` stage_field_rules; visible + read-only at FX_CONFIRM/FINAL/terminal.
- [ ] **Step 4:** Update `UploadSwiftRequest.php` to require the three-part package (remove legacy single-`file` acceptance) and wire the route/controller to `SwiftUploadForm.vue`.
- [ ] **Step 5:** Migrate `EngineSwiftUploadTest` to post the package; add missing/invalid-PDF, wrong-bank, wrong-role, duplicate, stale-version cases.
- [ ] **Step 6:** Run engine SWIFT tests — verify green; browser-verify the disabled transition control with reason via `playwright-cli`.
- [ ] **Step 7:** Pint + FE lint/format + commit `fix(workflow): require SWIFT package before leaving FX stage (WF-003)`.

### Task B4: semantic_role population + EXECUTIVE_REVIEW enum

**Files:**
- Modify: `backend/app/Enums/StageSemanticRole.php` (adopt canonical M1 §6 values incl. new `EXECUTIVE_REVIEW`; terminal semantics)
- Modify: `backend/database/seeders/ImportFinancingWorkflowSeeder.php` (`seedStages` sets `semantic_role` per M1 §6 map)
- Modify: read-model/dashboard to prefer `semantic_role` over literal codes (`EngineRequestReadModel`, `DashboardStatsService`, `SemanticRegistry`)
- Test: bucketing-by-semantic-role tests

- [ ] **Step 1:** Write failing test: each V2 stage resolves its approved semantic bucket; read-model buckets by `semantic_role`, not literal code.
- [ ] **Step 2:** Run — verify fail.
- [ ] **Step 3:** Add canonical enum cases per M1 §6 (`REQUEST_CREATION, BANK_INTERNAL_REVIEW, SUPPORT_COMMITTEE_REVIEW, EXECUTIVE_REVIEW, SWIFT_DOCUMENT_HANDLING, FX_CONFIRMATION, DIRECTOR_FINAL_CONFIRMATION`) + terminal `COMPLETED`/`REJECTED` (typed appropriately per the M1 §6 note). Do not reuse `EXECUTIVE_VOTE`.
- [ ] **Step 4:** Set `semantic_role` on all V2 stages in `seedStages`. Add a temporary V1 compatibility adapter (literal-code fallback) marked clearly temporary, used only for V1-pinned requests.
- [ ] **Step 5:** Point read-model/dashboard bucketing at `semantic_role` first, literal-code fallback second.
- [ ] **Step 6:** Run — verify green.
- [ ] **Step 7:** Pint + commit `feat(workflow): populate semantic_role; add EXECUTIVE_REVIEW; designer-driven bucketing (M1 §6)`.

### Phase B checkpoint

- [ ] V2 published + validator-clean; SWIFT gated; FINAL owned by Director; semantic metadata populated.
- [ ] **Request approval for the 48-request reset/recreate under V2** (data change) before executing it. Preserve terminal V1 records.
- [ ] Run full workflow + engine suites. Report + pause for review before Phase C.

---

## Phase C — API & UI reliability

### Task C1: API-UI-001 — MySQL stats + frontend request storm

**Files:**
- Modify: `backend/app/Services/Workflow/EngineRequestStatsService.php` (GROUP BY compatible with `ONLY_FULL_GROUP_BY`)
- Modify: frontend `/workflows` loader (single-flight queue+stats; cancel in-flight on filter change; stable failure boundary — surface the real 500)
- Test: MySQL integration test for stats per role/data-scope; Vitest single-flight test

- [ ] **Step 1:** Write a MySQL-targeted failing test for `EngineRequestStatsService` under `ONLY_FULL_GROUP_BY` (all role/data-scope branches).
- [ ] **Step 2:** Run — verify fail (500 on MySQL).
- [ ] **Step 3:** Fix the aggregation to select/group only `engine_requests.status` (no `.*` with ungrouped columns).
- [ ] **Step 4:** Run — verify green on MySQL.
- [ ] **Step 5:** Frontend: single-flight the queue+stats loads; cancel on filter change; one failed load → one request batch + stable retry, no auto-loop; surface the 500 (not a degraded 429).
- [ ] **Step 6:** Vitest test: one failed load causes one batch + stable retry.
- [ ] **Step 7:** Browser-verify `/workflows` no longer storms; Pint + FE lint + commit `fix(backend): MySQL-safe stats aggregation; single-flight workflow loads (API-UI-001)`.

### Task C2: UI-RBAC-001/002 — denial states

**Files:**
- Modify: `frontend/app/pages/admin/workflows.vue` (add `definePageMeta` + `screen` middleware, `requiredScreen: workflow_designer`)
- Create: shared denial/error component (landmark heading, reason, safe nav)
- Modify: `frontend/app/pages/workflows/instances/[id].vue` (catch `loadInstance()` 403/404/500/offline → denial state)
- Test: Vitest + `playwright-cli` direct-URL denial

- [ ] Steps: write failing denial-state tests → add page meta/guard → add shared denial component → catch instance-load errors → verify (browser: 403/404/429 render denial + one attempt + safe nav) → FE lint + commit `fix(ui): route guard + shared denial state for forbidden/failed pages (UI-RBAC-001/002)`.

### Task C3: UI-FX-001 + RBAC-005 — Director queue/nav consistency

**Files:**
- Modify: `frontend/app/pages/customs/index.vue` + `useEngineRequests.ts` (unify with the dashboard queue contract)
- Modify: nav derivation so `طلبات التمويل` shows only when the capability exists
- Test: dashboard ready-count == dedicated queue total; per-role `/auth/me` ↔ sidebar

- [ ] Steps: depends on B2 (Director owns FINAL → gains `requests` capability). Write failing consistency test → unify queue contract → capability-derived nav → verify → FE lint + commit `fix(ui): unify Director FX queue with dashboard; capability-derived nav (UI-FX-001, RBAC-005)`.

### Phase C checkpoint

- [x] **DONE + verified (2026-07-11).** Stats 200 on MySQL for every role; no storm; denial states render; Director dashboard == /customs == my-queue (verified by IDs, live V2 browser). Checkpoint: `docs/audit-functional/13-phase-c-checkpoint.md`. Commits `2d399706`, `352b7727`, `a59d675b`, `39e74922`.

---

## Phase D0 — Dynamic Dashboard Architecture Migration

**Approved 2026-07-11.** Decision report: `docs/audit-functional/14-dashboard-architecture-decision.md`. Runs **before** Phase D. Consolidates the per-role dashboard layer onto **two dashboards** — a capability-gated `SystemAdminDashboard` and a permission/workflow-driven `MyWorkDashboard` — feeding all "current work" surfaces from **one shared query**.

**Phase D0 constraints (in addition to Global Constraints):**

- **Shared actionable-work invariant (approved):** dashboard actionable count = dashboard actionable preview = navigation actionable badge = `my-queue` count = `my-queue` record set. **Verify by record IDs, not counts alone.** All five surfaces use one backend query contract.
- **No permanent per-role dashboard components.** Adding a dynamic role/stage/workflow must not require a new Vue component or a frontend role/stage-map edit.
- Admin/work boundary = the **`system_dashboard.view` capability**, not a frontend role-code branch. Every admin-dashboard endpoint independently authorizes on the same capability. Role code may persist only as a low-level protected invariant, never as the normal dashboard selector.
- Bank-admin analytics = **optional, capability-gated monitoring metrics**, bank-scoped, never contributing to the actionable count, always labeled analytics/monitoring, never exposing records outside DataScope.
- **Level 1 only** (fixed safe layout + dynamic authorized data + capability-gated optional metrics). Level 2 (metadata widget catalog) stays a future enhancement. The Workflow Designer must not become a general UI builder in this phase.
- Voting-remnant removal happens **only** after: no active workflow uses voting, no runtime route/table depends on it, equivalent current-work UI exists in `MyWorkDashboard`, replacement tests exist, historical requests stay readable. Do **not** broaden into deleting all backend voting legacy code (that is the separately-gated Phase F cleanup).
- `CommitteeDirectorDashboard.vue` is **transitional / rollback path** — removed only after `MyWorkDashboard` proves: Director actionable count matches my-queue, Director actionable IDs match my-queue, FINAL ownership correct, no voting UI, nav+badges aligned.
- **Pause only if:** the shared query cannot preserve current `my-queue` behavior, a schema change becomes necessary, or new evidence contradicts the approved authorization model.

**Compatibility strategy:** each role migrates behind the existing dashboards; the bespoke component + stats method stay live until its role is piloted green, then are deleted in the same slice. Backward-compat Director keys (`fx_confirmation_pending`, `customs_declaration_pending`) and the `useNavBadges` role switch are removed only after every consumer reads `actionable`.

### Task D0.1: Extract `UserActionableRequestQuery` (shared actionable-work query)

**Files:**
- Create: `backend/app/Services/Workflow/UserActionableRequestQuery.php`
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php` (`myQueue` delegates to it)
- Test: `backend/tests/Feature/Engine/UserActionableRequestQueryTest.php` (new)

**Interfaces:**
- Produces: `actionableStageIds(User): array` (= `accessibleStageIds(user, EXECUTE)`); `actionableQuery(User, Request): Builder` (`active()` + `forUser()` DataScope + `whereIn(current_stage_id, actionableStageIds)` + `applyFilters`); `actionableCount(User, Request): int`; `actionablePreview(User, Request, limit): Collection`.
- Consumes: `StagePermissionResolver`, `EngineRequestListQuery`, `EngineRequest` scopes. No role codes, no hard-coded stage codes.

- [ ] Step 1: Write parity test — `myQueue` output IDs before/after refactor identical for Director(FINAL)/Support(SUPPORT)/SWIFT(FX)/Reviewer(INTERNAL) on published V2.
- [ ] Step 2: Extract the query core from `myQueue:283-313` into the service; `myQueue` keeps `UnionStagePaginator` + SLA ordering but sources stage ids + branch factory from the service.
- [ ] Step 3: Verify — parity test green; existing engine/my-queue suites unchanged; Pint. Commit `refactor(workflow): extract UserActionableRequestQuery from my-queue`.

**Rollback:** revert the commit; `myQueue` is unchanged behaviorally, so no data/schema impact.
**Acceptance:** `my-queue` record IDs identical pre/post for all four EXECUTE roles; no behavior change.

### Task D0.2: `GET /api/v1/dashboard/work` generic work API

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/DashboardWorkController.php` + route
- Reuse: `UserActionableRequestQuery`, `EngineRequestReadModel::resourceCollection`, `DataScope`
- Test: `backend/tests/Feature/Dashboard/DashboardWorkApiTest.php` (new)

**Contract:** `{ actionable{count,items,queue_url}, claimed{count,items}, tracking{count,items,queue_url}, sla{near_due,overdue}, recent_activity[], metrics[] }`. Bounded previews; count and items from the **same** scoped query; capability-gated `metrics`; only authorized fields; multi-workflow/version safe; no N+1 (reuse eager loads).

- [ ] Step 1: Failing test — actionable count == `actionableCount`, actionable item IDs ⊆ `my-queue` IDs, same user; tracking = VIEW-not-EXECUTE only; cross-bank/org excluded; no-active-role → empty.
- [ ] Step 2: Implement; `actionable` from the shared query, `tracking` from VIEW-scope minus EXECUTE-scope, `claimed` from claim ownership, `sla` from stage SLA metadata + runtime, `metrics` gated on capability.
- [ ] Step 3: Verify + Pint. Commit `feat(workflow): add /dashboard/work generic actionable-work API`.

**Rollback:** remove route + controller + test (additive; nothing else consumes it yet).
**Acceptance:** count == preview scope; actionable IDs ⊆ my-queue IDs; negative cases (cross-scope, no-active-role, VIEW-only) hold.

### Task D0.3: `MyWorkDashboard.vue` + work store/composable

**Files:**
- Create: `frontend/app/components/dashboard/MyWorkDashboard.vue`, `frontend/app/composables/useDashboardWork.ts`, store slice
- Test: `frontend/app/tests/unit/components/MyWorkDashboard.test.ts` + section render/empty/loading/error/403/404/429/retry

**Layout (fixed, Level 1):** Actionable work → Claimed/assignable → Tracking → SLA alerts → Recent activity → Optional authorized metrics. Sections render only when the API returns data. shadcn-vue components; RTL; a11y; keyboard; responsive.

- [ ] Steps: build sections from `/dashboard/work`; actionable links open `?queue=mine`; ErrorState for denial/rate-limit (reuse C2). Verify + ESLint + typecheck + commit `feat(ui): add MyWorkDashboard (fixed layout, dynamic data)`.

**Rollback:** component unused until D0.4 routes a role to it; delete files.
**Acceptance:** all sections render from API data; state matrix covered; no role condition in the component.

### Task D0.4: Pilot one simple role + prove five-surface parity

**Pilot:** SWIFT Officer (single EXECUTE stage FX — simplest). Route only SWIFT through `MyWorkDashboard` behind the capability/selector; keep others on bespoke components.

- [ ] Step 1: Selector change — `dashboard.vue` renders `SystemAdminDashboard` when `system_dashboard.view`, else `MyWorkDashboard` for the piloted role, else legacy component (temporary).
- [ ] Step 2: Parity test (backend + E2E): SWIFT dashboard actionable count == preview IDs == nav badge == `my-queue` count == `my-queue` IDs — **by IDs**.
- [ ] Step 3: Browser-verify SWIFT via playwright-cli on V2. Commit `feat(ui): pilot SWIFT on MyWorkDashboard with five-surface parity`.

**Rollback:** flip the selector back to the bespoke SWIFT component (kept in tree).
**Acceptance:** five-surface ID parity for SWIFT; live V2 verification.

### Task D0.5: Migrate remaining workflow roles (Director last)

Order: Support → Bank Reviewer → Data Entry → Bank Admin (analytics as optional metrics) → Executive Member → **Committee Director last**. Per role slice: route to `MyWorkDashboard`, prove five-surface ID parity, browser-verify, delete the bespoke component + its role stats method **in the same slice**, then remove that role's `useNavBadges` branch.

- [ ] Per-role slices with individual parity tests + commits `refactor(ui): migrate <role> to MyWorkDashboard`.
- [ ] **Director slice** additionally re-proves: FINAL actionable count/IDs == my-queue, no voting UI, nav+badge aligned; only then delete `CommitteeDirectorDashboard.vue` (keep its FINAL-parity assertions, re-homed to the dynamic tests).

**Per-role deletion criteria (each bespoke dashboard):** its role renders `MyWorkDashboard`; five-surface ID parity green; browser-verified on V2; no other consumer of its stats method/keys remains.
**Rollback (per slice):** restore the role's selector branch + bespoke component from git; the stats method deletion is reverted with it.

### APPROVED REFINEMENT (2026-07-11): dashboard-family model

Not one dashboard per role, and not literally two Vue components — **two dashboard
families**, routed by **capability**, not role name:

- **Operational family — `MyWorkDashboard`** (the six workflow-executor roles + any
  new dynamic executor role automatically, via stage permissions). Sections:
  actionable / claimed / tracking / SLA / recent activity / **small** capability-gated
  operational KPI summaries (my-actionable, claimed-by-me, near-SLA, overdue,
  recently-completed-by-me). Contract: actionable count = preview IDs = nav badge =
  my-queue count = my-queue record set.
- **Analytics & governance family** — dedicated dashboards only where the user
  category has a fundamentally different purpose:
  - **`SystemAdminDashboard`** (evolve from `CbyAdminDashboard`): platform governance
    + platform-wide analytics; **capability-gated** (`system_dashboard.view`).
  - **`BankAdminDashboard`** (retained): bank-scoped analytics (4 KPIs, monthly
    volume chart, financing totals, completion/rejection summaries), all restricted
    by bank DataScope. Bank Admin has **no actionable queue**, so it must NOT route
    through `MyWorkDashboard` (that would show a permanent empty-work section and
    force the shared dashboard into a general analytics framework).

**Capability-family routing (selection order):** `system_dashboard.view` →
`SystemAdminDashboard`; else `bank_analytics.view` → `BankAdminDashboard`; else →
`MyWorkDashboard`. Backend endpoints enforce the same capabilities independently.
If a user ever holds >1 dashboard capability, do not silently pick by role
precedence — provide an explicit switcher or a documented capability priority.
**Do NOT** move the Bank Admin charting dashboard into the shared work-dashboard
widget contract in D0; the metadata-driven widget catalog remains a future
enhancement. A new **analytics** role should reuse an existing analytics dashboard
via capability + scope, not get a new component.

### Task D0.6: Capability-family routing + retire migrated executor dashboards + voting remnants

**Dispositions (approved family model):**
- Replace role-based dashboard selection with **capability-family routing** (order
  above) in `dashboard.vue` **and** `index.vue`.
- `MyWorkDashboard`: retained for the six executor roles.
- `CommitteeDirectorDashboard.vue`: remove (dynamic FINAL parity green + Director
  live-verified on MyWorkDashboard in D0.5); keep its FINAL-parity assertions,
  re-homed to the dynamic tests.
- Other executor-role dashboards (`DataEntry`, `BankReviewer`, `SupportCommittee`,
  `SwiftOfficer`): remove after migration + equivalent test coverage.
- `ExecutiveDashboard.vue`: remove voting remnants and retire once its non-legacy
  users have migrated (Executive Member is migrated → retire; strip voting UI /
  `VotingQueueItem` usage).
- `BankAdminDashboard.vue`: **retain** as the bank-scoped analytics dashboard.
- `CbyAdminDashboard.vue`: rename/evolve into `SystemAdminDashboard` (capability-gated).
- `useNavBadges`: the workflow actionable badge comes **only** from the shared
  actionable query; analytics dashboards must not fabricate a workflow badge. Drop
  the Director voting-sum remnant + the per-role badge switch for migrated roles.
- Obsolete role `*Stats()` methods + backward-compat Director keys removed only after
  no route/test/consumer references them (grep-confirm).
- **No backend voting-model deletion** here (Phase F).

**Required tests (family model):**
1. Executor roles route to `MyWorkDashboard`.
2. A newly created dynamic executor role routes to `MyWorkDashboard` with **no
   frontend change** (stage-permission grant only).
3. Bank Admin routes to `BankAdminDashboard`.
4. Bank Admin analytics restricted to its own bank.
5. Bank Admin does **not** receive a misleading actionable-work count.
6. System Admin routes to `SystemAdminDashboard`.
7. Unauthorized users cannot access bank/system analytics APIs.
8. Removing the analytics capability removes dashboard access.
9. Executor dashboards independent of hard-coded role names.
10. Approved charts remain available only to the analytics families.

**Rollback:** each removal/rename is its own commit for granular revert.
**Acceptance:** only the three components remain (`MyWorkDashboard`,
`BankAdminDashboard`, `SystemAdminDashboard`); capability-family selection (no
`role === UserRole` dashboard branch); nav badge reads `actionable`; no voting
dashboard UI; historical requests still open.

### Task D0.7: `AGENTS.md` + architecture docs

- [ ] Record the **dashboard-family model** (Operational: `MyWorkDashboard`;
  Analytics & governance: `BankAdminDashboard`, `SystemAdminDashboard`), the
  `UserActionableRequestQuery` invariant (count = preview = my-queue = badge, by
  IDs), the `/dashboard/work` contract, capability-family routing + the independent
  backend capability enforcement, and the "new executor role → MyWorkDashboard
  automatically; no new component per role" rule. Commit `docs(workflow): record dashboard-family architecture in AGENTS.md`.

### Phase D0 checkpoint

- [ ] Three dashboard components only (`MyWorkDashboard`, `BankAdminDashboard`,
  `SystemAdminDashboard`); five-surface ID parity proven for every executor role
  incl. Director; capability-family routing (no role-name dashboard branch);
  `SystemAdminDashboard` + bank/system analytics APIs capability-protected (positive
  **and** negative tests); a newly created dynamic executor role receives actionable
  work with **no frontend change** (E2E); Bank Admin shows no misleading actionable
  count; voting dashboard remnants gone with historical readability intact; nav badge
  = shared actionable query; full backend + frontend dashboard suites green; docs
  updated. Report + pause before Phase D.

---

## Phase D — Status-model reconciliation + docs + UX

### Task D1: API-contract additions (prerequisite for D2)

**Files:**
- Modify: `backend/app/Http/Resources/EngineRequestResource.php:34-77` (add `runtime_status`, `current_stage.semantic_role`, request-level `final_outcome`)
- Test: resource contract tests

- [ ] Steps: write failing resource test asserting the three fields → add `runtime_status` (from `status`), `current_stage.semantic_role`, request-level `final_outcome` → verify → Pint + commit `feat(backend): expose runtime_status, semantic_role, final_outcome on request resource (M6)`.

### Task D2: STATUS-DRIFT-001 — frontend enum reconciliation

**Files:**
- Modify: `frontend/app/types/enums.ts` (retire the 22-value `RequestStatus`) + introduce focused types + mapping functions
- Modify: all 42 dependent files (labels, badges, colors, timelines, dashboard buckets, filters, detail, tables, notifications, stores, composables, guards)
- Modify: tests + fixtures

**This is the largest task (1,119 refs / 42 files). Execute as internal slices, each with its own tests + commit, per the M6 10-step roadmap. This is NOT a blind global replace.**

- [ ] Step 1: **Inventory** — classify each `RequestStatus` usage as runtime-status / current-stage / semantic-role / final-outcome / display-only. Record the inventory before edits.
- [ ] Step 2: Introduce focused frontend types + mapping functions (no oversized enum); derive stage presentation from API metadata (no hard-coded stage list).
- [ ] Step 3: Add the temporary, clearly-marked V1 compatibility adapter for requests lacking `semantic_role`; never used for new versions; measurable removal criteria.
- [ ] Step 4–8: Migrate slice-by-slice (labels/badges → timelines → dashboard buckets → filters/queues → detail/tables/notifications), each slice: write/adjust tests first, migrate, verify old pinned V1 requests still render, commit. Keep the old mapping until each slice's replacement test is green.
- [ ] Step 9: Remove the 22-value `RequestStatus` enum + dead voting/customs presentation code (only after all slices green + dependency evidence).
- [ ] Verify: `pnpm typecheck` + full `pnpm test` (contract/type/store/cross-module change). Commit per slice `refactor(frontend): reconcile <slice> to engine status model (STATUS-DRIFT-001)`.

### Task D3: AGENTS.md + docs reconciliation (CF-6)

- [ ] Update AGENTS.md: five runtime statuses; designer-defined stages; final outcomes separate; real DB role codes; "Voting not in V1"; "COMMITTEE_DIRECTOR does not auto-inherit EXECUTIVE_MEMBER unless configured"; source-of-truth hierarchy; "frontend must not define an independent canonical status enum." Add a deprecation banner to `docs/user-view/`. Commit `docs(repo): reconcile AGENTS.md with dynamic engine; deprecate user-view (CF-6)`.

### Task D4: UX improvements

- [ ] Metadata-driven action labels; standardize the command-palette dialog pattern (focus-trap/Esc/return-focus) for all dialogs; ensure denial-state components expose landmark headings. Commit `fix(ui): metadata-driven action labels; standardized dialog a11y pattern`.

### Phase D checkpoint

- [ ] No `RequestStatus` enum; frontend derives state from API; historical requests render correctly; AGENTS.md matches the engine. Full FE suite + typecheck green. Report + pause before Phase E.

---

## Phase E — Regression & concurrency coverage

- [ ] Land the Phase-8 suites (RBAC org matrix, admin-only-screen negatives, active-identity matrix, workflow-coverage manifest, MySQL stats integration, denial-state browser tests, enum-reconciliation regression, adapter tests).
- [ ] Confirm `Phase2RbacProbeTest` + `Phase3WorkflowConfigurationProbeTest` fully green (standing gate).
- [ ] Real parallel MySQL transition race (20–50 concurrent callers, exactly one success, rest stale) — **requires approval for throwaway fixtures / load run** at this checkpoint.
- [ ] Commit `test(testing): land RBAC/workflow regression suites; concurrency race`.

### Phase E checkpoint — report + pause before Phase F.

---

## Phase F — Final verification + gated legacy cleanup

- [ ] Re-run all role/workflow paths (all 8 roles) against V2 via `playwright-cli`.
- [ ] Full backend (`php artisan test`) + frontend (`pnpm test`) for release sign-off; report against the known-red baseline.
- [ ] Execute the deployment-verification checklist (M2 §2) against prod/staging config.
- [ ] Legacy-cleanup task (voting stack removal + `docs/user-view` archival) — **gated on the removal criteria in `09-m6 §5` and separate approval**: confirm no route/table/persisted-data/queue-job/event-listener/current-version dependency, no audit history rendered unreadable, replacement tests exist.
- [ ] Remove the temporary status compatibility adapter once its exit criteria are met (all active V1 requests/versions resolved).
- [ ] Final report of every fixed finding with acceptance evidence.

### Phase F checkpoint — implementation complete.

---

## Self-review notes

- **Spec coverage:** every finding in `04-final-report §6` maps to a task — RBAC-004→A1, RBAC-002→A2, RBAC-001/003→A3, H6→A4, WF-001→B1, WF-002→B2, WF-003→B3, semantic/M1§6→B4, API-UI-001→C1, UI-RBAC-001/002→C2, UI-FX-001/RBAC-005→C3, STATUS-DRIFT-001/CF-6→D1/D2/D3, UX→D4, CF-5 (folded into D3 docs), concurrency→E, legacy→F.
- **Type consistency:** `DemoEnvironment::switchingAllowed()`, `activeRoleCodes()`, canonical SWIFT keys (`swift_reference`/`swift_file`/`fx_request_file`), and the `runtime_status`/`semantic_role`/`final_outcome` API fields are used consistently across tasks.
- **Data-change gates:** B (48-request reset), E (throwaway fixtures/load), F (legacy removal) each pause for explicit approval per the execution rules.
