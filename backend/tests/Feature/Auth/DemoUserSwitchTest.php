<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoUserSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
    }

    public function test_demo_users_list_returns_forbidden_when_flag_disabled(): void
    {
        config(['demo.allow_role_switch' => false]);

        $this->getJson('/api/auth/demo-users')->assertForbidden();
    }

    public function test_demo_users_list_returns_active_users_with_governance_identity(): void
    {
        config(['demo.allow_role_switch' => true]);

        $response = $this->getJson('/api/auth/demo-users')->assertOk();

        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();
        $entry = collect($response->json('data.users'))->firstWhere('id', $bankAdmin->id);

        $this->assertNotNull($entry);
        $this->assertSame($bankAdmin->name, $entry['name']);
        $this->assertSame($bankAdmin->email, $entry['email']);
        $this->assertSame(UserRole::BANK_ADMIN->value, $entry['role']);
        $this->assertSame(UserRole::BANK_ADMIN->label(), $entry['role_label']);
        $this->assertSame('commercial_banks', $entry['organization']['code']);
        $this->assertNotNull($entry['bank']);
    }

    public function test_demo_users_list_excludes_inactive_users(): void
    {
        config(['demo.allow_role_switch' => true]);

        $inactive = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->firstOrFail();
        $inactive->update(['is_active' => false]);

        $response = $this->getJson('/api/auth/demo-users')->assertOk();

        $ids = collect($response->json('data.users'))->pluck('id');
        $this->assertNotContains($inactive->id, $ids);
    }
}
