<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Models\NotificationTemplate;
use App\Services\Notifications\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_renderer_returns_multipart_markdown_through_email_theme(): void
    {
        $version = $this->createTemplateVersion(
            NotificationType::REQUEST_APPROVED,
            'موافقة {{reference_number}}',
            "# مرحبا {{user_name}}\n\nتمت الموافقة على **{{reference_number}}**."
        );

        $rendered = app(TemplateRenderer::class)->render(NotificationType::REQUEST_APPROVED, [
            'user_name' => 'محمد',
            'reference_number' => 'YFH-2026-000001',
        ]);

        $this->assertSame('موافقة YFH-2026-000001', $rendered['subject']);
        $this->assertStringContainsString('<strong>YFH-2026-000001</strong>', $rendered['html']);
        $this->assertStringContainsString('dir="rtl"', $rendered['html']);
        $this->assertStringContainsString('إشعار سرية', $rendered['html']);
        $this->assertStringContainsString('تمت الموافقة على YFH-2026-000001.', $rendered['text']);
        $this->assertSame($version->id, $rendered['template_version_id']);
        $this->assertSame('db', $rendered['source']);
        $this->assertSame('ar', $rendered['locale']);
    }

    public function test_renderer_escapes_substituted_values_in_html_body(): void
    {
        $this->createTemplateVersion(
            NotificationType::REQUEST_RETURNED,
            'إعادة {{reference_number}}',
            'مرحبا {{user_name}}'
        );

        $rendered = app(TemplateRenderer::class)->render(NotificationType::REQUEST_RETURNED, [
            'reference_number' => 'YFH-1',
            'user_name' => '<b>Injected</b>',
        ]);

        $this->assertStringContainsString('&lt;b&gt;Injected&lt;/b&gt;', $rendered['html']);
        $this->assertStringNotContainsString('<b>Injected</b>', $rendered['html']);
    }

    public function test_blade_source_returns_null_template_version_id(): void
    {
        $rendered = app(TemplateRenderer::class)->render(NotificationType::VOTING_OPENED, [
            'requestModel' => $this->makeRequestModel(),
        ], 'en');

        $this->assertSame('blade', $rendered['source']);
        $this->assertNull($rendered['template_version_id']);
        $this->assertSame('en', $rendered['locale']);
        $this->assertNotEmpty($rendered['html']);
        $this->assertNotEmpty($rendered['text']);
    }

    private function createTemplateVersion(NotificationType $type, string $subject, string $body): object
    {
        $template = NotificationTemplate::query()->create([
            'notification_type' => $type,
        ]);

        return $template->versions()->create([
            'subject' => $subject,
            'body' => $body,
            'is_active_version' => true,
        ]);
    }

    private function makeRequestModel(): object
    {
        return new class
        {
            public string $reference_number = 'YFH-2026-000001';

            public float $amount = 100000.0;

            public string $currency = 'USD';

            public string $supplier_name = 'مورد تجريبي';

            public int $id = 1;
        };
    }
}
