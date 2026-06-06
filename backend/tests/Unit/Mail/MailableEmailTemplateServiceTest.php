<?php

namespace Tests\Unit\Mail;

use App\Mail\RequestApprovedMail;
use App\Mail\RequestRejectedMail;
use App\Mail\RequestReturnedMail;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MailableEmailTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private ImportRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $bank = Bank::query()->create(['name' => 'Test Bank', 'code' => 'TB01', 'is_active' => true]);

        $creator = User::query()->create([
            'name' => 'محمد علي',
            'email' => 'creator@test.com',
            'password' => Hash::make('password'),
            'role' => 'DATA_ENTRY',
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);

        app()->instance('workflow.transition.active', true);
        try {
            $this->request = ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 50000.00,
                'supplier_name' => 'مورد تجريبي',
                'goods_description' => 'بضائع متنوعة',
                'port_of_entry' => 'ميناء عدن',
                'status' => 'SUBMITTED',
                'current_owner_role' => 'BANK_REVIEWER',
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    // ─── RequestApprovedMail ──────────────────────────────────────────────────

    public function test_approved_mail_uses_db_template_subject_when_available(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'approved' => [
                        'subject' => 'تم اعتماد طلبكم {{request_reference}}',
                        'body' => '<p>مرحبا {{user_name}}</p>',
                    ],
                ],
            ],
        ]);

        $mailable = new RequestApprovedMail($this->request);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('تم اعتماد طلبكم', $envelope->subject);
        $this->assertStringContainsString($this->request->reference_number, $envelope->subject);
    }

    public function test_approved_mail_uses_blade_subject_when_no_db_template(): void
    {
        $mailable = new RequestApprovedMail($this->request);
        $envelope = $mailable->envelope();

        $this->assertNotEmpty($envelope->subject);
        $this->assertStringContainsString('Yemen Flow Hub', $envelope->subject);
    }

    public function test_approved_mail_content_uses_html_string_when_db_template_available(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'approved' => [
                        'subject' => 'موافقة',
                        'body' => '<p>مرحبا {{user_name}}</p>',
                    ],
                ],
            ],
        ]);

        $mailable = new RequestApprovedMail($this->request);
        $content = $mailable->content();

        $this->assertNotNull($content->htmlString);
        $this->assertNull($content->view);
        $this->assertStringContainsString('<p>مرحبا', $content->htmlString);
    }

    public function test_approved_mail_content_uses_blade_view_when_no_db_template(): void
    {
        $mailable = new RequestApprovedMail($this->request);
        $content = $mailable->content();

        $this->assertNull($content->htmlString);
        $this->assertEquals('emails.request-approved', $content->view);
    }

    // ─── RequestRejectedMail ──────────────────────────────────────────────────

    public function test_rejected_mail_uses_db_template_subject_when_available(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'rejected' => [
                        'subject' => 'رُفض طلبكم {{request_reference}}',
                        'body' => '<p>عزيزي {{user_name}}</p>',
                    ],
                ],
            ],
        ]);

        $mailable = new RequestRejectedMail($this->request);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('رُفض طلبكم', $envelope->subject);
    }

    public function test_rejected_mail_uses_blade_subject_when_no_db_template(): void
    {
        $mailable = new RequestRejectedMail($this->request);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('Yemen Flow Hub', $envelope->subject);
    }

    public function test_rejected_mail_content_uses_html_string_when_db_template_available(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'rejected' => [
                        'subject' => 'رفض',
                        'body' => '<p>مرفوض {{user_name}}</p>',
                    ],
                ],
            ],
        ]);

        $mailable = new RequestRejectedMail($this->request);
        $content = $mailable->content();

        $this->assertNotNull($content->htmlString);
        $this->assertNull($content->view);
    }

    public function test_rejected_mail_content_uses_blade_view_when_no_db_template(): void
    {
        $mailable = new RequestRejectedMail($this->request);
        $content = $mailable->content();

        $this->assertNull($content->htmlString);
        $this->assertEquals('emails.request-rejected', $content->view);
    }

    // ─── RequestReturnedMail ─────────────────────────────────────────────────

    public function test_returned_mail_uses_db_template_subject_when_available(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'returned' => [
                        'subject' => 'إعادة طلبكم {{request_reference}}',
                        'body' => '<p>أُعيد {{user_name}}</p>',
                    ],
                ],
            ],
        ]);

        $mailable = new RequestReturnedMail($this->request);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('إعادة طلبكم', $envelope->subject);
    }

    public function test_returned_mail_uses_blade_subject_when_no_db_template(): void
    {
        $mailable = new RequestReturnedMail($this->request);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('Yemen Flow Hub', $envelope->subject);
    }

    public function test_returned_mail_content_uses_html_string_when_db_template_available(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'returned' => [
                        'subject' => 'إعادة',
                        'body' => '<p>أُعيد {{user_name}}</p>',
                    ],
                ],
            ],
        ]);

        $mailable = new RequestReturnedMail($this->request);
        $content = $mailable->content();

        $this->assertNotNull($content->htmlString);
        $this->assertNull($content->view);
    }

    public function test_returned_mail_content_uses_blade_view_when_no_db_template(): void
    {
        $mailable = new RequestReturnedMail($this->request);
        $content = $mailable->content();

        $this->assertNull($content->htmlString);
        $this->assertEquals('emails.request-returned', $content->view);
    }

    // ─── Variable substitution in subject ────────────────────────────────────

    public function test_approved_mail_subject_has_variables_substituted(): void
    {
        SystemSetting::create([
            'key' => 'settings.email',
            'value' => [
                'templates' => [
                    'approved' => [
                        'subject' => 'موافقة {{request_reference}} - {{bank_name}}',
                        'body' => 'نص',
                    ],
                ],
            ],
        ]);

        $mailable = new RequestApprovedMail($this->request);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString($this->request->reference_number, $envelope->subject);
        $this->assertStringNotContainsString('{{', $envelope->subject);
    }
}
