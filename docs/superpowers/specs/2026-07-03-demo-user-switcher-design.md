# Demo User Switcher — Design

**Date:** 2026-07-03
**Status:** Approved for planning

## Purpose

Testers need to switch between demo accounts (any of the ~8 roles, across multiple banks and CBY teams) without re-running the full login sequence (email/password → PIN → OTP/2FA) each time. A floating button, available everywhere in the app including the login screen, opens a list of every active demo user; clicking a user card logs in as that user immediately.

This is a testing/demo aid only. It must never be reachable in production.

## Non-goals

- Not a real "impersonate as admin" audit feature. No audit trail entry beyond the existing login audit log already written by `issueSession()`.
- Not a permissions/role picker UI for production admins.
- No changes to the existing password/PIN/OTP login flow.

## Gating (three layers)

1. **Backend config gate** — both new endpoints require `config('demo.allow_role_switch')` (env `APP_DEMO_ROLE_SWITCH`, already `false` by default) to be `true`, else `403`. This is the same flag the existing `switchDemoRole` endpoint already uses, so demo-mode stays a single on/off switch.
2. **Frontend runtime flag** — new `runtimeConfig.public.demoUserSwitch` (env `NUXT_PUBLIC_DEMO_USER_SWITCH`), set alongside the backend flag in `.env`. The floating button component only mounts when this is `true`. This keeps the control out of the DOM entirely when demo mode is off, not just disabled.
3. Both flags default to off; enabling the feature is an explicit opt-in per environment (local/staging), never defaulted on.

## Backend changes

### `GET /api/auth/demo-users`

- No auth middleware (must be reachable from the login screen, before any session exists) — same reachability posture as the existing `POST /api/auth/switch-demo-role`.
- Guarded by `config('demo.allow_role_switch')`; returns `403` with the existing `ApiResponse::forbidden()` shape when disabled.
- Returns all `is_active = true` users, each with: `id`, `name`, `email`, `role`, `role_label` (via existing `ROLE_LABELS`-equivalent PHP mapping or a resource accessor), `organization` (code + name), `team` (code + name), `bank` (id + name, nullable for CBY users).
- Implemented as `AuthController::demoUsers()`, reusing the `User` model's existing `organization`/`team`/`bank` relations (already loaded elsewhere via `UserResource`/`AuthMeData`-style shaping). Add a lightweight resource or inline transform — no new Eloquent query patterns needed.
- Throttled the same way as `switch-demo-role` (`throttle:20,1`) since it's unauthenticated.

### `POST /api/auth/switch-demo-user`

- Body: `{ user_id: int }`.
- No auth middleware (same reasoning as above — must work from the login screen).
- Guarded by the same `demo.allow_role_switch` config check.
- Validates `user_id` exists and `is_active = true`; `404` via `ApiResponse::notFound()` otherwise.
- Delegates to the existing private `issueSession($request, $user)` helper already used by `login`, `verifyOtp`, and `switchDemoRole` — no duplicated session/audit logic.
- Throttled `throttle:20,1`, matching `switch-demo-role`.

### Routing

Both routes added next to the existing `switch-demo-role` route in `routes/api.php`, inside the same unauthenticated `auth` route group.

## Frontend changes

### Runtime config

`nuxt.config.ts`: add `runtimeConfig.public.demoUserSwitch` sourced from `NUXT_PUBLIC_DEMO_USER_SWITCH` (boolean, default `false`).

### `auth.store.ts`

New action `switchDemoUser(userId: number): Promise<void>`, structurally identical to the existing `switchDemoRole` action (CSRF cookie fetch skipped — no session yet on first call from login screen — matches how `switchDemoRole` already behaves): calls `POST /api/auth/switch-demo-user`, sets `user`/`isAuthenticated`/persists auth mode/syncs avatar cache on success, throws on `!is_active` (mirrors existing pattern).

### New composable: `useDemoUsers.ts`

Thin wrapper: `fetchDemoUsers()` calling `GET /api/auth/demo-users`, returning the typed list. Keeps the fetch/error-handling out of the component per architecture rules (no business logic in components).

### New component: `DemoUserSwitcherButton.vue`

- Fixed-position floating `Button` (shadcn `Button`, `variant="secondary"`, `size="icon"`), positioned `fixed bottom-4 start-4 z-50` (RTL-correct: `start`, not `left`).
- Icon: a "users/switch" lucide icon (e.g. `Users` or `UserCog`).
- Renders only when `useRuntimeConfig().public.demoUserSwitch` is truthy — checked once at setup, no watcher needed (flag is build-time/env-time, not reactive).
- Mounted once in `app.vue`, outside the `auth`/default layouts, so it persists across the login ↔ authenticated transition without remounting.
- Clicking opens `DemoUserSwitcherSheet`.

### New component: `DemoUserSwitcherSheet.vue`

- shadcn `Sheet` with `side="start"`. The app sidebar is right-anchored (`end` side in RTL, per DESIGN.md), so the sheet opens from the opposite (`start`/left) side to avoid overlapping it.
- On open (`@update:open` → true), calls `useDemoUsers().fetchDemoUsers()`; shows `Skeleton` rows while loading, `Alert variant="destructive"` with retry on error (e.g. feature disabled in this environment).
- `InputGroup` + `InputGroupInput` search box at the top, filtering the fetched list client-side by name/email/role label/organization name (simple case-insensitive substring match, no backend param).
- List grouped by `organization.name` using section headings (plain `<h3>` text, not a new component) with `Card role="button" tabindex="0"` per user, adapted from `LoginSavedAccountCard`'s visual layout: avatar-less (no `BoringAvatar` needed here — keep it simpler, or reuse `BoringAvatar` for visual consistency, using the same fallback pattern), name, `team.name` as the org sub-line, `Badge` for role label.
- Clicking a card calls `authStore.switchDemoUser(user.id)`; on success, closes the sheet and `navigateTo('/dashboard')`; on failure, `toast.error(...)` (Sonner, matching existing error-notification pattern) and keeps the sheet open.
- Empty search results use `<Empty>` per SHADCN.md.

### Types

Extend `frontend/app/types/models.ts` with a `DemoUser` interface (id, name, email, role, role_label, organization, team, bank) reused by both the composable and the sheet component.

## Data flow

```
DemoUserSwitcherButton (floating, always mounted when flag on)
  → click → opens DemoUserSwitcherSheet
      → onOpen → useDemoUsers.fetchDemoUsers() → GET /api/auth/demo-users
      → renders grouped, searchable Card list
      → click card → authStore.switchDemoUser(id) → POST /api/auth/switch-demo-user
          → success → navigateTo('/dashboard')
          → failure → toast.error(...)
```

## Testing

- Backend: `tests/Feature/Auth/DemoUserSwitchTest.php` — covers: 403 when flag disabled, 200 + correct payload shape when enabled, 404 for unknown/inactive `user_id`, successful session issuance (cookie mode) mirroring existing `switchDemoRole` test coverage if present.
- Frontend: unit test for `switchDemoUser` action in `auth.store.test.ts` (mirrors existing `login`/`verifyOtp` tests); unit test for `DemoUserSwitcherSheet` covering: renders grouped list, search filters correctly, click triggers store action and navigation. Skip any assertion that requires introspecting `Sheet` teleported content if Vitest can't reach it — component integrity over test greenness, per project rule.

## Open questions resolved during brainstorming

- Availability: everywhere, including pre-auth login screen (not gated behind an existing session).
- Backend gate: reuse `demo.allow_role_switch`, not a new flag.
- Frontend visibility: hidden client-side (button doesn't mount) when the mirrored public runtime flag is off, in addition to the backend 403.
- Panel layout: single sheet listing all users grouped by organization, with a search/filter box (added after initial proposal, given dozens of users across banks).
