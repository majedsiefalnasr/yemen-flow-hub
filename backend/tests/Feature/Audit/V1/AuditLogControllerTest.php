<?php

namespace Tests\Feature\Audit\V1;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $bankUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $cbyOrg = Organization::where('code', 'national_committee')->first();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@cby.gov',
            'password' => bcrypt('password'),
            'role' => UserRole::CBY_ADMIN,
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $systemAdminRole = Role::query()->where('code', 'system_admin')->firstOrFail();
        $this->admin->roles()->attach($systemAdminRole->id);

        $bankOrg = Organization::where('code', 'commercial_banks')->first();
        $bank = Bank::create(['name' => 'Test Bank', 'code' => 'TST', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $this->bankUser = User::create([
            'name' => 'Bank User',
            'email' => 'entry@test.bank',
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
    }

    public function test_append_only_no_update_or_delete_path(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::LOGIN->value,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
        $this->assertFalse(method_exists(AuditLog::class, 'scopeUpdate'));
        $this->assertEmpty(
            array_filter(
                get_class_methods(AuditLogController::class),
                fn ($m) => in_array($m, ['update', 'destroy', 'delete'], true)
            )
        );
    }

    public function test_engine_columns_saved(): void
    {
        $correlationId = 'test-corr-123';

        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::STATUS_TRANSITION->value,
            'correlation_id' => $correlationId,
            'old_values' => ['stage' => 'INTAKE'],
            'new_values' => ['stage' => 'REVIEW'],
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'correlation_id' => $correlationId,
        ]);
        $fresh = $log->fresh();
        $this->assertEquals(['stage' => 'INTAKE'], $fresh->old_values);
        $this->assertEquals(['stage' => 'REVIEW'], $fresh->new_values);
    }

    public function test_index_requires_audit_view_permission(): void
    {
        $this->actingAs($this->bankUser)
            ->getJson('/api/v1/audit-logs')
            ->assertForbidden();
    }

    public function test_index_returns_paginated_results(): void
    {
        AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::USER_UPDATED->value, 'created_at' => now()]);
        AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::SETTINGS_UPDATED->value, 'created_at' => now()]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_excludes_login_and_logout_by_default(): void
    {
        AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::LOGIN->value, 'created_at' => now()]);
        AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::LOGOUT->value, 'created_at' => now()]);
        AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::LOGIN_FAILED->value, 'created_at' => now()]);
        AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::USER_UPDATED->value, 'created_at' => now()]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs')
            ->assertOk();

        $actions = collect($response->json('data'))->pluck('event_code')->all();
        $this->assertNotContains('LOGIN', $actions);
        $this->assertNotContains('LOGOUT', $actions);
        $this->assertContains('LOGIN_FAILED', $actions);
        $this->assertContains('USER_UPDATED', $actions);
    }

    public function test_index_filters_by_user(): void
    {
        AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::USER_UPDATED->value, 'created_at' => now()]);
        AuditLog::create(['user_id' => $this->bankUser->id, 'action' => AuditAction::USER_UPDATED->value, 'created_at' => now()]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs?user='.$this->admin->id)
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_filters_by_event(): void
    {
        AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::LOGIN->value, 'created_at' => now()]);
        AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::LOGOUT->value, 'created_at' => now()]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs?event=LOGIN')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('LOGIN', $response->json('data.0.event_code'));
    }

    public function test_index_filters_by_date_range(): void
    {
        $old = new AuditLog(['user_id' => $this->admin->id, 'action' => AuditAction::USER_UPDATED->value]);
        $old->created_at = '2025-01-01 10:00:00';
        $old->save();

        $recent = new AuditLog(['user_id' => $this->admin->id, 'action' => AuditAction::SETTINGS_UPDATED->value]);
        $recent->created_at = '2026-06-15 10:00:00';
        $recent->save();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs?from=2026-06-01&to=2026-06-30')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($recent->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_index_filters_by_correlation_id(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::STATUS_TRANSITION->value,
            'correlation_id' => 'abc-123',
            'created_at' => now(),
        ]);
        AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::LOGIN->value, 'created_at' => now()]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs?correlation_id=abc-123')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_returns_audit_log_detail(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::LOGIN->value,
            'ip_address' => '10.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs/'.$log->id)
            ->assertOk()
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.event_code', 'LOGIN')
            ->assertJsonPath('data.ip_address', '10.0.0.1');
    }

    public function test_export_returns_csv_and_audits_the_export(): void
    {
        AuditLog::create(['user_id' => $this->admin->id, 'action' => AuditAction::LOGIN->value, 'created_at' => now()]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AUDIT_LOG_EXPORTED->value,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_export_forbidden_without_permission(): void
    {
        $this->actingAs($this->bankUser)
            ->getJson('/api/v1/audit-logs/export')
            ->assertForbidden();
    }
}
