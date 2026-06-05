<?php

namespace App\Providers;

use App\Services\Authorization\PermissionService;
use App\Services\Documents\PdfGeneratorService;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionService::class, PermissionService::class);
        $this->app->singleton(PdfGeneratorService::class, PdfGeneratorService::class);
    }

    public function boot(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $mailer = (string) config('mail.default');
        $smtpHost = strtolower((string) config('mail.mailers.smtp.host'));

        if (
            $mailer !== 'smtp'
            || in_array($smtpHost, ['mailpit', 'localhost', '127.0.0.1'], true)
        ) {
            throw new RuntimeException(
                'Production mail must use the approved CBY/government SMTP server.'
            );
        }
    }
}
