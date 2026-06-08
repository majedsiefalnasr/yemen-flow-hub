<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Trader;
use App\Models\User;
use App\Policies\TraderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TraderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TraderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new TraderPolicy;
    }

    public function test_read_access_is_limited_to_bank_trader_management_roles(): void
    {
        // Epic 17-B decision #9: trader records (and owner identification PII)
        // are visible only to the bank-side trader roles — least privilege on
        // owner identification data.
        $allowed = [
            UserRole::DATA_ENTRY->value,
            UserRole::BANK_REVIEWER->value,
            UserRole::BANK_ADMIN->value,
        ];
        $trader = Trader::factory()->create();

        foreach (UserRole::cases() as $role) {
            $user = $this->makeUser($role);
            $expected = in_array($role->value, $allowed, true);

            $this->assertSame($expected, $this->policy->viewAny($user), "{$role->value} viewAny mismatch");
            $this->assertSame($expected, $this->policy->view($user, $trader), "{$role->value} view mismatch");
            $this->assertSame($expected, $this->policy->viewPii($user), "{$role->value} viewPii mismatch");
        }
    }

    public function test_write_access_is_limited_to_bank_trader_management_roles(): void
    {
        $allowed = [
            UserRole::DATA_ENTRY->value,
            UserRole::BANK_REVIEWER->value,
            UserRole::BANK_ADMIN->value,
        ];
        $trader = Trader::factory()->create();

        foreach (UserRole::cases() as $role) {
            $user = $this->makeUser($role);
            $expected = in_array($role->value, $allowed, true);

            $this->assertSame($expected, $this->policy->create($user), "{$role->value} create mismatch");
            $this->assertSame($expected, $this->policy->update($user, $trader), "{$role->value} update mismatch");
        }
    }

    private function makeUser(UserRole $role): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "Trader Policy User {$counter}",
            'email' => "trader-policy-{$counter}@example.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'is_active' => true,
        ]);
    }
}
