<?php

namespace Tests\Feature\Operations;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLogArchive;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArchiveOldAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $cbyOrg = Organization::where('code', 'national_committee')->firstOrFail();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@audit-archive.test',
            'password' => bcrypt('password'),
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $systemAdminRole = Role::query()->where('code', 'system_admin')->firstOrFail();
        $this->admin->roles()->attach($systemAdminRole->id);
    }

    public function test_archives_rows_older_than_hot_horizon(): void
    {
        $oldId = DB::table('audit_logs')->insertGetId([
            'user_id' => $this->admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGIN->value,
            'created_at' => now()->subMonths(13),
        ]);

        $recentId = DB::table('audit_logs')->insertGetId([
            'user_id' => $this->admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGOUT->value,
            'created_at' => now()->subMonth(),
        ]);

        $this->artisan('audit:archive-old')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $oldId]);
        $this->assertDatabaseHas('audit_log_archives', [
            'source_id' => $oldId,
            'action' => AuditAction::LOGIN->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recentId]);
    }

    public function test_archive_batch_is_idempotent(): void
    {
        $oldId = DB::table('audit_logs')->insertGetId([
            'user_id' => $this->admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGIN->value,
            'created_at' => now()->subMonths(13),
        ]);

        $this->artisan('audit:archive-old')->assertSuccessful();
        $this->artisan('audit:archive-old')->assertSuccessful();

        $this->assertSame(1, AuditLogArchive::query()->where('source_id', $oldId)->count());
    }

    public function test_logs_audit_archived_action_for_batch(): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $this->admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGIN->value,
            'created_at' => now()->subMonths(13),
        ]);

        $this->artisan('audit:archive-old')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AUDIT_ARCHIVED->value,
        ]);
    }

    /**
     * ARCH-006: SEC-002 added bank_id to audit_logs for scoped reads, but the
     * archive path must carry it forward too, or a bank-scoped row becomes
     * unscopable (and therefore invisible to a bank admin, or worse, visible
     * to the wrong bank if a future read ever defaults null to systemWide)
     * the moment it archives.
     */
    public function test_archived_row_preserves_bank_id(): void
    {
        $oldId = DB::table('audit_logs')->insertGetId([
            'user_id' => $this->admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGIN->value,
            'bank_id' => 7,
            'created_at' => now()->subMonths(13),
        ]);

        $this->artisan('audit:archive-old')->assertSuccessful();

        $this->assertDatabaseHas('audit_log_archives', [
            'source_id' => $oldId,
            'bank_id' => 7,
        ]);
    }
}
