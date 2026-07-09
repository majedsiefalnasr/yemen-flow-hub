# QUEUE-002 — Explicit tries/backoff/timeout on notification + export jobs

## What changed

`backend/app/Jobs/DispatchNotification.php` and `backend/app/Jobs/GenerateReportExport.php` gained explicit `$tries`, `$timeout`, and `backoff()` — mirroring the pattern already applied to `ScanEngineRequestDocument` for QUEUE-001. Both jobs already had `failed()`; only the retry/timeout config was missing, per the finding.

| Job | tries | timeout | backoff | Rationale |
| --- | --- | --- | --- | --- |
| `DispatchNotification` | 3 | 30s | `[5, 15, 30]` | Bounded recipient-list fan-out; DB insert only, should never legitimately run long. |
| `GenerateReportExport` | 3 | 300s | `[15, 60, 180]` | Up to `ROW_LIMIT` (10,000) rows with `bank`/`currentStage`/`merchant` relations, CSV build in PHP, disk write — real I/O, generous timeout per the finding's explicit call-out ("generous `$timeout` for exports"). |

Neither job's retry semantics changed in a way that affects correctness: `DispatchNotification` already used `insertOrIgnore` for the recipient rows (idempotent under retry); `GenerateReportExport`'s `failed()` already flips a stuck `PROCESSING` export to `FAILED` so a retry exhaustion can't leave it stuck.

## Why these values, not connection defaults

Both connections (`redis`, `database`) set `retry_after` to 90s (`config/queue.php`). Without an explicit per-job `$timeout`, a job is presumed dead and re-attempted after 90s even if it is still legitimately running — for `GenerateReportExport` specifically, a 10k-row CSV build well within normal operation could exceed 90s under load, causing a duplicate concurrent attempt at the same export. An explicit `$timeout` (300s, well under `retry_after`... actually greater) requires bumping worker-level `retry_after` awareness — see Residual below.

## Test evidence

`backend/tests/Feature/Jobs/QueueJobResilienceConfigTest.php` (2 tests, written first as red, then implementation made green):

```
Tests: 2, Assertions: 6
 - dispatch notification has explicit resilience config (tries=3, timeout=30s, backoff=[5,15,30])
 - generate report export has explicit resilience config (tries=3, timeout=300s, backoff=[15,60,180])
```

## Regression check

`tests/Feature/Notification/NotificationEventTest.php` (7), `tests/Feature/Report/ReportExportTest.php` (9), `tests/Feature/Report/ReportExportTruncationTest.php` (2), `tests/Feature/Engine/EngineNotificationTest.php` (10) — 28 tests, all green, no behavior change to job execution, only added resilience metadata.

## Residual

`GenerateReportExport`'s `$timeout` (300s) exceeds the `redis`/`database` connection `retry_after` (90s, `config/queue.php`). Per Laravel's queue worker semantics, `retry_after` should be set higher than the longest job `$timeout` on that connection, or a long job can be picked up again by another worker before the first attempt actually finishes. This finding's scope was the per-job resilience config specifically (QUEUE-002); the connection-level `retry_after` tuning is really QUEUE-003 territory (queue separation — a dedicated `exports` queue/connection could carry its own longer `retry_after` without affecting the shared `default`/`emails` connections). Flagged here so it isn't lost, not silently fixed as a scope-creep edit to `config/queue.php` in this pass.
