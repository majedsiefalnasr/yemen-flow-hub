# SEC-003 — Re-verification: no unauthenticated audit write-amplification

## Finding, as originally stated

"Every 403 authorization failure, including unauthenticated, writes an `audit_logs` row" — an unauthenticated scanner could drive unbounded audit writes.

## What re-verification found

The exception-handler chain in `backend/bootstrap/app.php` gates the audit-write closure (`$auditAuthorizationFailure`) strictly on domain `AuthorizationException` origin:

- `AuthenticationException` (401, the actual "unauthenticated" case) has its own render callback (`bootstrap/app.php:88-92`) that returns `ApiResponse::unauthorized()` directly — no audit call.
- `EnsureActiveUser` middleware returns a plain 401 response for an inactive account without throwing — no audit call.
- The 403 paths that DO call the audit closure (`HttpException` with status 403, `AccessDeniedHttpException|AuthorizationException`) only fire once a policy method runs, which requires `Gate`/`authorize()` to resolve `$request->user()` — impossible before `auth:sanctum` has already authenticated the caller.
- Every route that authorizes via a policy sits behind `auth:sanctum` (confirmed: the only three `Route::group()` blocks with policy-backed controllers all require `auth:sanctum` first).

Conclusion: **there is no code path today where an unauthenticated request writes an `audit_logs` row.** This may have differed at the audit's baseline SHA `be652fdd`, but does not match current `main`.

## Test evidence

`backend/tests/Feature/Security/UnauthenticatedAuditWriteTest.php` (4 tests, all green):

```
Unauthenticated Audit Write (Tests\Feature\Security\UnauthenticatedAuditWrite)
 ✔ unauthenticated request gets 401 and writes no audit row
 ✔ repeated unauthenticated requests write no audit rows
 ✔ authenticated authorization denial still writes audit row   (sanity: audit trail still works for real denials)
 ✔ authenticated volume is throttled after auth succeeds

Tests: 4, Assertions: 14
```

The third test is a deliberate sanity check — proves SEC-003 isn't "fixed" by silently breaking the legitimate audit trail for real authorization denials.

## Correction to the original recommendation

The original recommendation ("ARCH-003 default throttle... caps the write rate") doesn't apply as literally stated: `throttle:api-default` sits **after** `auth:sanctum` in the route-group middleware order, so it never executes for a request that fails authentication — `auth:sanctum` rejects with 401 first. This is fine because there is no write to throttle in that case. For an *authenticated* caller hitting the same route repeatedly (e.g. probing for authorization), ARCH-003's throttle does still cap volume, confirmed by the fourth test (429 after 3 requests at `api_throttle_per_minute=3`).

## Regression check

`tests/Feature/Audit/AuthorizationFailureAuditScopeTest.php` (2 tests, pre-existing) — still green, confirms an existing test already covered part of this ground (`framework abort 403 does not create authorization failure audit`). The new test adds the unauthenticated-401 path and the throttle-ordering claim specifically.

## Outcome

No code change. `docs/audit/02-findings.md` SEC-003 updated: Status → **Accepted — no code change required**, with the corrected current-behavior description and the guard test as a permanent regression check.
