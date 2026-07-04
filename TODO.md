# TODO

## Audit log — LOGIN/LOGOUT exclusion (no reseed needed)

`AuditLogController::index()` (`backend/app/Http/Controllers/Api/V1/AuditLogController.php`) now excludes `LOGIN`/`LOGOUT` actions by default from the "سجل النشاط" table on `/audit`. Fix is query-level (`whereNotIn`), applies live to all existing and future rows — confirmed no seeder or factory writes `LOGIN`/`LOGOUT` rows, so no reseed is required.

Optional follow-up, not urgent:

- [ ] Decide retention policy for `LOGIN`/`LOGOUT` rows in `audit_logs` (they're still written on every login/logout, just hidden from this one table). If the table grows large, consider archiving/purging old `LOGIN`/`LOGOUT` rows via a scheduled job instead of keeping them indefinitely.
