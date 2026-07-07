# TODO

## Audit log — LOGIN/LOGOUT exclusion (no reseed needed)

`AuditLogController::index()` (`backend/app/Http/Controllers/Api/V1/AuditLogController.php`) now excludes `LOGIN`/`LOGOUT` actions by default from the "سجل النشاط" table on `/audit`. Fix is query-level (`whereNotIn`), applies live to all existing and future rows — confirmed no seeder or factory writes `LOGIN`/`LOGOUT` rows, so no reseed is required.

Optional follow-up, not urgent:

- [ ] Decide retention policy for `LOGIN`/`LOGOUT` rows in `audit_logs` (they're still written on every login/logout, just hidden from this one table). If the table grows large, consider archiving/purging old `LOGIN`/`LOGOUT` rows via a scheduled job instead of keeping them indefinitely.

## Screen permissions matrix — pending capability-collapse migration

`backend/database/migrations/2026_07_05_000001_collapse_screen_permission_capabilities.php` was pending (un-run) on the local dev DB, so `screen_permissions` still had legacy `CREATE`/`UPDATE`/`DELETE` capability rows from before the app collapsed its capability model to `VIEW`/`MANAGE`/`EXPORT`. This made the `/admin/screen-permissions` matrix API return `manual` grants that didn't match what the frontend's toggle columns (`VIEW`/`MANAGE`/`EXPORT` only) could render — looked like "togglers don't reflect the DB."

- [ ] Run `php artisan migrate` (or targeted `--path=database/migrations/2026_07_05_000001_collapse_screen_permission_capabilities.php`) on every other environment (staging/prod/other local checkouts) that hasn't picked up this migration yet. It's transactional and forward-only (no rollback by design — see migration's `down()` comment); safe to re-run, no-op if already applied.
- [ ] After running, spot-check `SELECT DISTINCT capability FROM screen_permissions;` returns only `VIEW`/`MANAGE`/`EXPORT`.
