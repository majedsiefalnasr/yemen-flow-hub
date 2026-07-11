<?php

namespace Tests\Feature\Audit;

use App\Support\DemoEnvironment;
use Tests\TestCase;

/**
 * H6 / M2: demo identity switching must fail closed in production even when the
 * feature flag is true, and require both the flag AND an approved environment.
 */
class DemoEnvironmentGateTest extends TestCase
{
    public function test_production_denies_even_when_flag_true(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config([
            'demo.allow_role_switch' => true,
            'demo.allowed_environments' => ['local', 'staging', 'testing'],
        ]);

        $this->assertFalse(
            DemoEnvironment::switchingAllowed(),
            'H6: demo switching allowed in production with the flag on — must fail closed.'
        );
    }

    public function test_approved_env_requires_flag_true(): void
    {
        app()->detectEnvironment(fn () => 'local');

        config(['demo.allow_role_switch' => false, 'demo.allowed_environments' => ['local', 'staging', 'testing']]);
        $this->assertFalse(
            DemoEnvironment::switchingAllowed(),
            'Demo switching allowed with the flag off.'
        );

        config(['demo.allow_role_switch' => true]);
        $this->assertTrue(
            DemoEnvironment::switchingAllowed(),
            'Demo switching denied in an approved environment with the flag on.'
        );
    }

    public function test_unapproved_nonproduction_env_denies_even_with_flag(): void
    {
        app()->detectEnvironment(fn () => 'demo');
        config([
            'demo.allow_role_switch' => true,
            'demo.allowed_environments' => ['local', 'staging', 'testing'],
        ]);

        $this->assertFalse(
            DemoEnvironment::switchingAllowed(),
            'Demo switching allowed in an unapproved environment.'
        );
    }
}
