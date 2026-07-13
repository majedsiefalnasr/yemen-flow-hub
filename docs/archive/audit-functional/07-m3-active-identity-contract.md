# M3 — Active-Identity Authorization Contract (RBAC-001, Approved: Option A)

**Status:** Locked. Only the single active pivot attached to an active role may
authorize. Inactive pivot rows are historical audit records only. No code changed
yet. Evidence date: 2026-07-11.

**Severity / tier:** RBAC-001 stays **High**, Phase A / pre-production, security
and privilege-revocation. Confirmed privilege-retention defect, not accepted
behavior. RBAC-003 linked as the related `/auth/me` consistency issue.

---

## 1. The intended contract

A role participates in authorization **only** when all three hold:

1. `user_roles.is_active = true` (the pivot is active)
2. `roles.is_active = true` (the role record is active)
3. it is the user's single active role (`assertSingleActiveRole` invariant)

Deactivating or replacing a role → **immediate and complete** privilege
revocation. Inactive pivots must never grant runtime, administrative, workflow,
API, screen, dashboard, search, export, claim, or policy access.

## 2. Verified current defect

| Helper                               | Location           | Defect                                                                                                                                         |
| ------------------------------------ | ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `isSystemAdmin()`                    | `User.php:222-229` | Ignores `is_active` on both pivot and role; **both** the loaded-relation branch and the query branch match any historical `system_admin` pivot |
| `hasRoleCode()` / `hasAnyRoleCode()` | `User.php:231-249` | Correct when an active role exists, but **falls back to all historical roles** when `role()` is null                                           |
| `assignActiveRole()`                 | `User.php:160-181` | Correctly deactivates prior pivots but **keeps the rows** — this is intended history preservation and stays unchanged                          |

Dynamic evidence: admin→support reassignment still returned `isSystemAdmin()===true`
and reached `/admin/settings` (200); a role-free account with an inactive admin
pivot satisfied `hasRoleCode(system_admin)`.

## 3. Approved correction contract

**`isSystemAdmin()`** — return true only when the user has an active `system_admin`
role through the **active pivot**. Both execution paths (loaded-relation branch,
DB-query branch) must behave identically; neither may inspect historical pivots.

**`hasRoleCode()` / `hasAnyRoleCode()`** — evaluate the **active role only**.
Remove the historical-role fallback. A user with no active role has no
role-derived permissions even if inactive historical pivots remain.

**Inactive role records** — an active pivot pointing to a role with
`roles.is_active = false` must not authorize. Effective rule = pivot active
**AND** role record active.

**`/auth/me` + capability derivation** — align `PermissionService`
(`derivedRequestsCapabilitiesForUser` and screen-permission building) with the
same active-identity-only contract, so the frontend never receives request,
administrative, or screen capabilities from inactive historical roles. This
closes **RBAC-003** alongside RBAC-001.

## 4. Blast radius — confirmed contained

`grep` verification: **no code path independently queries historical roles.**
Zero hits for `->roles()->`, `wherePivot('is_active')`, or `whereHas('roles')`
outside `User.php`. All 58 privileged checks across ~20 files (policies,
controllers, jobs, services, providers — including `AuthServiceProvider`,
`HorizonServiceProvider`, `EngineRequestPolicy`, `AuditLogPolicy`, `UserPolicy`,
`BankPolicy`, `EngineClaimService`, `FxConfirmationAuthorizationService`,
`SearchController`, `AuditLogController`, `GenerateAuditLogExport`,
`PermissionService`) route through the three helpers.

**Implication:** fixing the three helpers centrally corrects every call site. No
scattered bypass to patch. Still, the implementation must re-grep after the fix
to confirm no new direct historical-role query was introduced.

## 5. Required implementation coverage (tests)

1. Active `system_admin` pivot + active role → authorized.
2. Inactive `system_admin` pivot → denied.
3. Active pivot + inactive role record → denied.
4. Admin reassigned to Support → all admin access revoked immediately.
5. No active role + historical admin pivot → denied.
6. Loaded-relation and unloaded/query paths produce identical results.
7. `/auth/me` exposes only the active role's capabilities.
8. Admin settings, audit read/export, global request listing, search, dashboards, claim overrides, FX authorization all reject the demoted user.
9. Historical pivot rows remain available for audit and reporting.
10. Reassignment to another valid role grants only the new role's permissions.

Plus: re-inspect all direct role checks/helper usages so no path independently
queries historical roles and bypasses the corrected helpers.

## 6. Constraints

Do **not** delete historical pivot rows. The fix is helper-level authorization
tightening, not history removal. `assignActiveRole` history preservation is
retained.
