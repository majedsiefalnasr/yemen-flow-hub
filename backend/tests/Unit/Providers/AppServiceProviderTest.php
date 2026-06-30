<?php

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use RuntimeException;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_local_environment_allows_mailpit(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'mailpit',
        ]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertTrue(true);
    }

    public function test_production_environment_rejects_mailpit(): void
    {
        $originalEnvironment = $this->app->environment();

        try {
            $this->app->detectEnvironment(fn () => 'production');
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => 'mailpit',
            ]);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(
                'Production mail must use the approved CBY/government SMTP server.'
            );

            (new AppServiceProvider($this->app))->boot();
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_production_environment_requires_smtp(): void
    {
        $originalEnvironment = $this->app->environment();

        try {
            $this->app->detectEnvironment(fn () => 'production');
            config([
                'mail.default' => 'log',
                'mail.mailers.smtp.host' => 'smtp.cby.gov.ye',
            ]);

            $this->expectException(RuntimeException::class);

            (new AppServiceProvider($this->app))->boot();
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }
}
