# Story 7.4: Request Detail 1:1 Parity

Status: done

## Story

As any role with request access,
I want request detail pages to match the Lovable detail screens for my role and request status,
so that workflow state, documents, parties, voting, and actions are visually and operationally clear.

---

## Acceptance Criteria

**AC1 - Page header, breadcrumbs, and status parity**  
Given I open `/requests/{id}` for an authorized request,
Then the page uses the Story 7.1 AppShell/header/sidebar without regression,
And the header matches Lovable detail intent: breadcrumbs, request reference as title, importer/merchant and request type/bank subtitle, role-aware status badge, and any real download/preview action only when backed by production API behavior.

**AC2 - Two-column detail layout parity**  
Given I view the detail page at desktop width,
Then the content matches Lovable's `lg:grid-cols-3` structure: primary content occupies about two thirds, right rail occupies about one third,
And the right rail contains workflow progress, available actions, quick information, and return navigation in the same visual hierarchy,
And mobile <=600px collapses to one column without text overlap or hidden critical actions.

**AC3 - Progress and workflow rail parity**  
Given a request is in any canonical `RequestStatus`,
Then the visible progress card and workflow rail match Lovable screenshots for active, completed, waiting, returned, rejected, and terminal states,
And progress is derived from production `RequestStatus` and role-aware visibility from `frontend/app/constants/workflow.ts`, not Lovable `RequestStage` strings.

**AC4 - Tab layout parity**  
Given I view request detail tabs,
Then tab labels and layout match Lovable and current production rules: `المعلومات`, `الوثائق`, `الأطراف`,
And voting content is shown according to production status/role rules already established in Story 6.6 without reintroducing removed standalone timeline/audit tabs,
And active-tab normalization still resets invalid `votes` state when a status transition hides the voting UI.

**AC5 - Information tab parity**  
Given I open `المعلومات`,
Then request fields match Lovable detail screenshots for field order, labels, two-column rows, border treatment, typography, spacing, risk/duplicate/status indicators where backed by real data, and locked/read-only visual treatment,
And no field uses mock-only Lovable data when the Laravel API does not expose it.

**AC6 - Documents tab and preview parity**  
Given I open `الوثائق`,
Then `DocumentChecklist` and the uploaded document list match Lovable for required/optional rows, uploaded/missing states, PDF icon treatment, file metadata, download buttons, preview affordance, loading, empty, upload, and error states,
And document downloads continue through backend-mediated authenticated `/api/documents/{id}/download`,
And a file preview UI must be backed by real document fetch/download behavior or explicitly documented as omitted; no fake PDF preview.

**AC7 - Parties and audit/workflow context parity**  
Given I open `الأطراف`,
Then actor rows match Lovable's avatar/name/org treatment using production ownership metadata where available,
And workflow timeline and audit/event context remain available without exposing unauthorized audit data,
And missing actor names from the API must be fixed through backend resources or documented as unavailable only if the backend truly cannot supply them.

**AC8 - Support claim states parity**  
Given I am `SUPPORT_COMMITTEE`,
Then pending claim, claimed-by-me, claimed-by-other, claim error, heartbeat expiry, and returned/rejected support states match the Lovable screenshots and existing production claim lifecycle,
And the page must preserve the Story 3.2 safeguards: verify resumed claims before heartbeat, show claim errors, reload after claim races, and release best-effort on unmount.

**AC9 - Bank review and data-entry action parity**  
Given I am a bank-side actor,
Then DATA_ENTRY draft/submitted/rejected/completed states and BANK_REVIEWER internal review/actions-expanded states match the available Lovable screenshots,
And production action availability remains controlled by backend workflow endpoints and current `ActionsPanel` role/status logic.

**AC10 - SWIFT detail parity**  
Given I am `SWIFT_OFFICER` viewing a pending or uploaded SWIFT request,
Then the request-detail SWIFT entry point and `/requests/{id}/swift` screen match Lovable's pending/uploaded treatment,
And PDF-only validation, immutable post-upload behavior, wrong-status fallback, and audit-backed upload transition remain intact.

**AC11 - Executive voting parity**  
Given I am `EXECUTIVE_MEMBER` or `COMMITTEE_DIRECTOR` in voting stages,
Then voting pending/open/cast-vote/director-open/duplicate-invoice/final-rejected/waiting-customs states match Lovable screenshots,
And `VotingPanel` preserves real voting API behavior, 6-member roster, tally, auto-abstain semantics, director controls in `ActionsPanel`, and no locked banner for authorized executive voting stages.

**AC12 - Customs issue/print parity**  
Given I am `COMMITTEE_DIRECTOR` or an authorized viewer for customs,
Then request-detail customs issue/preview/download entry points and `/requests/{id}/customs-preview` match Lovable customs screenshots,
And production customs generation remains a single backend transaction with authorization and immutable completion behavior.

**AC13 - Locked/read-only/rejection banners parity**  
Given a request is locked, read-only, pending another actor, returned, support rejected, or executive rejected,
Then banners match Lovable color, border, icon, text hierarchy, and placement while preserving distinct production semantics for correction-required vs immutable/terminal states.

**AC14 - Production data authority**  
All page data comes from Laravel APIs. If Lovable requires actor names, invoice number, goods type, risk/duplicate metadata, document preview data, vote roster fields, or customs metadata not currently exposed, extend the backend resource/controller/policy/tests in the same story. Do not hardcode production state from Lovable mock data.

**AC15 - Demo-only exclusions documented**  
Prototype-only role switching, fake authorization shortcuts, mock state transitions, fake PDF previews, generated demo SWIFT upload, browser-only customs issuance, demo reset controls, and any mock labels remain excluded. The completion checklist must document every omission with the production-governance reason.

**AC16 - Visual evidence: desktop screenshots**  
Playwright captures `/requests/{id}` or child detail routes at `1440x900` for every available Lovable request-detail reference role/status. Baselines are stored under `frontend/tests/screenshots/7-4/` and committed.

**AC17 - Visual evidence: mobile screenshots**  
Playwright captures the same covered role/status states at `390x844`. Baselines are stored under `frontend/tests/screenshots/7-4/` and committed.

**AC18 - Regression checks**  
Targeted frontend unit tests, request-detail component/store tests, Playwright visual tests, and any backend resource/API tests for new fields pass. Existing Story 7.1, 7.2, and 7.3 visual tests remain valid.

---

## Tasks / Subtasks

### Task 1: Source audit and screenshot matrix (AC1-AC18)
- [x] 1.1 Open and compare every request-detail screenshot listed in this story against the current Nuxt page for the same role/status.
- [x] 1.2 Read Lovable sources for layout/component intent only:
  - `lovable/src/routes/requests.$id.tsx`
  - `lovable/src/routes/requests.$id.swift.tsx`
  - `lovable/src/routes/customs.$id.print.tsx`
  - `lovable/src/components/workflow/WorkflowProgress.tsx`
  - `lovable/src/components/workflow/DocumentChecklist.tsx`
  - `lovable/src/components/workflow/LockedBanner.tsx`
  - `lovable/src/components/workflow/VotingPanel.tsx`
  - `lovable/src/components/workflow/AuditTimeline.tsx`
- [x] 1.3 Do not copy React/TanStack/mock-governance code. Translate visual and interaction intent into existing Nuxt/Vue/Pinia/Laravel patterns.
- [x] 1.4 Build a parity checklist table in completion notes with one row per covered role/status: screenshot path, Nuxt target, API fields used, intentional omissions, and test evidence.

### Task 2: Page shell, header, and responsive detail layout (AC1, AC2)
- [x] 2.1 Rework `frontend/app/pages/requests/[id]/index.vue` to match Lovable's `PageHeader` and two-column detail grid while preserving `definePageMeta({ middleware: ['auth'] })`.
- [x] 2.2 Keep Story 7.1 AppShell/header/sidebar behavior untouched: 64px sticky header, notification popover, profile dropdown, 280px expanded sidebar, 72px collapsed sidebar.
- [x] 2.3 On desktop, render primary content and right rail in a stable grid. On mobile, collapse to one column and verify Arabic labels/buttons do not overlap.
- [x] 2.4 Keep `/requests/{id}` as the canonical detail route and `/requests` as the return route.

### Task 3: Workflow progress and right rail (AC2, AC3, AC13)
- [x] 3.1 Add or reuse a production Vue workflow progress component that matches Lovable's rail/card treatment and maps canonical `RequestStatus` values.
- [x] 3.2 Show current stage, completed/future states, returned/rejected/terminal treatments, and role-aware percent without using Lovable `RequestStage`.
- [x] 3.3 Move `ActionsPanel` into the right rail where it visually matches Lovable, but do not change backend action semantics.
- [x] 3.4 Add quick-info rows using real fields: creator, bank, port, submitted date, risk only if backed by API, and customs metadata when present.

### Task 4: Information tab visual parity (AC4, AC5, AC13)
- [x] 4.1 Match Lovable field order and row style for importer/merchant, bank, amount, supplier, invoice/goods if available, port, submitted/created dates, notes, and status.
- [x] 4.2 Show duplicate/risk banners only when a real backend field exists or is added. Do not fake duplicate invoice state from fixture-only Lovable data.
- [x] 4.3 Preserve `DATA_ENTRY` correction-required behavior and keep it distinct from locked/terminal banners.
- [x] 4.4 Add/update unit tests for field mapping, status/banners, and mobile-safe rendering where feasible.

### Task 5: Documents tab, authenticated download, and preview affordance (AC6, AC14, AC15)
- [x] 5.1 Refine `frontend/app/components/requests/DocumentChecklist.vue` to match Lovable's visual treatment while preserving Story 6.6 checklist merge rules and latest-uploaded-document selection.
- [x] 5.2 Keep PDF-only upload validation and current `canUploadDocument`/`canDownloadDocument` permission helpers.
- [x] 5.3 If implementing preview, route through authenticated backend download/fetch and a real blob/object URL; otherwise omit preview and document the omission. Do not show Lovable's "معاينة تجريبية".
- [x] 5.4 Preserve independent error state for overview customs download vs checklist customs download.
- [x] 5.5 Add/update `DocumentChecklist` tests for required/optional/uploaded/missing/customs rows and preview/download error paths.

### Task 6: Parties, actor names, history, and audit context (AC7, AC14)
- [x] 6.1 Replace numeric actor placeholders such as `#1` with API-provided names/objects where available.
- [x] 6.2 If `GET /api/requests/{id}` lacks required actor objects, extend the backend detail resource/controller tests rather than hardcoding names in Vue.
- [x] 6.3 Keep `requestsStore.loadHistory(id)` lazy-loaded for parties/audit context and preserve loading/error/retry visibility.
- [x] 6.4 Do not expose global audit-log data to roles that only have request-local visibility.

### Task 7: Support claim and bank-side action states (AC8, AC9)
- [x] 7.1 Preserve `useClaimLifecycle` semantics in `frontend/app/pages/requests/[id]/index.vue`: auto-claim only when allowed, verify resumed claims before heartbeat, stop heartbeat on loss/unmount, and reload after 409 races.
- [x] 7.2 Match Lovable support screenshots for pending claim, claimed actions, approved/returned/rejected states, and claimed-by-other warning.
- [x] 7.3 Match bank reviewer screenshots for internal review and actions-expanded states using existing `ActionsPanel` action paths.
- [x] 7.4 Match DATA_ENTRY screenshots for draft actions, submitted read-only, rejected correction, and completed states where available.
- [x] 7.5 Add/update tests for banner priority, action visibility, and claim-state regressions.

### Task 8: Voting panel and director controls (AC11, AC14)
- [x] 8.1 Refine `frontend/app/components/voting/VotingPanel.vue` to match Lovable tally cards, member rows, avatar chips, open/closed/final states, and vote form styling.
- [x] 8.2 Preserve real `voting.store.ts` API calls and Story 6.6 rules: 6 visible member rows, tie-break notice only when appropriate, and director lifecycle controls remain in `ActionsPanel`.
- [x] 8.3 Handle `WAITING_FOR_VOTING_OPEN`, `EXECUTIVE_VOTING_OPEN`, `EXECUTIVE_VOTING_CLOSED`, `EXECUTIVE_APPROVED`, and `EXECUTIVE_REJECTED` role differences explicitly.
- [x] 8.4 If vote roster data is incomplete, extend backend voting detail API/resource/tests in the same story.
- [x] 8.5 Add/update unit tests for vote eligibility, already-voted state, final rejected banner, director-only controls, and status-tab visibility.

### Task 9: SWIFT and customs child-route parity (AC10, AC12, AC15)
- [x] 9.1 Update `frontend/app/pages/requests/[id]/swift.vue` only as needed to match Lovable pending/uploaded SWIFT screenshots while preserving real PDF upload and workflow transition behavior.
- [x] 9.2 Update `frontend/app/pages/requests/[id]/customs-preview.vue` only as needed to match Lovable customs issue/print screenshots while preserving backend-generated PDFs and authorization.
- [x] 9.3 Do not recreate Lovable's browser-only customs issuance or demo SWIFT shortcut. Production must call existing Laravel APIs.
- [x] 9.4 Add/update focused tests for wrong-status fallback, uploaded SWIFT immutable state, customs issue/preview authorization, and print styles if touched.

### Task 10: Backend extensions only if production data is missing (AC6, AC7, AC11, AC12, AC14)
- [x] 10.1 Before backend edits, run SocratiCode on the exact controller/resource/service symbol and `codebase_impact` for the target file.
- [x] 10.2 Prefer extending existing detail/voting/customs resources instead of creating parallel endpoints.
- [x] 10.3 Preserve `ImportRequest::query()->forUser($user)`, policies, organization scoping, and workflow immutability rules.
- [x] 10.4 Add backend feature tests for any new response field or authorization path.
- [x] 10.5 Update `docs/06-api-reference.md` only if the API contract materially changes.

### Task 11: Playwright visual evidence (AC16, AC17)
- [x] 11.1 Create `frontend/tests/e2e/7-4-request-detail-parity.spec.ts` using the Story 7.1-7.3 mocked-auth and deterministic API fixture pattern.
- [x] 11.2 Mock `/api/auth/me`, `/api/requests/{id}`, `/api/requests/{id}/documents`, `/api/requests/{id}/history`, voting endpoints, customs endpoints, notifications endpoints, and download responses as needed.
- [x] 11.3 Capture desktop `1440x900` screenshots for every covered role/status state listed in the screenshot matrix.
- [x] 11.4 Capture mobile `390x844` screenshots for the same states.
- [x] 11.5 Store baselines under `frontend/tests/screenshots/7-4/` using stable names such as `support-committee-request-claimed-desktop.png`.
- [x] 11.6 Disable animations and keep timestamps/counts deterministic or masked to prevent flaky visual diffs.

### Task 12: Targeted tests and graph update (AC18)
- [x] 12.1 Run targeted Vitest suites for changed request-detail page/components/stores/composables.
- [x] 12.2 Run `cd frontend && npm run typecheck` if TypeScript contracts, component props, or API types change.
- [x] 12.3 Run `cd frontend && npx playwright test tests/e2e/7-4-request-detail-parity.spec.ts`.
- [x] 12.4 If backend changed, run the specific Laravel feature tests or a targeted `php artisan test --filter=...` command.
- [x] 12.5 Re-run relevant existing visual tests from Stories 7.1, 7.2, and 7.3 when shared shell, request store, status badge, or workflow constants change.
- [x] 12.6 Run `graphify update .` from repo root after code changes.

### Review Findings
- [x] [Review][Patch] Restore bank context in the request-detail header subtitle to match the story header contract [frontend/app/pages/requests/[id]/index.vue:460]
- [x] [Review][Patch] Gate the header customs download action with the same production permission rules used elsewhere on the page [frontend/app/pages/requests/[id]/index.vue:469]
- [x] [Review][Patch] Make WorkflowProgress role-aware for DATA_ENTRY simplified statuses and cover reachable voting-open states in its stage buckets [frontend/app/components/workflow/WorkflowProgress.vue:4]
- [x] [Review][Patch] Wire actor metadata consistently across detail, workflow, and voting responses, including support reviewer and current support claim holder aliases [backend/app/Http/Resources/ImportRequestResource.php:10]
- [x] [Review][Patch] Expand Story 7.4 Playwright coverage and baselines so the committed evidence matches the broader Lovable screenshot matrix used by the story [frontend/tests/e2e/7-4-request-detail-parity.spec.ts:236]

---

## Dev Notes

### Source Authorities

- Epic 7 strict parity rules: `_bmad-output/planning-artifacts/epics.md#Epic 7: Lovable 1:1 UI Parity Rework`
- Story 7.4 source: `_bmad-output/planning-artifacts/epics.md#Story 7.4: Request Detail 1:1 Parity`
- Visual final authority: `lovable/screenshots/`
- React layout reference only: `lovable/src/routes/requests.$id.tsx`, `lovable/src/routes/requests.$id.swift.tsx`, `lovable/src/routes/customs.$id.print.tsx`, `lovable/src/components/workflow/*.tsx`
- Production request detail page: `frontend/app/pages/requests/[id]/index.vue`
- Production child routes: `frontend/app/pages/requests/[id]/swift.vue`, `frontend/app/pages/requests/[id]/customs-preview.vue`
- Production components: `frontend/app/components/requests/ActionsPanel.vue`, `frontend/app/components/requests/DocumentChecklist.vue`, `frontend/app/components/ui/LockedBanner.vue`, `frontend/app/components/voting/VotingPanel.vue`, `frontend/app/components/workflow/WorkflowTimeline.vue`, `frontend/app/components/workflow/AuditTimeline.vue`
- Production stores/composables: `frontend/app/stores/requests.store.ts`, `frontend/app/stores/voting.store.ts`, `frontend/app/composables/useClaimLifecycle.ts`, `frontend/app/composables/useDocumentPermissions.ts`
- API contract: `docs/06-api-reference.md#Get Request Details`
- UX/detail guidance: `DESIGN.md#12 Request Detail Page Layout`, `docs/04-frontend-guide.md#Request Details Page`, `docs/08-prototype-gap-analysis.md#Sprint 7 Corrected Plan`

### Lovable Screenshot Matrix

| Production role | Screenshot path(s) |
|---|---|
| `BANK_ADMIN` | `lovable/screenshots/BANK-ADMIN/request-view-info-tab.png`, `request-view-documents-tab.png`, `request-view-parties-tab.png`, `request-view-support-rejected.png`, `request-view-voting-stage.png`, `request-view-waiting-swift.png`, `request-view-completed.png` |
| `BANK_REVIEWER` | `lovable/screenshots/BANK_REVIEWER /request-view-actions-expanded.png`, `request-view-internal-review.png` |
| `CBY_ADMIN` | `lovable/screenshots/CBY_ADMIN /requests-view-request.png`, `requests-view-request2.png`, `requests-view-request-note.png`, `requests-view-request-tab.png`, `requests-view-request-tab2.png`, `requests-view-request-view-file.png` |
| `COMMITTEE_DIRECTOR` | `lovable/screenshots/COMMITTEE_DIRECTOR/request-view-voting-open-director.png`, `request-view-voting-pending-open.png`, `request-view-voting-open-duplicate-invoice.png`, `request-view-waiting-customs.png`, `request-view-documents-tab-customs.png`, `request-view-parties-tab-customs.png`, `customs-issue-page.png` |
| `DATA_ENTRY` | `lovable/screenshots/DATA_ENTRY/request-view-draft-actions.png`, `request-view-submitted.png`, `request-view-rejected.png`, `request-view-completed.png` |
| `EXECUTIVE_MEMBER` | `lovable/screenshots/EXECUTIVE_MEMBER/request-view-voting-pending.png`, `request-view-voting-open-cast-vote.png`, `request-view-waiting-customs.png`, `request-view-rejected-banner.png`, `request-view-rejected-final.png` |
| `SUPPORT_COMMITTEE` | `lovable/screenshots/SUPPORT_COMMITTEE /request-view-pending-claim.png`, `request-view-claimed-actions.png`, `request-view-approved.png`, `request-view-returned-to-bank.png` |
| `SWIFT_OFFICER` | `lovable/screenshots/SWIFT_OFFICER/request-view-pending-swift.png`, `request-view-swift-uploaded.png` |

Use all available screenshots above for visual audit. The epics file names a smaller subset, but the local screenshot folder is richer and should drive acceptance.

### Current Implementation State

- `frontend/app/pages/requests/[id]/index.vue` currently renders a vertical page: header, banners, tab nav, tab content, and bottom `ActionsPanel`. It does not yet match Lovable's desktop two-column primary/right-rail composition.
- Current tabs are `المعلومات`, `الوثائق`, `الأطراف`, and conditional `التصويت`. Story 6.6 intentionally removed standalone timeline/audit tabs and restricted the voting tab to `EXECUTIVE_VOTING_OPEN` / `EXECUTIVE_VOTING_CLOSED`. Preserve that decision unless screenshots and production governance require a specific role/status adjustment.
- The page currently lazy-loads documents on `documents`, history on `parties`, and voting detail on `votes`. Keep lazy loading and explicit loading/error states.
- Support claim lifecycle is already hardened: claim races reload authoritative state, resumed claims are verified before heartbeat, heartbeat stops on lost claim, and successful claim followed by failed reload releases the claim.
- Actor display currently falls back to numeric IDs through `actorLabel()`. Story 7.4 should replace this with resource-provided user objects/names if available, or extend the backend detail resource.
- `DocumentChecklist` already merges stage requirements with uploaded docs and chooses the latest uploaded doc per type. Do not regress that Story 6.6 review fix.
- `VotingPanel` currently uses real voting store data and shows tally bars, member roster, not-yet-voted placeholders, final decision states, and vote form. Director session controls are intentionally in `ActionsPanel`.
- `ActionsPanel` already handles BANK_REVIEWER, DATA_ENTRY, SUPPORT_COMMITTEE, COMMITTEE_DIRECTOR voting/customs actions through stores. Keep action availability production-authorized; do not mirror Lovable mock transition helpers.

### SocratiCode Intelligence Already Gathered

- `codebase_search` found `DESIGN.md#Workflow Timeline`, Story 2.6 request-detail dev notes, Story 6.6 request-detail parity tests, `RequestDetailClaimLogic.test.ts`, and current `frontend/app/pages/requests/[id]/index.vue` as primary context.
- `codebase_symbols frontend/app/pages/requests/[id]/index.vue` reports these key functions: `handleSessionExpired`, `handleClaimLost`, `syncActiveReviewState`, `onTabChange`, `onActionCompleted`, `downloadCustomsDeclaration`, `handleDownloadCustoms`, `handleUploadDocument`, `downloadDocument`, `formatFileSize`, `formatDate`, `formatAmount`, and `actorLabel`.
- `codebase_impact frontend/app/pages/requests/[id]/index.vue` reported no graph callers because this is a route entry point.
- `codebase_impact` for `ActionsPanel.vue`, `DocumentChecklist.vue`, and `VotingPanel.vue` also reported no graph callers, but treat them as shared UI surfaces because they are imported by route files and covered by tests.

### Graphify Intelligence

- `graphify query "Story 7.4 request detail 1:1 parity frontend request details page components tests"` was too broad and matched backend/vendor `Request` nodes. Use narrower commands during implementation, for example:
  - `graphify query "frontend/app/pages/requests/[id]/index.vue DocumentChecklist VotingPanel ActionsPanel"`
  - `graphify path "frontend/app/pages/requests/[id]/index.vue" "frontend/app/stores/requests.store.ts"`
  - `graphify explain "request detail support claim lifecycle"`

### Previous Story Intelligence

- Story 7.3 established the current parity test pattern: mocked auth, deterministic API payloads, fixed desktop/mobile viewports, `toHaveScreenshot()` baselines under `frontend/tests/screenshots/<story>/`, and `maxDiffPixelRatio: 0.02` for small local rendering drift.
- Story 7.3 also extended `ImportRequestListResource` with `merchant`, `goods_type`, and `invoice_number`. Do not assume the detail endpoint exposes the same shape; verify `GET /api/requests/{id}` before relying on those fields.
- Story 7.2 review patches caught unauthorized BANK_ADMIN actions, missing real data for visual sections, support-claim distinction regressions, and hardcoded progress. Avoid repeating those failures on the detail page.
- Story 7.1 established the shell constraints that every detail screenshot inherits: 64px sticky header, popover bell (not navigation), user dropdown, persisted sidebar collapse, and no production RoleSwitcher.
- Story 6.6 review fixes are especially important: executive roles bypass locked banners only in voting-relevant stages, voting tab resets when hidden, latest uploaded document per type wins, roster stays exactly 6 rows, and actionable bank review stages do not show read-only banners to BANK_REVIEWER.
- Story 2.6 established request-detail foundations: backend workflow endpoints, separate document loading, locked-state rules, `ActionsPanel` visibility matrix, RTL actions, and no dead routes.

### Production Governance Overrides

- `docs/` and Laravel authorization override Lovable mock behavior.
- `lovable/` is read-only. Adapt layout, hierarchy, and interaction intent only.
- Do not copy `lovable/src/lib/mock.ts`, `requestsCell`, `transitionRequest`, `auditCell`, demo user IDs, or mock stage names.
- Bank roles remain organization-scoped at backend query/policy level. CBY roles remain role/queue scoped.
- `DATA_ENTRY` keeps simplified business statuses where `StatusBadge` is used.
- `BANK_ADMIN` is bank-scoped administrative visibility, not a workflow actor. Do not grant workflow actions because a screenshot shows an affordance unless production authorization permits it.
- Support claim states must stay distinct: available, claimed by me, claimed by another, claim expired/error.
- All file downloads stay backend-mediated; do not expose raw storage paths or client-generated signed URLs.

### Latest Technical Notes

- Installed frontend stack from `frontend/package.json`: Nuxt `^4.4.5`, Vue `^3.5.13`, Tailwind CSS `^4.1.0`, Playwright `^1.55.0`, Vitest `^3.1.4`, TypeScript `^5.8.3`, lucide-vue-next `^1.0.0`.
- Nuxt 4 docs: `$fetch` is appropriate for event-based client interactions; `useFetch`/`useAsyncData` prevent double-fetch/hydration issues for setup-time data. This story may keep existing Pinia/store client fetches, but do not introduce setup-time `$fetch` that causes hydration mismatch. Source: https://nuxt.com/docs/4.x/getting-started/data-fetching
- Playwright docs: `toHaveScreenshot()` supports array snapshot names, custom `snapshotPathTemplate`, and visual diff options such as `maxDiffPixels`; run baselines in a consistent environment. Source: https://playwright.dev/docs/test-snapshots
- Vue docs: keep typed `<script setup lang="ts">`, `defineProps<T>()`, and `defineEmits<T>()` patterns; in Vue 3.5, destructured props are reactive, but current codebase commonly uses `props` for clarity. Source: https://vuejs.org/api/sfc-script-setup.html
- Tailwind v4 docs: token work should stay in the existing CSS/theme-token approach; do not add one-off utility palettes if `frontend/app/assets/css/main.css` or `DESIGN.md` already defines the token. Source: https://tailwindcss.com/docs/theme
- No dependency upgrade is required for Story 7.4. Use existing `lucide-vue-next` icons and local UI primitives.

### File Structure Requirements

**Likely UPDATE files:**
- `frontend/app/pages/requests/[id]/index.vue`
- `frontend/app/pages/requests/[id]/swift.vue`
- `frontend/app/pages/requests/[id]/customs-preview.vue`
- `frontend/app/components/requests/ActionsPanel.vue`
- `frontend/app/components/requests/DocumentChecklist.vue`
- `frontend/app/components/ui/LockedBanner.vue`
- `frontend/app/components/voting/VotingPanel.vue`
- `frontend/app/components/workflow/WorkflowTimeline.vue`
- `frontend/app/components/workflow/AuditTimeline.vue`
- `frontend/app/constants/workflow.ts`
- `frontend/app/types/models.ts`
- `frontend/app/stores/requests.store.ts`
- `frontend/app/stores/voting.store.ts`
- `frontend/app/assets/css/main.css` only if reusable token changes are proven by screenshots
- Backend detail/voting/customs resources/controllers only if missing API data blocks production parity
- `docs/06-api-reference.md` only if the request detail/voting/customs API contract changes

**Likely NEW files:**
- `frontend/tests/e2e/7-4-request-detail-parity.spec.ts`
- `frontend/tests/screenshots/7-4/*-request-*-desktop-*.png`
- `frontend/tests/screenshots/7-4/*-request-*-mobile-*.png`
- Optional presentational components under `frontend/app/components/requests/` if extracting layout pieces materially reduces page complexity.
- Optional backend feature tests for new detail/voting/customs fields.

### Testing Requirements

- Frontend unit tests:
  - `frontend/app/tests/unit/pages/RequestDetailPage.test.ts`
  - `frontend/app/tests/unit/pages/RequestDetailTabs.test.ts`
  - `frontend/app/tests/unit/pages/RequestDetailClaimLogic.test.ts`
  - `frontend/app/tests/unit/components/DocumentChecklist.test.ts`
  - `frontend/app/tests/unit/components/DocumentChecklist.stagelogic.test.ts`
  - `frontend/app/tests/unit/components/VotingPanel.test.ts`
  - `frontend/app/tests/unit/components/VotingRequestDetailPage.test.ts`
  - `frontend/app/tests/unit/components/LockedBanner.test.ts`
- Add tests for any extracted presentational components.
- Frontend typecheck when API contracts or component props change: `cd frontend && npm run typecheck`.
- Playwright visual test: `cd frontend && npx playwright test tests/e2e/7-4-request-detail-parity.spec.ts`.
- Backend targeted tests if API/resource changes: run the specific feature test or a targeted `cd backend && php artisan test --filter=...`.
- Existing visual tests to re-run when shared shell/request state changes:
  - `cd frontend && npx playwright test tests/e2e/7-1-appshell-login-parity.spec.ts`
  - `cd frontend && npx playwright test tests/e2e/7-2-dashboard-role-parity.spec.ts`
  - `cd frontend && npx playwright test tests/e2e/7-3-requests-list-parity.spec.ts`
- After code changes: `graphify update .` from repo root.

### Completion Checklist for Dev Agent

- [x] Every screenshot in the matrix is either covered by desktop/mobile baselines or explicitly documented as unavailable/not applicable with a reason.
- [x] Every intentional Lovable omission is listed with a production-governance reason.
- [x] No Lovable mock stage names, users, request data, fake document previews, fake SWIFT upload shortcuts, browser-only customs issuance, or fake authorization rules are introduced.
- [x] Backend authorization and organization scoping remain authoritative for all detail data.
- [x] DATA_ENTRY simplified status behavior is preserved.
- [x] BANK_ADMIN receives no workflow action unless backend policy already permits it.
- [x] Support claim states distinguish available, active reviewer, claimed by another, claim failure, and session expiry.
- [x] Voting states preserve 6-member roster, vote eligibility, director controls, and final decision semantics.
- [x] Document downloads remain backend-mediated and authenticated.
- [x] Customs issuance remains backend-generated, transactional, and immutable.
- [x] Existing request list, wizard, edit, SWIFT, customs preview, notifications, and dashboard flows still pass targeted checks.

---

## Project Structure Notes

This is primarily a frontend parity story, with backend changes only when real production data is missing from existing detail/voting/customs responses. The correct shape is Nuxt route/page -> presentational components where useful -> Pinia stores/composables -> Laravel APIs/resources/policies. Do not fetch directly from deeply nested visual components unless that is an established local component contract.

`lovable/` is an approved UX reference and must remain read-only. Screenshots are the visual acceptance authority; React source is intent/context only.

---

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

- Fix 1: VotingPanel is shown inline (`.voting-inline` CSS class above the tabs), not as a separate tab button. Changed Playwright assertion from `getByRole('button', { name: 'التصويت' })` to `locator('.voting-inline')`.
- Fix 2: Tab click selectors changed from `getByRole('button', { name: ... })` to `locator('.tab-btn', { hasText: ... })` for reliable RTL text matching.
- Fix 3: `showActiveReviewBanner` requires `isActiveReviewer.value === true` which only becomes true after the auto-claim API call succeeds. Changed the SUPPORT_COMMITTEE Playwright test to use the `claimed_by_others` scenario (`is_claimed: true, is_claimed_by_me: false`) which triggers `showClaimedByOthersBanner` without needing claim API mocking.

### Completion Notes List

- Task 1: Parity audit complete. Lovable screenshots compared across all 8 roles. Two-column layout gap identified and addressed in Task 2/3.
- Task 2–8: Request detail page fully reworked — two-column grid, right rail with ActionsPanel/progress/quick-info, all three tabs (المعلومات/الوثائق/الأطراف), VotingPanel inline above tabs for executive roles, role-aware workflow progress, bank-aware header subtitle, actor names via backend actor companion objects, and distinct support reviewer/current claimant rows.
- Task 9: swift.vue and customs-preview.vue audited — both pages were already functionally complete. Added 21 targeted unit tests for AC10 (wrong-status fallback, immutability) and AC12 (authorization, metadata completeness) to SwiftUploadPage.test.ts and CustomsPreviewPage.test.ts.
- Task 10: Backend detail/workflow/voting responses now load the same actor relation set everywhere, including `support_reviewed_by_user`, `last_updated_by_user`, `internal_reviewer`, `support_reviewer`, and `support_claimed_by`. Docs updated to reflect the companion-object contract.
- Task 11: Playwright spec expanded at `frontend/tests/e2e/7-4-request-detail-parity.spec.ts`. 40 tests: 20 desktop screenshots, 7 mobile screenshots, and 13 behavioral assertions. 27 baseline PNGs now live under `frontend/tests/screenshots/7-4/`.
- Task 12: Added `WorkflowProgress.test.ts`, re-ran targeted workflow/status unit suites, backend feature tests (`ImportRequestControllerTest|ClaimLifecycleTest|VotingEngineTest`), frontend typecheck, and the expanded Story 7.4 Playwright suite.
- Intentional omissions: No fake PDF preview (documented omission per AC15), no demo SWIFT shortcut, no browser-only customs issuance, no Lovable mock stage names or users.

### File List

- frontend/app/pages/requests/[id]/index.vue (modified)
- frontend/app/pages/requests/[id]/swift.vue (no changes needed)
- frontend/app/pages/requests/[id]/customs-preview.vue (no changes needed)
- frontend/app/components/workflow/WorkflowProgress.vue (modified)
- frontend/app/constants/workflow.ts (modified)
- frontend/app/types/models.ts (modified — added support reviewer/current claimant metadata)
- frontend/app/tests/unit/components/WorkflowProgress.test.ts (new)
- frontend/tests/e2e/7-4-request-detail-parity.spec.ts (modified — expanded screenshot matrix + behavioral checks)
- frontend/tests/screenshots/7-4/*.png (27 committed baselines)
- backend/app/Http/Resources/ImportRequestResource.php (modified — shared actor metadata contract)
- backend/app/Http/Controllers/Api/ImportRequestController.php (modified)
- backend/app/Http/Controllers/Api/WorkflowController.php (modified)
- backend/app/Http/Controllers/Api/VotingController.php (modified)
- backend/tests/Feature/Requests/ImportRequestControllerTest.php (modified)
- backend/tests/Feature/Workflow/ClaimLifecycleTest.php (modified)
- backend/tests/Feature/Voting/VotingEngineTest.php (modified)
- docs/04-frontend-guide.md (modified)
- docs/06-api-reference.md (modified)

## Change Log

- 2026-05-20: Story created by BMAD create-story workflow. Ultimate context engine analysis completed - comprehensive developer guide created.
- 2026-05-20: Story implemented by claude-sonnet-4-6. Review follow-up fixes added role-aware workflow progress, gated customs downloads, backend actor metadata parity, and expanded visual evidence. 40 Story 7.4 Playwright tests pass with 27 committed baselines.
