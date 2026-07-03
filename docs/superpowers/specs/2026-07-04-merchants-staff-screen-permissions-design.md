# Merchants Table, Staff Rename, Screen-Permissions Fix — Design

## Context

Second sub-project from the original 9-item admin/UX request (quick-wins slice already shipped). User explicitly requested these four items be bundled into one spec/plan (overriding the default one-subsystem-per-spec split):

1. Merchants page: bank-scoped advanced table for BANK_ADMIN, matching CBY_ADMIN's existing table.
2. Rename `admin/cby-staff` route to `admin/staff`.
3. Fix `admin/screen-permissions` table to reflect real, enforced permissions.
4. Make `admin/screen-permissions` the sole source of truth for role screen permissions (system admin excluded via hardcoded bypass, not a matrix row).

Items 3 and 4 turned out to be the same underlying backend bug, discovered during investigation (see below) — not two independent asks.

## 1. Merchants page — bank-scoped advanced table

**Current state** (`frontend/app/pages/merchants.vue`): the page already has two render branches gated on `isCbyAdmin`:
- CBY_ADMIN (`v-if="isCbyAdmin"`, lines 819-910): full TanStack `DataTable` — sortable columns, faceted filters (status, bank), bulk export, row-selection, the same tooling `admin/staff` (IdentityUsersPage) uses.
- BANK_ADMIN (`v-else`, lines 913-1055): a card-grid view (`grid md:grid-cols-2 lg:grid-cols-3`), no sorting/filtering/export, separate skeleton-loading markup.

Data is already bank-scoped via the `scoped` computed (`merchants.vue:175-180`): `isBankAdmin.value && user.value?.bank_id` filters `merchants.value` to the user's bank. Both `preFiltered` (used by the DataTable) and `filtered` (used by the card grid) derive from `scoped`.

**Decision:** give BANK_ADMIN the same `DataTable` component and `columns` array CBY_ADMIN uses. Delete the card-grid branch (lines 913-1055) and its dedicated skeleton markup entirely — one table implementation for both roles, difference is only in data scope (`scoped`) and available row actions (already conditional on `isBankAdmin.value` inside the `actions` column, `merchants.vue:602-622`).

**Column adjustments for BANK_ADMIN:**
- Hide the `bank` column (`id: 'bank'`, `merchants.vue:488-497`) — redundant when every row belongs to the same bank. Use `columnVisibility` default (`transactions: false` already exists at line 369; add `bank: !isCbyAdmin.value` computed default, or make it conditional via `v-if` equivalent in the columns array construction).
- The CBY-only "risk summary" banner (cross-bank duplicate warning, `merchants.vue:802-816`) and `riskSummary`/`crossBankNames` computeds stay CBY-only — no change needed, already gated.
- Faceted bank filter (`DataTableFacetedFilter` for `bank`, lines 859-864) hidden for BANK_ADMIN (no point filtering by bank when there's only one).
- KPI cards (`MetricGrid`, lines 757-800) stay for both roles — already scoped via `stats` computed which reads from `scoped`.

**Toolbar/actions:** `DataTableToolbar`, `DataTableViewOptions`, `DataTableExport`, `DataTableBulkExport` — all already role-agnostic (operate on whatever `columns`/`data` they're given), no change needed beyond passing the same props for both roles.

**Dialogs** (create/edit/view merchant, duplicate-warning) — unchanged, already shared between both current branches.

## 2. `admin/cby-staff` → `admin/staff` rename

Rename `frontend/app/pages/admin/cby-staff.vue` → `frontend/app/pages/admin/staff.vue`. Nuxt file-based routing updates `/admin/cby-staff` → `/admin/staff` automatically; page content (`<IdentityUsersPage audience="committee" />`) is unchanged.

**Naming collision check:** `frontend/app/pages/staff.vue` already exists at route `/staff` — a distinct, unrelated page (bank-side staff management for BANK_ADMIN, using `useUsers`/`StaffModal`, nothing to do with the CBY-side identity users page being renamed here). No actual path collision (`/staff` vs `/admin/staff` are different routes), but flagging since the two pages will now have confusingly similar names/paths. Out of scope to rename/merge `staff.vue` — not part of this request.

Update all reference sites (route path string `/admin/cby-staff` → `/admin/staff`):
- `frontend/app/constants/role-surfaces.ts:343` — `'nav.admin.cby_staff': '/admin/cby-staff'` → keep the key name or rename to `nav.admin.staff` (rename the key too, since `cby_staff` is the exact naming this task is retiring — update all consumers of this key accordingly).
- `frontend/app/constants/workflow.ts:1008` — `'/admin/cby-staff': rolesForSurface(...)` → update path and the surface key reference.
- `frontend/app/components/AppSidebar.vue:144` — sidebar nav path.
- `frontend/app/components/layout/GlobalSearch.vue:206` — `navigateTo('/admin/cby-staff')` → `navigateTo('/admin/staff')`.
- `frontend/app/components/CommandPalette.vue:82,105,222` — three occurrences: shortcut-key map, search-keyword map (`'cby staff admin users'` string can stay as a search alias or be updated — keep as-is, it's a search-matching string not a path), and the `GROUP_DEFS` routes array (note: this array already lists `/staff` and `/admin/cby-staff` side by side — after rename it becomes `/staff` and `/admin/staff` in the same group, which is correct, not a duplicate).
- `frontend/app/components/SearchForm.vue:44,63` — shortcut-key map and route list.
- Test files: `frontend/app/tests/unit/constants/nav-items.test.ts`, `frontend/app/tests/unit/pages/IdentityUsersPages.test.ts`, `frontend/app/tests/unit/pages/CbyAdminPages.test.ts`, `frontend/app/tests/unit/pages/prototype-parity-pages.smoke.test.ts` — update path assertions.

Arabic nav label ("إدارة المستخدمين") is unchanged — only the route path and any `cby_staff`-named keys change.

## 3+4. Screen-permissions accuracy + sole source of truth

### Root cause (verified by reading the code directly)

Two independent bugs, both in `backend/app/Http/Controllers/Api/V1/RoleScreenPermissionController.php`:

**Bug A — `requests` column shows fake data.** The matrix's `requests` column (`derivedRequestsAccess()`, lines 165-221) computes VIEW/CREATE/UPDATE from the **published workflow's `stage_permissions`** (which role is assigned to which workflow stage). But actual runtime enforcement — `PermissionService::screenPermissionsForGovernanceRole()` (`backend/app/Services/Authorization/PermissionService.php:76-100`) — reads the **static `screen_permissions` table** for `requests` exactly like every other screen, with no workflow awareness at all. These two computations are structurally independent and can disagree arbitrarily.

Per product intent (confirmed with user): workflow designer stage assignments **should** be the real source for `requests` access — matching the existing UI copy in `admin/workflows.vue`'s help text ("هذه الإسنادات... مصدر صلاحيات شاشة الطلبات"). Today that promise is false at the enforcement layer; only the matrix's display honors it.

**Bug B — dead role-code exclusion.** `matrix()` line 114: `->where('code', '!=', 'platform_admin')`. No role with code `platform_admin` exists anywhere in the seeders (`GovernanceSeeder.php`, `UserSeeder.php`) — the real CBY admin role code is `system_admin`. So `system_admin` is NOT excluded from the matrix; it appears as a row, but every screen it holds grants on is filtered out via `ADMIN_ONLY_SCREENS`/`UNIVERSAL_SCREENS` (lines 65-79), leaving a misleading blank row instead of a cleanly hidden one — contradicting the UI's own banner text ("مسؤول النظام يملك كل الصلاحيات تلقائيًا (غير معروض)" — "not shown").

**Also found, in scope for the fix:** `derivedRequestsAccess()` line 198 has a query bug — `->where('role_id', $roleIds)` where `$roleIds` is an array. This must be `whereIn('role_id', $roleIds)`; the current form does not behave as an IN clause. Must fix before this logic becomes the enforcement path, or the bug propagates from display-only into real access control.

### Fix design

**Extract and correct the derivation.** Move `derivedRequestsAccess()` out of `RoleScreenPermissionController` into `PermissionService` as a new method, e.g.:

```php
public function derivedRequestsCapabilities(array $roleIds): array
```

Same logic as today's `derivedRequestsAccess()`, with the `where` → `whereIn` fix at the `stage_permissions` query. Output shape unchanged: `array<roleId, ['view' => bool, 'add' => bool, 'edit' => bool]>`.

**Make it the enforcement source.** `screenPermissionsForGovernanceRole(int $roleId)` stops reading `screen_permissions` table rows for the `requests` key. After building `$result` from the table query (excluding `requests`), merge in a single-role call to `derivedRequestsCapabilities([$roleId])`, translating `view/add/edit` → `VIEW/CREATE/UPDATE` capability array format to match the existing map shape (e.g. `view` → `VIEW`, `add` → `CREATE`, `edit` → `UPDATE`).

**Cache correctly.** Current cache key `"screen_permissions.role.{$roleId}"`, 1-hour TTL, busted only by `clearScreenPermissionCache()` (called from `RoleScreenPermissionController::update()`). Since `requests` capability now depends on the published workflow version too, bust this same cache key (for all roles, since derivation is workflow-wide not per-role) whenever:
- `StagePermissionController::store/update/destroy` writes a `stage_permissions` row, or
- `WorkflowVersionController::publish/archive` changes which version is published.

Add a `clearAllScreenPermissionCaches()` helper (or loop known role ids) called from these four write paths. Accept this is a broader invalidation than per-role (correctness over precision — workflow changes are infrequent admin actions, not hot-path writes).

**Retire the static `requests` data.** `database/seeders/ScreenPermissionSeeder.php` — remove every `'requests' => [...]` line (lines 40, 46, 51, 59, 66, 73, 80, 87, 103) from the `$grants` array. Keep `'requests' => 'الطلبات'` in the `$screens` list (line 22) — the `Screen` row itself still needs to exist for the matrix header and any FK relationships. This ensures no parallel static data can silently disagree with the derived logic ever again — the seeder can no longer write `requests` rows into `screen_permissions` at all.

**Collapse the duplicate CREATE check.** `EngineRequestController.php:88` — remove the `userHasCapability($user, 'requests', 'CREATE')` gate entirely. The per-workflow-version stage check immediately below (lines 95-106, via `StagePermissionResolver::userCanAccessStage`) already correctly derives create-eligibility per workflow; the static check was a redundant, now-provably-inconsistent second gate. After removal, `availableWorkflows()` returns whatever the stage-permission filter produces — if that list is empty, the frontend's "create request" entry points naturally show nothing to select, which is the correct behavior (no separate 403 short-circuit needed).

**Matrix reuses the same method.** `RoleScreenPermissionController::matrix()` calls `$this->permissionService->derivedRequestsCapabilities($roleIds)` instead of its own private method — single source, structurally impossible to diverge from enforcement going forward. `requests` column stays visually read-only in the matrix (Switch disabled, no save wiring) — per user decision, editing `requests` grants happens only via the workflow designer, not this page. This is an intentional, acknowledged exception to "sole source of truth for screen permissions": the workflow designer remains the source for `requests` specifically; `screen-permissions.vue` remains the sole source for every other screen's grants.

**`system_admin` exclusion fix.** `matrix()` line 114: `'platform_admin'` → `'system_admin'`. This correctly removes the CBY admin role's row from the matrix entirely, matching the existing banner copy's claim.

### Frontend changes

None required beyond what already exists — `screen-permissions.vue` already renders `role.requests` as read-only-derived (`isForced`/`manualCan` logic already treats `requests` specially via the separate `REQUESTS_KEY` column group, `screen-permissions.vue:274-288`, which only reads, never toggles). Once the backend returns *correct* derived data and correctly excludes `system_admin`, the existing frontend renders it correctly with zero template changes. Verify via manual browser check after the backend fix lands.

## Testing

- **Merchants table**: existing merchants Vitest specs (if any reference the card-grid markup) need updating to assert against `DataTable` structure instead. New/updated test: bank-scoped column visibility (no `bank` column, no bank facet filter for BANK_ADMIN).
- **Staff rename**: update the 4 test files listed in section 2 to assert `/admin/staff` instead of `/admin/cby-staff`.
- **Screen-permissions backend**: this is the highest-risk change in the bundle (auth logic, caching, a live query bug).
  - Unit/feature test for `PermissionService::derivedRequestsCapabilities()`: multiple roles, `whereIn` behaves correctly with 2+ role ids (regression test for the found bug — a single-role array literal would have masked it).
  - Feature test: `screenPermissionsForGovernanceRole()` returns workflow-derived `requests` capabilities matching a seeded `stage_permissions` fixture, not the (now-removed) static seed data.
  - Feature test: publishing a new workflow version changes a role's effective `requests` capability on the next request (cache-bust correctness).
  - Feature test: `matrix()` endpoint's `requests` column matches `screenPermissionsForGovernanceRole()`'s output for the same role — the single-source guarantee, asserted directly.
  - Feature test: `system_admin` role does not appear in `matrix()`'s roles list.
  - Feature test: `EngineRequestController::availableWorkflows()` after removing the static CREATE gate — still correctly filters to workflows the user's role can access via stage permissions.
  - Existing tests likely to break and need updating: any fixture/test currently relying on seeded `screen_permissions` rows for `requests` (search `backend/tests` for `'requests'` capability assertions tied to `ScreenPermissionSeeder`).

## Out of scope

- Making `requests` grants editable from `screen-permissions.vue` (explicitly decided against — workflow designer remains the edit path).
- Any other screen-permissions matrix columns — only `requests` and the `system_admin` exclusion are touched.
- The remaining docs-cleanup item (separate spec/plan, per user's explicit split).
