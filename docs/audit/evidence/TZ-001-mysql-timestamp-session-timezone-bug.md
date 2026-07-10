# TZ-001 — MySQL TIMESTAMP columns stored the wrong true UTC instant

## Status: Fixed (`fix/tz-001-mysql-timestamp-timezone`)

## The bug

- `config('app.timezone')` (`.env`: `APP_TIMEZONE=Asia/Aden`, UTC+3) is what `now()` (Carbon) uses app-wide.
- `backend/config/database.php`'s `mysql` connection had no `'timezone'` key, so the PDO session's `time_zone` defaulted to MySQL's `SYSTEM` variable — confirmed on the dev container: `SYSTEM` → `UTC`.
- Eloquent's `save()`/`create()` path formats a Carbon instant to a naive wall-clock string (`fromDateTime()` → `format('Y-m-d H:i:s')`, no timezone marker) before binding it as a query parameter. MySQL's `TIMESTAMP` type stores that naive string as if it were in the session timezone (UTC) and internally represents it as UTC. Since the string actually represented Aden-local wall-clock, not UTC, the column's true stored UTC instant ended up **3 hours later** than the real-world moment the write was meant to capture.

Verified precisely via a live round trip on the real dev DB (`yfh-mysql`), writing through the actual Eloquent code path (not a raw query, which behaves differently — see Correction notes below):

```
Wrote now(): 2026-07-10 14:53:xx (Asia/Aden local) — true UTC instant: 2026-07-10 11:53:xx
Raw value MySQL stored (old, UTC session): 2026-07-10 14:53:xx
UNIX_TIMESTAMP(stored column), old session: 3h ahead of the true UTC epoch
```

## Blast radius

Every `TIMESTAMP` column in the schema — confirmed there are **zero** `DATETIME` columns to exclude. Full inventory captured via `information_schema.COLUMNS` (`DATA_TYPE = 'timestamp'`): **109 columns across 40 tables**, everything from `engine_requests.stage_entered_at` to `users.created_at` to `audit_logs.created_at`.

`git log -S "APP_TIMEZONE"` shows `Asia/Aden` was set in the very first commit and never changed — the skew has been uniform since day one, which made a single, predictable correction safe (no mixed-history risk to reason about).

## What was NOT wrong (corrected understanding — this was the session's key investigation finding)

The first pass of this investigation over-stated the impact. After building a proper verification tool (`tz:verify`, see below) and testing precisely:

- **Relative epoch math done entirely in one SQL session was never actually broken.** `EngineRequest::nowEpochSql()` (`UNIX_TIMESTAMP()`, no argument) and `epochSql(stage_entered_at)` (`UNIX_TIMESTAMP(column)`) are both computed under the *same* session in every real query — SLA breach/nearing/ok classification only ever compares two same-session epochs. Verified directly on the real dev DB: `UNIX_TIMESTAMP(stage_entered_at)`, `UNIX_TIMESTAMP()`, and their difference were **identical** whether the session was `SYSTEM` or `Asia/Aden`. The skew cancels out in any same-session relative comparison. **SLA breach detection was never wrong.**
- The bug only manifests when comparing a MySQL-session-derived epoch against an independently-computed one (e.g. PHP/Carbon's own `now()->getTimestamp()`, as the `tz:verify` tool does deliberately to surface the drift) — nothing in the shipped application code does this comparison today.
- The real, concrete risk was narrower: **display/export correctness**, and specifically a **one-time visible discontinuity on deploy**. Once the session-timezone config fix lands, `TIMESTAMP`→session-timezone conversion on read becomes correct for the first time — which means, without a paired backfill, every existing row would suddenly *display* 3 hours later than users have always seen it (because the stored instant itself is still off by the same 3 hours, and the read-side conversion is no longer silently canceling that out with a mismatched session).

## Fix

Two changes shipped together in the same migration/deploy, exactly to avoid a window where one is live without the other:

### 1. `backend/config/database.php` — session timezone

```php
'timezone' => config('app.timezone'),
```

Laravel's `MySqlConnector` issues `SET time_zone='...'` on every new PDO connection when this key is present (confirmed via `vendor/laravel/framework/.../Connectors/MySqlConnector.php:110-111`). Makes all future writes/reads self-consistent and correct — verified with `php artisan tz:verify`: 0s drift after the fix, versus 10800s (3h) before.

### 2. `database/migrations/2026_07_10_100002_correct_historical_timestamp_timezone_skew.php` — historical backfill

Shifts every affected `TIMESTAMP` column's stored instant back by `config('app.timezone')`'s UTC offset (`Carbon::now(config('app.timezone'))->getOffset()`, not a hardcoded "3 hours", so this generalizes if the app timezone ever changes). Chunked by primary key range (5000/batch) per table, same pattern as every other backfill migration in this remediation pass. All 44 affected tables confirmed to use a plain `id` primary key (no composite keys) before writing the loop.

Verified the correction arithmetic is session-independent: the same `+ INTERVAL n SECOND` shift on the real dev DB produced the same corrected true-UTC value whether run under the `Asia/Aden` or the `+00:00` session (confirmed by direct `SET time_zone` + read-back comparison).

**Net effect verified end-to-end**: after both changes, a historical row's *displayed* value under the Aden session is byte-for-byte identical to what it displayed before either change — no visible jump for users — while the same row's *true stored UTC instant*, read under a forced UTC session, is now correctly 3 hours earlier than it was pre-fix (matching the real-world moment the original write intended).

## Why a dedicated artisan command for verification, not a PHPUnit test

`phpunit.xml` forces `DB_CONNECTION=sqlite`, which has no session-timezone concept at all — this class of bug is structurally invisible to the existing test suite. `php artisan tz:verify` (`app/Console/Commands/TzVerifyCommand.php`) refuses to run on sqlite and performs a live write/read-back/epoch-comparison round trip against whatever `mysql` connection it's pointed at — usable as a standing verification tool against staging/production after this deploys, not just a one-off proof for this fix.

## Test evidence

```
php artisan tz:verify — before the config fix: FAIL, 10800s drift
php artisan tz:verify — after the config fix: PASS, 0s drift
```

Migration verified against the real dev DB: applied, spot-checked 5 rows across `engine_requests`, `audit_logs`, `users` (each shifted exactly -3h under a forced-UTC read), rolled back (values returned to pre-migration state), re-applied (values shifted again) — clean round trip.

Full backend suite (`php artisan test`, no path filter — run in full given this touches every `TIMESTAMP` column in the schema): **1282 tests, 4524 assertions, zero failures**. Runs on sqlite, so does not exercise MySQL session-timezone behavior directly, but confirms no structural regression from the `config/database.php` change or the migration's presence.

## `DashboardStatsService`'s pre-existing workaround — investigated, left as-is

`bankMonthlyRequests()`/`cbyadminMonthlyRequests()` carry a comment: *"Group in app layer using UTC to avoid DB/session timezone drift at month boundaries."* This code fetches raw `created_at` values and groups them in PHP via `CarbonImmutable::parse((string) $createdAt)->setTimezone($timezone)`.

`CarbonImmutable::parse()` with no explicit source timezone assumes PHP's process-default timezone (`UTC`, confirmed via `date_default_timezone_get()`). Before this fix, the stored `created_at` string was naive Aden-local wall-clock mis-stored as if UTC — so this code's `parse()` step correctly matched what was actually stored (both "UTC" by the same mistake), then `setTimezone($timezone)` shifted it to genuinely-wrong Aden-local for month-key grouping. This method's stated intent ("avoid drift") was not actually achieved pre-fix.

After this fix, `created_at` is genuine true-UTC, so `parse()`'s UTC assumption is now correct, and `setTimezone()` correctly converts to true Aden-local. **This code is now correct for the first time**, not merely redundant — left unchanged, since changing it further is out of this finding's scope and the logic itself needs no correction anymore.

## Residual / out of scope

- `SystemSettingsService`'s admin "Time Zone" setting (`settings.general.timeZone`, shown in the CBY Admin UI) is purely decorative — saved/read as opaque JSON, never wired to `config('app.timezone')` or any runtime timezone behavior. Changing it in the UI does nothing. Not fixed here (a UI-truthfulness issue, not a data-correctness one); flagged for a future pass if the platform wants that setting to actually do something.
- Did not convert any column from `TIMESTAMP` to `DATETIME` — the session-timezone-config approach was sufficient and lower-risk (no column-type migration, no risk of losing the automatic UTC-storage guarantee `TIMESTAMP` provides going forward).
