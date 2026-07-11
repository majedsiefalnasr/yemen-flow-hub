<?php

namespace App\Support;

class DemoEnvironment
{
    /**
     * Whether demo identity switching is permitted right now.
     *
     * Requires BOTH the feature flag AND an explicitly approved non-production
     * environment. Production always fails closed, even if
     * APP_DEMO_ROLE_SWITCH=true — a copied env var must never enable identity
     * switching in production (H6 / M2).
     */
    public static function switchingAllowed(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        if (! (bool) config('demo.allow_role_switch', false)) {
            return false;
        }

        return app()->environment((array) config('demo.allowed_environments', []));
    }
}
