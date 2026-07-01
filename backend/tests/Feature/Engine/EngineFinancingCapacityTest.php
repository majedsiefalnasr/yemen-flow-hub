<?php

namespace Tests\Feature\Engine;

use App\Enums\UserRole;
use App\Models\Bank;
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

        $permId = DB::table('permissions')->insertGetId([
            'slug' => 'request.create',
            'name_ar' => 'Create',
            'name_en' => 'Create',
            'group' => 'requests',
        ]);
        DB::table('role_permissions')->insertOrIgnore([
            'permission_id' => $permId,
            'role' => UserRole::DATA_ENTRY->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->dataEntry = User::create([
            'name' => 'Data Entry',
            'email' => 'de@cap.test',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);
    }

    public function test_utilization_endpoint_returns_remaining_and_used_percent(): void
    {
        $mock = Mockery::mock(EngineFinancingLedger::class);
        $mock->shouldReceive('usedPercent')->once()->with('TAX-CAP', 'INV-CAP', null)->andReturn(60.0);
        $mock->shouldReceive('remainingPercent')->once()->with('TAX-CAP', 'INV-CAP', null)->andReturn(40.0);
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
        $mock->shouldReceive('usedPercent')->once()->with('TAX-EX', 'INV-EX', 42)->andReturn(30.0);
        $mock->shouldReceive('remainingPercent')->once()->with('TAX-EX', 'INV-EX', 42)->andReturn(70.0);
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
