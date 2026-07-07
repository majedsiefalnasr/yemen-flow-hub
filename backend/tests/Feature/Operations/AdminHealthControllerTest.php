<?php

namespace Tests\Feature\Operations;

use App\Enums\UserRole;
use App\Models\SchedulerRunLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class AdminHealthControllerTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }

    private function makeCbyAdmin(): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'CBY Admin',
            'email' => 'admin-health@cby.gov.ye',
            'password' => Hash::make('password'),
            'bank_id' => null,
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);
    }

    private function makeBankUser(): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'Bank User',
            'email' => 'bank-user@bank.com',
            'password' => Hash::make('password'),
            'bank_id' => null,
            'is_active' => true,
        ]), UserRole::DATA_ENTRY);
    }

    public function test_cby_admin_can_view_health_surface(): void
    {
        $admin = $this->makeCbyAdmin();

        SchedulerRunLog::create([
            'command' => 'workflow:expire-engine-claims',
            'status' => 'success',
            'affected_count' => 1,
            'ran_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'scheduler',
                    'queue' => ['failed_jobs_count', 'recent_failures'],
                    'retention' => ['last_runs'],
                    'mail' => ['driver'],
                ],
            ]);
    }

    public function test_non_admin_forbidden(): void
    {
        $user = $this->makeBankUser();

        $this->actingAs($user)
            ->getJson('/api/admin/health')
            ->assertForbidden();
    }

    public function test_health_surface_reports_failed_jobs(): void
    {
        $admin = $this->makeCbyAdmin();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test failure',
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/health');

        $response->assertOk();
        $response->assertJsonPath('data.queue.failed_jobs_count', 1);
        $this->assertCount(1, $response->json('data.queue.recent_failures'));
    }

    public function test_check_scheduler_health_flags_stale_command(): void
    {
        SchedulerRunLog::create([
            'command' => 'workflow:expire-engine-claims',
            'status' => 'success',
            'affected_count' => 0,
            'ran_at' => now()->subMinutes(10),
        ]);

        $this->artisan('ops:check-scheduler-health')->assertSuccessful();

        $log = SchedulerRunLog::query()
            ->where('command', 'ops:check-scheduler-health')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertContains('workflow:expire-engine-claims', $log->meta['stale_commands'] ?? []);
    }
}
