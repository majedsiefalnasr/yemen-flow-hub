# Screen Permissions Simplification — Design

Date: 2026-07-04

## Problem

Two independent, disconnected permission systems exist today:

1. **`screen_permissions` table** (role_id, screen_id, capability) — 6 capabilities
   (VIEW, CREATE, UPDATE, DELETE, EXPORT, MANAGE). Edited via the admin
   Screen Permissions matrix page. This is what the matrix UI shows and
   what `PermissionService::userHasCapability()` reads.
2. **Legacy `permissions`/`role_permissions` tables** (slug-based, e.g.
   `merchants.manage`, `roles.manage`, `workflow.design`, `docrules.manage`,
   `audit.view`, `reports.view`) — read via `User::hasPermission($slug)` →
   `PermissionService::userCan()`, and via the global `Gate::before()` hook
   for any dotted ability name passed to `Gate::authorize()`.

15 policies (Merchant, Role, Team, Organization, 9 workflow-designer
policies, ReferenceValue, ReferenceTable, AuditLog) and 2 controllers
(`ReportController`, `ReportExportController`) enforce access through the
**legacy** system. None of them consult `screen_permissions`. This means
today, toggling a capability in the Screen Permissions admin UI does not
actually change what a role can do for Merchants, Reports, or Audit — the
UI and the enforcement are disconnected.

Additionally, the 6-capability model is more granular than the product
needs: CREATE/UPDATE/DELETE are never toggled independently anywhere in
the UI or policies — they always travel together as "manage".

## Goals

1. Reduce `ScreenCapability` to three values: `VIEW`, `MANAGE`, `EXPORT`.
2. Partition screens into three groups:
   - **System-admin-only** (never delegable, hidden from the matrix):
     Organizations, Users, Banks, Roles, Teams, Settings, Workflow
     Designer, Reference Data, Screen Permissions.
   - **Delegable** (shown in the Screen Permissions matrix, manually
     grantable to any role): Merchants, Reports, Audit.
   - **Global, no permission check**: Notifications, Settings (profile).
3. Special-case: `system_admin` on the Merchants screen gets `VIEW` +
   `EXPORT` only, never `MANAGE` — enforced in code, not just by omission
   from seed data.
4. Delete the legacy `permissions`/`role_permissions` tables, the
   `Permission` model, `PermissionSeeder`, and `PermissionService::userCan()`
   / `permissionsForRole()` / `legacyScreenPermissionsForUser()` /
   `SCREEN_MAP` — all dead once every consumer is rewired.
5. Rewire every real consumer of the legacy system to
   `PermissionService::userHasCapability()` against `screen_permissions`,
   so the admin UI's toggles actually control behavior end-to-end.

## Non-goals

- No change to `stage_permissions` / `StagePermissionResolver` (workflow
  stage access levels VIEW/EXECUTE) — unrelated system, same name
  coincidence only.
- No change to `requests` screen derivation logic (`derivedRequestsCapabilities()`)
  — stays workflow-stage-driven, excluded from manual grants, as today.
- No new EXPORT-specific gate on `ReportExportController` — it currently
  shares the `reports.view` gate with `ReportController`; that becomes
  `userHasCapability($user, 'reports', 'VIEW')` unchanged in effective
  behavior. Introducing a separate export capability check is a follow-up,
  not part of this change.
- No UI change to how `requests`/global screens are displayed.

## Backend Changes

### 1. `ScreenCapability` enum

```php
enum ScreenCapability: string
{
    case VIEW = 'VIEW';
    case MANAGE = 'MANAGE';
    case EXPORT = 'EXPORT';
}
```

### 2. Data migration

New migration on `screen_permissions`:
- Any row with capability `CREATE`, `UPDATE`, or `DELETE` → rewritten to
  `MANAGE`.
- Deduplicate resulting `(role_id, screen_id, 'MANAGE')` rows (the unique
  constraint on `(role_id, screen_id, capability)` means re-inserting an
  existing MANAGE row must be skipped, not violate the constraint).
- `VIEW`/`EXPORT`/`MANAGE` rows untouched.
- After rewrite: delete any `system_admin` + `merchants` + `MANAGE` row
  (enforces the carve-out at the data layer too, belt-and-suspenders with
  the code-level guard in `PermissionService`).

### 3. `ScreenPermissionSeeder`

Rewritten to only emit VIEW/MANAGE/EXPORT, matching the new partition.
`system_admin`'s merchants grant becomes `['VIEW', 'EXPORT']` (no MANAGE).

### 4. `RoleScreenPermissionController`

- `SCREEN_CAPABILITIES` const becomes the full delegable-screen capability
  catalog:
  ```php
  private const SCREEN_CAPABILITIES = [
      'merchants' => ['VIEW', 'MANAGE', 'EXPORT'],
      'reports' => ['VIEW', 'MANAGE', 'EXPORT'],
      'audit' => ['VIEW', 'MANAGE', 'EXPORT'],
  ];
  ```
  (Matches your spec: delegated roles may be granted MANAGE on Merchants,
  even though system_admin itself is barred from it — the carve-out is
  system_admin-specific, not a screen-wide cap removal.)
- `UNIVERSAL_SCREENS` / `ADMIN_ONLY_SCREENS` exclusion lists updated so
  the matrix query only ever returns Merchants, Reports, Audit as
  customizable screens (system-admin-only screens already excluded today;
  just confirming the final 9-screen list matches).
- `update()`'s `$validCapabilities` now derives from the shrunk enum
  automatically (`ScreenCapability::cases()`).

### 5. `PermissionService`

- Add `userHasCapability()` guard: if `$screenKey === 'merchants'` and the
  role is `system_admin`, strip `MANAGE` from the result before the
  `in_array` check (defense in depth alongside the data migration).
- Delete: `SCREEN_MAP`, `userCan()`, `permissionsForRole()`,
  `legacyScreenPermissionsForUser()`, `rolesForPermission()`,
  `clearRoleCache()` (all only serve the legacy path).
- `screenPermissionsForUser()` simplified to always call
  `screenPermissionsForGovernanceRole()` (the `legacyScreenPermissionsForUser()`
  fallback branch is deleted — every user has a governance role in the
  current model, confirmed by existing behavior).
- `capabilitiesForUser()` (used by `AuthMeResource`) unchanged in shape,
  still reads from `screenPermissionsForUser()`.

### 6. Policy rewiring (15 policies + `Gate::before`)

Each policy's single `hasPermission($slug)` call becomes
`userHasCapability($user, '<screen>', '<CAP>')`, resolved via dependency
injection of `PermissionService` (matching how `RoleScreenPermissionController`
already consumes it) — replace `$user->hasPermission()`, not `User::hasPermission()`
itself (that method is deleted since it only wrapped `userCan()`).

| Policy | Legacy slug | New check |
|---|---|---|
| MerchantPolicy::create/update/delete | `merchants.manage` | `userHasCapability($user, 'merchants', 'MANAGE')` |
| MerchantPolicy::viewAny (implicit via `is_active`) | — | unchanged, no screen check today |
| RolePolicy, TeamPolicy, OrganizationPolicy::viewAny | `roles.manage` | `userHasCapability($user, 'roles', 'MANAGE')` |
| 9 workflow-designer policies (`WorkflowDefinition/Version/Stage/Action/Transition/FieldGroup/FieldDefinition/StageFieldRule/StagePermission`)::viewAny | `workflow.design` | `userHasCapability($user, 'workflow_designer', 'MANAGE')` |
| ReferenceValuePolicy, ReferenceTablePolicy::viewAny | `docrules.manage` | `userHasCapability($user, 'reference_data', 'MANAGE')` |
| AuditLogPolicy::viewAny/view | `audit.view` | `userHasCapability($user, 'audit', 'VIEW')` |
| `ReportController`/`ReportExportController` (`Gate::authorize('reports.view')`) | `reports.view` | replace with `abort_unless($permissionService->userHasCapability($user,'reports','VIEW'), 403)` (Gate::authorize on a slug ability has no policy to fall back to once `Gate::before` is removed, so these call sites change from a bare ability string to a direct capability check) |

Each policy's `create`/`update`/`delete` methods already just delegate to
`viewAny($user)` (verified — no per-instance ownership logic mixed in
except Merchant's bank-scope check and Organization's `before()` active-user
gate, both preserved as-is around the new capability check).

`Gate::before()` hook in `AuthServiceProvider` is deleted along with the
legacy system (nothing left to dispatch dotted abilities to).

### 7. Cleanup

- Drop `permissions` and `role_permissions` tables (new migration).
- Delete `App\Models\Permission`.
- Delete `database/seeders/PermissionSeeder.php` and remove its call from
  `DatabaseSeeder`.

## Frontend Changes

- `useScreenPermissionsAdmin.ts` / `screen-permissions.vue`: `CAP_LABELS`
  shrinks to `{ VIEW: 'عرض', MANAGE: 'إدارة', EXPORT: 'تصدير' }` (CREATE/UPDATE/DELETE
  labels removed — dead now that the enum no longer produces them).
- `isForced()` (MANAGE implies VIEW) logic unchanged — still correct under
  the 3-capability model.
- No column-count change needed beyond what naturally follows from the
  backend now correctly returning `['VIEW','MANAGE','EXPORT']` for all
  three delegable screens (this also happens to fix the original bug
  report: Reports/Audit EXPORT grants will now render as visible columns).
- `types/models.ts` `ScreenCapability` type shrinks to
  `'VIEW' | 'MANAGE' | 'EXPORT'`.

## Testing

- Update `tests/Feature/Permission/ScreenPermissionTest.php` fixtures/assertions
  for the 3-capability model and the merchants system_admin carve-out.
- Add a migration test (or extend an existing one) asserting old
  CREATE/UPDATE/DELETE rows rewrite to MANAGE with no duplicate-key
  violation.
- Add policy-level tests (or extend existing feature tests for
  Merchant/Report/Audit endpoints) confirming a role granted `merchants.MANAGE`
  via `screen_permissions` can now actually create/update/delete a
  merchant — closing the enforcement gap this design fixes.
- Full backend suite run once at the end (cross-cutting change touching
  15 policies + a core service) rather than per-file, per repo verification
  ladder's "broad refactor" exception.

## Risk / Rollback

- Single deploy: migration + code must land together (old code path
  disappears, so this is not backward-compatible mid-deploy). Acceptable
  for this internal admin tool per existing deploy practice — flagging
  since AGENTS.md's normal verification ladder assumes narrow changes.
- Rollback = revert the code deploy; the data migration is one-directional
  (CREATE/UPDATE/DELETE→MANAGE is lossy) but functionally harmless to
  leave applied even if code is rolled back, since old code reads
  `screen_permissions` rows by exact string match against `CREATE`/`UPDATE`/`DELETE`,
  and would simply stop seeing those (roles would appear to lose granular
  grants, collapsing to MANAGE-shaped behavior only if union of new value
  is present) — acceptable given this is a full forward migration, not a
  toggle.
