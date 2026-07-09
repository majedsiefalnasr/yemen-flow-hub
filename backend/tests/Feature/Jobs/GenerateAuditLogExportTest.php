<?php

namespace Tests\Feature\Jobs;

use App\Enums\AuditAction;
use App\Jobs\GenerateAuditLogExport;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditService;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

/**
 * Guards API-004: audit-log export runs async via GenerateAuditLogExport
 * instead of synchronously in the request, streams rows via lazy() instead
 * of holding all matching rows in memory, and preserves the same range/entity
 * filter behavior and CSV shape as the old synchronous export().
 */
class GenerateAuditLogExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);
        Storage::fake('private');

        $cbyOrg = Organization::where('code', 'national_committee')->first();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@auditexport.test',
            'password' => bcrypt('password'),
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $systemAdminRole = Role::query()->where('code', 'system_admin')->firstOrFail();
        $this->admin->roles()->attach($systemAdminRole->id);
    }

    private function logAt(string $createdAt, ?string $subjectType = null): AuditLog
    {
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::STATUS_TRANSITION->value,
            'subject_type' => $subjectType,
        ]);
        DB::table('audit_logs')->where('id', $log->id)->update(['created_at' => $createdAt]);

        return $log->fresh();
    }

    private function makeExport(array $filters = []): ReportExport
    {
        return ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'audit-logs',
            'filters' => $filters,
            'format' => 'csv',
            'status' => 'PENDING',
        ]);
    }

    public function test_job_completes_the_export_and_writes_a_csv_file(): void
    {
        $this->logAt('2026-03-10 10:00:00');
        $export = $this->makeExport();

        (new GenerateAuditLogExport($export->id))->handle(app(AuditService::class));

        $export->refresh();
        $this->assertSame('COMPLETED', $export->status);
        $this->assertNotNull($export->file_path);
        $this->assertSame(1, $export->exported_count);
        Storage::disk('private')->assertExists($export->file_path);
    }

    public function test_job_applies_the_same_range_and_entity_filters_as_the_old_sync_export(): void
    {
        $inRange = $this->logAt('2026-03-10 10:00:00', 'App\\Models\\EngineRequest');
        $this->logAt('2026-03-09 10:00:00', 'App\\Models\\EngineRequest');
        $this->logAt('2026-03-10 10:00:00', 'App\\Models\\EngineRequestDocument');

        $export = $this->makeExport([
            'from' => '2026-03-10',
            'to' => '2026-03-10',
            'entity' => 'App\\Models\\EngineRequest',
        ]);

        (new GenerateAuditLogExport($export->id))->handle(app(AuditService::class));

        $export->refresh();
        $this->assertSame(1, $export->exported_count);

        $csv = Storage::disk('private')->get($export->file_path);
        $this->assertStringContainsString((string) $inRange->id, $csv);
    }

    public function test_job_marks_export_failed_on_error(): void
    {
        $export = $this->makeExport();
        // Force a failure: point at a non-existent requester relation path by
        // deleting the requester after creation isn't representative, so
        // instead corrupt the export's own state to trigger the catch block
        // via an invalid filter type that breaks query building.
        $export->update(['filters' => ['from' => 'not-a-real-date']]);

        $this->expectException(Throwable::class);

        try {
            (new GenerateAuditLogExport($export->id))->handle(app(AuditService::class));
        } finally {
            $export->refresh();
            $this->assertSame('FAILED', $export->status);
            $this->assertNull($export->file_path);
        }
    }

    public function test_non_pending_export_is_skipped(): void
    {
        $export = $this->makeExport();
        $export->update(['status' => 'COMPLETED', 'file_path' => 'exports/already-done.csv']);

        (new GenerateAuditLogExport($export->id))->handle(app(AuditService::class));

        $export->refresh();
        $this->assertSame('COMPLETED', $export->status);
        $this->assertSame('exports/already-done.csv', $export->file_path);
    }
}
