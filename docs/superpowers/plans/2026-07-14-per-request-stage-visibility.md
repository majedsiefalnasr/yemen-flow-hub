# Per-Request Stage & History Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Filter the per-request stage graph (`/api/v1/engine-requests/{id}/graph`) to only the stages the viewing user has `StagePermission` VIEW/EXECUTE access to, and redact `workflow_history` entries the user has no stage access to (except their own past actions, shown sanitized) — so the "سير العملية التنظيمية" process rail and timeline show a role/team/org-scoped journey instead of every designer-defined stage unconditionally.

**Architecture:** Two independent, additive backend changes behind the existing single-gate `EngineRequestPolicy::view()` check: (1) node/edge filtering in `EngineRequestController::graph()` using the same `array_intersect(accessibleStageIds(...), $versionStageIds)` pattern already used for `execute_stage_ids`, applied to the node/edge arrays themselves; (2) a new `StageHistoryVisibilityResolver` service that classifies each `WorkflowHistoryEntry` as FULL/SANITIZED/HIDDEN before `EngineRequestController::history()` serializes it. The frontend requires zero component changes for stage filtering (rail/stepper/progress all derive from the already-filtered `graph` object) and one type + composable + component change for history redaction display. The Workflow Designer's own graph endpoint (`WorkflowVersionController`) and `WorkflowGraphService::build()` are untouched — they stay version-only, user-agnostic.

**Tech Stack:** Laravel 11 (PHP 8.2+), PHPUnit feature tests; Nuxt 4 / Vue 3.5 / TypeScript, Vitest.

## Global Constraints

- `SYSTEM_ADMIN` (via `RoleCodes::SYSTEM_ADMIN` / `$user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)`) bypasses both new filters entirely — sees all stages, sees full unredacted history. No other role name (never `CBY_ADMIN`) is used for this bypass.
- Stage visibility threshold is `StageAccessLevel::VIEW` (which already includes EXECUTE via `satisfies()`) — one call to `accessibleStageIds($user, StageAccessLevel::VIEW)`, not two.
- Dangling edges (either endpoint stage filtered out) are dropped entirely from the response.
- History redaction: viewer has stage access → full entry. Viewer lacks access but `performed_by === viewer.id` → sanitized entry (own name kept, generic label, no `action_code`/`comments`/stage identifiers). Viewer lacks access and is not the actor → entry omitted entirely.
- The Workflow Designer's own per-version graph endpoint (`WorkflowVersionController` → `GET /workflow-versions/{v}/graph`, consumed by `WorkflowProcessGraph.vue`) is explicitly **out of scope** — must keep showing the full topology unfiltered.
- `audit_logs` (separate table/controller) and the `ReportExportController`/`GenerateReportExport` CSV export are explicitly **out of scope** for this plan — pre-existing, unrelated gaps, not to be touched.
- Verification ladder per `AGENTS.md`: run only the specific new/modified test files. Do not run full `php artisan test` or full `pnpm test`.

---

### Task 1: `StageHistoryVisibility` enum + `StageHistoryVisibilityResolver` service

**Files:**
- Create: `backend/app/Enums/StageHistoryVisibility.php`
- Create: `backend/app/Services/Workflow/StageHistoryVisibilityResolver.php`
- Test: `backend/tests/Unit/Services/Workflow/StageHistoryVisibilityResolverTest.php`

**Interfaces:**
- Consumes: `App\Services\Workflow\StagePermissionResolver::userCanAccessStage(User $user, WorkflowStage $stage, StageAccessLevel $required): bool` (existing, `backend/app/Services/Workflow/StagePermissionResolver.php:30`); `App\Support\RoleCodes::SYSTEM_ADMIN` (existing, `backend/app/Support/RoleCodes.php:24`); `App\Models\User::hasRoleCode(string $code): bool` (existing, `backend/app/Models/User.php:234`); `App\Models\WorkflowHistoryEntry` (existing model, `performed_by`, `toStage()`, `fromStage()` relations).
- Produces: `App\Enums\StageHistoryVisibility` with cases `FULL`, `SANITIZED`, `HIDDEN`; `App\Services\Workflow\StageHistoryVisibilityResolver::visibilityFor(User $user, WorkflowHistoryEntry $entry): StageHistoryVisibility` — consumed by Task 2.

- [ ] **Step 1: Write the failing unit test**

```php
<?php

namespace Tests\Unit\Services\Workflow;

use App\Enums\StageAccessLevel;
use App\Enums\StageHistoryVisibility;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\StageHistoryVisibilityResolver;
use App\Services\Workflow\StagePermissionResolver;
use App\Enums\WorkflowVersionState;
use App\Support\RoleCodes;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageHistoryVisibilityResolverTest extends TestCase
{
    use RefreshDatabase;

    private StageHistoryVisibilityResolver $resolver;

    private User $userWithAccess;

    private User $userWithoutAccess;

    private User $systemAdmin;

    private WorkflowStage $restrictedStage;

    private WorkflowHistoryEntry $entryByOtherUser;

    private WorkflowHistoryEntry $entryByUserWithoutAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);
        $this->resolver = new StageHistoryVisibilityResolver(new StagePermissionResolver);

        $org = \App\Models\Organization::query()->where('code', 'national_committee')->first();
        $role = \App\Models\Role::query()->where('code', 'support')->first();

        $this->userWithAccess = User::create([
            'name' => 'Has Access', 'email' => 'has-access@test.cby',
            'password' => bcrypt('password'), 'organization_id' => $org->id, 'is_active' => true,
        ]);
        $this->userWithAccess->roles()->attach($role);

        $this->userWithoutAccess = User::create([
            'name' => 'No Access', 'email' => 'no-access@test.cby',
            'password' => bcrypt('password'), 'organization_id' => $org->id, 'is_active' => true,
        ]);

        $otherRole = \App\Models\Role::query()->where('code', 'intake')->first();
        $this->userWithoutAccess->roles()->attach($otherRole);

        $this->systemAdmin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.cby',
            'password' => bcrypt('password'), 'organization_id' => $org->id, 'is_active' => true,
        ]);
        $adminRole = \App\Models\Role::query()->where('code', RoleCodes::SYSTEM_ADMIN)->first();
        $this->systemAdmin->roles()->attach($adminRole);

        $definition = WorkflowDefinition::create(['code' => 'test_visibility', 'name' => 'Test']);
        $version = $definition->versions()->create([
            'version_number' => 1, 'state' => WorkflowVersionState::PUBLISHED, 'published_at' => now(),
        ])->refresh();

        $entryStage = $version->stages()->create(['code' => 'ENTRY', 'name' => 'Entry', 'is_initial' => true, 'sort_order' => 1]);
        $this->restrictedStage = $version->stages()->create(['code' => 'RESTRICTED', 'name' => 'Restricted', 'is_final' => true, 'sort_order' => 2]);

        StagePermission::create([
            'stage_id' => $this->restrictedStage->id,
            'organization_id' => $org->id,
            'role_id' => $role->id,
            'access_level' => StageAccessLevel::VIEW,
            'version' => 1,
        ]);

        $action = WorkflowAction::create(['code' => 'APPROVE_TEST', 'name' => 'Approve', 'kind' => 'APPROVE', 'is_active' => true]);
        WorkflowTransition::create([
            'workflow_version_id' => $version->id,
            'from_stage_id' => $entryStage->id,
            'to_stage_id' => $this->restrictedStage->id,
            'action_id' => $action->id,
        ]);

        $requestRow = \App\Models\EngineRequest::create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $this->restrictedStage->id,
            'status' => 'ACTIVE',
            'data' => [],
            'version' => 1,
        ]);

        $this->entryByOtherUser = WorkflowHistoryEntry::create([
            'request_id' => $requestRow->id,
            'from_stage_id' => $entryStage->id,
            'to_stage_id' => $this->restrictedStage->id,
            'action_code' => 'APPROVE_TEST',
            'performed_by' => $this->userWithAccess->id,
            'comments' => 'secret comment',
            'created_at' => now(),
        ]);

        $this->entryByUserWithoutAccess = WorkflowHistoryEntry::create([
            'request_id' => $requestRow->id,
            'from_stage_id' => $entryStage->id,
            'to_stage_id' => $this->restrictedStage->id,
            'action_code' => 'APPROVE_TEST',
            'performed_by' => $this->userWithoutAccess->id,
            'comments' => 'my own secret comment',
            'created_at' => now(),
        ]);
    }

    public function test_user_with_stage_access_sees_full_visibility(): void
    {
        $this->assertSame(
            StageHistoryVisibility::FULL,
            $this->resolver->visibilityFor($this->userWithAccess, $this->entryByOtherUser),
        );
    }

    public function test_actor_without_stage_access_sees_sanitized_visibility_on_own_entry(): void
    {
        $this->assertSame(
            StageHistoryVisibility::SANITIZED,
            $this->resolver->visibilityFor($this->userWithoutAccess, $this->entryByUserWithoutAccess),
        );
    }

    public function test_non_actor_without_stage_access_sees_hidden_visibility(): void
    {
        $this->assertSame(
            StageHistoryVisibility::HIDDEN,
            $this->resolver->visibilityFor($this->userWithoutAccess, $this->entryByOtherUser),
        );
    }

    public function test_system_admin_always_sees_full_visibility(): void
    {
        $this->assertSame(
            StageHistoryVisibility::FULL,
            $this->resolver->visibilityFor($this->systemAdmin, $this->entryByOtherUser),
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Workflow/StageHistoryVisibilityResolverTest.php`
Expected: FAIL — `Class "App\Enums\StageHistoryVisibility" not found` (or similar autoload error, since neither the enum nor the service class exists yet).

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

enum StageHistoryVisibility
{
    case FULL;
    case SANITIZED;
    case HIDDEN;
}
```

- [ ] **Step 4: Write the resolver service**

```php
<?php

namespace App\Services\Workflow;

use App\Enums\StageAccessLevel;
use App\Enums\StageHistoryVisibility;
use App\Models\User;
use App\Models\WorkflowHistoryEntry;
use App\Support\RoleCodes;

/**
 * Classifies a single `workflow_history` row for a given viewer. A user's own
 * past action on a stage they no longer (or never did) have VIEW access to is
 * preserved in sanitized form — current StagePermission grants control content
 * access, but authorship of one's own action is never fully erased.
 */
class StageHistoryVisibilityResolver
{
    public function __construct(private StagePermissionResolver $permissionResolver) {}

    public function visibilityFor(User $user, WorkflowHistoryEntry $entry): StageHistoryVisibility
    {
        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            return StageHistoryVisibility::FULL;
        }

        $stage = $entry->toStage ?? $entry->fromStage;

        if ($stage !== null && $this->permissionResolver->userCanAccessStage($user, $stage, StageAccessLevel::VIEW)) {
            return StageHistoryVisibility::FULL;
        }

        if ((int) $entry->performed_by === (int) $user->getKey()) {
            return StageHistoryVisibility::SANITIZED;
        }

        return StageHistoryVisibility::HIDDEN;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Workflow/StageHistoryVisibilityResolverTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Format touched PHP files**

Run: `vendor/bin/pint app/Enums/StageHistoryVisibility.php app/Services/Workflow/StageHistoryVisibilityResolver.php tests/Unit/Services/Workflow/StageHistoryVisibilityResolverTest.php --test`
Expected: no formatting violations (or auto-fixable — rerun without `--test` if it reports fixes, then re-verify test still passes)

- [ ] **Step 7: Commit**

```bash
git add backend/app/Enums/StageHistoryVisibility.php backend/app/Services/Workflow/StageHistoryVisibilityResolver.php backend/tests/Unit/Services/Workflow/StageHistoryVisibilityResolverTest.php
git commit -m "feat(workflow): add per-entry history visibility resolver

Classifies workflow_history rows as FULL/SANITIZED/HIDDEN based on the
viewer's current StagePermission access, with an own-action exception."
```

---

### Task 2: Redact `EngineRequestController::history()` using the visibility resolver

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php:406-428` (the `history()` method), and constructor at lines 35-45 to inject the new resolver
- Test: `backend/tests/Feature/Engine/EngineRequestTest.php` (add new test methods after `test_history_returns_ordered_movements` at line 799)

**Interfaces:**
- Consumes: `App\Services\Workflow\StageHistoryVisibilityResolver::visibilityFor()` (Task 1); `App\Enums\StageHistoryVisibility` (Task 1); existing `EngineRequest::history()` HasMany relation (`backend/app/Models/EngineRequest.php:99`).
- Produces: `GET /api/v1/engine-requests/{id}/history` response `data[]` entries now include `restricted: bool` and, for sanitized entries, `restricted_label: string`; `from_stage`/`to_stage`/`action_code`/`comments` are `null` and `performed_by` is the viewer's own name for sanitized entries; hidden entries are absent from the array entirely.

- [ ] **Step 1: Write the failing feature tests**

Add to `backend/tests/Feature/Engine/EngineRequestTest.php`, immediately after the existing `test_history_returns_ordered_movements` method (after line 799), inside the `// ── 18.5.7: History & Graph ──` section:

```php
    public function test_history_hides_entry_for_user_without_stage_access_and_not_actor(): void
    {
        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        // The outsideUser holds the same role/org as executor (entry role, bank org)
        // but belongs to a different bank, so it fails the request-level `inScope`
        // check entirely — use a same-bank user with no stage access instead by
        // reusing `viewer`, who has support/CBY access on REVIEW+VIEW-on-DATA_ENTRY
        // only. To get a genuine "no access at all" user for this stage, assert
        // directly against the resolver-backed response for a user who only has
        // access removed from this specific request's stages: we simulate via a
        // fresh no-role user placed in scope by organization only.
        $noAccessUser = User::create([
            'name' => 'No Stage Access',
            'email' => 'no-stage-access@cby.gov',
            'password' => bcrypt('password'),
            'bank_id' => null,
            'organization_id' => \App\Models\Organization::where('code', 'national_committee')->first()->id,
            'is_active' => true,
        ]);
        // System-wide org scope (national_committee) passes `inScope`, but no
        // StagePermission row exists for this user on any stage of this request.

        $response = $this->actingAs($noAccessUser)->getJson("/api/v1/engine-requests/{$request->id}/history");
        $response->assertOk();

        $history = collect($response->json('data'));
        // Both CREATE (DATA_ENTRY) and SUBMIT (DATA_ENTRY->REVIEW) entries were
        // performed by `executor`, not `$noAccessUser`, and this user has no
        // StagePermission on DATA_ENTRY or REVIEW — both entries must be hidden.
        $this->assertCount(0, $history);
    }

    public function test_history_sanitizes_own_entry_when_actor_lacks_stage_access(): void
    {
        $request = $this->createRequest();

        // executor holds EXECUTE on DATA_ENTRY only (no access on REVIEW/COMPLETED
        // beyond the VIEW row already seeded in setUpWorkflow at line 207-214).
        // Remove that VIEW row so executor has zero access on REVIEW, to exercise
        // "actor's own action on a now-inaccessible stage."
        StagePermission::where('stage_id', $this->reviewStage->id)
            ->where('role_id', \App\Models\Role::where('code', 'intake')->first()->id)
            ->delete();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/history");
        $response->assertOk()->assertJsonCount(2, 'data');

        $history = $response->json('data');
        $submitEntry = collect($history)->firstWhere('action_code', null);

        $this->assertNotNull($submitEntry, 'the SUBMIT entry should be present but sanitized (action_code nulled)');
        $this->assertTrue($submitEntry['restricted']);
        $this->assertNotNull($submitEntry['restricted_label']);
        $this->assertNull($submitEntry['comments']);
        $this->assertNull($submitEntry['from_stage']);
        $this->assertNull($submitEntry['to_stage']);
        $this->assertSame($this->executor->id, $submitEntry['performed_by']['id']);
    }

    public function test_history_shows_full_entry_when_viewer_has_stage_access(): void
    {
        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        // viewer holds EXECUTE on REVIEW (support role, cby org) per setUpWorkflow
        // lines 189-196 — full stage access on the SUBMIT entry's to_stage (REVIEW),
        // so that entry must be FULL regardless of viewer's lack of access to
        // DATA_ENTRY (the CREATE entry's stage, not under test here).
        $response = $this->actingAs($this->viewer)->getJson("/api/v1/engine-requests/{$request->id}/history");
        $response->assertOk();

        $history = collect($response->json('data'));
        $submitEntry = $history->firstWhere('action_code', 'SUBMIT');

        $this->assertNotNull($submitEntry);
        $this->assertFalse($submitEntry['restricted']);
        $this->assertNull($submitEntry['restricted_label']);
        $this->assertNull($submitEntry['comments']);
        $this->assertSame($this->reviewStage->id, $submitEntry['to_stage']['id']);
    }

    public function test_system_admin_sees_full_unredacted_history(): void
    {
        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'sysadmin@cby.gov',
            'password' => bcrypt('password'),
            'organization_id' => \App\Models\Organization::where('code', 'national_committee')->first()->id,
            'is_active' => true,
        ]);
        $admin->roles()->attach(\App\Models\Role::where('code', \App\Support\RoleCodes::SYSTEM_ADMIN)->first());

        $response = $this->actingAs($admin)->getJson("/api/v1/engine-requests/{$request->id}/history");
        $response->assertOk()->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $entry) {
            $this->assertFalse($entry['restricted']);
        }
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=EngineRequestTest::test_history_hides_entry_for_user_without_stage_access_and_not_actor`
Expected: FAIL — response still contains both entries unfiltered (assertion `assertCount(0, $history)` fails), since the controller has no redaction logic yet. The `restricted`/`restricted_label` key assertions in the other new tests will fail with "Undefined array key" until Step 3 lands.

- [ ] **Step 3: Modify the controller**

In `backend/app/Http/Controllers/Api/V1/EngineRequestController.php`:

Add the import (alongside existing imports, e.g. after line 21 `use App\Services\Workflow\StagePermissionResolver;`):

```php
use App\Enums\StageHistoryVisibility;
use App\Services\Workflow\StageHistoryVisibilityResolver;
```

Add the constructor dependency (modify the constructor at lines 35-45):

```php
    public function __construct(
        private EngineRequestService $requestService,
        private EngineTransitionService $transitionService,
        private StagePermissionResolver $permissionResolver,
        private DuplicateInvoiceChecker $duplicateChecker,
        private WorkflowGraphService $graphService,
        private EngineNotificationDispatcher $notificationDispatcher,
        private EngineRequestListQuery $listQuery,
        private StageFieldOutputFilter $outputFilter,
        private UserActionableRequestQuery $actionableQuery,
        private StageHistoryVisibilityResolver $historyVisibilityResolver,
    ) {}
```

Replace the `history()` method body (currently lines 406-428):

```php
    public function history(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $user = $request->user();

        $entries = $engineRequest->history()
            ->with(['fromStage:id,code,name', 'toStage:id,code,name', 'performer:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $data = $entries
            ->map(function ($e) use ($user) {
                $visibility = $this->historyVisibilityResolver->visibilityFor($user, $e);

                return match ($visibility) {
                    StageHistoryVisibility::HIDDEN => null,
                    StageHistoryVisibility::SANITIZED => [
                        'id' => $e->id,
                        'from_stage' => null,
                        'to_stage' => null,
                        'action_code' => null,
                        'performed_by' => $e->performer ? ['id' => $e->performer->id, 'name' => $e->performer->name] : null,
                        'comments' => null,
                        'created_at' => $e->created_at?->toISOString(),
                        'restricted' => true,
                        'restricted_label' => 'إجراء تم في مرحلة مقيدة',
                    ],
                    StageHistoryVisibility::FULL => [
                        'id' => $e->id,
                        'from_stage' => $e->fromStage ? ['id' => $e->fromStage->id, 'code' => $e->fromStage->code, 'name' => $e->fromStage->name] : null,
                        'to_stage' => $e->toStage ? ['id' => $e->toStage->id, 'code' => $e->toStage->code, 'name' => $e->toStage->name] : null,
                        'action_code' => $e->action_code,
                        'performed_by' => $e->performer ? ['id' => $e->performer->id, 'name' => $e->performer->name] : null,
                        'comments' => $e->comments,
                        'created_at' => $e->created_at?->toISOString(),
                        'restricted' => false,
                        'restricted_label' => null,
                    ],
                };
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
```

Note: the method signature changes from `history(EngineRequest $engineRequest)` to `history(Request $request, EngineRequest $engineRequest)` to access `$request->user()` — confirm the route model binding in `backend/routes/api.php:188` still resolves correctly with the added leading `Request $request` parameter (Laravel resolves route-model-bound parameters by name, not position, so this is safe, matching the existing `graph(Request $request, EngineRequest $engineRequest)` signature immediately below it).

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=EngineRequestTest`
Expected: PASS — all existing tests in this file (including `test_history_returns_ordered_movements`, which must still pass since `executor` has EXECUTE on `initialStage` and holds a VIEW row on `reviewStage` per the base fixture, so both CREATE and SUBMIT entries stay FULL for `executor` viewing their own history) plus the 4 new tests from Step 1.

- [ ] **Step 5: Format touched PHP file**

Run: `vendor/bin/pint app/Http/Controllers/Api/V1/EngineRequestController.php tests/Feature/Engine/EngineRequestTest.php --test`

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/Api/V1/EngineRequestController.php backend/tests/Feature/Engine/EngineRequestTest.php
git commit -m "feat(workflow): redact history entries by stage access, keep own actions sanitized

history() now hides workflow_history rows the viewer has no VIEW access
to, except the viewer's own past actions which stay visible in a
sanitized minimal form. SYSTEM_ADMIN sees everything unredacted."
```

---

### Task 3: Filter stage nodes/edges in `EngineRequestController::graph()`

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php:430-481` (the `graph()` method)
- Test: `backend/tests/Feature/Engine/EngineRequestTest.php` (add new test methods after `test_graph_reports_execute_stage_ids_for_current_user`, currently ending at line 838)

**Interfaces:**
- Consumes: `App\Services\Workflow\StagePermissionResolver::accessibleStageIds(User $user, StageAccessLevel $required): array<int>` (existing, `backend/app/Services/Workflow/StagePermissionResolver.php:46`); `App\Support\RoleCodes::SYSTEM_ADMIN`; `$user->hasRoleCode()`.
- Produces: `GET /api/v1/engine-requests/{id}/graph` response `data.nodes`/`data.edges` now contain only stages/transitions the viewer has VIEW (or better) access to; `data.execute_stage_ids` computation is unchanged (still EXECUTE-scoped, still computed after filtering since it must still only reference stages present in the now-filtered node list — no behavior change needed there since `array_intersect` against the filtered `$versionStageIds` naturally narrows it further, which is strictly more correct, not a regression).

- [ ] **Step 1: Write the failing feature tests**

Add to `backend/tests/Feature/Engine/EngineRequestTest.php`, immediately after `test_graph_reports_execute_stage_ids_for_current_user` (after line 838):

```php
    public function test_graph_only_returns_stages_viewer_can_access(): void
    {
        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        // executor holds EXECUTE on DATA_ENTRY and VIEW on REVIEW (see setUpWorkflow
        // lines 180-214), but has no StagePermission row at all on COMPLETED.
        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/graph");
        $response->assertOk();

        $nodeCodes = collect($response->json('data.nodes'))->pluck('code');

        $this->assertTrue($nodeCodes->contains('DATA_ENTRY'));
        $this->assertTrue($nodeCodes->contains('REVIEW'));
        $this->assertFalse($nodeCodes->contains('COMPLETED'), 'executor has no StagePermission on COMPLETED, it must not appear');
    }

    public function test_graph_drops_edges_with_a_filtered_out_endpoint(): void
    {
        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        // approveTransition goes REVIEW -> COMPLETED. executor cannot see COMPLETED,
        // so this edge must be dropped even though its from_stage (REVIEW) is visible.
        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/graph");
        $response->assertOk();

        $edgeActionCodes = collect($response->json('data.edges'))->pluck('action_code');

        $this->assertFalse($edgeActionCodes->contains('APPROVE'), 'edge to the hidden COMPLETED stage must be dropped');
    }

    public function test_graph_shows_all_stages_for_system_admin(): void
    {
        $request = $this->createRequest();

        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'sysadmin-graph@cby.gov',
            'password' => bcrypt('password'),
            'organization_id' => \App\Models\Organization::where('code', 'national_committee')->first()->id,
            'is_active' => true,
        ]);
        $admin->roles()->attach(\App\Models\Role::where('code', \App\Support\RoleCodes::SYSTEM_ADMIN)->first());

        $response = $this->actingAs($admin)->getJson("/api/v1/engine-requests/{$request->id}/graph");
        $response->assertOk()->assertJsonCount(3, 'data.nodes')->assertJsonCount(2, 'data.edges');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=EngineRequestTest::test_graph_only_returns_stages_viewer_can_access`
Expected: FAIL — `assertFalse($nodeCodes->contains('COMPLETED'))` fails because `graph()` currently returns all 3 nodes unconditionally.

- [ ] **Step 3: Modify the controller**

Replace the `graph()` method body (currently lines 430-481) in `backend/app/Http/Controllers/Api/V1/EngineRequestController.php`:

```php
    public function graph(Request $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('view', $engineRequest);

        $engineRequest->load(['workflowVersion', 'history']);
        $graphData = $this->graphService->build($engineRequest->workflowVersion);

        $user = $request->user();
        $versionStageIds = array_column($graphData['nodes'], 'id');

        $viewableStageIds = $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
            ? $versionStageIds
            : array_values(array_intersect(
                $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::VIEW),
                $versionStageIds,
            ));
        $viewableIdSet = array_flip($viewableStageIds);

        $graphData['nodes'] = array_values(array_filter(
            $graphData['nodes'],
            fn ($node) => isset($viewableIdSet[$node['id']]),
        ));
        $graphData['edges'] = array_values(array_filter(
            $graphData['edges'],
            fn ($edge) => isset($viewableIdSet[$edge['from_stage_id']]) && isset($viewableIdSet[$edge['to_stage_id']]),
        ));

        $history = $engineRequest->history;

        $executedStageIds = $history
            ->pluck('to_stage_id')
            ->merge($history->pluck('from_stage_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $currentStageId = $engineRequest->current_stage_id;

        foreach ($graphData['nodes'] as &$node) {
            $node['state'] = match (true) {
                $node['id'] === $currentStageId => 'current',
                in_array($node['id'], $executedStageIds, true) => 'executed',
                default => 'possible',
            };
        }
        unset($node);

        $executedTransitionKeys = $history
            ->whereNotNull('from_stage_id')
            ->map(fn ($h) => "{$h->from_stage_id}-{$h->to_stage_id}")
            ->all();

        foreach ($graphData['edges'] as &$edge) {
            $key = "{$edge['from_stage_id']}-{$edge['to_stage_id']}";
            $edge['state'] = in_array($key, $executedTransitionKeys, true) ? 'executed' : 'possible';
        }
        unset($edge);

        // Stages the current user can execute, scoped to this version, so the UI can
        // mark non-current "دورك" (your turn) stages on the process rail.
        $executeStageIds = array_values(array_intersect(
            $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::EXECUTE),
            array_column($graphData['nodes'], 'id'),
        ));

        return response()->json([
            'success' => true,
            'data' => $graphData + ['execute_stage_ids' => $executeStageIds],
        ]);
    }
```

Key change from the original: node/edge filtering happens immediately after `graphService->build()` and before state annotation (so `current`/`executed`/`possible` are only ever computed on the already-filtered arrays); `execute_stage_ids` now intersects against `array_column($graphData['nodes'], 'id')` (the filtered node list) instead of the pre-filter `$versionStageIds` — strictly narrower and still correct, since a stage absent from `nodes` can never legitimately appear as an execute target in the UI.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=EngineRequestTest`
Expected: PASS — all existing graph tests (`test_graph_marks_executed_current_possible`, `test_graph_reports_execute_stage_ids_for_current_user`) still pass because `executor` has at least VIEW on all three stages it's asserted against in those tests (EXECUTE on DATA_ENTRY, VIEW on REVIEW; COMPLETED is asserted only via `execute_stage_ids` `assertNotContains`, which stays true whether or not COMPLETED is filtered from `nodes` — `executor` has zero access there so it's absent from both `nodes` and `execute_stage_ids` either way), plus the 3 new tests from Step 1.

- [ ] **Step 5: Format touched PHP file**

Run: `vendor/bin/pint app/Http/Controllers/Api/V1/EngineRequestController.php tests/Feature/Engine/EngineRequestTest.php --test`

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/Api/V1/EngineRequestController.php backend/tests/Feature/Engine/EngineRequestTest.php
git commit -m "feat(workflow): filter process-rail graph nodes/edges by stage access

graph() now omits stages the viewer has no VIEW/EXECUTE access to, and
drops any edge whose endpoint stage was filtered out. SYSTEM_ADMIN sees
the full topology unchanged. The Workflow Designer's own graph endpoint
is untouched."
```

---

### Task 4: Frontend type + timeline redaction display

**Files:**
- Modify: `frontend/app/types/models.ts:886-894` (the `EngineHistoryEntry` interface)
- Modify: `frontend/app/composables/useEngineTimeline.ts`
- Modify: `frontend/app/components/workflow/EngineTimeline.vue`
- Test: `frontend/app/tests/unit/composables/useEngineTimeline.test.ts` (create if it does not already exist — verify with the command in Step 0 below)

**Interfaces:**
- Consumes: backend response shape from Task 2 — `EngineHistoryEntry.restricted: boolean`, `EngineHistoryEntry.restricted_label: string | null`.
- Produces: `TimelineItem` (in `useEngineTimeline.ts`) gains `restricted: boolean` and `restrictedLabel: string | null`; `EngineTimeline.vue` renders a distinct visual row for restricted entries.

- [ ] **Step 0: Check for an existing test file**

Run: `find frontend/app/tests/unit/composables -iname "*EngineTimeline*"`
If a file already exists, read it fully before writing Step 1 so the new tests are added to it (matching existing style) rather than creating a duplicate file.

- [ ] **Step 1: Write the failing test**

Create (or append to, per Step 0's finding) `frontend/app/tests/unit/composables/useEngineTimeline.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { buildTimeline } from '@/composables/useEngineTimeline'
import type { EngineHistoryEntry } from '@/types/models'

function makeEntry(overrides: Partial<EngineHistoryEntry> = {}): EngineHistoryEntry {
  return {
    id: 1,
    from_stage: { id: 1, code: 'DATA_ENTRY', name: 'Data Entry' },
    to_stage: { id: 2, code: 'REVIEW', name: 'Review' },
    action_code: 'SUBMIT',
    performed_by: { id: 5, name: 'Test User' },
    comments: null,
    created_at: '2026-07-14T10:00:00Z',
    restricted: false,
    restricted_label: null,
    ...overrides,
  }
}

describe('buildTimeline', () => {
  it('marks a restricted entry with its label and keeps the actor name', () => {
    const entries = [
      makeEntry({
        id: 2,
        from_stage: null,
        to_stage: null,
        action_code: null,
        comments: null,
        restricted: true,
        restricted_label: 'إجراء تم في مرحلة مقيدة',
      }),
    ]

    const items = buildTimeline(entries)

    expect(items[0]?.restricted).toBe(true)
    expect(items[0]?.restrictedLabel).toBe('إجراء تم في مرحلة مقيدة')
    expect(items[0]?.actorName).toBe('Test User')
    expect(items[0]?.comment).toBeNull()
  })

  it('marks a normal entry as not restricted', () => {
    const items = buildTimeline([makeEntry()])

    expect(items[0]?.restricted).toBe(false)
    expect(items[0]?.restrictedLabel).toBeNull()
    expect(items[0]?.fromLabel).toBe('Data Entry')
    expect(items[0]?.toLabel).toBe('Review')
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm exec vitest run app/tests/unit/composables/useEngineTimeline.test.ts`
Expected: FAIL — TypeScript error / test failure since `EngineHistoryEntry` has no `restricted`/`restricted_label` fields yet and `TimelineItem` has no `restricted`/`restrictedLabel` fields yet.

- [ ] **Step 3: Update the type**

In `frontend/app/types/models.ts`, replace lines 886-894:

```ts
export interface EngineHistoryEntry {
  id: number
  from_stage: { id: number; code: string; name: string } | null
  to_stage: { id: number; code: string; name: string } | null
  action_code: string | null
  performed_by: { id: number; name: string } | null
  comments: string | null
  created_at: string | null
  restricted: boolean
  restricted_label: string | null
}
```

- [ ] **Step 4: Update the composable**

Replace `frontend/app/composables/useEngineTimeline.ts` in full:

```ts
import type { EngineHistoryEntry } from '@/types/models'

export interface TimelineItem {
  id: number
  fromLabel: string | null
  toLabel: string | null
  actionCode: string | null
  actorName: string
  timestamp: string
  comment: string | null
  isLast: boolean
  restricted: boolean
  restrictedLabel: string | null
}

const dateFormatter = new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium', timeStyle: 'short' })

function sortKey(entry: EngineHistoryEntry): number {
  return entry.created_at ? Date.parse(entry.created_at) : Number.MAX_SAFE_INTEGER
}

export function buildTimeline(entries: EngineHistoryEntry[]): TimelineItem[] {
  const ordered = [...entries].sort((a, b) => sortKey(a) - sortKey(b) || a.id - b.id)
  return ordered.map((entry, index) => ({
    id: entry.id,
    fromLabel: entry.from_stage?.name ?? null,
    toLabel: entry.to_stage?.name ?? null,
    actionCode: entry.action_code,
    actorName: entry.performed_by?.name ?? 'النظام',
    timestamp: entry.created_at ? dateFormatter.format(new Date(entry.created_at)) : '—',
    comment: entry.comments,
    isLast: index === ordered.length - 1,
    restricted: entry.restricted,
    restrictedLabel: entry.restricted_label,
  }))
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/composables/useEngineTimeline.test.ts`
Expected: PASS (2 tests)

- [ ] **Step 6: Update `EngineTimeline.vue` to render restricted entries distinctly**

Replace `frontend/app/components/workflow/EngineTimeline.vue` in full:

```vue
<!-- app/components/workflow/EngineTimeline.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { EngineHistoryEntry } from '@/types/models'
import { buildTimeline } from '@/composables/useEngineTimeline'
import { Empty, EmptyMedia, EmptyHeader, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import { History, Lock } from 'lucide-vue-next'

const props = defineProps<{ entries: EngineHistoryEntry[] }>()
const items = computed(() => buildTimeline(props.entries))
</script>

<template>
  <ol v-if="items.length" dir="rtl" class="relative flex flex-col gap-4 border-s ps-6">
    <li v-for="item in items" :key="item.id" class="relative">
      <span
        class="absolute -start-[1.6rem] top-1 h-3 w-3 rounded-full ring-4 ring-background"
        :class="item.isLast ? 'bg-primary' : 'bg-muted-foreground/40'"
      />
      <template v-if="item.restricted">
        <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
          <Lock class="h-3.5 w-3.5" aria-hidden="true" />
          <span class="font-medium">{{ item.restrictedLabel }}</span>
        </div>
        <p class="text-muted-foreground text-xs">{{ item.actorName }} · {{ item.timestamp }}</p>
      </template>
      <template v-else>
        <div class="flex flex-wrap items-center gap-2 text-sm">
          <span class="font-medium">{{ item.fromLabel ?? '—' }}</span>
          <span class="text-muted-foreground">←</span>
          <span class="font-medium">{{ item.toLabel ?? '—' }}</span>
          <span v-if="item.actionCode" class="text-muted-foreground text-xs">({{ item.actionCode }})</span>
        </div>
        <p class="text-muted-foreground text-xs">{{ item.actorName }} · {{ item.timestamp }}</p>
        <p v-if="item.comment" class="text-foreground/80 mt-1 text-xs">{{ item.comment }}</p>
      </template>
    </li>
  </ol>

  <Empty v-else>
    <EmptyMedia variant="icon"><History /></EmptyMedia>
    <EmptyHeader>
      <EmptyTitle>لا يوجد سجل بعد</EmptyTitle>
      <EmptyDescription>لم تُنفَّذ أي إجراءات على هذا الطلب حتى الآن.</EmptyDescription>
    </EmptyHeader>
  </Empty>
</template>
```

- [ ] **Step 7: Run any existing component test for EngineTimeline**

Run: `find frontend/app/tests/unit/components -iname "*EngineTimeline*"`

If a test file exists, run it: `pnpm exec vitest run <path found>`
Expected: PASS. If it fails due to shadcn-vue component introspection limits (per project convention, e.g. teleported content), skip/ignore that specific assertion rather than replacing shadcn-vue components with raw HTML — do not weaken the `Lock` icon or `Empty` usage to work around a test.

If no such file exists, this step is a no-op — proceed to Step 8.

- [ ] **Step 8: Typecheck**

Run: `pnpm typecheck`
Expected: no new type errors (this change touches a shared type — `EngineHistoryEntry` — so typecheck is required per the project's verification ladder, not just lint).

- [ ] **Step 9: Lint/format touched files**

Run: `pnpm exec eslint app/types/models.ts app/composables/useEngineTimeline.ts app/components/workflow/EngineTimeline.vue app/tests/unit/composables/useEngineTimeline.test.ts`
Run: `pnpm exec prettier app/types/models.ts app/composables/useEngineTimeline.ts app/components/workflow/EngineTimeline.vue app/tests/unit/composables/useEngineTimeline.test.ts --check`

- [ ] **Step 10: Commit**

```bash
git add frontend/app/types/models.ts frontend/app/composables/useEngineTimeline.ts frontend/app/components/workflow/EngineTimeline.vue frontend/app/tests/unit/composables/useEngineTimeline.test.ts
git commit -m "feat(workflow): render restricted history entries with a lock indicator

EngineHistoryEntry now carries restricted/restricted_label from the
backend redaction rule; the timeline shows a distinct minimal row
instead of blank stage/action fields so users don't mistake redaction
for a missing comment."
```

---

### Task 5: Confirm the process rail degrades gracefully with filtered graph data

**Files:**
- Modify (if needed): `frontend/app/composables/useEngineStagePath.ts:12-58`
- Test: `frontend/app/tests/unit/composables/useEngineStagePath.test.ts` (existing file — read first, then extend)

**Interfaces:**
- Consumes: `WorkflowGraph` (existing type) now potentially containing fewer `nodes`/`edges` than before per Task 3, and `EngineHistoryEntry[]` now potentially containing `restricted: true` entries with `to_stage: null` per Task 2.
- Produces: no interface change — `buildStagePath()` signature stays identical. This task only adds regression coverage confirming the existing implementation already handles the new filtered/redacted inputs safely (it does, per design analysis, since it only reads `entry.to_stage?.id` with optional chaining and iterates `graph.nodes` directly — a shorter array is not a new code path). If the test reveals an actual gap, fix it; otherwise this task is verification-only.

- [ ] **Step 1: Read the existing test file**

Read `frontend/app/tests/unit/composables/useEngineStagePath.test.ts` in full to match its existing fixture style before appending.

- [ ] **Step 2: Write the new test cases**

Append to `frontend/app/tests/unit/composables/useEngineStagePath.test.ts` (inside the existing `describe` block, using whatever graph-fixture helper the file already defines — if the file builds `WorkflowGraph` objects inline per-test rather than via a shared helper, follow that same inline style):

```ts
  it('omits a step for a stage the graph no longer includes (filtered by backend access control)', () => {
    // Simulates the backend (Task 3) filtering out a stage the viewer has no
    // access to — e.g. only DATA_ENTRY and REVIEW nodes come back, COMPLETED is
    // absent entirely rather than present-but-hidden.
    const graph = {
      nodes: [
        { id: 1, code: 'DATA_ENTRY', name: 'Data Entry', display_label: 'Data Entry', sort_order: 1, is_initial: true, is_final: false },
        { id: 2, code: 'REVIEW', name: 'Review', display_label: 'Review', sort_order: 2, is_initial: false, is_final: false },
      ],
      edges: [],
      execute_stage_ids: [1],
    }

    const steps = buildStagePath(graph, 2, [])

    expect(steps).toHaveLength(2)
    expect(steps.map((s) => s.id)).toEqual([1, 2])
  })

  it('does not throw when a history entry references a stage absent from the filtered graph', () => {
    // A sanitized history entry (Task 2) has to_stage: null — buildStagePath must
    // not throw when scanning history for visited-stage ids.
    const graph = {
      nodes: [
        { id: 1, code: 'DATA_ENTRY', name: 'Data Entry', display_label: 'Data Entry', sort_order: 1, is_initial: true, is_final: false },
      ],
      edges: [],
      execute_stage_ids: [],
    }

    const history = [
      {
        id: 1,
        from_stage: null,
        to_stage: null,
        action_code: null,
        performed_by: { id: 1, name: 'Someone' },
        comments: null,
        created_at: '2026-07-14T10:00:00Z',
        restricted: true,
        restricted_label: 'إجراء تم في مرحلة مقيدة',
      },
    ]

    expect(() => buildStagePath(graph, 1, history)).not.toThrow()
  })
```

- [ ] **Step 3: Run the test file**

Run: `pnpm exec vitest run app/tests/unit/composables/useEngineStagePath.test.ts`
Expected: PASS for both new cases without any implementation change — `buildStagePath()` (per the code read during planning, `frontend/app/composables/useEngineStagePath.ts:20-22`) already iterates `graph.nodes` directly (a shorter array produces fewer steps, not an error) and reads `entry.to_stage?.id` with optional chaining at line 26 (a `null` `to_stage` is already skipped safely, not dereferenced). If either assertion fails, only then modify `useEngineStagePath.ts` to fix the specific gap the failure reveals, re-running this step until green.

- [ ] **Step 4: Lint/format**

Run: `pnpm exec eslint app/tests/unit/composables/useEngineStagePath.test.ts`
Run: `pnpm exec prettier app/tests/unit/composables/useEngineStagePath.test.ts --check`

- [ ] **Step 5: Commit**

```bash
git add frontend/app/tests/unit/composables/useEngineStagePath.test.ts
git commit -m "test(workflow): cover process rail against filtered graph and redacted history

Confirms buildStagePath already degrades gracefully when the backend
omits inaccessible stages (Task 3) or returns a sanitized history entry
with a null to_stage (Task 2) — no rail code changes required."
```

---

## Self-Review Notes (completed during planning, not a task)

**Spec coverage check** — every numbered decision in the design doc (`docs/superpowers/specs/2026-07-14-per-request-stage-visibility-design.md`) maps to a task:
- §2.1–2.3 (strict filter, backend-omit, dangling-edge-drop) → Task 3
- §2.4 (scope: request-facing only, not the Designer's own graph) → confirmed via Global Constraints; no task touches `WorkflowVersionController`/`WorkflowGraphService`/`WorkflowProcessGraph.vue`
- §2.5 (version-pinning reused as-is) → Task 3 reuses `accessibleStageIds()` + intersection exactly as `execute_stage_ids` already does; no new version-safety code needed, confirmed safe during brainstorming's codebase audit
- §2.6 (history redaction rule, FULL/SANITIZED/HIDDEN, SYSTEM_ADMIN bypass) → Tasks 1–2
- §2.7 (audit_logs and export CSV out of scope) → Global Constraints, no task touches either
- §4.4 (graph()'s internal history load needs no redaction) → confirmed unchanged in Task 3, no behavior added there
- §5.1–5.3 (frontend type, timeline, rail) → Tasks 4–5
- §7 open items: (1) `action_code` nulled — resolved in Task 2's implementation (nulled, not a separate `outcome` field, since the user's approval of the spec did not flag this item for change); (2) `restricted_label` backend-supplied — resolved in Task 2 (Arabic string literal in the controller); (3) `WorkflowHistoryArchive` unreachable — no task needed, confirmed out of scope, left as a documented future note only.

**Placeholder scan** — an earlier draft of Task 2 Step 1 contained a broken placeholder assertion in `test_history_shows_full_entry_when_viewer_has_stage_access`; it has been rewritten in place with the correct assertions (`assertNull($submitEntry['comments'])`, `assertSame($this->reviewStage->id, $submitEntry['to_stage']['id'])`) and the stray implementer note removed. No other TBD/TODO/"handle appropriately" language present in the plan.

**Type consistency check** — `StageHistoryVisibility` (Task 1) is consumed by name in Task 2's `match` expression; `StageHistoryVisibilityResolver::visibilityFor()` signature matches between Task 1's definition and Task 2's constructor injection + call site; `EngineHistoryEntry.restricted`/`restricted_label` (Task 4, frontend) match the exact JSON keys emitted by Task 2's PHP array (`'restricted' => ...`, `'restricted_label' => ...`); `TimelineItem.restricted`/`restrictedLabel` (Task 4) are read correctly in `EngineTimeline.vue`'s template in the same task. No naming drift found.
