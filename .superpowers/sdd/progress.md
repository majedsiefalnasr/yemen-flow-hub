# WP-14 Legacy Cleanup Wave — Progress Ledger

Plan: docs/superpowers/plans/2026-07-07-wp14-legacy-cleanup-wave.md
Branch: worktree-wp14-legacy-cleanup
Base (merge-base main): TBD on first task

## Locked decisions
- D23-N13 sequence: migrate consumers BEFORE delete routes (Tasks 1–6 → Task 9 gate → Tasks 10–11)
- Task 2 audit widgets: **default DROP** (`AUDIT_LEGACY_WIDGETS=false`); rebuild only on product flag
- Task 13 `users.role` drop: **conditional** on WP-10 RM-3 verification — defer if gate fails
- `useNotifications` already V1 — legacy `NotificationController` purge only (Task 10)
- `resetBankAdminPassword` in `useBanks` unused — remove during Task 4 migration
- R9 envelope: adopt on rewritten endpoints (Task 7); never bundle with functional fixes
- Demo routes: absent from production (`APP_ENV` whitelist local + staging), not disabled handler

## Prerequisites (must be true before Task 10)
- [ ] Tasks 1–6 complete (all live consumers on V1)
- [ ] Task 9 grep gate green (`scripts/verify-no-legacy-api-consumers.sh`)
- [ ] WP-11/WP-12/WP-13 merged to main (Wave 5 complete ✅)

## Tasks
Task 1 (V1 report presets): pending
Task 2 (audit widgets optional drop): pending
Task 3 (migrate useUsers): pending
Task 4 (migrate useBanks): pending
Task 5 (migrate useAudit + audit.vue): complete — see task-5-report.md
Task 6 (migrate useReports presets): pending
Task 7 (API envelope tolerance): pending
Task 8 (demo route production gate): pending
Task 9 (zero-legacy grep gate): pending
Task 10 (purge legacy route batch 1): pending — blocked on Task 9
Task 11 (dead module + designer guard purge): pending
Task 12 (stale reference sweep): pending
Task 13 (conditional users.role drop): pending — gated on WP-10 RM-3
Task 14 (full regression + release notes): pending

## Session notes
- Worktree at `.claude/worktrees/wp14-legacy-cleanup` on branch `worktree-wp14-legacy-cleanup`
- V1 routes confirmed: `/api/v1/users`, `/api/v1/banks`, `/api/v1/audit-logs`, `/api/v1/notifications`
- Legacy still registered: `/api/users`, `/api/banks`, `/api/audit*`, `/api/report-presets`, duplicate `/api/notifications`
- `engine_claim:` cache mirror already absent (WP-5) — verify grep in Task 11
- Known-red baseline on full suites — report, do not chase unrelated reds in Tasks 1–13
