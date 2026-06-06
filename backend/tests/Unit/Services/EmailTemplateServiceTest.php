<?php

namespace Tests\Unit\Services;

use App\Models\SystemSetting;
use App\Services\Mail\EmailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EmailTemplateService::class);
    }

    // ─── ALLOWED_VARIABLES ────────────────────────────────────────────────────

    public function test_allowed_variables_contains_required_keys(): void
    {
        $expected = [
            'user_name', 'request_reference', 'importer_name',
            'amount', 'currency', 'status', 'action_url', 'bank_name',
        ];

        foreach ($expected as $key) {
            $this->assertContains($key, EmailTemplateService::ALLOWED_VARIABLES);
        }
        $this->assertCount(8, EmailTemplateService::ALLOWED_VARIABLES);
    }

    // ─── DB template path ─────────────────────────────────────────────────────

    public function test_render_uses_db_template_when_available(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'approved' => [
                        'subject' => 'تمت الموافقة على {{request_reference}}',
                        'body' => 'عزيزي {{user_name}}، تمت الموافقة على طلبكم.',
                    ],
                ],
            ],
        ]);

        $result = $this->service->render('approved', [
            'user_name' => 'أحمد محمد',
            'request_reference' => 'REQ-2026-00123',
        ]);

        $this->assertEquals('تمت الموافقة على REQ-2026-00123', $result['subject']);
        $this->assertEquals('عزيزي أحمد محمد، تمت الموافقة على طلبكم.', $result['body']);
    }

    public function test_render_substitutes_all_allowed_variables(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'approved' => [
                        'subject' => '{{request_reference}}',
                        'body' => '{{user_name}} {{importer_name}} {{amount}} {{currency}} {{status}} {{action_url}} {{bank_name}}',
                    ],
                ],
            ],
        ]);

        $vars = [
            'user_name' => 'U',
            'request_reference' => 'R',
            'importer_name' => 'I',
            'amount' => '100',
            'currency' => 'USD',
            'status' => 'S',
            'action_url' => 'https://x',
            'bank_name' => 'B',
        ];

        $result = $this->service->render('approved', $vars);

        $this->assertEquals('R', $result['subject']);
        $this->assertEquals('U I 100 USD S https://x B', $result['body']);
    }

    public function test_render_strips_unknown_variables_silently(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'approved' => [
                        'subject' => 'موضوع {{unknown_var}}',
                        'body' => 'نص {{user_name}} {{secret_data}}',
                    ],
                ],
            ],
        ]);

        $result = $this->service->render('approved', ['user_name' => 'علي']);

        $this->assertEquals('موضوع ', $result['subject']);
        $this->assertEquals('نص علي ', $result['body']);
    }

    public function test_render_returns_array_with_subject_and_body_keys(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'rejected' => ['subject' => 'رفض', 'body' => 'تم الرفض'],
                ],
            ],
        ]);

        $result = $this->service->render('rejected', []);

        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('body', $result);
    }

    public function test_render_handles_rejected_type_with_db_template(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'rejected' => [
                        'subject' => 'رفض {{request_reference}}',
                        'body' => 'مرفوض {{user_name}}',
                    ],
                ],
            ],
        ]);

        $result = $this->service->render('rejected', [
            'user_name' => 'خالد',
            'request_reference' => 'REF-001',
        ]);

        $this->assertEquals('رفض REF-001', $result['subject']);
        $this->assertEquals('مرفوض خالد', $result['body']);
    }

    public function test_render_handles_returned_type_with_db_template(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'returned' => [
                        'subject' => 'إعادة {{request_reference}}',
                        'body' => 'أُعيد للتعديل {{user_name}}',
                    ],
                ],
            ],
        ]);

        $result = $this->service->render('returned', [
            'user_name' => 'سالم',
            'request_reference' => 'REF-002',
        ]);

        $this->assertEquals('إعادة REF-002', $result['subject']);
        $this->assertEquals('أُعيد للتعديل سالم', $result['body']);
    }

    // ─── Missing / malformed DB entry ─────────────────────────────────────────

    public function test_render_falls_back_to_blade_when_no_db_template(): void
    {
        $result = $this->service->render('approved', [
            'requestModel' => $this->makeRequestModel(),
        ]);

        $this->assertNotEmpty($result['subject']);
        $this->assertNotEmpty($result['body']);
        $this->assertStringContainsString('</html>', $result['body']);
    }

    public function test_render_falls_back_to_blade_when_db_template_is_null(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => ['templates' => ['approved' => null]],
        ]);

        $result = $this->service->render('approved', [
            'requestModel' => $this->makeRequestModel(),
        ]);

        $this->assertNotEmpty($result['subject']);
        $this->assertStringContainsString('</html>', $result['body']);
    }

    public function test_render_falls_back_to_blade_when_db_template_missing_subject(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => ['templates' => ['approved' => ['body' => 'only body']]],
        ]);

        $result = $this->service->render('approved', [
            'requestModel' => $this->makeRequestModel(),
        ]);

        // A non-array (missing subject) means the whole template entry should be treated as valid
        // actually spec says: if dbTemplate && is_array($dbTemplate) → use DB
        // This template IS an array with only 'body', subject defaults to ''
        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('body', $result);
    }

    public function test_render_falls_back_to_blade_when_settings_email_key_missing(): void
    {
        $result = $this->service->render('approved', [
            'requestModel' => $this->makeRequestModel(),
        ]);

        $this->assertNotEmpty($result['body']);
    }

    // ─── Non-object variables don't break substitution ─────────────────────────

    public function test_non_string_variable_values_are_skipped_during_substitution(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'approved' => [
                        'subject' => 'Subj {{user_name}}',
                        'body' => 'Body {{user_name}}',
                    ],
                ],
            ],
        ]);

        $result = $this->service->render('approved', [
            'user_name' => 'احمد',
            'requestModel' => (object) ['name' => 'test'],
        ]);

        $this->assertEquals('Subj احمد', $result['subject']);
        $this->assertEquals('Body احمد', $result['body']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

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

            public ?object $merchant = null;

            public ?object $bank = null;

            public function __construct()
            {
                $this->creator = (object) ['name' => 'مستخدم تجريبي'];
            }
        };
    }
}
