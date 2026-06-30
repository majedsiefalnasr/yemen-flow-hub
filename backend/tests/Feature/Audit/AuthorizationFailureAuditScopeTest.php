<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationFailureAuditScopeTest extends TestCase
{
    use RefreshDatabase;

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

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "authz{$counter}@example.test",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(Bank $bank, User $creator): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Supplier Co.',
                'goods_description' => 'Industrial equipment',
                'port_of_entry' => 'Aden Port',
                'status' => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
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

    public function test_workflow_domain_authorization_denial_still_creates_audit(): void
    {
        $bank = $this->makeBank();
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $bank);
        $reviewer = $this->makeUser(UserRole::BANK_REVIEWER, $bank);
        $request = $this->makeRequest($bank, $creator);

        $this->actingAs($reviewer)
            ->postJson("/api/workflow/{$request->id}/submit")
            ->assertForbidden()
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $reviewer->id,
            'user_role' => UserRole::BANK_REVIEWER->value,
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
