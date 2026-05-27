# Role UX/UI Implementation Plans

This folder converts the role UX specifications in `docs/user-view/*.md` into implementation-ready tasklists and test plans. It is planning only. No application code is implemented here.

## Source Baseline

Use these sources in order when implementing:

1. The matching role specification in `docs/user-view/{role}.md`
2. `frontend/app/constants/role-surfaces.ts` for rendered surface authority
3. `frontend/app/constants/workflow.ts` for role labels, status labels, navigation, buckets, and progress
4. `docs/01-workflow-and-business-rules.md`, `docs/03-database-and-models.md`, and `docs/06-api-reference.md`
5. `DESIGN.md` and `frontend/app/assets/css/main.css` for visual tokens

## Tech Stack Alignment

- Frontend app: Nuxt 4 in SPA mode, Vue 3 `<script setup>`, TypeScript strict, Pinia stores, VueUse, VeeValidate/Zod, TanStack Vue Table, Unovis charts, Playwright and Vitest.
- UI system: shadcn-vue `new-york` style with source components in `frontend/app/components/ui`, Tailwind v4 tokens in `frontend/app/assets/css/main.css`, `lucide-vue-next` icons, RTL-first layout.
- Backend app: Laravel 11 API with Sanctum, role-aware policies, `WorkflowService::transition()`, audit logging, Redis support claims, and feature tests under `backend/tests`.
- API surfaces already present: `/api/dashboard/stats`, `/api/requests`, `/api/workflow/*`, `/api/voting/*`, `/api/documents/*`, `/api/customs/*`, `/api/users`, `/api/merchants`, `/api/banks`, `/api/audit`, `/api/reports/*`, `/api/settings`, `/api/admin/settings`.

## Shared Implementation Rules

- Role-forbidden controls must not be mounted/rendered. Do not show disabled forbidden controls as the main enforcement mechanism.
- Start every role page from `ROLE_SURFACE_MATRIX`, `ROUTE_ROLE_MAP`, and route middleware before adding page-level conditions.
- Use shadcn-vue components before custom markup: `Button`, `Badge`, `Card`, `Tabs`, `Table`, `Dialog`, `AlertDialog`, `Sheet`, `DropdownMenu`, `Select`, `Command`, `Skeleton`, `Alert`, `Sonner`, `Tooltip` or `Popover`.
- Use semantic tokens such as `bg-background`, `text-muted-foreground`, `border-border`, `text-primary`, and project tokens such as `--severity-amber`, `--severity-red`, `--voting`, `--info`.
- Use `gap-*`, not `space-*`; use `size-*` for square icons/buttons; use `cn()` for conditional class composition.
- Use skeleton states for async surfaces. Avoid full-page spinner overlays.
- Preserve URL-shareable filters through query params for role queues, request registries, reports, audit, and admin investigations.
- Use `useApi()` and existing stores/composables before creating new fetch logic.
- Backend changes must keep organization scope at query level and all workflow transitions through `WorkflowService::transition()`.
- Keep `lovable/` untouched.

## Required Alignment Before Implementation

The role plans intentionally follow `docs/user-view/*.md`, which describes the updated workflow:

`SUPPORT_APPROVED` -> executive voting -> `EXECUTIVE_APPROVED` -> `WAITING_FOR_SWIFT` -> `SWIFT_UPLOADED` -> `FX_CONFIRMATION_PENDING` -> `COMPLETED`.

Before implementing any role UI, create a small prerequisite story to reconcile the live code with that source of truth:

- [ ] Add/verify `FX_CONFIRMATION_PENDING` in backend and frontend enums, labels, colors, icons, buckets, tests, API resources, and seed/test fixtures.
- [ ] Update workflow transitions so support approval opens executive voting before SWIFT work, and executive approval sends the request to the SWIFT officer.
- [ ] Update SWIFT upload so it moves the request to `FX_CONFIRMATION_PENDING`, not back into voting.
- [x] Replace new user-facing "customs declaration" copy with external FX confirmation copy while preserving `/customs` as a legacy route alias.
- [ ] Split the existing generated-document behavior into the required Director sequence: download generated PDF, upload signed/stamped PDF, complete request.
- [ ] Update backend and frontend tests around transition order, dashboard queues, request buckets, document permissions, and notifications before implementing the role pages.

## Role Plan Files

- `data-entry-plan.md`
- `bank-reviewer-plan.md`
- `bank-admin-plan.md`
- `support-committee-plan.md`
- `swift-officer-plan.md`
- `executive-member-plan.md`
- `committee-director-plan.md`
- `cby-admin-plan.md`

## Shared Test Matrix

Every role implementation should include:

- Unit/component tests with Vitest and Vue Test Utils for dashboards, request lists, banners, actions, document rows, and route-surface behavior.
- Store/composable tests for query filters, dashboard stats normalization, request workflow calls, upload calls, document permissions, voting, support claims, and notifications.
- Backend feature tests for any API/data gaps introduced by the UI plan.
- Playwright role-flow tests for rendered/not-rendered controls and primary user journeys.
- Visual regression tests for the role dashboard, requests list, request detail, and one role-specific high-risk page at desktop widths, with reduced motion enabled.
- Accessibility checks in implementation review: keyboard order, visible focus, 44 px targets, labelled inputs, dialog titles, non-color-only status, Arabic/RTL text fit, and no horizontal scroll at 375 px.
