# Pre-existing Test Suite Failures — 2026-07-08

**Status:** Findings only, not fixed. Discovered while verifying the engine demo seeder redesign branch (`feat/engine-demo-seeder-redesign`) — confirmed pre-existing on `main` (`8314020`), unrelated to that branch, which touches only `backend/database/seeders/`.

**Scope:** Full `php artisan test` run: 1212 tests, 53 passed, 72 failed, 1087 deprecated-only. 72 failures span 32 files. This document covers 4 confirmed root causes across ~15 of those files; the remaining ~17 files were not investigated (Profile, Governance, Compliance, Permission, Notifications, Merchants clusters — likely additional distinct causes).

---

## 1. Dead Trader test suite

**Root cause:** `App\Models\Trader` and its tables were deleted in commit `9a17938a` ("refactor(backend): delete trader subsystem, remove stale RequestVote relation"), which dropped the tables via migration `2026_07_01_000001_drop_trader_tables.php` — but the test files for that subsystem were never removed.

**Affected files (all fatal with `Class "App\Models\Trader" not found`):**
- `backend/tests/Unit/Models/TraderSchemaTest.php`
- `backend/tests/Unit/Services/TraderServiceTest.php`
- `backend/tests/Unit/Policies/TraderPolicyTest.php`
- `backend/tests/Unit/Http/Requests/TraderRequestValidationTest.php`
- `backend/tests/Unit/Services/TraderSnapshotTest.php`

**Suggested fix:** Delete these 5 test files (mirrors the already-deleted subsystem). Confirm via `rg -l Trader backend/app` returns nothing relevant first.

---

## 2. Stage permission checks require `organization_id`, but many Engine test fixtures never set it

**Root cause:** `StagePermissionResolver::identityMatchesAny()` (`backend/app/Services/Workflow/StagePermissionResolver.php:34`) returns `false` immediately when `identity['organization_id'] === null`, regardless of whether any `StagePermission` row would otherwise match. This hard requirement was added by WP-1 (`f8ef7c1e`, organization classification foundation) and WP-7 (`13319c66`). Several Engine test fixtures build users directly with `User::create()` / `User::factory()->create(['bank_id' => ...])` and never set `organization_id`, so every permission check on those users now fails with `WORKFLOW_FORBIDDEN` (403) even when a matching `StagePermission` row exists.

**Affected files:**
- `backend/tests/Feature/Engine/EngineSwiftUploadTest.php` (setUp creates `swiftOfficer`/`wrongRoleUser` without `organization_id`; `StagePermission::create(['user_id' => ...])` grant is unreachable)
- `backend/tests/Feature/Engine/EngineVotingTest.php`
- `backend/tests/Feature/Engine/EngineRequestCanExecuteTest.php`
- `backend/tests/Feature/Engine/WorkflowStageRequiresClaimTest.php`

**Suggested fix:** Update each fixture's `User::create()`/`User::factory()->create()` call to also set `organization_id` (matching the bank's or the relevant governance org), and re-run to confirm the `StagePermission` rows then match as intended. Consider adding a factory default or a shared test helper (e.g. in `tests/Support/`) so future Engine test fixtures can't omit it silently.

---

## 3. `DataScope` silently empties queries for users without `organization_id`

**Root cause:** `DataScope::forUser()` (`backend/app/Services/Authorization/DataScope.php:17`) returns `DataScopeContext(systemWide: false, ownBankId: null)` when `$user->organization` is unset. Scoped queries built from that context then filter to `bank_id = null`, silently returning zero rows instead of erroring — so read-model/report tests get empty results where they expect fixture data.

**Affected files:**
- `backend/tests/Feature/Engine/EngineSharedReadModelTest.php` (`bucket mapping` expects `[2]`, gets `[]`)
- `backend/tests/Feature/Engine/EngineReportTest.php` (`by bank endpoint`, `by currency endpoint`, `requests over time`, `summary endpoint` — all expect populated `data` arrays, get empty ones)

**Suggested fix:** Same fix as #2 — set `organization_id` on the fixture users in these files. This is the same underlying gap (fixtures predate WP-1/WP-7's org-classification requirement) surfacing through a different code path (`DataScope` vs `StagePermissionResolver`).

---

## 4. Publish validation now requires `final_outcome` on every final stage; Workflow test fixtures predate that rule

**Root cause:** `WorkflowPublishRulePack` (`backend/app/Services/Workflow/WorkflowPublishRulePack.php:218`) rejects publishing a workflow version if any `is_final` stage has `final_outcome === null`. This rule was added in WP-9 (`fd136b1f`). `WorkflowVersionLifecycleTest::validDraftVersion()` (line 290) creates its final stage with only `'is_final' => true`, never setting `final_outcome` — a fixture last touched in WP-3 (`5aced82c`), before the WP-9 rule existed.

**Affected files:**
- `backend/tests/Feature/Workflow/WorkflowVersionLifecycleTest.php` — `test_publish_then_archive_lifecycle` expects `200`, gets `422` with `"final_outcome": ["The final outcome field is required."]`
- `backend/tests/Feature/Workflow/WorkflowStageTest.php` — `stage cannot be both initial and final` expects a validation error on `is_final`, gets one on `final_outcome` instead (same underlying fixture gap, different assertion)
- Likely `backend/tests/Feature/Workflow/WorkflowVersionDeleteTest.php` (assertion mismatch `WORKFLOW_VERSION_IN_USE` vs `PUBLISHED_NOT_DELETABLE` — not fully traced, but in the same file cluster and plausibly related to the same publish-lifecycle drift)

**Suggested fix:** Update `validDraftVersion()` and any other Workflow test fixture that creates a final stage to also set `final_outcome` (e.g. `FinalOutcome::COMPLETED`). Re-run `WorkflowVersionDeleteTest` after the fix to confirm whether its failure was downstream of the same root cause or a separate issue.

---

## Not investigated

~17 remaining failing files were not traced to root cause, per explicit scope decision to stop after covering the two clearest clusters:

- `Feature\Profile\ProfileControllerTest` (6 failures)
- `Feature\Governance\{BankTest,IdentityGovernanceTest,TeamTest}` (4 failures)
- `Feature\Compliance\{ComplianceDataScopeTest,ComplianceTest}` (3 failures)
- `Feature\Permission\DerivedRequestsEnforcementTest` (2 failures)
- `Feature\Settings\SettingsControllerTest`, `Feature\Notifications\SecurityEmailRedactionTest`, `Feature\Merchants\MerchantIntegrityTest`, `Feature\Admin\BankLifecycleGuardTest`, `Feature\Audit\AuthorizationFailureAuditScopeTest`, `Feature\Auth\AuthControllerTest`, `Feature\Report\V1ReportsTest`, `Unit\Services\PermissionServiceDerivedRequestsTest` (1 each)

Re-run `cd backend && php artisan test --compact` and grep `^   FAILED` to regenerate the current list before picking this up.
