# FE-002 — unread count routed through the dedicated endpoint

## What changed

`frontend/app/stores/notifications.store.ts` — `refreshUnreadCount()` previously called `useNotifications().fetchNotifications(1)` (a full paginated list fetch) and derived the badge count in JS from the returned rows, duplicating the cheap, already-existing `GET /api/v1/notifications/unread-count` endpoint (`useNotifications.ts`'s `fetchUnreadCount()`). Now it calls `fetchUnreadCount()` directly and adopts the returned count.

## Why this is safe

Checked every caller of `refreshUnreadCount()` before changing its contract:

- `AppSidebar.vue` (`refreshOperationalBadges()`) — only reads `notificationsStore.unreadCount` afterward.
- `DataEntryDashboard.vue` (`onMounted`) — same, fire-and-forget, only the badge count matters.

Neither call site reads `store.items` as a side effect of calling `refreshUnreadCount()`, so no caller depended on the full list being populated. `fetchRecent()` (which still fetches the full list and is the correct place to do so for actually rendering a notification list) was confirmed to have **zero callers** anywhere in the app today — left untouched since removing genuinely dead code was out of this finding's scope.

## Test evidence

`frontend/app/tests/unit/stores/notifications.store.test.ts` — 3 new tests replacing the 2 tests that asserted the old (over-fetching) behavior:

1. `calls the dedicated unread-count endpoint, not the full list fetch` — asserts `fetchUnreadCount` is called and `fetchNotifications` is not.
2. `adopts the count returned by the dedicated endpoint and sets lastFetched`.
3. `does not overwrite already-loaded list items` — proves the fix doesn't have a side effect of clearing `store.items` when a caller happens to have list state already loaded.

```
notifications.store.test.ts: 10 tests (3 new/replaced), all pass
```

## Regression check

```
AppSidebar.test.ts: 7 tests — pass
DataEntryDashboard.test.ts: 9 tests — pass
```

`pnpm typecheck` was run per the verification ladder (composable/store contract change). It reports pre-existing failures in unrelated files (`reports/index.vue`, `WorkflowFieldDesigner`, `StagePermissionEditor`, etc.) — confirmed via `git status` that only `notifications.store.ts` and its test file were modified, and none of the reported errors reference either file. No new typecheck errors introduced.

## ESLint / Prettier

Both touched files pass clean.
