# Story 5.7: Approved Lovable Prototype Parity & Production UI Alignment

## Status: done

## Story

**As a** stakeholder and product owner,
**I want** the production Nuxt application to match the accepted Lovable prototype as closely as possible within the current production tech stack,
**So that** stakeholder-approved UX intent is preserved while replacing demo-only behavior with secure, audited, production implementations.

## Acceptance Criteria

**AC-1 — Route Parity Map:** Every stakeholder-facing route in `lovable/src/routes/*` is mapped to one of: implemented production route, production-equivalent route with different path/name, intentionally excluded demo-only behavior, or explicitly deferred post-sprint item with reason.

**AC-2 — Missing Pages Built:** The following routes advertised in navigation but lacking a production page are fully implemented:
- `/merchants` — merchant list with bank-scoped visibility
- `/customs` — standalone customs queue for COMMITTEE_DIRECTOR
- `/audit` — audit/compliance log for CBY_ADMIN
- `/admin/workflow-docs` — document type rules management for CBY_ADMIN

**AC-3 — Navigation Integrity:** No navigation link points to a missing or broken page. Any route not yet ready is removed from the sidebar nav until implemented.

**AC-4 — Role Guards:** Every new page declares `definePageMeta` with correct `requiredRoles`. Backend enforces org-scope; frontend enforces role-visibility only.

**AC-5 — Production Stack Only:** Implementation uses Nuxt 4, Vue, TypeScript, Tailwind CSS, shadcn-vue patterns, and Pinia. No React/TanStack code from Lovable is copied. No prototype mock stores used as production state.

**AC-6 — Demo-Only Exclusions Documented:** Prototype-only demo controls (role switcher, fake login picker, demo reset, mock-state edits) are excluded and documented in the parity checklist in this story's Dev Notes.

**AC-7 — Visual Design Alignment:** New and existing pages use design tokens from `DESIGN.md` exactly — background `#f5f5f7`, surface `#ffffff`, border `#d2d2d7`, primary `#0071e3`, card radius `12px`, RTL-first.

**AC-8 — Empty / Loading / Error States:** Every new page handles loading spinner, empty state (no data message), and API error state.

**AC-9 — Parity Report:** A final route-by-route parity checklist is written in the Completion Notes of this story's Dev Agent Record.

**AC-10 — No Regressions:** All existing frontend tests pass after changes. New pages add unit tests for composables/stores and page-mount smoke tests.

## Tasks / Subtasks

### Task 1: Audit current navigation and remove broken links
- [x] 1.1 Identify which NAV_ITEMS routes lack a corresponding page file
- [x] 1.2 Remove `/bank/users` from NAV_ITEMS (no production role model for bank self-admin per audit report)
- [x] 1.3 Verify `/merchants`, `/customs`, `/audit`, `/admin/workflow-docs` are in NAV_ITEMS with correct roles
- [x] 1.4 Run nav-items unit tests and fix any failures
- [x] 1.5 Mark all existing tests pass

### Task 2: Build `/merchants` page
- [x] 2.1 Extend `useMerchants` composable with `createMerchant` and `updateMerchant` functions
- [x] 2.2 Create `frontend/app/pages/merchants.vue` — merchant list table with search, create/edit modal, CBY_ADMIN scoped
- [x] 2.3 Add `definePageMeta` with `requiredRoles: [CBY_ADMIN]`
- [x] 2.4 Handle loading, empty, and error states
- [x] 2.5 Write unit tests for extended composable and page smoke test

### Task 3: Build `/customs` page (standalone customs queue)
- [x] 3.1 Reuse `fetchRequests` with `status=EXECUTIVE_APPROVED` for queue — existing composable was sufficient
- [x] 3.2 Create `frontend/app/pages/customs/index.vue` — queue list of EXECUTIVE_APPROVED requests with issue customs action
- [x] 3.3 Add `definePageMeta` with `requiredRoles: [COMMITTEE_DIRECTOR]`
- [x] 3.4 Handle loading, empty, and error states
- [x] 3.5 Write unit tests for customs queue logic and composable interactions

### Task 4: Build `/audit` page (compliance log)
- [x] 4.1 Added `AuditLog` and `DocumentType` types to `frontend/app/types/models.ts`
- [x] 4.2 Create `frontend/app/composables/useAudit.ts` — paginated audit log fetcher with filter support
- [x] 4.3 Create `frontend/app/pages/audit.vue` — audit table with filters (action, date range), paginated
- [x] 4.4 Add `definePageMeta` with `requiredRoles: [CBY_ADMIN]`
- [x] 4.5 Handle loading, empty, and error states
- [x] 4.6 Write unit tests for composable (8 tests)

### Task 5: Build `/admin/workflow-docs` page (document type rules)
- [x] 5.1 Create `frontend/app/composables/useDocumentTypes.ts` — CRUD for document types via `/api/document-types`
- [x] 5.2 Create `frontend/app/pages/admin/workflow-docs.vue` — list of document types with create/edit, toggle active
- [x] 5.3 Add `definePageMeta` with `requiredRoles: [CBY_ADMIN]`
- [x] 5.4 Handle loading, empty, and error states
- [x] 5.5 Write unit tests for composable (8 tests)

### Task 6: Final parity validation and tests
- [x] 6.1 Run full frontend test suite — 816 tests, 0 regressions
- [x] 6.2 Write route-by-route parity checklist in Completion Notes
- [x] 6.3 Update story status to "done" after review patches

### Review Findings
- [x] [Review][Patch] Audit page expects a paginated `data.meta` shape that `/api/audit` does not return, so successful loads fall into the error state [frontend/app/pages/audit.vue:98]
- [x] [Review][Patch] Audit action filters use lowercase prototype action keys, but the backend filters against uppercase `AuditAction` enum values, so selecting a filter returns no matching production logs [frontend/app/pages/audit.vue:28]
- [x] [Review][Patch] Document type edit/toggle omits required `slug` from the PUT payload, causing backend validation failure on every update [frontend/app/pages/admin/workflow-docs.vue:109]
- [x] [Review][Patch] Story 5.7 did not add page-mount smoke tests for `/merchants`, `/audit`, or `/admin/workflow-docs`, despite AC-10 requiring smoke tests for new pages [frontend/app/tests/unit/pages/customs.queue.test.ts:1]
- [x] [Review][Patch] `npm run typecheck` is not clean and includes Story 5.7 test/type issues, so the story cannot substantiate its no-regressions claim from type-aware verification [frontend/app/tests/unit/pages/customs.queue.test.ts:14]

### Review Patch Resolution Notes
- Fixed `/api/audit` to return `{ data, meta }` pagination payload and updated backend audit tests.
- Updated `/audit` to use production `AuditAction` enum values for filters and labels.
- Included immutable `slug` in document type update payloads.
- Added Story 5.7 smoke coverage for `/merchants`, `/audit`, and `/admin/workflow-docs`.
- Removed Story 5.7-specific type errors from the reported typecheck set; global typecheck still fails on pre-existing unrelated project errors and the existing Nuxt/Volar resolver issue.

## Dev Notes

### Parity Scope

This story is a **parity/signoff** story. It does NOT re-implement existing pages. Focus is:
1. Plug navigation dead-links by building the 4 missing pages.
2. Remove `/bank/users` nav link (no production role owner).
3. Document demo-only exclusions.

### Demo-Only Exclusions (do NOT implement)
- Role switcher in header
- Fake demo login picker
- Mock-state in-memory edits
- Demo reset tools in settings
- Prototype footer "demo/prototype" label
- Theme/language toggles (not confirmed for production)
- UI-only auth shortcuts
- `/admin/roles` editable matrix (conflicts with canonical fixed role model)

### Backend APIs Available
- `GET /api/merchants` — paginated, bank-scoped in production policy
- `POST /api/merchants`, `PUT /api/merchants/{id}`, `DELETE /api/merchants/{id}`
- `GET /api/audit` — paginated audit log, CBY_ADMIN scoped
- `GET /api/document-types` — list document types
- `POST /api/document-types`, `PUT /api/document-types/{id}`, `DELETE /api/document-types/{id}`
- Customs queue: reuse `GET /api/requests?status=EXECUTIVE_APPROVED` scoped to COMMITTEE_DIRECTOR

### Composables Available
- `useMerchants` — only has `fetchMerchants`; extend with create/update
- `useApi` — base for all API calls
- `useRequests` — extend or reuse for customs queue
- New: `useAudit`, `useDocumentTypes`

### Design Pattern (from existing pages)
- Page background: `bg-[#f5f5f7] p-6`
- Card: `rounded-[12px] bg-white shadow-sm`
- Table: use existing `RequestsTable` pattern as visual reference
- Loading: spinning border div centered in card
- Empty: icon + message centered in card
- Error: red-tinted card with retry option

### `/bank/users` Decision
Per the lovable-prototype-current-project-audit: the canonical role enum has no `BANK_ADMIN` self-service user management. Remove from NAV_ITEMS or restrict to CBY_ADMIN only. Do NOT create a new `/bank/users` page — this requires a formal product decision that is out of scope for this story.

### Test Pattern
Follow existing test files under `frontend/app/tests/unit/`. Composable tests go in `tests/unit/composables/`, page smoke tests in `tests/unit/pages/` (create directory if missing).

## Dev Agent Record

### Debug Log
_To be filled during implementation_

### Completion Notes

**Story 5.7 — Route-by-Route Parity Checklist (2026-05-18)**

| Lovable Route | Production Route | Status | Notes |
|---|---|---|---|
| `/` (dashboard) | `/dashboard` | ✅ Implemented | Role dashboards active for all 7 roles |
| `/requests` | `/requests` | ✅ Implemented | List with filters, search, status badges |
| `/requests/new` | `/requests/new` | ✅ Implemented | RequestForm with VeeValidate+Zod |
| `/requests/:id` | `/requests/[id]` | ✅ Implemented | Detail page with tabs: overview, documents, timeline, votes, audit |
| `/requests/:id/swift` | `/requests/[id]/swift` | ✅ Implemented | SWIFT upload for SWIFT_OFFICER |
| `/customs` | `/customs` | ✅ Built (this story) | Standalone EXECUTIVE_APPROVED queue for COMMITTEE_DIRECTOR |
| `/customs/:id/print` | `/requests/[id]/customs-preview` | ✅ Implemented | Customs print preview page |
| `/merchants` | `/merchants` | ✅ Built (this story) | CBY_ADMIN list/create/edit merchants |
| `/reports` | `/reports` | ✅ Implemented (Story 5.6) | KPI strip, filters, presets, table, CSV/PDF export |
| `/audit` | `/audit` | ✅ Built (this story) | Paginated audit log with action/date filters for CBY_ADMIN |
| `/notifications` | `/notifications` | ✅ Implemented (Story 5.3) | Notification list with mark-read, bell badge in header |
| `/admin/cby-staff` | `/users` | ✅ Implemented (Story 1.4) | Production uses `/users` path |
| `/admin/entities` | `/banks` | ✅ Implemented (Story 1.4) | Production uses `/banks` path |
| `/admin/workflow-docs` | `/admin/workflow-docs` | ✅ Built (this story) | Document type CRUD for CBY_ADMIN |
| `/admin/roles` | — | 🚫 Excluded | Conflicts with canonical fixed role model; not a production feature |
| `/bank/users` | — | 🚫 Removed from nav | No production role owner; BANK_REVIEWER cannot self-administer users |
| `/profile` | `/profile` | ✅ Implemented (Story 5.2) | Profile page exists |
| `/settings` | `/settings` | ✅ Implemented (Story 5.2) | Settings with notification preferences |
| `/login` | `/login` | ✅ Implemented (Story 1.3) | Sanctum CSRF auth flow |

**Demo-Only Exclusions (NOT implemented):**
- Role switcher header component
- Fake demo login picker (any-user sign-in)
- Mock-state in-memory edits from `lovable/src/lib/mock.ts`
- Demo reset tools in settings
- Prototype footer "demo environment" label
- Theme/language toggle (not confirmed for production)
- `/admin/roles` editable permissions matrix

**New Tests Added This Story:** 37 new + 1 updated
- `useMerchants` composable: 10 tests (create/update/fetch + filters)
- Customs queue page logic: 8 tests
- `useAudit` composable: 8 tests
- `useDocumentTypes` composable: 8 tests
- Prototype parity page smoke tests: 3 tests
- Nav-items `/bank/users` removal: 1 test updated

**Final Test Suite:** 816 tests, 0 failures (was 784)

### Implementation Plan

1. Task 1: Audited NAV_ITEMS — removed dead `/bank/users` link (no production page). All 13 nav-items tests pass.
2. Task 2: Extended `useMerchants` with `createMerchant`/`updateMerchant` + filter support. Built `merchants.vue` mirroring `users.vue` pattern with search bar and modal form. CBY_ADMIN only.
3. Task 3: Built `customs/index.vue` standalone queue page using existing `fetchRequests(status=EXECUTIVE_APPROVED)` and `generateCustomsDeclaration`. No new composable needed.
4. Task 4: Added `AuditLog` type to models.ts. Created `useAudit` composable. Built `audit.vue` with action/date filters and pagination.
5. Task 5: Added `DocumentType` type to models.ts. Created `useDocumentTypes` composable. Built `admin/workflow-docs.vue` with create/edit/toggle-active support.
6. Task 6: Full suite green (813/0). Parity checklist documented above.

## File List

**Modified:**
- `frontend/app/constants/workflow.ts` — removed `/bank/users` NAV_ITEM
- `frontend/app/composables/useMerchants.ts` — extended with `createMerchant`, `updateMerchant`, `MerchantFilters`
- `frontend/app/types/models.ts` — updated `Merchant` type to full API shape; added `AuditLog`, `DocumentType` types
- `frontend/app/tests/unit/constants/nav-items.test.ts` — updated `/bank/users` test to assert removal
- `frontend/app/tests/unit/composables/useMerchants.test.ts` — expanded to 10 tests covering fetch/create/update

**Created:**
- `frontend/app/pages/merchants.vue` — merchant list/create/edit page (CBY_ADMIN)
- `frontend/app/pages/customs/index.vue` — customs queue page (COMMITTEE_DIRECTOR)
- `frontend/app/pages/audit.vue` — audit compliance log page (CBY_ADMIN)
- `frontend/app/pages/admin/workflow-docs.vue` — document type rules management (CBY_ADMIN)
- `frontend/app/composables/useAudit.ts` — paginated audit log fetcher with filters
- `frontend/app/composables/useDocumentTypes.ts` — document types CRUD
- `frontend/app/tests/unit/composables/useAudit.test.ts` — 8 tests
- `frontend/app/tests/unit/composables/useDocumentTypes.test.ts` — 8 tests
- `frontend/app/tests/unit/pages/customs.queue.test.ts` — 8 tests

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-05-18 | Story created and fully implemented | BMAD dev-story |
| 2026-05-18 | Removed /bank/users from nav, built /merchants, /customs, /audit, /admin/workflow-docs pages; added useAudit, useDocumentTypes composables; 29 new tests; 813 total green | Claude |
