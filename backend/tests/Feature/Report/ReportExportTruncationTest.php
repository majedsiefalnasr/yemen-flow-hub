<?php

namespace Tests\Feature\Report;

use App\Enums\UserRole;
use App\Jobs\GenerateReportExport;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Audit\AuditService;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportExportTruncationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private WorkflowVersion $version;

    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);
        Storage::fake('private');

        $cbyOrg = Organization::where('code', 'national_committee')->firstOrFail();
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-trunc@cby.gov',
            'password' => bcrypt('password'),
            'role' => UserRole::CBY_ADMIN,
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $systemAdminRole = Role::query()->where('code', 'system_admin')->firstOrFail();
        $this->admin->roles()->attach($systemAdminRole->id);

        $def = WorkflowDefinition::create(['code' => 'TRUNC_WF', 'name' => 'Trunc WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);
        $this->stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'INTAKE',
            'name' => 'Intake',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);
    }

    public function test_export_marks_truncated_when_rows_exceed_limit(): void
    {
        $this->seedEngineRequests(10001);

        $export = ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'summary',
            'filters' => ['status' => 'ACTIVE'],
            'status' => 'PENDING',
        ]);

        (new GenerateReportExport($export->id))->handle(app(AuditService::class));

        $export->refresh();
        $this->assertTrue($export->truncated);
        $this->assertSame(10000, $export->exported_count);
        $this->assertSame(10001, $export->total_matching);
        $this->assertNotNull($export->truncation_note);

        $csv = Storage::disk('private')->get($export->file_path);
        $this->assertStringContainsString('truncated', strtolower($csv));

        $this->actingAs($this->admin)
            ->getJson("/api/v1/reports/exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('data.truncated', true)
            ->assertJsonPath('data.exported_count', 10000)
            ->assertJsonPath('data.total_matching', 10001);
    }

    public function test_export_not_truncated_when_rows_within_limit(): void
    {
        $this->seedEngineRequests(5);

        $export = ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'summary',
            'filters' => [],
            'status' => 'PENDING',
        ]);

        (new GenerateReportExport($export->id))->handle(app(AuditService::class));

        $export->refresh();
        $this->assertFalse($export->truncated);
        $this->assertSame(5, $export->exported_count);
        $this->assertSame(5, $export->total_matching);
    }

    private function seedEngineRequests(int $count): void
    {
        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'workflow_version_id' => $this->version->id,
                'current_stage_id' => $this->stage->id,
                'reference' => 'TRUNC-'.$i,
                'status' => 'ACTIVE',
                'created_by' => $this->admin->id,
                'data' => json_encode([]),
                'version' => 1,
                'amount' => 1000,
                'currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                DB::table('engine_requests')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('engine_requests')->insert($rows);
        }
    }
}
