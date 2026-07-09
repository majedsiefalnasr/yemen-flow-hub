<?php

namespace Tests\Feature\Audit\V1;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards API-007: audit-log date/entity filters must use index-friendly
 * predicates. whereDate() wraps created_at in DATE(), defeating any index on
 * the column; infix subject_type LIKE '%X%' forces a full scan. Both are
 * replaced with half-open range bounds and an exact subject_type match
 * (mirrors the ARCH-004 fix already applied to EngineRequestListQuery).
 */
class AuditLogFilterPredicatesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $cbyOrg = Organization::where('code', 'national_committee')->first();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@filterpredicates.test',
            'password' => bcrypt('password'),
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $systemAdminRole = Role::query()->where('code', 'system_admin')->firstOrFail();
        $this->admin->roles()->attach($systemAdminRole->id);
    }

    private function logAt(string $createdAt, ?string $subjectType = null): AuditLog
    {
        // created_at is not mass-assignable, and AuditLog::booted() blocks
        // Eloquent updates entirely (append-only) — so the backdated
        // timestamp must be written via a direct query-builder update,
        // bypassing the model's updating() guard.
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => AuditAction::STATUS_TRANSITION->value,
            'subject_type' => $subjectType,
        ]);
        DB::table('audit_logs')->where('id', $log->id)->update(['created_at' => $createdAt]);

        return $log->fresh();
    }

    public function test_to_filter_is_inclusive_of_the_whole_day(): void
    {
        $onDate = $this->logAt('2026-03-10 23:30:00');
        $this->logAt('2026-03-11 00:15:00');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs?from=2026-03-10&to=2026-03-10');

        $response->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertEquals($onDate->id, $response->json('data.0.id'));
    }

    public function test_from_filter_excludes_earlier_days(): void
    {
        $this->logAt('2026-03-09 12:00:00');
        $onOrAfter = $this->logAt('2026-03-10 12:00:00');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs?from=2026-03-10');

        $response->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertEquals($onOrAfter->id, $response->json('data.0.id'));
    }

    public function test_entity_filter_matches_exact_subject_type_only(): void
    {
        $exact = $this->logAt('2026-03-10 10:00:00', 'App\\Models\\EngineRequest');
        // A different class whose name happens to contain the same substring
        // ("EngineRequest") would have matched the old infix LIKE but must
        // not match an exact-equality filter.
        $this->logAt('2026-03-10 10:05:00', 'App\\Models\\EngineRequestDocument');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs?entity='.urlencode('App\\Models\\EngineRequest'));

        $response->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertEquals($exact->id, $response->json('data.0.id'));
    }

    public function test_export_applies_the_same_range_and_entity_filters(): void
    {
        $inRange = $this->logAt('2026-03-10 10:00:00', 'App\\Models\\EngineRequest');
        $this->logAt('2026-03-09 10:00:00', 'App\\Models\\EngineRequest');
        $this->logAt('2026-03-10 10:00:00', 'App\\Models\\EngineRequestDocument');

        $response = $this->actingAs($this->admin)
            ->get('/api/v1/audit-logs/export?from=2026-03-10&to=2026-03-10&entity='.urlencode('App\\Models\\EngineRequest'));

        $response->assertOk();
        $csv = $response->getContent();
        $this->assertStringContainsString((string) $inRange->id, $csv);
        $this->assertSame(2, substr_count($csv, "\n"), 'Expected header + exactly one matching data row.');
    }
}
