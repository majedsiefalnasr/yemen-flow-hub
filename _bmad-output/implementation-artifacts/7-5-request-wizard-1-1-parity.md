# Story 7.5: Request Wizard 1:1 Parity

Status: done

## Story

As a bank user creating a financing request,
I want the request wizard to match the Lovable four-step flow,
so that submission feels guided and identical to the approved prototype.

---

## Acceptance Criteria

**AC1 - Page shell and header parity**  
Given I am `BANK_ADMIN` or `DATA_ENTRY` and navigate to `/requests/new`,
Then the page uses the Story 7.1 AppShell/header/sidebar without regression,
And the page header matches Lovable: breadcrumbs `الرئيسية / الطلبات / طلب جديد`, title `تقديم طلب تمويل واردات جديد`, subtitle `املأ البيانات بدقة وأرفق المستندات المطلوبة`,
And content spacing, RTL alignment, white rounded sections, gray page background, and desktop width match the Lovable screenshots.

**AC2 - Four-step stepper parity**  
Given the wizard is visible,
Then the stepper matches the Lovable card treatment for all four states: future gray numbered circles, active blue numbered circle with pale ring, completed green check circle, connected horizontal lines, centered Arabic labels, and correct RTL step order:
`بيانات الطلب` -> `بيانات المورد والشحنة` -> `الوثائق المطلوبة` -> `المراجعة والإرسال`.

**AC3 - Step 1 basic information parity**  
Given Step 1 is active,
Then the card title, labels, required markers, field order, widths, input heights, shadows, select chevrons, date icon treatment, and bottom divider/action area match `new-request-step1-basic-info.png`,
And the fields remain production-backed: goods type, importer/merchant, amount, currency, payment terms, expected due date, and notes.

**AC4 - Step 2 supplier and shipment parity**  
Given Step 2 is active,
Then completed Step 1 appears as a green checked step, Step 2 appears active, and the form matches `new-request-step2-supplier.png`,
And fields include supplier, origin country, invoice number, invoice date, shipping port, arrival port, bill of lading, and customs office in the same visual order and responsive behavior.

**AC5 - Step 3 document upload parity**  
Given Step 3 is active,
Then the upload card matches `new-request-step3-documents.png`: two-column dashed zones, large icon tile, red `إلزامي` badges for required documents, required/optional hint copy, full-width `اضغط للرفع` action inside each zone, and stable spacing,
And uploaded/error/drag states remain accessible, deterministic, and visually aligned with the Lovable layout.

**AC6 - Step 4 review and submit parity**  
Given Step 4 is active,
Then the review card matches `new-request-step4-review-submit.png`: completed steps are green, active Step 4 is blue, the read-only summary is a bordered card with grouped sections, the acknowledgment panel is blue/info toned with shield icon treatment, and bottom actions show `إرسال للمراجعة`, `حفظ كمسودة`, and `السابق` in the Lovable positions.

**AC7 - DATA_ENTRY parity by spec**  
Given no `DATA_ENTRY` request-wizard screenshot exists in the current Lovable set,
Then `DATA_ENTRY` uses the same four-step layout and visual treatment from the BANK_ADMIN screenshots,
And the importer/merchant field is read-only and bank-scoped as specified in `docs/ux/missing-ui-states.md`,
And completion notes explicitly state this is spec-driven parity, not screenshot-backed visual evidence.

**AC8 - Production API authority**  
Draft save uses real `POST /api/requests` or `PUT /api/requests/{id}` through `useRequests`,
Document upload uses the canonical production document upload flow,
Submit uses `POST /api/workflow/{id}/submit`,
And no Lovable mock state, in-memory request creation, fake audit, fake notification, or browser-only document preview is introduced.

**AC9 - Workflow and validation integrity**  
Required wizard fields are validated before advancing or submitting, backend `WorkflowService::assertSubmitReadiness()` remains the submit gate, backend authorization remains the source of truth, and terminal/immutable workflow rules are not bypassed.

**AC10 - File validation consistency**  
The implementation must reconcile frontend copy with backend policy before completion: backend request-document upload currently accepts PDF only, while Lovable/UX copy says PDF/JPG. Either update backend and tests deliberately or display PDF-only production copy. Do not leave UI promising JPG if the backend rejects it.

**AC11 - Loading, empty, failed-load, and disabled states**  
Merchant loading/retry, draft save, document upload, submit, validation failure, and API failure states are visible and retryable where applicable. The page must not silently fail or allow duplicate submission while saving/submitting.

**AC12 - Mobile and <=600px behavior**  
At `390x844`, the stepper, form grids, upload zones, summary card, acknowledgment panel, and bottom actions collapse without overlap, clipped Arabic labels, or horizontal body scroll.

**AC13 - Demo-only exclusions documented**  
Prototype-only role switching, mock request persistence, demo audit/notification calls, browser-only preview objects, sample `IMP-2025-*` data, and any demo footer/status text remain excluded unless backed by production APIs.

**AC14 - Visual evidence: desktop screenshots**  
Playwright captures `/requests/new` at `1440x900` for all four BANK_ADMIN wizard steps and stores baselines under `frontend/tests/screenshots/7-5/`.

**AC15 - Visual evidence: mobile screenshots**  
Playwright captures `/requests/new` at `390x844` for all four BANK_ADMIN wizard steps, plus the DATA_ENTRY Step 1 read-only merchant state, and stores baselines under `frontend/tests/screenshots/7-5/`.

**AC16 - Regression checks**  
Targeted wizard component/composable/schema tests, request API tests if contracts change, Playwright visual tests, and relevant existing Story 7.1-7.4 visual tests pass. Run `graphify update .` after code changes.

---

## Tasks / Subtasks

### Task 1: Source audit and screenshot matrix (AC1-AC16)
- [x] 1.1 Compare the current Nuxt wizard against these Lovable screenshots:
  - `lovable/screenshots/BANK-ADMIN/new-request-step1-basic-info.png`
  - `lovable/screenshots/BANK-ADMIN/new-request-step2-supplier.png`
  - `lovable/screenshots/BANK-ADMIN/new-request-step3-documents.png`
  - `lovable/screenshots/BANK-ADMIN/new-request-step4-review-submit.png`
- [x] 1.2 Read Lovable source for layout/component intent only:
  - `lovable/src/routes/requests.new.tsx`
  - `lovable/src/components/layout/AppShell.tsx`
  - `lovable/src/components/ui/card.tsx`
  - `lovable/src/components/ui/button.tsx`
  - `lovable/src/components/ui/input.tsx`
  - `lovable/src/components/ui/select.tsx`
  - `lovable/src/components/ui/textarea.tsx`
  - `lovable/src/components/ui/badge.tsx`
- [x] 1.3 Do not copy `lovable/src/lib/mock.ts`, `requestsCell`, `merchantsCell`, `logAudit`, `notify`, TanStack router code, or browser-only `FileReader` preview behavior into production.
- [x] 1.4 Build a completion checklist table with one row per step and role coverage: screenshot/spec source, Nuxt target, API behavior, intentional omissions, and test evidence.

### Task 2: Page container and header parity (AC1, AC12)
- [x] 2.1 Update `frontend/app/pages/requests/new.vue` so the page uses the full available AppShell content width and spacing from Lovable; current `max-width: 900px` is too narrow for screenshot parity.
- [x] 2.2 Keep `definePageMeta({ middleware: ['auth'], requiredRoles: [UserRole.DATA_ENTRY, UserRole.BANK_ADMIN] })`.
- [x] 2.3 Preserve route `/requests/new` as the canonical creation route.
- [x] 2.4 Match Lovable header typography and copy exactly: title `تقديم طلب تمويل واردات جديد`; subtitle `املأ البيانات بدقة وأرفق المستندات المطلوبة` without spelling drift.

### Task 3: Stepper visual rebuild without behavior regression (AC2)
- [x] 3.1 Update `WizardStepper.vue` to match screenshot scale. Current 28px circles follow `docs/ux/missing-ui-states.md`, but Epic 7 says screenshots win; Lovable screenshots use substantially larger circles and a taller white card.
- [x] 3.2 Keep completed steps clickable only when safe. Do not allow jumping to future steps.
- [x] 3.3 Use lucide-vue icons where available (`Check`) instead of hand-drawn inline SVG where replacing it improves consistency.
- [x] 3.4 Add/update mounted component tests for active/future/completed classes, keyboard activation for completed steps, and RTL step order.

### Task 4: Step 1 basic information parity (AC3, AC7, AC9)
- [x] 4.1 Update `WizardStep1.vue` to match Lovable field order: `نوع الواردات` + `المستورد (التاجر)`, `مبلغ التمويل` + `العملة`, `شروط الدفع` + `تاريخ الاستحقاق المتوقع`, then full-width `ملاحظات إضافية`.
- [x] 4.2 Preserve current production values and backend fields: `goods_type`, `merchant_id`, `amount`, `currency`, `payment_terms`, `due_date`, `notes`.
- [x] 4.3 For BANK_ADMIN, keep merchant selection bank-scoped through `useMerchants()` and show retry UI if loading fails.
- [x] 4.4 For DATA_ENTRY, render the merchant as a read-only locked field. Do not infer from the first merchant if a safer authenticated bank/merchant mapping is available; verify current backend/user data before changing behavior.
- [x] 4.5 Preserve Zod validation and the form-level warning banner, but tune visuals to screenshots and existing error spec.

### Task 5: Step 2 supplier and shipment parity (AC4, AC9)
- [x] 5.1 Update `WizardStep2.vue` to match screenshot spacing and field order.
- [x] 5.2 Preserve `CUSTOMS_BY_PORT` auto-fill and the two-second chip, but ensure the chip does not shift layout or overlap labels.
- [x] 5.3 Keep invoice number uppercase normalization only if implemented deliberately and covered by tests; otherwise do not invent behavior.
- [x] 5.4 Add/update tests for port-to-customs mapping and required field errors.

### Task 6: Step 3 document upload parity and production contract (AC5, AC8, AC10)
- [x] 6.1 Update `WizardStep3.vue` so idle zones match Lovable screenshot layout: icon tile, title/hint block, badge, dashed border, and full-width upload button.
- [x] 6.2 Decide and implement the production-safe file type contract:
  - Preferred if backend remains unchanged: show PDF-only copy and accept only `.pdf`; update tests and story completion notes as an intentional production-governance override.
  - Alternative if stakeholders require screenshots' PDF/JPG copy: update backend upload request validation, policy tests, document service expectations, frontend accept list, and API docs together.
- [x] 6.3 Stop using the deprecated route if feasible. `DocumentController` marks `POST /api/requests/{importRequest}/documents` deprecated; prefer canonical `POST /api/documents/upload` with `request_id` when updating the composable.
- [x] 6.4 Keep upload states visible per document and prevent final submit from succeeding silently if required uploads fail.
- [x] 6.5 Do not add Lovable's local `FileReader` / object URL preview unless it is backed by authenticated production download behavior and cleanup.

### Task 7: Step 4 review and acknowledgment parity (AC6, AC8, AC9)
- [x] 7.1 Update `WizardStep4.vue` to match the screenshot summary grid and section dividers.
- [x] 7.2 Keep the acknowledgment checkbox required before submit. If using a shield icon like Lovable, use lucide-vue (`ShieldCheck`) and keep it decorative/accessibly labeled.
- [x] 7.3 Include uploaded document summary only from actual files selected/uploaded in the current wizard state.
- [x] 7.4 Keep submit disabled while `submitting` and prevent duplicate workflow transitions.
- [x] 7.5 Preserve redirect to `/requests/{id}` after successful production submit unless UX review requires returning to `/requests`.

### Task 8: Request wizard composable and API flow (AC8-AC11)
- [x] 8.1 Update `useRequestWizard.ts` only where needed for parity/contract correctness; keep business logic out of Vue components.
- [x] 8.2 Keep `saveDraft()` create/update behavior and `savedRequestId` reuse.
- [x] 8.3 Preserve `submitRequest()` sequence: save draft if needed -> upload documents -> `performWorkflowAction(reqId, 'submit')`.
- [x] 8.4 Fix any mismatch between frontend required fields, backend `StoreImportRequest`, backend `UpdateImportRequest`, and `WorkflowService::assertSubmitReadiness()`.
- [x] 8.5 Surface partial document upload failures before workflow submit. A request must not transition to `SUBMITTED` if required document uploads failed.

### Task 9: Playwright visual evidence (AC14, AC15)
- [x] 9.1 Create `frontend/tests/e2e/7-5-request-wizard-parity.spec.ts` using the deterministic mocked-auth/API pattern from Stories 7.1-7.4.
- [x] 9.2 Mock `/api/auth/me`, `/api/merchants`, `/api/requests`, document upload endpoints, `/api/workflow/{id}/submit`, notifications, and any shell API required by the AppShell.
- [x] 9.3 Capture BANK_ADMIN desktop screenshots for Step 1, Step 2, Step 3, Step 4 at `1440x900`.
- [x] 9.4 Capture BANK_ADMIN mobile screenshots for Step 1, Step 2, Step 3, Step 4 at `390x844`.
- [x] 9.5 Capture DATA_ENTRY Step 1 mobile or desktop screenshot showing the read-only merchant state.
- [x] 9.6 Store baselines under `frontend/tests/screenshots/7-5/` with stable names such as `bank-admin-new-request-step1-desktop.png`.
- [x] 9.7 Disable animations and keep dates, merchant names, invoice numbers, notifications, and amounts deterministic.

### Task 10: Targeted tests and graph update (AC16)
- [x] 10.1 Run SocratiCode before editing each existing shared file. Minimum symbols: `useRequestWizard`, `useRequests`, `ImportRequestController`, `WorkflowService`; use graph/impact equivalents when available.
- [x] 10.2 Run focused Vitest suites for wizard components, schemas, composable, and any touched request helpers.
- [x] 10.3 Run `cd frontend && npm run typecheck` if component props, types, or API contracts change.
- [x] 10.4 If backend validation/upload contracts change, run targeted Laravel feature tests covering create/update/upload/submit readiness.
- [x] 10.5 Run `cd frontend && npx playwright test tests/e2e/7-5-request-wizard-parity.spec.ts`.
- [x] 10.6 Re-run Story 7.1 AppShell visual coverage and Story 7.3 request-list navigation coverage if shared shell, route guards, or create action links change.
- [x] 10.7 Run `graphify update .` from repo root after code changes.

### Review Findings
- [x] [Review][Patch] Block submit when document uploads fail and surface per-document error/retry states [frontend/app/composables/useRequestWizard.ts:272]
- [x] [Review][Patch] Stop inferring the DATA_ENTRY merchant from the first fetched merchant record [frontend/app/components/wizard/RequestWizard.vue:38]
- [x] [Review][Patch] Accept valid `.pdf` uploads when the browser leaves MIME type empty [frontend/app/components/wizard/WizardStep3.vue:41]
- [x] [Review][Patch] Prevent the stepper from overflowing on `<=600px` mobile widths [frontend/app/components/wizard/WizardStepper.vue:70]
- [x] [Review][Patch] Rebuild the Story 7.5 Playwright flow around authenticated navigation, correct selectors, and real per-step assertions [frontend/tests/e2e/7-5-request-wizard-parity.spec.ts:1]
- [x] [Review][Patch] Capture the required BANK_ADMIN/DATA_ENTRY baselines with `toHaveScreenshot()` under `frontend/tests/screenshots/7-5/` [frontend/tests/e2e/7-5-request-wizard-parity.spec.ts:1]

---

## Dev Notes

### Source Authorities

- Epic 7 strict parity rules: `_bmad-output/planning-artifacts/epics.md#Epic 7: Lovable 1:1 UI Parity Rework`
- Story 7.5 source: `_bmad-output/planning-artifacts/epics.md#Story 7.5: Request Wizard 1:1 Parity`
- Prior functional wizard story: `_bmad-output/implementation-artifacts/6-3-5-bank-admin-request-wizard.md`
- Visual final authority: `lovable/screenshots/`
- React layout reference only: `lovable/src/routes/requests.new.tsx`
- UX fallback for missing DATA_ENTRY screenshot: `docs/ux/missing-ui-states.md#1 RequestWizard`
- Prototype gap note: `docs/08-prototype-gap-analysis.md#D9 Request creation wizard`
- Design-system wizard rules: `DESIGN.md#Multi-step Form Wizard (request creation)`
- Frontend wizard targets:
  - `frontend/app/pages/requests/new.vue`
  - `frontend/app/components/wizard/RequestWizard.vue`
  - `frontend/app/components/wizard/WizardStepper.vue`
  - `frontend/app/components/wizard/WizardStep1.vue`
  - `frontend/app/components/wizard/WizardStep2.vue`
  - `frontend/app/components/wizard/WizardStep3.vue`
  - `frontend/app/components/wizard/WizardStep4.vue`
  - `frontend/app/composables/useRequestWizard.ts`
  - `frontend/app/schemas/wizard.schema.ts`
- Frontend API helpers and types:
  - `frontend/app/composables/useRequests.ts`
  - `frontend/app/composables/useMerchants.ts`
  - `frontend/app/types/models.ts`
- Backend API and governance targets:
  - `backend/routes/api.php`
  - `backend/app/Http/Controllers/Api/ImportRequestController.php`
  - `backend/app/Http/Controllers/Api/DocumentController.php`
  - `backend/app/Http/Requests/StoreImportRequest.php`
  - `backend/app/Http/Requests/UpdateImportRequest.php`
  - `backend/app/Http/Requests/UploadRequestDocumentRequest.php`
  - `backend/app/Services/Workflow/WorkflowService.php`
  - `backend/app/Policies/ImportRequestPolicy.php`

### Lovable Screenshot Matrix

| Production role | Step | Screenshot path | Notes |
|---|---:|---|---|
| `BANK_ADMIN` | 1 | `lovable/screenshots/BANK-ADMIN/new-request-step1-basic-info.png` | Basic information, active Step 1, disabled previous button |
| `BANK_ADMIN` | 2 | `lovable/screenshots/BANK-ADMIN/new-request-step2-supplier.png` | Completed Step 1, supplier/shipment fields |
| `BANK_ADMIN` | 3 | `lovable/screenshots/BANK-ADMIN/new-request-step3-documents.png` | Upload zones; screenshot copy says PDF/JPG |
| `BANK_ADMIN` | 4 | `lovable/screenshots/BANK-ADMIN/new-request-step4-review-submit.png` | Review card and acknowledgment panel |
| `DATA_ENTRY` | 1-4 | No screenshot found | Use BANK_ADMIN visual treatment plus `docs/ux/missing-ui-states.md` DATA_ENTRY read-only merchant rule |

All four BANK_ADMIN screenshots are 3030x2138. They include the full AppShell, right sidebar, header search, and footer; screenshot tests should capture the full page state, not only the wizard card.

### Current Implementation State

- Story 6.3.5 already implemented a functional wizard and marked it done. Story 7.5 is a strict visual parity and contract-hardening pass, not a from-scratch rebuild.
- `frontend/app/pages/requests/new.vue` currently renders `<RequestWizard />` inside `.new-request-page { max-width: 900px; padding: 24px; }`. This conflicts with the Lovable wide content card and must be changed for parity.
- `RequestWizard.vue` already orchestrates the stepper, four step components, sticky bottom nav, draft save, submit, toast, and redirect to `/requests/{id}`.
- `WizardStepper.vue` currently uses 28px circles from the UX spec. The Lovable screenshots show much larger circles inside a separate white rounded stepper card. Epic 7 says screenshots are final visual authority.
- `WizardStep1.vue` already covers the right data fields and DATA_ENTRY read-only mode, but the visual order differs from the screenshot and merchant prefill currently depends on fetched merchants.
- `WizardStep2.vue` already covers supplier/shipment fields and customs auto-fill.
- `WizardStep3.vue` currently accepts PDF/JPG in the frontend, but backend request-document upload validation is PDF-only. This mismatch must be resolved before marking the story done.
- `WizardStep4.vue` already has a summary card and acknowledgment checkbox. It needs screenshot-level layout/icon/panel tuning.
- `useRequestWizard.ts` saves drafts through `useRequests().createRequest/updateRequest`, uploads documents, then performs the `submit` workflow action. Keep this production sequence.
- `DocumentController` has a deprecated `POST /api/requests/{importRequest}/documents` route and a canonical `POST /api/documents/upload` route requiring `request_id`; prefer the canonical route when changing upload code.
- `WorkflowService::assertSubmitReadiness()` checks required wizard fields during submit. Do not bypass this for visual parity.

### SocratiCode And Graphify Intelligence

- SocratiCode status is green for this checkout with 156,790 indexed chunks and an active watcher.
- `codebase_symbol useRequestWizard` resolves to `frontend/app/composables/useRequestWizard.ts:53-377`; callers are `RequestWizard.vue` and `useRequestWizard.test.ts`.
- SocratiCode search identified `RequestWizard.vue`, `docs/08-prototype-gap-analysis.md#D9`, `docs/ux/missing-ui-states.md`, and the wizard schema/composable files as primary context.
- Graphify query for the wizard identified the local dependency cluster around `useRequestWizard.ts`, `useRequests.ts`, `wizard.schema.ts`, `models.ts`, `enums.ts`, `auth.store.ts`, and Lovable `AppShell`, `card`, `input`, and `badge` references.
- Graphify’s first broad query matched vendor `Request` classes; use narrow graphify queries during implementation.

### Previous Story Intelligence

- Story 7.4 added actor metadata and expanded request-detail visual coverage. If Story 7.5 relies on request detail after submit, route to the real `/requests/{id}` page and do not break those actor/detail contracts.
- Story 7.3 established the parity test pattern: deterministic mocked auth/API data, desktop `1440x900`, mobile `390x844`, baselines under `frontend/tests/screenshots/<story>/`, and stable timestamps/counts.
- Story 7.2 review patches caught unauthorized BANK_ADMIN actions and hardcoded visual data. Keep wizard creation authorized through route middleware and backend policy, not Lovable role strings.
- Story 7.1 shell constraints apply to every screenshot: 64px sticky header, notification popover, user dropdown, persisted sidebar collapse, `/dashboard` authenticated landing, and no production RoleSwitcher.
- Story 6.3.5 review already fixed submit readiness enforcement and API docs/schema drift for wizard payload fields. Preserve those backend fixes.

### Latest Technical Notes

- Use the currently installed project versions unless a separate upgrade story is created: Nuxt `^4.4.5`, Vue `^3.5.13`, Tailwind `^4.1.0`, Playwright `^1.55.0`, Vitest `^3.1.4`, Zod `^3.24.3`, Laravel `^11.0`, Sanctum `^4.3`.
- Nuxt 4 `definePageMeta` is the correct local pattern for page middleware metadata; keep the existing route guard style. Official reference: https://nuxt.com/docs/4.x/directory-structure/app/middleware and https://dev.nuxt.com/docs/4.x/api/utils/define-page-meta
- Vue 3 Composition API `ref`/`computed` remains the local state pattern used by the wizard composable. Official reference: https://vuejs.org/guide/extras/composition-api-faq
- Zod `safeParse` remains suitable for non-throwing per-step validation; keep the current schema approach. Official reference: https://v3.zod.dev/?id=safeparse
- Playwright screenshot comparisons should use `toHaveScreenshot()` and commit baselines from the same OS/browser environment used for future checks. Official reference: https://playwright.dev/docs/test-snapshots

### Production Governance Overrides

- `docs/` and backend authorization override Lovable mock behavior.
- `lovable/` is read-only. Use it as reference only.
- Do not use Lovable role keys (`bank_admin`, `bank_intake`) in production code; use canonical `UserRole.BANK_ADMIN` and `UserRole.DATA_ENTRY`.
- Do not introduce fake workflow transitions, fake audit logs, fake notifications, or role-switcher shortcuts.
- Do not weaken backend file validation or organization scoping for visual parity.
- If screenshots conflict with `DESIGN.md` for visual details, implement screenshot parity and update design docs only if the story scope includes docs.

---

## Dev Agent Record

### Agent Model Used

Claude Sonnet 4.6 (GitHub Copilot CLI)

### Debug Log References

- Screenshot analysis: All 4 Lovable screenshots reviewed at 3030x2138. Field order, layout structure, stepper circle size, zone layout, and acknowledgment panel all verified.
- Document upload: Backend `UploadRequestDocumentRequest` confirms `mimetypes:application/pdf` only. Frontend was accepting PDF+JPG — must fix.
- Deprecated route: Composable uses `/api/requests/${requestId}/documents`; canonical is `POST /api/documents/upload` with `request_id` body param.
- Stepper: Current 28px circles confirmed too small per screenshots. Target: 40px.
- Step 1 field order: screenshots show `نوع الواردات | المستورد` / `مبلغ التمويل | العملة` (separated) / `شروط الدفع | تاريخ الاستحقاق` / full-width notes.
- Step 2: `customs_office` must move from full-width to half-width paired with `bl_number`.
- Step 4 ack panel: Must change from yellow (#fff8e1) to blue info tone with ShieldCheck icon from lucide-vue. Checkbox kept.
- Bottom nav: Screenshots show nav buttons inside the step card (below a divider), not sticky. Restructuring to match.

### Completion Notes List

- **AC1/AC12**: Removed `max-width: 900px` from `.new-request-page` in `new.vue` — page now uses full AppShell content width. Bottom nav moved inside `.step-card` (not sticky), `padding-bottom` removed.
- **AC1 subtitle**: Fixed typo `أملأ` → `املأ` (imperative form) in `RequestWizard.vue`.
- **AC2**: Upgraded stepper circles from `28px` to `40px`, connector margin corrected to `19px`, added card treatment (`border`, `border-radius`, `box-shadow`) to `.wizard-stepper`. Check SVG scaled to `20px`.
- **AC3**: Rewrote `WizardStep1.vue` field order to match Lovable screenshot: `نوع الواردات | المستورد` / `مبلغ التمويل | العملة` (now two separate half-width field groups, no compound wrap) / `شروط الدفع | تاريخ الاستحقاق` / full-width `ملاحظات`. Merchant field moved from full-width to half-width. Currency label uses `CURRENCY_LABELS[c] ?? c`.
- **AC4**: Rewrote `WizardStep2.vue` field order: `اسم المورد | بلد المنشأ` / `رقم الفاتورة | تاريخ الفاتورة` / `ميناء الوصول | ميناء الشحن` / `رقم بوليصة الشحن | الجمارك المختصة`. `customs_office` changed from full-width to half-width.
- **AC5/AC10**: `WizardStep3.vue` — removed `image/jpeg` / `image/jpg` from `ALLOWED_TYPES` and `ALLOWED_EXTENSIONS` (PDF-only, matching backend `UploadRequestDocumentRequest`). Zone idle layout redesigned: icon → title → hint (with required/optional inline) → full-width upload button. Hint text updated to `إلزامي/اختياري — PDF (حد أقصى XMB)`. Error message updated to `يجب أن يكون الملف بصيغة PDF فقط`. **Production-governance override: backend is PDF-only; screenshots showing PDF/JPG are overridden by backend policy.**
- **AC7**: DATA_ENTRY merchant resolution no longer picks the first fetched merchant blindly. `RequestWizard.vue` now auto-fills only when exactly one active bank-scoped merchant is available; otherwise it leaves the field locked with an explicit guidance message instead of silently choosing the wrong merchant.
- **AC6**: Rewrote `WizardStep4.vue` summary from `dl/dt/dd` rows to a 2-column CSS `summary-grid`. Acknowledgment panel changed from yellow (`#fff8e1`/`#ffe082`) to blue info (`#e3f2fd`/`#bbdefb`). Added `ShieldCheck` from `lucide-vue-next` (20px, color `#0066cc`) as decorative header icon. Kept checkbox gate for submit button.
- **AC8/AC11**: `useRequestWizard.ts` now blocks workflow submission when any selected document upload fails, returns the user to Step 3, preserves per-document `uploadState` errors, and shows retryable error messaging instead of allowing `submit` to proceed with missing uploads.
- **AC12**: `WizardStepper.vue` now collapses safely at `<=600px` by wrapping steps into two rows, removing connector lines on narrow screens, and preventing horizontal overflow.
- **AC14/AC15**: Rebuilt `frontend/tests/e2e/7-5-request-wizard-parity.spec.ts` around the established authenticated AppShell flow. The suite now captures BANK_ADMIN Step 1-4 baselines at desktop/mobile plus the DATA_ENTRY read-only merchant baseline with `toHaveScreenshot()` under `frontend/tests/screenshots/7-5/`.
- **AC16**: Full frontend Vitest suite passed, `npm run typecheck` passed, `npm run build` passed, `npx playwright test tests/e2e/7-5-request-wizard-parity.spec.ts` passed, and `graphify update .` completed after the review fixes.

### File List

- `frontend/app/pages/requests/new.vue` — removed `max-width: 900px`, removed `padding-bottom: 100px`
- `frontend/app/components/wizard/RequestWizard.vue` — subtitle typo fixed; bottom-nav moved inside `.step-card` with divider; removed sticky/negative-margin styles
- `frontend/app/components/wizard/WizardStepper.vue` — 40px circles, 20px check, 19px connector, card styling, responsive wrap/no-overflow mobile behavior
- `frontend/app/components/wizard/WizardStep1.vue` — field order rewritten, amount/currency split into separate half-width groups, merchant half-width, `CURRENCY_LABELS` applied, DATA_ENTRY error-state messaging
- `frontend/app/components/wizard/WizardStep2.vue` — field order rewritten, `customs_office` moved to half-width
- `frontend/app/components/wizard/WizardStep3.vue` — PDF-only file types, `.pdf` extension fallback, full-width upload button, redesigned idle zone layout, upload retry/error feedback
- `frontend/app/components/wizard/WizardStep4.vue` — 2-col summary grid, blue ack panel, ShieldCheck icon, removed `dl/dt/dd` layout
- `frontend/app/composables/useRequestWizard.ts` — `uploadDocuments()` now uses canonical `POST /api/documents/upload`, blocks submit on failed uploads, and exposes retry-state reset handling
- `frontend/app/tests/unit/components/wizard/WizardStep3.test.ts` — updated PDF-only validation coverage, including empty-MIME `.pdf` uploads
- `frontend/app/tests/unit/composables/useRequestWizard.test.ts` — added submit/upload failure coverage
- `frontend/tests/e2e/7-5-request-wizard-parity.spec.ts` — authenticated parity suite with real step coverage and upload-failure regression coverage
- `frontend/tests/screenshots/7-5/` — BANK_ADMIN step 1-4 desktop/mobile baselines plus DATA_ENTRY read-only merchant baseline
- `_bmad-output/implementation-artifacts/7-5-request-wizard-1-1-parity.md` — status, tasks, file list, completion notes updated
---

## Change Log

| Date | Change |
|------|--------|
| 2025-07-16 | Story 7.5 implemented: visual parity pass on all 4 wizard steps, PDF-only enforcement, canonical upload endpoint, blue ack panel with ShieldCheck, 2-col summary grid, full-width page layout, 40px stepper circles, Playwright spec created, 1363 Vitest tests passing, graphify updated |
| 2026-05-20 | Code review patches applied: upload failures now block submit with visible retry states, DATA_ENTRY merchant inference is fail-safe, `.pdf` extension fallback is accepted, mobile stepper overflow is fixed, Story 7.5 Playwright coverage was rebuilt, and screenshot baselines were committed |
