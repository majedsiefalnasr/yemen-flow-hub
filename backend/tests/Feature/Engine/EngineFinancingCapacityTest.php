<?php

namespace Tests\Feature\Engine;

use App\Enums\OrganizationClassification;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Workflow\Engine\EngineFinancingLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class EngineFinancingCapacityTest extends TestCase
{
    use RefreshDatabase;

    private User $dataEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $bank = Bank::create(['name' => 'Cap Bank', 'code' => 'CAP', 'is_active' => true]);

        $org = Organization::query()->create([
            'code' => 'cap_test_org',
            'name' => 'Capacity Test Org',
            'classification' => OrganizationClassification::BANKING_SECTOR,
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'organization_id' => $org->id,
            'code' => 'cap_test_data_entry',
            'name' => 'Capacity Test Data Entry',
            'is_system' => false,
            'is_active' => true,
        ]);

        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'code' => 'cap_test_wf', 'name' => 'Capacity Test Workflow', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $versionId = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId, 'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED->value, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stageId = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $versionId, 'code' => 'intake', 'name' => 'Intake', 'is_initial' => true,
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stage_permissions')->insert([
            'stage_id' => $stageId, 'role_id' => $role->id, 'access_level' => 'EXECUTE',
            'display_label' => $role->name, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->dataEntry = User::create([
            'name' => 'Data Entry',
            'email' => 'de@cap.test',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $this->dataEntry->roles()->attach($role->id);
    }

    public function test_utilization_endpoint_returns_remaining_and_used_percent(): void
    {
        $mock = Mockery::mock(EngineFinancingLedger::class);
        $mock->shouldReceive('usedPercent')->once()->with('TAX-CAP', 'INV-CAP', null, Mockery::any())->andReturn(60.0);
        $mock->shouldReceive('remainingPercent')->once()->with('TAX-CAP', 'INV-CAP', null, Mockery::any())->andReturn(40.0);
        $this->app->instance(EngineFinancingLedger::class, $mock);

        $response = $this->actingAs($this->dataEntry)
            ->getJson('/api/financing/utilization?tax_number=TAX-CAP&invoice_number=INV-CAP')
            ->assertOk()
            ->assertJsonPath('data.blocked', false);

        $this->assertEquals(60, $response->json('data.used_percent'));
        $this->assertEquals(40, $response->json('data.remaining_percent'));
    }

    public function test_blocked_flag_true_when_capacity_exhausted(): void
    {
        $mock = Mockery::mock(EngineFinancingLedger::class);
        $mock->shouldReceive('usedPercent')->once()->andReturn(100.0);
        $mock->shouldReceive('remainingPercent')->once()->andReturn(0.0);
        $this->app->instance(EngineFinancingLedger::class, $mock);

        $response = $this->actingAs($this->dataEntry)
            ->getJson('/api/financing/utilization?tax_number=TAX-FULL&invoice_number=INV-FULL')
            ->assertOk()
            ->assertJsonPath('data.blocked', true);

        $this->assertEquals(100, $response->json('data.used_percent'));
    }

    public function test_blocked_false_when_capacity_partially_used(): void
    {
        $mock = Mockery::mock(EngineFinancingLedger::class);
        $mock->shouldReceive('usedPercent')->once()->andReturn(50.0);
        $mock->shouldReceive('remainingPercent')->once()->andReturn(50.0);
        $this->app->instance(EngineFinancingLedger::class, $mock);

        $this->actingAs($this->dataEntry)
            ->getJson('/api/financing/utilization?tax_number=TAX-HALF&invoice_number=INV-HALF')
            ->assertOk()
            ->assertJsonPath('data.blocked', false);
    }

    public function test_exclude_request_id_is_forwarded_to_ledger(): void
    {
        $mock = Mockery::mock(EngineFinancingLedger::class);
        $mock->shouldReceive('usedPercent')->once()->with('TAX-EX', 'INV-EX', 42, Mockery::any())->andReturn(30.0);
        $mock->shouldReceive('remainingPercent')->once()->with('TAX-EX', 'INV-EX', 42, Mockery::any())->andReturn(70.0);
        $this->app->instance(EngineFinancingLedger::class, $mock);

        $response = $this->actingAs($this->dataEntry)
            ->getJson('/api/financing/utilization?tax_number=TAX-EX&invoice_number=INV-EX&exclude_request_id=42')
            ->assertOk();

        $this->assertEquals(70, $response->json('data.remaining_percent'));
    }

    public function test_missing_params_returns_422(): void
    {
        $this->actingAs($this->dataEntry)
            ->getJson('/api/financing/utilization?tax_number=ONLY')
            ->assertUnprocessable();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/financing/utilization?tax_number=T&invoice_number=I')
            ->assertUnauthorized();
    }
}
