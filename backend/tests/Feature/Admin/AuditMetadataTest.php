<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditMetadataTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeBank(string $code = 'TBK'): Bank
    {
        return Bank::query()->create(['name' => "بنك {$code}", 'code' => $code, 'is_active' => true]);
    }

    private function makeCbyAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@cby.gov.ye',
            'password' => Hash::make('password'),
            'role' => UserRole::CBY_ADMIN->value,
            'is_active' => true,
        ]);
    }

    private function makeUser(UserRole $role, Bank $bank): User
    {
        static $n = 0;
        $n++;
        return User::query()->create([
            'name' => "User {$n}",
            'email' => "user{$n}@meta.test",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);
    }

    // ─── Migration: user_agent column width ───────────────────────────────────

    /** @test */
    public function test_audit_logs_user_agent_column_accepts_512_chars(): void
    {
        $longUa = str_repeat('A', 512);

        $log = AuditLog::query()->create([
            'action' => AuditAction::LOGIN->value,
            'user_agent' => $longUa,
        ]);

        $this->assertSame($longUa, $log->fresh()->user_agent);
    }

    /** @test */
    public function test_audit_logs_user_agent_column_is_nullable(): void
    {
        $log = AuditLog::query()->create(['action' => AuditAction::LOGIN->value]);
        $this->assertNull($log->fresh()->user_agent);
    }

    // ─── AuditService: ip + user_agent captured from request ─────────────────

    /** @test */
    public function test_audit_service_captures_ip_and_user_agent_from_request(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank  = $this->makeBank('IPT');

        // POST /api/users creates a USER_CREATED audit log with ip+user_agent.
        $this->actingAs($admin)
            ->withHeaders(['User-Agent' => 'TestBrowser/1.0'])
            ->postJson('/api/users', [
                'name'      => 'IP Test User',
                'email'     => 'iptest@cby.gov.ye',
                'password'  => 'Secret123!',
                'role'      => UserRole::DATA_ENTRY->value,
                'bank_id'   => $bank->id,
                'is_active' => true,
            ])
            ->assertStatus(201);

        $log = AuditLog::query()
            ->where('action', AuditAction::USER_CREATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->ip_address);
        $this->assertSame('TestBrowser/1.0', $log->user_agent);
    }

    /** @test */
    public function test_audit_service_writes_null_user_agent_when_no_ua_header_present(): void
    {
        // Symfony's Request::create() injects HTTP_USER_AGENT='Symfony' by default.
        // Strip it to faithfully simulate a CLI/queue context with no browser UA.
        $bare = \Illuminate\Http\Request::create('/');
        $bare->headers->remove('User-Agent');
        $bare->server->remove('HTTP_USER_AGENT');
        $this->app->instance('request', $bare);

        $log = app(AuditService::class)->log(
            AuditAction::CLAIM_RELEASED,
            null,
            null,
            ['source' => 'job'],
        );

        $this->assertNull($log->user_agent);
    }

    /** @test */
    public function test_audit_service_truncates_user_agent_to_512_chars(): void
    {
        $admin  = $this->makeCbyAdmin();
        $bank   = $this->makeBank('TRK');
        $longUa = str_repeat('X', 600);

        // Any write endpoint that logs via AuditService will do; POST /api/users is simplest.
        $this->actingAs($admin)
            ->withHeaders(['User-Agent' => $longUa])
            ->postJson('/api/users', [
                'name'      => 'Truncate Test',
                'email'     => 'trunc@cby.gov.ye',
                'password'  => 'Secret123!',
                'role'      => UserRole::DATA_ENTRY->value,
                'bank_id'   => $bank->id,
                'is_active' => true,
            ])
            ->assertStatus(201);

        $log = AuditLog::query()
            ->where('action', AuditAction::USER_CREATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->user_agent);
        $this->assertLessThanOrEqual(512, strlen($log->user_agent));
    }

    // ─── AuditLogResource: exposes user_agent ─────────────────────────────────

    /** @test */
    public function test_audit_log_resource_exposes_user_agent_and_ip_address(): void
    {
        $admin = $this->makeCbyAdmin();

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGIN->value,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0 TestAgent',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/audit');

        $response->assertOk();
        $entry = $response->json('data.data.0');

        $this->assertArrayHasKey('ip_address', $entry);
        $this->assertArrayHasKey('user_agent', $entry);
        $this->assertSame('10.0.0.1', $entry['ip_address']);
        $this->assertSame('Mozilla/5.0 TestAgent', $entry['user_agent']);
    }

    /** @test */
    public function test_audit_log_resource_renders_gracefully_when_user_agent_is_null(): void
    {
        $admin = $this->makeCbyAdmin();

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGIN->value,
            'ip_address' => null,
            'user_agent' => null,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/audit');

        $response->assertOk();
        $entry = $response->json('data.data.0');

        $this->assertArrayHasKey('user_agent', $entry);
        $this->assertNull($entry['user_agent']);
        $this->assertNull($entry['ip_address']);
    }

    // ─── Before/after on USER_UPDATED ─────────────────────────────────────────

    /** @test */
    public function test_user_update_audit_log_contains_only_changed_keys_in_before_after(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank();
        $target = $this->makeUser(UserRole::DATA_ENTRY, $bank);

        // Update only the role — name/email/is_active unchanged
        $this->actingAs($admin)->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $target->bank_id,
            'is_active' => $target->is_active,
        ])->assertOk();

        $log = AuditLog::query()
            ->where('action', AuditAction::USER_UPDATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $before = $log->metadata['before'] ?? [];
        $after  = $log->metadata['after']  ?? [];

        // Only 'role' changed — before/after must contain only that key
        $this->assertArrayHasKey('role', $before);
        $this->assertArrayHasKey('role', $after);
        $this->assertSame(UserRole::DATA_ENTRY->value, $before['role']);
        $this->assertSame(UserRole::BANK_REVIEWER->value, $after['role']);

        // Unchanged fields must NOT appear
        $this->assertArrayNotHasKey('name', $before);
        $this->assertArrayNotHasKey('email', $before);
        $this->assertArrayNotHasKey('is_active', $before);
    }

    /** @test */
    public function test_user_update_audit_log_before_after_empty_when_nothing_changed(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank('NBK');
        $target = $this->makeUser(UserRole::DATA_ENTRY, $bank);

        // Submit identical values
        $this->actingAs($admin)->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role->value,
            'bank_id' => $target->bank_id,
            'is_active' => $target->is_active,
        ])->assertOk();

        $log = AuditLog::query()
            ->where('action', AuditAction::USER_UPDATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEmpty($log->metadata['before'] ?? []);
        $this->assertEmpty($log->metadata['after'] ?? []);
    }

    // ─── Before/after on BANK_UPDATED ─────────────────────────────────────────

    /** @test */
    public function test_bank_update_audit_log_contains_only_changed_keys_in_before_after(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank('CBK');

        $this->actingAs($admin)->putJson("/api/banks/{$bank->id}", [
            'name' => 'بنك جديد',
            'code' => 'CBK',
            'is_active' => true,
        ])->assertOk();

        $log = AuditLog::query()
            ->where('action', AuditAction::BANK_UPDATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $before = $log->metadata['before'] ?? [];
        $after  = $log->metadata['after']  ?? [];

        // Only 'name' changed
        $this->assertArrayHasKey('name', $before);
        $this->assertArrayHasKey('name', $after);
        $this->assertSame("بنك CBK", $before['name']);
        $this->assertSame('بنك جديد', $after['name']);

        // Unchanged fields must NOT appear
        $this->assertArrayNotHasKey('code', $before);
        $this->assertArrayNotHasKey('is_active', $before);
    }

    /** @test */
    public function test_bank_update_audit_log_before_after_empty_when_nothing_changed(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank('ZBK');

        $this->actingAs($admin)->putJson("/api/banks/{$bank->id}", [
            'name' => "بنك ZBK",
            'code' => 'ZBK',
            'is_active' => true,
        ])->assertOk();

        $log = AuditLog::query()
            ->where('action', AuditAction::BANK_UPDATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEmpty($log->metadata['before'] ?? []);
        $this->assertEmpty($log->metadata['after'] ?? []);
    }
}
