<?php

namespace Tests\Feature\Compliance;

use App\Enums\OrganizationClassification;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComplianceDataScopeTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank1;
    private Bank $bank2;
    private User $ncUser;
    private User $bank1User;
    private User $otherUser;
    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        // Setup Organizations
        $ncOrg = Organization::create([
            'name' => 'National Committee',
            'code' => 'nc',
            'classification' => OrganizationClassification::NATIONAL_COMMITTEE,
            'is_active' => true,
        ]);

        $bankingOrg = Organization::create([
            'name' => 'Banking Sector',
            'code' => 'banking',
            'classification' => OrganizationClassification::BANKING_SECTOR,
            'is_active' => true,
        ]);

        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'code' => 'other',
            'classification' => OrganizationClassification::OTHER,
            'is_active' => true,
        ]);

        // Setup Banks
        $this->bank1 = Bank::create(['name' => 'Bank 1', 'code' => 'B1', 'is_active' => true, 'organization_id' => $bankingOrg->id]);
        $this->bank2 = Bank::create(['name' => 'Bank 2', 'code' => 'B2', 'is_active' => true, 'organization_id' => $bankingOrg->id]);

        // Setup Roles with 'audit VIEW' capability
        $auditRole = Role::create([
            'name' => 'Audit Viewer',
            'code' => 'audit_viewer',
            'organization_id' => $ncOrg->id,
            'is_active' => true,
        ]);
        
        $auditScreen = DB::table('screens')->where('key', 'audit')->first();
        DB::table('screen_permissions')->insert([
            'role_id' => $auditRole->id,
            'screen_id' => $auditScreen->id,
            'capability' => 'VIEW',
        ]);

        // Setup Users
        $this->ncUser = User::create([
            'name' => 'NC User',
            'email' => 'nc@test.com',
            'password' => bcrypt('password'),
            'organization_id' => $ncOrg->id,
            'is_active' => true,
        ]);
        $this->ncUser->roles()->attach($auditRole->id, ['is_active' => true]);

        $this->bank1User = User::create([
            'name' => 'Bank 1 User',
            'email' => 'bank1@test.com',
            'password' => bcrypt('password'),
            'bank_id' => $this->bank1->id,
            'organization_id' => $bankingOrg->id,
            'is_active' => true,
        ]);
        // We need a role for bank user too that has audit VIEW
        $bankAuditRole = Role::create([
            'name' => 'Bank Audit Viewer',
            'code' => 'bank_audit_viewer',
            'organization_id' => $bankingOrg->id,
            'is_active' => true,
        ]);
        DB::table('screen_permissions')->insert([
            'role_id' => $bankAuditRole->id,
            'screen_id' => $auditScreen->id,
            'capability' => 'VIEW',
        ]);
        $this->bank1User->roles()->attach($bankAuditRole->id, ['is_active' => true]);

        $this->otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@test.com',
            'password' => bcrypt('password'),
            'organization_id' => $otherOrg->id,
            'is_active' => true,
        ]);
        $otherAuditRole = Role::create([
            'name' => 'Other Audit Viewer',
            'code' => 'other_audit_viewer',
            'organization_id' => $otherOrg->id,
            'is_active' => true,
        ]);
        DB::table('screen_permissions')->insert([
            'role_id' => $otherAuditRole->id,
            'screen_id' => $auditScreen->id,
            'capability' => 'VIEW',
        ]);
        $this->otherUser->roles()->attach($otherAuditRole->id, ['is_active' => true]);

        // Setup Workflow for SLA breaches
        $def = WorkflowDefinition::create(['code' => 'IMPORT', 'name' => 'Import', 'is_active' => true]);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'published_by' => $this->ncUser->id,
            'published_at' => now(),
        ]);
        $this->stage = WorkflowStage::create([
            'workflow_version_id' => $version->id,
            'code' => 'INTAKE',
            'name' => 'Intake',
            'order' => 1,
            'is_initial' => true,
            'sla_duration_minutes' => 60,
        ]);
    }

    public function test_nc_user_sees_system_wide_data(): void
    {
        // Data for bank 1
        EngineRequest::create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-B1',
            'status' => 'ACTIVE',
            'bank_id' => $this->bank1->id,
            'created_by' => $this->ncUser->id,
            'invoice_number' => 'DUP-001',
            'amount' => 1000,
            'currency' => 'USD',
            'version' => 1,
        ]);
        // Data for bank 2
        EngineRequest::create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-B2',
            'status' => 'ACTIVE',
            'bank_id' => $this->bank2->id,
            'created_by' => $this->ncUser->id,
            'invoice_number' => 'DUP-001',
            'amount' => 2000,
            'currency' => 'USD',
            'version' => 1,
        ]);

        $response = $this->actingAs($this->ncUser)
            ->getJson('/api/v1/compliance/duplicate-invoices')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertCount(2, $response->json('data.0.requests'));
    }

    public function test_bank_user_sees_only_own_bank_data(): void
    {
        // Data for bank 1
        EngineRequest::create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-B1-1',
            'status' => 'ACTIVE',
            'bank_id' => $this->bank1->id,
            'created_by' => $this->ncUser->id,
            'invoice_number' => 'DUP-001',
            'amount' => 1000,
            'currency' => 'USD',
            'version' => 1,
        ]);
        EngineRequest::create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-B1-2',
            'status' => 'ACTIVE',
            'bank_id' => $this->bank1->id,
            'created_by' => $this->ncUser->id,
            'invoice_number' => 'DUP-001',
            'amount' => 1500,
            'currency' => 'USD',
            'version' => 1,
        ]);
        // Data for bank 2
        EngineRequest::create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-B2',
            'status' => 'ACTIVE',
            'bank_id' => $this->bank2->id,
            'created_by' => $this->ncUser->id,
            'invoice_number' => 'DUP-001',
            'amount' => 2000,
            'currency' => 'USD',
            'version' => 1,
        ]);

        $response = $this->actingAs($this->bank1User)
            ->getJson('/api/v1/compliance/duplicate-invoices');
            
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        // Should only see the 2 requests from bank 1, not the one from bank 2
        $this->assertCount(2, $response->json('data.0.requests'));
        foreach ($response->json('data.0.requests') as $req) {
            $this->assertStringContainsString('B1', $req['reference']);
        }
    }

    public function test_other_user_sees_no_data(): void
    {
        // Data for bank 1
        EngineRequest::create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-B1-1',
            'status' => 'ACTIVE',
            'bank_id' => $this->bank1->id,
            'created_by' => $this->ncUser->id,
            'invoice_number' => 'DUP-001',
            'amount' => 1000,
            'currency' => 'USD',
            'version' => 1,
        ]);
        EngineRequest::create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-B1-2',
            'status' => 'ACTIVE',
            'bank_id' => $this->bank1->id,
            'created_by' => $this->ncUser->id,
            'invoice_number' => 'DUP-001',
            'amount' => 1500,
            'currency' => 'USD',
            'version' => 1,
        ]);

        $response = $this->actingAs($this->otherUser)
            ->getJson('/api/v1/compliance/duplicate-invoices');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_nc_user_sees_system_wide_expired_documents(): void
    {
        Merchant::create([
            'bank_id' => $this->bank1->id,
            'name' => 'Expired Merchant B1',
            'tax_number' => '111',
            'tax_card_expiry' => now()->subMonth(),
            'status' => 'ACTIVE',
            'version' => 1,
        ]);
        Merchant::create([
            'bank_id' => $this->bank2->id,
            'name' => 'Expired Merchant B2',
            'tax_number' => '222',
            'tax_card_expiry' => now()->subMonth(),
            'status' => 'ACTIVE',
            'version' => 1,
        ]);

        $response = $this->actingAs($this->ncUser)
            ->getJson('/api/v1/compliance/expired-documents')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_bank_user_sees_only_own_bank_expired_documents(): void
    {
        Merchant::create([
            'bank_id' => $this->bank1->id,
            'name' => 'Expired Merchant B1',
            'tax_number' => '111',
            'tax_card_expiry' => now()->subMonth(),
            'status' => 'ACTIVE',
            'version' => 1,
        ]);
        Merchant::create([
            'bank_id' => $this->bank2->id,
            'name' => 'Expired Merchant B2',
            'tax_number' => '222',
            'tax_card_expiry' => now()->subMonth(),
            'status' => 'ACTIVE',
            'version' => 1,
        ]);

        $response = $this->actingAs($this->bank1User)
            ->getJson('/api/v1/compliance/expired-documents')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Expired Merchant B1', $response->json('data.0.merchant_name'));
    }

    public function test_nc_user_sees_system_wide_sla_breaches(): void
    {
        $r1 = EngineRequest::create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-B1',
            'status' => 'ACTIVE',
            'bank_id' => $this->bank1->id,
            'created_by' => $this->ncUser->id,
            'amount' => 1000,
            'currency' => 'USD',
            'version' => 1,
        ]);
        $r2 = EngineRequest::create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-B2',
            'status' => 'ACTIVE',
            'bank_id' => $this->bank2->id,
            'created_by' => $this->ncUser->id,
            'amount' => 2000,
            'currency' => 'USD',
            'version' => 1,
        ]);

        // Mock stage entry in the past for both
        DB::table('workflow_history')->insert([
            ['request_id' => $r1->id, 'to_stage_id' => $this->stage->id, 'performed_by' => $this->ncUser->id, 'created_at' => now()->subHours(2)],
            ['request_id' => $r2->id, 'to_stage_id' => $this->stage->id, 'performed_by' => $this->ncUser->id, 'created_at' => now()->subHours(2)],
        ]);

        $response = $this->actingAs($this->ncUser)
            ->getJson('/api/v1/compliance/sla-breaches')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_bank_user_sees_only_own_bank_sla_breaches(): void
    {
        $r1 = EngineRequest::create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-B1',
            'status' => 'ACTIVE',
            'bank_id' => $this->bank1->id,
            'created_by' => $this->ncUser->id,
            'amount' => 1000,
            'currency' => 'USD',
            'version' => 1,
        ]);
        $r2 = EngineRequest::create([
            'workflow_version_id' => $this->stage->workflow_version_id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-B2',
            'status' => 'ACTIVE',
            'bank_id' => $this->bank2->id,
            'created_by' => $this->ncUser->id,
            'amount' => 2000,
            'currency' => 'USD',
            'version' => 1,
        ]);

        // Mock stage entry in the past for both
        DB::table('workflow_history')->insert([
            ['request_id' => $r1->id, 'to_stage_id' => $this->stage->id, 'performed_by' => $this->ncUser->id, 'created_at' => now()->subHours(2)],
            ['request_id' => $r2->id, 'to_stage_id' => $this->stage->id, 'performed_by' => $this->ncUser->id, 'created_at' => now()->subHours(2)],
        ]);

        $response = $this->actingAs($this->bank1User)
            ->getJson('/api/v1/compliance/sla-breaches')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('REF-B1', $response->json('data.0.reference'));
    }
}
