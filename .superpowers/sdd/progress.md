# WP-13 Retention + Operations — Progress Ledger

Plan: docs/superpowers/plans/2026-07-07-wp13-retention-and-operations.md
Branch: worktree-wp13-retention-ops
Base (merge-base main): TBD on first task

## Locked decisions
- Archive-first defaults; CBY policy deferral for hard audit deletes
- Audit hot horizon draft: 12 months (config `retention.audit_hot_months`)
- Export file retention: 30 days; row kept as `EXPIRED`
- WP-12 owns FAILED export UX; WP-13 owns `EXPIRED` status + purge + `EXPORT_EXPIRED` 422
- Notifications never audit trail; purge per state-based policy
- Idempotent jobs + failure-visible (scheduler heartbeat + ops log keys)

## Tasks
Task 1: complete (97788a25 + fix c988d1c4)
Task 2: complete (057ce1c9)
Task 3 (wire claim + SLA commands): complete (9715c1b0)
Task 4 (notifications:purge-old): complete (f147067c)
Task 5 (reports:purge-old-exports + EXPIRED): complete (2472703a)
Task 6 (documents purge-orphans + archive-superseded): complete (871eb1cd)
Task 7 (audit:archive-old): complete (c0604f89)
Task 8 (failed-job visibility + AdminHealth): complete (81ac6dbe)
Task 9 (operations runbook): complete (ae117a28)
Task 10 (gate + final review): complete

## Gate results (Task 10)
- `php artisan test tests/Feature/Operations tests/Unit/Operations/RetentionConfigTest.php tests/Feature/Report/ReportExportTest.php` — **PASS** (38 tests, 107 assertions; PDO deprecation notices only)
- `vendor/bin/pint --test` on WP-13 PHP paths — **PASS**
- `pnpm exec eslint app/pages/admin/health.vue` — **PASS**

## Merge readiness
- **All WP-13 tasks (1–10) complete**
- Focused gate green; no WP-13 regressions found
- **Not merged to main** — awaiting review
- CBY policy deferrals documented in retention-policy.md (audit horizon, DB immutability)
- Escalation contacts in runbook.md are placeholders — confirm before production

## Session notes
- Worktree at .claude/worktrees/wp13-retention-ops on branch worktree-wp13-retention-ops
- Starting scheduler: only `workflow:expire-engine-claims` (everyMinute) + `workflow:notify-sla-signals` (hourly)
- Known-red baseline: do not chase unrelated test failures on full suite
