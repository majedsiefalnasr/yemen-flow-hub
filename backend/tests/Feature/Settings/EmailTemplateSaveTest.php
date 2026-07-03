<?php

namespace Tests\Feature\Settings;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class EmailTemplateSaveTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
        $this->admin = $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'CBY Admin',
            'email' => 'admin@cby.ye',
            'password' => Hash::make('password'),
            'role' => UserRole::CBY_ADMIN,
            'bank_id' => null,
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);
    }

    // ─── normalizeSectionData email branch ────────────────────────────────────

    public function test_save_email_section_stores_templates_structure(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/settings/save-section', [
            'section' => 'email',
            'data' => [
                'templates' => [
                    'approved' => ['subject' => 'موضوع الموافقة', 'body' => 'نص الموافقة'],
                    'rejected' => ['subject' => 'موضوع الرفض', 'body' => 'نص الرفض'],
                    'returned' => ['subject' => 'موضوع الإعادة', 'body' => 'نص الإعادة'],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.key', 'settings.email');

        $setting = SystemSetting::findByKey('settings.email');
        $this->assertNotNull($setting);
        $this->assertEquals('موضوع الموافقة', $setting->value['templates']['approved']['subject']);
        $this->assertEquals('نص الموافقة', $setting->value['templates']['approved']['body']);
        $this->assertEquals('موضوع الرفض', $setting->value['templates']['rejected']['subject']);
        $this->assertEquals('موضوع الإعادة', $setting->value['templates']['returned']['subject']);
    }

    public function test_save_email_section_trims_whitespace(): void
    {
        $this->actingAs($this->admin)->postJson('/api/settings/save-section', [
            'section' => 'email',
            'data' => [
                'templates' => [
                    'approved' => ['subject' => '  موضوع  ', 'body' => '  نص  '],
                ],
            ],
        ])->assertStatus(200);

        $setting = SystemSetting::findByKey('settings.email');
        $this->assertEquals('موضوع', $setting->value['templates']['approved']['subject']);
        $this->assertEquals('نص', $setting->value['templates']['approved']['body']);
    }

    public function test_save_email_section_preserves_variable_placeholders(): void
    {
        $subject = 'الموافقة على {{request_reference}}';
        $body = 'عزيزي {{user_name}}، تمت الموافقة على طلبكم {{request_reference}}.';

        $this->actingAs($this->admin)->postJson('/api/settings/save-section', [
            'section' => 'email',
            'data' => [
                'templates' => [
                    'approved' => compact('subject', 'body'),
                ],
            ],
        ])->assertStatus(200);

        $setting = SystemSetting::findByKey('settings.email');
        $this->assertEquals($subject, $setting->value['templates']['approved']['subject']);
        $this->assertEquals($body, $setting->value['templates']['approved']['body']);
    }

    public function test_save_email_section_rejects_non_string_subject(): void
    {
        $this->actingAs($this->admin)->postJson('/api/settings/save-section', [
            'section' => 'email',
            'data' => [
                'templates' => [
                    'approved' => ['subject' => ['nested' => 'array'], 'body' => 'valid body'],
                ],
            ],
        ])->assertStatus(200);

        $setting = SystemSetting::findByKey('settings.email');
        $this->assertEquals('', $setting->value['templates']['approved']['subject']);
        $this->assertEquals('valid body', $setting->value['templates']['approved']['body']);
    }

    public function test_save_email_section_ignores_unknown_template_types(): void
    {
        $this->actingAs($this->admin)->postJson('/api/settings/save-section', [
            'section' => 'email',
            'data' => [
                'templates' => [
                    'approved' => ['subject' => 'موافقة', 'body' => 'تمت'],
                    'unknown_type' => ['subject' => 'شيء', 'body' => 'ما'],
                ],
            ],
        ])->assertStatus(200);

        $setting = SystemSetting::findByKey('settings.email');
        $this->assertArrayNotHasKey('unknown_type', $setting->value['templates']);
        $this->assertArrayHasKey('approved', $setting->value['templates']);
    }

    public function test_save_email_section_handles_missing_templates_key(): void
    {
        $this->actingAs($this->admin)->postJson('/api/settings/save-section', [
            'section' => 'email',
            'data' => ['templates' => []],
        ])->assertStatus(200);

        $setting = SystemSetting::findByKey('settings.email');
        $this->assertEquals([], $setting->value['templates']);
    }

    // ─── Audit logging ────────────────────────────────────────────────────────

    public function test_save_email_section_logs_email_template_updated_audit_entry(): void
    {
        $this->actingAs($this->admin)->postJson('/api/settings/save-section', [
            'section' => 'email',
            'data' => [
                'templates' => [
                    'approved' => ['subject' => 'موافقة', 'body' => 'تمت الموافقة'],
                    'rejected' => ['subject' => 'رفض', 'body' => 'تم الرفض'],
                ],
            ],
        ])->assertStatus(200);

        $logs = AuditLog::query()
            ->where('action', AuditAction::EMAIL_TEMPLATE_UPDATED->value)
            ->get();

        $this->assertCount(2, $logs);

        $types = $logs->pluck('metadata')->map(fn ($m) => $m['template_type'])->all();
        $this->assertContains('approved', $types);
        $this->assertContains('rejected', $types);
    }

    public function test_save_email_section_audit_entry_contains_changed_by_not_body(): void
    {
        $this->actingAs($this->admin)->postJson('/api/settings/save-section', [
            'section' => 'email',
            'data' => [
                'templates' => [
                    'approved' => ['subject' => 'موافقة', 'body' => 'محتوى سري'],
                ],
            ],
        ])->assertStatus(200);

        $log = AuditLog::query()
            ->where('action', AuditAction::EMAIL_TEMPLATE_UPDATED->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('approved', $log->metadata['template_type']);
        $this->assertEquals($this->admin->id, $log->metadata['changed_by']);
        $this->assertArrayNotHasKey('body', $log->metadata);
        $this->assertArrayNotHasKey('subject', $log->metadata);
    }

    public function test_save_email_section_also_logs_generic_settings_updated(): void
    {
        $this->actingAs($this->admin)->postJson('/api/settings/save-section', [
            'section' => 'email',
            'data' => [
                'templates' => [
                    'approved' => ['subject' => 'موافقة', 'body' => 'نص'],
                ],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SETTINGS_UPDATED->value,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_non_cby_admin_cannot_save_email_section(): void
    {
        $bankUser = User::query()->create([
            'name' => 'Bank User',
            'email' => 'bank@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::BANK_REVIEWER,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($bankUser)->postJson('/api/settings/save-section', [
            'section' => 'email',
            'data' => ['templates' => ['approved' => ['subject' => 'x', 'body' => 'y']]],
        ])->assertStatus(403);

        $this->assertDatabaseMissing('system_settings', ['key' => 'settings.email']);
    }
}
