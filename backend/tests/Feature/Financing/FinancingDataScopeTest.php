<?php

namespace Tests\Feature\Financing;

use App\Enums\OrganizationClassification;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinancingDataScopeTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank1;

    private Bank $bank2;

    private User $bank1User;

    private User $bank2User;

    private User $ncUser;

    private User $noOrgUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Banks
        $this->bank1 = Bank::query()->create(['name' => 'Bank 1', 'code' => 'B1', 'is_active' => true]);
        $this->bank2 = Bank::query()->create(['name' => 'Bank 2', 'code' => 'B2', 'is_active' => true]);

        // Setup Organizations
        $org1 = Organization::query()->create([
            'code' => 'org1',
            'name' => 'Org 1',
            'classification' => OrganizationClassification::BANKING_SECTOR,
            'is_active' => true,
        ]);
        $org2 = Organization::query()->create([
            'code' => 'org2',
            'name' => 'Org 2',
            'classification' => OrganizationClassification::BANKING_SECTOR,
            'is_active' => true,
        ]);
        $ncOrg = Organization::query()->create([
            'code' => 'nc_org',
            'name' => 'NC Org',
            'classification' => OrganizationClassification::NATIONAL_COMMITTEE,
            'is_active' => true,
        ]);

        // Setup Roles with CREATE capability
        $role1 = Role::query()->create(['organization_id' => $org1->id, 'code' => 'r1', 'name' => 'R1', 'is_active' => true]);
        $role2 = Role::query()->create(['organization_id' => $org2->id, 'code' => 'r2', 'name' => 'R2', 'is_active' => true]);
        $ncRole = Role::query()->create(['organization_id' => $ncOrg->id, 'code' => 'nc', 'name' => 'NC', 'is_active' => true]);

        // Grant CREATE capability via a dummy workflow stage
        $defId = DB::table('workflow_definitions')->insertGetId(['code' => 'test', 'name' => 'Test', 'created_at' => now(), 'updated_at' => now()]);
        $verId = DB::table('workflow_versions')->insertGetId(['workflow_definition_id' => $defId, 'version_number' => 1, 'state' => 'PUBLISHED', 'created_at' => now(), 'updated_at' => now()]);
        $stageId = DB::table('workflow_stages')->insertGetId(['workflow_version_id' => $verId, 'code' => 's1', 'name' => 'S1', 'is_initial' => true, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);

        foreach ([$role1->id, $role2->id, $ncRole->id] as $rid) {
            DB::table('stage_permissions')->insert([
                'stage_id' => $stageId, 'role_id' => $rid, 'access_level' => 'EXECUTE',
                'display_label' => 'Execute', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Grant audit VIEW to NC role
        $auditScreenId = DB::table('screens')->where('key', 'audit')->value('id');
        if (! $auditScreenId) {
            $auditScreenId = DB::table('screens')->insertGetId(['key' => 'audit', 'label' => 'Audit', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('screen_permissions')->insert([
            'role_id' => $ncRole->id, 'screen_id' => $auditScreenId, 'capability' => 'VIEW',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Setup Users
        $this->bank1User = User::query()->create([
            'name' => 'Bank 1 User', 'email' => 'b1@test.com', 'password' => Hash::make('password'),
            'bank_id' => $this->bank1->id, 'organization_id' => $org1->id, 'is_active' => true,
        ]);
        $this->bank1User->roles()->attach($role1->id);

        $this->bank2User = User::query()->create([
            'name' => 'Bank 2 User', 'email' => 'b2@test.com', 'password' => Hash::make('password'),
            'bank_id' => $this->bank2->id, 'organization_id' => $org2->id, 'is_active' => true,
        ]);
        $this->bank2User->roles()->attach($role2->id);

        $this->ncUser = User::query()->create([
            'name' => 'NC User', 'email' => 'nc@test.com', 'password' => Hash::make('password'),
            'organization_id' => $ncOrg->id, 'is_active' => true,
        ]);
        $this->ncUser->roles()->attach($ncRole->id);

        $this->noOrgUser = User::query()->create([
            'name' => 'No Org User', 'email' => 'none@test.com', 'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Setup Merchants
        Merchant::query()->create([
            'bank_id' => $this->bank1->id,
            'name' => 'Merchant 1',
            'tax_number' => 'TAX-1',
            'is_active' => true,
        ]);
        Merchant::query()->create([
            'bank_id' => $this->bank2->id,
            'name' => 'Merchant 2',
            'tax_number' => 'TAX-2',
            'is_active' => true,
        ]);
    }

    public function test_bank_user_can_check_own_merchant(): void
    {
        $this->actingAs($this->bank1User)
            ->getJson('/api/financing/utilization?tax_number=TAX-1&invoice_number=INV-1')
            ->assertOk()
            ->assertJsonPath('data.used_percent', 0);
    }

    public function test_bank_user_cannot_probe_other_bank_merchant(): void
    {
        $this->actingAs($this->bank1User)
            ->getJson('/api/financing/utilization?tax_number=TAX-2&invoice_number=INV-1')
            ->assertForbidden();
    }

    public function test_bank_user_can_check_non_existent_merchant(): void
    {
        $this->actingAs($this->bank1User)
            ->getJson('/api/financing/utilization?tax_number=NON-EXISTENT&invoice_number=INV-1')
            ->assertOk()
            ->assertJsonPath('data.used_percent', 0);
    }

    public function test_nc_user_can_check_any_merchant(): void
    {
        $this->actingAs($this->ncUser)
            ->getJson('/api/financing/utilization?tax_number=TAX-1&invoice_number=INV-1')
            ->assertOk();

        $this->actingAs($this->ncUser)
            ->getJson('/api/financing/utilization?tax_number=TAX-2&invoice_number=INV-1')
            ->assertOk();
    }

    public function test_user_without_org_is_denied(): void
    {
        $this->actingAs($this->noOrgUser)
            ->getJson('/api/financing/utilization?tax_number=TAX-1&invoice_number=INV-1')
            ->assertForbidden();
    }
}
