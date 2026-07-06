# WP-7 Two-Layer Visibility Wave Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce a shared `DataScope` primitive and apply two-layer visibility (capability gates access; organization classification bounds data) across audit, compliance, reports/exports, dashboard, search, notifications, financing advisory, duplicate-invoice masking, invoice normalization, and inactive team/role filtering.

**Architecture:** `App\Services\Authorization\DataScope` resolves `{ classification, ownBankId, systemWide }` from the authenticated user and exposes `applyTo($query, $bankColumn)`. Controllers/policies keep capability checks; every read surface replaces ad-hoc `applyScope` with `DataScope`. Invoice matching uses upgraded `InvoiceKey::normalize()` plus a backfill migration. `StagePermissionResolver::identityFor` filters inactive teams/roles (T-3 pin flip).

**Tech Stack:** Laravel 11 (PHP 8.2+), PHPUnit, existing `OrganizationClassification` enum from WP-1, `StagePermissionAudience` from WP-R.

**Authority:** `docs/superpowers/specs/2026-07-06-wp7-two-layer-visibility-wave.md`

## Global Constraints

- All commits signed; never `--no-gpg-sign`/`--no-sign`/`-c commit.gpgsign=false`.
- Commit message format `type(scope): description`; scope from `{auth, backend, docs, frontend, repo, settings, testing, ui, workflow}`.
- Co-author line: `Co-Authored-By: Claude <noreply@anthropic.com>`.
- Capability checks stay in controllers/policies; `DataScope` is data-bounding only — never merge the two layers.
- `BANKING_SECTOR` → `{ systemWide: false, ownBankId: user->bank_id }`; null bank ⇒ matches nothing.
- `NATIONAL_COMMITTEE` → `{ systemWide: true }` when capability granted.
- `OTHER` → `{ systemWide: false, ownBankId: null }` ⇒ matches nothing by default.
- Null `bank_id` never implies system-wide.
- Focused verification per task; no full `php artisan test` unless task says so.
- Do not touch WP-8 field visibility, WP-12 list redesign, or WP-14 cleanup.

---

### Task 1: `DataScope` primitive + unit tests (S-0)

**Files:**
- Create: `backend/app/Services/Authorization/DataScope.php`
- Create: `backend/app/DTOs/Authorization/DataScopeContext.php` (value object, if cleaner than inline array)
- Test: `backend/tests/Unit/Services/Authorization/DataScopeTest.php`

**Interfaces:**
- `DataScope::forUser(User $user): DataScopeContext` — reads `organization.classification` + `user.bank_id`.
- `DataScope::applyTo(Builder $query, DataScopeContext $scope, string $bankColumn = 'bank_id'): Builder`
  - `systemWide: true` → no bank filter added.
  - `systemWide: false` + `ownBankId` set → `where($bankColumn, $ownBankId)`.
  - `systemWide: false` + `ownBankId` null → `whereRaw('1 = 0')` (matches nothing).

- [ ] **Step 1: Write failing unit tests** for BANKING_SECTOR+bank, BANKING_SECTOR+null-bank, NATIONAL_COMMITTEE, OTHER, null-org edge.
- [ ] **Step 2: Run** `php artisan test tests/Unit/Services/Authorization/DataScopeTest.php` — expect FAIL.
- [ ] **Step 3: Implement** `DataScope` + context value object.
- [ ] **Step 4: Run tests** — expect PASS.
- [ ] **Step 5: Pint** touched PHP files.
- [ ] **Step 6: Commit** `feat(backend): add DataScope authorization primitive (WP-7 S-0)`.

---

### Task 2: Inactive team/role filtering (S-10)

**Files:**
- Modify: `backend/app/Services/Workflow/StagePermissionResolver.php` (`identityFor`)
- Modify: `backend/app/Services/Workflow/StagePermissionAudience.php` (if needed for parity)
- Test: flip WP-0 T-3 inactive pins; run audience comparative tests.

- [ ] Filter `teams()->where('is_active', true)` and `roles()->where('is_active', true)` in `identityFor`.
- [ ] Update/flip T-3 characterization cases for inactive team/role → no-match.
- [ ] Commit `feat(workflow): filter inactive teams and roles from stage identity (WP-7 S-10)`.

---

### Task 3: Audit read scope NC-only (S-1)

**Files:**
- Modify: `backend/app/Policies/AuditLogPolicy.php`
- Modify: `backend/app/Http/Controllers/Api/AuditLogController.php` (if query guard needed)
- Test: `backend/tests/Feature/Admin/AuditScopeTest.php` (new)

- [ ] `viewAny`/`view` return false for BANKING_SECTOR even with `audit VIEW` capability.
- [ ] NATIONAL_COMMITTEE + capability → platform-wide (unchanged).
- [ ] Commit `feat(backend): restrict audit reads to national committee scope (WP-7 S-1)`.

---

### Task 4: Compliance scope via DataScope (S-2)

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/ComplianceController.php` — replace private `applyScope` with `DataScope`.
- Test: characterization tests NC vs bank vs OTHER.

- [ ] Commit `feat(backend): apply DataScope to compliance reads (WP-7 S-2)`.

---

### Task 5: Reports + exports scope, EXPORT gate, audit (S-3)

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/ReportController.php`
- Modify: `backend/app/Jobs/GenerateReportExport.php` (re-derive scope from requester classification)
- Test: export capability + audit assertions.

- [ ] Commit `feat(backend): scope reports and gate exports with audit trail (WP-7 S-3)`.

---

### Task 6: Dashboard scope (S-4)

**Files:**
- Modify: `backend/app/Services/Dashboard/DashboardStatsService.php`
- Test: T-4 dashboard snapshots for NC/system_admin; bank scoped; OTHER empty.

- [ ] Commit `feat(backend): apply DataScope to dashboard stats (WP-7 S-4)`.

---

### Task 7: Search scope (S-5)

**Files:**
- Modify: `backend/app/Http/Controllers/Api/SearchController.php`
- Test: bank vs NC vs OTHER search groups.

- [ ] Commit `feat(backend): apply DataScope to global search (WP-7 S-5)`.

---

### Task 8: Notification audience scope (S-6)

**Files:**
- Modify: `backend/app/Services/Notifications/EngineNotificationDispatcher.php`
- Use `StagePermissionAudience` + classification checks.

- [ ] Commit `feat(backend): scope notification audiences by classification (WP-7 S-6)`.

---

### Task 9: Financing advisory scope (S-7)

**Files:**
- Modify financing utilization endpoint/controller
- Test: cross-bank probe denied; own-bank aggregate only.

- [ ] Commit `feat(backend): scope financing advisory by DataScope (WP-7 S-7)`.

---

### Task 10: Duplicate-invoice masking (S-8)

**Files:**
- Modify: `backend/app/Services/Workflow/DuplicateInvoiceChecker.php`
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php` (response shaping)
- Modify: `EngineNotificationDispatcher` body masking for non-NC.

- [ ] Commit `feat(workflow): mask cross-bank duplicate warnings for bank users (WP-7 S-8)`.

---

### Task 11: Invoice normalization upgrade + backfill (S-9)

**Files:**
- Modify: `backend/app/Support/InvoiceKey.php` — trim + uppercase + collapse spaces.
- Wire: `DuplicateInvoiceChecker`, `RequestProjectionSync`, `EngineFinancingLedger`, advisory endpoint.
- Migration: add normalized column or backfill `engine_requests` projected invoice field per spec open question (recommend separate normalized column).
- Test: `INV-1`/`inv-1`/`INV 1` collapse.

- [ ] Commit `feat(workflow): unify invoice normalization and backfill projections (WP-7 S-9)`.

---

### Task 12: Wave gate + manual verification

- [ ] Run smoke: WP-0 T-3/T-4 suites + per-surface characterization tests from this plan.
- [ ] Manual checks per spec § Manual verification steps (6 items).
- [ ] `composer format:check` on touched backend files.
