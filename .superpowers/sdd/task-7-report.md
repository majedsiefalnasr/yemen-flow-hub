# WP-14 Task 7 Report — API envelope tolerance (R9)

**Status:** Complete  
**Branch:** `worktree-wp14-legacy-cleanup`  
**Date:** 2026-07-07

## Summary

Unified frontend API error extraction to tolerate both legacy (`message`, `error_code`, `errors`) and rich R9 envelopes (`error.code`, `error.message`, `error.fields`, `request_id`). Re-exported helpers from `useApi` for composable consumers.

## Files changed

| File | Change |
|------|--------|
| `frontend/app/utils/apiErrors.ts` | `extractRequestId`; rich `error.fields` in `extractApiFieldErrors`; shared payload reader |
| `frontend/app/composables/useApi.ts` | Re-export error extraction helpers |
| `frontend/app/tests/unit/utils/apiErrors.test.ts` | New tests for both envelope shapes |
| `backend/tests/Feature/V1/ReportPresetTest.php` | Assert rich 422 envelope on validation |

## Verification

```bash
cd frontend && pnpm exec vitest run app/tests/unit/utils/apiErrors.test.ts
cd backend && php artisan test tests/Feature/V1/ReportPresetTest.php
```

**Result:** 6 frontend tests PASS; backend preset tests PASS
