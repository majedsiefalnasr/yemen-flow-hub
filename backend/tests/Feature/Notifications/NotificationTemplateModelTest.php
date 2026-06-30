<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_has_versions_and_active_version_relationships(): void
    {
        $template = NotificationTemplate::query()->create([
            'notification_type' => NotificationType::REQUEST_APPROVED->value,
        ]);

        $inactive = $template->versions()->create([
            'subject' => 'Old',
            'body' => 'Old body',
            'is_active_version' => false,
        ]);

        $active = $template->versions()->create([
            'subject' => 'Current',
            'body' => 'Current body',
            'is_active_version' => true,
        ]);

        $this->assertTrue($template->versions->contains($inactive));
        $this->assertTrue($template->versions->contains($active));
        $this->assertTrue($template->activeVersion->is($active));
    }

    public function test_create_active_version_atomically_flips_existing_active_version(): void
    {
        $template = NotificationTemplate::query()->create([
            'notification_type' => NotificationType::REQUEST_APPROVED->value,
        ]);

        $first = $template->createActiveVersion('First', 'First body');
        $second = $template->createActiveVersion('Second', 'Second body');

        $this->assertFalse($first->refresh()->is_active_version);
        $this->assertTrue($second->refresh()->is_active_version);
        $this->assertSame(1, $template->versions()->where('is_active_version', true)->count());
        $this->assertTrue($template->refresh()->activeVersion->is($second));
    }
}
