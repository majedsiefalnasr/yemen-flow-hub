# Performance & Scalability Audit — Design

**Date:** 2026-07-08
**Status:** Approved design — ready for implementation planning
**Type:** Read-only technical audit (no application changes)

## 1. Purpose

Perform a complete performance, scalability, database, API, caching, queue, frontend-consumption, security-overhead, and observability audit of Yemen Flow Hub (Laravel 11 backend, Nuxt 4 frontend, MySQL, Redis, Sanctum), and produce a structured, evidence-backed improvement plan. The audit documents the system first and recommends second. No code is modified during the audit.

## 2. Calibration

| Dimension | Value |
| --- | --- |
| **Audit design target** | Millions of `engine_requests`, `workflow_history`, `audit_logs`, `notifications` rows; hundreds of concurrent users |
| **Expected initial business scale** | Tens of banks, hundreds of users |
| **Deployment status** | Pre-production. No production metrics exist; every document states this explicitly |

- **Severity** (Critical/High/Medium/Low) is rated against the design target, tied to explicit "at what scale does this break" reasoning.
- **Infrastructure trigger rule:** advanced infrastructure (partitioning, read replicas, dedicated search engines, sharding) is recommended for *implementation* only when evidence shows simpler optimizations (indexes, query rewrites, pagination strategy, caching, archival) are insufficient. Such items may appear in the roadmap only as threshold-gated or optional tiers.
- **Roadmap tiers:** every recommendation is placed in exactly one tier:
  1. **Pre-production** — required before go-live.
  2. **Threshold-gated** — required when a stated, measurable threshold is reached (e.g., table row count, p95 latency, queue depth).
  3. **Optional** — future scaling improvement; not required by current evidence.

## 3. Read-Only Definition

During the audit:

- No application behavior, schema, configuration, or dependency changes.
- No changes to backend/frontend source, migrations, `.env` handling, composer/pnpm dependencies, or CI/hook configuration.
- Local synthetic-data tooling may create and modify **a dedicated local audit database only**.
- Only new committed files: audit documentation under `docs/audit/` and this spec/plan under `docs/superpowers/specs/`.
- Pre-existing dirty files (`.superpowers/sdd/*`) remain untouched.
- Fixes happen only in later, separately approved work, each with its own plan, migration, and rollback strategy.

## 4. Method & Evidence Rules

### Discovery tooling

- Prefer graphify (`graphify query/path/explain`) and SocratiCode (`codebase_search`, `codebase_symbol`, `codebase_impact`, `codebase_flow`) where they return reliable results.
- Do not block on tooling: where a repository area is unsupported or results are unreliable, fall back to direct code inspection, framework tooling (`php artisan route:list`, query log capture), SQL capture, and targeted search.

### Evidence standards

- Every finding cites concrete evidence: `file:line`, migration, route definition, or captured SQL + query plan.
- Evidence status labels: **Verified** (code/plan evidence in hand), **Partially Verified** (some evidence, gaps stated), **Assumption** (requires production data or unavailable information; states what data would confirm it).
- No invented benchmark numbers. Only captured plans and timings from the local seeded environment, always labeled local-synthetic.
- Local timings are comparative context only. Query shape, examined rows, index usage, filesort/temporary-table behavior, and join strategy are the primary dynamic evidence, not raw execution time.
- Both the application-generated SQL (from Eloquent/query builder) and its query plan are captured, so each finding links plan evidence to the actual implementation that produces the query.

### Repository audit baseline

Recorded in `00-scope-and-method.md` and mirrored in `evidence/environment.md` at audit start:

- Repository branch and starting commit SHA
- Audit start date and time
- Initial `git status` output and relevant pre-existing dirty files
- Backend and frontend dependency lockfile hashes where practical (`composer.lock`, `pnpm-lock.yaml`)

All `file:line` evidence refers to this baseline commit unless a later block explicitly records a different SHA. Line numbers alone are not durable evidence if the branch changes during the audit.

### Environment capture

`docs/audit/evidence/environment.md` records the exact local environment used for dynamic evidence: MySQL version, PHP version, Laravel environment/config relevant to queries, dataset size and distribution summary, available CPU and memory, and relevant database configuration (buffer pool size, etc.).

### Synthetic data realism

The seeded dataset models realistic distributions, not uniform random rows:

- Uneven request counts per bank (skewed distribution).
- Mixed workflow stages and statuses matching the canonical status enum.
- Recency skew: recent records over-represented in access patterns.
- Long history/audit chains for a subset of requests.
- Nullable and optional relationships populated realistically.

Dataset profile documented in `docs/audit/evidence/dataset-profile.md`.

### Synthetic-volume fallback

Goal: at least one million rows in the hot tables. Controlled fallback:

- Seed progressively (e.g., 100k → 500k → 1M+ rows).
- Use 1M+ where the local environment supports it safely.
- If local hardware prevents the intended volume, record the maximum achieved dataset, the limiting resource, and how that weakens the evidence.
- Never present results from a smaller dataset as proof of design-target performance.

### EXPLAIN safety protocol

- `EXPLAIN ANALYZE` only for safe read queries.
- Write, locking, or destructive operations: disposable local database, transaction safeguards (wrap + rollback), plain `EXPLAIN`, or safe local reproduction and transaction inspection instead.
- Never force `EXPLAIN ANALYZE` onto behavior-changing statements.

### Evidence sanitization

Committed SQL and plans must contain no secrets, tokens, credentials, real personal data, or sensitive business values. Application-generated SQL is stored with placeholders by preference; bindings may be recorded separately only when they are synthetic and useful for reproducing the plan.

### EXPLAIN scope (bounded)

Not every suspected query receives seeded `EXPLAIN ANALYZE`. Static analysis prioritizes a bounded hot/high-risk set based on: expected call frequency, expected table growth, query complexity, deep sorting/filtering, authorization joins, dashboard aggregation, queue-list importance, history/audit growth, and locking/concurrency risk. Ordinary findings may rest on code and schema evidence alone. Dynamic evidence is reserved for queries where it materially affects the conclusion.

## 5. Seeder & Harness Policy

- The synthetic seeder and EXPLAIN harness stay **local-only and uncommitted** during the audit (scratchpad or ignored local scripts). No audit-specific code enters the production repository before its long-term value is proven.
- Committed evidence may include the seeder's configuration parameters and resulting plans, but not the tooling itself.
- The final report includes a separate keep/discard recommendation on converting the harness into a permanent performance-testing asset (candidate homes: `tests/Performance`, `tools/performance`, a non-production seeding command, or a default-disabled CI performance profile). Commit later only if reusable, deterministic, documented, isolated from normal seeders, and protected from running in production.

## 6. Work Blocks

Six sequential blocks. Security and correctness are **cross-cutting constraints checked in every block**, not deferred to Block 5: every pagination, query, cache, or queue recommendation must preserve bank/organization scoping, policy and permission behavior, transactional boundaries, and audit-log completeness and ordering. Block 5 consolidates and verifies these checks rather than discovering them.

### Block 1 — Discovery & architecture map (prompt Phase 1)

Full request lifecycle: Nuxt composable → API route → middleware → controller → service → Eloquent → MySQL/Redis. Sanctum auth and authorization chain (policies, role checks, step-up). Business-logic distribution across controllers/services/models/jobs/events/middleware. Transaction handling inventory. Error/response conventions. Legacy and duplicate-logic scan. Outputs: system discovery report, request/data-flow map, prioritized list of highest-risk endpoints and database operations, missing-information list.

### Block 2 — API & Laravel backend audit (Phases 2 + 5)

Owns **backend endpoint behavior, Laravel execution, serialization, and response construction**. All registered API routes — beginning with `backend/routes/api.php` and including every route file imported or registered from it, verified against `php artisan route:list` output so split route groups cannot be silently excluded: unbounded `get()`/`all()`, pagination strategy per list endpoint (offset vs simple vs cursor/keyset with justification), N+1, over-fetching of columns/relations, API-resource-triggered queries, repeated queries, expensive authorization, sync work that should queue, rate limits, response-shape consistency, HTTP status correctness. Laravel specifics: accessors/appends, global scopes, observers, events, middleware cost, Sanctum overhead, validation, config/route caching, Octane suitability assessment.

### Block 3 — Database audit + seeded EXPLAIN (Phases 3 + 4)

All 131 migrations consolidated into a schema model (tables, types, keys, indexes, JSON columns, pivot/audit/history tables, soft deletes). Index inventory cross-checked against actual query patterns collected in Blocks 1–2. Local audit database seeded to design-target volume with the realistic distribution profile; SQL + plans captured for the bounded hot-query set (queue lists, filters, dashboards, history/audit reads, counts). Locking paths (e.g., `EngineTransitionService`) follow the safety protocol: safe read components may receive `EXPLAIN` or `EXPLAIN ANALYZE`; locking and write paths are assessed through transaction-boundary inspection, generated-SQL review, lock-order analysis, and safe concurrent reproduction against the disposable audit database; no behavior-changing statement receives `EXPLAIN ANALYZE`. Growth-forever tables, archival/retention needs, locking/deadlock/contention risks, connection exhaustion. Every index proposal includes table, columns, order, benefiting query/endpoint, expected benefit, write-cost trade-off, and suggested migration + rollback.

### Block 4 — Frontend consumption + caching + queues (Phases 6 + 7 + 8)

Owns **how the frontend calls and consumes the API** (an oversized response is a Block 2 finding; re-requesting or storing it inefficiently in Pinia is a Block 4 finding — no duplicate findings across the boundary). Nuxt data fetching: duplicate calls, render-triggered refetches, cancellation, debounce/throttle, claim-heartbeat polling, reference-data caching, table rendering, client-side filtering/sorting of large sets, bundle weight. Caching strategy: cacheable vs never-cache, key structure, TTLs, invalidation, user/org/role/permission isolation, stampedes, Redis-down fallback. Queue audit: sync-in-HTTP-path operations, retry/backoff/timeout/idempotency, queue separation and priority, failed-job handling, transaction interaction (`afterCommit` semantics and audit-log ordering).

### Block 5 — Security-correctness gate + observability + load-test plan (Phases 9 + 10 + 11)

Consolidated verification that every recommendation from Blocks 1–4 preserves authorization, org scoping, transactions, auditability, and idempotency; user-supplied sort/filter field safety; enumeration and race-condition review. Observability gaps and recommendations sized to this project (slow-query log, per-endpoint query counts, queue metrics, p50/p95/p99 targets; Pulse/Horizon/Telescope where justified). Measurable performance targets per endpoint class. Load & stress testing **plan only** (scenarios, dataset sizes, concurrency, RPS, success criteria, failure thresholds) — execution is out of scope.

### Block 6 — Compile deliverables

Executive summary, consolidated findings table, quick wins, high-priority list, database optimization plan, API optimization plan, architecture improvement plan, three-tier roadmap organized into prompt Phases A–F, before/after examples, verification checklist, seeder keep/discard recommendation.

## 7. Checkpoint Protocol

Per-block sequence:

1. Complete the block analysis.
2. Write the draft audit files.
3. Present the checkpoint summary and decisions requiring approval.
4. Revise files based on feedback.
5. Create the signed conventional commit (approved content only — no pre-approval commits).
6. Start the next block.

**Exception:** critical security or correctness findings are reported immediately when found, without waiting for the checkpoint.

Each checkpoint summary contains:

- Work completed
- New findings by severity
- Findings whose severity changed
- Verified evidence produced
- Assumptions still unresolved
- Decisions requiring approval
- Missing information (batched; mid-block requests only when truly blocking)
- Proposed scope for the next block
- Files ready to commit

Approval covers both the findings and the next block's priorities.

Approvals are recorded in a compact checkpoint history inside `00-scope-and-method.md`, one entry per block: block number, approval date, approved commit SHA, important decisions or scope changes, and any findings intentionally deferred.

## 8. Deliverable Layout

```text
docs/audit/
├── README.md                     ← navigation, audit status, final executive summary
├── 00-scope-and-method.md        ← decision & assumption register (below)
├── 01-architecture.md            ← Block 1
├── 02-findings.md                ← consolidated findings table, stable IDs
├── 03-database-plan.md           ← Block 3 plans, index proposals, migrations + rollbacks
├── 04-api-plan.md                ← Block 2 plans: pagination, filtering, responses, rate limits
├── 05-frontend-caching-queues.md ← Block 4
├── 06-security-observability.md  ← Block 5
├── 07-roadmap.md                 ← Phases A–F, three tiers, before/after, verification checklist
├── 08-load-test-plan.md          ← scenarios, datasets, criteria
└── evidence/
    ├── environment.md            ← exact local environment for dynamic evidence
    ├── dataset-profile.md        ← synthetic data volumes and distributions
    ├── queries/                  ← captured application SQL, prefixed by finding ID
    └── explain/                  ← captured plans, prefixed by finding ID
```

Raw evidence stays out of narrative documents; each finding links to its captured SQL and plan files. A finding may have multiple evidence files when it covers multiple authorization scopes, filters, or before/after plans, named with the finding ID plus a descriptive suffix, e.g.:

```text
evidence/queries/DB-001-bank-queue.sql
evidence/queries/DB-001-admin-queue.sql
evidence/explain/DB-001-bank-queue-before.txt
evidence/explain/DB-001-bank-queue-after.txt
```

### `00-scope-and-method.md` — decision & assumption register

Contains: locked audit decisions, scale assumptions (design target + initial business scale), known evidence limitations, deferred questions, approved deviations from the original audit prompt, the infrastructure trigger rules, the repository audit baseline (branch, starting SHA, start timestamp, initial `git status`, lockfile hashes), and the checkpoint approval history. Prevents findings from being read without calibration context. `README.md` stays navigation + status + executive summary only.

## 9. Finding Schema

Every finding carries:

| Field | Values / notes |
| --- | --- |
| ID | Stable, prefixed by area: `API-001`, `DB-007`, `FE-003`, `CACHE-002`, `QUEUE-001`, `SEC-001`, `OBS-001`, `ARCH-001` |
| Area / component | File or component, endpoint or query |
| Current behavior & problem | With at-what-scale-dangerous reasoning |
| Severity | Critical / High / Medium / Low (rated against design target) |
| Evidence status | Verified / Partially Verified / Assumption |
| Finding status | Open / Revised / Superseded / Accepted |
| Roadmap tier | Pre-production / Threshold-gated / Optional |
| First identified in block / last reviewed in block | Block numbers |
| Related finding IDs | Cross-references |
| Evidence references | Links into `evidence/queries/` and `evidence/explain/` |
| Confidence | High / Medium / Low |
| Recommendation | With expected impact, trade-offs, risks, implementation complexity, validation method |

Lifecycle rules: later evidence updates a finding in place under its stable ID (severity/status change), never duplicates it. Invalidated findings are marked **Superseded** with a short explanation, never silently removed.

**`Accepted`** means the finding and its roadmap disposition were approved during a checkpoint. It does **not** mean the underlying problem has been fixed, nor that the risk was waived. Implementation status remains outside this audit.

## 10. Commit Conventions

- Signed conventional commits, one per approved block, from the repository root.
- **Scope decision (resolved):** all audit commits use `docs(docs)`. The preferred `docs(audit)` scope is not in the AGENTS.md allowed-scope list, and adding it (AGENTS.md + both commitlint configs) was rejected as an unnecessary exception to the audit's read-only rule for only six documentation commits.
- Block commit messages:

  ```text
  docs(docs): document architecture and request lifecycle
  docs(docs): record API and Laravel audit findings
  docs(docs): add database plans and query evidence
  docs(docs): document frontend caching and queue findings
  docs(docs): add security observability and load-test plans
  docs(docs): compile final roadmap and executive summary
  ```

## 11. Out of Scope

- Implementing any fix, index, migration, cache, or queue change (separate approved work later).
- Executing load tests (plan only).
- Production infrastructure provisioning or metrics collection.
- Committing the seeder/harness (end-of-audit recommendation only).

## 12. Success Criteria

- All 11 prompt phases covered across the six blocks; all required deliverable sections present in `docs/audit/`.
- Every finding evidence-linked, severity-rated, tiered, and lifecycle-tracked per the schema.
- Every optimization recommendation explicitly confirms preserved authorization, org scoping, transactions, and audit logging.
- No application, schema, configuration, or dependency changes on any branch.
- Six checkpoint approvals recorded; final report readable standalone via `README.md` + `00-scope-and-method.md`.
