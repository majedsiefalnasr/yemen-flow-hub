<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\SessionInvalidationService;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
    }

    public function test_auth_me_returns_bank_identity_and_computed_permissions(): void
    {
        $user = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.organization.code', 'commercial_banks')
            ->assertJsonPath('data.team.code', 'bank_admin')
            ->assertJsonPath('data.role.code', 'bank_admin')
            ->assertJsonPath('data.bank.id', $user->bank_id)
            ->assertJsonPath('data.screen_permissions.users', ['MANAGE', 'VIEW'])
            ->assertJsonPath('data.capabilities.manage_users', true);
    }

    public function test_auth_me_returns_null_bank_for_committee_identity(): void
    {
        $user = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->firstOrFail();

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.organization.code', 'national_committee')
            ->assertJsonPath('data.team.code', 'support')
            ->assertJsonPath('data.role.code', 'support')
            ->assertJsonPath('data.bank', null);
    }

    public function test_session_invalidation_revokes_all_tokens(): void
    {
        $user = User::query()->firstOrFail();
        $user->createToken('one');
        $user->createToken('two');

        app(SessionInvalidationService::class)->invalidate($user);

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_deactivated_user_is_rejected_on_subsequent_authenticated_request(): void
    {
        $user = User::query()->firstOrFail();
        Sanctum::actingAs($user);
        $user->update(['is_active' => false]);

        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
