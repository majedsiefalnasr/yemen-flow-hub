<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Guards QUEUE-003: the Horizon dashboard exposes queue depth/failure-rate
 * data — operational infrastructure detail, not appropriate for any
 * authenticated user. The default Horizon scaffold denies everyone (empty
 * email allowlist); this pins that only a system admin passes the gate,
 * matching the same check already used elsewhere in the app.
 */
class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class]);
    }

    public function test_system_admin_passes_the_horizon_gate(): void
    {
        $cbyOrg = Organization::where('code', 'national_committee')->first();
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@horizon.test',
            'password' => bcrypt('password'),
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $admin->roles()->attach(Role::where('code', 'system_admin')->firstOrFail());

        $this->assertTrue(Gate::forUser($admin)->allows('viewHorizon'));
    }

    public function test_regular_bank_user_fails_the_horizon_gate(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->first();
        $bank = Bank::create(['name' => 'Horizon Bank', 'code' => 'HZB', 'is_active' => true, 'organization_id' => $bankOrg->id]);
        $bankUser = User::create([
            'name' => 'Bank User',
            'email' => 'entry@horizon.test',
            'password' => bcrypt('password'),
            'bank_id' => $bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);

        $this->assertFalse(Gate::forUser($bankUser)->allows('viewHorizon'));
    }

    public function test_unauthenticated_fails_the_horizon_gate(): void
    {
        $this->assertFalse(Gate::forUser(null)->allows('viewHorizon'));
    }
}
