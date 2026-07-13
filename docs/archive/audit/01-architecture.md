# 01 — Current Architecture & Request Lifecycle

Block 1 deliverable. All `file:line` references are at baseline SHA `be652fdd` (see `00-scope-and-method.md`). Paths are repo-relative; backend paths omit the `backend/` prefix where unambiguous.

## 1. Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP ^8.2 (8.5.4 local), Laravel ^11.0 (11.51.0), Sanctum ^4.3, dompdf ^3.1 **and** mpdf ^8.3 (two PDF engines), l5-swagger, google2fa |
| Frontend | Nuxt ^4.4.6, Vue ^3.5.13, Pinia ^2.3.1, Tailwind ^4.1 |
| Data | MySQL 8.4 (Docker), Redis 7 (cache + queues), sessions in database (`SESSION_DRIVER=database`) |
| Queue | `QUEUE_CONNECTION=redis`, 4 job classes (`app/Jobs`) |

## 2. Request lifecycle

```
Nuxt page/composable
  → $fetch (Sanctum SPA: stateful cookie via EnsureFrontendRequestsAreStateful)
  → route (219 registered; 209 under /api — all from routes/api.php, verified vs route:list)
  → middleware: HandleCors (prepended) → api group → EnsureFrontendRequestsAreStateful
      → auth:sanctum (198 routes) → EnsureActiveUser (198 routes) → per-route throttle (~20 routes only)
  → controller (44, thin-to-medium) → Form Request / inline validate()
  → service layer (59 services) → Eloquent → MySQL
  → response: ApiResponse envelope or manual arrays; EngineRequestResource for engine rows
```

- Route model binding with `Route::pattern('engineRequest', '[0-9]+')` (`routes/api.php:44`).
- Exception→response mapping is centralized in `bootstrap/app.php:63-222`: a per-domain-exception render table producing `{success, message, error_code}` envelopes; catch-all `Throwable` → 500. Authorization failures additionally write an `audit_logs` row (`bootstrap/app.php:64-75`).
- Two API namespaces coexist: `Api\V1\*` (prefix `/api/v1/...`) and unversioned `Api\*` (`/api/profile`, `/api/settings`, `/api/dashboard/stats`, `/api/search`, `/api/admin/*`, `/api/auth/*`) — one platform, two routing conventions.

## 3. Authentication & authorization chain

| Layer | Mechanism | Evidence |
| --- | --- | --- |
| AuthN | Sanctum stateful SPA (cookie) + token auth; `auth:sanctum` alias | `bootstrap/app.php:54-61` |
| Active gate | `EnsureActiveUser` on all authenticated routes | route:list |
| Resource policies | 20 policies in `app/Policies` (EngineRequestPolicy, etc.), invoked via `$this->authorize(...)` in controllers | `EngineRequestController.php:127,153,290` |
| Workflow-stage access | `StagePermissionResolver` — data-driven rows in `stage_permissions` (org/team/role/user; null = wildcard; AND within row, OR across rows; EXECUTE ⊃ VIEW). The **sole routing gate** for engine actions | `app/Services/Workflow/StagePermissionResolver.php:25-57` |
| Screen capabilities | `PermissionService` — role-keyed cache (`screen_permissions.role.{id}`, 1 h TTL) + uncached per-user overlay for team-scoped rows (used by `/auth/me`) | `app/Services/Authorization/PermissionService.php:61-101,254-322` |
| Bank/org data scope | `DataScope::forUser` → `applyTo($query, ..., 'bank_id')`; NATIONAL_COMMITTEE = system-wide, BANKING_SECTOR = own bank, otherwise `1=0` deny-default. Applied in `EngineRequest::scopeForUser` | `app/Services/Authorization/DataScope.php:42-54`, `app/Models/EngineRequest.php:125-134` |
| Creation gate | `RequestCreationGate::userCanCreateRequests` | `EngineRequestService.php:30` |

Bank scoping is applied at the Eloquent query level (deny-by-default), matching the AGENTS.md rule.

## 4. Business-logic distribution

| Location | Role | Observations |
| --- | --- | --- |
| Controllers (44) | Mostly thin: validate → service → resource | Exceptions: `EngineRequestController` carries duplicate-invoice prechecks, warning masking, and formSchema assembly (`:49-60,151-217,474-495`); several governance controllers (`Team`, `Organization`, `User`, `Merchant`, `Bank`, `Role`) open their own `DB::transaction` |
| Services (59) | Core domain: `Workflow/` (engine), `Authorization/`, `Audit/`, `Customs/`, `Documents/`, `Notifications/`, `Settings/`, `Operations/` | `WorkflowDesignerService` is the largest (834 lines, 39 txn/lock sites) |
| Models (39) | Relations + scopes; `EngineRequest` carries SLA SQL-expression builders (`slaDeadlineEpochSql`) | No business mutations in models; status changes go through `EngineTransitionService` only |
| Jobs (4) | `DispatchNotification`, `GenerateReportExport`, `ScanEngineRequestDocument`, `SendEmailDelivery` | Queue = Redis |
| Events/listeners | None registered (no `EventServiceProvider` listeners found); side-effects run via the singleton `StageHookRegistry` | `AppServiceProvider.php:61-85` |
| Stage hooks | `financing.reserve` → `FinancingLedgerEffect`; `fx.confirmation_pdf` → `CustomsFxPdfEffect` — **fire inside the transition transaction** | `AppServiceProvider.php:65-84`, `EngineTransitionService.php:143-163` |
| Middleware (2 custom) | `Authenticate`, `EnsureActiveUser` | Thin |
| Repositories | None (Eloquent directly in services) — consistent, no dual data-access pattern | |

## 5. Transaction & locking inventory

| Site | Pattern | Notes |
| --- | --- | --- |
| `EngineTransitionService::execute` (`:48-176`) | `DB::transaction` + `EngineRequest::lockForUpdate()` + optimistic `version` check | Inside the lock: field validation, projection sync (2nd UPDATE), `workflow_history` insert, `audit_logs` insert, stage hooks (**incl. FX PDF generation**), notification audience queries. Queue push deferred correctly via `DB::afterCommit` (`EngineNotificationDispatcher.php:274-280`) |
| `EngineTransitionService::saveDraft` / `abandonDraft` | Same lock + version pattern | |
| `EngineRequestService::create` (`:70-107`) | Transaction around insert + history + audit; unique-reference retry loop (`:117-144`) computes `MAX(reference)` per attempt | |
| `EngineClaimService` (8 sites) | Claim/heartbeat/release; TTL in `claim_expires_at` (DB is source of truth) | |
| `WorkflowDesignerService` (39 sites) | Designer CRUD/publish — admin-frequency, not hot path | |
| Governance controllers (Team/Org/User/Merchant/Bank/Role, 2-3 each) | Controller-level transactions | |

Vote submission and voting-session closure ride the same `execute()` row lock (voting is a transition action, per `backend/CLAUDE.md`).

## 6. Error & response conventions

- Success envelope: `{success: true, message?, data}`; errors `{success: false, message, error_code?}` via `App\Support\ApiResponse`.
- Domain error codes mapped in `bootstrap/app.php`: `WORKFLOW_FORBIDDEN` (403), `REQUEST_CLOSED` (engine exception), `WORKFLOW_IMMUTABLE_STATE` (rendered **403** at `:201-209`; AGENTS.md describes it as 409 — behavioral observation recorded, not judged here), `WORKFLOW_LOCKED_STATE` (422), financing codes (422/409), throttle → 429, catch-all → 500.
- Pagination meta: `{current_page, last_page, per_page, total}` (`EngineRequestListQuery::paginatedResponse`).

## 7. Lists, filters, search, pagination (current behavior)

- Engine lists (`index`, `myQueue`): offset `paginate()` (default 25, max 100) with total count; ordered by `created_at DESC, id` (index) or SLA priority (myQueue).
- Filtering via `EngineRequestListQuery::applyFilters` (`app/Support/EngineRequestListQuery.php:23-66`): whitelisted statuses, integer filters, `whereDate()` on `created_at`, **leading-wildcard search** `LIKE '%term%'` on `reference`/`invoice_number`, SLA-status filter built from raw epoch expressions + correlated `workflow_history` subquery.
- `scopeWithStageEntry` (`EngineRequest.php:141-157`): correlated subselect `MAX(workflow_history.created_at)` per row + join to `workflow_stages`; `scopeOrderBySlaPriority` (`:163-171`) embeds that subquery **inside ORDER BY** — evaluated for every candidate row before LIMIT.
- No cursor/keyset pagination anywhere; every list endpoint found so far uses offset `paginate()` (fuller sweep in Block 2).

## 8. Files, notifications, reports, long-running work

- Documents: PDF-only uploads, throttled 10/min, virus-scan job `ScanEngineRequestDocument`; downloads policy-checked.
- Notifications: `EngineNotificationDispatcher` resolves audiences via `stage_permissions`/screen-permission queries, then `DispatchNotification` job **after commit** — correct transactional ordering; per-recipient rows fan out in the job.
- Reports: 10 aggregate endpoints (`reports/*`) + `DashboardStatsService` (646 lines) compute on request; exports go through `reports/exports` backed by `GenerateReportExport` job (async ✓), but `audit-logs/export` is a synchronous GET (Block 2 depth).
- FX confirmation PDF (`CustomsFxPdfEffect`) is generated synchronously **inside the transition transaction** — deliberate atomicity per AGENTS.md, with a lock-hold cost (Block 3 quantifies).
- Redis: cache store + queue; claim TTL is DB-based, not Redis.

## 9. Legacy & duplication scan

- Customs-declaration terminology retained alongside FX confirmation (`CustomsDeclaration` model, `EngineCustomsService`, `customs-declaration/*` routes vs `FxConfirmationAuthorizationService`, `fx-confirmation-signed`) — known migration state per AGENTS.md, dual naming in one flow.
- Two PDF libraries (dompdf + mpdf) in `composer.json` — one flow, two engines (Block 2 confirms usage split).
- Two API namespace conventions (§2).
- `TestApiController` + unauthenticated `/test-api` web route — see SEC-001 (reported immediately).
- No repository layer, no parallel ORM paths — single data-access idiom ✓.

## 10. Top-risk list (ranked; bounds Block 2 depth and Block 3 EXPLAIN set)

| # | Endpoint / operation | Risk at design target | Evidence |
| --- | --- | --- | --- |
| 1 | `GET /v1/engine-requests/my-queue` — SLA ordering | Correlated `workflow_history` subquery inside ORDER BY runs per candidate row before LIMIT; unindexable epoch arithmetic | `EngineRequest.php:163-184` |
| 2 | Every engine list/graph call — `accessibleStageIds` | `StagePermission::query()->get()` loads the **whole table** into PHP per request + 2 identity queries | `StagePermissionResolver.php:46-57` |
| 3 | `GET /v1/engine-requests` — search & filters | `LIKE '%term%'` (no index use), `whereDate()` (function on column), offset pagination + COUNT at millions of rows | `EngineRequestListQuery.php:42-53` |
| 4 | `POST .../actions` — transition transaction breadth | Row lock held across validation, projection UPDATE, 2 log inserts, audience queries, and synchronous FX PDF generation on hook stages | `EngineTransitionService.php:48-176` |
| 5 | Request creation reference allocator | `MAX(reference)` lexicographic on `ENG-YYYY-%06d`: at sequence 1,000,000 the 7-digit value sorts **below** `999999`, so MAX stalls and the 5-attempt retry exhausts → permanent `REFERENCE_ALLOCATION_FAILED` at the millionth request/year; also serialization contention under concurrent creates | `EngineRequestService.php:117-144` |
| 6 | `audit_logs` growth + authz-failure write amplification; sync `GET audit-logs/export` | Grows forever; unauthenticated-403 bursts each write a row; export path memory (Block 2) | `AuditService.php:31-46`, `bootstrap/app.php:100-122` |
| 7 | Dashboard/reports aggregates (`DashboardStatsService`, `reports/*`) | Recomputed per request over growing tables (Block 2/3 quantify) | `app/Services/Dashboard/DashboardStatsService.php` |
| 8 | `workflow_history` correlated reads (`stage_entered_at`, SLA filters) | Same subquery family as #1 used in filters too | `EngineRequestListQuery.php:95-124` |
| 9 | No default throttle on 198 authenticated routes | Single client can drive unbounded aggregate load | route:list middleware counts |
| 10 | `/auth/me` permission overlay | Uncached per-user derivation loads all `stage_permissions` for published stages + teams/roles queries | `PermissionService.php:254-322` |

## 11. Missing information

- Production deployment topology (servers, PHP-FPM vs Octane, worker counts) — does not exist yet (pre-production).
- Intended queue worker/Horizon setup — nothing in composer.json (no horizon package).
- Expected published-workflow count and stage_permissions cardinality at steady state (affects risks #2/#10) — needs product input.
- Retention/archival policy intent for `audit_logs`, `workflow_history`, `notifications` — none found in code (only `AuditLogArchiveService`, scope checked in Block 3).
