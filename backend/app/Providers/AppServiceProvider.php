<?php

namespace App\Providers;

use App\Services\Authorization\PermissionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionService::class, PermissionService::class);
    }

    public function boot(): void
    {
    }
}
