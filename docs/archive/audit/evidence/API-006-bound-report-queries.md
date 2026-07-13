# API-006 — Bound/SQL-ify unbounded report queries

## Scope clarified vs. the original finding

The finding named three endpoints (`sla`, `stage-duration`, `team-performance`) as having unbounded `->get()` calls. On inspection, only `sla()` genuinely materialized per-row data into PHP for derivation (`groupBy()`/`filter()` on the full request set). `stage-duration` and `team-performance` were already SQL-aggregated (`GROUP BY`/`COUNT`/`AVG`) — their *result set* is bounded by the number of distinct groups (stage codes, role names), not by row count; only the *underlying scan* was unbounded (no date-window default). Both problems were fixed:

1. **`sla()` — SQL-ified.** Bucketing (breached/nearing/ok) now happens in one grouped query instead of per-row PHP derivation.
2. **All three — bounded scan.** Default 90-day `created_at` window when no `from`/`to` filter is given, with an explicit `?all=true` escape hatch.

## Why the escape hatch, not a silent default

This is a Central Bank regulatory platform — a silently truncated default window on a compliance/audit report view is a correctness risk, not just a UX nuisance (confirmed with the user before implementing: a silent 90-day default without an override was rejected). `?all=true` lets a caller explicitly opt into full history.

## sla() SQL-ification

Replaced `EngineRequest::query()->withStageEntry()->get()` + PHP `groupBy()`/`filter()` with one grouped query using `SUM(CASE WHEN ... THEN 1 ELSE 0 END)` per bucket, reusing the exact deadline formula already shipped in `EngineRequestListQuery::applySlaStatusFilterInternal()` (breached: past deadline; nearing: within the final 20% of the SLA window, min 1 minute, before the deadline; ok: otherwise) — same source of truth, not a re-derivation.

## A real bug found and fixed along the way: `phpunit.xml` didn't pin `APP_TIMEZONE`

Writing the SLA parity test surfaced a genuine pre-existing test-determinism gap, unrelated to my SQL rewrite:

- `phpunit.xml`'s `<php>` block overrides `DB_CONNECTION`, `CACHE_STORE`, `QUEUE_CONNECTION`, etc. for the test environment, but never pinned `APP_TIMEZONE` — tests inherited whatever value was in the developer's local `.env` (`Asia/Aden`, UTC+3, in this environment).
- Under SQLite (the test DB driver), `strftime('%s','now')` always returns true UTC, with no session timezone awareness — but `stage_entered_at` gets stored as Aden-local wall-clock (Laravel's `now()`/datetime casts use `config('app.timezone')` with no UTC conversion on write).
- Result: `nowEpochSql()` (`strftime('now')`, true UTC) and `slaDeadlineEpochSql()` (derived from an Aden-local-but-treated-as-UTC stored value) disagreed by exactly the local/UTC offset (3 hours in this environment) — enough to flip breached/nearing/ok classification.
- **Confirmed this is SQLite/test-only, not a production bug**: `docker exec yfh-mysql mysql ... SELECT UNIX_TIMESTAMP('...'), UNIX_TIMESTAMP()` shows MySQL's `time_zone=SYSTEM` applies consistently to both the naive stored string and "now," so production (MySQL) stays self-consistent regardless of `APP_TIMEZONE`.
- **Why the existing `SlaProjectionParityTest` didn't catch it**: its assertions check relative ordering and set membership (breached < nearing < ok priority order; null-SLA never included), both of which are invariant under a *constant* skew applied to all rows — the skew doesn't cancel in absolute breach/nearing/ok classification (which my new `sla()` report exposes numerically), but does cancel in a same-direction ordering comparison.

**Fix**: pinned `<env name="APP_TIMEZONE" value="UTC"/>` in `phpunit.xml`. Full backend suite (1243 tests) reruns clean after the change — confirms no other test silently depended on the Aden-local skew.

## Test evidence

`backend/tests/Feature/Report/SlaReportTest.php` (5 tests, new — no prior SLA report coverage existed):
- Buckets requests correctly across breached/nearing/ok at exact SLA-window boundaries (100-minute SLA, deadline ± minutes)
- Default 90-day window excludes an older row
- `?all=true` includes it
- Excludes stages with no SLA configured
- Respects bank scope

`backend/tests/Feature/Report/V1ReportsTest.php` — added `test_stage_duration_defaults_to_a_ninety_day_window` and `test_team_performance_defaults_to_a_ninety_day_window` (2 new tests).

```
SlaReportTest: 5 tests, 15 assertions
V1ReportsTest: 12 tests (2 new), 37 assertions
Full backend suite: 1243 tests, exit 0
```

## Regression check

Full suite run (1243 tests) after the `phpunit.xml` timezone pin — all green, including `SlaProjectionParityTest`, `EngineRequestTest`, `EngineRequestStatsTest`, and every other date/time-sensitive test in the codebase.

## Residual

`summary()`, `requestsOverTime()`, `byWorkflowStage()`, `byBank()`, `byCurrency()` (the shared `applyFilters()` callers not named in the finding) deliberately did NOT get the default window — they're dashboard-style widgets likely expected to show all-time totals by design, and adding a silent default there would be an unrequested behavior change beyond this finding's scope.
