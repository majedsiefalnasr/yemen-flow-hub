<?php

namespace Tests\Feature\Report;

use App\Enums\AuditAction;
use App\Enums\OrganizationClassification;
use App\Enums\UserRole;
use App\Jobs\GenerateReportExport;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WP7Task5Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $bankUser;
    private User $bankAdmin;
    private Bank $bank;
    private Bank $otherBank;
    private WorkflowVersion $version;
    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $cbyOrg = Organization::where('code', 'national_committee')->first();
        $bankOrg = Organization::where('code', 'commercial_banks')->first();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@cby.gov',
            'password' => bcrypt('password'),
            'role' => UserRole::CBY_ADMIN,
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $systemAdminRole = Role::query()->where('code', 'system_admin')->firstOrFail();
        $this->admin->roles()->attach($systemAdminRole->id);

        $this->bank = Bank::create(['name' => 'Test Bank', 'code' => 'TST', 'is_active' => true, 'organization_id' => $bankOrg->id]);
        $this->otherBank = Bank::create(['name' => 'Other Bank', 'code' => 'OTH', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $this->bankUser = User::create([
            'name' => 'Entry',
            'email' => 'entry@test.bank',
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);

        $this->bankAdmin = User::create([
            'name' => 'Bank Admin',
            'email' => 'admin@test.bank',
            'password' => bcrypt('password'),
            'role' => UserRole::BANK_ADMIN,
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $bankAdminRole = Role::query()->where('code', 'bank_admin')->firstOrFail();
        $this->bankAdmin->roles()->attach($bankAdminRole->id);

        $def = WorkflowDefinition::create(['code' => 'IMPORT', 'name' => 'Import', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'published_by' => $this->admin->id,
            'published_at' => now(),
        ]);
        $this->stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'INTAKE',
            'name' => 'Intake',
            'order' => 1,
            'is_initial' => true,
        ]);
    }

    private function createRequest(array $overrides = []): EngineRequest
    {
        return EngineRequest::create(array_merge([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-'.rand(1000, 9999),
            'status' => 'ACTIVE',
            'created_by' => $this->bankUser->id,
            'bank_id' => $this->bank->id,
            'data' => [],
            'version' => 1,
            'amount' => 10000,
            'currency' => 'USD',
        ], $overrides));
    }

    public function test_bank_admin_can_view_reports_but_not_export(): void
    {
        // Bank admin has reports:VIEW but NOT reports:EXPORT per ScreenPermissionSeeder
        $this->actingAs($this->bankAdmin)
            ->getJson('/api/v1/reports/summary')
            ->assertOk();

        $this->actingAs($this->bankAdmin)
            ->postJson('/api/v1/reports/exports', ['report_type' => 'summary'])
            ->assertForbidden();
    }

    public function test_bank_admin_reports_are_scoped_to_own_bank(): void
    {
        $this->createRequest(['bank_id' => $this->bank->id]);
        $this->createRequest(['bank_id' => $this->otherBank->id]);

        $response = $this->actingAs($this->bankAdmin)
            ->getJson('/api/v1/reports/summary')
            ->assertOk();

        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_national_committee_reports_are_system_wide(): void
    {
        $this->createRequest(['bank_id' => $this->bank->id]);
        $this->createRequest(['bank_id' => $this->otherBank->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/summary')
            ->assertOk();

        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_export_creation_is_audited(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->postJson('/api/v1/reports/exports', [
                'report_type' => 'summary',
                'filters' => ['status' => 'ACTIVE'],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::REPORT_EXPORT_CREATED->value,
            'user_id' => $this->admin->id,
        ]);

        $log = AuditLog::where('action', AuditAction::REPORT_EXPORT_CREATED->value)->first();
        $this->assertEquals(OrganizationClassification::NATIONAL_COMMITTEE->value, $log->metadata['classification']);
        $this->assertEquals('summary', $log->metadata['report_type']);
        $this->assertEquals(['status' => 'ACTIVE'], $log->metadata['filters']);
    }

    public function test_export_download_is_audited(): void
    {
        Storage::fake('private');
        $path = 'exports/test.csv';
        Storage::disk('private')->put($path, 'test');

        $export = ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'summary',
            'filters' => [],
            'status' => 'COMPLETED',
            'file_path' => $path,
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/reports/exports/{$export->id}/download")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::REPORT_EXPORT_DOWNLOADED->value,
            'user_id' => $this->admin->id,
        ]);

        $log = AuditLog::where('action', AuditAction::REPORT_EXPORT_DOWNLOADED->value)->first();
        $this->assertEquals($export->id, $log->metadata['export_id']);
    }

    public function test_generate_report_export_job_respects_requester_scope(): void
    {
        Storage::fake('private');
        $this->createRequest(['bank_id' => $this->bank->id, 'reference' => 'OUR-REF']);
        $this->createRequest(['bank_id' => $this->otherBank->id, 'reference' => 'THEIR-REF']);

        // Create export for bank admin
        $export = ReportExport::create([
            'requested_by' => $this->bankAdmin->id,
            'report_type' => 'summary',
            'filters' => [],
            'status' => 'PENDING',
        ]);

        $job = new GenerateReportExport($export->id);
        app()->call([$job, 'handle']);

        $export->refresh();
        $this->assertEquals('COMPLETED', $export->status);
        $csv = Storage::disk('private')->get($export->file_path);

        $this->assertStringContainsString('OUR-REF', $csv);
        $this->assertStringNotContainsString('THEIR-REF', $csv);

        // Audit log for completion should also be detailed
        $log = AuditLog::where('action', AuditAction::REPORT_EXPORTED->value)->first();
        $this->assertEquals(1, $log->metadata['row_count']);
        $this->assertEquals(OrganizationClassification::BANKING_SECTOR->value, $log->metadata['classification']);
    }
}
