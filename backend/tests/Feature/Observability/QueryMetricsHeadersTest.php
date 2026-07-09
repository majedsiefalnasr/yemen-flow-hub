<?php

namespace Tests\Feature\Observability;

use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards OBS-001: every API response carries a query-count/query-time header pair
 * so N+1 regressions and the API-001/API-002/API-005 query-count gates are
 * assertable without attaching a profiler. Headers must reflect the real
 * per-request query volume, not a hardcoded value.
 */
class QueryMetricsHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);
    }

    private function bankUser(string $email): User
    {
        $org = Organization::where('code', 'commercial_banks')->firstOrFail();
        $role = Role::where('code', 'intake')->firstOrFail();
        $suffix = uniqid();
        $bank = Bank::create(['name' => "Metrics Bank {$suffix}", 'code' => 'MET'.$suffix, 'is_active' => true, 'organization_id' => $org->id]);

        $user = User::create([
            'name' => 'Metrics U',
            'email' => $email,
            'password' => bcrypt('pass'),
            'bank_id' => $bank->id,
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        return $user->fresh(['roles']);
    }

    public function test_api_response_carries_query_count_and_time_headers(): void
    {
        config(['observability.expose_query_metrics_headers' => true]);
        $user = $this->bankUser('metrics-a@obs.test');

        $response = $this->actingAs($user)->getJson('/api/v1/engine-requests');

        $response->assertOk();
        $response->assertHeader('X-Query-Count');
        $response->assertHeader('X-Query-Time-Ms');
        $this->assertGreaterThan(0, (int) $response->headers->get('X-Query-Count'));
    }

    public function test_query_count_header_reflects_actual_query_volume(): void
    {
        config(['observability.expose_query_metrics_headers' => true]);
        $user = $this->bankUser('metrics-b@obs.test');

        $actualCount = 0;
        DB::listen(function () use (&$actualCount) {
            $actualCount++;
        });

        $response = $this->actingAs($user)->getJson('/api/v1/engine-requests');

        $response->assertOk();
        $this->assertSame($actualCount, (int) $response->headers->get('X-Query-Count'));
    }

    public function test_headers_are_absent_when_disabled(): void
    {
        config(['observability.expose_query_metrics_headers' => false]);
        $user = $this->bankUser('metrics-c@obs.test');

        $response = $this->actingAs($user)->getJson('/api/v1/engine-requests');

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Query-Count'));
    }

    public function test_headers_are_disabled_by_default_in_production(): void
    {
        config(['app.env' => 'production']);
        $user = $this->bankUser('metrics-d@obs.test');

        $response = $this->actingAs($user)->getJson('/api/v1/engine-requests');

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Query-Count'));
    }
}
