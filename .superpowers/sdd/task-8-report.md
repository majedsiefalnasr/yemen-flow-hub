# WP-14 Task 8 Report — Demo route production gate

**Status:** Complete  
**Branch:** `worktree-wp14-legacy-cleanup`  
**Date:** 2026-07-07

## Summary

Demo switch routes register only when `APP_ENV` is in `config('demo.allowed_environments')` (`local`, `staging`, `testing`) and `APP_DEMO_ROLE_SWITCH` is enabled. Production route list excludes all demo endpoints. Demo switches write `DEMO_USER_SWITCH` audit logs with actor, target, IP, and environment metadata. Frontend skips demo API calls when `runtimeConfig.public.demoEnabled` is false.

## Files changed

| File | Change |
|------|--------|
| `backend/config/demo.php` | `allowed_environments` whitelist |
| `backend/routes/api.php` | Env-gated demo route registration |
| `backend/app/Enums/AuditAction.php` | `DEMO_USER_SWITCH` action |
| `backend/app/Http/Controllers/Api/AuthController.php` | Audit on `switchDemoUser` / `switchDemoRole` |
| `backend/tests/Feature/Auth/DemoRouteEnvironmentGateTest.php` | Production route absence + audit tests |
| `frontend/nuxt.config.ts` | `demoEnabled` runtime flag |
| `frontend/app/stores/auth.store.ts` | Guard demo switch methods |
| `frontend/app/composables/useDemoUsers.ts` | Skip fetch when disabled |
| `frontend/app/components/auth/DemoUserSwitcherButton.vue` | Use `demoEnabled` |
| Frontend tests | Demo guard coverage |

## Verification

```bash
cd backend && php artisan test tests/Feature/Auth/DemoRouteEnvironmentGateTest.php tests/Feature/Auth/DemoUserSwitchTest.php
cd frontend && pnpm exec vitest run app/tests/unit/composables/useDemoUsers.test.ts app/tests/unit/stores/auth.store.test.ts -t "switchDemo|demo"
```

**Result:** 11 backend tests PASS (28 assertions); 5 frontend tests PASS
