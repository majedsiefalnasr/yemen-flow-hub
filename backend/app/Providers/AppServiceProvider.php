<?php

namespace App\Providers;

use App\Services\Authorization\PermissionService;
use App\Services\Documents\PdfGeneratorService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionService::class, PermissionService::class);
        $this->app->singleton(PdfGeneratorService::class, PdfGeneratorService::class);
    }

    public function boot(): void
    {
    }
}
