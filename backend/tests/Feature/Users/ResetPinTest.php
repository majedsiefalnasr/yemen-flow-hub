<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class ResetPinTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }

    public function test_v1_reset_pin_clears_pin_and_is_policy_gated(): void
    {
        $admin = $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-pin@test.gov',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);

        $target = $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'Support',
            'email' => 'support-pin@test.gov',
            'password' => Hash::make('Password123'),
            'is_active' => true,
            'pin_enabled' => true,
            'pin_code_hash' => Hash::make('125812'),
        ]), UserRole::SUPPORT_COMMITTEE);

        $this->actingAs($admin)->postJson("/api/v1/users/{$target->id}/reset-pin")->assertOk();

        $target->refresh();
        $this->assertFalse($target->pin_enabled);
        $this->assertNull($target->pin_code_hash);
    }

    public function test_v1_reset_pin_self_is_forbidden(): void
    {
        $admin = $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-self-pin@test.gov',
            'password' => Hash::make('Password123'),
            'is_active' => true,
            'pin_enabled' => true,
            'pin_code_hash' => Hash::make('125812'),
        ]), UserRole::CBY_ADMIN);

        $this->actingAs($admin)->postJson("/api/v1/users/{$admin->id}/reset-pin")->assertForbidden();
    }
}
