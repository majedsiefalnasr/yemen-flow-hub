<?php

namespace Tests\Unit\Seeders;

use Database\Seeders\Concerns\GuardsDemoSeedEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class DemoSeedGuardTest extends TestCase
{
    use GuardsDemoSeedEnvironment;
    use RefreshDatabase;

    public function test_production_environment_blocks_demo_seed(): void
    {
        $this->app['env'] = 'production';
        config(['demo.seed_demo_data' => true]);
        config(['demo.allowed_seed_environments' => ['local', 'staging', 'testing']]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('forbidden in production');

        $this->ensureDemoSeedAllowed();
    }

    public function test_disabled_seed_demo_data_blocks_demo_seed(): void
    {
        $this->app['env'] = 'local';
        config(['demo.seed_demo_data' => false]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('demo.seed_demo_data=false');

        $this->ensureDemoSeedAllowed();
    }

    public function test_disallowed_environment_blocks_demo_seed(): void
    {
        $this->app['env'] = 'local';
        config(['demo.seed_demo_data' => true]);
        config(['demo.allowed_seed_environments' => ['staging']]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('not allowed in this environment');

        $this->ensureDemoSeedAllowed();
    }

    public function test_allowed_local_environment_permits_demo_seed(): void
    {
        $this->app['env'] = 'local';
        config(['demo.seed_demo_data' => true]);
        config(['demo.allowed_seed_environments' => ['local', 'staging', 'testing']]);

        $this->ensureDemoSeedAllowed();
        $this->assertTrue(true);
    }
}
