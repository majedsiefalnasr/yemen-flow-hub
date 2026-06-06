<?php

namespace Tests\Unit\Mail;

use App\Enums\NotificationType;
use App\Mail\RequestApprovedMail;
use App\Mail\RequestRejectedMail;
use App\Mail\RequestReturnedMail;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\NotificationTemplate;
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

    public function test_request_approved_mail_uses_active_database_template(): void
    {
        $this->createTemplateVersion(
            NotificationType::REQUEST_APPROVED,
            'تم اعتماد طلبكم {{reference_number}}',
            'مرحبا {{user_name}}'
        );

        $mailable = new RequestApprovedMail($this->request);

        $this->assertStringContainsString($this->request->reference_number, $mailable->envelope()->subject);
        $this->assertNotNull($mailable->content()->htmlString);
        $this->assertNull($mailable->content()->view);
    }

    public function test_request_rejected_mail_uses_active_database_template(): void
    {
        $this->createTemplateVersion(
            NotificationType::REQUEST_REJECTED,
            'رُفض طلبكم {{reference_number}}',
            'مرفوض {{user_name}}'
        );

        $mailable = new RequestRejectedMail($this->request);

        $this->assertStringContainsString('رُفض طلبكم', $mailable->envelope()->subject);
        $this->assertNotNull($mailable->content()->htmlString);
        $this->assertNull($mailable->content()->view);
    }

    public function test_request_returned_mail_uses_active_database_template(): void
    {
        $this->createTemplateVersion(
            NotificationType::REQUEST_RETURNED,
            'إعادة طلبكم {{reference_number}}',
            'أُعيد {{user_name}}'
        );

        $mailable = new RequestReturnedMail($this->request);

        $this->assertStringContainsString('إعادة طلبكم', $mailable->envelope()->subject);
        $this->assertNotNull($mailable->content()->htmlString);
        $this->assertNull($mailable->content()->view);
    }

    public function test_request_mailables_fall_back_to_blade_when_database_template_missing(): void
    {
        $approved = new RequestApprovedMail($this->request);
        $rejected = new RequestRejectedMail($this->request);
        $returned = new RequestReturnedMail($this->request);

        $this->assertEquals('emails.request-approved', $approved->content()->view);
        $this->assertEquals('emails.request-rejected', $rejected->content()->view);
        $this->assertEquals('emails.request-returned', $returned->content()->view);
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
}
