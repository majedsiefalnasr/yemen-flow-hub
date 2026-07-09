<?php

namespace Tests\Feature\Operations;

use App\Enums\AuditAction;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowHistoryArchive;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ARCH-006: workflow_history had no retention/archival path at all. Rows
 * must only ever archive once the owning engine_request is no longer ACTIVE
 * -- an in-flight request's history rows are load-bearing for
 * EngineRequest::withStageEntry() (SLA stage-entry lookup) and
 * ReportController::stageDuration() (consecutive-row hour-diff join), so
 * archiving them early would silently corrupt live SLA/report data.
 */
class ArchiveOldWorkflowHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $performer;

    private WorkflowVersion $version;

    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $cbyOrg = Organization::where('code', 'national_committee')->firstOrFail();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@workflow-archive.test',
            'password' => bcrypt('password'),
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $systemAdminRole = Role::query()->where('code', 'system_admin')->firstOrFail();
        $this->admin->roles()->attach($systemAdminRole->id);

        $this->performer = User::create([
            'name' => 'Performer',
            'email' => 'performer@workflow-archive.test',
            'password' => bcrypt('password'),
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);

        $def = WorkflowDefinition::create(['code' => 'ARCHIVE_WF', 'name' => 'Archive WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);
        $this->stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'REVIEW',
            'name' => 'Review',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);
    }

    private function makeRequest(string $status, int $bankId): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REF-'.uniqid(),
            'status' => $status,
            'created_by' => $this->performer->id,
            'bank_id' => $bankId,
            'data' => [],
            'version' => 1,
        ]);
    }

    public function test_archives_history_rows_for_closed_requests_past_the_hot_horizon(): void
    {
        $bank = Bank::factory()->create();
        $request = $this->makeRequest('CLOSED', $bank->id);

        $oldId = DB::table('workflow_history')->insertGetId([
            'request_id' => $request->id,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->performer->id,
            'created_at' => now()->subMonths(13),
        ]);

        $this->artisan('workflow-history:archive-old')->assertSuccessful();

        $this->assertDatabaseMissing('workflow_history', ['id' => $oldId]);
        $this->assertDatabaseHas('workflow_history_archives', [
            'source_id' => $oldId,
            'request_id' => $request->id,
            'bank_id' => $bank->id,
        ]);
    }

    public function test_never_archives_history_for_an_active_request(): void
    {
        $bank = Bank::factory()->create();
        $request = $this->makeRequest('ACTIVE', $bank->id);

        $oldId = DB::table('workflow_history')->insertGetId([
            'request_id' => $request->id,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->performer->id,
            'created_at' => now()->subMonths(13),
        ]);

        $this->artisan('workflow-history:archive-old')->assertSuccessful();

        $this->assertDatabaseHas('workflow_history', ['id' => $oldId]);
        $this->assertDatabaseMissing('workflow_history_archives', ['source_id' => $oldId]);
    }

    public function test_does_not_archive_closed_request_history_inside_the_hot_horizon(): void
    {
        $bank = Bank::factory()->create();
        $request = $this->makeRequest('CLOSED', $bank->id);

        $recentId = DB::table('workflow_history')->insertGetId([
            'request_id' => $request->id,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->performer->id,
            'created_at' => now()->subMonth(),
        ]);

        $this->artisan('workflow-history:archive-old')->assertSuccessful();

        $this->assertDatabaseHas('workflow_history', ['id' => $recentId]);
        $this->assertDatabaseMissing('workflow_history_archives', ['source_id' => $recentId]);
    }

    public function test_archive_batch_is_idempotent(): void
    {
        $bank = Bank::factory()->create();
        $request = $this->makeRequest('CLOSED', $bank->id);

        $oldId = DB::table('workflow_history')->insertGetId([
            'request_id' => $request->id,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->performer->id,
            'created_at' => now()->subMonths(13),
        ]);

        $this->artisan('workflow-history:archive-old')->assertSuccessful();
        $this->artisan('workflow-history:archive-old')->assertSuccessful();

        $this->assertSame(1, WorkflowHistoryArchive::query()->where('source_id', $oldId)->count());
    }

    public function test_logs_audit_archived_action_for_batch(): void
    {
        $bank = Bank::factory()->create();
        $request = $this->makeRequest('CLOSED', $bank->id);

        DB::table('workflow_history')->insert([
            'request_id' => $request->id,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->performer->id,
            'created_at' => now()->subMonths(13),
        ]);

        $this->artisan('workflow-history:archive-old')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::WORKFLOW_HISTORY_ARCHIVED->value,
        ]);
    }
}
