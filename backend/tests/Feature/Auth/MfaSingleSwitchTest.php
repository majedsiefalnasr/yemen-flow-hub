<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Api\AuthController;
use App\Models\SystemSetting;
use App\Services\Auth\AuthSecuritySettings;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfaSingleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_required_false_disables_gate(): void
    {
        SystemSetting::where('key', 'mfa_required')->update(['value' => false]);

        $this->assertFalse(app(AuthSecuritySettings::class)->mfaRequired());
    }

    public function test_config_mfa_enabled_no_longer_overrides_when_db_false(): void
    {
        config(['mfa.enabled' => true]);
        SystemSetting::where('key', 'mfa_required')->update(['value' => false]);

        // After consolidation, config('mfa.enabled') is NOT consulted by mfaRequiredFor.
        $reflection = new \ReflectionMethod(AuthController::class, 'mfaRequiredFor');
        $reflection->setAccessible(true);

        $controller = app(AuthController::class);
        $user = UserFactory::new()->create();

        $this->assertFalse($reflection->invoke($controller, $user));
    }
}
