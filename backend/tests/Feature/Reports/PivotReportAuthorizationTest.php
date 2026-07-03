<?php

namespace Tests\Feature\Reports;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivotReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(BankSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_support_committee_can_access_workflow_report(): void
    {
        $support = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->firstOrFail();

        $response = $this->actingAs($support)->getJson('/api/reports/workflow');
        $response->assertOk();
    }

    public function test_data_entry_cannot_access_workflow_report(): void
    {
        $entry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        $response = $this->actingAs($entry)->getJson('/api/reports/workflow');
        $response->assertForbidden();
    }

    public function test_cby_admin_gets_cross_bank_breakdown_in_bank_report(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($admin)->getJson('/api/reports/bank');
        $response->assertOk();
        $this->assertArrayHasKey('per_bank', $response->json('data'));
    }

    public function test_swift_officer_cannot_access_bank_report(): void
    {
        $swift = User::query()->where('role', UserRole::SWIFT_OFFICER->value)->firstOrFail();

        $response = $this->actingAs($swift)->getJson('/api/reports/bank');
        $response->assertForbidden();
    }

    public function test_bank_admin_can_access_bank_report_scoped_to_own_bank(): void
    {
        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($bankAdmin)->getJson('/api/reports/bank');
        $response->assertOk();
        $this->assertArrayNotHasKey('per_bank', $response->json('data'));
    }
}
