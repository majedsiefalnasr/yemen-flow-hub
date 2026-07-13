# Functional / RBAC / Workflow Audit - Phase 2 Checkpoint

**Scope:** focused authorization probes for the Phase 1 candidates in
`00-discovery.md`. This is an audit artifact, not an implementation plan and not
approval to change application behavior.

**Evidence date:** 2026-07-11

## Verification summary

Command:

```bash
cd backend
php artisan test tests/Feature/Audit/Phase2RbacProbeTest.php
```

Result: 9 probes executed. Six secure expectations failed. Three secure
baselines passed but PHPUnit reported the repository's existing
`PDO::MYSQL_ATTR_SSL_CA` deprecation notice.

Control command:

```bash
php artisan test tests/Feature/Permission/ScreenPermissionTest.php
```

Result: 15 tests and 52 assertions passed, with the same deprecation notice.
This confirms that the focused failures are not caused by a generally broken
screen-permission fixture.

## Confirmed findings

### RBAC-001 - Inactive historical roles retain privileged identity

**Severity:** High

**Status:** Confirmed by unit-level model probes and a real reassignment probe.

**Current behavior:**

- `User::isSystemAdmin()` searches every loaded or persisted role without
  requiring `user_roles.is_active = true` or `roles.is_active = true`.
- `User::hasRoleCode()` and `hasAnyRoleCode()` fall back to all historical roles
  when the user has no active role.
- `assignActiveRole()` intentionally keeps prior pivot rows and marks them
  inactive.

**Expected behavior:** only the single active pivot role, whose role record is
also active, may contribute authorization.

**Dynamic evidence:**

- An account whose only `system_admin` pivot was inactive returned
  `isSystemAdmin() === true`.
- The same account returned `hasRoleCode(system_admin) === true`.
- An account reassigned from `system_admin` to `support` still returned
  `isSystemAdmin() === true`.
- The reassigned account was denied `/api/admin/settings`, demonstrating an
  important distinction: checks using `hasRoleCode()` short-circuit to the new
  active Support role, while checks using `isSystemAdmin()` remain bypassed.

**Confirmed affected authorization surfaces:**

- `EngineRequest::scopeForUser()` system-wide list bypass.
- Audit-log system-wide visibility and audit export scope.
- Pulse and Horizon operational dashboards.
- Direct `AuditLogPolicy` access.
- Search system-wide branches.

**Conditionally affected surfaces:** users with no active role but an inactive
historical admin pivot also satisfy `hasRoleCode(system_admin)`. This reaches
bank/user policies, engine request detail bypass, claim release override, FX
authorization, and system settings. These endpoints require dedicated direct
API probes before the final blast-radius matrix is closed.

**Reproduction:** `Phase2RbacProbeTest::test_cf1_*`.

**Security impact:** privilege revocation is incomplete. Removing or replacing
the active administrator role does not reliably remove administrator access.

### RBAC-002 - Admin-only screen permissions are API-delegable

**Severity:** High

**Status:** Confirmed end to end at the API/policy layer.

**Current behavior:** the screen-permission matrix hides
`workflow_designer` and documents it as system-admin-only, but
`RoleScreenPermissionController::update()` accepts every screen except
`requests`. A system administrator can therefore submit:

```json
{
  "grants": {
    "workflow_designer": ["MANAGE"]
  }
}
```

for the Support role. The update returned HTTP 200. After cache invalidation, a
Support user received HTTP 200 from `GET /api/v1/workflow-definitions` because
the designer policies trust `workflow_designer:MANAGE`.

**Expected behavior:** screens in `ADMIN_ONLY_SCREENS` are rejected by the
write API, not merely omitted from the UI matrix.

**Reproduction:**
`Phase2RbacProbeTest::test_cf2_admin_only_screen_grant_rejected`.

**Security impact:** the backend contract permits delegation of workflow
definition control to roles that the product declares ineligible. The same
write path must be tested against every admin-only screen, especially
`screen_permissions`, `users`, `roles`, `organizations`, `banks`, and
`reference_data`.

### RBAC-003 - `/auth/me` derives request capabilities from inactive roles

**Severity:** Medium

**Status:** Confirmed.

**Current behavior:** `PermissionService::derivedRequestsCapabilitiesForUser()`
collects all team and role IDs without active filters. The runtime
`StagePermissionResolver` filters inactive teams and roles. A user whose only
Support pivot was inactive received `requests: [VIEW, UPDATE]` from `/auth/me`
although runtime stage authorization denied the identity.

**Expected behavior:** navigation and screen capabilities use the same active
identity as runtime authorization.

**Reproduction:**
`Phase2RbacProbeTest::test_cf4_auth_me_requests_capability_uses_active_roles_only`.

**Impact:** misleading navigation and action visibility. This is currently a
frontend fail-open display issue rather than proof of a backend action bypass,
because runtime authorization independently rejects the inactive identity.

### UI-RBAC-001 - Workflow designer direct URL renders a blank denial state

**Severity:** Medium

**Status:** Confirmed with `playwright-cli` using the seeded Support user.

**Current behavior:** the Support navigation correctly omits the workflow
designer. Direct navigation to `/admin/workflows` nevertheless mounts the page,
runs `onMounted(reload)`, and calls
`GET /api/v1/workflow-definitions?page=1&per_page=25...`. The backend correctly
returns HTTP 403. The page-level `ScreenGuard` then suppresses its slot, leaving
an authenticated shell with no page content, forbidden explanation, recovery
action, or redirect.

**Expected behavior:** the route declares `auth` + `screen` middleware with
`requiredScreen: workflow_designer` and redirects the user to `/forbidden`
before the designer fetch runs. A denied API response must also render a clear
error state as defense in depth.

**Evidence:**

- Browser account: `support1@cby.gov.ye` (Support Committee).
- URL remained `/admin/workflows`.
- Network: workflow-definitions request returned 403.
- Console: one failed-resource 403 error.
- Accessibility snapshot: application shell and MFA reminder only; no main page
  heading, forbidden message, or retry/navigation action.
- Source: `frontend/app/pages/admin/workflows.vue` has no `definePageMeta`, calls
  `onMounted(reload)`, and wraps the template in `ScreenGuard`.

**Security impact:** none demonstrated; the backend remained authoritative and
denied the data. **User impact:** confusing blank page, unnecessary forbidden
request, and inconsistent direct-URL behavior.

## Secure controls verified

| Control                          | Evidence                                                                              | Result                               |
| -------------------------------- | ------------------------------------------------------------------------------------- | ------------------------------------ |
| Cross-bank request detail        | Bank B user requested Bank A engine request by ID                                     | Denied                               |
| Demo identity switch disabled    | `switch-demo-user` called while `demo.allow_role_switch=false`                        | Denied                               |
| Existing screen-permission suite | 15 tests / 52 assertions                                                              | Passed with deprecation notices      |
| Runtime inactive stage identity  | Existing `AccessibleStageIdsParityTest` and resolver source filter active teams/roles | Present; targeted suite still to run |

## Candidates still open

| Candidate                         | Current evidence                                                                                                                      | Next verification                                                      |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| CF-3 list/detail scope asymmetry  | Static mismatch confirmed between `DataScope` and `EngineRequestPolicy::inScope`; dynamic probe stopped at its RBAC-001 precondition  | Re-run with a role-free OTHER-classification user and stage VIEW grant |
| CF-5 immutable-version error code | Two exception paths appear to emit 403 vs 409                                                                                         | Exercise controller and global exception paths                         |
| CF-6 enum/documentation drift     | Static evidence confirmed                                                                                                             | Product decision on legacy status/role contract                        |
| UI-001 settings route duplication | Closed as false positive: `pages/settings.vue` is the parent `<NuxtPage />` wrapper and `pages/settings/index.vue` is its index child | No further action                                                      |

## Phase 2 next slice

1. Direct API probes for RBAC-001 across audit detail/export, request list/detail,
   search, claim release, FX authorization, Pulse/Horizon, settings, users, and
   banks.
2. Exhaustive negative probes for every `ADMIN_ONLY_SCREENS` key and for granting
   `screen_permissions:MANAGE` to a non-admin role.
3. Organization and bank isolation probes for users, teams, roles, reports,
   notification records, audit exports, and file downloads.
4. Stage-permission matrix probes for wildcard, org, team, role, and user rows,
   including inactive identities and EXECUTE-implies-VIEW behavior.

## Information required before browser and environment-sensitive phases

- Local runtime is confirmed available: Nuxt on port 3000, Laravel on 8000,
  MySQL on 3306, and Redis on 6379. Permission is still required before creating
  throwaway audit fixtures or executing destructive workflow transitions.
- Deployment values for `APP_ENV`, `demo.allowed_environments`,
  `demo.allow_role_switch`, and `NUXT_PUBLIC_VISUAL_BYPASS`.
- Product confirmation whether stage-level EXECUTE intentionally grants every
  outgoing transition, and whether field visibility is intentionally stage-only
  rather than viewer-specific.
