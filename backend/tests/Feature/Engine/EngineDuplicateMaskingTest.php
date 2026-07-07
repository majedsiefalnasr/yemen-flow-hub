<?php

namespace Tests\Feature\Engine;

use App\Enums\FieldType;
use App\Enums\OrganizationClassification;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\FieldGroup;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Models\EngineNotification;
use App\Models\Screen;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EngineDuplicateMaskingTest extends TestCase
{
    use RefreshDatabase;

    private User $bankAUser;
    private User $bankBUser;
    private User $ncUser;
    private WorkflowVersion $version;
    private Bank $bankA;
    private Bank $bankB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $ncOrg = Organization::where('code', 'national_committee')->firstOrFail();
        
        $entryRole = Role::where('organization_id', $bankOrg->id)->where('code', 'intake')->firstOrFail();
        $entryTeam = Team::where('organization_id', $bankOrg->id)->where('code', 'entry')->firstOrFail();
        
        $ncRole = Role::where('organization_id', $ncOrg->id)->where('code', 'support')->firstOrFail();
        $ncTeam = Team::where('organization_id', $ncOrg->id)->where('code', 'support')->firstOrFail();

        $this->bankA = Bank::create(['name' => 'Bank A', 'code' => 'BA', 'is_active' => true, 'organization_id' => $bankOrg->id]);
        $this->bankB = Bank::create(['name' => 'Bank B', 'code' => 'BB', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $this->bankAUser = User::create([
            'name' => 'Bank A User',
            'email' => 'a@bank.test',
            'password' => bcrypt('password'),
            'bank_id' => $this->bankA->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->bankAUser->teams()->attach($entryTeam);
        $this->bankAUser->roles()->attach($entryRole);

        $this->bankBUser = User::create([
            'name' => 'Bank B User',
            'email' => 'b@bank.test',
            'password' => bcrypt('password'),
            'bank_id' => $this->bankB->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->bankBUser->teams()->attach($entryTeam);
        $this->bankBUser->roles()->attach($entryRole);

        $this->ncUser = User::create([
            'name' => 'NC User',
            'email' => 'nc@cby.test',
            'password' => bcrypt('password'),
            'organization_id' => $ncOrg->id,
            'is_active' => true,
        ]);
        $this->ncUser->teams()->attach($ncTeam);
        $this->ncUser->roles()->attach($ncRole);

        // Setup Workflow
        $def = WorkflowDefinition::create(['code' => 'MASK_WF', 'name' => 'Mask Workflow', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
            'version' => 1,
        ]);

        $stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'ENTRY',
            'name' => 'Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $stage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Entry',
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $stage->id,
            'organization_id' => $ncOrg->id,
            'role_id' => $ncRole->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'View',
            'version' => 1,
        ]);

        $group = FieldGroup::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'general',
            'label' => 'General',
            'sort_order' => 1,
            'version' => 1,
        ]);

        FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'invoice_number',
            'label' => 'Invoice Number',
            'type' => FieldType::TEXT,
            'sort_order' => 1,
            'is_required' => false,
            'version' => 1,
        ]);
    }

    public function test_bank_user_sees_full_details_for_own_bank_duplicates(): void
    {
        // Create request in Bank A
        $merchantA = Merchant::create(['bank_id' => $this->bankA->id, 'name' => 'M A', 'tax_number' => 'T-A', 'status' => 'ACTIVE']);
        $first = $this->actingAs($this->bankAUser)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $merchantA->id,
            'data' => ['invoice_number' => 'INV-OWN-001'],
        ])->assertCreated();

        // Create second request in Bank A with same invoice
        $response = $this->actingAs($this->bankAUser)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $merchantA->id,
            'data' => ['invoice_number' => 'INV-OWN-001'],
        ]);

        $response->assertCreated();
        $duplicates = $response->json('warnings.0.duplicates');
        $this->assertCount(1, $duplicates);
        $this->assertEquals($first->json('data.reference'), $duplicates[0]['reference']);
        $this->assertEquals($first->json('data.id'), $duplicates[0]['id']);
    }

    public function test_bank_user_sees_masked_details_for_other_bank_duplicates(): void
    {
        // Create request in Bank B
        $merchantB = Merchant::create(['bank_id' => $this->bankB->id, 'name' => 'M B', 'tax_number' => 'T-B', 'status' => 'ACTIVE']);
        $first = $this->actingAs($this->bankBUser)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $merchantB->id,
            'data' => ['invoice_number' => 'INV-OTHER-001'],
        ])->assertCreated();

        // Create request in Bank A with same invoice
        $merchantA = Merchant::create(['bank_id' => $this->bankA->id, 'name' => 'M A', 'tax_number' => 'T-A', 'status' => 'ACTIVE']);
        $response = $this->actingAs($this->bankAUser)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $merchantA->id,
            'data' => ['invoice_number' => 'INV-OTHER-001'],
        ]);

        $response->assertCreated();
        $duplicates = $response->json('warnings.0.duplicates');
        $this->assertCount(1, $duplicates);
        $this->assertEquals('طلب مكرر في مؤسسة أخرى', $duplicates[0]['reference']);
        $this->assertNull($duplicates[0]['id']);
    }

    public function test_nc_user_sees_full_details_for_all_duplicates(): void
    {
        // Create request in Bank A
        $merchantA = Merchant::create(['bank_id' => $this->bankA->id, 'name' => 'M A', 'tax_number' => 'T-A', 'status' => 'ACTIVE']);
        $firstA = $this->actingAs($this->bankAUser)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $merchantA->id,
            'data' => ['invoice_number' => 'INV-NC-001'],
        ])->assertCreated();

        // Create request in Bank B
        $merchantB = Merchant::create(['bank_id' => $this->bankB->id, 'name' => 'M B', 'tax_number' => 'T-B', 'status' => 'ACTIVE']);
        $firstB = $this->actingAs($this->bankBUser)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $merchantB->id,
            'data' => ['invoice_number' => 'INV-NC-001'],
        ])->assertCreated();

        // NC user views one of them
        $response = $this->actingAs($this->ncUser)->getJson("/api/v1/engine-requests/{$firstA->json('data.id')}");

        $response->assertOk();
        $duplicates = $response->json('warnings.0.duplicates');
        $this->assertCount(1, $duplicates); // Should see the one from Bank B
        $this->assertEquals($firstB->json('data.reference'), $duplicates[0]['reference']);
        $this->assertEquals($firstB->json('data.id'), $duplicates[0]['id']);
    }

    public function test_notifications_are_masked_for_bank_recipients_and_full_for_nc(): void
    {
        // Grant audit VIEW to bank role so they receive compliance notifications
        $auditScreen = Screen::where('key', 'audit')->firstOrFail();
        $bankRole = Role::where('organization_id', Organization::where('code', 'commercial_banks')->first()->id)
            ->where('code', 'intake')->firstOrFail();
        DB::table('screen_permissions')->insert([
            'role_id' => $bankRole->id,
            'screen_id' => $auditScreen->id,
            'capability' => 'VIEW',
        ]);

        // Create request in Bank B
        $merchantB = Merchant::create(['bank_id' => $this->bankB->id, 'name' => 'M B', 'tax_number' => 'T-B', 'status' => 'ACTIVE']);
        $this->actingAs($this->bankBUser)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $merchantB->id,
            'data' => ['invoice_number' => 'INV-NOTIF-001'],
        ])->assertCreated();

        // Create request in Bank A with same invoice
        $merchantA = Merchant::create(['bank_id' => $this->bankA->id, 'name' => 'M A', 'tax_number' => 'T-A', 'status' => 'ACTIVE']);
        $this->actingAs($this->bankAUser)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $merchantA->id,
            'data' => ['invoice_number' => 'INV-NOTIF-001'],
        ])->assertCreated();

        // Check notifications
        // We expect two notifications (one for NC, one for Bank A user)
        $notifications = EngineNotification::where('type', 'compliance.duplicate_invoice')->get();
        $this->assertCount(2, $notifications);

        $ncNotif = $notifications->first(fn($n) => str_contains($n->body, 'INV-NOTIF-001') && !str_contains($n->body, 'طلب مكرر'));
        $bankNotif = $notifications->first(fn($n) => str_contains($n->body, 'طلب مكرر'));

        $this->assertNotNull($ncNotif, 'NC should receive full detail notification');
        $this->assertNotNull($bankNotif, 'Bank user should receive masked notification');
    }
}
