# Engine Stage-Claim Feature Implementation Plan (G11 / P0.5)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the legacy 15-minute support-claim soft-lock onto the dynamic workflow engine so a `requires_claim` stage can only be acted on by the user who holds an unexpired claim, with heartbeat extension and automatic TTL expiry.

**Architecture:** Add claim columns to `engine_requests` and a `requires_claim` flag to `workflow_stages`. Claim state is dual-stored (DB columns + Cache key `engine_claim:{id}`) exactly like the legacy path. Three new engine endpoints (claim / heartbeat / release) plus an execution guard inside `EngineTransitionService`. A rewritten scheduler command releases expired claims. This mirrors the legacy `WorkflowController` claim methods + `ExpireClaimsCommand`, rebound from fixed statuses to a stage flag.

**Tech Stack:** Laravel 11, PHP 8.2, MySQL, Redis (Cache), PHPUnit.

## Global Constraints

- Commit format: `type(scope): description`; scope must be `workflow`. End every commit message with `Co-Authored-By: Claude <noreply@anthropic.com>`. Commits stay signed — never `--no-gpg-sign`.
- Backend changes are committed once in the root monorepo, staging paths as `backend/<files>`.
- All business logic in services, not controllers/models/routes. Controllers stay thin.
- Org-scoped visibility enforced at the Eloquent query level.
- Every claim mutation logs to `audit_logs` via `AuditService`.
- TTL config already exists: `config('workflow.support_claim_ttl_minutes')` (default 15). Reuse it; do not add a new key.
- Cache key namespace for engine claims: `engine_claim:{engine_request_id}` (distinct from legacy `support_claim:{id}`).
- Error responses use `EngineException` (shape: `{success:false, message, error_code}`); claim conflicts return `409 STAGE_CLAIMED`, non-holder actions `403 CLAIM_NOT_HELD`.
- Run focused tests only: `php artisan test --filter=<Name>`. Format touched files: `vendor/bin/pint <file> --test`.

---

### Task 1: Database schema — claim columns + stage flag

**Files:**
- Create: `backend/database/migrations/2026_06_30_000001_add_claim_columns_to_engine_requests_table.php`
- Create: `backend/database/migrations/2026_06_30_000002_add_requires_claim_to_workflow_stages_table.php`
- Modify: `backend/app/Models/EngineRequest.php` (fillable + casts + relation + helpers)
- Modify: `backend/app/Models/WorkflowStage.php` (fillable + casts)

**Interfaces:**
- Produces: `engine_requests.claimed_by` (FK users nullable), `engine_requests.claimed_at` (timestamp nullable), `engine_requests.claim_expires_at` (timestamp nullable); `workflow_stages.requires_claim` (bool default false).
- Produces on `EngineRequest`: `claimedBy(): BelongsTo`, `isClaimed(): bool`, `claimIsExpired(): bool`.

- [ ] **Step 1: Write the migration for engine_requests claim columns**

`backend/database/migrations/2026_06_30_000001_add_claim_columns_to_engine_requests_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->foreignId('claimed_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('claimed_by');
            $table->timestamp('claim_expires_at')->nullable()->after('claimed_at');
            $table->index('claimed_by');
            $table->index('claim_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claimed_by');
            $table->dropColumn(['claimed_at', 'claim_expires_at']);
        });
    }
};
```

- [ ] **Step 2: Write the migration for workflow_stages.requires_claim**

`backend/database/migrations/2026_06_30_000002_add_requires_claim_to_workflow_stages_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->boolean('requires_claim')->default(false)->after('sla_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropColumn('requires_claim');
        });
    }
};
```

- [ ] **Step 3: Run the migrations**

Run: `cd backend && php artisan migrate`
Expected: both migrations run "DONE".

- [ ] **Step 4: Add columns to EngineRequest model**

In `backend/app/Models/EngineRequest.php`, add to `$fillable` (after `'created_by',`):

```php
        'claimed_by',
        'claimed_at',
        'claim_expires_at',
```

Add to the `casts()` array return (after `'version' => 'integer',`):

```php
            'claimed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
```

Add these methods after the existing `creator()` relation:

```php
    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function isClaimed(): bool
    {
        return $this->claimed_by !== null
            && $this->claim_expires_at !== null
            && $this->claim_expires_at->isFuture();
    }

    public function claimIsExpired(): bool
    {
        return $this->claim_expires_at !== null && $this->claim_expires_at->isPast();
    }
```

- [ ] **Step 5: Add requires_claim to WorkflowStage model**

In `backend/app/Models/WorkflowStage.php`, add to `$fillable` (after `'sla_duration_minutes',`):

```php
        'requires_claim',
```

Add to the `casts()` return (after `'sla_duration_minutes' => 'integer',`):

```php
            'requires_claim' => 'boolean',
```

- [ ] **Step 6: Verify migration + model with a smoke test**

Create `backend/tests/Feature/Engine/EngineClaimSchemaTest.php`:

```php
<?php

namespace Tests\Feature\Engine;

use App\Models\EngineRequest;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineClaimSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_engine_request_has_claim_columns_and_helpers(): void
    {
        $this->assertTrue(\Schema::hasColumn('engine_requests', 'claimed_by'));
        $this->assertTrue(\Schema::hasColumn('engine_requests', 'claimed_at'));
        $this->assertTrue(\Schema::hasColumn('engine_requests', 'claim_expires_at'));
        $this->assertTrue(\Schema::hasColumn('workflow_stages', 'requires_claim'));

        $request = new EngineRequest(['claim_expires_at' => now()->addMinutes(5), 'claimed_by' => 1]);
        $this->assertTrue($request->isClaimed());
        $this->assertFalse($request->claimIsExpired());
    }
}
```

Run: `cd backend && php artisan test --filter=EngineClaimSchemaTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
cd backend
git add database/migrations/2026_06_30_000001_add_claim_columns_to_engine_requests_table.php database/migrations/2026_06_30_000002_add_requires_claim_to_workflow_stages_table.php app/Models/EngineRequest.php app/Models/WorkflowStage.php tests/Feature/Engine/EngineClaimSchemaTest.php
git commit -m "feat(workflow): add engine stage-claim schema and stage flag

Co-Authored-By: Claude <noreply@anthropic.com>"
cd ..
git add backend/database/migrations/2026_06_30_000001_add_claim_columns_to_engine_requests_table.php backend/database/migrations/2026_06_30_000002_add_requires_claim_to_workflow_stages_table.php backend/app/Models/EngineRequest.php backend/app/Models/WorkflowStage.php backend/tests/Feature/Engine/EngineClaimSchemaTest.php
git commit -m "feat(workflow): add engine stage-claim schema and stage flag

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 2: EngineClaimService — claim / heartbeat / release domain logic

**Files:**
- Create: `backend/app/Services/Workflow/EngineClaimService.php`
- Modify: `backend/app/Exceptions/EngineException.php` (two new factories)
- Test: `backend/tests/Feature/Engine/EngineClaimServiceTest.php`

**Interfaces:**
- Consumes: `EngineRequest` (Task 1 columns), `config('workflow.support_claim_ttl_minutes')`, `AuditService::log(...)`.
- Produces: `EngineClaimService::claim(EngineRequest $r, User $u): EngineRequest`, `::heartbeat(EngineRequest $r, User $u): EngineRequest`, `::release(EngineRequest $r, User $u): EngineRequest`. Throws `EngineException::stageClaimed()` (409) and `EngineException::claimNotHeld()` (403).

- [ ] **Step 1: Add EngineException factories**

In `backend/app/Exceptions/EngineException.php`, add after `noInitialStage()`:

```php
    public static function stageClaimed(): self
    {
        return new self('This request is already being reviewed by another user.', 'STAGE_CLAIMED', 409);
    }

    public static function claimNotHeld(): self
    {
        return new self('You do not hold the claim on this request.', 'CLAIM_NOT_HELD', 403);
    }
```

- [ ] **Step 2: Write the failing service test**

`backend/tests/Feature/Engine/EngineClaimServiceTest.php`:

```php
<?php

namespace Tests\Feature\Engine;

use App\Exceptions\EngineException;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Workflow\EngineClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Support\EngineWorkflowFactory;

class EngineClaimServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(): EngineRequest
    {
        return EngineWorkflowFactory::seedRequestOnClaimStage();
    }

    public function test_claim_sets_holder_and_expiry(): void
    {
        $svc = app(EngineClaimService::class);
        $user = User::factory()->create();
        $request = $this->makeRequest();

        $claimed = $svc->claim($request, $user);

        $this->assertSame($user->id, $claimed->claimed_by);
        $this->assertNotNull($claimed->claimed_at);
        $this->assertTrue($claimed->claim_expires_at->isFuture());
    }

    public function test_claim_by_second_user_throws_stage_claimed(): void
    {
        $svc = app(EngineClaimService::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $request = $this->makeRequest();

        $svc->claim($request, $a);
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('already being reviewed');
        $svc->claim($request->fresh(), $b);
    }

    public function test_reclaim_by_same_user_is_idempotent(): void
    {
        $svc = app(EngineClaimService::class);
        $a = User::factory()->create();
        $request = $this->makeRequest();

        $svc->claim($request, $a);
        $again = $svc->claim($request->fresh(), $a);
        $this->assertSame($a->id, $again->claimed_by);
    }

    public function test_heartbeat_extends_expiry_for_holder(): void
    {
        $svc = app(EngineClaimService::class);
        $a = User::factory()->create();
        $request = $this->makeRequest();
        $svc->claim($request, $a);

        $before = $request->fresh()->claim_expires_at;
        $this->travel(2)->minutes();
        $after = $svc->heartbeat($request->fresh(), $a)->claim_expires_at;

        $this->assertTrue($after->greaterThan($before));
    }

    public function test_heartbeat_by_non_holder_throws_claim_not_held(): void
    {
        $svc = app(EngineClaimService::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $request = $this->makeRequest();
        $svc->claim($request, $a);

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('do not hold the claim');
        $svc->heartbeat($request->fresh(), $b);
    }

    public function test_release_clears_holder(): void
    {
        $svc = app(EngineClaimService::class);
        $a = User::factory()->create();
        $request = $this->makeRequest();
        $svc->claim($request, $a);

        $released = $svc->release($request->fresh(), $a);
        $this->assertNull($released->claimed_by);
        $this->assertNull($released->claim_expires_at);
    }
}
```

> NOTE: `Tests\Support\EngineWorkflowFactory::seedRequestOnClaimStage()` is a test helper created in Step 3 — it seeds a published workflow whose current stage has `requires_claim = true` and returns an `EngineRequest` parked on it. If the project already has an engine test seeding helper, reuse it and add this method instead of creating a new class.

- [ ] **Step 3: Create the test seeding helper**

`backend/tests/Support/EngineWorkflowFactory.php` — add a static method that creates the minimal published workflow + a `requires_claim` stage + an active `EngineRequest` on it. Reuse existing engine seeders/factories where present; the method must return an `EngineRequest` whose `current_stage_id` points at a stage with `requires_claim = true`, `status = 'ACTIVE'`. (Mirror the structure used by existing engine feature tests in `backend/tests/Feature/Engine/`.)

- [ ] **Step 4: Run the test to verify it fails**

Run: `cd backend && php artisan test --filter=EngineClaimServiceTest`
Expected: FAIL — "Class EngineClaimService not found".

- [ ] **Step 5: Implement EngineClaimService**

`backend/app/Services/Workflow/EngineClaimService.php`:

```php
<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Exceptions\EngineException;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EngineClaimService
{
    public function __construct(private AuditService $auditService) {}

    private function ttlMinutes(): int
    {
        return (int) config('workflow.support_claim_ttl_minutes', 15);
    }

    private function cacheKey(EngineRequest $request): string
    {
        return "engine_claim:{$request->id}";
    }

    public function claim(EngineRequest $request, User $user): EngineRequest
    {
        return DB::transaction(function () use ($request, $user) {
            $locked = EngineRequest::lockForUpdate()->findOrFail($request->id);

            if ($locked->isClaimed() && $locked->claimed_by !== $user->id) {
                throw EngineException::stageClaimed();
            }

            $expiresAt = now()->addMinutes($this->ttlMinutes());
            $isNew = ! $locked->isClaimed();
            $locked->forceFill([
                'claimed_by' => $user->id,
                'claimed_at' => $locked->claimed_at ?? now(),
                'claim_expires_at' => $expiresAt,
            ])->save();

            Cache::put($this->cacheKey($locked), $user->id, $expiresAt);

            if ($isNew) {
                $this->auditService->log(AuditAction::CLAIM_ACQUIRED, $user, $locked, [
                    'entity_type' => 'engine_request',
                ]);
            }

            return $locked;
        });
    }

    public function heartbeat(EngineRequest $request, User $user): EngineRequest
    {
        $fresh = EngineRequest::findOrFail($request->id);
        if ($fresh->claimed_by !== $user->id) {
            throw EngineException::claimNotHeld();
        }
        $expiresAt = now()->addMinutes($this->ttlMinutes());
        $fresh->forceFill(['claim_expires_at' => $expiresAt])->save();
        Cache::put($this->cacheKey($fresh), $user->id, $expiresAt);

        return $fresh;
    }

    public function release(EngineRequest $request, User $user): EngineRequest
    {
        return DB::transaction(function () use ($request, $user) {
            $locked = EngineRequest::lockForUpdate()->findOrFail($request->id);
            $isAdmin = $user->role === \App\Enums\UserRole::CBY_ADMIN;
            if (! $isAdmin && $locked->claimed_by !== $user->id) {
                throw EngineException::claimNotHeld();
            }
            $locked->forceFill([
                'claimed_by' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
            ])->save();
            Cache::forget($this->cacheKey($locked));
            $this->auditService->log(AuditAction::CLAIM_RELEASED, $user, $locked, [
                'entity_type' => 'engine_request',
            ]);

            return $locked;
        });
    }
}
```

> Verify `AuditAction::CLAIM_ACQUIRED` and `AuditAction::CLAIM_RELEASED` exist in `backend/app/Enums/AuditAction.php`. `CLAIM_RELEASED` already exists (Story 8.4). If `CLAIM_ACQUIRED` is missing, add it to the enum in this step. Verify `AuditService::log()`'s exact signature and adapt the call to match (the example uses `(AuditAction, User, model, array)` — match the real signature).

- [ ] **Step 6: Run the test to verify it passes**

Run: `cd backend && php artisan test --filter=EngineClaimServiceTest`
Expected: PASS (all 6 tests).

- [ ] **Step 7: Format + commit**

```bash
cd backend
vendor/bin/pint app/Services/Workflow/EngineClaimService.php app/Exceptions/EngineException.php --test
git add app/Services/Workflow/EngineClaimService.php app/Exceptions/EngineException.php tests/Feature/Engine/EngineClaimServiceTest.php tests/Support/EngineWorkflowFactory.php
git commit -m "feat(workflow): add EngineClaimService claim/heartbeat/release

Co-Authored-By: Claude <noreply@anthropic.com>"
cd ..
git add backend/app/Services/Workflow/EngineClaimService.php backend/app/Exceptions/EngineException.php backend/tests/Feature/Engine/EngineClaimServiceTest.php backend/tests/Support/EngineWorkflowFactory.php
git commit -m "feat(workflow): add EngineClaimService claim/heartbeat/release

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 3: Execution guard — block action on a claimed stage without the claim

**Files:**
- Modify: `backend/app/Services/Workflow/EngineTransitionService.php:55-60` (after EXECUTE check, before comment check)
- Test: `backend/tests/Feature/Engine/EngineClaimGuardTest.php`

**Interfaces:**
- Consumes: `EngineRequest` claim helpers (Task 1), `WorkflowStage::$requires_claim` (Task 1).
- Produces: `applyAction`/`execute` throws `EngineException::claimNotHeld()` when `fromStage->requires_claim` and the actor is not the unexpired claim holder.

- [ ] **Step 1: Write the failing guard test**

`backend/tests/Feature/Engine/EngineClaimGuardTest.php`:

```php
<?php

namespace Tests\Feature\Engine;

use App\Exceptions\EngineException;
use App\Models\User;
use App\Services\Workflow\EngineClaimService;
use App\Services\Workflow\EngineTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Support\EngineWorkflowFactory;

class EngineClaimGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_on_claim_stage_blocked_without_claim(): void
    {
        ['request' => $request, 'transitionId' => $tid, 'executor' => $user]
            = EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('do not hold the claim');
        app(EngineTransitionService::class)->execute($request, $tid, null, [], $request->version, $user);
    }

    public function test_action_on_claim_stage_allowed_for_holder(): void
    {
        ['request' => $request, 'transitionId' => $tid, 'executor' => $user]
            = EngineWorkflowFactory::seedClaimStageWithTransition();

        app(EngineClaimService::class)->claim($request, $user);
        $result = app(EngineTransitionService::class)->execute($request->fresh(), $tid, null, [], $request->fresh()->version, $user);

        $this->assertNotNull($result);
    }
}
```

> `EngineWorkflowFactory::seedClaimStageWithTransition()` extends the Task-2 helper to also create an outgoing transition from the `requires_claim` stage and return `['request', 'transitionId', 'executor']` where `executor` has EXECUTE on that stage. Add it alongside `seedRequestOnClaimStage()`.

- [ ] **Step 2: Run to verify it fails**

Run: `cd backend && php artisan test --filter=EngineClaimGuardTest`
Expected: FAIL — the "blocked without claim" test fails because no guard exists (action proceeds).

- [ ] **Step 3: Add the guard in EngineTransitionService::execute**

In `backend/app/Services/Workflow/EngineTransitionService.php`, immediately AFTER the EXECUTE permission check block (the `if (! $this->permissionResolver->userCanAccessStage(...))` that throws `stageExecutionForbidden`), add:

```php
            if ($transition->fromStage->requires_claim
                && ! ($request->claimed_by === $user->id && $request->isClaimed())) {
                throw EngineException::claimNotHeld();
            }
```

- [ ] **Step 4: Run to verify it passes**

Run: `cd backend && php artisan test --filter=EngineClaimGuardTest`
Expected: PASS (both tests).

- [ ] **Step 5: Format + commit**

```bash
cd backend
vendor/bin/pint app/Services/Workflow/EngineTransitionService.php --test
git add app/Services/Workflow/EngineTransitionService.php tests/Feature/Engine/EngineClaimGuardTest.php tests/Support/EngineWorkflowFactory.php
git commit -m "feat(workflow): block engine action on claim stage without held claim

Co-Authored-By: Claude <noreply@anthropic.com>"
cd ..
git add backend/app/Services/Workflow/EngineTransitionService.php backend/tests/Feature/Engine/EngineClaimGuardTest.php backend/tests/Support/EngineWorkflowFactory.php
git commit -m "feat(workflow): block engine action on claim stage without held claim

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 4: HTTP endpoints — claim / heartbeat / release

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php` (3 methods + constructor dep + use)
- Modify: `backend/routes/api.php` (3 routes in the engine block, ~line 181)
- Test: `backend/tests/Feature/Engine/EngineClaimEndpointTest.php`

**Interfaces:**
- Consumes: `EngineClaimService` (Task 2), `EngineRequestResource`.
- Produces: `POST engine-requests/{engineRequest}/claim`, `POST engine-requests/{engineRequest}/claim/heartbeat`, `DELETE engine-requests/{engineRequest}/claim`. Each returns the updated request as `EngineRequestResource` (200); conflicts/forbidden via `EngineException` (409/403).

- [ ] **Step 1: Write the failing endpoint test**

`backend/tests/Feature/Engine/EngineClaimEndpointTest.php`:

```php
<?php

namespace Tests\Feature\Engine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Support\EngineWorkflowFactory;

class EngineClaimEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_endpoint_returns_200_and_sets_holder(): void
    {
        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->actingAs($user)
            ->postJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.claimed_by', $user->id);
    }

    public function test_second_user_claim_returns_409(): void
    {
        ['request' => $request, 'executor' => $a] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $b = EngineWorkflowFactory::executorPeer($a, $request);

        $this->actingAs($a)->postJson("/api/v1/engine-requests/{$request->id}/claim")->assertOk();
        $this->actingAs($b)->postJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'STAGE_CLAIMED');
    }

    public function test_heartbeat_by_non_holder_returns_403(): void
    {
        ['request' => $request, 'executor' => $a] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $b = EngineWorkflowFactory::executorPeer($a, $request);
        $this->actingAs($a)->postJson("/api/v1/engine-requests/{$request->id}/claim")->assertOk();

        $this->actingAs($b)->postJson("/api/v1/engine-requests/{$request->id}/claim/heartbeat")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'CLAIM_NOT_HELD');
    }

    public function test_release_clears_holder(): void
    {
        ['request' => $request, 'executor' => $a] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $this->actingAs($a)->postJson("/api/v1/engine-requests/{$request->id}/claim")->assertOk();

        $this->actingAs($a)->deleteJson("/api/v1/engine-requests/{$request->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.claimed_by', null);
    }
}
```

> `EngineWorkflowFactory::executorPeer($a, $request)` returns a second user who also has EXECUTE on the same stage (same org/team/role audience). Add it to the helper.

- [ ] **Step 2: Run to verify it fails**

Run: `cd backend && php artisan test --filter=EngineClaimEndpointTest`
Expected: FAIL — 404 (routes don't exist).

- [ ] **Step 3: Add controller methods + dependency**

In `EngineRequestController.php`, add to the `use` block:

```php
use App\Services\Workflow\EngineClaimService;
```

Add `EngineClaimService $claimService` to the constructor's promoted properties (match the existing constructor style). Then add three methods:

```php
    public function claim(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);
        $updated = $this->claimService->claim($engineRequest, request()->user());

        return response()->json(['success' => true, 'data' => new EngineRequestResource($updated)]);
    }

    public function heartbeatClaim(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);
        $updated = $this->claimService->heartbeat($engineRequest, request()->user());

        return response()->json(['success' => true, 'data' => new EngineRequestResource($updated)]);
    }

    public function releaseClaim(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);
        $updated = $this->claimService->release($engineRequest, request()->user());

        return response()->json(['success' => true, 'data' => new EngineRequestResource($updated)]);
    }
```

> **Authorization (mandatory):** each method MUST call `$this->authorize('execute', $engineRequest)` first — matching every other mutating method in this controller (`executeAction`/`draft`/`uploadDocument`). Omitting it lets any authenticated user claim an out-of-org-scope request (AGENTS.md org-scope violation). Add a test that a user WITHOUT EXECUTE on the stage gets 403.
> **Response envelope:** this app does NOT auto-wrap `JsonResource::response()` in `{data:...}`. Match the controller's existing convention `response()->json(['success' => true, 'data' => new EngineRequestResource($x)])` (as `show`/`store`/`draft` do).
> Confirm `EngineRequestResource` exposes `claimed_by` in its `toArray`. If not, add `'claimed_by' => $this->claimed_by,` (and optionally `claimed_at`, `claim_expires_at`) to `backend/app/Http/Resources/EngineRequestResource.php` so the assertions on `data.claimed_by` pass.

- [ ] **Step 4: Add routes**

In `backend/routes/api.php`, in the Engine Requests block (after the documents routes, ~line 181), add:

```php
    Route::post('engine-requests/{engineRequest}/claim', [EngineRequestController::class, 'claim']);
    Route::post('engine-requests/{engineRequest}/claim/heartbeat', [EngineRequestController::class, 'heartbeatClaim']);
    Route::delete('engine-requests/{engineRequest}/claim', [EngineRequestController::class, 'releaseClaim']);
```

- [ ] **Step 5: Run to verify it passes**

Run: `cd backend && php artisan test --filter=EngineClaimEndpointTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Format + commit**

```bash
cd backend
vendor/bin/pint app/Http/Controllers/Api/V1/EngineRequestController.php app/Http/Resources/EngineRequestResource.php --test
git add app/Http/Controllers/Api/V1/EngineRequestController.php app/Http/Resources/EngineRequestResource.php routes/api.php tests/Feature/Engine/EngineClaimEndpointTest.php tests/Support/EngineWorkflowFactory.php
git commit -m "feat(workflow): add engine claim/heartbeat/release endpoints

Co-Authored-By: Claude <noreply@anthropic.com>"
cd ..
git add backend/app/Http/Controllers/Api/V1/EngineRequestController.php backend/app/Http/Resources/EngineRequestResource.php backend/routes/api.php backend/tests/Feature/Engine/EngineClaimEndpointTest.php backend/tests/Support/EngineWorkflowFactory.php
git commit -m "feat(workflow): add engine claim/heartbeat/release endpoints

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 5: Scheduled TTL expiry — ExpireEngineClaimsCommand

**Files:**
- Create: `backend/app/Console/Commands/ExpireEngineClaimsCommand.php`
- Modify: `backend/routes/console.php` (register schedule, every minute)
- Test: `backend/tests/Feature/Engine/ExpireEngineClaimsCommandTest.php`

**Interfaces:**
- Consumes: `EngineClaimService::release(...)` (Task 2), `EngineNotificationDispatcher` for the release signal.
- Produces: artisan command `workflow:expire-engine-claims`; scheduled every minute.

- [ ] **Step 1: Write the failing command test**

`backend/tests/Feature/Engine/ExpireEngineClaimsCommandTest.php`:

```php
<?php

namespace Tests\Feature\Engine;

use App\Services\Workflow\EngineClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Support\EngineWorkflowFactory;

class ExpireEngineClaimsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_claim_is_released_by_command(): void
    {
        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();
        app(EngineClaimService::class)->claim($request, $user);

        // force expiry in the past
        $request->fresh()->forceFill(['claim_expires_at' => now()->subMinute()])->save();

        $this->artisan('workflow:expire-engine-claims')->assertSuccessful();

        $this->assertNull($request->fresh()->claimed_by);
    }

    public function test_unexpired_claim_is_not_released(): void
    {
        ['request' => $request, 'executor' => $user] = EngineWorkflowFactory::seedClaimStageWithTransition();
        app(EngineClaimService::class)->claim($request, $user);

        $this->artisan('workflow:expire-engine-claims')->assertSuccessful();

        $this->assertSame($user->id, $request->fresh()->claimed_by);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd backend && php artisan test --filter=ExpireEngineClaimsCommandTest`
Expected: FAIL — command not found.

- [ ] **Step 3: Implement the command**

`backend/app/Console/Commands/ExpireEngineClaimsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Services\Workflow\EngineClaimService;
use Illuminate\Console\Command;

class ExpireEngineClaimsCommand extends Command
{
    protected $signature = 'workflow:expire-engine-claims';

    protected $description = 'Release engine request claims whose TTL has expired';

    public function handle(EngineClaimService $claimService, EngineNotificationDispatcher $dispatcher): void
    {
        $systemActor = User::query()->where('role', UserRole::CBY_ADMIN)->first();
        if (! $systemActor) {
            $this->error('No CBY_ADMIN user found — cannot expire engine claims.');

            return;
        }

        $expiredIds = EngineRequest::query()
            ->whereNotNull('claim_expires_at')
            ->where('claim_expires_at', '<', now())
            ->pluck('id');

        $count = 0;
        foreach ($expiredIds as $id) {
            try {
                $request = EngineRequest::find($id);
                if ($request === null || ! $request->claimIsExpired()) {
                    continue;
                }
                $released = $claimService->release($request, $systemActor);
                $dispatcher->custom(
                    'claim.released',
                    'info',
                    'انتهت مهلة المطالبة',
                    "تم تحرير المطالبة على الطلب {$released->reference} لانتهاء المهلة.",
                    'engine_request',
                    (string) $released->id,
                    null,
                    [],
                );
                $count++;
            } catch (\Throwable $e) {
                $this->error("Failed to expire engine claim for request {$id}: {$e->getMessage()}");
            }
        }

        $this->info("Released {$count} expired engine claim(s).");
    }
}
```

> Confirm `EngineNotificationDispatcher::custom(...)`'s exact signature (from `06 notification` doc it is `custom(string $type, string $severity, string $title, string $body, string $entityType, ?string $entityId, ?string $actionUrl, array $recipientUserIds)`). Adapt the call to the real signature; if recipient resolution differs, pass the appropriate audience.

- [ ] **Step 4: Register the schedule**

In `backend/routes/console.php`, add the import near the existing command imports:

```php
use App\Console\Commands\ExpireEngineClaimsCommand;
```

And add at the bottom near the other `Schedule::command` lines:

```php
Schedule::command(ExpireEngineClaimsCommand::class)->everyMinute();
```

- [ ] **Step 5: Run to verify it passes**

Run: `cd backend && php artisan test --filter=ExpireEngineClaimsCommandTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Format + commit**

```bash
cd backend
vendor/bin/pint app/Console/Commands/ExpireEngineClaimsCommand.php --test
git add app/Console/Commands/ExpireEngineClaimsCommand.php routes/console.php tests/Feature/Engine/ExpireEngineClaimsCommandTest.php
git commit -m "feat(workflow): add scheduled engine claim TTL expiry

Co-Authored-By: Claude <noreply@anthropic.com>"
cd ..
git add backend/app/Console/Commands/ExpireEngineClaimsCommand.php backend/routes/console.php backend/tests/Feature/Engine/ExpireEngineClaimsCommandTest.php
git commit -m "feat(workflow): add scheduled engine claim TTL expiry

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 6: Frontend — claim composable + soft-lock banner on the instance page

**Files:**
- Create: `frontend/app/composables/useEngineClaim.ts`
- Modify: `frontend/app/pages/workflows/instances/[id].vue` (mount claim controls + banner)
- Create: `frontend/app/components/workflow/ClaimBanner.vue`
- Test: `frontend/app/tests/unit/composables/useEngineClaim.test.ts`

**Interfaces:**
- Consumes: engine endpoints from Task 4 (`/engine-requests/{id}/claim`, `.../claim/heartbeat`, `.../claim`).
- Produces: `useEngineClaim(requestId)` → `{ claim, release, heartbeatActive, isHeldByMe, heldByOther }`; `ClaimBanner.vue` showing "X يراجع هذا الطلب الآن" when held by another.

- [ ] **Step 1: Write the failing composable test**

`frontend/app/tests/unit/composables/useEngineClaim.test.ts`:

```ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { useEngineClaim } from '@/composables/useEngineClaim'

const post = vi.fn()
const del = vi.fn()
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ post, delete: del }),
}))

describe('useEngineClaim', () => {
  beforeEach(() => {
    post.mockReset()
    del.mockReset()
  })

  it('claim() posts to the claim endpoint and sets holder', async () => {
    post.mockResolvedValue({ data: { id: 1, claimed_by: 7 } })
    const { claim, isHeldByMe } = useEngineClaim(ref(1), ref(7))
    await claim()
    expect(post).toHaveBeenCalledWith('/engine-requests/1/claim')
    expect(isHeldByMe.value).toBe(true)
  })

  it('release() deletes the claim', async () => {
    del.mockResolvedValue({ data: { id: 1, claimed_by: null } })
    const { release } = useEngineClaim(ref(1), ref(7))
    await release()
    expect(del).toHaveBeenCalledWith('/engine-requests/1/claim')
  })
})
```

> Adapt the mock to the project's real API composable (the engine pages already use one — check `useEngineRequests.ts`/`useEngineRequestActions.ts` for the exact `useApi`/`$fetch` pattern and mirror it; do not introduce a new HTTP client).

- [ ] **Step 2: Run to verify it fails**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/composables/useEngineClaim.test.ts`
Expected: FAIL — module not found.

- [ ] **Step 3: Implement useEngineClaim**

`frontend/app/composables/useEngineClaim.ts` — implement `claim`, `release`, `heartbeat` calling the Task-4 endpoints; expose `isHeldByMe` (claimed_by === currentUserId), `heldByOther` (claimed_by set and not me), and a `heartbeatActive` interval (60s, matching the project's 60s heartbeat convention) that calls heartbeat while the claim is held by me and stops on release/unmount. Follow the exact API-call and lifecycle patterns in `useEngineRequestActions.ts`.

- [ ] **Step 4: Run to verify it passes**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/composables/useEngineClaim.test.ts`
Expected: PASS.

- [ ] **Step 5: Build ClaimBanner.vue and wire into the instance page**

`frontend/app/components/workflow/ClaimBanner.vue` — a shadcn-vue Alert/banner (RTL, amber/locked-gray per DESIGN.md) shown when `heldByOther`, reading "‏{name} يراجع هذا الطلب الآن". Must use shadcn-vue components, not raw HTML. Wire `useEngineClaim` + `ClaimBanner` into `pages/workflows/instances/[id].vue`: show a "بدء المراجعة" (claim) button when the current stage `requires_claim` and unclaimed; show the banner when held by another; gate the action panel behind `isHeldByMe`.

- [ ] **Step 6: Run the touched FE tests**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/composables/useEngineClaim.test.ts`
Expected: PASS. Also: `pnpm exec eslint app/composables/useEngineClaim.ts app/components/workflow/ClaimBanner.vue` clean.

- [ ] **Step 7: Commit frontend changes from root**

```bash
git add frontend/app/composables/useEngineClaim.ts frontend/app/components/workflow/ClaimBanner.vue frontend/app/pages/workflows/instances/[id].vue frontend/app/tests/unit/composables/useEngineClaim.test.ts
git commit -m "feat(workflow): add engine claim soft-lock UI on instance page

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 7: Designer — expose `requires_claim` on the stage editor

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/WorkflowStageController.php` (allow `requires_claim` in validation/update)
- Modify: `frontend/app/pages/admin/workflows.vue` (stage editor: `requires_claim` toggle)
- Test: `backend/tests/Feature/Engine/WorkflowStageRequiresClaimTest.php`

**Interfaces:**
- Consumes: `workflow_stages.requires_claim` (Task 1).
- Produces: stage create/update accepts and persists `requires_claim`; designer UI toggles it.

- [ ] **Step 1: Write the failing test**

`backend/tests/Feature/Engine/WorkflowStageRequiresClaimTest.php`:

```php
<?php

namespace Tests\Feature\Engine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Support\EngineWorkflowFactory;

class WorkflowStageRequiresClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_update_persists_requires_claim(): void
    {
        ['admin' => $admin, 'stage' => $stage] = EngineWorkflowFactory::draftStageForAdmin();

        $this->actingAs($admin)
            ->patchJson("/api/v1/workflow-versions/{$stage->workflow_version_id}/stages/{$stage->id}", [
                'requires_claim' => true,
            ])
            ->assertOk();

        $this->assertTrue((bool) $stage->fresh()->requires_claim);
    }
}
```

> `EngineWorkflowFactory::draftStageForAdmin()` returns a CBY_ADMIN user + a stage on a DRAFT version. Confirm the real stage-update route/verb in `routes/api.php` (designer block) and match it.

- [ ] **Step 2: Run to verify it fails**

Run: `cd backend && php artisan test --filter=WorkflowStageRequiresClaimTest`
Expected: FAIL — `requires_claim` not persisted (not in validation/fillable use).

- [ ] **Step 3: Allow requires_claim in the controller**

In `WorkflowStageController.php`, add `'requires_claim' => ['sometimes', 'boolean'],` to the store/update validation rules, and include it in the data passed to the stage create/update (it is already in `$fillable` from Task 1).

- [ ] **Step 4: Run to verify it passes**

Run: `cd backend && php artisan test --filter=WorkflowStageRequiresClaimTest`
Expected: PASS.

- [ ] **Step 5: Add the designer toggle**

In `frontend/app/pages/admin/workflows.vue` stage editor, add a shadcn-vue `Switch` + label "يتطلب مطالبة (قفل مرن)" bound to the stage's `requires_claim`, included in the stage save payload. Use shadcn-vue components only.

- [ ] **Step 6: Format + commit backend and frontend changes from root**

```bash
cd backend
vendor/bin/pint app/Http/Controllers/Api/V1/WorkflowStageController.php --test
cd ../frontend
pnpm exec eslint app/pages/admin/workflows.vue
cd ..
git add backend/app/Http/Controllers/Api/V1/WorkflowStageController.php backend/tests/Feature/Engine/WorkflowStageRequiresClaimTest.php backend/tests/Support/EngineWorkflowFactory.php frontend/app/pages/admin/workflows.vue
git commit -m "feat(workflow): expose requires_claim on stage designer

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Final verification

- [ ] **Run all engine claim tests**

Run: `cd backend && php artisan test --filter=Claim`
Expected: all claim tests green (schema, service, guard, endpoint, expiry, designer).

- [ ] **Run touched frontend tests**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/composables/useEngineClaim.test.ts`
Expected: PASS.

- [ ] **Manual smoke (playwright-cli)**

Log in as a support-pool user, open a `requires_claim` stage request, click "بدء المراجعة", confirm action panel unlocks; in a second session confirm the banner "يراجع هذا الطلب الآن" and that claim returns 409; wait past TTL (or run `php artisan workflow:expire-engine-claims`) and confirm release.

---

## Self-Review notes

- **Spec coverage:** schema (T1), service claim/heartbeat/release (T2), execution guard (T3), endpoints (T4), TTL expiry scheduler (T5), FE soft-lock + 60s heartbeat (T6), designer flag (T7) — all P0.5 spec bullets covered.
- **Type consistency:** `EngineClaimService::{claim,heartbeat,release}` names and `EngineException::{stageClaimed,claimNotHeld}` used consistently across T2–T5; route paths consistent T4↔T6; `requires_claim` consistent T1/T3/T7.
- **Verify-before-use flagged** for the few project-specific unknowns (AuditService signature, EngineNotificationDispatcher::custom signature, AuditAction::CLAIM_ACQUIRED existence, real stage-update route, FE API composable pattern, EngineRequestResource fields) — each step says to confirm and adapt rather than assume.
