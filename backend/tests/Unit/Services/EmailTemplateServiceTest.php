<?php

namespace Tests\Unit\Services;

use App\Enums\NotificationType;
use App\Models\NotificationTemplate;
use App\Services\Mail\EmailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_variables_use_canonical_reference_number(): void
    {
        $this->assertContains('reference_number', EmailTemplateService::ALLOWED_VARIABLES);
        $this->assertNotContains('request_reference', EmailTemplateService::ALLOWED_VARIABLES);
    }

    public function test_legacy_type_and_request_reference_delegate_to_renderer(): void
    {
        $this->createTemplateVersion(
            NotificationType::REQUEST_APPROVED,
            'تمت الموافقة على {{reference_number}}',
            'مرحبا {{user_name}} {{reference_number}}'
        );

        $result = app(EmailTemplateService::class)->render('approved', [
            'user_name' => 'محمد',
            'request_reference' => 'YFH-2026-000001',
        ]);

        $this->assertSame('db', $result['source']);
        $this->assertSame('تمت الموافقة على YFH-2026-000001', $result['subject']);
        $this->assertStringContainsString('YFH-2026-000001', $result['body']);
        $this->assertArrayHasKey('text', $result);
        $this->assertArrayHasKey('template_version_id', $result);
    }

    public function test_missing_database_template_falls_back_to_blade(): void
    {
        $result = app(EmailTemplateService::class)->render('approved', [
            'requestModel' => $this->makeRequestModel(),
        ]);

        $this->assertSame('blade', $result['source']);
        $this->assertSame('تمت الموافقة على طلبكم - Yemen Flow Hub', $result['subject']);
        $this->assertStringContainsString('</html>', $result['body']);
    }

    private function createTemplateVersion(NotificationType $type, string $subject, string $body): void
    {
        $template = NotificationTemplate::query()->create([
            'notification_type' => $type,
        ]);

        $template->versions()->create([
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

            public ?object $creator = null;

            public function __construct()
            {
                $this->creator = (object) ['name' => 'مستخدم تجريبي'];
            }
        };
    }
}
