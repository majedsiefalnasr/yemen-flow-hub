# 00 — Scope, Method, and Decision Register

Read this document before any findings file. Findings must not be interpreted without this calibration context.

Governing spec: `docs/superpowers/specs/2026-07-08-performance-scalability-audit-design.md`. Execution plan: `docs/superpowers/plans/2026-07-08-performance-scalability-audit.md`.

## Repository audit baseline

| Item | Value |
| --- | --- |
| Branch | `main` |
| Starting commit SHA | `be652fdd5c56767acb6ab2bf3863de28c92e50aa` |
| Audit start | 2026-07-08 19:48 UTC |
| Initial `git status` | `.superpowers/sdd/progress.md`, `.superpowers/sdd/task-6-report.md`, `.superpowers/sdd/task-7-report.md`, `.superpowers/sdd/task-8-report.md` (modified; out of audit scope, untouched) |
| `backend/composer.lock` sha256 | `b8ea6313838f87448e0a557863e2c7b1524cb0834af39ef7badc2766d21ec21d` |
| `frontend/pnpm-lock.yaml` sha256 | `e62dc70a22837b590760b86c9a1d76a6e19b96aa3c59a1fbf00c255775259d4d` |

All `file:line` evidence in this audit refers to this baseline commit unless a later block explicitly records a different SHA.

## Calibration

| Dimension | Value |
| --- | --- |
| **Audit design target** | Millions of `engine_requests`, `workflow_history`, `audit_logs`, `notifications` rows; hundreds of concurrent users |
| **Expected initial business scale** | Tens of banks, hundreds of users |
| **Deployment status** | Pre-production. No production metrics exist. All dynamic evidence is local-synthetic. |

Severity (Critical / High / Medium / Low) is rated against the **design target**, tied to explicit "at what scale does this break" reasoning. Initial business scale informs roadmap tiering, not severity.

Every recommendation is placed in exactly one roadmap tier:

1. **Pre-production** — required before go-live.
2. **Threshold-gated** — required when a stated, measurable threshold is reached (e.g., table row count, p95 latency, queue depth).
3. **Optional** — future scaling improvement; not required by current evidence.

## Infrastructure trigger rule

Advanced infrastructure (partitioning, read replicas, dedicated search engines, sharding) is recommended for *implementation* only when evidence shows simpler optimizations (indexes, query rewrites, pagination strategy, caching, archival) are insufficient. Such items may appear in the roadmap only as threshold-gated or optional tiers.

## Read-only definition

During the audit:

- No application behavior, schema, configuration, or dependency changes.
- No changes to backend/frontend source, migrations, `.env` handling, composer/pnpm dependencies, or CI/hook configuration.
- Local synthetic-data tooling may create and modify **a dedicated local audit database only** (`yfh_audit`).
- Only new committed files: audit documentation under `docs/audit/` and spec/plan under `docs/superpowers/`.
- The synthetic seeder and EXPLAIN harness stay local-only and uncommitted.
- Fixes happen only in later, separately approved work, each with its own plan, migration, and rollback strategy.

## Locked decisions

- Commit scope: `docs(docs)` for all audit commits (`docs(audit)` rejected — not in the AGENTS.md allowed-scope list; adding it would be an unnecessary exception to the read-only rule).
- One signed conventional commit per approved block, created only after checkpoint approval.
- Critical security or correctness findings are reported immediately when found, without waiting for the checkpoint.
- Seeder/EXPLAIN harness: local-only, uncommitted; keep/discard recommendation due in Block 6.
- Security and correctness are cross-cutting: every recommendation in every block must preserve bank/org scoping, policy/permission behavior, transactional boundaries, and audit-log completeness and ordering. Block 5 verifies these checks; it does not discover them.
- Block 2 owns backend endpoint behavior/serialization; Block 4 owns frontend consumption of those endpoints. Cross-reference, never duplicate findings across this boundary.

## Known evidence limitations

- Pre-production: no production metrics, traffic patterns, or real data volumes exist. All dynamic evidence is local-synthetic and labeled as such.
- Local MySQL runs with `innodb_buffer_pool_size` = 128 MB (server default, Docker container). Design-target datasets exceed this buffer pool, so absolute timings are heavily disk-bound and understate production hardware. Query shape (examined rows, index usage, filesort/temporary tables, join strategy) is the primary evidence; timings are comparative context only.
- MySQL runs on aarch64 inside Docker Desktop; I/O passes through virtualized storage.

## Deferred questions

(appended as they arise)

## Approved deviations from the original audit prompt

- Load tests: plan only, not executed (approved in design).
- `EXPLAIN ANALYZE` bounded to a prioritized hot/high-risk read-query set, not every suspect query (approved in design).
- Locking/write paths assessed via transaction-boundary inspection, generated-SQL review, lock-order analysis, and safe concurrent reproduction — never `EXPLAIN ANALYZE` on behavior-changing statements (approved in design).

## Checkpoint approval history

| Block | Approval date | Approved commit SHA | Decisions / scope changes | Deferred findings |
| --- | --- | --- | --- | --- |
| 1 — Discovery & architecture | 2026-07-08 | `62e6a395` | SEC-001 approved for immediate fix (committed `375fe5f2`, `fix(backend): remove unauthenticated test-api endpoint`) — the only application-code change in the audit. Block 2 scope approved. | None |
| 2 — API & Laravel | 2026-07-08 | `a26f789c` | 7 API findings; API-000 superseded by API-003. Block 3 scope approved. Highest-leverage fix identified: ARCH-001. | None |
| 3 — Database & seeded EXPLAIN | 2026-07-08 | (this docs commit) | 1M-row design-target dataset (set-based fallback for the 1M tier, documented). ARCH-002/004 verified with before/after evidence; 3 index proposals (DB-001..003); ARCH-001 reclassified as application-layer. Block 4 scope approved. | Low-selectivity index removal (needs query-log evidence); steady-state published-workflow count |

(one row appended per approved block)

## Finding template

Every finding in `02-findings.md` carries these fields:

```markdown
### <ID> — <one-line title>
| Field | Value |
| --- | --- |
| Area / component | <file or component> |
| Endpoint / query | <route or query> |
| Current behavior | <what the code does today, with file:line at baseline SHA> |
| Problem | <why it is a problem; at what scale it becomes dangerous> |
| Severity | Critical / High / Medium / Low |
| Evidence status | Verified / Partially Verified / Assumption |
| Finding status | Open / Revised / Superseded / Accepted |
| Roadmap tier | Pre-production / Threshold-gated (<threshold>) / Optional |
| First identified / last reviewed | Block N / Block M |
| Related findings | <IDs> |
| Evidence | <links into evidence/queries/ and evidence/explain/> |
| Confidence | High / Medium / Low |
| Recommendation | <solution, expected impact, trade-offs, risks, complexity, validation method> |
| Security gate | <preserved scoping/authz/transactions/audit-logging — verified in Block 5> |
```

ID prefixes: `ARCH-`, `API-`, `DB-`, `FE-`, `CACHE-`, `QUEUE-`, `SEC-`, `OBS-`. IDs are stable: later evidence updates a finding in place (severity/status change), never duplicates it. Invalidated findings are marked `Superseded` with a short explanation, never removed. **`Accepted`** means the finding and its roadmap disposition were approved during a checkpoint — it does **not** mean the problem has been fixed, nor that the risk was waived; implementation status is outside this audit.

## Checkpoint summary template

Each block checkpoint presents:

- Work completed
- New findings by severity
- Findings whose severity changed
- Verified evidence produced
- Assumptions still unresolved
- Decisions requiring approval
- Missing information (batched)
- Proposed scope for the next block
- Files ready to commit

Approval covers both the findings and the next block's priorities, and is recorded in the table above.
