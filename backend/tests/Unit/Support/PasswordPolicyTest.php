<?php

namespace Tests\Unit\Support;

use App\Models\PasswordHistory;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_rejects_blacklisted_password(): void
    {
        $user = User::query()->create([
            'name' => 'Policy User',
            'email' => 'policy@example.com',
            'password' => Hash::make('Oldpass9'),
            'role' => 'BANK_REVIEWER',
            'is_active' => true,
        ]);

        $errors = PasswordPolicy::validate($user, 'password123');

        $this->assertArrayHasKey('password', $errors);
    }

    public function test_validate_rejects_reused_password_from_history(): void
    {
        $user = User::query()->create([
            'name' => 'Policy User',
            'email' => 'policy2@example.com',
            'password' => Hash::make('Oldpass9'),
            'role' => 'BANK_REVIEWER',
            'is_active' => true,
        ]);

        PasswordHistory::query()->create([
            'user_id' => $user->id,
            'password_hash' => Hash::make('Historic1'),
            'created_at' => now(),
        ]);

        $errors = PasswordPolicy::validate($user, 'Historic1');

        $this->assertArrayHasKey('password', $errors);
    }

    public function test_record_history_trims_to_configured_count(): void
    {
        config(['auth_security.password_history_count' => 2]);
        $user = User::query()->create([
            'name' => 'Policy User',
            'email' => 'policy3@example.com',
            'password' => Hash::make('Current9'),
            'role' => 'BANK_REVIEWER',
            'is_active' => true,
        ]);

        PasswordPolicy::recordHistory($user);
        $user->password = Hash::make('Second9');
        $user->save();
        PasswordPolicy::recordHistory($user);
        $user->password = Hash::make('Third9');
        $user->save();
        PasswordPolicy::recordHistory($user);

        $this->assertEquals(2, PasswordHistory::query()->where('user_id', $user->id)->count());
    }
}
