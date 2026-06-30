<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Models\NotificationTemplate;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_three_admin_editable_request_templates(): void
    {
        $this->seed(NotificationTemplateSeeder::class);

        foreach ([NotificationType::REQUEST_APPROVED, NotificationType::REQUEST_REJECTED, NotificationType::REQUEST_RETURNED] as $type) {
            $template = NotificationTemplate::query()
                ->where('notification_type', $type->value)
                ->with('activeVersion')
                ->first();

            $this->assertNotNull($template, "{$type->value} template should be seeded.");
            $this->assertTrue($template->is_active);
            $this->assertNotNull($template->activeVersion);
            $this->assertStringContainsString('{{reference_number}}', $template->activeVersion->body);
            $this->assertStringNotContainsString('{{request_reference}}', $template->activeVersion->body);
        }
    }
}
