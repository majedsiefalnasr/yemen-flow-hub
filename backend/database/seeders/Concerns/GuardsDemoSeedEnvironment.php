<?php

namespace Database\Seeders\Concerns;

use LogicException;

trait GuardsDemoSeedEnvironment
{
    protected function ensureDemoSeedAllowed(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('Demo engine request seeders are forbidden in production.');
        }

        if (! config('demo.seed_demo_data', false)) {
            throw new LogicException('Demo seeding is disabled (demo.seed_demo_data=false).');
        }

        if (! in_array(app()->environment(), config('demo.allowed_seed_environments', []), true)) {
            throw new LogicException('Demo seeding is not allowed in this environment.');
        }
    }
}
