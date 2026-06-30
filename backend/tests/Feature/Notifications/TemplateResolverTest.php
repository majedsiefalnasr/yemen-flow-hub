<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Models\NotificationTemplate;
use App\Services\Notifications\TemplateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_source_uses_active_database_version_when_present(): void
    {
        $version = $this->createTemplateVersion(NotificationType::REQUEST_APPROVED, 'DB subject', 'DB body');

        $resolved = app(TemplateResolver::class)->resolve(NotificationType::REQUEST_APPROVED);

        $this->assertSame('db', $resolved['source']);
        $this->assertSame('DB subject', $resolved['subject']);
        $this->assertSame('DB body', $resolved['body']);
        $this->assertSame($version->id, $resolved['template_version_id']);
    }

    public function test_db_source_falls_back_to_blade_when_active_database_version_is_missing(): void
    {
        $resolved = app(TemplateResolver::class)->resolve(NotificationType::REQUEST_APPROVED);

        $this->assertSame('blade', $resolved['source']);
        $this->assertSame('emails.request-approved', $resolved['view']);
        $this->assertSame('تمت الموافقة على طلبكم - The National Committee for Regulating & Financing Imports', $resolved['subject']);
        $this->assertNull($resolved['template_version_id']);
    }

    public function test_blade_source_bypasses_database_versions(): void
    {
        $this->createTemplateVersion(NotificationType::VOTING_OPENED, 'Wrong DB subject', 'Wrong DB body');

        $resolved = app(TemplateResolver::class)->resolve(NotificationType::VOTING_OPENED);

        $this->assertSame('blade', $resolved['source']);
        $this->assertSame('emails.voting-opened', $resolved['view']);
        $this->assertSame('تم فتح جلسة التصويت - The National Committee for Regulating & Financing Imports', $resolved['subject']);
        $this->assertNull($resolved['template_version_id']);
    }

    public function test_default_subjects_use_national_committee_identity(): void
    {
        $expectedSubjects = [
            NotificationType::REQUEST_APPROVED->value => 'تمت الموافقة على طلبكم - The National Committee for Regulating & Financing Imports',
            NotificationType::REQUEST_REJECTED->value => 'تم رفض طلبكم - The National Committee for Regulating & Financing Imports',
            NotificationType::REQUEST_RETURNED->value => 'تم إعادة طلبكم للتعديل - The National Committee for Regulating & Financing Imports',
            NotificationType::VOTING_OPENED->value => 'تم فتح جلسة التصويت - The National Committee for Regulating & Financing Imports',
            NotificationType::MFA_OTP->value => 'رمز التحقق متعدد العوامل - The National Committee for Regulating & Financing Imports',
            NotificationType::PASSWORD_RESET->value => 'رمز استعادة كلمة المرور - The National Committee for Regulating & Financing Imports',
        ];

        foreach (NotificationType::cases() as $type) {
            $resolved = app(TemplateResolver::class)->resolve($type);

            $this->assertSame($expectedSubjects[$type->value], $resolved['subject']);
            $this->assertStringNotContainsString('Yemen Flow Hub', $resolved['subject']);
        }
    }

    public function test_inactive_versions_are_ignored(): void
    {
        $template = NotificationTemplate::query()->create([
            'notification_type' => NotificationType::REQUEST_REJECTED,
        ]);

        $template->versions()->create([
            'subject' => 'Inactive subject',
            'body' => 'Inactive body',
            'is_active_version' => false,
        ]);

        $active = $template->versions()->create([
            'subject' => 'Active subject',
            'body' => 'Active body',
            'is_active_version' => true,
        ]);

        $resolved = app(TemplateResolver::class)->resolve(NotificationType::REQUEST_REJECTED);

        $this->assertSame('db', $resolved['source']);
        $this->assertSame('Active subject', $resolved['subject']);
        $this->assertSame('Active body', $resolved['body']);
        $this->assertSame($active->id, $resolved['template_version_id']);
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
}
