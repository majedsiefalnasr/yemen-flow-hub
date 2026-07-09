# 02 — Consolidated Findings

All findings carry the lifecycle fields defined in `00-scope-and-method.md`. IDs are stable across blocks. Severity is rated against the design target (millions of rows, hundreds of concurrent users). Evidence `file:line` is at baseline SHA `be652fdd`.

Blocks are additive: Block 1 seeds architecture (`ARCH-`) and any security defect found during discovery (`SEC-`). API/DB/FE/CACHE/QUEUE/OBS findings arrive in later blocks. Database findings are `Partially Verified` until Block 3 captures plans.

## Summary counts (through Block 5 — final)

| Severity | Count | IDs |
| --- | --- | --- |
| Critical | 1 | SEC-001 — **fixed** in Block 1 (commit `375fe5f2`) |
| High | 8 | ARCH-001, ARCH-002, API-001, API-002, API-003, DB-001, DB-002, OBS-001 |
| Medium | 12 | ARCH-003, ARCH-004, ARCH-006, ARCH-007, API-004, API-005, API-006, FE-001, CACHE-001, QUEUE-001, SEC-002, OBS-002 |
| Low | 8 | ARCH-005, API-007, DB-003, FE-002, FE-003, QUEUE-002, QUEUE-003, SEC-003 |

_Total: 29 findings (1 Critical fixed). Block 5 added SEC-002/003, OBS-001/002 and ran the consolidated security gate: every recommendation preserves scoping/authz/transactions/audit if its stated condition holds (CACHE-001 scope-key and QUEUE-001 fail-closed are the two conditions to watch)._

---

## SEC-001 — Unauthenticated route exposes all users' names, emails, roles, and banks

| Field | Value |
| --- | --- |
| Area / component | `backend/routes/web.php`, `app/Http/Controllers/TestApiController.php`, `resources/views/test_api.blade.php` |
| Endpoint / query | `GET /test-api` (web route, **no auth, no environment guard**) |
| Current behavior | Renders every `is_active` user with `name`, `email`, role, and bank name/code (`TestApiController.php:11-25`). Route registered unconditionally (`web.php:10`), no `auth`, no `environment()` gate. |
| Problem | Unauthenticated PII/enumeration endpoint: anyone who can reach the app lists the full staff directory (emails, roles, bank assignments) — directly useful for phishing and targeted credential attacks against a Central Bank platform. |
| Severity | **Critical** |
| Evidence status | Verified |
| Finding status | **Accepted — fixed** (commit `375fe5f2`) |
| Roadmap tier | Pre-production (resolved before any further work) |
| First identified / last reviewed | Block 1 / Block 1 |
| Related findings | — |
| Evidence | `routes/web.php:10`, `app/Http/Controllers/TestApiController.php:11-25` (at baseline SHA, now removed) |
| Confidence | High |
| Recommendation | **Applied:** deleted the route, `TestApiController`, and `test_api.blade.php`. The authenticated API already covers legitimate user listing. |
| Security gate | Removal restores the invariant that user data requires authentication. |

> **Reported immediately** at the Block 1 checkpoint under the standing critical-security exception. Fixed under user authorization in commit `375fe5f2` (`fix(backend): remove unauthenticated test-api endpoint`) — the only application-code change in this audit, explicitly approved.

---

## ARCH-001 — Full `stage_permissions` table loaded into PHP on every engine list/queue/graph request

| Field | Value |
| --- | --- |
| Area / component | `app/Services/Workflow/StagePermissionResolver.php` |
| Endpoint / query | `accessibleStageIds()` — used by `engine-requests` index, `my-queue`, `graph`, and `/auth/me` overlay |
| Current behavior | `StagePermission::query()->get()->groupBy('stage_id')->filter(...)` loads **the entire `stage_permissions` table** into memory and evaluates matches in PHP (`:46-57`), plus two per-request identity subqueries for the user's active teams and roles (`:115-116`). Runs once per list/queue/graph call. |
| Problem | Cost scales with total permission rows across all workflows, not with the user's scope. As published workflows and stage permissions accumulate, every hot list request pulls the whole table and filters in PHP — memory and CPU grow with global config size, unbounded by pagination. |
| Severity | High |
| Evidence status | Verified (code); DB cost profile confirmed in Block 3 |
| Finding status | **Fixed** (SQL-ified on `perf/arch-001-sql-permission-resolution`; parity tests green) |
| Roadmap tier | Threshold-gated (when `stage_permissions` row count × request rate makes the full-table load material; quantify in Block 3) |
| First identified / last reviewed | Block 1 / Post-audit fix |
| Related findings | ARCH-002, and Block 2 API list findings |
| Evidence | `StagePermissionResolver.php:46-57,111-119`; fix + plan: `evidence/explain/ARCH-001-accessible-stage-ids.txt`; parity: `tests/Feature/Workflow/AccessibleStageIdsParityTest.php` |
| Confidence | High |
| Recommendation | Push matching into SQL (a scoped `stage_id` query filtered by the user's org/team/role/user identity via `whereIn`/wildcard-aware `where`), returning only accessible `stage_id`s; cache the resolved set per user identity for the request (or short TTL) since it is reused across index+resource serialization. Preserve exact match semantics (null=wildcard, AND-in-row, OR-across-rows) and EXECUTE⊃VIEW. Trade-off: SQL translation of wildcard semantics needs careful test parity with the current pure-PHP evaluator. |
| Security gate | Must preserve identity-set semantics exactly; verified in Block 5. |

---

## ARCH-002 — SLA-priority ordering embeds a correlated subquery inside ORDER BY

| Field | Value |
| --- | --- |
| Area / component | `app/Models/EngineRequest.php` |
| Endpoint / query | `GET /v1/engine-requests/my-queue` via `scopeOrderBySlaPriority` + `scopeWithStageEntry` |
| Current behavior | The queue orders by an SLA deadline expression that re-runs `(select max(created_at) from workflow_history where request_id = engine_requests.id and to_stage_id = engine_requests.current_stage_id)` and epoch arithmetic **inside ORDER BY** (`:163-184`); `scopeWithStageEntry` also adds the same correlated subselect as a column (`:141-157`). Applied to every row matching the scope before LIMIT. |
| Problem | Correlated subquery per candidate row + arithmetic in ORDER BY is unindexable as written; MySQL must evaluate it for the whole scoped set, then sort (filesort), before pagination. At millions of `engine_requests`/`workflow_history` rows this dominates queue latency. |
| Severity | High |
| Evidence status | **Verified** (plan + before/after timing, Block 3) |
| Finding status | Revised (evidence upgraded) |
| Roadmap tier | Pre-production (DB-001 index) + Threshold-gated (projection-column sort fix) |
| First identified / last reviewed | Block 1 / Block 3 |
| Related findings | ARCH-001, DB-001, API-002, API-006, FE queue polling (Block 4) |
| Evidence | `EngineRequest.php:141-184`; `evidence/explain/ARCH-002-my-queue-sla.txt` (96,186-row sort before LIMIT; ~2.6 s), `...-after-index.txt` (2.6 s → 0.7 s with DB-001) |
| Confidence | High |
| Recommendation | Candidate directions (evidence-select in Block 3): (a) maintain a `stage_entered_at` projection column on `engine_requests`, updated on transition (the projection-sync pattern already exists in `RequestProjectionSync`), so SLA ordering/filtering hits an indexed column instead of a correlated subquery; (b) covering index on `workflow_history (request_id, to_stage_id, created_at)` to make the subquery seek-bounded. Prefer (a) if Block 3 shows the subquery cost is the bottleneck. Trade-off: (a) adds a projection column + backfill migration and write-path maintenance. |
| Security gate | Ordering change only; scoping unaffected. Verified in Block 5. |

---

## API-000 — Reference allocator breaks at ~1,000,000 requests/year → **Superseded by API-003**

> **Superseded** in Block 2 by the stable-ID record **API-003** (identical finding, renumbered into the API series). Retained here for traceability; see API-003 below for the live record.


| Field | Value |
| --- | --- |
| Area / component | `app/Services/Workflow/EngineRequestService.php` |
| Endpoint / query | `POST /v1/engine-requests` → `createWithUniqueReference()` |
| Current behavior | Sequence derived from `MAX(reference)` where `reference` = `ENG-YYYY-%06d` string (`:117-144`). Lexicographic MAX over a 6-zero-padded numeric suffix. |
| Problem | Two defects at scale: (1) once the yearly sequence exceeds 6 digits (`ENG-2026-1000000`), string MAX compares `"1000000"` < `"999999"`, so `MAX` returns the wrong (lower) row and the +attempt retry cannot escape → permanent `REFERENCE_ALLOCATION_FAILED` (500) for every new request that year. (2) Every create recomputes `MAX` and races other creators, resolved only by unique-constraint retry — serialization contention under concurrent load. |
| Severity | High |
| Evidence status | Verified (logic); scale threshold arithmetic-confirmed, contention profile in Block 3 |
| Finding status | Open |
| Roadmap tier | Threshold-gated (annual request volume approaching 10^6) — but the contention aspect is Pre-production-worth reviewing |
| First identified / last reviewed | Block 1 / Block 1 (renumbered to `API-xxx` in Block 2) |
| Related findings | Block 2 create-path findings, Block 3 locking |
| Evidence | `EngineRequestService.php:117-144` |
| Confidence | High |
| Recommendation | Replace lexicographic MAX with a monotonic allocator: a dedicated per-year sequence table/row incremented under the transaction, or `MAX(CAST(SUBSTRING(reference,10) AS UNSIGNED))`, or zero-pad wide enough and cast. Removes both the digit-overflow correctness bug and the recompute-per-create race. Trade-off: sequence table adds one row of contention but is deterministic; casting keeps schema unchanged but still recomputes MAX. Decide with Block 3 evidence. |
| Security gate | Reference uniqueness/scoping preserved. Verified in Block 5. |

---

## ARCH-003 — Default rate limiting absent on 198 authenticated API routes

| Field | Value |
| --- | --- |
| Area / component | `bootstrap/app.php` middleware config; `routes/api.php` |
| Endpoint / query | All `auth:sanctum`+`active` routes except the ~20 with explicit `throttle:*` |
| Current behavior | `ThrottleRequests` is applied only to specific auth/document/settings routes (login 5/1, OTP 10/1, etc.). The 198 authenticated routes (lists, reports, dashboard, transitions) have **no throttle** (`route:list` middleware inventory). |
| Problem | A single authenticated client can drive unbounded request volume against the most expensive endpoints (reports, dashboard aggregates, unindexed searches), amplifying every other performance finding into an availability risk. |
| Severity | Medium |
| Evidence status | Verified |
| Finding status | Open |
| Roadmap tier | Pre-production (add a sane default group throttle) |
| First identified / last reviewed | Block 1 / Block 1 |
| Related findings | ARCH-001, ARCH-002, report/dashboard findings (Block 2) |
| Evidence | route:list; `bootstrap/app.php:43-62` |
| Confidence | High |
| Recommendation | Apply a default authenticated throttle (e.g. `throttle:120,1` per user) on the `v1` and unversioned authenticated groups, with tighter per-route limits on report/export/aggregate endpoints. Trade-off: must be sized above legitimate dashboard/queue polling to avoid false positives (coordinate with Block 4 polling cadence). |
| Security gate | Additive control; no scoping change. Verified in Block 5. |

---

## ARCH-004 — Leading-wildcard search and function-wrapped date filter cannot use indexes

| Field | Value |
| --- | --- |
| Area / component | `app/Support/EngineRequestListQuery.php` |
| Endpoint / query | `GET /v1/engine-requests?search=&created_from=&created_to=` |
| Current behavior | Search uses `LIKE '%term%'` on `reference` and `invoice_number` (`:47-52`); date filters use `whereDate('created_at', ...)` (`:44-46`), wrapping the column in a function. |
| Problem | Leading `%` forces a full scan (no index seek); `whereDate()` applies `DATE()` to the column, defeating any index on `created_at`. Combined with offset pagination + COUNT over millions of rows, list latency degrades sharply. |
| Severity | Medium |
| Evidence status | **Verified** (plan + before/after timing, Block 3) |
| Finding status | Revised (evidence upgraded) |
| Roadmap tier | Pre-production (DB-002 index + range-date code fix); prefix/FULLTEXT search Threshold-gated |
| First identified / last reviewed | Block 1 / Block 3 |
| Related findings | ARCH-002, DB-002, API-007, Block 2 pagination findings |
| Evidence | `EngineRequestListQuery.php:42-53`; `evidence/explain/ARCH-004-list-search-offset.txt` (492,814-row filter+sort; ~0.3 s), `...-after-composite.txt` (→ covering scan, ~0.08 s) |
| Confidence | High |
| Recommendation | Replace `whereDate` with half-open range predicates (`created_at >= ? AND created_at < ?`) to use an index; for search, prefer prefix `LIKE 'term%'` on `reference` where UX allows, or a normalized/`FULLTEXT` approach for `invoice_number` (already has `invoice_number_normalized`). Decide index set in Block 3 against real plans. Trade-off: prefix search changes UX (no infix match) — confirm with product; FULLTEXT adds an index maintenance cost. |
| Security gate | Filter behavior only; scoping preserved. Verified in Block 5. |

---

## ARCH-005 — Two PDF engines and two API-namespace conventions coexist

| Field | Value |
| --- | --- |
| Area / component | `composer.json`; `routes/api.php`; `app/Http/Controllers/Api/*` |
| Endpoint / query | N/A (structural) |
| Current behavior | `barryvdh/laravel-dompdf` **and** `mpdf/mpdf` are both required. API routes split between `Api\V1\*` (`/api/v1`) and unversioned `Api\*` (`/api/profile`, `/api/dashboard`, `/api/search`, `/api/admin`, `/api/auth`). |
| Problem | Maintainability, not runtime performance: two PDF libraries double the dependency/security surface for one capability; two routing conventions complicate versioning, throttling defaults, and client expectations. |
| Severity | Low |
| Evidence status | Verified (dependency + route inventory); PDF usage split confirmed in Block 2 |
| Finding status | Open |
| Roadmap tier | Optional |
| First identified / last reviewed | Block 1 / Block 1 |
| Related findings | ARCH-003 (throttle grouping benefits from namespace consistency) |
| Evidence | `composer.json` require; `routes/api.php:1-41,243-285` |
| Confidence | Medium (PDF usage split pending Block 2) |
| Recommendation | Confirm which PDF engine each generator uses (Block 2); consolidate to one if feasible. Namespace consolidation is a larger, optional refactor — record, don't force. Trade-off: consolidation touches many call sites for modest gain; justified only if one engine is unused. |
| Security gate | N/A. |

---

## ARCH-006 — `audit_logs` write amplification on authorization failures; unbounded audit/history growth

| Field | Value |
| --- | --- |
| Area / component | `bootstrap/app.php`, `app/Services/Audit/AuditService.php`, `audit_logs` / `workflow_history` tables |
| Endpoint / query | Every 403 authorization failure; every transition |
| Current behavior | Authorization failures write an `audit_logs` row, including from unauthenticated requests (`user_id: NULL`) (`bootstrap/app.php:64-122`). `audit_logs` and `workflow_history` are append-only with no retention/archival wired into the request path (an `AuditLogArchiveService` exists — scope verified in Block 3). |
| Problem | (1) A burst of forbidden/unauthenticated requests writes one audit row each — a write-amplification and table-growth vector under scanning/abuse. (2) Both tables grow forever; at design target they become the largest tables and slow every correlated read (ARCH-002) and audit query. |
| Severity | Medium |
| Evidence status | Partially Verified; growth/retention confirmed in Block 3 |
| Finding status | Open |
| Roadmap tier | Threshold-gated (row count / retention window) |
| First identified / last reviewed | Block 1 / Block 1 |
| Related findings | ARCH-002, ARCH-003, Block 3 archival plan |
| Evidence | `bootstrap/app.php:64-122`, `AuditService.php:31-46` |
| Confidence | Medium |
| Recommendation | Keep audit logging (required), but (a) ensure ARCH-003 throttling caps the authz-failure write rate, and (b) define a retention/archival policy for `audit_logs`/`workflow_history` (partition or move to `audit_log_archives` on a schedule) — Block 3 designs this with rollback. Do **not** drop audit rows for performance. Trade-off: archival adds a scheduled job + read-path awareness for historical queries. |
| Security gate | Auditability must be fully preserved (archival ≠ deletion). Verified in Block 5. |

---

## ARCH-007 — Synchronous FX-confirmation PDF generation inside the transition row lock

| Field | Value |
| --- | --- |
| Area / component | `app/Providers/AppServiceProvider.php`, `app/Services/Workflow/EngineTransitionService.php`, `CustomsFxPdfEffect` |
| Endpoint / query | `POST /v1/engine-requests/{id}/actions` landing on an `fx.confirmation_pdf` hook stage |
| Current behavior | Stage hooks fire **inside** the `DB::transaction` + `lockForUpdate` block (`EngineTransitionService.php:143-163`); `fx.confirmation_pdf` runs `CustomsFxPdfEffect` (PDF render via dompdf/mpdf) synchronously while the `engine_requests` row lock is held. |
| Problem | PDF rendering is CPU/time-heavy; holding the row lock (and a DB connection) across it lengthens the critical section, increasing lock-wait and connection-pool pressure under concurrent transitions on hot stages. AGENTS.md *requires* FX generation to be transactional/atomic, so this is a constrained trade-off, not a free async move. |
| Severity | Medium |
| Evidence status | Partially Verified; lock-hold duration measured in Block 3 |
| Finding status | Open |
| Roadmap tier | Threshold-gated (concurrent transition rate on FX stages) |
| First identified / last reviewed | Block 1 / Block 1 |
| Related findings | Block 3 locking analysis, Block 8 queue interaction |
| Evidence | `AppServiceProvider.php:65-84`, `EngineTransitionService.php:143-163` |
| Confidence | Medium |
| Recommendation | Preserve atomicity of the *state change + record creation*, but investigate rendering the PDF bytes **before** acquiring the lock (or storing a "pending" marker and generating via an after-commit job that cannot invalidate the transition), so the lock does not span PDF CPU time. Must keep the guarantee that a committed FX confirmation always has its document. Decide only after Block 3 quantifies the lock-hold cost. Trade-off: any async split must not create a committed-transition-without-document window. |
| Security gate | Atomicity + audit ordering must be preserved. Verified in Block 5. |

---

---

# API series (Block 2)

## API-001 — Per-row `fx_panel` authorization resolves two uncached queries per engine-request row

| Field | Value |
| --- | --- |
| Area / component | `app/Http/Resources/EngineRequestResource.php`, `app/Services/Customs/FxConfirmationAuthorizationService.php` |
| Endpoint / query | `GET /v1/engine-requests`, `.../my-queue`, `.../{id}` — `fx_panel` key |
| Current behavior | `EngineRequestResource::toArray` calls `FxConfirmationAuthorizationService::panelCapabilities($user, $request)` for **every** serialized row (`:131-135`); that method runs `WorkflowStage::query()` (`:50`) and `EngineRequest::query()` (`:67`) with no cross-row memoization. `can_execute` and `StageFieldOutputFilter` are memoized across rows, but `fx_panel` is not. |
| Problem | A page of 100 rows issues ~200 extra queries for the FX panel alone — a classic serialization N+1. Cost scales with page size on the hottest list endpoints. |
| Severity | High |
| Evidence status | Verified (code); query count confirmed in Block 3 capture |
| Finding status | Open |
| Roadmap tier | Pre-production (list endpoints are core; fix before go-live) |
| First identified / last reviewed | Block 2 / Block 2 |
| Related findings | ARCH-001, API-002 |
| Evidence | `EngineRequestResource.php:131-135`, `FxConfirmationAuthorizationService.php:50,67,148` |
| Confidence | High |
| Recommendation | Add a batch entry point to `FxConfirmationAuthorizationService` that resolves panel inputs once for the page's stage/request set (mirroring the existing `StageFieldOutputFilter` singleton-cache and `$canExecuteCache` patterns), then map per row. Preserve identical per-row capability output. Trade-off: new batch method + call-site change in `EngineRequestListQuery::paginatedResponse`. |
| Security gate | Per-row FX authorization output must be byte-identical; verified in Block 5. |

## API-002 — `engine-requests/stats` runs six aggregate passes, each reloading all stage permissions and the SLA subquery

| Field | Value |
| --- | --- |
| Area / component | `app/Services/Workflow/EngineRequestStatsService.php` |
| Endpoint / query | `GET /v1/engine-requests/stats` |
| Current behavior | `aggregate()` builds the scoped query then runs `total`, `active`, `breached_sla`, `nearing_sla`, `unclaimed_active`, and `by_status` as **separate** count/group passes (`:35-53`). Each `buildScopedQuery` calls `accessibleStageIds()` (whole `stage_permissions` table into PHP — ARCH-001) and `withStageEntry()` (correlated `workflow_history` subquery — ARCH-002). |
| Problem | One stats call = 6 full scans of the scoped `engine_requests` set plus repeated whole-table permission loads and correlated subqueries. At design target this is the most expensive dashboard call, multiplied by every polling client (Block 4). |
| Severity | High |
| Evidence status | Partially Verified (code); plans in Block 3 |
| Finding status | Open |
| Roadmap tier | Threshold-gated (row count × dashboard poll rate) |
| First identified / last reviewed | Block 2 / Block 2 |
| Related findings | ARCH-001, ARCH-002, API-005 |
| Evidence | `EngineRequestStatsService.php:30-82` |
| Confidence | High |
| Recommendation | Collapse `total`/`active`/`unclaimed_active`/`by_status` into a single grouped pass (`SELECT status, COUNT(*), SUM(claimed_by IS NULL...) GROUP BY status`); compute SLA breached/nearing in one grouped pass over the SLA expression instead of two filtered counts. Depends on ARCH-001 fix to remove the per-pass permission load. Trade-off: one more complex query replacing six simple ones — validate plan in Block 3. |
| Security gate | Scoping (`forUser` + accessible stages) preserved on the merged query; verified in Block 5. |

## API-003 — Request reference allocator overflows and races (carried from Block 1 API-000)

| Field | Value |
| --- | --- |
| Area / component | `app/Services/Workflow/EngineRequestService.php` |
| Endpoint / query | `POST /v1/engine-requests` → `createWithUniqueReference()` |
| Current behavior | Sequence from lexicographic `MAX(reference)` over `ENG-YYYY-%06d` (`:117-144`), recomputed per create, races resolved by unique-constraint retry. |
| Problem | (1) At `ENG-YYYY-1000000` string MAX mis-orders 7-digit vs 6-digit suffixes → permanent `REFERENCE_ALLOCATION_FAILED` for the year. (2) Per-create `MAX` recompute + retry = serialization contention under concurrency. |
| Severity | High |
| Evidence status | Verified (logic) |
| Finding status | Open (renamed from API-000) |
| Roadmap tier | Threshold-gated (≈10^6 requests/year); contention review Pre-production |
| First identified / last reviewed | Block 1 / Block 2 |
| Related findings | Block 3 locking |
| Evidence | `EngineRequestService.php:117-144` |
| Confidence | High |
| Recommendation | Monotonic allocator: per-year sequence row incremented in-transaction, or `MAX(CAST(SUBSTRING(reference,10) AS UNSIGNED))`. Fixes overflow + reduces contention. Trade-off in full record (Block 1). |
| Security gate | Reference uniqueness preserved; verified in Block 5. |

## API-004 — Synchronous audit-log CSV export in the request lifecycle

| Field | Value |
| --- | --- |
| Area / component | `app/Http/Controllers/Api/V1/AuditLogController.php` |
| Endpoint / query | `GET /v1/audit-logs/export` |
| Current behavior | Loads up to 10,000 audit rows with relations via `->get()` and builds the CSV string in PHP within the HTTP request (`:106-131`). |
| Problem | 10k rows × relations held in memory + string concat blocks a web worker; concurrent exports multiply memory pressure. Bounded (10k cap) so not catastrophic, but wrong lifecycle for a growing table. |
| Severity | Medium |
| Evidence status | Verified |
| Finding status | Open |
| Roadmap tier | Threshold-gated (audit table size / export frequency) |
| First identified / last reviewed | Block 2 / Block 2 |
| Related findings | API-006, ARCH-006, QUEUE (Block 4) |
| Evidence | `AuditLogController.php:82-132` |
| Confidence | High |
| Recommendation | Route through the existing `GenerateReportExport` job pattern (async artifact + download endpoint), streaming rows with `lazy()`/cursor instead of `get()`. Trade-off: client contract changes to poll-for-artifact (Block 4 coordinates). |
| Security gate | Scope + `viewAny` policy preserved in the job; export action still audit-logged. Verified in Block 5. |

## API-005 — `reports/summary` uses seven full-scan passes instead of one grouped query

| Field | Value |
| --- | --- |
| Area / component | `app/Http/Controllers/Api/V1/ReportController.php` |
| Endpoint / query | `GET /v1/reports/summary` |
| Current behavior | Runs `count()` six times (total + 5 statuses) plus `sum(amount)` — seven independent scans of the same scoped set (`:25-31`). |
| Problem | Seven full scans where one `GROUP BY status` + conditional aggregation suffices; cost multiplies over the largest table at design target. |
| Severity | Medium |
| Evidence status | Partially Verified (code); plan in Block 3 |
| Finding status | Open |
| Roadmap tier | Threshold-gated (row count) |
| First identified / last reviewed | Block 2 / Block 2 |
| Related findings | API-002 |
| Evidence | `ReportController.php:25-33` |
| Confidence | High |
| Recommendation | Single `selectRaw('COUNT(*) total, SUM(status="ACTIVE") active, ..., SUM(amount) total_amount')` grouped/aggregated in one pass. Trade-off: none material. |
| Security gate | `applyScope` preserved on the single query; verified in Block 5. |

## API-006 — Unbounded `->get()` on SLA / stage-duration / team-performance reports

| Field | Value |
| --- | --- |
| Area / component | `app/Http/Controllers/Api/V1/ReportController.php` |
| Endpoint / query | `GET /v1/reports/sla`, `.../stage-duration`, `.../team-performance` |
| Current behavior | `sla` loads **all** matching `engine_requests` via `->get()` then groups and derives `sla_status` per row in PHP (`:244-262`). `stage-duration` joins `workflow_history` to itself with a correlated MIN subquery and `->get()` (`:197-220`). `team-performance` joins history→users→roles and `->get()` (`:271-289`). None have a LIMIT. |
| Problem | These scan `engine_requests`/`workflow_history` (the two largest tables) without bound; `sla` additionally materializes the full result set into PHP. At millions of rows these are memory- and time-unbounded. |
| Severity | Medium |
| Evidence status | Partially Verified (code); plans in Block 3 |
| Finding status | Open |
| Roadmap tier | Threshold-gated (row count) — SLA report Pre-production-worth if it is a default dashboard widget (confirm in Block 4) |
| First identified / last reviewed | Block 2 / Block 2 |
| Related findings | ARCH-002, API-002, Block 3 index plan |
| Evidence | `ReportController.php:186-299` |
| Confidence | Medium-High |
| Recommendation | Push SLA-status bucketing into SQL (the epoch expressions already exist in `EngineRequestListQuery`) and return grouped counts, not row sets; ensure `stage-duration`'s self-join subquery is index-supported (Block 3); add date-window defaults so unfiltered calls cannot scan all history. Trade-off: SLA report must move derivation from PHP to SQL — test parity needed. |
| Security gate | `applyScope` preserved; verified in Block 5. |

## API-007 — Index-defeating filter predicates on audit and report queries

| Field | Value |
| --- | --- |
| Area / component | `app/Http/Controllers/Api/V1/AuditLogController.php`, `ReportController.php` |
| Endpoint / query | `GET /v1/audit-logs` (+ export), `reports/*` date filters |
| Current behavior | `whereDate('created_at', ...)` wraps the column in `DATE()` (`AuditLogController.php:40-41`, `ReportController.php:211-212,319-322`); audit entity filter uses `subject_type LIKE '%X%'` (`AuditLogController.php:38,99`). |
| Problem | Function-wrapped column and leading-wildcard LIKE cannot use an index → full scans on `audit_logs` (grows forever). Same root as ARCH-004 on `engine_requests`. |
| Severity | Low |
| Evidence status | Partially Verified; plans + index proposals in Block 3 |
| Finding status | Open |
| Roadmap tier | Threshold-gated (audit table size) |
| First identified / last reviewed | Block 2 / Block 2 |
| Related findings | ARCH-004, ARCH-006, Block 3 index plan |
| Evidence | `AuditLogController.php:38-41,99`, `ReportController.php:211-212,319-322` |
| Confidence | Medium-High |
| Recommendation | Half-open range bounds instead of `whereDate`; exact `subject_type = ?` (fully-qualified class) or a normalized entity column instead of infix LIKE. Consolidated into Block 3 index plan. Trade-off: entity filter UX must switch from substring to exact/known-type. |
| Security gate | Scope preserved; verified in Block 5. |

---

# DB series (Block 3) — index proposals

Full rationale, before/after plans, and migrations in `03-database-plan.md §4`. These are actionable proposals derived from captured evidence, tracked as findings for lifecycle.

## DB-001 — Missing `workflow_history (request_id, to_stage_id, created_at)` covering index

| Field | Value |
| --- | --- |
| Area / component | `workflow_history` table; SLA subquery in `EngineRequest` scopes |
| Endpoint / query | my-queue, `stats` SLA metrics, `reports/sla` |
| Current behavior | SLA correlated subquery hits `(request_id, created_at)` then filters `to_stage_id` post-lookup (per-row). |
| Problem | Per-row subquery cost across the my-queue sort set. |
| Severity | High |
| Evidence status | Verified |
| Finding status | Open |
| Roadmap tier | Pre-production (paired with ARCH-002) |
| First identified / last reviewed | Block 3 / Block 3 |
| Related findings | ARCH-002, API-002, API-006 |
| Evidence | `evidence/explain/ARCH-002-my-queue-sla.txt`, `...-after-index.txt` (2.6 s → 0.7 s; subquery cost 4.31 → 1.14) |
| Confidence | High |
| Recommendation | Add index `wh_req_tostage_created`; migration + rollback in `03-database-plan.md §4 DB-IDX-1`. Trade-off: one more index on a 5M-row append table (once-per-transition write). |
| Security gate | Index only; no scoping/behavior change. |

## DB-002 — Missing `engine_requests (bank_id, created_at, id)` composite index

| Field | Value |
| --- | --- |
| Area / component | `engine_requests` table; default list sort + report date ranges |
| Endpoint / query | `GET /v1/engine-requests`, report date filters |
| Current behavior | No composite for bank-scoped `created_at DESC` ordering → full filter+sort of the bank's rows. |
| Problem | 492k-row filter+sort per bank-scoped list page at target. |
| Severity | High |
| Evidence status | Verified |
| Finding status | Open |
| Roadmap tier | Pre-production (paired with ARCH-004 code fix) |
| First identified / last reviewed | Block 3 / Block 3 |
| Related findings | ARCH-004, API-007 |
| Evidence | `evidence/explain/ARCH-004-list-search-offset.txt`, `...-after-composite.txt` (0.3 s → 0.08 s; becomes covering range scan) |
| Confidence | High |
| Recommendation | Add index `er_bank_created` (`03-database-plan.md §4 DB-IDX-2`). **Only effective with** the ARCH-004/API-007 code change to stop wrapping `created_at` in `DATE()`. Trade-off: one composite on the 1M-row hot table; low churn (`created_at` set-once). |
| Security gate | Index only; scoping preserved by the query's existing `bank_id` predicate. |

## DB-003 — Conditional `audit_logs (subject_type, created_at)` index (gated on code fix)

| Field | Value |
| --- | --- |
| Area / component | `audit_logs` table |
| Endpoint / query | `GET /v1/audit-logs` (+ export) entity/date filters |
| Current behavior | `whereDate` + infix `subject_type LIKE '%…%'`; relies on reverse-PRIMARY early stop. |
| Problem | Degrades toward full reverse scan on selective/old filters over a 5M-row table. |
| Severity | Low |
| Evidence status | Partially Verified |
| Finding status | Open |
| Roadmap tier | Threshold-gated (audit table size + confirmed audit-filter workload) |
| First identified / last reviewed | Block 3 / Block 3 |
| Related findings | API-007, ARCH-006 |
| Evidence | `evidence/explain/API-007-audit-filter.txt` |
| Confidence | Medium |
| Recommendation | Only after switching to exact `subject_type = ?` + range dates (API-007); then add `al_subject_created` (`03-database-plan.md §4 DB-IDX-3`). Unused without the code fix. Trade-off: index on the largest append-only table. |
| Security gate | Index only; note the separate `audit_logs` no-`bank_id` scoping limitation → SEC series (Block 5). |

---

# Frontend / Caching / Queue series (Block 4)

Detailed plans in `05-frontend-caching-queues.md`. Compact records here; all carry the standard lifecycle fields.

## FE-001 — No request cancellation (no AbortController) in the API layer

| Field | Value |
| --- | --- |
| Area / component | `frontend/app/composables/useApi.ts` |
| Current behavior | `$fetch` wrapper exposes no `signal`; navigating away mid-request leaves it in flight. |
| Problem | Wasted bandwidth/connections on rapid navigation; the token-guard prevents stale state but not the redundant network work. Matters most on slow links + heavy list/detail loads. |
| Severity | Medium · Evidence Verified · Status Open · Confidence High |
| Roadmap tier | Threshold-gated (user count / connection budget) |
| First/last | Block 4 / Block 4 · Related: FE-003 |
| Recommendation | Thread an `AbortController` `signal` through `useApi` and abort on unmount/navigation for GET list/detail calls. Trade-off: must not abort mutations mid-flight. |
| Security gate | No scoping impact. |

## FE-002 — Notification store derives unread count by fetching the full list

| Field | Value |
| --- | --- |
| Area / component | `frontend/app/stores/notifications.store.ts` |
| Current behavior | `refreshUnreadCount()`/`fetchRecent()` fetch notifications page 1 and count in JS, duplicating the cheap `notifications/unread-count` endpoint (`useNotifications.ts:90`). |
| Problem | Over-fetch when a caller uses the store path instead of the dedicated endpoint. |
| Severity | Low · Evidence Verified · Status Open · Confidence Medium |
| Roadmap tier | Optional |
| First/last | Block 4 / Block 4 |
| Recommendation | Route all unread-count reads through `unread-count`; keep the list fetch only for rendering the list. |
| Security gate | No scoping impact. |

## FE-003 — Stable reference data refetched every use (no client cache)

| Field | Value |
| --- | --- |
| Area / component | `frontend/app/composables/useReferenceData.ts` and peers |
| Current behavior | Reference tables/values, workflow definitions, banks refetched on each call; no cross-call cache. |
| Problem | Repeated network calls for slow-changing lookup data. |
| Severity | Low · Evidence Verified · Status Open · Confidence Medium |
| Roadmap tier | Optional |
| First/last | Block 4 / Block 4 · Related: FE-001, CACHE-001 |
| Recommendation | Cache stable reference data in a Pinia store / `useState` with a modest TTL or explicit invalidation on admin edit. |
| Security gate | Reference data is non-sensitive/global; no isolation concern. |

## CACHE-001 — Dashboard & report aggregates recomputed per request, uncached

| Field | Value |
| --- | --- |
| Area / component | `app/Services/Dashboard/DashboardStatsService.php`, `app/Http/Controllers/Api/V1/ReportController.php` |
| Endpoint / query | `GET /api/dashboard/stats`, `GET /v1/reports/*` |
| Current behavior | Every call recomputes aggregates over the largest tables; no cache (Block 3: `reports/summary` ~0.96 s at 1M rows). |
| Problem | Read-heavy, slow-changing aggregates recomputed on every dashboard/report view; multiplied by concurrent users. |
| Severity | Medium · Evidence Verified (Block 3 timings) · Status Open · Confidence High |
| Roadmap tier | Threshold-gated (user count × table size) |
| First/last | Block 4 / Block 4 · Related: API-002, API-005, API-006 |
| Recommendation | Cache per (scope-key = org-classification + bank_id, filter-set), short TTL, `Cache::lock`-guarded regeneration, Redis-down → compute live. Pairs with the API-002/005 query fixes; does not replace them. **Never** per-raw-user or cross-bank shared. |
| Security gate | **Scope-key MUST encode org/bank** so cached aggregates never leak across banks — verified in Block 5. |

## QUEUE-001 — Document virus-scan job has no resilience or failure handling

| Field | Value |
| --- | --- |
| Area / component | `app/Jobs/ScanEngineRequestDocument.php` |
| Current behavior | No `tries`/`timeout`/`backoff`/`failed()`; a failed/stuck scan has no dead-letter path. |
| Problem | Security-relevant: an uploaded PDF whose scan fails silently could be treated as scanned/clean; no retry, no alert. |
| Severity | Medium · Evidence Verified · Status Open · Confidence High |
| Roadmap tier | Pre-production (security-relevant) |
| First/last | Block 4 / Block 4 · Related: QUEUE-002 |
| Recommendation | Add `$tries`, `$timeout`, `backoff`, and a `failed()` that marks the document scan **failed (fail-closed)** so it is not treated as clean; alert on failure. |
| Security gate | Preserves the PDF-only + scanned-before-trusted invariant; verified in Block 5. |

## QUEUE-002 — Notification & report-export jobs lack explicit tries/backoff/timeout

| Field | Value |
| --- | --- |
| Area / component | `app/Jobs/DispatchNotification.php`, `app/Jobs/GenerateReportExport.php` |
| Current behavior | Both have `failed()` but inherit worker defaults for tries/backoff/timeout. `GenerateReportExport` can be long-running. |
| Problem | No explicit timeout on a potentially long export; default retry semantics on notification fan-out. |
| Severity | Low · Evidence Verified · Status Open · Confidence High |
| Roadmap tier | Threshold-gated |
| First/last | Block 4 / Block 4 · Related: QUEUE-001, API-004 |
| Recommendation | Set explicit `$tries`/`$backoff`/`$timeout`; generous `$timeout` for exports (which also absorbs API-004's async export move). |
| Security gate | No scoping impact; export still policy-scoped. |

## QUEUE-003 — No queue separation beyond emails; no Horizon (no queue observability)

| Field | Value |
| --- | --- |
| Area / component | `config/queue.php`, `app/Jobs/*`, composer.json |
| Current behavior | Only `SendEmailDelivery` is on a separate `emails` queue; everything else on `default`. No Horizon installed. |
| Problem | Fan-out, exports, and scans compete on one queue; no depth/latency/failure-rate visibility. |
| Severity | Low · Evidence Verified · Status Open · Confidence High |
| Roadmap tier | Threshold-gated (Horizon Pre-production if queue volume expected at launch) |
| First/last | Block 4 / Block 4 · Related: OBS (Block 5) |
| Recommendation | Separate queues by workload (`notifications`, `exports`, `scans`, `emails`); add Horizon for monitoring (ties into Block 5 observability). |
| Security gate | No scoping impact. |

---

# Security / Observability series (Block 5)

Detail + the consolidated security gate in `06-security-observability.md`. Compact records here.

## SEC-002 — `audit_logs` cannot be bank-scoped (no `bank_id`); bank admins get no scoped audit access

| Field | Value |
| --- | --- |
| Area / component | `audit_logs` table; `app/Http/Controllers/Api/V1/AuditLogController.php` |
| Current behavior | No `bank_id` column, so audit queries can't be bank-scoped at the query level; `show()` denies **all** non-system-wide users (`:69-73`) as a workaround. |
| Problem | Scoping-model weakness + functional gap: bank admins get zero scoped audit visibility; audit reads can't be safely bank-filtered. |
| Severity | Medium · Evidence Verified · Status Open · Confidence High |
| Roadmap tier | Threshold-gated (when bank-scoped audit access is required) |
| First/last | Block 5 / Block 5 · Related: DB-003, ARCH-006 |
| Recommendation | Add `bank_id` to `audit_logs` (derived from `workflow_instance_id`/subject at write time), backfill, then enable scoped reads + a scoped index (DB-003). Migration + rollback + backfill in the roadmap. |
| Security gate | Enables correct scoping; must backfill without exposing cross-bank rows. |

## SEC-003 — Unauthenticated 403s write audit rows (audit-table write-amplification / cheap DoS)

| Field | Value |
| --- | --- |
| Area / component | `bootstrap/app.php:64-122`, `AuditService` |
| Current behavior | Every 403 authorization failure, including unauthenticated, writes an `audit_logs` row. |
| Problem | An unauthenticated scanner can drive unbounded audit writes → write-amplification against the largest table. |
| Severity | Low · Evidence Verified · Status Open · Confidence Medium |
| Roadmap tier | Pre-production (resolved by ARCH-003 default throttle) |
| First/last | Block 5 / Block 5 · Related: ARCH-003, ARCH-006 |
| Recommendation | The ARCH-003 default authenticated throttle + an unauthenticated-endpoint throttle caps the write rate. Keep logging genuine failures. |
| Security gate | Preserves audit completeness; only rate-limits abuse. |

## OBS-001 — No application performance observability

| Field | Value |
| --- | --- |
| Area / component | `composer.json`, `app/Providers`, `config/` |
| Current behavior | No Telescope/Pulse/Horizon/Sentry; no slow-query log, no per-request query-count/duration, no cache-hit/error-rate/response-size tracking. |
| Problem | The Block 3/4 hot paths and any regression would be invisible in production; no way to validate targets or catch N+1 reintroduction. |
| Severity | High · Evidence Verified · Status Open · Confidence High |
| Roadmap tier | Pre-production (measurement is Phase A of the roadmap) |
| First/last | Block 5 / Block 5 · Related: OBS-002, all perf findings |
| Recommendation | Enable MySQL slow-query log + `log_queries_not_using_indexes` (staging); add per-request query-count+duration logging; adopt **Laravel Pulse** (low-overhead, first-party). Telescope local/staging only. |
| Security gate | Telescope must never run in production (stores payloads). |

## OBS-002 — No queue monitoring (no Horizon)

| Field | Value |
| --- | --- |
| Area / component | `config/queue.php`, composer.json |
| Current behavior | No Horizon; queue depth/latency/failure-rate unobservable. |
| Problem | Queue backlogs, slow jobs, and failure spikes are invisible. |
| Severity | Medium · Evidence Verified · Status Open · Confidence High |
| Roadmap tier | Threshold-gated (Pre-production if queue volume expected at launch) |
| First/last | Block 5 / Block 5 · Related: QUEUE-003, OBS-001 |
| Recommendation | Add Horizon for Redis queue monitoring; pairs with QUEUE-003 queue separation. |
| Security gate | Horizon dashboard must be auth-gated. |

_All findings recorded. Block 6 compiles the roadmap and executive summary._
