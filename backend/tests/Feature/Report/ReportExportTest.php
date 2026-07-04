<?php

namespace Tests\Feature\Report;

use App\Enums\UserRole;
use App\Jobs\GenerateReportExport;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $bankUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $cbyOrg = Organization::where('code', 'national_committee')->first();
        $bankOrg = Organization::where('code', 'commercial_banks')->first();
        $bank = Bank::create(['name' => 'Test', 'code' => 'TST', 'is_active' => true, 'organization_id' => $bankOrg->id]);

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

        $this->bankUser = User::create([
            'name' => 'Entry',
            'email' => 'entry@test.bank',
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
    }

    public function test_store_enqueues_export_job(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/reports/exports', [
                'report_type' => 'summary',
                'filters' => ['bank' => 1, 'from' => '2026-01-01'],
            ])
            ->assertCreated();

        $this->assertEquals('PENDING', $response->json('data.status'));
        $this->assertEquals('summary', $response->json('data.report_type'));

        Queue::assertPushed(GenerateReportExport::class);
    }

    public function test_store_forbidden_without_permission(): void
    {
        $this->actingAs($this->bankUser)
            ->postJson('/api/v1/reports/exports', ['report_type' => 'summary'])
            ->assertForbidden();
    }

    public function test_show_returns_export_status(): void
    {
        $export = ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'by-bank',
            'filters' => [],
            'status' => 'COMPLETED',
            'file_path' => 'exports/test.csv',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/reports/exports/{$export->id}")
            ->assertOk();

        $this->assertEquals('COMPLETED', $response->json('data.status'));
    }

    public function test_show_forbidden_for_other_users_export(): void
    {
        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@cby.gov',
            'password' => bcrypt('password'),
            'role' => UserRole::COMMITTEE_DIRECTOR,
            'organization_id' => $this->admin->organization_id,
            'is_active' => true,
        ]);

        $export = ReportExport::create([
            'requested_by' => $otherUser->id,
            'report_type' => 'summary',
            'filters' => [],
            'status' => 'PENDING',
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/reports/exports/{$export->id}")
            ->assertForbidden();
    }

    public function test_download_returns_422_when_not_ready(): void
    {
        $export = ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'summary',
            'filters' => [],
            'status' => 'PENDING',
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/reports/exports/{$export->id}/download")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'EXPORT_NOT_READY');
    }

    public function test_index_returns_only_own_exports(): void
    {
        ReportExport::create([
            'requested_by' => $this->admin->id,
            'report_type' => 'summary',
            'filters' => [],
            'status' => 'COMPLETED',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/exports')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }
}
