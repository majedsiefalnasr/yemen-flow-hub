<?php

namespace Tests\Feature\Engine;

use App\Models\CustomsDeclaration;
use App\Models\EngineNotification;
use App\Models\ReportExport;
use App\Models\SystemSetting;
use Database\Seeders\BankSeeder;
use Database\Seeders\DemoSystemSettingsSeeder;
use Database\Seeders\EngineAuxiliaryDemoSeeder;
use Database\Seeders\EngineRequestAnchorSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ImportFinancingWorkflowSeeder;
use Database\Seeders\MerchantSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkflowActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EngineAuxiliaryDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            GovernanceSeeder::class,
            ReferenceDataSeeder::class,
            WorkflowActionSeeder::class,
            ImportFinancingWorkflowSeeder::class,
            BankSeeder::class,
            UserSeeder::class,
            MerchantSeeder::class,
            NotificationTemplateSeeder::class,
            EngineRequestAnchorSeeder::class,
        ]);
    }

    public function test_seeds_fx_confirmations_for_completed_anchors(): void
    {
        $this->seed(EngineAuxiliaryDemoSeeder::class);

        $this->assertSame(2, CustomsDeclaration::query()->whereNotNull('engine_request_id')->count());
    }

    public function test_seeds_notifications_for_hook_anchors(): void
    {
        $this->seed(EngineAuxiliaryDemoSeeder::class);

        $this->assertGreaterThanOrEqual(4, EngineNotification::query()->count());
    }

    public function test_seeds_email_deliveries(): void
    {
        $this->seed(EngineAuxiliaryDemoSeeder::class);

        $this->assertSame(3, DB::table('email_deliveries')->count());
    }

    public function test_seeds_report_exports_with_completed_truncated_failed_states(): void
    {
        $this->seed(EngineAuxiliaryDemoSeeder::class);

        $this->assertDatabaseHas('report_exports', ['status' => 'completed']);
        $this->assertDatabaseHas('report_exports', ['status' => 'truncated']);
        $this->assertDatabaseHas('report_exports', ['status' => 'failed']);
    }

    public function test_rerun_is_idempotent(): void
    {
        $this->seed(EngineAuxiliaryDemoSeeder::class);
        $this->seed(EngineAuxiliaryDemoSeeder::class);

        $this->assertSame(2, CustomsDeclaration::query()->whereNotNull('engine_request_id')->count());
        $this->assertSame(3, DB::table('email_deliveries')->count());
        $this->assertSame(3, ReportExport::query()->count());
    }

    public function test_demo_system_settings_seeder_sets_scan_enforcement(): void
    {
        $this->seed(DemoSystemSettingsSeeder::class);

        $setting = SystemSetting::query()->where('key', 'document_scan_enforced')->firstOrFail();

        $this->assertTrue((bool) $setting->value);
    }
}
