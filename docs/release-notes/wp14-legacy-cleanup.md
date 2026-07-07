# WP-14 Legacy Cleanup Wave — Release Notes

**Date:** 2026-07-07  
**Branch:** `worktree-wp14-legacy-cleanup`  
**Scope:** Migrate live consumers to V1/canonical APIs, remove legacy routes and dead modules, align notification deep links with engine workflow instances.

## Summary

WP-14 completes the D23-N13 cleanup sequence: V1 replacements and consumer migration first, grep verification, then legacy route and dead-module removal. Demo auth routes are absent outside `local`/`staging`/`testing`. Report presets are promoted to `/api/v1/report-presets`.

## Removed API routes (404)

| Legacy route family | Replacement |
| --- | --- |
| `GET/POST/PUT /api/users` (+ recovery duplicates) | `/api/v1/users` (+ `reset-password`, `reset-mfa`, `reset-pin`) |
| `GET/POST/PUT /api/banks` | `/api/v1/banks` (+ activate/deactivate/destroy) |
| `GET /api/audit`, `/api/audit/stats`, `/duplicates`, `/risk-indicators` | `/api/v1/audit-logs` (+ export, show); compliance widgets optional via feature flag |
| `GET/POST/DELETE /api/report-presets` | `/api/v1/report-presets` |
| Duplicate `GET /api/notifications` | `/api/v1/notifications` |
| `GET /api/reports/workflow`, `GET /api/reports/voting` | `/api/v1/reports/*` |
| `document-types` legacy resource | Removed (no live consumer) |

## Removed backend modules

- `UserController`, `BankController`, `AuditController`, `NotificationController`, `ReportController`, `ReportPresetsController`, `MerchantController` (legacy), `DocumentTypeController`
- `DocumentType` model (if present)
- Legacy feature tests that only asserted removed routes

## Frontend migrations

| Composable / surface | Now calls |
| --- | --- |
| `useUsers` | `/api/v1/users` |
| `useBanks` | `/api/v1/banks` (removed unused `resetBankAdminPassword`) |
| `useAudit` | `/api/v1/audit-logs` (legacy audit widgets default off) |
| `useReports` presets | `/api/v1/report-presets` |
| `useNotifications` | Already V1 — notification page deep links use `/workflows/instances/{id}` |
| `useDocumentTypes` | Deleted |

## Auth / demo

- Demo switch routes (`demo-users`, `switch-demo-user`, `switch-demo-role`) register only when `APP_ENV` is in `config('demo.allowed_environments')` **and** `APP_DEMO_ROLE_SWITCH=true`.
- Demo switches write `DEMO_USER_SWITCH` audit rows (actor, target, IP, environment).

## API envelope (R9)

Rewritten endpoints (e.g. V1 report presets validation) return the rich error shape `{ error: { code, message, fields }, request_id }`. Frontend `extractApiErrorMessage` / `extractApiErrorCode` tolerate legacy and rich envelopes during transition.

## Designer / dropped-table guards

- `WorkflowDesignerService::stageIsBound` and `FieldDesignerService::fieldIsUsed` query `engine_requests` (not dropped `import_requests`).

## Notification deep links

- `EngineNotificationDispatcher::engineRequestActionUrl()` → `/workflows/instances/{id}`
- Data migration rewrites stored `/requests/{id}` notification URLs where mappable
- Frontend `resolveNotificationTargetUrl()` prefers `action_url`, then maps legacy `request_id` / `engine_request` entity ids

## WP-10 RM-3 — `users.role` column removed (post-WP-14)

- Migration `2026_07_07_000001_drop_users_role_column` drops `users.role` and its index.
- Canonical role source: `user_roles` pivot only (`User::role()` governance `Role` model).
- API `UserRole` enum exposure: `User::asUserRole()` + `UserRoleMapper` (replaces `legacyRole` / `LegacyRoleMapper`).
- Dead form requests removed: `StoreUserRequest`, `UpdateUserRequest` (legacy `/api/users` surface).
- Dashboard / nav deep links: `/requests*` → `/workflows*`.

## Verification (Task 14 gate)

| Check | Result |
| --- | --- |
| `scripts/verify-no-legacy-api-consumers.sh` | PASS |
| `LegacyRouteAbsentTest` | PASS |
| `ReportPresetTest`, `DemoRouteEnvironmentGateTest` | PASS |
| Focused composable tests (`useUsers`, `useBanks`, `useAudit`, `useReports`, `apiErrors`, `notificationNavigation`) | PASS (66 tests) |

## Upgrade notes

1. Deploy backend before frontend so V1 routes exist when UI migrates.
2. Re-run migrations; notification URL rewrite migration applies on deploy.
3. Production: confirm `APP_ENV=production` — demo routes must 404.
4. Operators using saved report presets: no action — storage column unchanged, path prefix only.
