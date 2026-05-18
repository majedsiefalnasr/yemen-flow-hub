# Story 5.5: Customs Print Preview Page

## Status

done

## Story

As a bank or CBY operations user with customs visibility,
I want a browser print-preview page for issued customs declarations,
So that I can inspect and print the declaration operationally while the official PDF remains the legal document.

## Acceptance Criteria

1. Given a customs declaration exists for a request, when I navigate to `/requests/{id}/customs-preview`, then the page renders a print-optimized RTL preview using the same canonical data as the official PDF.
2. The preview is read-only and immutable — no edit controls are shown.
3. The page includes a "تحميل PDF الرسمي" (Download Official PDF) action that triggers the existing `GET /api/customs/{id}/download` endpoint.
4. Browser print support works correctly: `window.print()` triggered via a "طباعة" button, with print-specific CSS hiding the app shell and action buttons.
5. A watermark/notice banner is displayed: "معاينة تشغيلية — ملف PDF الرسمي هو الوثيقة القانونية المعتمدة".
6. Given the user does not have permission (not COMMITTEE_DIRECTOR, CBY_ADMIN, or BANK_REVIEWER for the same bank), when they navigate to the preview, the backend/API and frontend guard deny access — the page redirects to `/dashboard` or shows a 403 state.
7. Given the request has no issued customs declaration (status is not COMPLETED), the page shows a "لا يوجد بيان جمركي" (no declaration) state.
8. A backend endpoint `GET /api/requests/{importRequest}/customs-preview` returns the declaration data for the given request (by request ID), enforcing the same permission matrix as the download endpoint.

## Tasks / Subtasks

- [x] Backend: customs preview endpoint
  - [x] Add `preview` method to `CustomsController` for `GET /api/requests/{importRequest}/customs-preview`
  - [x] Reuse `CustomsDeclarationPolicy::download` for authorization (same permission matrix)
  - [x] Return `CustomsDeclarationResource` with declaration data or 404 if no declaration exists
  - [x] Add route to `routes/api.php`
  - [x] Write backend feature tests for preview endpoint
- [x] Frontend: customs preview page
  - [x] Create `frontend/app/pages/requests/[id]/customs-preview.vue`
  - [x] Page `definePageMeta` with `auth` middleware (no role restriction — backend enforces permission)
  - [x] On mount: fetch declaration via `GET /api/requests/{id}/customs-preview` using `$fetch`
  - [x] Show 403/404 error state if access denied or no declaration
  - [x] Render RTL declaration data matching PDF blade template fields
  - [x] Add watermark/notice banner (operational preview notice)
  - [x] Add "طباعة" button calling `window.print()`
  - [x] Add "تحميل PDF الرسمي" button using existing store action
  - [x] Print CSS: hide app shell, nav, action buttons; show only declaration content
  - [x] Add "رجوع" (back) link to parent request page
  - [x] Write frontend unit tests for the preview page
- [x] Frontend: link from request detail page
  - [x] Add "معاينة البيان الجمركي" link/button in the customs card on the request detail index page
  - [x] Only show link when `request.customs_declaration` exists and user has permission to view customs

### Review Findings

- [x] [Review][Patch] Preview endpoint checks declaration existence before permission, leaking no-declaration state to unauthorized users [backend/app/Http/Controllers/Api/CustomsController.php:83]
- [x] [Review][Patch] Print CSS does not hide the default app shell/sidebar/header because the preview page uses scoped styles only [frontend/app/pages/requests/[id]/customs-preview.vue:480]

## Dev Notes

### Architecture

- The backend preview endpoint is a simple wrapper: look up the request, check `customs_declaration` relationship, authorize via existing `CustomsDeclarationPolicy::download`, return resource.
- The same `CustomsDeclarationResource` is reused — no new resource class needed.
- The frontend page fetches via `useRequests` composable (add `fetchCustomsPreview(requestId)` function), stores locally in page state (no Pinia store needed — this is a standalone read-only page).
- Print CSS uses `@media print` to hide `.app-header`, `.app-sidebar`, `.preview-actions` and show only `.customs-preview-content`.
- Permission check on frontend: if API returns 403 → redirect to `/dashboard`; if 404 → show "لا يوجد بيان جمركي" state.

### Data Fields (from `snapshot` in `CustomsService`)

The `metadata` column of `CustomsDeclaration` stores the snapshot used to generate the PDF:
- `reference_number`, `bank.name`, `bank.code`, `supplier_name`, `amount`, `currency`, `goods_description`, `port_of_entry`
- `bank_approved_at`, `support_approved_at`, `executive_decided_at`

The resource also exposes: `declaration_number`, `issued_at`, `issuer.name`.

### Permission Matrix (from `CustomsDeclarationPolicy::download`)

| Role | Access |
|------|--------|
| COMMITTEE_DIRECTOR | All banks |
| CBY_ADMIN | All banks |
| BANK_REVIEWER | Own bank only |
| All others | Denied (403) |

### Previous Learnings

- Always `loadMissing('request')` before `authorize()` in customs methods (patch from Story 3.6 review).
- `CustomsDeclarationResource` does NOT expose `pdf_path` (removed in 3.6 review patch).
- DomPDF RTL uses DejaVu Sans. The browser preview uses standard CSS with `direction: rtl`.
- The existing `CustomsDeclaration` model is immutable (no `updating`/`deleting` events need to fire).

## Dev Agent Record

### Debug Log

- SocratiCode verified live at workflow start (WorkflowService transition search returned results).
- Backend RED tests confirmed 11 failures before implementation (route didn't exist).
- Backend GREEN: all 12 `CustomsPreviewTest` assertions pass (29 assertions); full suite 22 tests / 1298 assertions green, 0 regressions.
- Frontend RED→GREEN: 19 new tests in `useRequests.customs-preview.test.ts` and `CustomsPreviewPage.test.ts` all pass.
- Full frontend suite: 767 pass / 3 pre-existing failures (nav-items + enums from Story 5.1 BANK_ADMIN addition — not introduced by this story).
- `window.print()` cannot be called directly in Vue template — extracted to `triggerPrint()` function.
- `CustomsController::preview` uses `$importRequest->customsDeclaration()->first()` (HasOne via `customs_declaration_id` FK on `import_requests`), then authorizes via existing `CustomsDeclarationPolicy::download` — no new policy needed.

### Completion Notes

- **Backend:** Added `preview` action to `CustomsController` returning `CustomsDeclarationResource` for a request by ID. Authorizes via `CustomsDeclarationPolicy::download` (COMMITTEE_DIRECTOR + CBY_ADMIN unrestricted; BANK_REVIEWER own-bank only; all others 403). Returns 404 when no declaration exists. Route wired as `GET /api/requests/{importRequest}/customs-preview`.
- **Frontend composable:** Added `fetchCustomsPreview(requestId)` to `useRequests` — calls the new endpoint and returns `CustomsDeclaration`.
- **Frontend page:** `/requests/{id}/customs-preview` — RTL, read-only, renders all canonical declaration fields from `metadata` snapshot (matching the DomPDF blade template fields). Shows operational watermark banner. Has "طباعة" + "تحميل PDF الرسمي" action buttons. 403 redirects to `/dashboard`; 404 shows "لا يوجد بيان جمركي" state. Full `@media print` CSS hides action bar and watermark.
- **Request detail link:** Added "معاينة البيان" link in customs card, visible only to COMMITTEE_DIRECTOR, CBY_ADMIN, BANK_REVIEWER when declaration exists.
- **Tests:** 12 backend (29 assertions) + 19 frontend tests (all green).

### File List

- `backend/app/Http/Controllers/Api/CustomsController.php`
- `backend/routes/api.php`
- `backend/tests/Feature/Customs/CustomsPreviewTest.php`
- `frontend/app/composables/useRequests.ts`
- `frontend/app/pages/requests/[id]/customs-preview.vue`
- `frontend/app/pages/requests/[id]/index.vue`
- `frontend/app/tests/unit/composables/useRequests.customs-preview.test.ts`
- `frontend/app/tests/unit/pages/CustomsPreviewPage.test.ts`
- `_bmad-output/implementation-artifacts/5-5-customs-print-preview-page.md`
- `_bmad-output/implementation-artifacts/sprint-status.yaml`

### Change Log

- 2026-05-18: Implemented Story 5.5 — customs print preview page. Backend preview endpoint, frontend page with RTL layout + print CSS, composable method, request-detail link, and 31 new tests (12 backend + 19 frontend).
