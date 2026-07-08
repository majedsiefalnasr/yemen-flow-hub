# Backend Test Baseline Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore a trustworthy backend PHPUnit baseline by deleting obsolete tests, repairing fixture drift from the governance/engine migrations, fixing one stale production report query, and documenting any remaining environment-bound failures.

**Architecture:** Work by root-cause cluster, not by failing file. Most failures are stale tests/fixtures after the Trader deletion, pivot-role migration, organization classification migration, final-outcome validation, and engine projection changes; production code should change only where it still references removed schema such as `users.role`. Every task has narrow verification before commit, with the full backend suite reserved for inventory and final closeout.

**Tech Stack:** Laravel 11, PHP 8.2+, PHPUnit, SQLite test database, Laravel Sanctum, existing governance/engine models and seeders.

## Global Constraints

- Work from branch `fix/backend-test-baseline-remediation`.
- Start every task with `git -c core.fsmonitor=false status --short` from the root repo.
- Commit from the root repo only.
- Commit messages must use allowed scope `testing`, `backend`, or `workflow`.
- Keep commits signed; never use `--no-gpg-sign`, `--no-sign`, or `-c commit.gpgsign=false`.
- Include `Co-Authored-By: Claude <noreply@anthropic.com>` in every commit.
- Do not stage or commit `graphify-out/`.
- Use SocratiCode before changing existing services/models/controllers.
- Default remediation is to fix tests and fixtures to match current architecture; quarantine only proven flaky or environment-bound cases.

---

## Current Failure Inventory

Confirmed from `cd backend && php artisan test --compact` on 2026-07-08:

- Result: `75 failed, 53 passed, 1084 deprecated`
- Duration: `683.35s`
- Existing report: `docs/superpowers/2026-07-08-pre-existing-test-suite-failures.md`

Fresh targeted investigation added these root-cause clusters:

| Cluster | Files | Root Cause | Remediation |
| --- | --- | --- | --- |
| Obsolete Trader tests | `tests/Unit/{Models,Services,Policies,Http/Requests}/Trader*Test.php` | Trader subsystem and tables were intentionally deleted | Delete stale tests |
| Organization classification fixture drift | `IdentityGovernanceTest`, `PermissionServiceDerivedRequestsTest`, `BankTest` | Raw `Organization`/bank payloads omit now-required `classification` or `organization_id` | Add current classification/org fields |
| Pivot-role migration drift | `ProfileControllerTest`, `SettingsControllerTest`, `AuthorizationFailureAuditScopeTest`, `V1ReportsTest` | Tests and one report query still assume scalar `users.role` / `$user->role->value` | Use governance identity helpers and `asUserRole()`; remove `u.role` SQL fallback |
| MFA / queue environment drift | `AuthControllerTest` | MFA email dispatch reaches Redis queue in tests | Fake queue or force sync/database-safe queue in the affected tests |
| Demo role switch contract drift | `AuthControllerTest` | `/switch-demo-role` now requires a `role` request field | Send explicit role in the test |
| Password reset issuance assertion drift | `SecurityEmailRedactionTest` | Test name expects stable issuance, assertion expects two different IDs | Assert stable issuance ID |
| Bank/merchant lifecycle fixture drift or guard regression | `BankLifecycleGuardTest`, `MerchantIntegrityTest` | Fixtures do not fully model current organization/governance access and lifecycle dependencies | Repair fixtures first; fix production guard only if fixture repair still exposes a real regression |
| SLA / report / capacity projection drift | `Compliance*Test`, `OutcomeSemanticsTest`, `EngineReportTest`, `EngineSharedReadModelTest` | Tests predate `DataScope`, stage-entry SLA SQL, and ledger projection columns | Set org-scoped users and complete engine projection columns/history |
| Workflow validation and retention drift | `WorkflowStageTest`, `WorkflowVersionLifecycleTest`, `WorkflowVersionDeleteTest`, `WorkflowDefinitionDeleteTest` | Tests predate final-outcome publish validation and no-hard-delete published retention | Add `final_outcome` where needed; update assertions to current retention contract |
| Workflow dynamic option / graph label drift | `FieldDefinitionTest`, `WorkflowGraphTest` | Tests expect old data/label behavior after Trader deletion and graph label simplification | Use merchants with current scope fields; update label assertion to current graph contract |

---

### Task 1: Record Fresh Failure Inventory

**Files:**
- Create: `docs/superpowers/2026-07-08-backend-test-baseline-remediation-inventory.md`

**Interfaces:**
- Consumes: `docs/superpowers/2026-07-08-pre-existing-test-suite-failures.md`
- Produces: A working investigation ledger for the implementation wave.

- [ ] **Step 1: Confirm branch and clean status**

Run:
```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git branch --show-current
git -c core.fsmonitor=false status --short
```
Expected:
```text
fix/backend-test-baseline-remediation
```
and no dirty files.

- [ ] **Step 2: Create the inventory note**

Create `docs/superpowers/2026-07-08-backend-test-baseline-remediation-inventory.md`:

```markdown
# Backend Test Baseline Remediation Inventory - 2026-07-08

## Full Suite Snapshot

- Command: `cd backend && php artisan test --compact`
- Result before remediation: `75 failed, 53 passed, 1084 deprecated`
- Duration before remediation: `683.35s`

## Cluster Decisions

| Cluster | Files | Decision | Verification |
| --- | --- | --- | --- |
| Obsolete Trader tests | `tests/Unit/**/Trader*Test.php` | Delete stale tests; production surface removed | `rg -l "Trader|StoreTraderRequest|UpdateTraderRequest|TraderPolicy|TraderService" backend/app backend/tests` |
| Organization classification fixture drift | Governance and PermissionService tests | Add required `classification`/`organization_id` fixture data | Targeted governance and permission tests |
| Pivot-role migration drift | Profile, Settings, Audit, Report | Use governance role pivots and remove stale `users.role` SQL fallback | Targeted profile/settings/audit/report tests |
| Auth/notification contract drift | Auth and SecurityEmailRedaction tests | Fake queue, send required role, assert stable issuance | Targeted auth/notification tests |
| Bank/merchant lifecycle drift | BankLifecycleGuard and MerchantIntegrity tests | Repair fixtures; then fix real guard regression if still failing | Targeted lifecycle tests |
| SLA/report/capacity projection drift | Compliance, Engine read-model/report, OutcomeSemantics | Populate org-scoped users, stage history, merchant/projection columns | Targeted compliance/engine/workflow tests |
| Workflow validation/retention drift | Workflow lifecycle/stage/delete tests | Add `final_outcome`; align delete assertions to retention rules | Targeted workflow tests |
| Workflow option/graph drift | FieldDefinition and WorkflowGraph tests | Use current merchant scope fields; align graph label assertion | Targeted workflow tests |
| Environment-bound Redis queue | Auth MFA email path | Keep test-local queue fakes; do not require Redis for default test suite | Targeted auth tests |
```

- [ ] **Step 3: Commit**

Run:
```bash
git add docs/superpowers/2026-07-08-backend-test-baseline-remediation-inventory.md
git commit -m "docs(testing): record backend test baseline inventory" -m "Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 2: Delete Obsolete Trader Tests

**Files:**
- Delete: `backend/tests/Unit/Models/TraderSchemaTest.php`
- Delete: `backend/tests/Unit/Services/TraderServiceTest.php`
- Delete: `backend/tests/Unit/Services/TraderSnapshotTest.php`
- Delete: `backend/tests/Unit/Policies/TraderPolicyTest.php`
- Delete: `backend/tests/Unit/Http/Requests/TraderRequestValidationTest.php`

**Interfaces:**
- Consumes: The historical deletion of the Trader subsystem.
- Produces: No remaining PHPUnit failures for deleted Trader classes/tables.

- [ ] **Step 1: Verify production Trader surface is gone**

Run:
```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
rg -n "class Trader|TraderService|TraderPolicy|StoreTraderRequest|UpdateTraderRequest" backend/app backend/database
```
Expected: no output.

- [ ] **Step 2: Delete stale tests**

Run:
```bash
git rm backend/tests/Unit/Models/TraderSchemaTest.php \
       backend/tests/Unit/Services/TraderServiceTest.php \
       backend/tests/Unit/Services/TraderSnapshotTest.php \
       backend/tests/Unit/Policies/TraderPolicyTest.php \
       backend/tests/Unit/Http/Requests/TraderRequestValidationTest.php
```

- [ ] **Step 3: Verify no stale Trader tests remain**

Run:
```bash
rg -n "App\\\\Models\\\\Trader|TraderService|TraderPolicy|StoreTraderRequest|UpdateTraderRequest|trader_companies|trader_owners" backend/tests
```
Expected: no output.

- [ ] **Step 4: Commit**

Run:
```bash
git commit -m "test(testing): delete obsolete trader tests" -m "Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 3: Repair Organization Classification Fixture Drift

**Files:**
- Modify: `backend/tests/Feature/Governance/BankTest.php`
- Modify: `backend/tests/Feature/Governance/IdentityGovernanceTest.php`
- Modify: `backend/tests/Unit/Services/PermissionServiceDerivedRequestsTest.php`

**Interfaces:**
- Consumes: `App\Enums\OrganizationClassification`
- Produces: Test fixtures that satisfy the current `organizations.classification` and bank `organization_id` requirements.

- [ ] **Step 1: Add classification enum imports**

In both `IdentityGovernanceTest.php` and `PermissionServiceDerivedRequestsTest.php`, add:

```php
use App\Enums\OrganizationClassification;
```

- [ ] **Step 2: Update raw organization creates**

Every raw `Organization::query()->create([...])` or `DB::table('organizations')->insertGetId([...])` in the touched files must include classification.

Use this shape in `IdentityGovernanceTest.php`:

```php
$organization = Organization::query()->create([
    'code' => 'test_org',
    'name' => 'Test organization',
    'classification' => OrganizationClassification::OTHER,
]);
```

Use this shape in `PermissionServiceDerivedRequestsTest.php`:

```php
$orgId = DB::table('organizations')->insertGetId([
    'code' => 'ORG1',
    'name' => 'Org One',
    'classification' => OrganizationClassification::OTHER->value,
    'created_at' => now(),
    'updated_at' => now(),
]);
```

Repeat the same pattern for `ORG2` and `ORG3`.

- [ ] **Step 3: Update bank creation payload**

In `BankTest::test_create_bank_with_engine_fields_and_unique_swift`, load the commercial banks organization and include `organization_id` in payloads:

```php
$bankOrg = \App\Models\Organization::query()->where('code', 'commercial_banks')->firstOrFail();
$payload = [
    'organization_id' => $bankOrg->id,
    'code' => 'NEW',
    'name' => 'New Bank',
    'license_number' => 'LIC-1',
    'swift_code' => 'NEWBYESA',
    'status' => 'ACTIVE',
];
```

For the `NULL1` and `NULL2` payloads, also include `'organization_id' => $bankOrg->id`.

- [ ] **Step 4: Verify targeted files**

Run:
```bash
cd backend
php artisan test tests/Feature/Governance/BankTest.php tests/Feature/Governance/IdentityGovernanceTest.php tests/Unit/Services/PermissionServiceDerivedRequestsTest.php --compact
```
Expected: these files pass, apart from deprecation-only warnings marked with `!`.

- [ ] **Step 5: Commit**

Run:
```bash
cd ..
git add backend/tests/Feature/Governance/BankTest.php \
        backend/tests/Feature/Governance/IdentityGovernanceTest.php \
        backend/tests/Unit/Services/PermissionServiceDerivedRequestsTest.php
git commit -m "test(testing): align governance fixtures with organization classification" -m "Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 4: Repair Pivot-Role Drift And Report Role Query

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/ReportController.php`
- Modify: `backend/tests/Feature/Profile/ProfileControllerTest.php`
- Modify: `backend/tests/Feature/Settings/SettingsControllerTest.php`
- Modify: `backend/tests/Feature/Audit/AuthorizationFailureAuditScopeTest.php`
- Test: `backend/tests/Feature/Report/V1ReportsTest.php`

**Interfaces:**
- Consumes: `User::role()` method, `User::asUserRole()`, `Tests\Support\AssignsGovernanceIdentity`
- Produces: Tests and report query that no longer assume the removed `users.role` scalar column.

- [ ] **Step 1: Run SocratiCode impact checks**

Use SocratiCode:

```text
codebase_symbol ReportController
codebase_impact backend/app/Http/Controllers/Api/V1/ReportController.php
codebase_symbol User
codebase_impact backend/app/Models/User.php
```

Record any unexpected high-risk dependencies in the final task notes before editing.

- [ ] **Step 2: Fix `ReportController::teamPerformance` query**

In `backend/app/Http/Controllers/Api/V1/ReportController.php`, replace the `user_roles` join and role select/group expressions with active-pivot-only logic:

```php
->leftJoin('user_roles as ur', function ($join) {
    $join->on('ur.user_id', '=', 'u.id')
        ->where('ur.is_active', true);
})
->leftJoin('roles as r', 'r.id', '=', 'ur.role_id')
```

Replace:

```php
->selectRaw("COALESCE(r.name, u.role, 'Unknown') as role_name, COUNT(DISTINCT workflow_history.id) as actions")
```

with:

```php
->selectRaw("COALESCE(r.name, 'Unknown') as role_name, COUNT(DISTINCT workflow_history.id) as actions")
```

Replace:

```php
->groupByRaw("COALESCE(r.name, u.role, 'Unknown')")
```

with:

```php
->groupByRaw("COALESCE(r.name, 'Unknown')")
```

- [ ] **Step 3: Convert profile/settings/audit tests to governance identity**

For every test user in `ProfileControllerTest.php` and `SettingsControllerTest.php` that calls authenticated profile/settings endpoints, use the existing trait:

```php
use Tests\Support\AssignsGovernanceIdentity;

class ProfileControllerTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }
}
```

After each raw `User::query()->create([...])` for an authenticated user, wrap it:

```php
$user = $this->assignGovernanceIdentity($user, UserRole::CBY_ADMIN);
```

For bank-facing profile assertions, use `UserRole::DATA_ENTRY` and provide `bank_id` before assigning identity.

Replace `$user->role->value` assertions with:

```php
$this->assertSame($user->asUserRole()?->value, $auditLog->user_role);
```

In `AuthorizationFailureAuditScopeTest.php`, assign the actor a CBY admin governance identity before the gate-denial request.

- [ ] **Step 4: Verify targeted files**

Run:
```bash
cd backend
php artisan test tests/Feature/Profile/ProfileControllerTest.php tests/Feature/Settings/SettingsControllerTest.php tests/Feature/Audit/AuthorizationFailureAuditScopeTest.php tests/Feature/Report/V1ReportsTest.php --compact
```
Expected: the stale `User::role` relationship errors and `u.role` SQL error are gone.

- [ ] **Step 5: Format touched PHP files**

Run:
```bash
vendor/bin/pint app/Http/Controllers/Api/V1/ReportController.php tests/Feature/Profile/ProfileControllerTest.php tests/Feature/Settings/SettingsControllerTest.php tests/Feature/Audit/AuthorizationFailureAuditScopeTest.php --test
```
Expected: PASS.

- [ ] **Step 6: Commit**

Run:
```bash
cd ..
git add backend/app/Http/Controllers/Api/V1/ReportController.php \
        backend/tests/Feature/Profile/ProfileControllerTest.php \
        backend/tests/Feature/Settings/SettingsControllerTest.php \
        backend/tests/Feature/Audit/AuthorizationFailureAuditScopeTest.php
git commit -m "fix(backend): remove stale role scalar assumptions" -m "Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 5: Repair Auth And Notification Test Contract Drift

**Files:**
- Modify: `backend/tests/Feature/Auth/AuthControllerTest.php`
- Modify: `backend/tests/Feature/Notifications/SecurityEmailRedactionTest.php`

**Interfaces:**
- Consumes: `Queue::fake()`, `/api/auth/switch-demo-role` required `role`, stable notification issuance IDs
- Produces: Auth tests that do not require Redis and match current demo/notification contracts.

- [ ] **Step 1: Fake queue in MFA login tests that dispatch email jobs**

In `AuthControllerTest::test_login_returns_requires_mfa_when_mfa_enabled`, add before the login request:

```php
Queue::fake();
```

If `Queue` is not imported at the top of the file, add:

```php
use Illuminate\Support\Facades\Queue;
```

- [ ] **Step 2: Send required demo role payload**

In `AuthControllerTest::test_switch_demo_role_switches_session_when_enabled`, replace the empty request body with:

```php
[
    'role' => UserRole::CBY_ADMIN->value,
]
```

Keep the existing assertions for switched user id and role.

- [ ] **Step 3: Align stable password-reset issuance assertion**

In `SecurityEmailRedactionTest::test_password_reset_email_uses_stable_issuance_id_and_resolved_user_id`, replace:

```php
$this->assertNotSame($firstIssuanceId, $secondIssuanceId);
```

with:

```php
$this->assertSame($firstIssuanceId, $secondIssuanceId);
```

The test name and event-id assertions already describe stable issuance.

- [ ] **Step 4: Verify targeted files**

Run:
```bash
cd backend
php artisan test tests/Feature/Auth/AuthControllerTest.php tests/Feature/Notifications/SecurityEmailRedactionTest.php --compact
```
Expected: the Redis `Operation not permitted`, missing `role`, and issuance mismatch failures are gone.

- [ ] **Step 5: Commit**

Run:
```bash
cd ..
git add backend/tests/Feature/Auth/AuthControllerTest.php backend/tests/Feature/Notifications/SecurityEmailRedactionTest.php
git commit -m "test(testing): align auth notification tests with current contracts" -m "Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 6: Repair Workflow Validation, Retention, Options, And Graph Tests

**Files:**
- Modify: `backend/tests/Feature/Workflow/WorkflowVersionLifecycleTest.php`
- Modify: `backend/tests/Feature/Workflow/WorkflowStageTest.php`
- Modify: `backend/tests/Feature/Workflow/WorkflowVersionDeleteTest.php`
- Modify: `backend/tests/Feature/Workflow/WorkflowDefinitionDeleteTest.php`
- Modify: `backend/tests/Feature/Workflow/FieldDefinitionTest.php`
- Modify: `backend/tests/Feature/Workflow/WorkflowGraphTest.php`

**Interfaces:**
- Consumes: `App\Enums\FinalOutcome`, current no-hard-delete published workflow retention rules, current dynamic merchant options, current graph node label behavior
- Produces: Workflow tests that validate the current designer contract.

- [ ] **Step 1: Add final outcome to publishable final stages**

In `WorkflowVersionLifecycleTest.php`, import:

```php
use App\Enums\FinalOutcome;
```

In `validDraftVersion()`, change the final stage creation to:

```php
$done = $version->stages()->create([
    'code' => 'done',
    'name' => 'Done',
    'is_final' => true,
    'final_outcome' => FinalOutcome::COMPLETED,
    'sort_order' => 1,
]);
```

- [ ] **Step 2: Make initial+final validation tests reach the intended rule**

In both failing `WorkflowStageTest` requests, include:

```php
'final_outcome' => FinalOutcome::COMPLETED->value,
```

Keep `assertJsonValidationErrors('is_final')`.

- [ ] **Step 3: Align delete tests to retention rules**

In `WorkflowVersionDeleteTest.php`, update the published-version expectations:

```php
->assertStatus(422)
->assertJsonPath('error.code', 'PUBLISHED_NOT_DELETABLE');
```

Do not assert the published version is deleted.

In `WorkflowDefinitionDeleteTest.php`, if the definition contains any published version, assert:

```php
->assertStatus(422)
->assertJsonPath('error.code', 'DEFINITION_HAS_PUBLISHED_OR_ARCHIVED_VERSION');
```

If the production error code differs, use the actual current error code from the response and record it in the task notes.

- [ ] **Step 4: Fix merchant dynamic options fixture**

In `FieldDefinitionTest::test_dynamic_select_options_resolve_from_merchants`, ensure the authenticated admin has system-wide visibility and merchants satisfy current scope fields. Use active merchants with `status` and required columns:

```php
Merchant::query()->create([
    'name' => 'تاجر أ',
    'bank_id' => $bank->id,
    'tax_number' => '100',
    'status' => 'ACTIVE',
]);
Merchant::query()->create([
    'name' => 'تاجر ب',
    'bank_id' => $bank->id,
    'tax_number' => '200',
    'status' => 'ACTIVE',
]);
```

If options are still empty, inspect `DynamicFieldOptionsResolver` and add the required request/bank context rather than weakening data scope.

- [ ] **Step 5: Align graph label assertion**

In `WorkflowGraphTest::test_node_display_label_comes_from_stage_permissions`, update the assertion to the current graph node contract:

```php
$this->assertSame('Intake', $nodes->firstWhere('code', 'intake')['display_label']);
```

Keep the `StagePermission` row in the test only if another assertion verifies permissions are present; otherwise remove that row from the test.

- [ ] **Step 6: Verify targeted files**

Run:
```bash
cd backend
php artisan test tests/Feature/Workflow/WorkflowVersionLifecycleTest.php tests/Feature/Workflow/WorkflowStageTest.php tests/Feature/Workflow/WorkflowVersionDeleteTest.php tests/Feature/Workflow/WorkflowDefinitionDeleteTest.php tests/Feature/Workflow/FieldDefinitionTest.php tests/Feature/Workflow/WorkflowGraphTest.php --compact
```
Expected: these files pass, apart from deprecation-only warnings.

- [ ] **Step 7: Commit**

Run:
```bash
cd ..
git add backend/tests/Feature/Workflow/WorkflowVersionLifecycleTest.php \
        backend/tests/Feature/Workflow/WorkflowStageTest.php \
        backend/tests/Feature/Workflow/WorkflowVersionDeleteTest.php \
        backend/tests/Feature/Workflow/WorkflowDefinitionDeleteTest.php \
        backend/tests/Feature/Workflow/FieldDefinitionTest.php \
        backend/tests/Feature/Workflow/WorkflowGraphTest.php
git commit -m "test(workflow): align designer tests with current validation rules" -m "Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 7: Repair Engine Scope, SLA, And Capacity Fixtures

**Files:**
- Modify: `backend/tests/Feature/Engine/EngineSwiftUploadTest.php`
- Modify: `backend/tests/Feature/Engine/EngineVotingTest.php`
- Modify: `backend/tests/Feature/Engine/EngineRequestCanExecuteTest.php`
- Modify: `backend/tests/Feature/Engine/WorkflowStageRequiresClaimTest.php`
- Modify: `backend/tests/Feature/Engine/EngineSharedReadModelTest.php`
- Modify: `backend/tests/Feature/Engine/EngineReportTest.php`
- Modify: `backend/tests/Feature/Compliance/ComplianceDataScopeTest.php`
- Modify: `backend/tests/Feature/Compliance/ComplianceTest.php`
- Modify: `backend/tests/Feature/Workflow/OutcomeSemanticsTest.php`

**Interfaces:**
- Consumes: `Tests\Support\AssignsGovernanceIdentity`, `EngineRequest::scopeWithStageEntry`, `EngineFinancingLedger`
- Produces: Engine/report/compliance fixtures that satisfy organization-scoped visibility and projection-column requirements.

- [ ] **Step 1: Add organization identity to Engine executor fixtures**

In each Engine test file that creates users with `User::create()` or `User::factory()->create(['bank_id' => ...])`, assign governance identity after creation:

```php
$user = $this->assignGovernanceIdentity($user, UserRole::SWIFT_OFFICER);
```

Use the role that matches the stage permission being tested:

- SWIFT upload: `UserRole::SWIFT_OFFICER`
- Executive voting: `UserRole::EXECUTIVE_MEMBER`
- Director reject/final action: `UserRole::COMMITTEE_DIRECTOR`
- Bank submit/review: `UserRole::DATA_ENTRY` or `UserRole::BANK_REVIEWER`

Seed governance once in `setUp()` if the file does not already seed it:

```php
use Tests\Support\AssignsGovernanceIdentity;

use AssignsGovernanceIdentity;

protected function setUp(): void
{
    parent::setUp();
    $this->seedGovernance();
}
```

- [ ] **Step 2: Repair report/read-model users**

In `EngineSharedReadModelTest.php`, `EngineReportTest.php`, and `OutcomeSemanticsTest.php`, ensure every acting user has:

```php
'organization_id' => $organization->id,
```

or is passed through `assignGovernanceIdentity()`.

For CBY/National Committee users expected to see all banks, use `UserRole::CBY_ADMIN` or another national/system role with the relevant report capability seeded by `ScreenPermissionSeeder`.

- [ ] **Step 3: Repair SLA breach fixtures**

For every compliance SLA breach fixture, make sure the request has a stage history row that matches the current stage and is older than the stage SLA:

```php
WorkflowHistoryEntry::query()->create([
    'request_id' => $request->id,
    'from_stage_id' => null,
    'to_stage_id' => $this->stage->id,
    'performed_by' => $this->bankUser->id,
    'created_at' => now()->subHours(2),
    'updated_at' => now()->subHours(2),
]);
```

If the row is still not selected, verify that the request uses `status = 'ACTIVE'`, the stage has `sla_duration_minutes`, and the workflow version uses the current `state = 'PUBLISHED'` column.

- [ ] **Step 4: Repair financing capacity fixtures**

In `OutcomeSemanticsTest::test_abandoned_request_frees_financing_capacity`, ensure the created request has the columns used by `EngineFinancingLedger`:

```php
'merchant_id' => $merchant->id,
'invoice_number' => 'INV-CAP-1',
'invoice_number_normalized' => \App\Support\InvoiceKey::normalize('INV-CAP-1'),
'request_percentage' => 40,
'status' => 'ACTIVE',
```

Use the same normalized invoice number when asserting:

```php
$this->assertSame(40.0, $ledger->usedPercent($tax, 'INV-CAP-1'));
```

- [ ] **Step 5: Verify targeted files**

Run:
```bash
cd backend
php artisan test tests/Feature/Engine/EngineSwiftUploadTest.php tests/Feature/Engine/EngineVotingTest.php tests/Feature/Engine/EngineRequestCanExecuteTest.php tests/Feature/Engine/WorkflowStageRequiresClaimTest.php tests/Feature/Engine/EngineSharedReadModelTest.php tests/Feature/Engine/EngineReportTest.php tests/Feature/Compliance/ComplianceDataScopeTest.php tests/Feature/Compliance/ComplianceTest.php tests/Feature/Workflow/OutcomeSemanticsTest.php --compact
```
Expected: these files pass, apart from deprecation-only warnings.

- [ ] **Step 6: Commit**

Run:
```bash
cd ..
git add backend/tests/Feature/Engine/EngineSwiftUploadTest.php \
        backend/tests/Feature/Engine/EngineVotingTest.php \
        backend/tests/Feature/Engine/EngineRequestCanExecuteTest.php \
        backend/tests/Feature/Engine/WorkflowStageRequiresClaimTest.php \
        backend/tests/Feature/Engine/EngineSharedReadModelTest.php \
        backend/tests/Feature/Engine/EngineReportTest.php \
        backend/tests/Feature/Compliance/ComplianceDataScopeTest.php \
        backend/tests/Feature/Compliance/ComplianceTest.php \
        backend/tests/Feature/Workflow/OutcomeSemanticsTest.php
git commit -m "test(testing): align engine fixtures with organization scope" -m "Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 8: Repair Bank And Merchant Lifecycle Guards

**Files:**
- Modify: `backend/tests/Feature/Admin/BankLifecycleGuardTest.php`
- Modify: `backend/tests/Feature/Merchants/MerchantIntegrityTest.php`
- Possibly modify: `backend/app/Http/Controllers/Api/V1/BankController.php`
- Possibly modify: `backend/app/Http/Controllers/Api/V1/MerchantController.php`

**Interfaces:**
- Consumes: current bank/user/organization relationships and merchant data-scope rules
- Produces: lifecycle tests that distinguish fixture drift from real guard regressions.

- [ ] **Step 1: Run SocratiCode checks before controller edits**

Use SocratiCode only if fixture repair does not fix the failures:

```text
codebase_symbol BankController
codebase_impact backend/app/Http/Controllers/Api/V1/BankController.php
codebase_symbol MerchantController
codebase_impact backend/app/Http/Controllers/Api/V1/MerchantController.php
```

- [ ] **Step 2: Repair lifecycle fixtures first**

In `BankLifecycleGuardTest::bank()`, set the bank organization to the commercial banks organization:

```php
$organization = \App\Models\Organization::query()->where('code', 'commercial_banks')->firstOrFail();

return Bank::query()->create(array_merge([
    'name' => 'WP0 Test Bank',
    'code' => 'WP0BANK',
    'organization_id' => $organization->id,
    'status' => 'ACTIVE',
    'is_active' => true,
], $attributes));
```

In `MerchantIntegrityTest::setUp()`, seed governance before screen permissions and assign identities instead of attaching roles manually:

```php
$this->seed(\Database\Seeders\GovernanceSeeder::class);
$this->seed(ScreenPermissionSeeder::class);
```

For bank admin and CBY admin users, use `AssignsGovernanceIdentity`:

```php
$this->bankAdmin = $this->assignGovernanceIdentity($this->bankAdmin, UserRole::BANK_ADMIN);
$this->cbyadmin = $this->assignGovernanceIdentity($this->cbyadmin, UserRole::CBY_ADMIN);
```

If the CBY admin still cannot manage merchants because the current permission model intentionally denies that capability, update the test to use an authorized bank admin for immutable-bank checks or grant the exact screen permission row in the fixture.

- [ ] **Step 3: Fix production guard only if still failing**

If `BankLifecycleGuardTest::test_bank_with_user_is_blocked_from_lifecycle_removal` still returns `200` after fixture repair, update the bank lifecycle guard to count active users by `users.bank_id = banks.id`, not only organization references.

If `MerchantIntegrityTest::test_bank_change_blocked_after_first_request` still returns `404`, inspect whether the controller hides out-of-scope merchants before the immutable-bank guard. Fix only if a system/national actor with manage capability should reach that guard under current docs.

- [ ] **Step 4: Verify targeted files**

Run:
```bash
cd backend
php artisan test tests/Feature/Admin/BankLifecycleGuardTest.php tests/Feature/Merchants/MerchantIntegrityTest.php --compact
```
Expected: lifecycle files pass.

- [ ] **Step 5: Format if production controllers changed**

Run only if controller files changed:

```bash
vendor/bin/pint app/Http/Controllers/Api/V1/BankController.php app/Http/Controllers/Api/V1/MerchantController.php --test
```

- [ ] **Step 6: Commit**

Run:
```bash
cd ..
git add backend/tests/Feature/Admin/BankLifecycleGuardTest.php backend/tests/Feature/Merchants/MerchantIntegrityTest.php
git add backend/app/Http/Controllers/Api/V1/BankController.php backend/app/Http/Controllers/Api/V1/MerchantController.php
git commit -m "test(testing): repair lifecycle guard fixtures" -m "Co-Authored-By: Claude <noreply@anthropic.com>"
```

If no controller file changed, `git add` will ignore them if absent from the diff; verify `git status --short` before committing.

---

### Task 9: Final Backend Verification And Remaining-Failure Policy

**Files:**
- Modify: `docs/superpowers/2026-07-08-backend-test-baseline-remediation-inventory.md`

**Interfaces:**
- Consumes: all prior task commits.
- Produces: final backend suite result and any remaining quarantine/environment note.

- [ ] **Step 1: Run focused aggregate checks**

Run:
```bash
cd backend
php artisan test tests/Feature/Governance/BankTest.php tests/Feature/Governance/IdentityGovernanceTest.php tests/Feature/Governance/TeamTest.php tests/Feature/Profile/ProfileControllerTest.php tests/Feature/Settings/SettingsControllerTest.php tests/Feature/Auth/AuthControllerTest.php tests/Feature/Notifications/SecurityEmailRedactionTest.php tests/Feature/Admin/BankLifecycleGuardTest.php tests/Feature/Merchants/MerchantIntegrityTest.php tests/Feature/Audit/AuthorizationFailureAuditScopeTest.php tests/Feature/Report/V1ReportsTest.php tests/Unit/Services/PermissionServiceDerivedRequestsTest.php --compact
```
Expected: targeted previously-uninvestigated files pass, apart from deprecation-only warnings.

- [ ] **Step 2: Run engine/workflow aggregate checks**

Run:
```bash
php artisan test tests/Feature/Engine/EngineSwiftUploadTest.php tests/Feature/Engine/EngineVotingTest.php tests/Feature/Engine/EngineRequestCanExecuteTest.php tests/Feature/Engine/WorkflowStageRequiresClaimTest.php tests/Feature/Engine/EngineSharedReadModelTest.php tests/Feature/Engine/EngineReportTest.php tests/Feature/Workflow/WorkflowVersionLifecycleTest.php tests/Feature/Workflow/WorkflowStageTest.php tests/Feature/Workflow/WorkflowVersionDeleteTest.php tests/Feature/Workflow/WorkflowDefinitionDeleteTest.php tests/Feature/Workflow/FieldDefinitionTest.php tests/Feature/Workflow/WorkflowGraphTest.php tests/Feature/Workflow/OutcomeSemanticsTest.php --compact
```
Expected: targeted confirmed clusters pass, apart from deprecation-only warnings.

- [ ] **Step 3: Run full backend suite**

Run:
```bash
php artisan test --compact
```
Expected: materially fewer failures than the starting `75 failed, 53 passed, 1084 deprecated`. The preferred result is zero failures with deprecations only.

- [ ] **Step 4: Update inventory with final result**

Append this section to `docs/superpowers/2026-07-08-backend-test-baseline-remediation-inventory.md`:

```markdown
## Final Suite Snapshot

- Command: `cd backend && php artisan test --compact`
- Result after remediation: replace this sentence with the exact final PHPUnit summary line from Step 3.
- Remaining failures: write `none` when there are no failures; otherwise list each failing file and test method from Step 3.
- Deprecations: write the exact deprecation count from Step 3.
- Environment-bound notes: write `none` when there are no environment failures; otherwise list the exact Redis, queue, filesystem, or sandbox condition from Step 3.
```

- [ ] **Step 5: Run backend formatter check**

Run:
```bash
composer format:check
```
Expected: PASS.

- [ ] **Step 6: Commit final inventory**

Run:
```bash
cd ..
git add docs/superpowers/2026-07-08-backend-test-baseline-remediation-inventory.md
git commit -m "docs(testing): record backend test baseline closeout" -m "Co-Authored-By: Claude <noreply@anthropic.com>"
```

If the final suite is fully green and the inventory already has exact final results in a prior commit, skip this commit and leave the worktree clean.
