# 04 — API & Laravel Optimization Plan

Block 2 deliverable. Owns backend endpoint behavior, Laravel execution, serialization, and response construction. Frontend consumption of these endpoints is Block 4 (cross-referenced, not duplicated). Evidence at baseline SHA `be652fdd`.

## Method

All 209 API routes reviewed against `php artisan route:list` output. Every `->get()`/`::all()` site (36 in controllers) was read and classified, not flagged from grep. Pagination, N+1, over-fetch, serialization cost, sync-vs-queue, rate limits, and response consistency assessed. Dynamic plan capture for the confirmed hot-query set is deferred to Block 3.

## What is already good (no finding)

- **Pagination is universal and capped.** Every list endpoint uses offset `paginate()` with default 25 (audit 30, notifications/exports 20) and max 100 (`EngineRequestListQuery.php:68`, and per-controller `min(max(...),100)`). No unbounded list endpoint exists. The scale concern is offset-`COUNT` cost, not missing pagination (Block 3).
- **No accessor executes a query.** Only `EngineRequest::getSlaStatusAttribute` (`EngineRequest.php:200`) exists and it reads already-selected columns; no `$appends`. No hidden accessor N+1.
- **List serialization is deliberately memoized.** `StageFieldOutputFilter` (singleton, per-version/stage field-key cache) and `EngineRequestResource::$canExecuteCache` (per user+stage, flushed per response) eliminate the obvious per-row `FieldDefinition` and stage-permission repeats (`AppServiceProvider.php:27-33`, `EngineRequestResource.php:24-77`, `StageFieldOutputFilter.php:26-31`).
- **Eager loading is explicit** on the engine list/queue (`with([...])`, `EngineRequestController.php:243,273`) — no lazy relation access in `EngineRequestResource` (all `whenLoaded`).
- **Notifications, report exports, and document scanning are queued** (`DispatchNotification`, `GenerateReportExport`, `ScanEngineRequestDocument`); notification dispatch is correctly `DB::afterCommit` (`EngineNotificationDispatcher.php:274`).
- **Aggregate reports use SQL GROUP BY + explicit `limit`** where a long tail is possible (`byMerchant` limit 50, `requestsOverTime` limit 24) — not PHP-side grouping (mostly; exceptions below).
- **CSV export neutralizes formula injection** (`AuditLogController.php:139-152`).

## Findings (API series)

Full finding records are in `02-findings.md`. Summary of what Block 2 adds:

| ID | Severity | Endpoint | Core issue |
| --- | --- | --- | --- |
| API-001 | High | `GET /v1/engine-requests`, `my-queue`, detail, `fx_panel` | `EngineRequestResource` calls `FxConfirmationAuthorizationService::panelCapabilities` per row → 2 uncached queries/row (`WorkflowStage::query()` + `EngineRequest::query()`); `can_execute` memo only covers repeats within one response |
| API-002 | High | `GET /v1/engine-requests/stats` | 6 full aggregate passes, each re-running `accessibleStageIds()` (whole `stage_permissions` load, ARCH-001) and `withStageEntry` correlated subquery; `total`+`by_status` overlap |
| API-003 | High (renamed from API-000) | `POST /v1/engine-requests` | Reference allocator digit-overflow + per-create `MAX(reference)` race (see full record; carried from Block 1) |
| API-004 | Medium | `GET /v1/audit-logs/export` | Synchronous 10k-row load + in-PHP CSV build in the request lifecycle |
| API-005 | Medium | `GET /v1/reports/summary` | 7 separate full-scan COUNT/SUM passes over the same scoped set instead of one grouped pass |
| API-006 | Medium | `GET /v1/reports/sla`, `stage-duration`, `team-performance` | Unbounded `->get()` over joins/scans with no LIMIT; `sla` pulls all matching rows into PHP to group + derive SLA status per row |
| API-007 | Low | `GET /v1/audit-logs`, `reports/*` | `whereDate()` and `subject_type LIKE '%...%'` defeat indexes (shared root with ARCH-004; consolidated in Block 3 index plan) |

Cross-references: API-001/002 depend on **ARCH-001** (stage-permission full-table load) and **ARCH-002** (SLA correlated subquery). Fixing ARCH-001 materially reduces API-001/002 cost.

## Optimization plan by theme

### Pagination & counts
- Keep offset pagination for admin/governance lists (small, bounded tables) — appropriate, no change.
- For `engine-requests` index/queue and `audit-logs` (design-target millions), Block 3 evaluates: (a) keyset/cursor pagination for the default `created_at DESC, id` ordering to avoid deep-offset scans; (b) `simplePaginate` (drops the `COUNT(*)` total) where the UI does not need an exact last-page. Decision gated on Block 3 plans; offset is not recommended at millions of rows without a covering index.

### Serialization (API-001)
- Make `fx_panel` and `can_execute` batch-resolvable: resolve accessible EXECUTE stage ids and FX-panel inputs **once per response** for the page's stage set, then map per row — the same pattern already used for `StageFieldOutputFilter`. Preserves identical authorization output. Trade-off: `FxConfirmationAuthorizationService` needs a batch entry point.

### Aggregates (API-002, API-005)
- Collapse multi-pass counts into a single grouped query (`SELECT status, COUNT(*) ... GROUP BY status` already exists for `by_status`; derive `total`/`active`/etc. from it in PHP). For `reports/summary`, one `selectRaw` with conditional `SUM(CASE WHEN ...)` replaces 7 passes.
- For stats SLA metrics, compute breached/nearing in the same grouped pass rather than two extra filtered counts.

### Async offload (API-004)
- Route `audit-logs/export` through the existing `GenerateReportExport` job pattern (async, downloadable artifact) instead of a synchronous CSV. Trade-off: changes the client contract from direct download to poll-for-artifact (coordinate with Block 4).

### Index-defeating predicates (API-007)
- Consolidated into the Block 3 index/query plan: replace `whereDate('created_at', ...)` with half-open range bounds; replace `subject_type LIKE '%X%'` with an exact/prefixed match on a normalized column or class-name equality.

### Rate limiting (ARCH-003, Block 1)
- Apply a default authenticated throttle to the `v1` and unversioned authenticated groups; tighter limits on `reports/*`, `*/export`, `stats`, `dashboard/stats`. Sizing coordinated with Block 4 polling cadence.

### Structural (ARCH-005, Block 1)
- PDF engine usage split confirmed in Block 3/6 (both dompdf and mpdf are required; identify which generator uses which). Namespace consolidation remains Optional.

## Laravel-specific notes

- Route/config caching: no blocker found; recommend `config:cache`+`route:cache` in the deploy runbook (Block 6 checklist).
- Octane: viable long-term (stateless services, singletons already used correctly), but **not** recommended pre-production — the singleton caches (`StageFieldOutputFilter`, `$canExecuteCache`) are per-request-flushed and would need Octane-safety review first. Optional tier.
- Sanctum: stateful SPA cookie path is standard; token lookup cost is per-request and not a hot-path concern at design target.
- Validation: Form Requests + inline `validate()` mix is fine; no oversized validation found.
