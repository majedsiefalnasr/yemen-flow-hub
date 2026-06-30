<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountRecoveryAdminResetTest extends TestCase
{
    use RefreshDatabase;

    private function makeBank(string $code = 'YCB'): Bank
    {
        return Bank::query()->create([
            'name' => "Bank {$code}",
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function makeUser(UserRole $role, ?Bank $bank, string $email): User
    {
        return User::query()->create([
            'name' => $role->value,
            'email' => $email,
            'password' => Hash::make('Password123'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    public function test_cby_admin_can_reset_bank_admin_password(): void
    {
        $cbyAdmin = $this->makeUser(UserRole::CBY_ADMIN, null, 'admin@cby.gov.ye');
        $bank = $this->makeBank();
        $target = $this->makeUser(UserRole::BANK_ADMIN, $bank, 'bank.admin@bank.test');

        $this->actingAs($cbyAdmin)->postJson("/api/users/{$target->id}/reset-password", [
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ])->assertOk();

        $target->refresh();
        $this->assertTrue(Hash::check('TempPassword123', $target->password));
        $this->assertTrue($target->must_change_password);
        $this->assertNotNull($target->temporary_password_set_at);
    }

    public function test_cby_admin_can_reset_cby_user_password(): void
    {
        $cbyAdmin = $this->makeUser(UserRole::CBY_ADMIN, null, 'admin@cby.gov.ye');
        $target = $this->makeUser(UserRole::SUPPORT_COMMITTEE, null, 'support@cby.gov.ye');

        $this->actingAs($cbyAdmin)->postJson("/api/users/{$target->id}/reset-password", [
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ])->assertOk();

        $this->assertTrue($target->refresh()->must_change_password);
    }

    public function test_bank_admin_can_reset_own_bank_staff_password_only(): void
    {
        $bank = $this->makeBank();
        $bankAdmin = $this->makeUser(UserRole::BANK_ADMIN, $bank, 'admin@bank.test');
        $target = $this->makeUser(UserRole::DATA_ENTRY, $bank, 'entry@bank.test');

        $this->actingAs($bankAdmin)->postJson("/api/users/{$target->id}/reset-password", [
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ])->assertOk();

        $this->assertTrue($target->refresh()->must_change_password);
    }

    public function test_bank_admin_cannot_reset_other_bank_staff(): void
    {
        $bank = $this->makeBank('YCB');
        $otherBank = $this->makeBank('OTH');
        $bankAdmin = $this->makeUser(UserRole::BANK_ADMIN, $bank, 'admin@bank.test');
        $target = $this->makeUser(UserRole::DATA_ENTRY, $otherBank, 'entry@other.test');

        $this->actingAs($bankAdmin)->postJson("/api/users/{$target->id}/reset-password", [
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ])->assertForbidden();
    }

    public function test_bank_admin_cannot_reset_bank_admin_password(): void
    {
        $bank = $this->makeBank();
        $bankAdmin = $this->makeUser(UserRole::BANK_ADMIN, $bank, 'admin@bank.test');
        $target = $this->makeUser(UserRole::BANK_ADMIN, $bank, 'other.admin@bank.test');

        $this->actingAs($bankAdmin)->postJson("/api/users/{$target->id}/reset-password", [
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ])->assertForbidden();
    }

    public function test_admin_password_reset_does_not_reset_mfa_or_pin(): void
    {
        $cbyAdmin = $this->makeUser(UserRole::CBY_ADMIN, null, 'admin@cby.gov.ye');
        $target = $this->makeUser(UserRole::SUPPORT_COMMITTEE, null, 'support@cby.gov.ye');
        $target->forceFill([
            'mfa_enabled' => true,
            'totp_enabled' => true,
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'totp_recovery_codes' => [Hash::make('ABCD2345')],
            'pin_enabled' => true,
            'pin_code_hash' => Hash::make('125812'),
        ])->save();

        $this->actingAs($cbyAdmin)->postJson("/api/users/{$target->id}/reset-password", [
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ])->assertOk();

        $target->refresh();
        $this->assertTrue($target->mfa_enabled);
        $this->assertTrue($target->totp_enabled);
        $this->assertSame('JBSWY3DPEHPK3PXP', $target->totp_secret);
        $this->assertTrue($target->pin_enabled);
        $this->assertNotNull($target->pin_code_hash);
    }

    public function test_mfa_and_pin_reset_are_separate_and_bank_admin_can_reset_own_staff(): void
    {
        $bank = $this->makeBank();
        $bankAdmin = $this->makeUser(UserRole::BANK_ADMIN, $bank, 'admin@bank.test');
        $cbyAdmin = $this->makeUser(UserRole::CBY_ADMIN, null, 'admin@cby.gov.ye');
        $target = $this->makeUser(UserRole::DATA_ENTRY, $bank, 'entry@bank.test');
        $target->forceFill([
            'mfa_enabled' => true,
            'totp_enabled' => true,
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'pin_enabled' => true,
            'pin_code_hash' => Hash::make('125812'),
        ])->save();

        $this->actingAs($bankAdmin)->postJson("/api/users/{$target->id}/reset-mfa")->assertOk();
        $this->actingAs($bankAdmin)->postJson("/api/users/{$target->id}/reset-pin")->assertOk();

        $target->refresh();
        $this->assertFalse($target->mfa_enabled);
        $this->assertFalse($target->totp_enabled);
        $this->assertNull($target->totp_secret);
        $this->assertNull($target->totp_recovery_codes);
        $this->assertFalse($target->pin_enabled);
        $this->assertNull($target->pin_code_hash);
    }

    public function test_bank_admin_cannot_reset_other_bank_staff_mfa_or_pin(): void
    {
        $bank = $this->makeBank('YCB');
        $otherBank = $this->makeBank('OTH');
        $bankAdmin = $this->makeUser(UserRole::BANK_ADMIN, $bank, 'admin@bank.test');
        $target = $this->makeUser(UserRole::DATA_ENTRY, $otherBank, 'entry@other.test');

        $this->actingAs($bankAdmin)->postJson("/api/users/{$target->id}/reset-mfa")->assertForbidden();
        $this->actingAs($bankAdmin)->postJson("/api/users/{$target->id}/reset-pin")->assertForbidden();
    }

    public function test_admin_reset_forces_password_change_on_next_login(): void
    {
        $cbyAdmin = $this->makeUser(UserRole::CBY_ADMIN, null, 'admin@cby.gov.ye');
        $target = $this->makeUser(UserRole::SUPPORT_COMMITTEE, null, 'support@cby.gov.ye');

        $this->actingAs($cbyAdmin)->postJson("/api/users/{$target->id}/reset-password", [
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ])->assertOk();

        $login = $this->postJson('/api/auth/login', [
            'email' => $target->email,
            'password' => 'TempPassword123',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.user.must_change_password', true);

        $this->actingAs($target->fresh())->postJson('/api/profile/change-temporary-password', [
            'password' => 'FinalPassword123',
            'password_confirmation' => 'FinalPassword123',
        ])->assertOk();

        $this->assertFalse($target->refresh()->must_change_password);
    }
}
