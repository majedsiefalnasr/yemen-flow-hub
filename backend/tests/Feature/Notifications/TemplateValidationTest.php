<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Models\NotificationTemplate;
use App\Services\Notifications\TemplateRenderer;
use App\Services\Notifications\TemplateValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TemplateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_time_rejects_variables_not_allowed_by_registry(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('secret_token');

        app(TemplateValidator::class)->validateForSave(
            NotificationType::REQUEST_APPROVED,
            'طلب {{reference_number}}',
            'مرحبا {{user_name}} {{secret_token}}'
        );
    }

    public function test_save_time_strips_raw_html_from_markdown_body(): void
    {
        $validated = app(TemplateValidator::class)->validateForSave(
            NotificationType::REQUEST_APPROVED,
            'طلب {{reference_number}}',
            '<script>alert("x")</script><p>مرحبا</p> **{{user_name}}**'
        );

        $this->assertSame('طلب {{reference_number}}', $validated['subject']);
        $this->assertStringNotContainsString('<script', $validated['body']);
        $this->assertStringNotContainsString('<p>', $validated['body']);
        $this->assertStringContainsString('**{{user_name}}**', $validated['body']);
    }

    public function test_render_time_missing_allowed_variable_uses_safe_fallback(): void
    {
        $template = NotificationTemplate::query()->create([
            'notification_type' => NotificationType::REQUEST_APPROVED,
        ]);

        $template->versions()->create([
            'subject' => 'طلب {{reference_number}}',
            'body' => 'مرحبا {{user_name}} بخصوص {{reference_number}}',
            'is_active_version' => true,
        ]);

        $rendered = app(TemplateRenderer::class)->render(NotificationType::REQUEST_APPROVED, [
            'user_name' => 'محمد',
        ]);

        $this->assertStringNotContainsString('{{reference_number}}', $rendered['subject']);
        $this->assertStringNotContainsString('{{reference_number}}', $rendered['html']);
        $this->assertStringContainsString('مرحبا محمد بخصوص', $rendered['text']);
    }
}
