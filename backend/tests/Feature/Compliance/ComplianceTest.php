<?php

namespace Tests\Feature\Compliance;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $bankUser;

    private Bank $bank;

    private WorkflowVersion $version;

    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class]);

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

        $this->bank = Bank::create(['name' => 'Test Bank', 'code' => 'TST', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $this->bankUser = User::create([
            'name' => 'Entry',
            'email' => 'entry@test.bank',
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);

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
            'sla_duration_minutes' => 60,
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

    public function test_duplicate_invoices_lists_duplicates(): void
    {
        $this->createRequest(['invoice_number' => 'INV-001']);
        $this->createRequest(['invoice_number' => 'INV-001']);
        $this->createRequest(['invoice_number' => 'INV-002']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/compliance/duplicate-invoices')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('INV-001', $response->json('data.0.invoice_number'));
        $this->assertEquals(2, $response->json('data.0.count'));
    }

    public function test_duplicate_invoices_forbidden_without_permission(): void
    {
        $this->actingAs($this->bankUser)
            ->getJson('/api/v1/compliance/duplicate-invoices')
            ->assertForbidden();
    }

    public function test_expired_documents_lists_expired_merchants(): void
    {
        Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Expired Merchant',
            'tax_number' => '111',
            'tax_card_expiry' => now()->subMonth(),
            'status' => 'ACTIVE',
            'version' => 1,
        ]);
        Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Valid Merchant',
            'tax_number' => '222',
            'tax_card_expiry' => now()->addYear(),
            'status' => 'ACTIVE',
            'version' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/compliance/expired-documents')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Expired Merchant', $response->json('data.0.merchant_name'));
        $this->assertEquals('tax_card', $response->json('data.0.expired_documents.0.type'));
    }

    public function test_sla_breaches_lists_breached_requests(): void
    {
        $request = $this->createRequest();

        // Simulate time-in-stage > SLA by inserting a history entry far in the past
        WorkflowHistoryEntry::create([
            'request_id' => $request->id,
            'from_stage_id' => null,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->bankUser->id,
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/compliance/sla-breaches')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($request->id, $response->json('data.0.id'));
        $this->assertEquals('breached', $response->json('data.0.sla_status'));
    }

    public function test_sla_breaches_excludes_non_breached(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/compliance/sla-breaches')
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_sla_breaches_scope_respected(): void
    {
        $this->actingAs($this->bankUser)
            ->getJson('/api/v1/compliance/sla-breaches')
            ->assertForbidden();
    }

    public function test_no_speculative_fraud_indicators(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/compliance/duplicate-invoices')
            ->assertOk();

        $this->assertArrayNotHasKey('fraud_score', $response->json());
        $this->assertArrayNotHasKey('risk_indicators', $response->json());
    }
}
