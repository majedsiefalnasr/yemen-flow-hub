# TZ-001 (new finding, NOT fixed this session) — MySQL TIMESTAMP columns silently store wall-clock 3 hours off from the true instant

## Severity: High. Status: Open, investigated, reverted, not fixed.

## Discovery context

Found while verifying the DB-001/DB-002 `sla_deadline_epoch` migration's backfill against the real dev MySQL DB. A backfilled value didn't match a manually-computed expectation — chasing that discrepancy surfaced this bug. Not introduced by this session's work; pre-existing on `main`, on every `TIMESTAMP` column, in production, today.

## The bug

- `config('app.timezone')` (`.env`: `APP_TIMEZONE=Asia/Aden`, UTC+3) is what `now()` (Carbon) uses app-wide — every `stage_entered_at`, `created_at`, `updated_at`, etc. is written as Aden-local wall-clock.
- `backend/config/database.php`'s `mysql` connection has no `'timezone'` key set, so the PDO session's `time_zone` defaults to MySQL's `SYSTEM` variable — confirmed on the dev container: `SYSTEM` → `UTC`.
- Every `engine_requests`/`workflow_history`/etc. timestamp column that uses SQL `TIMESTAMP` type (not `DATETIME`) is stored internally as true UTC and converted to/from the **session** timezone on every read and write — this is standard, correct MySQL `TIMESTAMP` semantics, but it means: **the app writes a string like `2026-07-09 11:04:39` intending "11:04:39 Aden time", but MySQL — whose session is UTC — stores it as if `11:04:39` were UTC.** The true UTC instant that actually gets persisted is 3 hours later than the one the app meant (Aden is UTC+3, so `11:04:39` Aden = `08:04:39` UTC, but the column ends up holding the equivalent of `11:04:39 UTC`).

Verified precisely on the real dev DB with a live row (`engine_requests.id=57`):

```sql
SET time_zone = '+00:00'; SELECT stage_entered_at FROM engine_requests WHERE id=57;  -- 2026-07-09 11:04:39
SET time_zone = 'Asia/Aden'; SELECT stage_entered_at FROM engine_requests WHERE id=57;  -- 2026-07-09 14:04:39
```

Same underlying stored instant, read back 3 hours apart depending on session timezone — direct proof of the stored-value skew, not just a read-time display artifact.

## Blast radius

- Every `TIMESTAMP` column written via `now()`/Carbon is affected — not limited to SLA-related columns. `stage_entered_at`, `created_at`, `updated_at`, `claimed_at`, `claim_expires_at`, audit log timestamps, etc.
- This is the same *class* of bug as the timezone issue found and fixed in API-006 earlier this remediation pass (`phpunit.xml` lacked `APP_TIMEZONE=UTC`, causing SQLite-vs-`Asia/Aden` SLA classification drift in tests) — but that fix only pinned the **test suite's** timezone to make tests self-consistent with SQLite's UTC-only epoch functions. It did not address (and could not have caught) this separate, production-only MySQL `TIMESTAMP` session-timezone gap, since the test suite runs on SQLite, which has no session-timezone concept at all.
- Practical effect on SLA logic specifically: `EngineRequest::nowEpochSql()` (`UNIX_TIMESTAMP()`, no argument) is unaffected — it always returns the true current UTC epoch regardless of session timezone. But `UNIX_TIMESTAMP(stored_column)` (used throughout `stageEnteredAtSql()`/`slaDeadlineEpochSql()`'s fallback expressions) is affected — it reads a stored-as-3-hours-late `TIMESTAMP` value, so **computed SLA deadlines are silently 3 hours later than intended, meaning breach detection under-fires by up to 3 hours** on real production MySQL today.

## Investigation performed this session (not a fix)

1. Attempted the standard Laravel fix: added `'timezone' => env('APP_TIMEZONE', 'UTC')` to `config/database.php`'s `mysql` connection (Laravel's `MySqlConnector` issues `SET time_zone='...'` on every new PDO connection when this key is present — confirmed via `vendor/laravel/framework/.../MySqlConnector.php:110-111`).
2. Verified via `php artisan tinker` that this correctly changed the session timezone (`SELECT @@session.time_zone` → `Asia/Aden`) and made `UNIX_TIMESTAMP(stored_string)` agree with PHP-side Carbon computation of the same string.
3. **Discovered the config change also altered how existing `TIMESTAMP` columns display** (the same row's `stage_entered_at` read as `11:04:39` before the change and `14:04:39` after) — because `TIMESTAMP` columns convert between session timezone and stored UTC on every read, not just at query-time SQL functions. This proved that **all historical data already has the 3-hour skew baked into its stored UTC instant**, not just misread by session-timezone-blind SQL functions.
4. **Reverted the config change immediately** rather than proceeding, since fixing forward (correcting the session timezone) without also correcting historical data would just move the direction of the discrepancy, and correcting historical data requires its own careful, dedicated investigation (see below) — not something to do inline while working on an unrelated performance gate.

## Why this needs its own dedicated session, not an inline fix

- Full audit needed of every `TIMESTAMP` column across the schema (not just `engine_requests`) to scope true impact.
- A safe backfill strategy for historical data needs design: is a blanket "-3 hours" correction safe, or did some rows get written correctly at some point (e.g., if `APP_TIMEZONE` was ever `UTC` in a prior deployment, or if any write path bypasses Carbon/`now()`)? This needs verification, not assumption.
- The fix touches every table with a `TIMESTAMP` column — audit logs (`audit_logs`, `audit_log_archives`), workflow history (`workflow_history`, `workflow_history_archives` — both touched by this session's own ARCH-006 fix), engine requests, claims, and more. A wrong correction here has compliance/audit-trail implications (this platform's own AGENTS.md calls out audit-sensitivity as a core requirement) — the risk of getting this backfill wrong is high enough to warrant a dedicated, carefully-scoped session with its own plan, migration, and rollback, per this project's own standing practice for every other fix in this remediation pass.
- Needs a decision on whether to convert affected columns from `TIMESTAMP` to `DATETIME` (naive, no session-timezone conversion — arguably more predictable given the app's UTC-unaware write pattern) as part of the fix, versus keeping `TIMESTAMP` and fixing the session-timezone config, which are two different remediation strategies with different migration shapes.

## Current state of the repo

No code or config change from this investigation was kept — `config/database.php` is unchanged from before this session. This finding is purely a discovery to hand off.

## Recommendation

Treat as a new, separately-tracked finding (suggested ID `TZ-001`) requiring its own dedicated session: scope the full column inventory, decide the correction strategy (session-timezone config fix + historical backfill, vs. column-type change), write the migration with up/down and a verified backfill, and re-verify SLA breach classification against real data before/after. Given the audit-sensitivity of this platform, treat this as at least as high-priority as the Pre-production-tier findings already fixed this pass.
