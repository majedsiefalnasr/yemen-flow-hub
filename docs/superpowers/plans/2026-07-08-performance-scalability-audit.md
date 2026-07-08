# Performance & Scalability Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute the read-only performance & scalability audit defined in `docs/superpowers/specs/2026-07-08-performance-scalability-audit-design.md`, producing evidence-backed findings and an improvement roadmap in `docs/audit/`.

**Architecture:** Six sequential audit blocks with a checkpoint-before-commit gate after each: discovery → API/Laravel → database with seeded EXPLAIN → frontend/caching/queues → security-observability gate → compiled roadmap. A local-only seeder/EXPLAIN harness (never committed) generates dynamic evidence against a dedicated local audit database.

**Tech Stack:** Laravel 11 / PHP artisan tooling, MySQL (local audit DB), graphify + SocratiCode for discovery, markdown deliverables.

## Global Constraints

Copied from the approved spec — every task implicitly includes these:

- **Read-only:** no application behavior, schema, configuration, or dependency changes. No edits to backend/frontend source, migrations, composer/pnpm dependencies, CI, or hooks. Local synthetic tooling may create/modify **a dedicated local audit database only** (`yfh_audit`).
- **Only committed files:** documentation under `docs/audit/` (and this plan/spec under `docs/superpowers/`). Seeder and EXPLAIN harness stay in the session scratchpad, uncommitted. Never stage `graphify-out/`.
- **Pre-existing dirty files** (`.superpowers/sdd/*`) remain untouched.
- **Commit cadence:** one signed conventional commit per approved block, scope `docs(docs)`, created only **after** checkpoint approval. Exception: critical security/correctness findings are reported to the user immediately when found.
- **Severity** (Critical/High/Medium/Low) rated against design target: millions of rows in `engine_requests`, `workflow_history`, `audit_logs`, `notifications`; hundreds of concurrent users. Expected initial business scale (tens of banks, hundreds of users) informs roadmap tiering, not severity.
- **Roadmap tiers:** every recommendation gets exactly one of `Pre-production` / `Threshold-gated` (with a measurable threshold) / `Optional`.
- **Infrastructure trigger rule:** partitioning, read replicas, search engines, sharding → recommend for implementation only when evidence shows simpler optimizations insufficient; otherwise threshold-gated or optional tier only.
- **Evidence:** every finding cites `file:line` (at baseline SHA), migration, route, or captured SQL + plan. Labels: Verified / Partially Verified / Assumption. No invented numbers; local timings labeled local-synthetic and subordinate to plan shape (examined rows, index usage, filesort/temp table, join strategy).
- **EXPLAIN safety:** `EXPLAIN ANALYZE` only on safe reads. Write/locking paths: transaction-boundary inspection, generated-SQL review, lock-order analysis, safe concurrent reproduction on the disposable audit DB. Never `EXPLAIN ANALYZE` a behavior-changing statement.
- **Sanitization:** committed SQL/plans contain no secrets, tokens, credentials, real personal data, or sensitive business values. SQL stored with placeholders; synthetic bindings recorded separately only when needed for reproduction.
- **Finding lifecycle:** stable IDs (`API-001`, `DB-007`, `FE-003`, `CACHE-002`, `QUEUE-001`, `SEC-001`, `OBS-001`, `ARCH-001`); update in place, never duplicate; invalidated findings marked `Superseded` with explanation; `Accepted` = checkpoint-approved disposition, not fixed.
- **Cross-cutting security:** every recommendation in every block must preserve bank/org scoping, policy/permission behavior, transactional boundaries, and audit-log completeness/ordering.

---

### Task 1: Audit scaffolding, baseline register, and templates

**Files:**
- Create: `docs/audit/README.md`
- Create: `docs/audit/00-scope-and-method.md`
- Create: `docs/audit/evidence/environment.md`
- Create: `docs/audit/evidence/dataset-profile.md` (skeleton, filled in Task 5)

**Interfaces:**
- Produces: the finding template and checkpoint-summary template all later tasks must use; the baseline SHA all `file:line` evidence refers to.

- [ ] **Step 1: Capture the repository baseline**

Run from repo root and keep the output for Step 3:

```bash
git rev-parse --abbrev-ref HEAD && git rev-parse HEAD && date -u '+%Y-%m-%d %H:%M UTC'
git -c core.fsmonitor=false status --short
shasum -a 256 backend/composer.lock frontend/pnpm-lock.yaml
```

Expected: branch `main`, a 40-char SHA, current UTC timestamp, dirty list containing only `.superpowers/sdd/*` files (report anything else to the user before proceeding), two SHA-256 hashes.

- [ ] **Step 2: Capture the local environment**

```bash
mysql --version && php -v | head -1
sysctl -n hw.ncpu && echo "$(($(sysctl -n hw.memsize) / 1073741824)) GB RAM" && sw_vers -productVersion
mysql -e "SELECT @@version, @@innodb_buffer_pool_size, @@max_connections\G"
cd backend && php artisan --version
```

Expected: versions print without error. If `mysql` CLI is unavailable, stop and ask the user how local MySQL is run (e.g., DBngin, Homebrew, Docker) — do not guess.

- [ ] **Step 3: Write `docs/audit/00-scope-and-method.md`**

Sections, in order, populated from the spec and Steps 1–2 (copy calibration values verbatim from spec §2–§5):

```markdown
# 00 — Scope, Method, and Decision Register

## Repository audit baseline
| Item | Value |
| --- | --- |
| Branch | main |
| Starting commit SHA | <from Step 1> |
| Audit start | <UTC timestamp from Step 1> |
| Initial git status | .superpowers/sdd/progress.md, task-6/7/8-report.md (modified; out of audit scope) |
| composer.lock sha256 | <from Step 1> |
| pnpm-lock.yaml sha256 | <from Step 1> |

All file:line evidence refers to this SHA unless a block records a different one.

## Calibration
<design target, initial business scale, severity basis — copy from spec §2>

## Infrastructure trigger rule
<copy from spec §2>

## Read-only definition
<copy from spec §3>

## Locked decisions
- Commit scope: docs(docs) for all audit commits (docs(audit) rejected — not in AGENTS.md scope list).
- Seeder/EXPLAIN harness: local-only, uncommitted; keep/discard recommendation due in Block 6.
- Checkpoint-before-commit cadence; immediate reporting of critical security findings.

## Known evidence limitations
- Pre-production: no production metrics, traffic, or real data volumes exist. All dynamic evidence is local-synthetic.

## Deferred questions
(append as they arise)

## Approved deviations from the original audit prompt
- Load tests: plan only, not executed (approved in design).
- EXPLAIN ANALYZE bounded to hot/high-risk read queries, not every suspect query (approved in design).

## Checkpoint approval history
| Block | Approval date | Approved commit SHA | Decisions / scope changes | Deferred findings |
| --- | --- | --- | --- | --- |
(one row appended per approved block)

## Finding template
(the table from spec §9, reproduced as a copyable markdown block)

## Checkpoint summary template
- Work completed
- New findings by severity
- Findings whose severity changed
- Verified evidence produced
- Assumptions still unresolved
- Decisions requiring approval
- Missing information (batched)
- Proposed scope for next block
- Files ready to commit
```

- [ ] **Step 4: Write `docs/audit/README.md`**

Navigation only at this stage:

```markdown
# Performance & Scalability Audit

**Status:** Block 1 in progress · Baseline SHA `<short-sha>` · Started 2026-07-08

Read `00-scope-and-method.md` first — findings must not be interpreted without its calibration context.

| Document | Contents | Status |
| --- | --- | --- |
| 00-scope-and-method.md | Decision & assumption register, baseline, approval history | Live |
| 01-architecture.md | Current architecture and request lifecycle | Pending |
| 02-findings.md | Consolidated findings table | Pending |
| 03-database-plan.md | Schema/index/query/archival plan | Pending |
| 04-api-plan.md | Pagination/filtering/response/rate-limit plan | Pending |
| 05-frontend-caching-queues.md | Frontend consumption, caching, queue findings | Pending |
| 06-security-observability.md | Security gate, monitoring targets | Pending |
| 07-roadmap.md | Phased roadmap, before/after, verification checklist | Pending |
| 08-load-test-plan.md | Load & stress test plan (not executed) | Pending |
| evidence/ | Environment, dataset profile, captured SQL and plans | Live |

## Executive summary
(written in Block 6)
```

- [ ] **Step 5: Write `docs/audit/evidence/environment.md` and `dataset-profile.md` skeleton**

`environment.md`: table with MySQL version, PHP version, Laravel version, `APP_ENV` used for capture, CPU cores, RAM, macOS version, `innodb_buffer_pool_size`, `max_connections`, audit DB name `yfh_audit`, plus a copy of the baseline table. `dataset-profile.md`: heading + "Populated during Block 3 seeding" note listing the planned distribution dimensions (per-bank skew, status mix, recency skew, history-chain skew, nullable relations).

- [ ] **Step 6: Do NOT commit**

These files are drafts; they ship with the Block 1 commit after Checkpoint 1 approval.

---

### Task 2: Block 1 — Discovery & architecture map

**Files:**
- Create: `docs/audit/01-architecture.md`
- Create: `docs/audit/02-findings.md` (table skeleton + any ARCH findings)
- Modify: `docs/audit/README.md` (status), `docs/audit/00-scope-and-method.md` (approval history after checkpoint)

**Interfaces:**
- Consumes: templates and baseline from Task 1.
- Produces: prioritized top-risk endpoint/query list that bounds Task 3 and Task 6 scope; request-lifecycle map all later blocks reference.

- [ ] **Step 1: Build the route and middleware inventory**

```bash
cd backend && php artisan route:list --json > "$SCRATCHPAD/routes.json"
python3 -c "import json;rs=json.load(open('$SCRATCHPAD/routes.json'));print(len(rs),'routes');[print(r['method'],r['uri'],'→',r.get('action','')) for r in rs[:20]]"
```

Expected: route count printed (verifies every registered route is covered, not just `routes/api.php` — spec requirement). Cross-check any route whose action lives outside `app/Http/Controllers`.

- [ ] **Step 2: Map the request lifecycle with graphify + SocratiCode**

Run, capturing outputs as working notes:

```bash
graphify query "how does an API request flow from route through middleware, controller, service to the database"
graphify explain "EngineTransitionService"
graphify query "where are database transactions started and committed"
```

Use `codebase_search` for: "authorization policy role check", "claim TTL heartbeat", "audit log write", "notification dispatch". Fall back to direct reading of `backend/app/Http/Middleware`, `backend/app/Services`, `backend/app/Policies` where graph output is thin.

- [ ] **Step 3: Write `docs/audit/01-architecture.md`**

Required sections (each grounded in `file:line` at baseline SHA):

1. Stack and versions (from composer.json/package.json — read, do not modify).
2. Request lifecycle: Nuxt composable → `$fetch`/interceptor → route → middleware chain (list actual middleware classes in order) → controller → service → Eloquent → MySQL/Redis; response construction (API Resources vs manual arrays).
3. AuthN/AuthZ chain: Sanctum token flow, policies, role checks, step-up, org-scoping mechanism (name the actual scopes/traits).
4. Business-logic distribution table: controllers / services / models / jobs / events / middleware — with representative file references and any logic-in-controller violations noted as ARCH findings.
5. Transaction inventory: every `DB::transaction` / `lockForUpdate` site (grep + confirm by reading), what each protects.
6. Error and response conventions: error codes (`REQUEST_CLOSED`, `WORKFLOW_IMMUTABLE_STATE`), envelope shape, status-code usage.
7. Legacy/duplication scan results (e.g., customs-declaration vs FX-confirmation dual paths).
8. **Top-risk list:** ranked table of endpoints/queries most likely to break at design target, with reason (growth table, unbounded read, aggregation, lock contention). This bounds Block 2 depth and Block 3 EXPLAIN set.
9. Missing-information list.

- [ ] **Step 4: Seed `docs/audit/02-findings.md`**

Consolidated findings table using the Task 1 template columns; add any ARCH-prefixed findings discovered during mapping. Findings reference evidence files (none yet for ARCH) and carry all lifecycle fields.

- [ ] **Step 5: CHECKPOINT 1 — present and wait**

Present the checkpoint summary (Task 1 template) to the user. **Do not proceed or commit until approval.** Apply requested revisions to the drafts.

- [ ] **Step 6: Record approval and commit**

Append the approval row to `00-scope-and-method.md` checkpoint history, update README status, then:

```bash
git add docs/audit/
git commit -m "docs(docs): document architecture and request lifecycle"
```

Expected: signed commit; `git -c core.fsmonitor=false status --short` afterwards shows only the pre-existing `.superpowers/sdd/*` dirt.

---

### Task 3: Block 2 — API & Laravel backend audit

**Files:**
- Create: `docs/audit/04-api-plan.md`
- Modify: `docs/audit/02-findings.md` (API-xxx findings), `docs/audit/README.md`, `docs/audit/00-scope-and-method.md`

**Interfaces:**
- Consumes: `routes.json`, top-risk list, lifecycle map from Task 2.
- Produces: per-endpoint pagination verdicts and the confirmed hot-query candidate list consumed by Task 6 (EXPLAIN capture).

- [ ] **Step 1: Sweep for unbounded reads**

```bash
cd backend && grep -rn --include='*.php' -E '->get\(\)|::all\(\)|->pluck\(' app/Http app/Services | grep -v -i 'config\|cache' > "$SCRATCHPAD/unbounded-candidates.txt"
wc -l "$SCRATCHPAD/unbounded-candidates.txt"
```

Expected: candidate list. Then **read each site** — classify as bounded-by-nature (small reference table), bounded-by-scope, or genuinely unbounded (finding). No finding is filed from grep output alone.

- [ ] **Step 2: Audit every list endpoint from routes.json**

For each index/list route, record in a worksheet (scratchpad table): pagination type used (none / paginate / simplePaginate / cursorPaginate), default + max page size, sortable/filterable fields and whether user-supplied fields are whitelisted, eager loads, count queries. Verdict column: appropriate / needs change, with the spec's rule that offset pagination on design-target tables requires justification.

- [ ] **Step 3: Audit N+1, over-fetching, and resource-triggered queries**

For the top-risk endpoints from Task 2: read controller + service + API Resource chain; flag lazy-loaded relations inside Resources, accessors/`$appends` that query, missing `select` column lists on wide tables, repeated per-item authorization queries.

- [ ] **Step 4: Laravel execution review**

Check and document (with file references): global scopes, observers, sync event listeners that dispatch heavy work, middleware cost per request (count middleware, note any that query), validation cost, Sanctum token lookup, `config:cache`/`route:cache` readiness, sync-in-HTTP work that belongs on queues (notifications, document generation, mail), rate-limit coverage per route group, response-shape consistency, HTTP status correctness (`REQUEST_CLOSED` 403 vs `WORKFLOW_IMMUTABLE_STATE` 409 usage).

- [ ] **Step 5: Write `docs/audit/04-api-plan.md` and file API-xxx findings**

Plan document sections: pagination strategy per endpoint class (with cursor/keyset recommendations where design-target tables are listed), filtering/sorting whitelist design, response-shape and column-selection plan, rate-limit plan, conditional-request/caching candidates, async-offload list. Every recommendation states preserved org-scoping/authorization explicitly and carries trade-offs + roadmap tier. Findings go into `02-findings.md` under stable API-xxx IDs with the confirmed hot-query list marked for Task 6.

- [ ] **Step 6: CHECKPOINT 2 — present, wait, revise, then commit**

Same protocol as Task 2 Step 5–6. Commit message:

```bash
git add docs/audit/
git commit -m "docs(docs): record API and Laravel audit findings"
```

---

### Task 4: Block 3a — Schema model and index inventory

**Files:**
- Create: `docs/audit/03-database-plan.md` (schema + index inventory sections)
- Modify: `docs/audit/02-findings.md` (DB-xxx schema-level findings)

**Interfaces:**
- Consumes: hot-query list from Task 3.
- Produces: `yfh_audit` database with full schema; `SHOW CREATE TABLE` captures that Task 5's seeder is built from.

- [ ] **Step 1: Create the dedicated local audit database and migrate**

```bash
mysql -e "CREATE DATABASE IF NOT EXISTS yfh_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
cd backend && DB_DATABASE=yfh_audit php artisan migrate --force
```

Expected: all 131 migrations run green against `yfh_audit`. Env var override only — no `.env` or config file edits. If migrations require other env overrides, pass them the same way and record them in `evidence/environment.md`.

- [ ] **Step 2: Capture the schema**

```bash
mysqldump --no-data --skip-comments yfh_audit > "$SCRATCHPAD/schema.sql"
mysql yfh_audit -e "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA='yfh_audit' ORDER BY TABLE_NAME" 
mysql yfh_audit -e "SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='yfh_audit' GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE ORDER BY TABLE_NAME" > "$SCRATCHPAD/index-inventory.txt"
```

- [ ] **Step 3: Write the schema-model and index-inventory sections of `03-database-plan.md`**

Sections: table catalog (grouped: workflow runtime / history & audit / files / notifications / auth & governance / reference), data-type and key observations (JSON columns, soft deletes, polymorphics, pivot tables), growth classification per table (static / slow / grows-forever), full index inventory table, and a cross-check matrix: hot queries (from Task 3) × existing indexes → gaps and suspected-unused/duplicate indexes. Schema-level findings (missing FK, index-order suspicion, unbounded-growth table without archival) filed as DB-xxx — evidence status `Partially Verified` until Task 6 plans confirm.

- [ ] **Step 4: No checkpoint yet**

Block 3 checkpoints once, after Task 6. Continue.

---

### Task 5: Block 3b — Local seeder harness and progressive seeding

**Files:**
- Create (scratchpad, NEVER committed): `$SCRATCHPAD/audit-harness/seed.php`, `$SCRATCHPAD/audit-harness/distributions.php`
- Modify: `docs/audit/evidence/dataset-profile.md`, `docs/audit/evidence/environment.md`

**Interfaces:**
- Consumes: `schema.sql` captures from Task 4.
- Produces: seeded `yfh_audit` at recorded volume; dataset profile document Task 6's plans cite.

- [ ] **Step 1: Build the seeder harness in the scratchpad**

Structure (columns taken from Task 4's `schema.sql`, not guessed):

```php
<?php
// $SCRATCHPAD/audit-harness/seed.php — run via:
// cd backend && DB_DATABASE=yfh_audit php artisan tinker --execute="require '<scratchpad>/audit-harness/seed.php';"
// Chunked raw inserts (DB::table()->insert of 1000-row chunks), NOT Eloquent
// factories, so 1M rows stays tractable. Distributions per spec:
//   - banks: Zipf-like skew (top bank ~30% of requests, long tail)
//   - statuses: weighted mix across the canonical enum, terminal-heavy for old rows
//   - created_at: recency skew (60% of rows in last 90 days)
//   - workflow_history/audit_logs: 5-50 rows per request, heavy chains for 5% of requests
//   - nullable relations: populated at realistic rates (e.g. swift doc only past WAITING_FOR_SWIFT)
// Deterministic: mt_srand(42) so reruns give identical distributions.
```

Implement `distributions.php` helpers (`pickBankSkewed()`, `pickStatusWeighted()`, `skewedTimestamp()`) and per-table insert loops for `engine_requests`, `workflow_history`, `audit_logs`, `notifications`, plus the minimum parent rows (banks/organizations, users, workflow definitions) needed for FK validity. Honor every FK and NOT NULL constraint from `schema.sql`.

- [ ] **Step 2: Seed progressively — 100k first**

```bash
cd backend && DB_DATABASE=yfh_audit php artisan tinker --execute="\$GLOBALS['TARGET']=100000; require '$SCRATCHPAD/audit-harness/seed.php';"
mysql yfh_audit -e "SELECT COUNT(*) FROM engine_requests; SELECT COUNT(*) FROM workflow_history; SELECT COUNT(*) FROM audit_logs"
```

Expected: counts match target ratios; note wall-clock time and disk usage (`du -sh` on MySQL data dir if accessible, else `information_schema.TABLES` data_length).

- [ ] **Step 3: Scale to 500k, then 1M+**

Repeat with `TARGET=500000` then `TARGET=1000000` (fresh `DROP DATABASE`/re-migrate/re-seed each time, or additive — pick one and record it). **Fallback rule (spec):** if hardware cannot support the volume, stop at the maximum achieved size, record the limiting resource (disk/RAM/time), and state in `dataset-profile.md` how this weakens the evidence. Never present smaller-dataset results as design-target proof.

- [ ] **Step 4: Record the dataset profile**

Fill `evidence/dataset-profile.md`: final row counts per table, distribution parameters actually used (bank skew, status weights, recency split, history-chain distribution), seed determinism note (seed=42), seeding duration, storage size. Update `environment.md` with any env-var overrides used.

---

### Task 6: Block 3c — SQL capture, EXPLAIN evidence, and database plan

**Files:**
- Create: `docs/audit/evidence/queries/DB-xxx-*.sql`, `docs/audit/evidence/explain/DB-xxx-*.txt` (multiple files per finding allowed, ID-prefixed)
- Modify: `docs/audit/03-database-plan.md`, `docs/audit/02-findings.md`, `docs/audit/README.md`, `docs/audit/00-scope-and-method.md`

**Interfaces:**
- Consumes: seeded `yfh_audit`, hot-query list (Task 3), index inventory (Task 4).
- Produces: verified DB-xxx findings and index/migration proposals consumed by Task 8's roadmap.

- [ ] **Step 1: Capture application-generated SQL for the hot set**

Scratchpad capture script using `DB::listen`:

```php
<?php
// $SCRATCHPAD/audit-harness/capture.php — run via tinker with DB_DATABASE=yfh_audit
DB::listen(function ($q) { file_put_contents($GLOBALS['CAPTURE_FILE'], $q->sql . ";\n-- bindings(synthetic): " . json_encode($q->bindings) . "\n-- time: {$q->time}ms\n\n", FILE_APPEND); });
// Then invoke the actual service/query path per hot item, e.g.:
// $GLOBALS['CAPTURE_FILE'] = '/tmp/DB-001-bank-queue.sql';
// app(\App\Services\...::class)->...(...);  // exact call per hot-query worksheet
```

For each hot query: drive the real code path (service call or HTTP request against a locally running app with `DB_DATABASE=yfh_audit`) as a seeded user of the right role, capture SQL, replace binding values with `?` placeholders, and save the sanitized file to `docs/audit/evidence/queries/DB-xxx-<slug>.sql`. Synthetic bindings kept only when needed to reproduce the plan.

- [ ] **Step 2: EXPLAIN the safe reads**

```bash
mysql yfh_audit -e "EXPLAIN FORMAT=TREE <captured query with synthetic bindings>" > docs/audit/evidence/explain/DB-001-bank-queue.txt
mysql yfh_audit -e "EXPLAIN ANALYZE <same>" >> docs/audit/evidence/explain/DB-001-bank-queue.txt
```

Read-only queries only. Record examined rows, chosen index, filesort/temp-table, join order. Where a proposed index would change the plan, create it **inside a scratch copy** (`CREATE INDEX` on `yfh_audit` is acceptable — disposable audit DB), re-run EXPLAIN, save as `-after.txt`, then `DROP INDEX` to keep the DB canonical between findings.

- [ ] **Step 3: Assess locking and write paths (no EXPLAIN ANALYZE)**

For `EngineTransitionService::execute()` and vote/claim paths: transaction-boundary inspection (what's inside `DB::transaction`), generated-SQL review via the capture harness inside a rolled-back transaction, lock-order analysis (which rows/indexes `lockForUpdate` touches at 1M rows — plain `EXPLAIN` on the locking SELECT is safe), and safe concurrent reproduction: two tinker sessions against `yfh_audit` attempting conflicting transitions, observing wait/deadlock behavior. Document contention and deadlock findings with evidence.

- [ ] **Step 4: Complete `docs/audit/03-database-plan.md`**

Add: query-by-query analysis (each linking its `evidence/queries/` + `evidence/explain/` files), index proposals (table, columns, order, benefiting endpoint, expected benefit from before/after plans, write-cost trade-off, suggested migration **and rollback**), archival/retention plan for grows-forever tables (tier-tagged), count-query strategy, connection/transaction-length observations. Update DB-xxx findings to `Verified` where plans confirm; mark `Superseded` with explanation where disproved.

- [ ] **Step 5: CHECKPOINT 3 — present, wait, revise, then commit**

Include in the summary: achieved dataset size vs 1M+ goal and any fallback weakening. Commit after approval:

```bash
git add docs/audit/
git commit -m "docs(docs): add database plans and query evidence"
```

---

### Task 7: Block 4 — Frontend consumption, caching, queues

**Files:**
- Create: `docs/audit/05-frontend-caching-queues.md`
- Modify: `docs/audit/02-findings.md` (FE/CACHE/QUEUE findings), `docs/audit/README.md`, `docs/audit/00-scope-and-method.md`

**Interfaces:**
- Consumes: API-xxx findings (boundary rule: oversized response = Block 2 finding; re-requesting/inefficient storing of it = Block 4 finding — cross-reference, don't duplicate).
- Produces: caching strategy + queue plan consumed by Task 8's roadmap.

- [ ] **Step 1: Frontend data-fetch inventory**

```bash
cd frontend && grep -rn --include='*.ts' --include='*.vue' -E 'useFetch|useAsyncData|\$fetch|useApi' app/ | wc -l
```

Then per page/composable (35 pages): duplicate calls on mount, watchers triggering refetch, missing debounce on search inputs, missing `AbortController`/cancellation, claim-heartbeat polling implementation (60s ping — verify interval cleanup on unmount), reference-data fetched per page vs cached in Pinia, client-side filtering/sorting over server-pageable lists, table rendering of large arrays, bundle heft (`pnpm build` output sizes if quick — otherwise skip and note).

- [ ] **Step 2: Backend caching audit**

Inventory every `Cache::`/`cache(` call in `backend/`. For each: key structure, TTL, invalidation trigger, user/org/role/permission isolation (permission-leak check), stampede exposure (missing `Cache::lock`/`remember` with lock), Redis-down fallback behavior (`CACHE_STORE` fallback). Then the strategy: what may be cached (reference data, workflow definitions), what must never be (queue contents, permission-dependent lists — unless key includes full isolation), tag-based invalidation where the store supports it.

- [ ] **Step 3: Queue audit**

Inventory jobs (`backend/app/Jobs`), queued listeners/notifications, and everything dispatched synchronously in the HTTP path that shouldn't be. Per job: retry/`tries`, `backoff`, `timeout`, idempotency, uniqueness, `failed()` handling, `afterCommit` usage relative to transactions and audit-log ordering. Queue topology recommendation (separation by workload, priority) tier-tagged; check `config/queue.php` and Horizon presence (read-only).

- [ ] **Step 4: Write `docs/audit/05-frontend-caching-queues.md` + findings**

Three sections mirroring steps 1–3. Every cache recommendation states its isolation model (user/org/role/permission) explicitly; every queue recommendation states transactional/audit-ordering preservation. FE-xxx / CACHE-xxx / QUEUE-xxx findings into `02-findings.md`, cross-referencing related API-xxx IDs.

- [ ] **Step 5: CHECKPOINT 4 — present, wait, revise, then commit**

```bash
git add docs/audit/
git commit -m "docs(docs): document frontend caching and queue findings"
```

---

### Task 8: Block 5 — Security-correctness gate, observability, load-test plan

**Files:**
- Create: `docs/audit/06-security-observability.md`, `docs/audit/08-load-test-plan.md`
- Modify: `docs/audit/02-findings.md` (SEC/OBS findings + gate columns on existing findings), `docs/audit/README.md`, `docs/audit/00-scope-and-method.md`

**Interfaces:**
- Consumes: every finding and recommendation from Blocks 1–4.
- Produces: gate verdicts (per-recommendation security confirmation) required before Task 9 may compile the roadmap.

- [ ] **Step 1: Run the consolidated security-correctness gate**

For **every** recommendation in `02-findings.md`: verify and record that it preserves (a) bank/org scoping at query level, (b) policy/permission behavior, (c) transaction boundaries and locking correctness, (d) audit-log completeness and ordering, (e) idempotency where relevant. Add a "Gate" column/annotation: `Pass` / `Pass with condition (stated)` / `Fail → finding revised`. Cross-cutting checks were made in-block; this step verifies rather than discovers. Any gate failure that reveals a live security defect in current code → report to user immediately (standing exception).

- [ ] **Step 2: Standalone security review items**

User-supplied sort/filter field handling (whitelist vs raw column pass-through — SQL-injection surface), mass assignment on audit-relevant models, enumeration risks on ID-addressed endpoints, race conditions on double-submit (idempotency keys), rate-limit coverage vs the security baseline (5/min login, lockout). File SEC-xxx findings.

- [ ] **Step 3: Observability audit and targets**

Current state: logging config, slow-query log status, any Telescope/Pulse/Horizon presence (read composer.json — do not install). Recommendations sized to this project, tier-tagged. Define measurable targets table: per endpoint class (list, detail, transition, dashboard) — p95 first-page latency bound, max queries/request, bounded response size, queue-latency bounds. Numbers are **targets to validate against**, not claimed results.

- [ ] **Step 4: Write `docs/audit/08-load-test-plan.md`**

Plan only (never executed here): scenarios (queue browsing at 1M rows, concurrent transitions on one request, voting-session close under contention, dashboard load, export burst, Redis outage, queue burst), dataset sizes per scenario (reusing the seeder profile), concurrent users, target RPS, success criteria tied to Step 3's targets, metrics to collect, failure thresholds, tooling suggestion (e.g., k6) with trade-offs.

- [ ] **Step 5: CHECKPOINT 5 — present, wait, revise, then commit**

```bash
git add docs/audit/
git commit -m "docs(docs): add security observability and load-test plans"
```

---

### Task 9: Block 6 — Compile roadmap and executive summary

**Files:**
- Create: `docs/audit/07-roadmap.md`
- Modify: `docs/audit/README.md` (executive summary + final status), `docs/audit/02-findings.md` (final statuses), `docs/audit/00-scope-and-method.md` (final approval row)

**Interfaces:**
- Consumes: everything.
- Produces: the final deliverable set matching the original audit prompt's 11 required sections.

- [ ] **Step 1: Write `docs/audit/07-roadmap.md`**

Sections: quick wins (low-risk, immediate); high-priority improvements (failure/timeout/memory/overload risks at design target); architecture improvement plan (structural changes with justification, or explicit "none needed"); phased roadmap **A–F** (A measurement & safety, B critical fixes, C database & API optimization, D caching & queues, E scalability, F load testing & validation) — each phase with tasks (finding IDs), dependencies, risks, expected benefit, validation method; every item tagged `Pre-production` / `Threshold-gated (threshold stated)` / `Optional`; before/after examples for the top findings (existing code excerpt vs improved version, plan-shape delta from the captured before/after EXPLAINs); verification checklist (a measurable check per optimization).

- [ ] **Step 2: Write the executive summary into `README.md`**

Current system health, main performance risks, main scalability risks, most urgent improvements, overall architecture assessment — each referencing finding IDs. Update the document status table to Complete. State prominently: pre-production audit, all dynamic evidence local-synthetic.

- [ ] **Step 3: Seeder keep/discard recommendation**

Add to `07-roadmap.md`: recommendation on converting the scratchpad harness into a permanent asset (`tests/Performance`, `tools/performance`, non-production seed command, or default-off CI profile) judged against the spec's criteria (reusable, deterministic, documented, isolated, production-protected) — or discard.

- [ ] **Step 4: Final coverage check against the original prompt**

Verify all 11 required deliverable sections exist and every prompt phase (1–11) maps to a document section. Verify every finding has all lifecycle fields and evidence links resolve. Fix gaps.

- [ ] **Step 5: FINAL CHECKPOINT — present, wait, revise, then commit**

```bash
git add docs/audit/
git commit -m "docs(docs): compile final roadmap and executive summary"
```

Then clean up: `mysql -e "DROP DATABASE yfh_audit"` (offer to keep it if the user wants to continue experimenting), confirm scratchpad harness was never staged, run `graphify update .` (local only, never staged).
