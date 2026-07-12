# Permission Model

Yemen Flow Hub runs **two independent authorization systems**, not one.
They gate different things, use different code paths, and match on
different dimensions — both must be understood to reason about "can this
user do X."

1. **Screen-capability system** — gates admin-console and analytics
   screens (`system_dashboard`, `bank_analytics`, `merchants`, …). Grants
   are **role-based**: a `screen_permissions` row is keyed by `role_id`.
2. **Workflow stage-permission system** — gates VIEW/EXECUTE on
   `EngineRequest` workflow stages. Grants match on an **identity set**:
   optional `organization_id`, `team_id`, `role_id`, and `user_id`
   dimensions, not `role_id` alone (see §2 below).

The `App\Enums\UserRole` PHP enum (the canonical role list quoted below) is
**not consulted by either authorization system's runtime resolver**
(`PermissionService::userHasCapability()` or
`StagePermissionResolver::userCanAccessStage()`). It is, however, used
elsewhere: `App\Models\User::asUserRole()`/`hasRole()`/`isBankUser()`/
`isCbyUser()` (classification helpers — the last two delegate to
`App\Enums\UserRole::isBankRole()`/`isCbyRole()` on the enum itself),
`scopeWithUserRole()`/`scopeWithoutUserRole()` (Eloquent query scopes),
API serialization (e.g.
`GET /api/auth/me`, `AuthController`), and
`App\Services\Notifications\NotificationRegistry` (selecting notification
recipients by `UserRole` case). Treat it as a role-classification and
serialization vocabulary layered on top of the two permission systems, not
as a bystander.

For the request-state fields these permissions gate access to
(`runtime_status`, `current_stage`, `final_outcome`), see
[`05-request-state-model.md`](05-request-state-model.md). For the stage
graph these permissions attach to, see `02-workflow-engine.md`
(**planned, not yet written** — Step 4; today's authority is
[`../01-workflow-and-business-rules.md`](../01-workflow-and-business-rules.md)
together with
[`../decisions/semantic-mapping.md`](../decisions/semantic-mapping.md)).

---

## Canonical role enum

```
DATA_ENTRY
BANK_REVIEWER
BANK_ADMIN
SWIFT_OFFICER
SUPPORT_COMMITTEE
EXECUTIVE_MEMBER
COMMITTEE_DIRECTOR
CBY_ADMIN
```

Source: `backend/app/Enums/UserRole.php`.

This enum is **not** the primary authorization identity. Runtime checks
resolve against `App\Models\Role` (table `roles`, snake_case `code`, e.g.
`system_admin`, `bank_admin`, `intake`, `internal_reviewer`, `fx_swift`,
`support`, `committee_manager`, `committee_director`, `fx_confirm` — see
`backend/app/Support/RoleCodes.php`) via the `user_roles` pivot.
`backend/app/Support/UserRoleMapper.php` maps governance role codes to
`UserRole` cases only for API responses (e.g. `GET /api/auth/me`). Note that
two governance codes — `committee_manager` and `fx_confirm` — both map to
`UserRole::EXECUTIVE_MEMBER`.

---

## 1. Screen-capability system

Gates access to admin-console and analytics **screens** — not workflow
requests.

| Concept    | Model / Enum                  | Fields                                                     |
| ---------- | ----------------------------- | ---------------------------------------------------------- |
| Screen     | `App\Models\Screen`           | `key` (e.g. `system_dashboard`, `bank_analytics`), `label` |
| Capability | `App\Enums\ScreenCapability`  | `VIEW`, `MANAGE`, `EXPORT`                                 |
| Grant      | `App\Models\ScreenPermission` | `role_id`, `screen_id`, `capability`                       |

Seeded in `backend/database/seeders/ScreenPermissionSeeder.php` — this is
where the literal screen keys (`system_dashboard`, `bank_analytics`, …)
are defined and granted to roles. **Screens cannot be created at runtime.**
`ScreenController` exposes only `index()` — adding a new screen requires a
migration, a seeder update, and a deploy. An admin can only reassign
capabilities on **existing** screens, via
`PUT /api/v1/roles/{role}/screen-permissions`.

The runtime gate is a plain service method, not a Laravel `Gate`/`Policy`:

```php
App\Services\Authorization\PermissionService::userHasCapability(
    User $user,
    string $screenKey,
    string $capability
): bool
```

`PermissionService::screenPermissionsForUser()` /
`screenPermissionsForGovernanceRole()` cache results for one hour.

`App\Services\Dashboard\DashboardStatsService` routes on this exact
`system_dashboard` → `SYSTEM_ADMIN` / `bank_analytics` → `BANK_ADMIN`
capability check to select which analytics dashboard a user gets — the
same capability-family routing described in
[`04-dashboard-architecture.md`](04-dashboard-architecture.md).

### CBY_ADMIN restriction on `merchants:MANAGE`

`PermissionService::userHasCapability()` hardcodes a deny: if
`$screenKey === 'merchants'`, `$capability === 'MANAGE'`, and the user
holds `RoleCodes::SYSTEM_ADMIN`, the method returns `false`
unconditionally — defense-in-depth on top of the seeder, which never
grants `system_admin` MANAGE on `merchants` in the first place (migration
`2026_07_05_000001_collapse_screen_permission_capabilities.php` actively
strips any pre-existing grant of that shape).

This is the **only** place a CBY_ADMIN-specific code guard exists. The
broader "CBY_ADMIN must never act as a workflow super-actor for Director,
SWIFT, Support, Bank Reviewer, or Executive Member actions" rule (see
AGENTS.md) is **not** mechanically enforced anywhere in the
stage-permission system below — no validator or DB constraint stops a
`stage_permissions` row from granting `system_admin` EXECUTE on a
workflow stage. It holds today only because no such row is seeded.
Treat it as a convention that the Workflow Designer must continue to
respect, not a code-enforced invariant — until a corresponding guard is
added, do not assume it can't happen.

---

## 2. Workflow stage-permission system

Gates VIEW/EXECUTE on `EngineRequest` workflow stages — this is what
`EngineTransitionService` checks before allowing a transition.

| Concept      | Model / Enum                 | Fields                                                                                                     |
| ------------ | ---------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Grant        | `App\Models\StagePermission` | `stage_id`, `organization_id`, `team_id`, `role_id`, `user_id`, `access_level`, `display_label`, `version` |
| Access level | `App\Enums\StageAccessLevel` | `VIEW`, `EXECUTE` (no separate `CLAIM` level — see §4)                                                     |

`StageAccessLevel::satisfies()` — `EXECUTE` satisfies a `VIEW` check.

### Resolution

`App\Services\Workflow\StagePermissionResolver`:

```php
userCanAccessStage(User $user, WorkflowStage $stage, StageAccessLevel $level = VIEW): bool
accessibleStageIds(User $user, StageAccessLevel $level = VIEW): array
```

Matching is identity-set based: each set field on a `stage_permissions`
row (`organization_id`, `team_id`, `role_id`, `user_id`) must match the
user for that row to apply; a `null` field is a wildcard; multiple
matching rows OR together. `Http\Requests\StagePermissionConsistency`
validates only that `team_id`/`role_id`/`user_id` on a row belong to the
same `organization_id` when one is set — it is data-integrity validation,
not a role-exclusion rule.

Managing `stage_permissions` rows in the designer itself requires the
`workflow_designer:MANAGE` screen capability
(`App\Policies\StagePermissionPolicy`) — that policy gates who may _edit
grants_, not which role a grant may name.

### Enforcement order in `EngineTransitionService::execute()`

Called inside a single `DB::transaction()`, in this order:

1. `EngineRequest::lockForUpdate()->findOrFail($id)` — pessimistic lock.
2. `isActive()` check → `REQUEST_CLOSED` (403) if not.
3. Optimistic `version` match → `REQUEST_STALE` (409) on mismatch.
4. Transition/from-stage match → `TRANSITION_NOT_AVAILABLE`.
5. **Permission**: `StagePermissionResolver::userCanAccessStage(..., EXECUTE)` → `StageExecutionForbidden` (403).
6. **Claim** (only reached if step 5 passed) — see §4.

A user without EXECUTE permission never reaches the claim check. The same
lock → status → version → transition → permission → claim ordering
applies in `saveDraft()` and `abandonDraft()`.

---

## 3. Organization / data-scope enforcement

`App\Services\Authorization\DataScope` — a plain static-method service,
**not** a global scope, Eloquent trait, or query-builder macro. Each read
surface must call it explicitly; it is not automatic.

```php
DataScope::forUser(User $user): DataScopeContext  // { systemWide: bool, ownBankId: ?int }
DataScope::applyTo(Builder $query, DataScopeContext $scope, string $bankColumn = 'bank_id'): Builder
```

Resolution rule (`forUser()`): based on
`$user->organization->classification` —
`OrganizationClassification::NATIONAL_COMMITTEE` → system-wide (no
filter); `BANKING_SECTOR` → own-bank only (`where($bankColumn, $ownBankId)`);
anything else → **deny-by-default** (`whereRaw('1 = 0')`).

Confirmed call site: `App\Services\Dashboard\DashboardStatsService::stats()`
threads a resolved `DataScopeContext` through its per-role stats
builders. Because `DataScope::applyTo()` must be invoked per read surface
rather than being enforced automatically at the model layer, do not
assume every controller/query already applies it — verify the specific
surface you're touching calls `DataScope` before trusting its output is
org-scoped.

---

## 4. Claim ownership

Claim is a separate gate from stage permission, checked only after
permission passes (step 6 above):

```php
if ($stage->requires_claim && !($request->claimed_by === $user->id && $request->isClaimed())) {
    // CLAIM_NOT_HELD (403)
}
```

Claim fields live on `engine_requests`: `claimed_by`, `claimed_at`,
`claim_expires_at`, `claim_stage_id`. TTL and heartbeat endpoints are
documented in AGENTS.md's Support Claim Behavior section.

**The live claim TTL is admin-configurable, not the static config value.**
`App\Services\Workflow\EngineClaimService` reads
`AdminSettingsService::get('support_claim_ttl', 15)` (admin-editable,
5–60 minutes) — this is the value actually enforced at runtime.
`config('workflow.support_claim_ttl_minutes')` (`backend/config/workflow.php`,
env `SUPPORT_CLAIM_TTL_MINUTES`, default 15) exists but is **not** read by
`EngineClaimService`. Both default to 15 minutes today, but they are two
different settings — treat the `AdminSettingsService`-backed value as
authoritative for claim TTL behavior.

Force-release of another user's claim (`EngineClaimService::release()`)
requires `RoleCodes::SYSTEM_ADMIN`; otherwise `CLAIM_NOT_HELD`.

---

## 5. Audit logging

`App\Services\Audit\AuditService::log(AuditAction $action, ?User $actor, ?Model $subject, array $metadata, ...)`.

Role is captured fresh at call time — true role-at-time-of-action, not a
cached login-time value:

- `actor_role_id` — `$actor?->role()?->id` (FK to `roles`).
- `user_role` — `$actor?->asUserRole()?->value ?? $engineRole?->code`
  (string, for unauthenticated/system entries `user_id` is `NULL`).

This is a **manual, per-call** contract — there is no Eloquent
observer/event listener that auto-writes audit rows. Audit-sensitive
mutations are expected to call `AuditService::log()` explicitly
themselves; there is no mechanism that adds this automatically, so when
writing a new mutating service method that should be audited, you must
add the call yourself.

**Coverage is not universal — verify per caller, don't assume it.**
Several services do call it (`EngineTransitionService`,
`EngineClaimService`, `WorkflowDesignerService`, `WorkflowActionService`,
…), but at least one confirmed counterexample mutates without any
`AuditService` dependency at all:
`App\Services\Settings\UserPreferencesService` writes
`$user->user_preferences` via `updateForUser()`/`resetForUser()`/
`saveSection()` and calls `$user->save()` directly, with no
`AuditService` injected into the class. Before documenting or relying on
"this mutation is audited," check the specific service for an
`AuditService` dependency and an actual `log()` call — do not assume
coverage from the pattern being common elsewhere.

**The transaction boundary is caller-defined, not a property of
`AuditService` itself.** `EngineTransitionService::execute()` does call
`AuditService::log()` from inside its `DB::transaction()`, so a failed
transition there rolls back its audit row along with the mutation. But
this is not universal — e.g. `PasswordRecoveryService`'s
`AuditService::log()` call runs after its `$user->save()` with no
surrounding transaction, so the audit write and the mutation are two
separate, sequential statements there, not one atomic unit. Check the
specific caller before assuming its audit row is guaranteed atomic with
the mutation it describes.

`workflow_history` (per-transition stage log) is written by
`EngineTransitionService::execute()` immediately before the paired audit
call, sharing a `correlation_id` UUID with it. `workflow_history` itself
carries **no role column** — role attribution for a transition lives only
in its linked `audit_logs` row via that shared `correlation_id`.

---

## Summary: where to look

| Question                                         | File                                                                      |
| ------------------------------------------------ | ------------------------------------------------------------------------- |
| Can this user see/use this admin screen?         | `PermissionService::userHasCapability()`                                  |
| Can this user VIEW/EXECUTE this workflow stage?  | `StagePermissionResolver::userCanAccessStage()`                           |
| Is this query scoped to the user's organization? | `DataScope::forUser()` + `applyTo()` (caller must invoke)                 |
| Does this user hold the claim on this request?   | `EngineClaimService`, checked inside `EngineTransitionService::execute()` |
| Who did this, in what role, when?                | `AuditService::log()` + linked `workflow_history` row                     |
