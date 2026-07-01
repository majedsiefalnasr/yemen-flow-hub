<?php

namespace Tests\Feature\Financing;

use App\Enums\UserRole;
use App\Models\Bank;
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
        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'request.create',
            'name_ar' => 'Create',
            'name_en' => 'Create',
            'group' => 'requests',
        ]);
        DB::table('role_permissions')->insert([
            'permission_id' => $permissionId,
            'role' => UserRole::DATA_ENTRY->value,
        ]);

        $this->dataEntry = User::query()->create([
            'name' => 'Data Entry',
            'email' => 'de@util.test',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);
    }

    public function test_returns_aggregate_utilization_shape(): void
    {
        $mock = Mockery::mock(EngineFinancingLedger::class);
        $mock->shouldReceive('usedPercent')->once()->with('TAX-1', 'INV-1', null)->andReturn(72.5);
        $mock->shouldReceive('remainingPercent')->once()->with('TAX-1', 'INV-1', null)->andReturn(27.5);
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
        $mock->shouldReceive('usedPercent')->once()->with('TAX-3', 'INV-3', 9)->andReturn(40.0);
        $mock->shouldReceive('remainingPercent')->once()->with('TAX-3', 'INV-3', 9)->andReturn(60.0);
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
            'role' => UserRole::BANK_REVIEWER,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);

        $this->actingAs($reviewer)
            ->getJson('/api/financing/utilization?tax_number=TAX-1&invoice_number=INV-1')
            ->assertForbidden();
    }
}
