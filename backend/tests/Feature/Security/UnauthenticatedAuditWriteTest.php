<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Guards SEC-003: an unauthenticated caller must never write an audit_logs
 * row. The exception-handler chain in bootstrap/app.php only calls
 * $auditAuthorizationFailure for domain AuthorizationException denials,
 * which requires an authenticated $request->user() to reach a policy check —
 * a plain AuthenticationException (401, no session) never triggers the
 * audit write. ARCH-003's default throttle additionally caps unauthenticated
 * volume on any route it covers. This test proves both halves so a future
 * regression (e.g. wiring the audit call into the 401 handler) is caught.
 */
class UnauthenticatedAuditWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        ThrottleRequests::shouldHashKeys(false);
        RateLimiter::clear('api-default');
    }

    private function bankUser(string $email, string $roleCode): User
    {
        $org = Organization::where('code', 'commercial_banks')->firstOrFail();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $suffix = uniqid();
        $bank = Bank::create(['name' => "SEC003 Bank {$suffix}", 'code' => 'S3'.$suffix, 'is_active' => true, 'organization_id' => $org->id]);

        $user = User::create([
            'name' => 'SEC003 U',
            'email' => $email,
            'password' => bcrypt('pass'),
            'bank_id' => $bank->id,
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        return $user->fresh(['roles']);
    }

    public function test_unauthenticated_request_gets_401_and_writes_no_audit_row(): void
    {
        $before = AuditLog::count();

        $response = $this->getJson('/api/v1/roles');

        $response->assertStatus(401);
        $this->assertSame($before, AuditLog::count());
    }

    public function test_repeated_unauthenticated_requests_write_no_audit_rows(): void
    {
        $before = AuditLog::count();

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/v1/roles')->assertStatus(401);
        }

        $this->assertSame($before, AuditLog::count());
    }

    public function test_authenticated_authorization_denial_still_writes_audit_row(): void
    {
        // Sanity check: the audit write for genuine policy denials (an
        // authenticated user who is not permitted) must still function —
        // SEC-003 must not be "fixed" by silencing legitimate audit trail.
        $user = $this->bankUser('denied@sec003.test', 'intake');
        $before = AuditLog::count();

        $response = $this->actingAs($user)->getJson('/api/v1/roles');

        $response->assertStatus(403);
        $this->assertSame($before + 1, AuditLog::count());
    }

    public function test_authenticated_volume_is_throttled_after_auth_succeeds(): void
    {
        // auth:sanctum runs before throttle:api-default in the route group, so
        // an unauthenticated caller is rejected (401) before ever reaching the
        // throttle — there is no amplification vector to throttle in that case
        // (proven above: zero audit writes). Once authenticated, ARCH-003's
        // default throttle still caps that user's volume against this route.
        config(['auth_security.api_throttle_per_minute' => 3]);
        $user = $this->bankUser('throttled@sec003.test', 'intake');

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->getJson('/api/v1/roles')->assertStatus(403);
        }

        $this->actingAs($user)->getJson('/api/v1/roles')->assertStatus(429);
    }
}
