# Integration tests

Tests here talk to real external services (currently: a real MySQL server)
rather than the SQLite in-memory connection every other suite uses. They are
**not** part of the default `composer test` / `php artisan test` run.

## Running

```bash
# The mysql-integration group specifically:
composer test:mysql-integration

# Equivalent, explicit form:
php artisan test --testsuite=Integration --group=mysql-integration
```

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
never silently skipped.

## Why this exists

`tests/Unit/Services/Workflow/LeaseRenewalTest.php` documents a same-second
lease-renewal false negative, but runs on SQLite (the project's default test
connection), whose `UPDATE` always reports rows *matched*, never rows
*changed* — so it cannot actually reproduce the bug; every test there passes
even against the pre-fix implementation. Only MySQL's PDO driver exhibits the
rows-changed behavior that caused `SUBMISSION_LEASE_LOST` false negatives in
production. `tests/Integration/Workflow/LeaseRenewalMysqlRegressionTest.php`
is the real reproduction: demonstrated failing against the pre-fix
`IdempotencyCoordinator::renewLease()` / `TemporaryUploadReservationService::renew()`,
passing against the corrected implementation.
