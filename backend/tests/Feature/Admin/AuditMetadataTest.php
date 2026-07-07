<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Audit\AuditService;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class AuditMetadataTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
        $this->seed(ScreenPermissionSeeder::class);
    }

    private function makeBank(string $code = 'TBK'): Bank
    {
        $organization = Organization::query()->where('code', 'commercial_banks')->firstOrFail();

        return Bank::query()->create([
            'name' => "بنك {$code}",
            'code' => $code,
            'is_active' => true,
            'organization_id' => $organization->id,
            'status' => 'ACTIVE',
            'version' => 1,
        ]);
    }

    private function makeCbyAdmin(): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@cby.gov.ye',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);
    }

    private function makeUser(UserRole $role, Bank $bank): User
    {
        static $n = 0;
        $n++;

        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => "User {$n}",
            'email' => "user{$n}@meta.test",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]), $role);
    }

    /** @return array<string, mixed> */
    private function v1UserPayload(UserRole $role, Bank $bank, array $overrides = []): array
    {
        $organization = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $map = [
            UserRole::DATA_ENTRY->value => ['entry', 'intake'],
            UserRole::BANK_REVIEWER->value => ['internal_review', 'internal_reviewer'],
        ];
        [$teamCode, $roleCode] = $map[$role->value];
        $team = Team::query()->whereBelongsTo($organization)->where('code', $teamCode)->firstOrFail();
        $govRole = Role::query()->whereBelongsTo($organization)->where('code', $roleCode)->firstOrFail();

        return array_merge([
            'organization_id' => $organization->id,
            'team_id' => $team->id,
            'role_id' => $govRole->id,
            'bank_id' => $bank->id,
            'name' => 'Test User',
            'email' => 'test@cby.gov.ye',
            'password' => 'Secret123!',
            'is_active' => true,
        ], $overrides);
    }

    public function test_audit_logs_user_agent_column_accepts_512_chars(): void
    {
        $longUa = str_repeat('A', 512);

        $log = AuditLog::query()->create([
            'action' => AuditAction::LOGIN->value,
            'user_agent' => $longUa,
        ]);

        $this->assertSame($longUa, $log->fresh()->user_agent);
    }

    public function test_audit_logs_user_agent_column_is_nullable(): void
    {
        $log = AuditLog::query()->create(['action' => AuditAction::LOGIN->value]);
        $this->assertNull($log->fresh()->user_agent);
    }

    public function test_audit_service_captures_ip_and_user_agent_from_request(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank('IPT');

        $this->actingAs($admin)
            ->withHeaders(['User-Agent' => 'TestBrowser/1.0'])
            ->postJson('/api/v1/users', $this->v1UserPayload(UserRole::DATA_ENTRY, $bank, [
                'name' => 'IP Test User',
                'email' => 'iptest@cby.gov.ye',
            ]))
            ->assertStatus(201);

        $log = AuditLog::query()
            ->where('action', AuditAction::USER_CREATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->ip_address);
        $this->assertSame('TestBrowser/1.0', $log->user_agent);
    }

    public function test_audit_service_writes_null_user_agent_when_no_ua_header_present(): void
    {
        $bare = Request::create('/');
        $bare->headers->remove('User-Agent');
        $bare->server->remove('HTTP_USER_AGENT');
        $this->app->instance('request', $bare);

        $log = app(AuditService::class)->log(
            AuditAction::CLAIM_RELEASED,
            null,
            null,
            ['source' => 'job'],
        );

        $this->assertNull($log->ip_address);
        $this->assertNull($log->user_agent);
    }

    public function test_audit_service_truncates_user_agent_to_512_chars(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank('TRK');
        $longUa = str_repeat('X', 600);

        $this->actingAs($admin)
            ->withHeaders(['User-Agent' => $longUa])
            ->postJson('/api/v1/users', $this->v1UserPayload(UserRole::DATA_ENTRY, $bank, [
                'name' => 'Truncate Test',
                'email' => 'trunc@cby.gov.ye',
            ]))
            ->assertStatus(201);

        $log = AuditLog::query()
            ->where('action', AuditAction::USER_CREATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->user_agent);
        $this->assertLessThanOrEqual(512, strlen($log->user_agent));
    }

    public function test_audit_service_truncates_multibyte_user_agent_without_breaking_utf8(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank('UTF');
        $longUa = str_repeat('متصفح-', 120);

        $this->actingAs($admin)
            ->withHeaders(['User-Agent' => $longUa])
            ->postJson('/api/v1/users', $this->v1UserPayload(UserRole::DATA_ENTRY, $bank, [
                'name' => 'UTF User Agent',
                'email' => 'utf-agent@cby.gov.ye',
            ]))
            ->assertStatus(201);

        $log = AuditLog::query()
            ->where('action', AuditAction::USER_CREATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->user_agent);
        $this->assertSame(512, mb_strlen($log->user_agent));
        $this->assertTrue(mb_check_encoding($log->user_agent, 'UTF-8'));
    }

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

        $response = $this->actingAs($admin)->getJson('/api/v1/audit-logs?event=LOGIN');

        $response->assertOk();
        $entry = $response->json('data.0');

        $this->assertArrayHasKey('ip_address', $entry);
        $this->assertArrayHasKey('user_agent', $entry);
        $this->assertSame('10.0.0.1', $entry['ip_address']);
        $this->assertSame('Mozilla/5.0 TestAgent', $entry['user_agent']);
    }

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

        $response = $this->actingAs($admin)->getJson('/api/v1/audit-logs?event=LOGIN');

        $response->assertOk();
        $entry = $response->json('data.0');

        $this->assertArrayHasKey('user_agent', $entry);
        $this->assertNull($entry['user_agent']);
        $this->assertNull($entry['ip_address']);
    }
}
