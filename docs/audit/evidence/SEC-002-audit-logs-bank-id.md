# SEC-002 — audit_logs.bank_id + scoped reads

## What changed

### 1. Schema: `audit_logs.bank_id` + covering index

Migration `2026_07_09_100005_add_bank_id_to_audit_logs.php` adds `bank_id` (nullable, since not every audited entity has a bank) and `al_bank_created (bank_id, created_at)` — following the exact chunked-backfill pattern already shipped for `stage_entered_at` (`2026_07_09_100003_add_stage_entered_at_to_engine_requests.php`).

**Backfill resolution rule** (applied both in the migration for existing rows and in `AuditService` for future writes — same source of truth):

- Subject is a `Bank` → use its own `id`.
- Subject is a `User`/`Merchant`/`EngineRequest` → use that row's `bank_id` column.
- Anything else (`Organization`, `Role`, settings, no subject) → `null`. **Never falls back to the acting user's bank** — a CBY staff actor (Support Committee, SWIFT Officer, etc.) is not bank-scoped, and guessing would misattribute the row to the wrong bank.

Verified against the real dev DB (`cby_imports`, 258 pre-existing `audit_logs` rows, all `subject_type = App\Models\User`): 97 rows correctly backfilled with the subject user's `bank_id`; 161 correctly left `null` (CBY-side users with no bank). Spot-checked both null and non-null cases against the source `users` table directly. Migration tested: run → verified → rollback → verified column dropped → re-migrate → verified backfill re-ran correctly.

### 2. `AuditService::log()` resolves `bank_id` at write time

New `resolveBankId()` private method applies the same rule above, so every future audit write (118 call sites across the app, all going through this single method) is bank-scoped automatically — no per-call-site changes needed.

### 3. Scoped reads enabled

- `AuditLogPolicy::viewAny()` — previously only `systemWide` users passed; now a bank-scoped user (`ownBankId !== null`) with the `audit.VIEW` capability also passes. Query-level scoping (already-shipped `DataScope::applyTo($query, $scope)` in `index()`) now works correctly since the column exists.
- `AuditLogController::show()` — previously **denied every non-systemWide user outright** (`// Audit logs don't have bank_id yet, so for now non-systemWide users see nothing`). Now checks the specific log's `bank_id` against the requester's own bank; mismatched or null `bank_id` on a non-systemWide request still returns 403.
- `GenerateAuditLogExport`'s existing `DataScope::applyTo($query, $scope)` call (built in API-004, already correct) now genuinely bank-scopes exports for a bank-admin requester — a correctness improvement that falls out of the schema change with no code change needed there.

### 4. Capability grant: `bank_admin` → `audit.VIEW`

`ScreenPermissionSeeder` previously granted **no** `audit` capability to `bank_admin` at all — the finding's "bank admins get zero scoped audit visibility" was true at both the policy layer and the permission-grant layer. Added `'audit' => ['VIEW']` to the `bank_admin` grant, matching the roadmap's own verification checklist ("after `bank_id` backfill, a bank admin sees only their bank's audit rows").

## Why this needed confirmation before starting

Granting a new capability to an existing role is a security-relevant policy change, not just a scoping bugfix — confirmed with the user before writing any code, per the audit remediation plan's explicit "stop before SEC-002" checkpoint.

## Test evidence

- `backend/tests/Feature/Audit/AuditServiceBankResolutionTest.php` (6 tests, new): resolves from `Bank`/`User`/`Merchant`/`EngineRequest` subjects; `null` for no-bank-concept subjects (`Organization`); `null` when no subject given, even with a bank-scoped actor (proves no actor-bank fallback).
- `backend/tests/Feature/Admin/AuditScopeTest.php` — rewrote `banking_sector_user_with_audit_view_capability_is_denied_access` (asserted blanket 403) into `banking_sector_user_with_audit_view_capability_sees_only_own_bank_rows` (asserts scoped access now works: 2 rows seeded across 2 banks, bank admin sees only 1). Added `banking_sector_user_without_a_bank_id_is_denied_access` (a bank-org user with no `bank_id` set must not fall through to seeing all rows).
- `backend/tests/Feature/Audit/V1/AuditLogControllerTest.php` — added `test_show_returns_own_bank_log_but_denies_cross_bank_log`: own-bank log → 200; a second bank's log → 403. This is the explicit cross-bank leakage test the roadmap's verification checklist calls for.

```
AuditServiceBankResolutionTest: 6 tests, 6 assertions
AuditScopeTest: 5 tests (2 new/rewritten), 7 assertions
AuditLogControllerTest: 13 tests (1 new), 43 assertions
```

## Regression check

Full backend suite rerun (security-critical, broad-scoping change per the verification ladder's exception).

## Residual

`AuditLogController::export()`/`GenerateAuditLogExport` were not given an explicit new test for bank-scoped export content (beyond the already-existing `DataScope::applyTo` call now working correctly) — the job's scope-application logic was unit-tested in API-004's own test suite (`GenerateAuditLogExportTest`) using a mocked scope; a full end-to-end bank-scoped export test would be a reasonable follow-up but is not required by SEC-002's own scope (export bank-scoping is a consequence of the schema fix, not something SEC-002 asked for directly).
