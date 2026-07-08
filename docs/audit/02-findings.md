# 02 — Consolidated Findings

All findings carry the lifecycle fields defined in `00-scope-and-method.md`. IDs are stable across blocks. Severity is rated against the design target (millions of rows, hundreds of concurrent users). Evidence `file:line` is at baseline SHA `be652fdd`.

Blocks are additive: Block 1 seeds architecture (`ARCH-`) and any security defect found during discovery (`SEC-`). API/DB/FE/CACHE/QUEUE/OBS findings arrive in later blocks. Database findings are `Partially Verified` until Block 3 captures plans.

## Summary counts (through Block 1)

| Severity | Count | Notes |
| --- | --- | --- |
| Critical | 1 | SEC-001 — **fixed** in Block 1 (commit `375fe5f2`) |
| High | 3 | ARCH-001, ARCH-002, API-000 |
| Medium | 4 | ARCH-003, ARCH-004, ARCH-006, ARCH-007 |
| Low | 1 | ARCH-005 |

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
| Finding status | Open |
| Roadmap tier | Threshold-gated (when `stage_permissions` row count × request rate makes the full-table load material; quantify in Block 3) |
| First identified / last reviewed | Block 1 / Block 1 |
| Related findings | ARCH-002, and Block 2 API list findings |
| Evidence | `StagePermissionResolver.php:46-57,111-119` |
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
| Evidence status | Partially Verified (code); plan capture in Block 3 |
| Finding status | Open |
| Roadmap tier | Threshold-gated (queue result-set size; threshold set from Block 3 plans) |
| First identified / last reviewed | Block 1 / Block 1 |
| Related findings | ARCH-001, DB findings (Block 3), FE queue polling (Block 4) |
| Evidence | `EngineRequest.php:141-184`, `EngineRequestListQuery.php:95-124` |
| Confidence | Medium-High |
| Recommendation | Candidate directions (evidence-select in Block 3): (a) maintain a `stage_entered_at` projection column on `engine_requests`, updated on transition (the projection-sync pattern already exists in `RequestProjectionSync`), so SLA ordering/filtering hits an indexed column instead of a correlated subquery; (b) covering index on `workflow_history (request_id, to_stage_id, created_at)` to make the subquery seek-bounded. Prefer (a) if Block 3 shows the subquery cost is the bottleneck. Trade-off: (a) adds a projection column + backfill migration and write-path maintenance. |
| Security gate | Ordering change only; scoping unaffected. Verified in Block 5. |

---

## API-000 — Reference allocator breaks at ~1,000,000 requests/year (placeholder; confirmed in Block 2)

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
| Evidence status | Partially Verified (code); plans in Block 3 |
| Finding status | Open |
| Roadmap tier | Threshold-gated (row count; thresholds from Block 3) |
| First identified / last reviewed | Block 1 / Block 1 |
| Related findings | ARCH-002, Block 2 pagination findings, Block 3 index proposals |
| Evidence | `EngineRequestListQuery.php:42-53` |
| Confidence | Medium-High |
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

_No API/DB/FE/CACHE/QUEUE/OBS findings yet — those blocks follow. `API-000` will be renumbered to a stable `API-xxx` ID when Block 2 opens the API series._
