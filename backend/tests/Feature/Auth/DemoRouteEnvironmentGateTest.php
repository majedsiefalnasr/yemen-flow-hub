<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DemoRouteEnvironmentGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_environment_is_excluded_from_demo_allowed_list(): void
    {
        $this->assertNotContains('production', config('demo.allowed_environments'));
    }

    public function test_demo_routes_not_registered_in_production_environment(): void
    {
        $process = new Process(
            [PHP_BINARY, base_path('artisan'), 'route:list', '--path=auth/demo'],
            base_path(),
            [
                'APP_ENV' => 'production',
                'APP_KEY' => config('app.key'),
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
                'MAIL_MAILER' => 'smtp',
                'MAIL_HOST' => 'smtp.cby.gov.ye',
            ],
        );

        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertStringNotContainsString('demo-users', $process->getOutput());
        $this->assertStringNotContainsString('switch-demo-user', $process->getOutput());
        $this->assertStringNotContainsString('switch-demo-role', $process->getOutput());
    }

    public function test_demo_routes_available_in_allowed_environment_when_flag_enabled(): void
    {
        config(['demo.allow_role_switch' => true]);

        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);

        $this->getJson('/api/auth/demo-users')->assertOk();
    }

    public function test_switch_demo_user_writes_audit_log(): void
    {
        config(['demo.allow_role_switch' => true]);
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);

        $target = User::query()->where('is_active', true)->orderBy('id')->firstOrFail();

        $this->withHeader('Referer', 'http://'.config('sanctum.stateful.0'))
            ->postJson('/api/auth/switch-demo-user', ['user_id' => $target->id])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DEMO_USER_SWITCH',
            'subject_id' => $target->id,
        ]);
    }
}
