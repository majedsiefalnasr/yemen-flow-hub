# Story 5.4: Global Search Phase 1

## Story

**As** an authenticated user,
**I want** global search across the entities I am allowed to see,
**So that** I can quickly find requests, banks, customs declarations, users, and workflow records without navigating through multiple queues.

---

## Acceptance Criteria

### AC1 — Backend: Scoped Search Endpoint
**Given** I am authenticated  
**When** I call `GET /api/search?q=<query>`  
**Then** I receive results grouped by entity type (requests, users, banks, customs)  
**And** results are always scoped to my role and organization before serialization  
**And** bank-scoped users never see records outside their own bank  
**And** the response shape is `{ data: { requests: [...], users: [...], banks: [...], customs: [...] } }`

### AC2 — Backend: Role-Scoped Request Results
**Given** I search by a query string  
**When** request results are returned  
**Then** bank-scoped roles (DATA_ENTRY, BANK_REVIEWER, BANK_ADMIN, SWIFT_OFFICER) only see requests from their own bank  
**And** CBY roles see requests from all banks  
**And** search matches: `reference_number`, `supplier_name`, `goods_description`, `port_of_entry`

### AC3 — Backend: Admin-Only User Search
**Given** I am CBY_ADMIN or BANK_ADMIN  
**When** user results are returned  
**Then** `CBY_ADMIN` sees all users respecting the existing user-management policy  
**And** `BANK_ADMIN` sees only own-bank `DATA_ENTRY` and `BANK_REVIEWER` users  
**And** non-admin roles receive an empty `users` array (never a 403 — just empty)

### AC4 — Backend: Bank Results
**Given** I am CBY_ADMIN  
**When** bank results are returned  
**Then** all active banks matching the query are returned  
**And** non-CBY roles receive an empty `banks` array

### AC5 — Backend: Customs Declaration Results
**Given** I search with a query  
**When** customs results are returned  
**Then** bank-scoped users only see customs declarations belonging to their bank's requests  
**And** CBY roles see all matching customs declarations  
**And** search matches: `declaration_number`

### AC6 — Backend: Recent Searches Stored Per User
**Given** I perform a search with a non-empty query  
**When** results are returned  
**Then** the query string is persisted in my `user_preferences` as `recent_searches` (array, max 10, newest first, deduped)  
**And** `GET /api/search/recent` returns my recent searches array

### AC7 — Frontend: Search Input in AppHeader (Debounced)
**Given** I am authenticated on any page  
**When** I type 2+ characters in the search bar in AppHeader  
**Then** a debounced request is sent to `GET /api/search?q=<query>` after 350ms  
**And** results appear in a dropdown overlay  
**And** clearing the input hides the dropdown  
**And** the search input is accessible (aria-label, keyboard dismiss with Escape)

### AC8 — Frontend: Grouped Results with Filter Chips
**Given** search results are returned  
**When** the results dropdown is shown  
**Then** results are grouped by entity type with Arabic section labels  
**And** a "الكل / All" chip and per-type chips allow filtering the visible results  
**And** each result item shows a label, secondary detail, and an entity-type icon  
**And** clicking a result deep-links to the correct production route and closes the dropdown

### AC9 — Frontend: Empty State and Loading State
**Given** a search query returns zero results  
**When** the dropdown is shown  
**Then** an Arabic empty-state message "لا توجد نتائج" is shown  
**And** a loading spinner is shown while the API call is in-flight

### AC10 — Frontend: Recent Searches in Dropdown
**Given** I focus the search input with an empty query  
**When** I have prior recent searches  
**Then** recent searches are shown under "عمليات البحث الأخيرة"  
**And** clicking a recent search term populates the input and triggers search  
**And** if no recent searches exist, the dropdown is hidden on focus

---

## Tasks / Subtasks

### Task 1: Backend — SearchController + Route

- [x] 1.1 Create `backend/app/Http/Controllers/Api/SearchController.php` with `search()` and `recent()` methods
- [x] 1.2 Add `GET /api/search` and `GET /api/search/recent` routes in `routes/api.php` under `auth:sanctum`
- [x] 1.3 Write test: unauthenticated request returns 401
- [x] 1.4 Write test: empty query `?q=` returns all groups empty (no DB-intensive full-table query)

### Task 2: Backend — Role-Scoped Request Search

- [x] 2.1 In `SearchController::search()`, build an `ImportRequest` query using the existing `scopeForUser()` scope
- [x] 2.2 Apply LIKE search across `reference_number`, `supplier_name`, `goods_description`, `port_of_entry` (OR conditions) — minimum 2 chars before querying
- [x] 2.3 Limit to 10 request results, return via `ImportRequestListResource`
- [x] 2.4 Write test: bank user only sees own-bank requests in results
- [x] 2.5 Write test: CBY user sees all-bank requests in results
- [x] 2.6 Write test: short query `?q=a` (< 2 chars) returns empty requests array without DB query

### Task 3: Backend — Admin-Only User Search

- [x] 3.1 In `SearchController::search()`, run user search only for CBY_ADMIN and BANK_ADMIN roles
- [x] 3.2 BANK_ADMIN: scope to `bank_id == user->bank_id` AND `role IN [DATA_ENTRY, BANK_REVIEWER]`
- [x] 3.3 CBY_ADMIN: no bank scope restriction; respect existing UserPolicy `viewAny`
- [x] 3.4 All other roles: return empty `users` array
- [x] 3.5 Limit to 10 user results, return via `UserResource`
- [x] 3.6 Write test: BANK_ADMIN sees only own-bank manageable users
- [x] 3.7 Write test: CBY_ADMIN sees all-org users
- [x] 3.8 Write test: DATA_ENTRY gets empty users array (no 403)

### Task 4: Backend — Bank Search (CBY Admin Only)

- [x] 4.1 In `SearchController::search()`, run bank search only for CBY_ADMIN
- [x] 4.2 Search `banks` by `name`, `code` (LIKE) — Bank model has single `name` field, not `name_ar`/`name_en`
- [x] 4.3 Non-CBY roles receive empty `banks` array
- [x] 4.4 Limit to 10 bank results
- [x] 4.5 Write test: CBY_ADMIN gets bank results; bank-scoped user gets empty banks array

### Task 5: Backend — Customs Declaration Search

- [x] 5.1 In `SearchController::search()`, search `customs_declarations` by `declaration_number` (LIKE)
- [x] 5.2 For bank-scoped users: join to `import_requests` and filter by `bank_id = user->bank_id`
- [x] 5.3 For CBY roles: no bank restriction
- [x] 5.4 Limit to 10 customs results
- [x] 5.5 Write test: bank user only sees own-bank customs; CBY user sees all customs

### Task 6: Backend — Recent Searches Storage

- [x] 6.1 After a successful search with `q` ≥ 2 chars, update `user_preferences.recent_searches` array: prepend new query, dedup by exact match, trim to 10 items
- [x] 6.2 Add `GET /api/search/recent` endpoint returning `{ data: { recent_searches: string[] } }`
- [x] 6.3 Write test: after 3 searches, `GET /api/search/recent` returns them newest-first
- [x] 6.4 Write test: duplicate query is deduped (moved to front, not doubled)
- [x] 6.5 Write test: more than 10 unique queries trims to 10

### Task 7: Frontend — useSearch Composable

- [x] 7.1 Create `frontend/app/composables/useSearch.ts`
  - `search(query: string)` → `GET /api/search?q=<query>` (debounced internally via `useDebounceFn` or manual `setTimeout`)
  - `fetchRecent()` → `GET /api/search/recent`
  - Reactive state: `results` (grouped), `recentSearches`, `loading`, `error`, `activeFilter` (chip selection)
  - Debounce delay: 350ms, minimum 2 chars before request fires
- [x] 7.2 Add `SearchResults` and `SearchResultItem` types to `frontend/app/types/models.ts`
- [x] 7.3 Write unit tests for `useSearch`: triggers debounced call with 2+ chars; does not call API for 1 char; `fetchRecent` populates `recentSearches`

### Task 8: Frontend — GlobalSearch Component

- [x] 8.1 Create `frontend/app/components/layout/GlobalSearch.vue`
  - Input field: placeholder "بحث..." with search icon, aria-label, Escape to close
  - On input ≥ 2 chars: triggers `search()` from `useSearch()`
  - On focus with empty input: shows recent searches section (if any)
  - Dropdown overlay: positioned below input, z-index above header, closes on outside click
- [x] 8.2 Render grouped results inside dropdown:
  - Section header per type: "الطلبات", "المستخدمون", "البنوك", "البيانات الجمركية"
  - Filter chips: "الكل", and one per entity type that has results
  - Each result item: entity icon + primary label + secondary detail (e.g., status badge for requests)
- [x] 8.3 Deep-link navigation on result click:
  - Request → `/requests/{id}`
  - User → `/users` (no individual user detail page yet; link to users list with ID highlight is acceptable)
  - Bank → `/banks`
  - Customs → `/requests/{request_id}` (request detail page)
- [x] 8.4 Empty state: "لا توجد نتائج لـ «{query}»" when all groups are empty
- [x] 8.5 Loading state: spinner shown during in-flight API call
- [x] 8.6 Recent searches section: clickable items populate input and trigger search
- [x] 8.7 Write component tests: renders results dropdown; chip filters group visibility; Escape closes dropdown; recent searches show on empty-focus; empty state renders when no results

### Task 9: Frontend — Wire GlobalSearch into AppHeader

- [x] 9.1 Import and render `<GlobalSearch />` inside `AppHeader.vue` in `header-center` div with `flex: 1` (desktop)
- [x] 9.2 On mobile (≤600px): show a search icon button instead of the inline input; tapping it expands a full-width search bar overlaying the header
- [x] 9.3 Write AppHeader component test: GlobalSearch mock importable; mobile search toggle state logic verified

### Review Findings

- [x] [Review][Decision] Clearing input behavior conflicts between AC7 and AC10 — resolved to show recent searches after clear while input remains focused (align AC10). [frontend/app/components/layout/GlobalSearch.vue:22]
- [x] [Review][Patch] Mobile overlay renders hidden search component at ≤600px due to component-level media rule. [frontend/app/components/layout/GlobalSearch.vue:411]
- [x] [Review][Patch] Duplicate `global-search-wrapper` DOM id across desktop and mobile instances can break click-outside handling. [frontend/app/components/layout/GlobalSearch.vue:100]
- [x] [Review][Patch] Debounced search can show stale results from out-of-order async responses. [frontend/app/composables/useSearch.ts:31]
- [x] [Review][Patch] CBY admin bank search does not restrict to active banks (`is_active = true`). [backend/app/Http/Controllers/Api/SearchController.php:127]
- [x] [Review][Patch] BANK_ADMIN search path should guard against null `bank_id` to prevent matching unassigned users. [backend/app/Http/Controllers/Api/SearchController.php:106]
- [x] [Review][Patch] GlobalSearch/AppHeader tests do not exercise the actual component behavior required by AC7-AC10 [frontend/app/tests/unit/components/GlobalSearch.test.ts:51]

---

## Dev Notes

### Architecture Context

**What already exists (do NOT recreate):**

- `backend/app/Models/ImportRequest.php` — `scopeForUser(Builder $query, User $user)` at line 189 scopes requests to `bank_id` for bank users, unrestricted for CBY users. Use this directly in `SearchController`.
- `backend/app/Models/User.php` — `isBankUser()`, `isCbyUser()`, `hasRole(UserRole)` helpers exist. The `BANK_ADMIN` role was added in Story 5.1 and is included in `UserRole::isBankRole()`.
- `backend/app/Http/Controllers/Api/ImportRequestController.php` — existing `?search=` on `GET /api/requests` performs LIKE on `reference_number` and `supplier_name` only. The global search needs `goods_description` and `port_of_entry` added and is a separate endpoint.
- `backend/app/Http/Resources/ImportRequestListResource.php` and `UserResource.php` — reuse for search results.
- `backend/app/Support/ApiResponse.php` — use for all response shapes.
- `backend/app/Services/Settings/UserPreferencesService.php` — handles `user_preferences` JSON column on users. Recent searches should be stored as `recent_searches` key inside that JSON. Check the service for existing `get()` and `set()` patterns before writing custom update logic.
- `frontend/app/composables/useApi.ts` — provides `get()` helper wrapping `$fetch` with Sanctum credentials. Use this in `useSearch.ts`.
- `frontend/app/components/layout/AppHeader.vue` — existing header with notification bell. GlobalSearch goes in the `header-start` or center area. Do NOT restructure the existing header CSS; inject the new component alongside existing elements.
- `frontend/app/constants/workflow.ts` — check for existing entity-icon mapping patterns before creating new ones.
- `frontend/app/types/models.ts` — add `SearchResults` type to the existing file.

**Key patterns to follow:**

- All backend scoping must happen at query level, never filtered post-retrieval.
- Use `->limit(10)` not `->paginate()` for search results — the dropdown is not paginated.
- Recent searches update must be fire-and-forget in the controller (do not delay search response for preference write). Use `try/catch` around the preference update.
- Debounce in the frontend: use plain `setTimeout`/`clearTimeout` pattern (no extra dependency). 350ms delay. Min 2 chars before API call.
- The `SearchController` must NOT query `audit_logs` for non-CBY_ADMIN users. Audit search is out of scope for this story.
- SWIFT reference search: the `import_requests` table has `swift_uploaded_at` but no `swift_reference` column. Story 5.4 searchable entities include "SWIFT references where stored" — in practice this means the `reference_number` covers SWIFT context. Do not add a column; just note in completion notes that SWIFT reference search maps to request `reference_number`.

**Role visibility matrix for search:**

| Entity | DATA_ENTRY / BANK_REVIEWER / BANK_ADMIN / SWIFT_OFFICER | SUPPORT_COMMITTEE / EXECUTIVE_MEMBER / COMMITTEE_DIRECTOR | CBY_ADMIN |
|--------|---|---|---|
| Requests | own bank only | all (no bank restriction) | all |
| Users | BANK_ADMIN: own bank DATA_ENTRY+BANK_REVIEWER; others: empty | empty | all (per UserPolicy) |
| Banks | empty | empty | all |
| Customs | own bank only | all | all |

**Deep-link routes:**

- Request result → `/requests/{id}` (existing detail page)
- User result → `/users` (list page; no individual user route exists yet)
- Bank result → `/banks` (list page)
- Customs result → `/requests/{request_id}` (customs data visible on the request detail tabs)

**GlobalSearch component placement:**

In `AppHeader.vue` the layout is `flex-direction: row-reverse` (RTL). `header-start` is visually on the right (mobile menu). `header-end` is on the left (bell + user meta). The search bar sits between them — add a `header-center` div with `flex: 1` and render `<GlobalSearch />` inside it. Max width 480px on desktop, hidden/icon-only on mobile.

**Keyboard shortcut:** The epics spec mentions keyboard shortcut support "if it does not conflict with browser/system shortcuts and is accessible." Do NOT implement a keyboard shortcut in this story — it is explicitly deferred. The Escape key to close the dropdown is required (it is dismiss, not open).

**Test count reference at story start:**

- Backend: ~508 test methods (from php artisan test --list-tests)  
- Frontend: 718 total tests, 715 passing, 3 pre-existing failures (enums.test.ts UserRole count mismatch from BANK_ADMIN addition in Story 5.1; nav-items.test.ts)

### Test Patterns

**Backend:**
- All controller tests use `RefreshDatabase`, `actingAs($user)`, and seeded users via `DatabaseSeeder`.
- For scoping tests: create two banks, two users (one per bank), create requests for each bank, assert search results only include the authenticated user's bank records.
- For the recent searches tests: call `GET /api/search?q=query` multiple times, then assert `GET /api/search/recent` response.
- File: `backend/tests/Feature/Search/SearchControllerTest.php`

**Frontend:**
- Follow the pattern in `frontend/app/tests/unit/composables/useNotifications.test.ts`: `vi.stubGlobal('useRuntimeConfig', ...)` + mock `$fetch` at the top.
- For `GlobalSearch.vue` component tests: use `@vue/test-utils` `shallowMount`; mock `useSearch` composable return values.
- For `AppHeader.vue` tests: follow existing `frontend/app/tests/unit/components/AppHeader.test.ts` pattern.

---

## Dev Agent Record

### Implementation Plan

Red-green-refactor approach. Backend Tasks 1–6 implemented first (SearchController with role-scoped queries for all 4 entity types + recent searches + route registration). Frontend Tasks 7–9 followed: types → useSearch composable → GlobalSearch component → AppHeader wiring.

### Debug Log

- **CustomsDeclaration test helper**: `pdf_path` is NOT NULL in migration. Test helper was missing `pdf_path`, causing SQL integrity error. Fixed by adding `'pdf_path' => 'customs/test/' . $declarationNumber . '.pdf'` to the helper.
- **Bank model has single `name` field**: Story spec mentioned `name_ar`/`name_en` but the Bank model only has `name`. SearchController was implemented accordingly and the task note was updated.
- **RTK output truncation**: `php artisan test` shows "431 deprecated, 22 passed" due to RTK proxy truncating output. Verified via `--list-tests` (507 methods) and assertion count delta (+56 matching exactly our 22 test methods).
- **SWIFT reference column**: No `swift_reference` column on `import_requests`. SWIFT search maps to `reference_number` as documented in completion notes.

### Completion Notes

- All 10 ACs satisfied.
- Backend: 22 new tests (SearchControllerTest) — 56 assertions, all green. Backend assertion count 1208 → 1264.
- Frontend: 32 new tests (12 useSearch composable, 16 GlobalSearch component, 4 AppHeader.GlobalSearch) — all green; total 750 tests, 747 passing (3 pre-existing failures from Story 5.1 BANK_ADMIN addition unchanged).
- `SearchController` enforces all role/org scoping at query level: `scopeForUser()` for requests, explicit `bank_id` + role filter for BANK_ADMIN users, CBY_ADMIN-only for banks, `whereHas('request', ...)` for customs.
- Recent searches stored as `user_preferences.recent_searches[]` (fire-and-forget, max 10, deduped).
- `GlobalSearch.vue` implements: debounced input (350ms), grouped result sections, filter chips, empty state, loading spinner, recent searches on empty focus, Escape to close, outside-click to close, RTL-first layout.
- `AppHeader.vue` gains `header-center` flex region for desktop search; mobile (≤600px) shows search icon button + full-width overlay.
- SWIFT reference search note: no `swift_reference` column exists — SWIFT context is covered by `reference_number` LIKE search which is already implemented.

---

## File List

### Backend

- `backend/app/Http/Controllers/Api/SearchController.php` — new
- `backend/routes/api.php` — added `GET search/recent`, `GET search` routes + `SearchController` import
- `backend/tests/Feature/Search/SearchControllerTest.php` — new (22 tests)

### Frontend

- `frontend/app/types/models.ts` — added `SearchEntityType`, `SearchRequestResult`, `SearchUserResult`, `SearchBankResult`, `SearchCustomsResult`, `SearchResults` types
- `frontend/app/composables/useSearch.ts` — new
- `frontend/app/components/layout/GlobalSearch.vue` — new
- `frontend/app/components/layout/AppHeader.vue` — added GlobalSearch, mobile search icon button, header-center layout region
- `frontend/app/tests/unit/composables/useSearch.test.ts` — new (12 tests)
- `frontend/app/tests/unit/components/GlobalSearch.test.ts` — new (16 tests)
- `frontend/app/tests/unit/components/AppHeader.GlobalSearch.test.ts` — new (4 tests)

---

## Change Log

| Date | Change |
|------|--------|
| 2026-05-17 | Story created — Status: ready-for-dev |
| 2026-05-17 | Implementation complete — all 10 ACs satisfied, 22 new backend tests + 32 new frontend tests green |

---

## Status

done
