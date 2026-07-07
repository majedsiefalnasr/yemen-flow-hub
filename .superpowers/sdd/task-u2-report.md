# WP-12 Task U-2 Report — Transition confirmation dialogs (D2-N4)

**Status:** Complete  
**Branch:** `worktree-wp12-runtime-ux`  
**Commit:** `feat(workflow): gate destructive transitions behind confirmation dialog (WP-12 U-2)`

## Summary

Runtime workflow graph edges now expose `confirmation_message`, `is_destructive`, and `is_default_submit`. The `useTransitionConfirm` composable centralizes confirmation logic; `EngineActionsRail` gates `run` emits behind shadcn `AlertDialog` for destructive or message-bearing transitions.

## Changes

**Backend**
- `WorkflowGraphService::build()` — edge payload includes confirmation/destructive/default-submit flags
- `WorkflowGraphServiceTest` — TDD coverage for graph edge shape

**Frontend**
- `useTransitionConfirm.ts` — `needsConfirmation`, `confirmIfNeeded`, dialog state helpers
- `WorkflowGraphEdge` type extended
- `EngineActionsRail.vue` — AlertDialog gate before `emit('run')`
- Unit tests for composable + rail confirmation flow

## Instance page

`workflows/instances/[id].vue` unchanged: confirmation is enforced at the rail before `run` is emitted, keeping business logic out of the page component.

## Test summary

| Suite | Result |
|-------|--------|
| `backend/tests/Feature/Workflow/WorkflowGraphServiceTest.php` | **1/1 pass** |
| `frontend/.../useTransitionConfirm.test.ts` | **4/4 pass** |
| `frontend/.../EngineActionsRail.test.ts` | **5/5 pass** |
