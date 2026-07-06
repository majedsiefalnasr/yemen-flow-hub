# WP-10 — Role Model Migration

**Status:** Draft for review (Phase 6) — authorization-core change
**Source of authority:** `2026-07-05-feature-notes.md`
**Traceability:** D14-N2 / D23-N7 (pivot canonical + `users.role` removal), D15-N1 / D23-N8 (`committee_director` resolution), D15-N3 (reset-password coverage), D15-N7 / D23-N12 (reset-PIN V1), D3-N5 (protected role codes), D8-N2 / D15-pre-note (single-role-per-user).
**Dependencies:** WP-0; WP-R R6 (`RoleCodes` constants exist). Respects WP-9 guards (role delete/deactivate already workflow-aware by then).
**Enables:** WP-14 (legacy cleanup terminal — role column drop is a prerequisite for the cleanup wave).
**Overall risk:** medium-high — authorization core + a column drop. Mitigated by staged migration: dual-write exists today; this package makes pivot canonical, then removes the column after all readers migrate.

## Change classification

| Item | Kind |
|------|------|
| RM-1 pivot canonical (all readers migrate) | Approved functional (D14-N2) |
| RM-2 single-role enforcement | Approved functional (D8-N2) |
| RM-3 `users.role` column removal | Migration/cleanup (D23-N7) — terminal step |
| RM-4 protected role codes | Approved functional (D3-N5) |
| RM-5 stale `committee_director` gate resolution | Approved functional (D15-N1, D23-N8) |
| RM-6 admin reset-password coverage | Approved functional (D15-N3) |
| RM-7 V1 reset-PIN | Approved functional (D15-N7, D23-N12) |

**Explicitly out of scope:** two-layer visibility (WP-7); lifecycle guards (WP-9, already shipped); legacy controller removal (WP-14 — RM-3 feeds it).

---

## RM-1 — Pivot canonical: migrate all `users.role` readers

**Current:** dual storage — `user_roles` pivot (drives `StagePermissionResolver`, policies, `PermissionService`, FX/policy role-code checks) **and** `users.role` enum column (drives `UserResource`, `AuthMeResource`, `GovernanceUserResource`, `DemoUserResource`, `StageHistoryResource`, `CustomsDeclarationResource`, `AuditLogResource` via `user_role`, frontend `auth.store`, demo endpoints, dashboard dispatch, search groups). No propagation; the two can disagree.
**Required:**
- Inventory every `users.role` reader (D23-N7 list: 8 resources + frontend + demo + dashboard/search).
- Migrate each to read the pivot relationship (`$user->role()` / `roles()`) instead of the column. `UserResource`/`AuthMeResource` resolve the role from the pivot; `AuditLogResource` keeps reading the captured `user_role`/`actor_role_id` snapshot (audit is point-in-time — unchanged).
- `AuditService` already captures both (`user_role` string + `actor_role_id`) — keep; it's snapshot data, not live role.
- Dual-write (`legacyRoleFor` in `V1UserController`) stays during RM-1 as a compatibility shim; removed in RM-3.
**Acceptance:** grep finds no live-role read from `users.role` outside the audit snapshot and the legacy-write shim; authorization + presentation agree (single source).

## RM-2 — Single-role enforcement

**Current:** `V1UserController::validateIdentity` enforces one team + one role at validation (`team_ids`/`role_ids` prohibited). Pivot is M:N structurally.
**Required:**
- DB-level guard: unique partial index or a `user_roles.is_active`/single-active-role convention + application guard preventing a second active role assignment. (A user may have historical inactive role rows for audit; exactly one active.)
- `User::role()` returns the single active role; multi-role helpers (`hasAnyRoleCode` across multiple roles) reviewed — under single-role, "any" collapses to "the one"; keep the helper for code-shape but it operates on one role.
- Assignment enforces one active role; changing role deactivates the prior active row (audited).
**Acceptance:** a user can never hold two active roles; permission resolution is unambiguous.

## RM-3 — `users.role` column removal (terminal)

**Prerequisite:** RM-1 complete (no live reader) + a migration window verifying no dependency remains.
**Required:**
- Backfill verification: every user's pivot active-role matches the column (or column is already unused); discrepancies resolved pre-drop.
- Drop the `users.role` column; remove `legacyRoleFor` dual-write shim.
- Remove the `UserRole` enum where it only backed the column (keep if referenced by other code — audit).
**Risk:** the one irreversible step in the package — column drop. Mitigated by shipping RM-1 first and waiting a release before RM-3 if cautious.
**Acceptance:** schema has no `users.role`; app functions end-to-end on pivot alone.

## RM-4 — Protected role codes (D3-N5)

**Current:** role codes are editable in CRUD (D14); `system_admin` and other system anchors protected only by `is_system`/`isProtected` (D14-N5, verified in WP-9 G-3).
**Required:**
- A protected-code registry (extends R6 `RoleCodes`): codes that cannot be renamed/deleted/code-changed — `system_admin` plus any system-critical anchor. Display names editable; technical codes stable.
- Role CRUD (WP-14 V1) blocks rename/code-change/delete of protected codes with a clear error.
**Acceptance:** protected codes immune to admin rename/delete; display name editable.

## RM-5 — Stale `committee_director` resolution (D15-N1, D23-N8)

**Current:** multiple security gates reference `committee_director` (FX upload, customs policy, `UserPolicy::resetPassword`) but it's not in the `legacyRoleFor` mapper → possibly unassignable → gates may be dead. Post-WP-8, FX auth is stage-permission-based (F-12), so most `committee_director` gates are removed there.
**Required:**
- After WP-8 removes the FX role-code gates, sweep remaining `committee_director` references.
- **Decision (from D15-N1):** if a Director business role is needed, create it as a real protected assignable role in the registry (RM-4); otherwise remove the stale gates entirely (auth driven by stage permissions + capabilities, not the code).
- Verify seeds: confirm which role code actually represents the Director; reconcile `committee_manager` (mapped) vs `committee_director` (referenced).
**Acceptance:** no stale/dead role-code gate remains; Director (if kept) is a real assignable role.

## RM-6 — Admin reset-password coverage (D15-N3)

**Current:** `UserPolicy::resetPassword` allows system_admin to reset only `system_admin/support/committee_manager/committee_director/bank_admin`; bank_admin covers own-bank `intake/internal_reviewer`. **Nobody can admin-reset `fx_swift` or `fx_confirm`.**
**Required:**
- system_admin: reset any non-self user (safety checks apply).
- bank_admin: own bank/org scope only.
- Self-reset via admin endpoint stays blocked.
- All resets: `must_change_password`, session/token invalidation, audit, centralized policy (WP-6 A-3).
**Acceptance:** every operational role has an admin recovery path; no role stranded on email-OTP-only.

## RM-7 — V1 reset-PIN (D15-N7, D23-N12)

**Current:** reset-PIN exists only in the legacy `UserController`; V1 has reset-password + reset-mfa but no reset-pin.
**Required:**
- `POST /v1/users/{id}/reset-pin` (policy-gated like reset-mfa): clears PIN, user sets new one, session invalidation as appropriate, audited, follows WP-6 step-up/security decisions.
- Legacy reset-pin migrated/removed in WP-14.
**Acceptance:** V1 provides a complete account-recovery/admin-security surface; legacy reset-pin retireable.

---

## Business rules (consolidated)

1. One canonical role source: `user_roles` pivot (single active role per user).
2. `users.role` column removed after migration; no live role decisions from it.
3. System-critical role codes are protected from rename/delete/code-change.
4. No stale/dead role-code authorization gates; Director (if kept) is a real role.
5. Every operational role has an admin password/PIN reset path.

## Error cases

| Case | Response |
|------|----------|
| Second active role assignment | 422 `MULTIPLE_ROLES_NOT_ALLOWED` |
| Rename/delete protected code | 422 `ROLE_CODE_PROTECTED` |
| Self admin reset | 403 |

## Acceptance criteria

1. No live `users.role` reader (outside audit snapshot); authorization + presentation agree.
2. Single-active-role enforced at DB + app; assignment deactivates prior.
3. `users.role` column dropped; app runs on pivot alone.
4. Protected-code registry blocks rename/delete of system anchors.
5. No stale `committee_director` gate; Director resolved (real role or removed).
6. Admin reset-password covers all roles; V1 reset-PIN exists.
7. All WP-0 suites green; WP-9 guards respected (role lifecycle still workflow-aware).

## Test cases

- **Feature:** role assignment single-active enforcement; protected-code rename/delete blocked; admin reset coverage matrix; V1 reset-PIN flow.
- **Unit:** `User::role()` returns active role; resource serialization from pivot.
- **Migration:** backfill verification (pivot matches column) pre-drop; post-drop app health.
- **Regression:** existing role-based access unchanged for valid single-role users.

## Manual verification steps

1. Assign a second role → blocked; change role → prior deactivated.
2. Rename `system_admin` → blocked; rename display name → succeeds.
3. Reset password for an `fx_swift` user as system_admin → succeeds (previously impossible).
4. Reset PIN via V1 → cleared, user sets new.
5. Confirm no `committee_director` gate remains dead; Director role (if kept) assignable.

## Rollback considerations

RM-1/RM-2/RM-4/RM-5/RM-6/RM-7 revert independently. **RM-3 (column drop) is irreversible** — ship behind RM-1 + a verification window; if cautious, defer RM-3 a release. Dual-write shim makes RM-1 safe to revert (column still populated).

## Open questions

1. **RM-5 Director fate:** keep as a real protected assignable role, or remove entirely (auth via stage permissions)? Needs business confirmation — the Director is a named business role in PRODUCT.md, so lean keep-as-real-role.
2. **RM-2 single-active mechanism:** DB partial unique index vs application guard vs `is_active` flag on pivot rows — recommend `is_active` flag (allows historical inactive rows for audit) + app guard. Confirm.
3. **RM-3 timing:** drop immediately after RM-1, or wait a release for safety? Recommend wait one release.
