# FE-001 — AbortController in useApi

## What changed

`frontend/app/composables/useApi.ts`:

- New `getAbortable<T>(path, options)` — same as `get()`, but creates an `AbortController`, passes its `signal` to the underlying `$fetch` call, and registers the controller in a per-composable-instance `Set` that's aborted (and cleared) in `onUnmounted()` — following the exact same `getCurrentInstance()`-guarded lifecycle pattern already shipped in `useEngineClaim.ts`, so the composable still works safely outside a component context (tests, direct calls).
- New `isAbortError(err)` helper — callers can distinguish "the user navigated away" from a real fetch failure and skip showing an error toast for the former.
- `get`, `post`, `put`, `patch`, `del` are **unchanged** — no signature or behavior change to any of the 38 existing call sites across the app.

## Why additive, not a signature change to `get()`

Threading a signal automatically into every existing `get()` call would silently change behavior for callers that intentionally `await` a GET across a navigation boundary (e.g. a background prefetch). `getAbortable()` is opt-in: a caller (or a future composable wrapping `useApi()`) chooses cancellable behavior explicitly. Matches the finding's own stated trade-off ("must not abort mutations mid-flight") — mutations were never touched here at all, not just left un-auto-aborted.

## Test evidence

`frontend/app/tests/unit/composables/useApi.test.ts` — 3 new tests: signal is passed to the underlying fetch, an aborted signal produces an `AbortError`-shaped rejection recognized by `isAbortError`, `isAbortError` returns `false` for non-abort errors and non-object values.

```
useApi — getAbortable (FE-001)
 ✓ passes an AbortController signal to the underlying fetch call
 ✓ rejects with an AbortError-shaped error when the signal is aborted before the response resolves
 ✓ isAbortError returns false for a regular error
```

**Note on how these were verified**: `useApi.test.ts` is a pre-existing quarantined file (`vitest.config.ts`'s `baselineRedTests` list, dated 2026-06-07 — "assertions drifted from the shipped shadcn/data-table/page behavior"), excluded from the default `pnpm test` run. The 2 pre-existing tests in that file (CSRF cookie init/retry) are already known-red and unrelated to this change. Verified my 3 new tests pass by running the file against a temporary vitest config with the exclude list removed (confirmed: 3 new tests pass, the 2 pre-existing tests fail with the same pre-existing CSRF-related errors, unrelated to `getAbortable`). Did not de-quarantine the file — fixing the 2 pre-existing failures is a separate, unrelated task.

## Regression check

`pnpm exec eslint app/composables/useApi.ts` — clean. `pnpm typecheck` — no new errors attributable to `useApi.ts` (confirmed via grep against the pre-existing baseline-red error list). `pnpm exec prettier --check` — clean.

## Residual

`getAbortable()` is not wired into any of the 38 existing `useApi().get()` call sites — the finding asks for the composable-level plumbing ("thread an AbortController signal through useApi"), not a migration of every list/detail fetch in the app. Migrating individual pages to `getAbortable()` where cancellation is actually valuable (rapid list-page navigation, search-as-you-type) is a natural follow-up, left for when a specific page needs it — mirrors how `useReports.ts`'s async-export functions (`requestExport`/`pollExportUntilComplete`) already exist unwired in this codebase until a UI needs them.
