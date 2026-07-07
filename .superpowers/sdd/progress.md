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
Task 6 (documents purge-orphans + archive-superseded): pending
Task 7 (audit:archive-old): pending
Task 8 (failed-job visibility + AdminHealth): pending
Task 9 (operations runbook): pending
Task 10 (gate + final review): pending

## Session notes
- Worktree at .claude/worktrees/wp13-retention-ops on branch worktree-wp13-retention-ops
- Starting scheduler: only `workflow:expire-engine-claims` (everyMinute) + `workflow:notify-sla-signals` (hourly)
- Known-red baseline: do not chase unrelated test failures on full suite
