# Screen Permissions Matrix Cleanup — Design

Date: 2026-07-13
Status: Approved (pending spec review)

## Problem

`admin/screen-permissions.vue` currently has three defects:

1. **`system_dashboard` is shown as a grantable matrix column**, but only `system_admin` can ever hold it — no other role should route to the CBY governance dashboard. A column that can never legitimately be toggled for any other role is not logical to present as grantable.
2. **`bank_analytics` is misnamed.** The capability gates the bank-scoped analytics dashboard, but "bank" is the wrong noun — the analytics dashboard belongs to *an organization* (an org of type bank today, but the concept is organizational, not bank-specific). The key and label should say `org_analytics`.
3. **There is no screen-permission column for the org's own staff-management page (`/staff.vue`).** Today that page is gated by a hardcoded `requiredRoles: [UserRole.BANK_ADMIN]` role check — bypassing the screen-permission system entirely. It cannot be granted to any other role without a code change.

While investigating (3), a duplicate route was found: `/bank/users.vue` is a thin wrapper around the shared `IdentityUsersPage` component (`audience="bank"`), gated by the shared `users` screen key. It has **zero navigation references** anywhere in the app (sidebar, command palette, search, dashboards) — `/staff.vue` (752 lines, bespoke implementation) is the page actually in use. `/bank/users.vue` is dead code left over from an earlier implementation; `AGENTS.md`'s documented page list still names it as canonical, which is now wrong.

## Decisions

### 1. Hide `system_dashboard` from the matrix UI only

`DashboardStatsService.php:35` uses `system_dashboard` as a live, revocable capability gate (`analyticsGate(RoleCodes::SYSTEM_ADMIN, 'system_dashboard')`), and `DashboardFamilyCapabilityTest.php` explicitly tests that revoking it removes dashboard access. This is load-bearing — full removal would break dashboard routing and delete the only mechanism for ever revoking the grant.

Fix: exclude `system_dashboard` from the frontend matrix's grantable columns, the same way `requests` is already excluded (`REQUESTS_KEY` pattern in `screen-permissions.vue`). Backend (Screen row, seeder grant, `DashboardStatsService` gate) is untouched.

### 2. Rename `bank_analytics` → `org_analytics`

A straight key + label rename, not a behavior change. The gate logic stays `hasRoleCode(BANK_ADMIN) && capability` — this does not open the analytics dashboard to any new role, it only corrects the name.

Touch points:

- **Backend:** `ScreenPermissionSeeder.php` (screen key `bank_analytics` → `org_analytics`, Arabic label "تحليلات البنك" → "تحليلات المنظمة", grant key under `bank_admin`), `DashboardStatsService.php:36` gate check string, `DashboardFamilyCapabilityTest.php` (test method names `test_bank_admin_with_capability_gets_bank_analytics` / assertions / `revokeCapability('bank_admin', 'bank_analytics')` calls), comment references in `AssignsGovernanceIdentity.php`, `DashboardStatsTest.php`, `CbyAdminDashboardStatsTest.php`, key list in `ScreenPermissionTest.php:74`.
- **Frontend:** `dashboard.vue:35` and `index.vue:31` (`can('bank_analytics', 'VIEW')` → `can('org_analytics', 'VIEW')`), `DashboardPage.test.ts` (mock implementation, test name, assertions).
- **Deploy note:** the seeder's `Screen::updateOrCreate(['key' => $key], ...)` matches by key. A stale `bank_analytics` row in an already-seeded environment will not auto-rename to `org_analytics` — it will remain as an orphaned row alongside a newly-created `org_analytics` row. Environments must either re-run the seeder after a manual key rename in the DB, or accept a new row is created and manually clean up the old one. This is a deploy-time step, not something the code change handles automatically.

### 3. New `staff` screen for `/staff.vue`

Adds a normal, grantable, **VIEW-only** column (matching the `reports`/`audit` pattern — no separate MANAGE switch, since `/staff.vue` doesn't distinguish read-only vs. manage behavior today).

- **Seeder:** add `'staff' => 'الموظفون'` to the screens list; grant `bank_admin` `['VIEW']` by default, preserving current access.
- **Frontend (`staff.vue`):** replace
  ```ts
  definePageMeta({
    middleware: ['auth', 'role'],
    requiredRoles: [UserRole.BANK_ADMIN],
  })
  ```
  with
  ```ts
  definePageMeta({
    middleware: ['auth', 'screen'],
    requiredScreen: 'staff',
    requiredCapability: 'VIEW',
  })
  ```
  This is the only reference to `UserRole.BANK_ADMIN` in the file.
- **Matrix UI (`screen-permissions.vue`):** `staff` requires no special handling — it is not in `MANAGEABLE_SCREENS`, so it automatically renders as a single VIEW switch via the existing `displayedCaps()` logic.
- **Backend (`UserPolicy.php`):** the `/api/v1/users` endpoints (shared by `/staff.vue` and `/admin/staff.vue`) are authorized entirely by hardcoded role checks (`RoleCodes::SYSTEM_ADMIN`, `RoleCodes::BANK_ADMIN`) — independent of screen-permission capabilities. Granting `staff` to a non-`BANK_ADMIN` role via the matrix would unlock the frontend page but every API call would still 403, making the grant a no-op. To make the column functionally real, inject `PermissionService` into `UserPolicy` and OR a `staff` VIEW-capability check alongside the existing `BANK_ADMIN` role check in `viewAny`, `create`, `update`, `delete`, `resetPassword`, `resetMfa`, `resetPin` — the same defense-in-depth pattern `DashboardStatsService::analyticsGate` already uses. `canManageOwnBankUser`'s `bank_id` scoping is unchanged (out of scope — this task fixes the *permission* gate, not the org-scoping model).

### 4. Delete `/bank/users.vue`

Confirmed dead: no references in `AppSidebar.vue`, `CommandPalette.vue`, `SearchForm.vue`, `GlobalSearch.vue`, or any dashboard component. Delete `frontend/app/pages/bank/users.vue`. Update `AGENTS.md`'s `## Pages` list (drop `/bank/users`, keep `/staff` as the documented org-staff page — it is already listed as `/staff` there, so this removes a route that was never actually wired to that documented entry).

## Out of scope

- Renaming the `BankAdminDashboard.vue` component or any other "bank"-named identifier not directly tied to the `bank_analytics` screen-permission key.
- Generalizing `bank_id`-based org scoping to a broader `organization_id` model.
- Adding a MANAGE capability to the `staff` screen.
- Any change to `/admin/staff.vue` (CBY staff management) — it continues to use the shared `users` screen key, unaffected by this work.

## Testing

- Backend: `php artisan test --filter=ScreenPermissionTest`, `php artisan test --filter=DashboardFamilyCapabilityTest`, `php artisan test --filter=UserPolicyTest` (or equivalent Feature test covering `/api/v1/users` authorization) — add a case granting `staff` to a non-BANK_ADMIN role and confirming API access.
- Frontend: `pnpm exec vitest run app/tests/unit/pages/screen-permissions.test.ts`, `pnpm exec vitest run app/tests/unit/composables/useScreenPermissionsAdmin.test.ts`, `pnpm exec vitest run app/tests/unit/pages/DashboardPage.test.ts`.
- No full suite run by default per project verification ladder; focused tests only unless baseline is needed.
