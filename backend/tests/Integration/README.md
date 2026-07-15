# Integration tests

Tests here talk to real external services (currently: a real MySQL server)
rather than the SQLite in-memory connection every other suite uses. They are
**not** part of the default `composer test` / `php artisan test` run.

## Running

```bash
composer test:mysql-integration

# Equivalent, explicit form:
vendor/bin/phpunit -c phpunit.integration.xml
```

Isolation from the default run comes from `phpunit.integration.xml` itself,
not from a group exclusion on `phpunit.xml`: that file (the config both
`composer test` and a bare `php artisan test` use) does not declare
`tests/Integration` as a testsuite at all, so PHPUnit never discovers
anything here under the default config regardless of any `--exclude-group`
flag. `LeaseRenewalMysqlRegressionTest` additionally carries
`#[Group('mysql-integration')]`, which lets `--group=mysql-integration` (or
`--exclude-group=mysql-integration`) target it specifically if this
directory ever gains a test meant to run under the default config too — it
is not what provides isolation for the current test.

## `mysql-integration` group

Requires five environment variables, set explicitly — there is no fallback to
the application's own `DB_HOST`/`DB_USERNAME`/`DB_PASSWORD`:

```bash
LEASE_MYSQL_TEST_HOST=127.0.0.1
LEASE_MYSQL_TEST_PORT=3306
LEASE_MYSQL_TEST_DATABASE=cby_lease_regression_test   # must contain "test"; must not name a real app database
LEASE_MYSQL_TEST_USERNAME=cby
LEASE_MYSQL_TEST_PASSWORD=cby_password
```

The target database must be a dedicated, disposable database — the test
refuses to run against `cby_imports`, `yfh_audit`, or any name that doesn't
contain "test". It never creates or drops that database, and it never
touches a table named `idempotency_keys` or `temporary_uploads` — every
table it creates carries a random per-process prefix (applied through the
connection's own `prefix` config, so `IdempotencyKey`/`TemporaryUpload`
resolve to the prefixed names transparently) and is dropped again in
`tearDown()`, even after an assertion or setup failure.

Because this group is only ever run on deliberate request, a missing
environment variable or an unreachable server **fails** the test — it is
never silently skipped. Reachability is checked by connecting with
`dbname=<LEASE_MYSQL_TEST_DATABASE>` in the PDO DSN, so a server that's up
but doesn't actually have that database (or doesn't grant these credentials
access to it) fails the same way a fully unreachable server does.

## Why this exists

`tests/Unit/Services/Workflow/LeaseRenewalTest.php` documents a same-second
lease-renewal false negative, but runs on SQLite (the project's default test
connection), whose `UPDATE` always reports rows _matched_, never rows
_changed_ — so it cannot actually reproduce the bug; every test there passes
even against the pre-fix implementation. Only MySQL's PDO driver exhibits the
rows-changed behavior that caused `SUBMISSION_LEASE_LOST` false negatives in
production. `tests/Integration/Workflow/LeaseRenewalMysqlRegressionTest.php`
is the real reproduction: demonstrated failing against the pre-fix
`IdempotencyCoordinator::renewLease()` / `TemporaryUploadReservationService::renew()`,
passing against the corrected implementation.
