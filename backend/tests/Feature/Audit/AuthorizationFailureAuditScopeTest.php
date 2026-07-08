<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class AuthorizationFailureAuditScopeTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }

    private function makeBank(): Bank
    {
        return Bank::query()->create([
            'name' => 'Test Bank',
            'code' => 'TB',
            'is_active' => true,
        ]);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;

        $user = User::query()->create([
            'name' => "User {$counter}",
            'email' => "authz{$counter}@example.test",
            'password' => Hash::make('password'),
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);

        return $this->assignGovernanceIdentity($user, $role);
    }

    public function test_framework_abort_403_does_not_create_authorization_failure_audit(): void
    {
        Route::get('/api/test-framework-forbidden', fn () => abort(403, 'Framework denial'))
            ->middleware('auth:sanctum');

        $actor = $this->makeUser(UserRole::CBY_ADMIN);

        $this->actingAs($actor)
            ->getJson('/api/test-framework-forbidden')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');

        $this->assertDatabaseMissing('audit_logs', [
            'user_id' => $actor->id,
            'action' => AuditAction::AUTHORIZATION_FAILURE->value,
        ]);
    }

    public function test_gate_authorization_denial_still_creates_audit(): void
    {
        Gate::define('story-16-5-denied-ability', fn () => false);
        Route::get('/api/test-gate-forbidden', fn () => Gate::authorize('story-16-5-denied-ability'))
            ->middleware('auth:sanctum');

        $actor = $this->makeUser(UserRole::CBY_ADMIN);

        $this->actingAs($actor)
            ->getJson('/api/test-gate-forbidden')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::AUTHORIZATION_FAILURE->value,
        ]);
    }
}
