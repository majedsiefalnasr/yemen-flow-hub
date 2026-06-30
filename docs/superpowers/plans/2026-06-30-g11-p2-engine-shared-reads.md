# G11-P2 Engine Shared Reads Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite Class-B shared read surfaces from legacy `ImportRequest` reads to `EngineRequest` while keeping legacy coexistence until P2 is complete.

**Architecture:** Add a small backend read-model adapter for engine requests so dashboards, search, audit, profile, financing, and model guards can share one status/stage/projection mapping. Then migrate each API surface and its focused feature tests in reviewable slices, leaving legacy routes/controllers/pages in place for P3-P6 rollback safety.

**Tech Stack:** Laravel 11, Eloquent, PHPUnit feature tests, existing `EngineRequest`, `EngineRequestResource`, `workflow_history`, `engine_request_documents`, `EngineFinancingLedger`, and G11 P0.5 claim columns.

## Global Constraints

- Single root repo: `frontend/` and `backend/` are regular directories; commit from `/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code` with `git add backend/<files> frontend/<files> docs/<files>`.
- `main` is currently unpushed and `0 behind / 4 ahead` of `origin/main`; do not push during P2 unless the user explicitly decides push timing.
- Root worktree was clean before P2 planning.
- Coexistence stays until P2 is done: do not delete legacy controllers, models, routes, migrations, frontend pages, or OpenAPI entries in this phase.
- P2 target from `docs/dynamic-engine-reverse/13-g11-cutover-plan.md`: shared reads only: `DashboardController`, `ReportController`, `SearchController`, `AuditController` / `AuditLogResource`, `ProfileController`, `FinancingController`, `DocumentService`, `Bank` / `Merchant` model relations.
- Engine request lifecycle statuses are `ACTIVE`, `CLOSED`, and `REJECTED`; operational buckets must use `currentStage.code`, `currentStage.name`, claim fields, and workflow history rather than reintroducing the legacy 21-status enum.
- Voting is removed by DI-3; do not rebuild legacy voting reads on engine. Existing legacy voting endpoints stay for coexistence until P3/P4 deletion.
- Engine document upload/download already exists on `EngineRequestController`; do not port the legacy `DocumentController` upload path onto engine in P2 unless a shared service dependency requires it.
- Use SocratiCode before modifying an existing file: `codebase_symbol`/`codebase_impact` for known symbols, or `codebase_search` for related code.
- After code changes, run `graphify update .` from the root, but never stage or commit `graphify-out/`.

---

## File Structure

- Create `backend/app/Support/EngineRequestReadModel.php`: status/stage bucket predicates, base scoped query, recent-list resource normalization, and reference formatting for shared reads.
- Modify `backend/app/Models/EngineRequest.php`: add any narrowly needed relations/scopes only if the read model cannot express them cleanly.
- Modify `backend/app/Http/Controllers/Api/DashboardController.php`: replace all `ImportRequest`, `RequestStatus`, `RequestVote`, and `ImportRequestResource` dashboard reads with `EngineRequestReadModel`.
- Modify `backend/app/Http/Controllers/Api/SearchController.php`: search `EngineRequest` projections and engine customs relation fields.
- Modify `backend/app/Http/Controllers/Api/AuditController.php` and `backend/app/Http/Resources/AuditLogResource.php`: resolve engine request subjects and duplicate groups from engine data.
- Modify `backend/app/Http/Controllers/Api/ProfileController.php`: compute profile stats from `EngineRequest`.
- Modify `backend/app/Http/Controllers/Api/FinancingController.php`: authorize and read utilization through engine financing service.
- Modify `backend/app/Models/Bank.php` and `backend/app/Models/Merchant.php`: add `engineRequests()` and rewrite merchant active/any request guards to engine data.
- Review `backend/app/Services/Documents/DocumentService.php`: leave legacy-specific methods intact for coexistence unless tests require a shared helper rename; engine documents already live on `EngineRequestController`.
- Modify focused tests under `backend/tests/Feature/DashboardStatsTest.php`, `backend/tests/Feature/Search/SearchControllerTest.php`, `backend/tests/Feature/Admin/AuditControllerTest.php`, `backend/tests/Feature/Profile/ProfileControllerTest.php`, `backend/tests/Feature/Financing/FinancingUtilizationEndpointTest.php`, and merchant/bank guard tests.

## Task 1: Engine Shared Read Model

**Files:**
- Create: `backend/app/Support/EngineRequestReadModel.php`
- Test: `backend/tests/Feature/Engine/EngineSharedReadModelTest.php`

**Interfaces:**
- Produces: `EngineRequestReadModel::queryFor(User $user): Builder`
- Produces: `EngineRequestReadModel::bucket(string $name): Closure`
- Produces: `EngineRequestReadModel::resourceCollection(iterable $requests): array`
- Produces: `EngineRequestReadModel::reference(?EngineRequest $request, ?int $fallbackId = null): ?string`

- [ ] **Step 1: Write tests for role scope, bucket mapping, and resource shape**

Create engine requests with stages `CREATE`, `INTERNAL`, `SUPPORT`, `EXEC`, `FX`, `FX_CONFIRM`, `FINAL`, `CLOSED`; assert bank users see only their bank, CBY roles see all, `pending_bank_review` maps to `INTERNAL`, `support_queue` maps to `SUPPORT`, `fx_confirmation_pending` maps to `FX_CONFIRM`, `completed` maps to `CLOSED`, and resource items include both `reference_number` and `reference`.

- [ ] **Step 2: Run the failing test**

Run: `cd backend && php artisan test tests/Feature/Engine/EngineSharedReadModelTest.php`

- [ ] **Step 3: Implement the read model**

Use `EngineRequest::query()->with(['bank', 'merchant', 'creator', 'currentStage', 'claimedBy'])`. Keep mappings explicit:

```php
private const STAGE_BUCKETS = [
    'draft' => ['CREATE'],
    'pending_bank_review' => ['INTERNAL'],
    'at_cby' => ['SUPPORT', 'EXEC', 'FX', 'FX_CONFIRM', 'FINAL'],
    'support_queue' => ['SUPPORT'],
    'swift_queue' => ['FX'],
    'executive_queue' => ['EXEC'],
    'fx_confirmation_queue' => ['FX_CONFIRM'],
];
```

Status buckets: `active` = `ACTIVE`, `approved_or_completed` = `CLOSED`, `rejected` = `REJECTED`, `in_progress` = `ACTIVE`.

- [ ] **Step 4: Verify**

Run: `cd backend && php artisan test tests/Feature/Engine/EngineSharedReadModelTest.php`

## Task 2: Dashboard Shared Reads

**Files:**
- Modify: `backend/app/Http/Controllers/Api/DashboardController.php`
- Test: `backend/tests/Feature/DashboardStatsTest.php`
- Test: `backend/tests/Feature/CbyAdminDashboardStatsTest.php`

**Interfaces:**
- Consumes: `EngineRequestReadModel::queryFor()`, `bucket()`, `resourceCollection()`
- Produces: unchanged `/api/dashboard/stats` response keys where they are still meaningful under engine coexistence.

- [ ] **Step 1: Convert test factories to create `EngineRequest` rows**

Replace `makeRequest(... RequestStatus $status ...)` with a helper that creates an engine request on a named stage and status. Preserve existing assertion keys; update status-value assertions to engine status/stage fields.

- [ ] **Step 2: Run focused dashboard tests and capture failures**

Run: `cd backend && php artisan test tests/Feature/DashboardStatsTest.php tests/Feature/CbyAdminDashboardStatsTest.php`

- [ ] **Step 3: Rewrite dashboard reads**

Remove `ImportRequest`, `RequestStatus`, `RequestVote`, and `ImportRequestResource` dependencies. Use stage buckets for role queues. Return zeroed voting counters because DI-3 removed voting; keep the keys only for frontend compatibility during coexistence.

- [ ] **Step 4: Verify**

Run: `cd backend && php artisan test tests/Feature/DashboardStatsTest.php tests/Feature/CbyAdminDashboardStatsTest.php`

## Task 3: Search, Audit Resource, and Profile

**Files:**
- Modify: `backend/app/Http/Controllers/Api/SearchController.php`
- Modify: `backend/app/Http/Controllers/Api/AuditController.php`
- Modify: `backend/app/Http/Resources/AuditLogResource.php`
- Modify: `backend/app/Http/Controllers/Api/ProfileController.php`
- Test: `backend/tests/Feature/Search/SearchControllerTest.php`
- Test: `backend/tests/Feature/Admin/AuditControllerTest.php`
- Test: `backend/tests/Feature/Profile/ProfileControllerTest.php`

**Interfaces:**
- Consumes: `EngineRequestReadModel::queryFor()`, `reference()`, and `resourceCollection()`
- Produces: search `requests` group from engine projections and audit `entity_reference` for `EngineRequest`.

- [ ] **Step 1: Update tests to seed engine subjects**

Audit tests should log `subject_type = EngineRequest::class`; search tests should query `reference`, `invoice_number`, merchant name, bank name, and JSON `data` fields that are already projected.

- [ ] **Step 2: Run focused tests and capture failures**

Run: `cd backend && php artisan test tests/Feature/Search/SearchControllerTest.php tests/Feature/Admin/AuditControllerTest.php tests/Feature/Profile/ProfileControllerTest.php`

- [ ] **Step 3: Rewrite controllers/resources**

Search requests through `EngineRequest`, customs through `engineRequest`, audit duplicate rows through engine duplicate logic, audit entity references through `EngineRequestReadModel::reference()`, and profile totals from engine status buckets.

- [ ] **Step 4: Verify**

Run the same focused test command until green.

## Task 4: Financing and Model Relations

**Files:**
- Modify: `backend/app/Http/Controllers/Api/FinancingController.php`
- Modify: `backend/app/Models/Bank.php`
- Modify: `backend/app/Models/Merchant.php`
- Test: `backend/tests/Feature/Financing/FinancingUtilizationEndpointTest.php`
- Test: `backend/tests/Feature/Merchants/MerchantIntegrityTest.php`
- Test: `backend/tests/Feature/Governance/BankTest.php`

**Interfaces:**
- Consumes: `App\Services\Workflow\Engine\EngineFinancingLedger`
- Produces: `Bank::engineRequests(): HasMany`, `Merchant::engineRequests(): HasMany`

- [ ] **Step 1: Update tests to seed engine requests**

Financing tests should seed `engine_requests` rows with `merchant_id`, `invoice_number`, `request_percentage`, and non-rejected status. Merchant integrity tests should assert active engine requests block bank changes/suspension.

- [ ] **Step 2: Run focused tests and capture failures**

Run: `cd backend && php artisan test tests/Feature/Financing/FinancingUtilizationEndpointTest.php tests/Feature/Merchants/MerchantIntegrityTest.php tests/Feature/Governance/BankTest.php`

- [ ] **Step 3: Rewrite implementation**

Use `EngineFinancingLedger` in `FinancingController`. Authorize through engine request create capability or existing `requests` CREATE capability used by `EngineRequestController`. Add `engineRequests()` relations and make merchant `hasActiveRequests()`/`hasAnyRequests()` use engine requests.

- [ ] **Step 4: Verify**

Run the same focused test command until green.

## Task 5: Legacy ReportController Triage

**Files:**
- Modify: `backend/app/Http/Controllers/Api/ReportController.php`
- Test: `backend/tests/Feature/ReportControllerTest.php`

**Interfaces:**
- Consumes: `EngineRequest`, `workflow_history`, `EngineRequestReadModel`
- Produces: existing legacy `/api/reports/*` response keys where still present in routes.

- [ ] **Step 1: Decide endpoint behavior inside P2**

Because `Api\V1\ReportController` is already engine-native, either rewrite the older `/api/reports/workflow` and `/api/reports/bank` endpoints onto engine data or make them delegate to the V1 report read helpers. Do not preserve legacy voting analytics except as compatibility keys returning zero/empty data.

- [ ] **Step 2: Update tests away from legacy voting/import request factories**

Convert workflow and bank report tests to engine data. Mark voting-report tests for P3/P4 deletion or update them to assert compatibility zeroes only.

- [ ] **Step 3: Run focused report tests**

Run: `cd backend && php artisan test tests/Feature/ReportControllerTest.php tests/Feature/Report/V1ReportsTest.php tests/Feature/Report/ReportExportTest.php`

- [ ] **Step 4: Rewrite and verify**

Replace `import_requests`, `request_stage_history`, `RequestStatus`, `RequestVote`, and `ImportRequest` reads with `engine_requests`, `workflow_history`, `EngineRequest`, and stage/status buckets. Run the same focused report command until green.

## Task 6: DocumentService Review and ImportRequest Sweep

**Files:**
- Review: `backend/app/Services/Documents/DocumentService.php`
- Potentially modify only if shared endpoints still call it for engine reads.
- Test: `backend/tests/Feature/Documents/DocumentControllerTest.php`
- Test: `backend/tests/Feature/Documents/DocumentDownloadPermissionTest.php`

**Interfaces:**
- Consumes: existing `EngineRequestController` document endpoints for engine documents.
- Produces: no new engine document API unless a real shared-read dependency remains.

- [ ] **Step 1: Confirm P2 boundary**

Run `rg -n "DocumentService|RequestDocument|EngineRequestDocument" backend/app backend/tests/Feature/Documents` and confirm legacy `DocumentService` is only backing legacy routes that P3/P4/P6 will delete.

- [ ] **Step 2: If no shared engine dependency exists, leave implementation untouched**

Record this as an intentional P2 no-op in the progress ledger. Do not rewrite legacy upload behavior during shared-read migration.

- [ ] **Step 3: If a shared read dependency exists, isolate it**

Extract only shared download/resource logic needed by engine endpoints; keep legacy upload methods unchanged for coexistence.

- [ ] **Step 4: Verify**

Run: `cd backend && php artisan test tests/Feature/Documents/DocumentControllerTest.php tests/Feature/Documents/DocumentDownloadPermissionTest.php`

## Task 7: Final Sweep, Graph Refresh, and Commit

**Files:**
- Modify: this plan's touched backend tests/code only.
- Do not stage: `graphify-out/**`

**Interfaces:**
- Produces: P2 completion evidence and one root commit.

- [ ] **Step 1: Search for Class-B legacy references**

Run: `rg -n "ImportRequest|RequestStatus|RequestVote|request_stage_history|import_requests|ImportRequestResource|ImportRequestListResource" backend/app/Http/Controllers/Api/DashboardController.php backend/app/Http/Controllers/Api/SearchController.php backend/app/Http/Controllers/Api/AuditController.php backend/app/Http/Resources/AuditLogResource.php backend/app/Http/Controllers/Api/ProfileController.php backend/app/Http/Controllers/Api/FinancingController.php backend/app/Models/Bank.php backend/app/Models/Merchant.php backend/app/Http/Controllers/Api/ReportController.php`

Expected: no matches except explicitly documented legacy compatibility in `ReportController` if still routed.

- [ ] **Step 2: Run focused backend gate**

Run: `cd backend && composer format:check && php artisan test tests/Feature/Engine/EngineSharedReadModelTest.php tests/Feature/DashboardStatsTest.php tests/Feature/CbyAdminDashboardStatsTest.php tests/Feature/Search/SearchControllerTest.php tests/Feature/Admin/AuditControllerTest.php tests/Feature/Profile/ProfileControllerTest.php tests/Feature/Financing/FinancingUtilizationEndpointTest.php tests/Feature/Merchants/MerchantIntegrityTest.php tests/Feature/ReportControllerTest.php tests/Feature/Report/V1ReportsTest.php`

- [ ] **Step 3: Refresh graph**

Run: `graphify update .`

- [ ] **Step 4: Commit from root**

Run:

```bash
git add backend/app backend/tests docs/superpowers/plans/2026-06-30-g11-p2-engine-shared-reads.md
git commit -m "refactor(workflow): read shared surfaces from engine requests"
```

Commit body must include `Co-Authored-By: Claude <noreply@anthropic.com>`. Do not bypass signing or hooks.

## Self-Review

- Spec coverage: P2 Class-B surfaces are all covered. V1 reports are already engine-native but remain in verification. `DocumentService` is explicitly reviewed as likely legacy-only during coexistence rather than rewritten unnecessarily.
- Placeholder scan: no TBD/TODO placeholders.
- Type consistency: all later tasks consume the same `EngineRequestReadModel` methods introduced in Task 1.
