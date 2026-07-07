# WP-14 Legacy Cleanup Wave — Progress Ledger

Plan: docs/superpowers/plans/2026-07-07-wp14-legacy-cleanup-wave.md
Branch: worktree-wp14-legacy-cleanup
Base (merge-base main): c4fcf58d (Wave 5 complete)

## Locked decisions
- D23-N13 sequence: migrate consumers BEFORE delete routes (Tasks 1–6 → Task 9 gate → Tasks 10–11)
- Task 2 audit widgets: **default DROP** (`AUDIT_LEGACY_WIDGETS=false`); rebuild only on product flag
- Task 13 `users.role` drop: **conditional** on WP-10 RM-3 verification — defer if gate fails
- `useNotifications` already V1 — legacy `NotificationController` purge only (Task 10)
- `resetBankAdminPassword` in `useBanks` unused — remove during Task 4 migration
- R9 envelope: adopt on rewritten endpoints (Task 7); never bundle with functional fixes
- Demo routes: absent from production (`APP_ENV` whitelist local + staging + testing), not disabled handler

## Prerequisites (must be true before Task 10)
- [x] Tasks 1–6 complete (all live consumers on V1)
- [x] Task 9 grep gate green (`scripts/verify-no-legacy-api-consumers.sh`)
- [x] WP-11/WP-12/WP-13 merged to main (Wave 5 complete)

## Tasks
| Task | Description | Status | Commit |
| --- | --- | --- | --- |
| 1 | V1 report presets | complete | 9be2e98f |
| 2 | Audit widgets optional drop | complete | (default off) |
| 3 | Migrate useUsers | complete | ed5242ad |
| 4 | Migrate useBanks | complete | f19c5e62 |
| 5 | Migrate useAudit + audit.vue | complete | a4a9ca28 |
| 6 | Migrate useReports presets | complete | ed1851b2 |
| 7 | API envelope tolerance | complete | d460c113 |
| 8 | Demo route production gate | complete | e620e922 |
| 9 | Zero-legacy grep gate | complete | 000a300e |
| 10 | Purge legacy route batch 1 | complete | 77105d43 |
| 11 | Dead module + designer guard purge | complete | a14a7ba1 |
| 12 | Stale reference sweep | complete | 03bacb22 |
| 13 | Conditional users.role drop | **deferred** | — |
| 14 | Regression + release notes | complete | (this commit) |

## Task 13 deferral (WP-10 RM-3 gate failed)

Pre-flight grep on 2026-07-07 found live `users.role` / `->role` readers outside `User::legacyRole()` fallback:

- `Bank::bankAdmin()` — `where('role', UserRole::BANK_ADMIN)`
- `User` model — `role` in `$fillable` / casts; `legacyRole()` reads `getAttributes()['role']`
- `StoreUserRequest` / `UpdateUserRequest` — validate and persist `role`
- `V1\UserController` — `LegacyRoleMapper::toLegacyValue()` on create/update responses
- Multiple API resources — `legacyRole()` exposure for transitional clients

**Action:** No `drop_users_role_column` migration shipped. Revisit after WP-10 RM-3 completes.

## Task 14 gate results (2026-07-07)

| Gate | Result |
| --- | --- |
| `scripts/verify-no-legacy-api-consumers.sh` | PASS |
| `LegacyRouteAbsentTest` | PASS (5 tests) |
| `ReportPresetTest` | PASS (3 tests) |
| `DemoRouteEnvironmentGateTest` | PASS (4 tests) |
| Focused frontend composables + utils | PASS (66 tests) |

## Session notes
- Worktree at `.claude/worktrees/wp14-legacy-cleanup` on branch `worktree-wp14-legacy-cleanup`
- V1 routes confirmed: `/api/v1/users`, `/api/v1/banks`, `/api/v1/audit-logs`, `/api/v1/notifications`, `/api/v1/report-presets`
- Legacy routes removed: `/api/users`, `/api/banks`, `/api/audit*`, `/api/report-presets`, duplicate `/api/notifications`
- `engine_claim:` cache mirror absent (WP-5) — verified in Task 11
- Placebo settings UI: no stragglers found (WP-11 complete)
- `committee_director`: only `RoleCodes::COMMITTEE_DIRECTOR` constant remains (WP-10 RM-5 clean)
- Notification deep links: `/workflows/instances/{id}` (backend dispatcher + frontend navigation util)
- **Merge readiness:** branch is feature-complete for WP-14 scope; Task 13 deferral documented; dashboard `/requests` list links remain a follow-up (not blocking API cleanup)
