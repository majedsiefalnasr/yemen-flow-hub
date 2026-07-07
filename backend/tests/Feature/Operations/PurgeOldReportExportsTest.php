<?php

namespace Tests\Feature\Operations;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeOldReportExportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $cbyOrg = Organization::where('code', 'national_committee')->firstOrFail();

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
    }

    public function test_marks_completed_export_expired_and_deletes_file(): void
    {
        $path = 'exports/report-1.csv';
        Storage::disk('private')->put($path, 'csv');

        $export = ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'summary',
            'filters' => [],
            'status' => 'COMPLETED',
            'file_path' => $path,
        ]);
        $export->forceFill(['created_at' => now()->subDays(31)])->save();

        $this->artisan('reports:purge-old-exports')->assertSuccessful();

        $export->refresh();
        $this->assertSame('EXPIRED', $export->status);
        $this->assertNull($export->file_path);
        Storage::disk('private')->assertMissing($path);
    }

    public function test_skips_recent_completed_exports(): void
    {
        $path = 'exports/report-recent.csv';
        Storage::disk('private')->put($path, 'csv');

        $export = ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'summary',
            'filters' => [],
            'status' => 'COMPLETED',
            'file_path' => $path,
        ]);
        $export->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->artisan('reports:purge-old-exports')->assertSuccessful();

        $export->refresh();
        $this->assertSame('COMPLETED', $export->status);
        $this->assertSame($path, $export->file_path);
        Storage::disk('private')->assertExists($path);
    }

    public function test_skips_pending_processing_and_failed_exports(): void
    {
        $pending = ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'summary',
            'filters' => [],
            'status' => 'PENDING',
        ]);
        $pending->forceFill(['created_at' => now()->subDays(60)])->save();

        $failed = ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'summary',
            'filters' => [],
            'status' => 'FAILED',
        ]);
        $failed->forceFill(['created_at' => now()->subDays(60)])->save();

        $this->artisan('reports:purge-old-exports')->assertSuccessful();

        $this->assertSame('PENDING', $pending->fresh()->status);
        $this->assertSame('FAILED', $failed->fresh()->status);
    }

    public function test_idempotent_on_already_expired_export(): void
    {
        $export = ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'summary',
            'filters' => [],
            'status' => 'EXPIRED',
            'file_path' => null,
        ]);
        $export->forceFill(['created_at' => now()->subDays(60)])->save();

        $this->artisan('reports:purge-old-exports')->assertSuccessful();
        $this->artisan('reports:purge-old-exports')->assertSuccessful();

        $export->refresh();
        $this->assertSame('EXPIRED', $export->status);
        $this->assertNull($export->file_path);
    }

    public function test_records_scheduler_heartbeat(): void
    {
        $this->artisan('reports:purge-old-exports')->assertSuccessful();

        $this->assertDatabaseHas('scheduler_run_logs', [
            'command' => 'reports:purge-old-exports',
            'status' => 'success',
        ]);
    }
}
