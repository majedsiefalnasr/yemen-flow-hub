<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * QUEUE-003: queue-depth/failure-rate data is operational infrastructure
     * detail, not appropriate for any authenticated user — restricted to
     * system admins (CBY_ADMIN), matching the same check already used to
     * gate audit-log/report system-wide visibility elsewhere in the app.
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return $user !== null && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin();
        });
    }
}
