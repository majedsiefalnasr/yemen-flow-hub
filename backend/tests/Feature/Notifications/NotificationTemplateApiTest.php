<?php

namespace Tests\Feature\Notifications;

use App\Enums\AuditAction;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class NotificationTemplateApiTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    private User $admin;

    private User $bankUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NotificationTemplateSeeder::class);
        $this->seedGovernance();

        $this->admin = $this->makeUser(UserRole::CBY_ADMIN, 'admin@cby.ye');
        $this->bankUser = $this->makeUser(UserRole::BANK_REVIEWER, 'reviewer@bank.ye');
    }

    public function test_only_cby_admin_can_access_template_api(): void
    {
        $this->actingAs($this->bankUser)
            ->getJson('/api/admin/notification-templates')
            ->assertStatus(403);

        $this->actingAs($this->bankUser)
            ->getJson('/api/admin/notification-templates/REQUEST_APPROVED')
            ->assertStatus(403);

        $this->actingAs($this->bankUser)
            ->putJson('/api/admin/notification-templates/REQUEST_APPROVED', [
                'subject' => 'x',
                'body' => 'y',
            ])
            ->assertStatus(403);

        $this->actingAs($this->bankUser)
            ->postJson('/api/admin/notification-templates/REQUEST_APPROVED/preview', [
                'subject' => 'x',
                'body' => 'y',
            ])
            ->assertStatus(403);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/notification-templates')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_index_lists_only_admin_editable_templates(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/notification-templates')
            ->assertOk();

        $types = collect($response->json('data'))->pluck('type')->all();

        $this->assertEqualsCanonicalizing([
            NotificationType::REQUEST_APPROVED->value,
            NotificationType::REQUEST_REJECTED->value,
            NotificationType::REQUEST_RETURNED->value,
        ], $types);
        $this->assertNotContains(NotificationType::VOTING_OPENED->value, $types);
        $response->assertJsonPath('data.0.admin_editable', true);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'type',
                    'allowed_variables',
                    'active' => ['subject', 'body'],
                    'versions' => [
                        '*' => ['id', 'changed_by', 'changed_by_name', 'changed_at', 'is_active_version'],
                    ],
                ],
            ],
        ]);
    }

    public function test_non_editable_types_are_not_editable_or_previewable(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/notification-templates/'.NotificationType::MFA_OTP->value)
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->putJson('/api/admin/notification-templates/'.NotificationType::MFA_OTP->value, [
                'subject' => 'رمز',
                'body' => 'رمز {{otp_code}}',
            ])
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->postJson('/api/admin/notification-templates/'.NotificationType::MFA_OTP->value.'/preview', [
                'subject' => 'رمز',
                'body' => 'رمز {{otp_code}}',
            ])
            ->assertNotFound();
    }

    public function test_update_creates_new_active_version_and_audit_without_body(): void
    {
        $template = NotificationTemplate::query()
            ->where('notification_type', NotificationType::REQUEST_APPROVED->value)
            ->with('activeVersion')
            ->firstOrFail();
        $oldVersionId = $template->activeVersion->id;

        $response = $this->actingAs($this->admin)
            ->putJson('/api/admin/notification-templates/REQUEST_APPROVED', [
                'subject' => ' موافقة {{reference_number}} ',
                'body' => '<p>مرحبا {{user_name}}</p> **{{reference_number}}**',
            ])
            ->assertOk();

        $response->assertJsonPath('data.active.subject', 'موافقة {{reference_number}}');
        $response->assertJsonPath('data.active.body', 'مرحبا {{user_name}} **{{reference_number}}**');
        $response->assertJsonMissingPath('data.versions.0.subject');
        $response->assertJsonMissingPath('data.versions.0.body');

        $template->refresh()->load('activeVersion');
        $this->assertNotSame($oldVersionId, $template->activeVersion->id);
        $this->assertSame($this->admin->id, $template->activeVersion->changed_by);
        $this->assertDatabaseHas('notification_template_versions', [
            'id' => $oldVersionId,
            'is_active_version' => false,
        ]);

        $log = AuditLog::query()
            ->where('action', AuditAction::EMAIL_TEMPLATE_UPDATED->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(NotificationType::REQUEST_APPROVED->value, $log->metadata['template_type']);
        $this->assertSame($this->admin->id, $log->metadata['changed_by']);
        $this->assertArrayNotHasKey('body', $log->metadata);
        $this->assertArrayNotHasKey('subject', $log->metadata);
    }

    public function test_update_rejects_unknown_template_variables(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/api/admin/notification-templates/REQUEST_APPROVED', [
                'subject' => 'موافقة {{reference_number}}',
                'body' => 'سر {{secret_token}}',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }

    public function test_preview_returns_sanitized_source_and_multipart_rendered_sample(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/notification-templates/REQUEST_APPROVED/preview', [
                'subject' => ' <b>موافقة</b> {{reference_number}} ',
                'body' => '<script>alert("x")</script>تمت الموافقة على **{{reference_number}}** لدى {{bank_name}} بمبلغ {{amount}} {{currency}}.',
            ])
            ->assertOk();

        $response->assertJsonPath('data.source.subject', 'موافقة {{reference_number}}');
        $response->assertJsonPath(
            'data.source.body',
            'alert("x")تمت الموافقة على **{{reference_number}}** لدى {{bank_name}} بمبلغ {{amount}} {{currency}}.'
        );
        $response->assertJsonPath('data.rendered.subject', 'موافقة YFH-2026-000123');
        $response->assertJsonPath('data.rendered.source', 'preview');
        $response->assertJsonPath('data.rendered.template_version_id', null);
        $this->assertStringContainsString('dir="rtl"', $response->json('data.rendered.html'));
        $this->assertStringContainsString('<strong>YFH-2026-000123</strong>', $response->json('data.rendered.html'));
        $this->assertStringContainsString('Yemen International Bank', $response->json('data.rendered.text'));
        $this->assertStringContainsString('1,000,000 USD', $response->json('data.rendered.text'));
    }

    private function makeUser(UserRole $role, string $email): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => $role->value,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'bank_id' => null,
            'is_active' => true,
        ]), $role);
    }
}
