# Evidence Environment

Exact local environment used for all dynamic evidence in this audit. No credentials are recorded here; local development credentials live in the compose file and `backend/.env`, neither of which is production-relevant.

## Repository baseline (mirror of 00-scope-and-method.md)

| Item | Value |
| --- | --- |
| Branch / SHA | `main` / `be652fdd5c56767acb6ab2bf3863de28c92e50aa` |
| Audit start | 2026-07-08 19:48 UTC |
| `backend/composer.lock` sha256 | `b8ea6313838f87448e0a557863e2c7b1524cb0834af39ef7badc2766d21ec21d` |
| `frontend/pnpm-lock.yaml` sha256 | `e62dc70a22837b590760b86c9a1d76a6e19b96aa3c59a1fbf00c255775259d4d` |

## Host

| Item | Value |
| --- | --- |
| Machine | Apple Silicon, 11 CPU cores, 18 GB RAM |
| OS | macOS 26.5.1 |
| PHP (host CLI) | 8.5.4 (NTS) |
| Laravel | 11.51.0, `APP_ENV=local` |

## Database

| Item | Value |
| --- | --- |
| MySQL | 8.4.9 (Community), image `mysql:8.4`, container `yfh-mysql`, linux/aarch64 |
| Access | Host port 3306; client via `docker exec yfh-mysql mysql` (no host `mysql` CLI) |
| `innodb_buffer_pool_size` | 128 MB (server default — see limitation below) |
| `max_connections` | 151 (server default) |
| Storage | Docker named volume `mysql_data` (virtualized I/O through Docker Desktop) |
| App database | `cby_imports` (untouched by the audit) |
| Audit database | `yfh_audit` — created in Block 3, disposable, the only database the audit tooling writes to |

## Supporting services

| Item | Value |
| --- | --- |
| Redis | 7 (alpine), container `yfh-redis`, port 6379 — cache store and queue connection |
| Session driver | `database` |
| Mail (dev) | Mailpit container `yfh-mailpit` |

## Limitations affecting evidence

- Buffer pool (128 MB) is far below design-target dataset size, so absolute timings are disk-bound and understate any production-grade server. Query shape — examined rows, index usage, filesort/temporary tables, join strategy — is the primary evidence; timings are comparative context only, always labeled local-synthetic.
- aarch64 container with virtualized storage: I/O characteristics differ from production hardware.
- Env-var overrides used for audit runs (e.g., `DB_DATABASE=yfh_audit`) are recorded here as they occur; no configuration files are modified.

## Overrides used (running log)

| Date | Command context | Overrides |
| --- | --- | --- |
| 2026-07-08 | Migrate + seed against audit DB | `DB_DATABASE=yfh_audit` env var only |
| 2026-07-08 | PHP seeder volume runs | `php -d memory_limit=1024M..3072M` (base tier); 1M tier built set-based in SQL (no PHP) |
| 2026-07-08 | All EXPLAIN/timing captures | `docker exec yfh-mysql mysql -ucby ...`; `ANALYZE TABLE` run before captures; disposable indexes created + dropped per before/after test |

Timing method: `/usr/bin/time -p docker exec ... mysql -e "<query>"`, `SQL_NO_CACHE`, 2–3 runs, `real` reported. Includes ~0.1 s docker-exec + client overhead — treat sub-0.15 s results as "effectively instant," not literal query time. Plan shape (examined rows, index usage, sort/temp-table) is the primary evidence.
