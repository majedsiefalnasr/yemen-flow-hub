<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
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
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
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

        $bankAdmin = $this->firstUserWithRole(UserRole::BANK_ADMIN);
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

        $inactive = $this->firstUserWithRole(UserRole::SUPPORT_COMMITTEE);
        $inactive->update(['is_active' => false]);

        $response = $this->getJson('/api/auth/demo-users')->assertOk();

        $ids = collect($response->json('data.users'))->pluck('id');
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_switch_demo_user_returns_forbidden_when_flag_disabled(): void
    {
        config(['demo.allow_role_switch' => false]);

        $user = $this->firstUserWithRole(UserRole::DATA_ENTRY);

        $this->postJson('/api/auth/switch-demo-user', ['user_id' => $user->id])
            ->assertForbidden();
    }

    public function test_switch_demo_user_returns_not_found_for_unknown_id(): void
    {
        config(['demo.allow_role_switch' => true]);

        $this->postJson('/api/auth/switch-demo-user', ['user_id' => 999999])
            ->assertNotFound();
    }

    public function test_switch_demo_user_returns_not_found_for_inactive_user(): void
    {
        config(['demo.allow_role_switch' => true]);

        $inactive = $this->firstUserWithRole(UserRole::SWIFT_OFFICER);
        $inactive->update(['is_active' => false]);

        $this->postJson('/api/auth/switch-demo-user', ['user_id' => $inactive->id])
            ->assertNotFound();
    }

    public function test_switch_demo_user_issues_session_for_target_user(): void
    {
        config(['demo.allow_role_switch' => true]);

        $target = $this->firstUserWithRole(UserRole::COMMITTEE_DIRECTOR);

        $response = $this->withHeader('Referer', 'http://'.config('sanctum.stateful.0'))
            ->postJson('/api/auth/switch-demo-user', ['user_id' => $target->id])
            ->assertOk();

        $response->assertJsonPath('data.user.id', $target->id);
        $response->assertJsonPath('data.requires_mfa', false);
        $this->assertAuthenticatedAs($target);
    }
}
