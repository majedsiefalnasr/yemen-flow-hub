# Lovable Prototype vs Current Project Audit

Date: 2026-05-17
Purpose: prepare the next sprint by comparing the accepted `lovable/` prototype with the current Laravel/Nuxt production project.

## Executive Summary

The current production project has implemented the core regulatory workflow: authentication, bank request lifecycle, support committee claim flow, SWIFT upload, executive voting, customs issuance, audit timeline, document permissions, role dashboards, and admin bank/user basics.

The accepted Lovable prototype now shows a broader operational product shell. The biggest remaining gap is not the workflow engine; it is productionizing the prototype's surrounding screens and operational UX in Nuxt, using the Laravel APIs that already exist in many cases.

Recommended next sprint theme:

> Prototype parity and operational administration.

In simple words: make the real Nuxt app look and behave closer to the accepted Lovable prototype for the screens stakeholders now expect, while excluding prototype-only demo controls.

## Source Inputs

- Accepted prototype: `lovable/src/routes/*`, `lovable/src/components/*`, `lovable/src/lib/mock.ts`, `lovable/src/lib/governance.ts`
- Current frontend: `frontend/app/pages/*`, `frontend/app/components/*`, `frontend/app/constants/workflow.ts`
- Current backend: `backend/routes/api.php`, `backend/app/Http/Controllers/Api/*`
- Sprint status: `_bmad-output/implementation-artifacts/sprint-status.yaml`
- Deferred work: `_bmad-output/implementation-artifacts/deferred-work.md`
- Product docs: `docs/01-workflow-and-business-rules.md`, `docs/04-frontend-guide.md`, `docs/06-api-reference.md`

## Current Production Coverage

### Done or mostly done

- Authentication shell: login, logout, current user, protected Nuxt routes.
- Core request workflow: create, edit, submit, bank review, support review, SWIFT upload, executive voting, customs issue.
- Role-specific dashboards for the seven canonical production roles.
- Request list and request details with tabs for overview, documents, workflow timeline, votes, and audit history.
- Document checklist and PDF-only upload/download flows.
- Backend audit API and request history API.
- Backend notifications API.
- Backend reports API for workflow and voting.
- Backend merchants API.
- Backend document-type/rules API.
- Backend and frontend bank/user management for CBY admin basics.

### Important current mismatch

The Nuxt navigation already lists routes such as `/merchants`, `/reports`, `/audit`, `/notifications`, `/admin/workflow-docs`, `/bank/users`, `/settings`, and `/customs`, but several of those production pages do not exist yet. This creates a visible gap between the app shell and available screens.

## Accepted Prototype Coverage

The Lovable prototype contains these accepted stakeholder-facing screens:

| Prototype area | Prototype route | Current production status | Sprint meaning |
| --- | --- | --- | --- |
| Main dashboard | `/` | Implemented as `/dashboard` with role dashboards | Keep improving visual parity |
| Request list | `/requests` | Implemented | Polish parity only |
| Request create | `/requests/new` | Implemented | Polish parity only |
| Request details | `/requests/:id` | Implemented | Polish parity only |
| SWIFT upload | `/requests/:id/swift` | Implemented | Polish parity only |
| Customs queue | `/customs` | Missing as standalone page | Build page |
| Customs print view | `/customs/:id/print` | Partially covered by PDF download | Decide print preview vs download-only |
| Merchants | `/merchants` | Backend/composable exist, page missing | Build page |
| Reports | `/reports` | Backend APIs exist, page missing | Build page |
| Audit and compliance | `/audit` | Backend API exists, page missing | Build page |
| Notifications | `/notifications` | Backend API exists, page missing | Build page |
| CBY staff | `/admin/cby-staff` | Production uses `/users` and page exists | Improve parity |
| Bank entities | `/admin/entities` | Production uses `/banks` and page exists | Improve parity |
| Document workflow rules | `/admin/workflow-docs` | Backend API exists, page missing | Build page |
| Roles and permissions | `/admin/roles` | Not in canonical MVP as editable matrix | Treat as future/admin hardening unless product confirms |
| Bank users | `/bank/users` | Route advertised, page missing | Needs product decision on role model |
| Profile | `/profile` | Missing | Optional, lower priority |
| Settings | `/settings` | Route advertised, page missing | Build only production-safe settings |

## Prototype-Only Demo Features to Exclude

These should not become production stories:

- Login role picker that signs in as any demo user.
- Header `RoleSwitcher`.
- In-memory mock data edits from `lovable/src/lib/mock.ts` and `lovable/src/lib/governance.ts`.
- Demo reset tools in settings.
- Prototype footer text saying this is a demo/prototype environment.
- Theme/language toggles unless product explicitly confirms them for production.
- Any fake login shortcuts, fake user switching, or UI-only authorization bypass.

Database seeders are different: keep development/QA seeders where useful. The exclusion above is about demo UI behavior, not backend seed data.

## Recommended Next Sprint Scope

### Story 1: Productionize missing routed pages

Build the Nuxt pages that are already advertised by production navigation but are missing:

- `/merchants`
- `/reports`
- `/audit`
- `/notifications`
- `/admin/workflow-docs`
- `/customs`
- `/settings` if it remains in navigation

Acceptance focus:

- No broken sidebar links.
- Each page uses real backend APIs, not prototype mock state.
- Each page enforces `definePageMeta` role access.
- RTL layout follows `DESIGN.md`.
- Empty, loading, error, and unauthorized states are handled.

### Story 2: Merchant management page

Productionize the accepted prototype's merchant management experience.

Current state:

- Backend `MerchantController` exists.
- Frontend `useMerchants()` exists but only fetches merchants.
- No Nuxt page exists.

Recommended scope:

- List/search merchants.
- Create/edit merchant records.
- Enforce bank-scoped visibility for bank-side roles and CBY visibility for admin roles.
- Use real validation and API errors.

### Story 3: Audit and compliance page

Productionize the accepted prototype's audit/compliance screen.

Current state:

- Backend `GET /api/audit` exists.
- Request detail audit tab exists.
- No standalone `/audit` page exists.

Recommended scope:

- CBY admin audit table.
- Filters for user, action, entity/request, date, and status if backend supports them.
- Link audit entries back to request details where possible.
- Keep advanced risk widgets simple unless real backend data exists.

### Story 4: Reports and operational analytics page

Productionize reports from existing APIs.

Current state:

- Backend `GET /api/reports/workflow` and `GET /api/reports/voting` exist.
- No Nuxt `/reports` page exists.
- Deferred work notes list performance and correctness issues in `ReportController`.

Recommended scope:

- Workflow report cards and charts from real API data.
- Voting report cards and charts from real API data.
- CSV/export can be deferred unless stakeholders explicitly need it.
- Fix the known `ReportController` deferred issues that affect correctness or production safety.

### Story 5: Notifications center

Productionize the accepted notifications page.

Current state:

- Backend notifications API exists.
- Header notification icon exists only in prototype, not production shell.
- No Nuxt `/notifications` page exists.

Recommended scope:

- List notifications.
- Mark one as read.
- Mark all as read.
- Role-scoped notification visibility.
- Optional unread badge in production header.

### Story 6: Document rules/admin workflow docs page

Productionize the prototype's document-rules screen.

Current state:

- Backend `document-types` CRUD exists.
- Production nav contains `/admin/workflow-docs`.
- No Nuxt page exists.

Recommended scope:

- List document types/rules.
- Create/edit/deactivate document types.
- Keep upload validation PDF-only.
- Avoid adding arbitrary workflow mutation unless backend model explicitly supports it.

### Story 7: Customs queue page

Productionize the accepted `/customs` queue.

Current state:

- Customs generation works through request detail actions.
- Committee director dashboard shows requests awaiting customs issuance.
- No standalone `/customs` page exists.

Recommended scope:

- Queue of `EXECUTIVE_APPROVED` requests.
- Generate customs declaration.
- Download issued declaration.
- Show completed/issued declarations read-only.
- Decide whether a browser print preview is required or whether PDF download is enough.

### Story 8: Admin and bank user role-model cleanup

The prototype has `bank_admin`, but production canonical roles do not. Production has only:

- `DATA_ENTRY`
- `BANK_REVIEWER`
- `SWIFT_OFFICER`
- `SUPPORT_COMMITTEE`
- `EXECUTIVE_MEMBER`
- `COMMITTEE_DIRECTOR`
- `CBY_ADMIN`

Current production nav maps `/bank/users` to `BANK_REVIEWER`, which is probably not the right long-term owner.

Recommended decision:

- Do not add `BANK_ADMIN` unless the canonical role enum and docs are intentionally changed.
- If bank self-administration is required, add it as a formal product decision and update backend/frontend enums, permissions, docs, and tests.
- If not required, remove `/bank/users` from production navigation and keep user management under CBY admin.

## Lower Priority Polish Backlog

- Add `/profile` page if account self-service is required.
- Improve production header to match prototype: search, unread notification badge, user menu.
- Improve visual parity for sidebar branding and page headers.
- Add retry actions to timeline/history error states.
- Replace raw actor IDs with resolved actor names in request details and timelines.
- Add mobile sidebar/accessibility polish from deferred work.

## Production Risks to Address Before Sprint Completion

- Sidebar links to missing pages cause broken UX.
- Reports API has known deferred correctness/performance issues; do not build a polished reports UI over inaccurate data.
- Editable roles/permissions matrix from prototype conflicts with the current fixed canonical role model.
- Demo-only prototype controls could create a security smell if copied directly.
- Some backend APIs exist but frontend composables are incomplete for create/update flows, especially merchants and document types.

## Recommended Sprint Goal

By the end of the next sprint, stakeholders should be able to navigate the real Nuxt app through the same major operational areas they accepted in Lovable, with no mock-only controls and no broken advertised routes.

Suggested sprint name:

> Sprint 5: Prototype Parity and Operational Admin

Suggested BMAD next step:

1. Use this report as input to `bmad-create-epics-and-stories` or directly create the next sprint stories.
2. Prioritize missing routed pages first.
3. Keep role-model changes as an explicit product decision, not an accidental copy from Lovable.

## Questions for Product/Stakeholders

1. Should production include a separate bank administrator role, or should all user administration stay with CBY admin?
2. Is the customs print-preview page required, or is official PDF download enough?
3. Are advanced reports required for the next sprint, or is a simple workflow/voting dashboard enough?
4. Should profile/settings pages be part of MVP, or should they be removed from navigation until later?
5. Should the production header include global search and notification popover now, or should those wait until after the missing pages are completed?
