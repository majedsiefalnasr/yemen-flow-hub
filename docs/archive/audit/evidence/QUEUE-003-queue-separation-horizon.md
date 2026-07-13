# QUEUE-003 — Queue separation + Horizon

## Queue separation

Following the exact pattern already shipped for `SendEmailDelivery`'s `emails` queue (`$this->onConnection()`/`$this->onQueue()` inside the job constructor):

| Job | Queue | Connection |
| --- | --- | --- |
| `DispatchNotification` | `notifications` | `redis` (shared) |
| `ScanEngineRequestDocument` | `scans` | `redis` (shared) |
| `GenerateReportExport` | `exports` | `exports` (dedicated) |
| `GenerateAuditLogExport` | `exports` | `exports` (dedicated) |
| `SendEmailDelivery` | `emails` | `emails` (unchanged) |

The two export jobs get a **dedicated connection**, not just a dedicated queue on the shared `redis` connection — this closes the QUEUE-002 residual noted earlier: `GenerateReportExport`/`GenerateAuditLogExport` carry `$timeout=300`, which exceeds the shared `redis` connection's `retry_after=90`. Laravel expects `retry_after` to exceed the longest job `$timeout` on a connection, or a still-running job can be picked up twice by another worker. The new `exports` connection sets `retry_after=360` (env-overridable via `REDIS_EXPORTS_QUEUE_RETRY_AFTER`), which now correctly exceeds both jobs' 300s timeout.

## Horizon

Installed `laravel/horizon` (`^5.47`) — confirmed via `git diff composer.lock` that only `laravel/horizon` and its own dependency `laravel/sentinel` (official `laravel/*` GitHub org, MIT license) were added; no existing package versions changed. `composer audit`'s 19 reported advisories are pre-existing `symfony/*` transitive-dependency findings unrelated to this install.

`php artisan horizon:install` published `app/Providers/HorizonServiceProvider.php` and `config/horizon.php`, and registered the provider in `bootstrap/providers.php`.

### Auth gate (Security gate requirement)

The default Horizon scaffold's `viewHorizon` gate denies everyone (`in_array($user->email, [])`, empty allowlist) — a real deployment would need to remember to fill this in, an easy step to miss. Wired it to `$user->isSystemAdmin()` instead, matching the same check already used to gate audit-log/report system-wide visibility elsewhere in this app (`AuditLogController`, `ReportController`). Confirmed the parent `HorizonApplicationServiceProvider::boot()` (called via `parent::boot()`) correctly wires `Horizon::auth()` to consult this gate — Horizon's own `Horizon::check()` defaults to `app()->environment('local')` only if `Horizon::auth()` is never called, which isn't the case here.

`local` environment still bypasses the gate unconditionally (Horizon's own `|| app()->environment('local')` in the parent provider) — standard Laravel/Horizon convention, consistent with how this app already treats `local` specially elsewhere (e.g. `AppServiceProvider`'s production-only mail-server check).

### Supervisor configuration

Horizon's default scaffold only watches the `default` queue on the `redis` connection. Since Horizon supervisors are scoped per-connection, added `supervisor-exports` (watches the new `exports` connection) and `supervisor-emails` (watches the existing `emails` connection) alongside the updated `supervisor-1` (now watches `default`, `notifications`, `scans` on `redis`). `supervisor-exports`'s worker `timeout` (330s) exceeds the export jobs' own `$timeout` (300s) so Horizon doesn't kill a legitimately-running export process before the job's own timeout has a chance to fire.

## Test evidence

`backend/tests/Feature/Jobs/QueueSeparationTest.php` (5 tests): each of the four newly-separated jobs lands on its expected queue/connection; `SendEmailDelivery` (pre-existing) still uses `emails` — regression guard proving QUEUE-003 didn't disturb it.

`backend/tests/Feature/HorizonAccessTest.php` (3 tests): a system admin passes the `viewHorizon` gate; a regular bank user fails it; an unauthenticated request fails it.

```
QueueSeparationTest: 5 tests, 8 assertions
HorizonAccessTest: 3 tests, 3 assertions
```

## Regression check

Full backend suite rerun after both the queue-separation and Horizon changes (broad, security-relevant change per the verification ladder's exception).

## Residual

Worker deployment (`php artisan queue:work --queue=notifications,scans` etc., or `php artisan horizon` with the new supervisors) is an infrastructure/ops concern outside this repo's application code — the config is correct and tested at the unit level (jobs route to the right queue; the gate authorizes correctly), but actually running dedicated workers per queue in production is a deployment-pipeline change, not a code change this task can make.
