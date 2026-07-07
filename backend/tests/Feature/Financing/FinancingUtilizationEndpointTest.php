<?php

namespace Tests\Feature\Financing;

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

class FinancingUtilizationEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $dataEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $bank = Bank::query()->create(['name' => 'Bank', 'code' => 'B1', 'is_active' => true]);

        $org = Organization::query()->create([
            'code' => 'util_test_org',
            'name' => 'Utilization Test Org',
            'classification' => OrganizationClassification::BANKING_SECTOR,
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'organization_id' => $org->id,
            'code' => 'util_test_data_entry',
            'name' => 'Utilization Test Data Entry',
            'is_system' => false,
            'is_active' => true,
        ]);

        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'code' => 'util_test_wf', 'name' => 'Utilization Test Workflow', 'created_at' => now(), 'updated_at' => now(),
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

        $this->dataEntry = User::query()->create([
            'name' => 'Data Entry',
            'email' => 'de@util.test',
            'password' => Hash::make('password'),
            'bank_id' => $bank->id,
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $this->dataEntry->roles()->attach($role->id);
    }

    public function test_returns_aggregate_utilization_shape(): void
    {
        $mock = Mockery::mock(EngineFinancingLedger::class);
        $mock->shouldReceive('usedPercent')->once()->with('TAX-1', 'INV-1', null, Mockery::any())->andReturn(72.5);
        $mock->shouldReceive('remainingPercent')->once()->with('TAX-1', 'INV-1', null, Mockery::any())->andReturn(27.5);
        $this->app->instance(EngineFinancingLedger::class, $mock);

        $this->actingAs($this->dataEntry)
            ->getJson('/api/financing/utilization?tax_number=TAX-1&invoice_number=INV-1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.used_percent', 72.5)
            ->assertJsonPath('data.remaining_percent', 27.5)
            ->assertJsonPath('data.blocked', false)
            ->assertJsonMissing(['reference_number' => true]);
    }

    public function test_blocked_true_when_remaining_percent_is_zero(): void
    {
        $mock = Mockery::mock(EngineFinancingLedger::class);
        $mock->shouldReceive('usedPercent')->once()->andReturn(100.0);
        $mock->shouldReceive('remainingPercent')->once()->andReturn(0.0);
        $this->app->instance(EngineFinancingLedger::class, $mock);

        $this->actingAs($this->dataEntry)
            ->getJson('/api/financing/utilization?tax_number=TAX-2&invoice_number=INV-2')
            ->assertOk()
            ->assertJsonPath('data.blocked', true);
    }

    public function test_passes_exclude_request_id_to_service(): void
    {
        $mock = Mockery::mock(EngineFinancingLedger::class);
        $mock->shouldReceive('usedPercent')->once()->with('TAX-3', 'INV-3', 9, Mockery::any())->andReturn(40.0);
        $mock->shouldReceive('remainingPercent')->once()->with('TAX-3', 'INV-3', 9, Mockery::any())->andReturn(60.0);
        $this->app->instance(EngineFinancingLedger::class, $mock);

        $this->actingAs($this->dataEntry)
            ->getJson('/api/financing/utilization?tax_number=TAX-3&invoice_number=INV-3&exclude_request_id=9')
            ->assertOk()
            ->assertJsonPath('data.remaining_percent', 60);
    }

    public function test_returns_validation_error_when_params_missing(): void
    {
        $this->actingAs($this->dataEntry)
            ->getJson('/api/financing/utilization?tax_number=TAX-ONLY')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/financing/utilization?tax_number=TAX-1&invoice_number=INV-1')
            ->assertUnauthorized();
    }

    public function test_requires_request_create_permission(): void
    {
        $bank = Bank::query()->first();
        $reviewer = User::query()->create([
            'name' => 'Reviewer',
            'email' => 'rev@util.test',
            'password' => Hash::make('password'),
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);

        $this->actingAs($reviewer)
            ->getJson('/api/financing/utilization?tax_number=TAX-1&invoice_number=INV-1')
            ->assertForbidden();
    }
}
