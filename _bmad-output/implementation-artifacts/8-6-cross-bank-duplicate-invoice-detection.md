# Story 8.6: Cross-Bank Duplicate Invoice Detection

## Story

**As** a `BANK_REVIEWER`, `SUPPORT_COMMITTEE` member, or `CBY_ADMIN`,
**I want** the system to detect duplicate `invoice_number` values across all banks (not only within my own bank),
**So that** the same supplier invoice cannot be financed twice through different commercial banks.

**Source:** `docs/09-user-stories-gap-analysis.md` §3 Workflow F, §5 item 13, §6 S6 · USER-STORIES.md §15.13 + §15.14 · Gap G5.

---

## Acceptance Criteria

### AC1 — Backend: Composite index
**Given** the database migration runs
**Then** `import_requests` has a composite index on `(invoice_number, deleted_at)`
**And** the index does NOT include `bank_id` (cross-bank scans must be fast)

### AC2 — Backend: DuplicateDetectionService
**Given** `app/Services/DuplicateDetectionService.php`
**Then** the service exposes `findDuplicatesForInvoice(string $invoiceNumber, ?int $excludeRequestId = null): Collection`
**And** the query has NO bank scope filter
**And** excludes soft-deleted rows
**And** excludes the row passed as `excludeRequestId`
**And** returns rows containing at minimum: `id`, `reference_number`, `bank_id`, `bank.name`, `amount`, `currency`, `created_at`, `status`

### AC3 — Backend: System setting `duplicate_invoice_policy`
**Given** the `system_settings` table seed
**Then** key `duplicate_invoice_policy` exists with default value `warn` and allowed values `warn`/`block`
**And** `AdminSettingsController` exposes get/set for this key (scoped to `CBY_ADMIN`)

### AC4 — Backend: Wizard submission honors policy
**Given** `POST /api/requests` receives a payload whose `invoice_number` matches existing non-deleted rows
**When** `duplicate_invoice_policy = block`
**Then** the response is `422` with Arabic error: "رقم الفاتورة مكرر في طلبات أخرى - يرجى المراجعة"
**When** `duplicate_invoice_policy = warn` (default)
**Then** the request is created and an audit entry `REQUEST_CREATED` includes `notes.duplicate_count` > 0

### AC5 — Backend: Detail endpoint exposes warnings
**Given** `GET /api/requests/{id}` is called by a reviewer/auditor role
**Then** the response includes `data.duplicate_warnings: array` (possibly empty)
**For** `CBY_ADMIN` and `SUPPORT_COMMITTEE`: full payload (other banks' reference, amount, currency, bank name, created_at)
**For** bank-scoped roles (`BANK_REVIEWER`, `BANK_ADMIN`): count + bank names only (no other-bank reference numbers or amounts)
**For** `DATA_ENTRY`: field is omitted entirely (no leakage)

### AC6 — Backend: Audit duplicates tab cross-bank scan
**Given** `GET /api/audit/duplicates`
**Then** the response uses `DuplicateDetectionService` to enumerate groups of requests sharing an `invoice_number`
**And** rows are grouped by `invoice_number`
**And** each group includes the participating bank names

### AC7 — Frontend: Detail-page duplicate widget
**Given** a request detail page where `duplicate_warnings.length > 0` and actor is reviewer/auditor
**Then** a warning badge "مكرر" is visible at the top of the page
**And** an expandable section "فواتير مكررة" shows the peer rows side by side
**For** CBY/support actors: full peer data (ref, bank, amount, currency, date)
**For** bank actors: count + bank names only ("مكرر مع: بنك التضامن، بنك سبأ")

### AC8 — Frontend: Audit page duplicates tab
**Given** `pages/audit.vue` duplicates tab
**Then** rows are grouped by invoice number with all participating banks expanded
**And** sorted by most recent duplicate event

### AC9 — Frontend: Admin settings toggle
**Given** I am `CBY_ADMIN` on `/admin/settings`
**Then** a section "سياسة الفواتير المكررة" shows a select with values "تحذير (warn)" / "حظر (block)"
**And** saving updates the system setting via the existing admin settings endpoint

### AC10 — Performance test
**Given** a database seeded with 10,000 import_requests with random invoice numbers
**Then** a single duplicate scan completes in < 50 ms (verified by a timed test)

### AC11 — Tests
- Backend: service unit tests; policy-driven 422 vs 201; detail visibility per role; audit endpoint; performance test
- Frontend: warning badge visibility per role; expandable widget rendering; audit duplicates tab; admin settings toggle

---

## Tasks / Subtasks

### Task 1: Backend — Migration + index
- [x] 1.1 New migration `add_index_invoice_number_to_import_requests.php` with composite index `(invoice_number, deleted_at)`
- [x] 1.2 Migration test (down + up)

### Task 2: Backend — DuplicateDetectionService
- [x] 2.1 Create `app/Services/DuplicateDetectionService.php`
- [x] 2.2 Implement `findDuplicatesForInvoice` with no bank scope
- [x] 2.3 Add `findDuplicateGroups()` returning grouped result for audit page
- [x] 2.4 Unit tests

### Task 3: Backend — System setting + admin endpoint
- [x] 3.1 Add `duplicate_invoice_policy` to `system_settings` seeder with default `warn`
- [x] 3.2 Extend `AdminSettingsController` whitelist + validation rule
- [x] 3.3 Feature test getting and setting the value

### Task 4: Backend — Wizard policy enforcement
- [x] 4.1 In `StoreImportRequest` (or controller) read the policy; on duplicate + policy=block return 422
- [x] 4.2 On policy=warn, populate `notes.duplicate_count` in REQUEST_CREATED audit
- [x] 4.3 Feature tests for both modes

### Task 5: Backend — Detail endpoint + audit endpoint
- [x] 5.1 In `ImportRequestController::show()`, attach `duplicate_warnings` to resource per role
- [x] 5.2 Update `AuditController::duplicates()` to use the new service
- [x] 5.3 Test per-role payload shape; test grouping

### Task 6: Frontend — Composables + types
- [x] 6.1 Update `useRequests.ts` `ImportRequest` type with `duplicate_warnings?: DuplicateWarning[]`
- [x] 6.2 Update `useAudit.ts` for duplicate groups

### Task 7: Frontend — Detail-page widget
- [x] 7.1 Add warning badge + expandable peer-rows section to `pages/requests/[id]/index.vue`
- [x] 7.2 Role-aware rendering for bank actors vs CBY/support actors
- [x] 7.3 Component test

### Task 8: Frontend — Audit page tab + settings toggle
- [x] 8.1 Render grouped duplicates in `pages/audit.vue`
- [x] 8.2 Add policy select to `pages/admin/settings.vue`
- [x] 8.3 Tests

### Task 9: Pre-flight + post-flight
- [x] 9.1 SocratiCode: `codebase_symbol` on `ImportRequestController`, `AuditController`, `AdminSettingsController`
- [x] 9.2 `codebase_impact` on `import_requests` schema and `ImportRequestController::show`
- [x] 9.3 All tests green; performance test green; `graphify update .`
- [x] 9.4 Signed commits to both repos

---

## Out of Scope

- Full side-by-side amount/currency diff highlighting (fast-follow once both rows render)
- Auto-block based on amount/currency mismatch (defer)
- Cross-bank user notification on duplicate creation (defer)

## Dependencies

None.

---

## Dev Agent Record

### Implementation Plan
- Backend: migration with composite index `(invoice_number, deleted_at)` (no bank_id for cross-bank speed), `DuplicateDetectionService` with `findDuplicatesForInvoice` and `findDuplicateGroups`, policy enforcement in `ImportRequestController::store`, role-aware `duplicate_warnings` in `show`, grouped `AuditController::duplicates`.
- Frontend: `DuplicateWarning`/`DuplicateGroup` types in `models.ts`, `useAudit.ts` extended with grouped `fetchDuplicates`, `useAdminSettings.ts` typed with `duplicate_invoice_policy`, detail-page widget with role-aware rendering, audit tab accordion groups, admin settings policy select.

### Completion Notes
- ✅ AC1: Composite index `idx_invoice_number_deleted_at` on `(invoice_number, deleted_at)` — no `bank_id` column per spec.
- ✅ AC2: `DuplicateDetectionService::findDuplicatesForInvoice` queries across all banks, excludes soft-deleted and the source request.
- ✅ AC3: `duplicate_invoice_policy` seeded with default `warn`; `AdminSettingsController` validates `warn|block`.
- ✅ AC4: `ImportRequestController::store` reads policy; `block` → 422, `warn` → audit note with `duplicate_count`.
- ✅ AC5: `show()` attaches role-scoped `duplicate_warnings`: full for CBY/SUPPORT, count+names for bank roles, omitted for DATA_ENTRY.
- ✅ AC6: `AuditController::duplicates()` returns `{data:[{invoice_number, banks[], requests[]}]}` via service.
- ✅ AC7: Detail-page duplicate widget renders full table for CBY/SUPPORT or summary text for bank roles; hidden for DATA_ENTRY and no-duplicates.
- ✅ AC8: Audit page duplicates tab rewritten as accordion — one card per `invoice_number`, expandable inner table.
- ✅ AC9: Admin settings workflow tab has "سياسة الفواتير المكررة" select with `warn`/`block` options wired to settings endpoint.
- ✅ AC10: Performance test in `DuplicateDetectionServiceTest` confirms sub-50ms scan on seeded dataset.
- ✅ AC11: 7 backend unit tests + 13 feature tests (duplicate detection, policy, RBAC) + 7 frontend unit tests + 3 updated audit composable tests = 30 story-specific tests all green. Pre-existing failures in `BankAdminRbacTest` (2) and `WorkflowControllerTest` (1) are unrelated to this story (confirmed by `git stash` revert).

### Debug Log
- Pre-existing `AuditControllerTest::test_duplicates_endpoint_returns_requests_with_same_invoice_number` asserted old flat-list format — updated to grouped format matching story 8.6 spec.
- `useAudit.test.ts` `fetchDuplicates` mock updated from paginated flat list to `DuplicateGroup[]` shape.
- 3 backend failures confirmed pre-existing via `git stash` verification; not introduced by this story.

---

## File List

### Backend
- `backend/app/Services/DuplicateDetectionService.php` (created)
- `backend/database/migrations/2026_05_22_000001_add_index_invoice_number_to_import_requests.php` (created)
- `backend/database/seeders/SystemSettingsSeeder.php` (modified — `duplicate_invoice_policy`)
- `backend/app/Http/Controllers/Api/ImportRequestController.php` (modified — store policy + show role-aware warnings)
- `backend/app/Http/Controllers/Api/AuditController.php` (modified — duplicates grouped endpoint)
- `backend/app/Services/Settings/AdminSettingsService.php` (modified — `duplicate_invoice_policy` defaults + validation)
- `backend/tests/Unit/Services/DuplicateDetectionServiceTest.php` (created)
- `backend/tests/Feature/Requests/DuplicateInvoiceTest.php` (created)
- `backend/tests/Feature/Admin/AuditControllerTest.php` (modified — updated duplicates assertions to grouped format)

### Frontend
- `frontend/app/types/models.ts` (modified — `DuplicateWarning` interface + `ImportRequest.duplicate_warnings`)
- `frontend/app/composables/useAudit.ts` (modified — `DuplicateRequest`, `DuplicateGroup`, updated `fetchDuplicates`)
- `frontend/app/composables/useAdminSettings.ts` (modified — `duplicate_invoice_policy` typed)
- `frontend/app/pages/audit.vue` (modified — accordion groups in duplicates tab)
- `frontend/app/pages/requests/[id]/index.vue` (modified — duplicate widget with role-aware rendering)
- `frontend/app/pages/admin/settings.vue` (modified — duplicate policy select in workflow tab)
- `frontend/app/tests/unit/pages/DuplicateInvoiceDetection.test.ts` (created)
- `frontend/app/tests/unit/composables/useAudit.test.ts` (modified — updated fetchDuplicates + 2 new tests)

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-05-22 | Story 8.6 implemented: cross-bank duplicate invoice detection — backend service, policy enforcement, role-aware API, frontend widget + audit tab + admin toggle. 30 new/updated tests green. | Dev Agent |

---

## Status

review
