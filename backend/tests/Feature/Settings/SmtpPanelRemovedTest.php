<?php

namespace Tests\Feature\Settings;

use App\Services\Settings\AdminSettingsService;
use Tests\TestCase;

class SmtpPanelRemovedTest extends TestCase
{
    public function test_smtp_routes_absent(): void
    {
        $routes = collect(\Route::getRoutes()->get('PUT'))
            ->flatMap(fn ($methods, $uri) => [$uri]);

        $this->assertFalse(in_array('api/admin/settings/smtp', $this->allUris()));
        $this->assertFalse(collect($this->allUris())->contains('api/admin/settings/email/test'));
    }

    private function allUris(): array
    {
        return collect(\Route::getRoutes())->map(fn ($r) => $r->uri())->toArray();
    }

    public function test_smtp_methods_absent_from_service(): void
    {
        $this->assertFalse(
            method_exists(AdminSettingsService::class, 'getSmtpSettings'),
            'SMTP settings methods must be removed (env-only mail).'
        );
        $this->assertFalse(
            method_exists(AdminSettingsService::class, 'updateSmtpSettings')
        );
    }
}
